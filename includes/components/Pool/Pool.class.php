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

    /** @return array poolId => [maxActive, nMembers, nMine, description, respawn: [min, max], areas: [areaId => n]] for the pools this entry spawns in */
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

        // this entry's pooled spawns
        $mine = DB::World()->selectAssoc(
           'SELECT p.`spawnId` AS ARRAY_KEY, p.`poolSpawnId` AS "poolId", s.`spawntimesecs` AS "respawn"
            FROM   pool_members p
            JOIN   %n s ON s.`guid` = p.`spawnId`
            WHERE  p.`type` = %i AND s.`id` = %i',
            $spawnTbl, $memberType, $entry
        ) ?: [];

        if (!$mine)
            return [];

        $poolIds = array_values(array_unique(array_map('intVal', array_column($mine, 'poolId'))));

        $tpl = DB::World()->selectAssoc('SELECT `entry` AS ARRAY_KEY, `max_limit`, `description` FROM pool_template WHERE `entry` IN %in', $poolIds) ?: [];

        // total members of each pool: spawns of either kind and nested pools, as a mother pool's slots are shared with its children
        $total = DB::World()->selectPairs('SELECT `poolSpawnId`, COUNT(1) FROM pool_members WHERE `poolSpawnId` IN %in GROUP BY `poolSpawnId`', $poolIds) ?: [];

        // the world spawn's zoneId is mostly unset, ::spawns has the area resolved from the position
        $areas = DB::Aowow()->selectPairs('SELECT `guid`, `areaId` FROM ::spawns WHERE `type` = %i AND `guid` IN %in', $type, array_keys($mine)) ?: [];

        $out = [];
        foreach ($poolIds as $pId)
            $out[$pId] = array(
                'maxActive'   => (int)($tpl[$pId]['max_limit'] ?? 0),
                'nMembers'    => (int)($total[$pId] ?? 0),
                'nMine'       => 0,
                'description' => (string)($tpl[$pId]['description'] ?? ''),
                'respawn'     => [PHP_INT_MAX, 0],
                'areas'       => []
            );

        foreach ($mine as $guid => $m)
        {
            $p = &$out[(int)$m['poolId']];
            $p['nMine']++;
            $p['respawn'] = [min($p['respawn'][0], (int)$m['respawn']), max($p['respawn'][1], (int)$m['respawn'])];
            if ($a = (int)($areas[$guid] ?? 0))
                $p['areas'][$a] = ($p['areas'][$a] ?? 0) + 1;
        }
        unset($p);

        foreach ($out as &$p)
            arsort($p['areas']);
        unset($p);

        return $out;
    }

    /** rows for the `spawnpool` listview from the result of getForEntry() */
    public static function getListviewData(array $pools) : array
    {
        $data = [];
        foreach ($pools as $pId => $p)
            $data[$pId] = array(
                'id'       => $pId,
                'name'     => $p['description'],
                'location' => array_keys($p['areas']),
                'active'   => $p['maxActive'],
                'total'    => $p['nMembers'],
                'mine'     => $p['nMine'],
                'respawn'  => $p['respawn']
            );

        return $data;
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
