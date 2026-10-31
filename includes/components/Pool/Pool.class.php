<?php

namespace Aowow;

if (!defined('AOWOW_REVISION'))
    die('illegal access');


/*
 * Spawn pooling.
 *
 * `pool_template` + `pool_members` decide how many of a set of spawn points are live at once.
 * None of it was ever read, so a map drew all 27 points of a herb node or a rare as though every
 * one of them were up simultaneously - which is the single most misleading thing the spawn maps do.
 *
 * `pool_members`.`type` is 0: creature spawn, 1: gameobject spawn, 2: nested pool; `spawnId` is the
 * guid or the child pool, `poolSpawnId` the pool it belongs to (PoolMgr::LoadFromDB).
 *
 * Pooling is per guid, not per entry: the same creature can be pooled at some spawns and not others.
 */
class Pool
{
    private const int MEMBER_CREATURE   = 0;
    private const int MEMBER_GAMEOBJECT = 1;
    private const int MEMBER_POOL       = 2;

    /** @return array poolId => [maxActive, nMembers, nMine, description] for the pools this entry spawns in */
    public static function getForEntry(int $type, int $entry) : array
    {
        if ($entry <= 0)
            return [];

        [$memberType, $spawnTbl] = match ($type)
        {
            Type::NPC    => [self::MEMBER_CREATURE,   'creature'],
            Type::OBJECT => [self::MEMBER_GAMEOBJECT, 'gameobject'],
            default      => [null, null]
        };

        if ($memberType === null || !self::hasTable('pool_members') || !self::hasTable('pool_template'))
            return [];

        // which pools hold a spawn of this entry, and how many of that pool's slots are ours
        $mine = DB::World()->selectAssoc(
           'SELECT   p.`poolSpawnId` AS ARRAY_KEY, COUNT(1) AS "nMine"
            FROM     pool_members p
            JOIN     %n s ON s.`guid` = p.`spawnId`
            WHERE    p.`type` = %i AND s.`id` = %i
            GROUP BY p.`poolSpawnId`',
            $spawnTbl, $memberType, $entry
        ) ?: [];

        if (!$mine)
            return [];

        $info = self::describe(array_keys($mine));

        $out = [];
        foreach ($mine as $pId => $r)
            $out[(int)$pId] = $info[(int)$pId] + ['nMine' => (int)$r['nMine']];

        return $out;
    }

    /** @return array guid => [poolId, maxActive, nMembers, description] for those of the given spawns that are pooled */
    public static function getForGuids(int $type, array $guids) : array
    {
        $memberType = match ($type)
        {
            Type::NPC    => self::MEMBER_CREATURE,
            Type::OBJECT => self::MEMBER_GAMEOBJECT,
            default      => null
        };

        if ($memberType === null || !($guids = array_values(array_filter(array_map('intVal', $guids), fn($x) => $x > 0))) || !self::hasTable('pool_members') || !self::hasTable('pool_template'))
            return [];

        if (!$pools = DB::World()->selectPairs('SELECT `spawnId`, `poolSpawnId` FROM pool_members WHERE `type` = %i AND `spawnId` IN %in', $memberType, $guids))
            return [];

        $info = self::describe(array_unique($pools));

        $out = [];
        foreach ($pools as $guid => $pId)
            $out[(int)$guid] = ['poolId' => (int)$pId] + $info[(int)$pId];

        return $out;
    }

    /** @return array poolId => [maxActive, nMembers, description] */
    private static function describe(array $poolIds) : array
    {
        $poolIds = array_values(array_map('intVal', $poolIds));

        $tpl = DB::World()->selectAssoc('SELECT `entry` AS ARRAY_KEY, `max_limit`, `description` FROM pool_template WHERE `entry` IN %in', $poolIds) ?: [];

        // total members of each pool: spawns of either kind and nested pools, as a mother pool's slots are shared with its children
        $total = DB::World()->selectPairs('SELECT `poolSpawnId`, COUNT(1) FROM pool_members WHERE `poolSpawnId` IN %in GROUP BY `poolSpawnId`', $poolIds) ?: [];

        $out = [];
        foreach ($poolIds as $pId)
            $out[$pId] = array(
                'maxActive'   => (int)($tpl[$pId]['max_limit'] ?? 0),
                'nMembers'    => (int)($total[$pId] ?? 0),
                'description' => (string)($tpl[$pId]['description'] ?? '')
            );

        return $out;
    }

    /** @return array of pool ids: the given pools plus every pool nested below them */
    public static function withChildren(array $poolIds) : array
    {
        if (!($poolIds = array_values(array_filter(array_map('intVal', $poolIds)))) || !self::hasTable('pool_members'))
            return $poolIds;

        $open = $poolIds;
        while ($open)
        {
            $children = array_map('intVal', DB::World()->selectCol('SELECT `spawnId` FROM pool_members WHERE `type` = %i AND `poolSpawnId` IN %in', self::MEMBER_POOL, $open) ?: []);
            $open     = array_values(array_diff($children, $poolIds));
            $poolIds  = array_merge($poolIds, $open);
        }

        return $poolIds;
    }

    /** the other entries sharing these pools, so a page can name what it competes with */
    public static function getMembers(array $poolIds, int $excludeType = 0, int $excludeEntry = 0) : array
    {
        if (!($poolIds = array_values(array_filter(array_map('intVal', $poolIds)))) || !self::hasTable('pool_members'))
            return [];

        $out = [];
        foreach ([[Type::NPC, self::MEMBER_CREATURE, 'creature'], [Type::OBJECT, self::MEMBER_GAMEOBJECT, 'gameobject']] as [$type, $memberType, $spawnTbl])
        {
            foreach (DB::World()->selectCol(
               'SELECT DISTINCT s.`id` FROM pool_members p JOIN %n s ON s.`guid` = p.`spawnId` WHERE p.`type` = %i AND p.`poolSpawnId` IN %in',
                $spawnTbl, $memberType, $poolIds) ?: [] as $id)
            {
                $id = (int)$id;
                if ($id > 0 && !($type == $excludeType && $id == $excludeEntry))
                    $out[$type][$id] = $id;
            }
        }

        return $out;
    }

    private static function hasTable(string $tbl) : bool
    {
        static $known = [];

        return $known[$tbl] ??= (bool)DB::World()->selectCell('SHOW TABLES LIKE %s', $tbl);
    }
}

?>
