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
 * guid or the child pool, `poolSpawnId` the pool it belongs to (PoolMgr::LoadFromDB). A spawn that
 * despawns is not respawned in place: once its respawn time is up, PoolGroup::SpawnObject spawns a
 * random inactive member of the pool instead.
 *
 * Pooling is per guid, not per entry: the same creature can be pooled at some spawns and not others.
 */
class Pool
{
    private const int MEMBER_CREATURE   = 0;
    private const int MEMBER_GAMEOBJECT = 1;
    private const int MEMBER_POOL       = 2;

    private const array MEMBER_TYPES = array(               // pool_members.type => [Type, spawn table]
        self::MEMBER_CREATURE   => [Type::NPC,    'creature'],
        self::MEMBER_GAMEOBJECT => [Type::OBJECT, 'gameobject']
    );

    public static function isAvailable() : bool
    {
        return self::hasTable('pool_members') && self::hasTable('pool_template');
    }

    /**
     * @param  ?array $poolIds  null for every pool
     * @return array  poolId => [id, name, active, total, creatures, objects, pools, mother, location, respawn]
     *                location: areaIds by spawn count, of the pool's own spawns and those of its child pools
     *                respawn:  [min, max] respawn time of those spawns, or null if it has none
     */
    public static function getRows(?array $poolIds = null) : array
    {
        if (!self::isAvailable() || ($poolIds !== null && !($poolIds = array_values(array_filter(array_map('intVal', $poolIds))))))
            return [];

        $tpl = $poolIds === null
             ? DB::World()->selectAssoc('SELECT `entry` AS ARRAY_KEY, `max_limit`, `description` FROM pool_template')
             : DB::World()->selectAssoc('SELECT `entry` AS ARRAY_KEY, `max_limit`, `description` FROM pool_template WHERE `entry` IN %in', $poolIds);

        if (!$tpl)
            return [];

        // child pools contribute their zones and respawn times to the mother, so they are read as well
        $allIds  = self::withChildren(array_keys($tpl));
        $members = DB::World()->selectAssoc('SELECT `type`, `spawnId`, `poolSpawnId` FROM pool_members WHERE `poolSpawnId` IN %in', $allIds) ?: [];

        $mothers = DB::World()->selectPairs('SELECT `spawnId`, `poolSpawnId` FROM pool_members WHERE `type` = %i AND `spawnId` IN %in', self::MEMBER_POOL, array_keys($tpl)) ?: [];

        $byPool = [];                                       // poolId => [n: count by member type, guids: by member type, children: pool ids]
        $guids  = [];                                       // member type => guids
        foreach ($members as $m)
        {
            $pId = (int)$m['poolSpawnId'];
            $t   = (int)$m['type'];
            $id  = (int)$m['spawnId'];

            $byPool[$pId] ??= ['n' => [], 'guids' => [], 'children' => []];
            $byPool[$pId]['n'][$t] = ($byPool[$pId]['n'][$t] ?? 0) + 1;

            if ($t == self::MEMBER_POOL)
                $byPool[$pId]['children'][] = $id;
            else if (isset(self::MEMBER_TYPES[$t]))
            {
                $byPool[$pId]['guids'][$t][] = $id;
                $guids[$t][] = $id;
            }
        }

        $respawn = $areas = [];                             // member type => guid => respawn time / areaId
        foreach ($guids as $t => $g)
        {
            [$type, $spawnTbl] = self::MEMBER_TYPES[$t];
            $respawn[$t] = DB::World()->selectPairs('SELECT `guid`, `spawntimesecs` FROM %n WHERE `guid` IN %in', $spawnTbl, $g) ?: [];
            // the world spawn's zoneId is mostly unset, ::spawns has the area resolved from the position
            $areas[$t]   = DB::Aowow()->selectPairs('SELECT `guid`, `areaId` FROM ::spawns WHERE `type` = %i AND `guid` IN %in', $type, $g) ?: [];
        }

        // zones and respawn times of a pool and everything below it
        $collect = function (int $pId, array &$seen) use (&$collect, $byPool, $respawn, $areas) : array
        {
            $loc = [];
            $rsp = null;
            if (isset($seen[$pId]) || !isset($byPool[$pId]))
                return [$loc, $rsp];

            $seen[$pId] = 1;

            foreach ($byPool[$pId]['guids'] as $t => $g)
            {
                foreach ($g as $guid)
                {
                    if ($a = (int)($areas[$t][$guid] ?? 0))
                        $loc[$a] = ($loc[$a] ?? 0) + 1;

                    if (isset($respawn[$t][$guid]))
                    {
                        $r   = (int)$respawn[$t][$guid];
                        $rsp = $rsp ? [min($rsp[0], $r), max($rsp[1], $r)] : [$r, $r];
                    }
                }
            }

            foreach ($byPool[$pId]['children'] as $child)
            {
                [$cLoc, $cRsp] = $collect($child, $seen);
                foreach ($cLoc as $a => $n)
                    $loc[$a] = ($loc[$a] ?? 0) + $n;

                if ($cRsp)
                    $rsp = $rsp ? [min($rsp[0], $cRsp[0]), max($rsp[1], $cRsp[1])] : $cRsp;
            }

            return [$loc, $rsp];
        };

        $out = [];
        foreach ($tpl as $pId => $t)
        {
            $pId  = (int)$pId;
            $n    = $byPool[$pId]['n'] ?? [];
            $seen = [];

            [$loc, $rsp] = $collect($pId, $seen);
            arsort($loc);

            $out[$pId] = array(
                'id'        => $pId,
                'name'      => (string)$t['description'],
                'active'    => (int)$t['max_limit'],
                'total'     => array_sum($n),
                'creatures' => $n[self::MEMBER_CREATURE]   ?? 0,
                'objects'   => $n[self::MEMBER_GAMEOBJECT] ?? 0,
                'pools'     => $n[self::MEMBER_POOL]       ?? 0,
                'mother'    => (int)($mothers[$pId] ?? 0),
                'location'  => array_keys($loc),
                'respawn'   => $rsp
            );
        }

        return $out;
    }

