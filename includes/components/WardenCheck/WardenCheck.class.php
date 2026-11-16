<?php

namespace Aowow;

if (!defined('AOWOW_REVISION'))
    die('illegal access');


/*
 * `warden_checks` - the anti-cheat probes the core sends a client (WardenCheckMgr / WardenWin).
 *
 * Every row is one probe: a memory read, a page or module scan, a file hash, a Lua snippet. Which
 * columns mean anything depends on the type - WardenCheckMgr::LoadWardenChecks() reads `data`,
 * `result`, `address`, `length` and `str` only for the types that use them - so a row is described
 * through that same table here rather than by dumping every column.
 *
 * What happens when a check fails is not in the world DB: it is Warden.ClientCheckFailAction in
 * worldserver.conf, overridden per check by `warden_action` in each realm's characters DB.
 */
class WardenCheck
{
    // enum WardenCheckType
    public const int TIMING_CHECK   = 87;
    public const int DRIVER_CHECK   = 113;
    public const int PROC_CHECK     = 126;
    public const int LUA_EVAL_CHECK = 139;
    public const int MPQ_CHECK      = 152;
    public const int PAGE_CHECK_A   = 178;
    public const int PAGE_CHECK_B   = 191;
    public const int MODULE_CHECK   = 217;
    public const int MEM_CHECK      = 243;

    // enum WardenCheckCategory; null: a type the core does not support and skips while loading
    public const int CAT_INJECT = 0;
    public const int CAT_LUA    = 1;
    public const int CAT_MODDED = 2;

    public const int MAX_LUA_LENGTH = 170;                  // WARDEN_MAX_LUA_CHECK_LENGTH
    public const int MAX_LUA_ID     = 9999;                 // the id is sent as four digits

    // enum WardenActions
    public const int ACTION_LOG  = 0;
    public const int ACTION_KICK = 1;
    public const int ACTION_BAN  = 2;

    /** type => [name, category, columns WardenCheckMgr reads for it] */
    private const array TYPES = array(
        self::TIMING_CHECK   => ['TIMING_CHECK',   null,             []                            ],
        self::DRIVER_CHECK   => ['DRIVER_CHECK',   self::CAT_INJECT, ['data', 'str']               ],
        self::PROC_CHECK     => ['PROC_CHECK',     null,             []                            ],
        self::LUA_EVAL_CHECK => ['LUA_EVAL_CHECK', self::CAT_LUA,    ['str']                       ],
        self::MPQ_CHECK      => ['MPQ_CHECK',      self::CAT_MODDED, ['str', 'result']             ],
        self::PAGE_CHECK_A   => ['PAGE_CHECK_A',   self::CAT_INJECT, ['data', 'address', 'length'] ],
        self::PAGE_CHECK_B   => ['PAGE_CHECK_B',   self::CAT_INJECT, ['data', 'address', 'length'] ],
        self::MODULE_CHECK   => ['MODULE_CHECK',   self::CAT_INJECT, ['str']                       ],
        self::MEM_CHECK      => ['MEM_CHECK',      self::CAT_MODDED, ['str', 'address', 'result']  ]
    );

    public static function hasTable() : bool
    {
        static $has = null;

        return $has ??= (bool)DB::World()->selectCell('SHOW TABLES LIKE %s', 'warden_checks');
    }

    public static function typeName(int $type) : string
    {
        return self::TYPES[$type][0] ?? ('#'.$type);
    }

    public static function category(int $type) : ?int
    {
        return self::TYPES[$type][1] ?? null;
    }

    /** @return string[] the columns the core reads for a check of this type */
    public static function usedColumns(int $type) : array
    {
        return self::TYPES[$type][2] ?? [];
    }

    /** @return array<int, string> type => name, for the types the listing can be narrowed to */
    public static function typeNames() : array
    {
        return array_map(fn($x) => $x[0], self::TYPES);
    }

    /**
     * the rows as the pages use them: binary columns as hex, every other column as read
     *
     * @return array[] id => row
     */
    public static function getRows(?int $id = null) : array
    {
        if (!self::hasTable())
            return [];

        $rows = DB::World()->selectAssoc(
           'SELECT `id` AS ARRAY_KEY, `id`, `type`, `str`, `address`, `length`, `comment`, HEX(`data`) AS "data", HEX(`result`) AS "result"
            FROM   warden_checks
            %if',  $id !== null, 'WHERE `id` = %i', $id, '%end
            ORDER BY `id` ASC'
        ) ?: [];

        foreach ($rows as &$r)
        {
            $r['id']      = (int)$r['id'];
            $r['type']    = (int)$r['type'];
            $r['address'] = $r['address'] !== null ? (int)$r['address'] : null;
            $r['length']  = $r['length']  !== null ? (int)$r['length']  : null;
            $r['str']     = (string)($r['str'] ?? '');
            $r['comment'] = trim((string)($r['comment'] ?? ''));
            $r['data']    = (string)($r['data']   ?? '');
            $r['result']  = (string)($r['result'] ?? '');
        }

        return $rows;
    }

    /**
     * why WardenCheckMgr::LoadWardenChecks() would skip this row; null if it loads
     */
    public static function skipReason(array $row) : ?string
    {
        if (self::category($row['type']) === null)
            return Lang::warden('skipType');

        if ($row['type'] == self::LUA_EVAL_CHECK && $row['id'] > self::MAX_LUA_ID)
            return Lang::warden('skipLuaId', [self::MAX_LUA_ID]);

        if ($row['type'] == self::LUA_EVAL_CHECK && strlen($row['str']) > self::MAX_LUA_LENGTH)
            return Lang::warden('skipLuaLength', [self::MAX_LUA_LENGTH]);

        return null;
    }

    /**
     * the main column of a check - what it looks at - as plain text
     * a memory or page check without a module looks at the client's own executable
     */
    public static function target(array $row) : string
    {
        return match ($row['type'])
        {
            self::MEM_CHECK                         => ($row['str'] !== '' ? $row['str'] : 'Wow.exe').' @ 0x'.strtoupper(dechex((int)$row['address'])).' ('.(strlen($row['result']) / 2).' B)',
            self::PAGE_CHECK_A, self::PAGE_CHECK_B  => '0x'.strtoupper(dechex((int)$row['address'])).' ('.(int)$row['length'].' B)',
            self::MPQ_CHECK, self::MODULE_CHECK,
            self::DRIVER_CHECK, self::LUA_EVAL_CHECK => $row['str'],
            default                                 => ''
        };
    }

    /**
     * `warden_action` of every realm whose characters DB is configured
     *
     * @return array  checkId => [realm name => WardenActions]
     */
    public static function actionOverrides() : array
    {
        $out = [];
        foreach (Profiler::getRealms() as $rId => $realm)
        {
            if (!DB::Characters($rId)->selectCell('SHOW TABLES LIKE %s', 'warden_action'))
                continue;

            foreach (DB::Characters($rId)->selectAssoc('SELECT `wardenId` AS "0", `action` AS "1" FROM warden_action') ?: [] as [$check, $action])
                $out[(int)$check][Util::ucWords($realm['name'])] = (int)$action;
        }

        return $out;
    }
}

?>
