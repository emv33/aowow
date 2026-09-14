<?php

namespace Aowow;

if (!defined('AOWOW_REVISION'))
    die('illegal access');


/*
 * Spawn pooling.
 *
 * `pool_template` + `pool_creature` / `pool_gameobject` / `pool_pool` decide how many of a set of
 * spawn points are live at once. None of it was ever read, so a map drew all 27 points of a herb
 * node or a rare as though every one of them were up simultaneously - which is the single most
 * misleading thing the spawn maps do.
 *
 * Pooling is per guid, not per entry: the same creature can be pooled at some spawns and not others.
 */
class Pool
{
    /** @return array poolId => [maxActive, nMembers, nMine, description] for the pools this entry spawns in */
    public static function getForEntry(int $type, int $entry) : array
    {
        if ($entry <= 0)
            return [];

        [$poolTbl, $spawnTbl] = match ($type)
        {
            Type::NPC    => ['pool_creature',   'creature'],
            Type::OBJECT => ['pool_gameobject', 'gameobject'],
            default      => [null, null]
        };

        if (!$poolTbl || !self::hasTable($poolTbl) || !self::hasTable('pool_template'))
            return [];

        // which pools hold a spawn of this entry, and how many of that pool's slots are ours
        $mine = DB::World()->selectAssoc(
           'SELECT   p.`pool_entry` AS ARRAY_KEY, p.`pool_entry`, COUNT(1) AS "nMine"
            FROM     %n p
            JOIN     %n s ON s.`guid` = p.`guid`
            WHERE    s.`id` = %i
            GROUP BY p.`pool_entry`',
            $poolTbl, $spawnTbl, $entry
        ) ?: [];

        if (!$mine)
            return [];

        $poolIds = array_map('intVal', array_keys($mine));

        $tpl = DB::World()->selectAssoc('SELECT `entry` AS ARRAY_KEY, `max_limit`, `description` FROM pool_template WHERE `entry` IN %in', $poolIds) ?: [];

        // total members of each pool, counting both spawn tables and any nested pools
        $total = [];
        foreach (['pool_creature', 'pool_gameobject'] as $t)
        {
            if (!self::hasTable($t))
                continue;

            foreach (DB::World()->selectAssoc('SELECT `pool_entry` AS ARRAY_KEY, COUNT(1) AS "n" FROM %n WHERE `pool_entry` IN %in GROUP BY `pool_entry`', $t, $poolIds) ?: [] as $pId => $r)
                $total[(int)$pId] = ($total[(int)$pId] ?? 0) + (int)$r['n'];
        }

        if (self::hasTable('pool_pool'))
            foreach (DB::World()->selectAssoc('SELECT `mother_pool` AS ARRAY_KEY, COUNT(1) AS "n" FROM pool_pool WHERE `mother_pool` IN %in GROUP BY `mother_pool`', $poolIds) ?: [] as $pId => $r)
                $total[(int)$pId] = ($total[(int)$pId] ?? 0) + (int)$r['n'];

        $out = [];
        foreach ($mine as $pId => $r)
        {
            $pId = (int)$pId;
            $out[$pId] = array(
                'maxActive'   => (int)($tpl[$pId]['max_limit'] ?? 0),
                'nMembers'    => $total[$pId] ?? 0,
                'nMine'       => (int)$r['nMine'],
                'description' => (string)($tpl[$pId]['description'] ?? '')
            );
        }

        return $out;
    }

    /** the other entries sharing these pools, so a page can name what it competes with */
    public static function getMembers(array $poolIds, int $excludeType = 0, int $excludeEntry = 0) : array
    {
        if (!$poolIds = array_values(array_filter(array_map('intVal', $poolIds))))
            return [];

        $out = [];
        foreach ([[Type::NPC, 'pool_creature', 'creature'], [Type::OBJECT, 'pool_gameobject', 'gameobject']] as [$type, $poolTbl, $spawnTbl])
        {
            if (!self::hasTable($poolTbl))
                continue;

            foreach (DB::World()->selectCol(
               'SELECT DISTINCT s.`id` FROM %n p JOIN %n s ON s.`guid` = p.`guid` WHERE p.`pool_entry` IN %in',
                $poolTbl, $spawnTbl, $poolIds) ?: [] as $id)
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