    /** @return array poolId => row of getRows() + mine: the number of this entry's spawn points in the pool */
    public static function getForEntry(int $type, int $entry) : array
    {
        if ($entry <= 0 || !self::isAvailable())
            return [];

        $memberType = null;
        foreach (self::MEMBER_TYPES as $t => [$ty, ])
            if ($ty == $type)
                $memberType = $t;

        if ($memberType === null)
            return [];

        $mine = DB::World()->selectPairs(
           'SELECT   p.`poolSpawnId`, COUNT(1)
            FROM     pool_members p
            JOIN     %n s ON s.`guid` = p.`spawnId`
            WHERE    p.`type` = %i AND s.`id` = %i
            GROUP BY p.`poolSpawnId`',
            self::MEMBER_TYPES[$memberType][1], $memberType, $entry
        );

        if (!$mine)
            return [];

        $rows = self::getRows(array_keys($mine));
        foreach ($rows as $pId => &$r)
            $r['mine'] = (int)$mine[$pId];

        return $rows;
    }

    /** @return array [Type, guid, entry, chance] for each spawn directly in this pool */
    public static function getSpawns(int $poolId) : array
    {
        if (!self::isAvailable())
            return [];

        $out = [];
        foreach (self::MEMBER_TYPES as $t => [$type, $spawnTbl])
            foreach (DB::World()->selectAssoc(
               'SELECT p.`spawnId`, s.`id`, p.`chance` FROM pool_members p JOIN %n s ON s.`guid` = p.`spawnId` WHERE p.`type` = %i AND p.`poolSpawnId` = %i',
                $spawnTbl, $t, $poolId) ?: [] as $r)
                $out[] = [$type, (int)$r['spawnId'], (int)$r['id'], (float)$r['chance']];

        return $out;
    }

    /** @return int[] the pools directly nested in this pool */
    public static function getChildren(int $poolId) : array
    {
        if (!self::isAvailable())
            return [];

        return array_map('intVal', DB::World()->selectCol('SELECT `spawnId` FROM pool_members WHERE `type` = %i AND `poolSpawnId` = %i', self::MEMBER_POOL, $poolId) ?: []);
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
        foreach (self::MEMBER_TYPES as $memberType => [$type, $spawnTbl])
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
