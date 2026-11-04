<?php

namespace Aowow;

if (!defined('AOWOW_REVISION'))
    die('illegal access');


/*
 * aowow - custom
 * which of the graveyards `graveyard_zone` links to a zone the core actually sends a ghost to
 *
 * ObjectMgr::GetClosestGraveyard() looks at the links of the ghost's zone that are open to its team and
 *   1. takes the nearest graveyard on the map the ghost died on, else
 *   2. the one nearest the map's ghost entrance (Map.dbc CorpseMapID / Corpse) on the entrance map, else
 *   3. any other one
 * so a link to an instance zone loses for good to a graveyard inside the instance or one nearer the entrance.
 * Battlegrounds and arenas pick their graveyards in their own scripts and are left alone.
 */
abstract class Graveyard
{
    public const int SIDE_ALLIANCE = 0x1;
    public const int SIDE_HORDE    = 0x2;

    private const array TEAMS = [self::SIDE_ALLIANCE => 469, self::SIDE_HORDE => 67];

    private static array $linkCache = [];
    private static array $locCache  = [];

    /**
     * the graveyards the core picks instead of $graveyardId for the ghosts of $zoneId
     * @return array [side => graveyardId | 0] - 0 when the pick depends on where the ghost died; sides the link is used for or not open to are absent
     */
    public static function supersededBy(int $graveyardId, int $zoneId) : array
    {
        $links = self::getLinks($zoneId);
        if (!isset($links[$graveyardId]))
            return [];

        $zone = DB::Aowow()->selectRow(
           'SELECT m.`id`, m.`areaType`, m.`parentMapId`, m.`parentX`, m.`parentY`
            FROM   ::zones z JOIN dbc_map m ON m.`id` = z.`mapId`
            WHERE  z.`id` = %i', $zoneId
        );
        if (!$zone || in_array((int)$zone['areaType'], [3, 4]))    // MAP_BATTLEGROUND, MAP_ARENA
            return [];

        $locs  = self::getLocations(array_keys($links));
        $mapId = (int)$zone['id'];
        $out   = [];

        foreach (self::TEAMS as $side => $team)
        {
            $faction = $links[$graveyardId];
            if ($faction && $faction != $team)
                continue;

            // graveyards without a WorldSafeLocs entry are skipped by the core
            $cands = array_filter($links, fn($f, $id) => (!$f || $f == $team) && isset($locs[$id]), ARRAY_FILTER_USE_BOTH);
            if (!isset($cands[$graveyardId]))
                continue;

            $near = array_filter(array_keys($cands), fn($id) => $locs[$id]['mapId'] == $mapId);
            if ($near)
            {
                if (!in_array($graveyardId, $near))
                    $out[$side] = count($near) == 1 ? reset($near) : 0;

                continue;
            }

            if ((int)$zone['parentMapId'] < 0 || (!(float)$zone['parentX'] && !(float)$zone['parentY']))
                continue;

            $best = null;
            $dist = 0.0;
            foreach (array_keys($cands) as $id)
            {
                if ($locs[$id]['mapId'] != (int)$zone['parentMapId'])
                    continue;

                $d = ($locs[$id]['posX'] - (float)$zone['parentX']) ** 2 + ($locs[$id]['posY'] - (float)$zone['parentY']) ** 2;
                if ($best === null || $d < $dist)
                    [$best, $dist] = [$id, $d];
            }

            if ($best !== null && $best != $graveyardId)
                $out[$side] = $best;
        }

        return $out;
    }

    /** @return array [graveyardId => faction] faction: 469 Alliance, 67 Horde, 0 any */
    private static function getLinks(int $zoneId) : array
    {
        if (isset(self::$linkCache[$zoneId]))
            return self::$linkCache[$zoneId];

        $links = [];
        foreach (DB::World()->selectAssoc('SELECT `ID`, `Faction` FROM graveyard_zone WHERE `GhostZone` = %i', $zoneId) ?: [] as $r)
            $links[(int)$r['ID']] = (int)$r['Faction'];

        return self::$linkCache[$zoneId] = $links;
    }

    /** @return array [graveyardId => [mapId, posX, posY]] */
    private static function getLocations(array $graveyardIds) : array
    {
        if ($missing = array_values(array_diff($graveyardIds, array_keys(self::$locCache))))
        {
            foreach ($missing as $id)
                self::$locCache[$id] = null;

            foreach (DB::Aowow()->selectAssoc('SELECT `id` AS ARRAY_KEY, `mapId`, `posX`, `posY` FROM dbc_worldsafelocs WHERE `id` IN %in', $missing) ?: [] as $id => $r)
                self::$locCache[$id] = ['mapId' => (int)$r['mapId'], 'posX' => (float)$r['posX'], 'posY' => (float)$r['posY']];
        }

        return array_filter(array_intersect_key(self::$locCache, array_flip($graveyardIds)));
    }
    /**
     * listview rows of the `graveyard` template, out of the link table and, on cores that still ship it, `game_graveyard`
     *
     * @param  ?int[] $graveyardIds  only these graveyards (null: all of them)
     * @param  ?int[] $zoneIds       out: the zones the rows name, for the page's globals
     * @return array                 [row, ...]
     */
    public static function getListviewData(?array $graveyardIds = null, ?array &$zoneIds = []) : array
    {
        $zoneIds = [];
        $zoneTbl = self::hasTable('graveyard_zone')
            ? 'graveyard_zone'
            : (self::hasTable('game_graveyard_zone') ? 'game_graveyard_zone' : null);

        if (!$zoneTbl || $graveyardIds === [])
            return [];

        // [graveyardId => [zones => [zoneId => true], ally => bool, horde => bool, comment => string]]
        $links = [];
        $byId  = $graveyardIds === null ? [] : [' WHERE `ID` IN %in', $graveyardIds];
        foreach (DB::World()->selectAssoc('SELECT * FROM '.$zoneTbl.($byId[0] ?? ''), ...array_slice($byId, 1)) ?: [] as $r)
        {
            $lc = array_change_key_case($r, CASE_LOWER);
            $id = (int)($lc['id'] ?? 0);
            if (!$id)
                continue;

            $zone = (int)($lc['ghostzone'] ?? $lc['ghost_zone'] ?? 0);
            $links[$id]['zones'][$zone] = true;

            // the link table stores the faction template id: 469 Alliance, 67 Horde, 0 any
            $faction = (int)($lc['faction'] ?? 0);
            if ($faction == self::TEAMS[self::SIDE_ALLIANCE])
            {
                $links[$id]['ally'] = true;
                $links[$id]['sides'][$zone] = ($links[$id]['sides'][$zone] ?? 0) | self::SIDE_ALLIANCE;
            }
            else if ($faction == self::TEAMS[self::SIDE_HORDE])
            {
                $links[$id]['horde'] = true;
                $links[$id]['sides'][$zone] = ($links[$id]['sides'][$zone] ?? 0) | self::SIDE_HORDE;
            }

            $comment = trim((string)($lc['comment'] ?? ''));
            if ($comment !== '')
                $links[$id]['comment'] = $comment;
        }

        // optional position table on cores that still ship it; its name and map win where present
        $positions = [];
        if (self::hasTable('game_graveyard'))
        {
            foreach (DB::World()->selectAssoc('SELECT * FROM game_graveyard'.($byId[0] ?? ''), ...array_slice($byId, 1)) ?: [] as $r)
            {
                $lc = array_change_key_case($r, CASE_LOWER);
                $id = (int)($lc['id'] ?? 0);

                $positions[$id] = array(
                    'name' => trim((string)($lc['comment'] ?? $lc['name'] ?? '')),
                    'map'  => (int)($lc['map'] ?? $lc['mapid'] ?? $lc['map_id'] ?? 0)
                );
            }
        }

        $ids = array_keys($links);
        sort($ids);

        // the link table has no map column, so the map is taken from the first ghost zone
        foreach ($links as $link)
            foreach (array_keys($link['zones']) as $z)
                $zoneIds[$z] = $z;

        $zoneIds   = array_values($zoneIds);
        $mapByZone = [];

        if ($zoneIds)
            foreach (DB::Aowow()->selectAssoc('SELECT `id` AS ARRAY_KEY, `mapId` FROM ::zones WHERE `id` IN %in', $zoneIds) ?: [] as $zId => $zRow)
                $mapByZone[(int)$zId] = (int)$zRow['mapId'];

        $rows   = [];
        $mapIds = [];
        foreach ($ids as $id)
        {
            $link  = $links[$id];
            $zones = array_keys($link['zones']);
            sort($zones);

            $pos  = $positions[$id] ?? null;
            $name = $pos && $pos['name'] !== '' ? $pos['name'] : (string)($link['comment'] ?? '');
            $map  = $pos && $pos['map'] ? $pos['map'] : (isset($zones[0]) ? ($mapByZone[$zones[0]] ?? 0) : 0);

            $faction = 0;
            if (!empty($link['ally']))
                $faction |= self::SIDE_ALLIANCE;
            if (!empty($link['horde']))
                $faction |= self::SIDE_HORDE;

            $mapIds[$map] = $map;

            $row = array(
                'id'       => $id,
                'name'     => $name === '' ? Lang::zone('graveyardUnnamed', [$id]) : ($name[0] == '$' ? ' '.$name : $name),
                'map'      => $map,
                'mapLink'  => isset($zones[0]) ? '?maps='.$zones[0] : '',
                'zones'    => $zones,
                'location' => $zones,
                'faction'  => $faction
            );

            // the side(s) a zone's link is restricted to; a zone left out is open to both, the label above only sums them up
            if (!empty($link['sides']))
                $row['sides'] = $link['sides'];

            $rows[$id] = $row;
        }

        $mapNameByMap = WorldPosition::getMapNames(array_values($mapIds));
        foreach ($rows as &$row)
            $row['mapName'] = $mapNameByMap[$row['map']] ?? '';

        return array_values($rows);
    }

    private static function hasTable(string $tbl) : bool
    {
        static $known = [];

        return $known[$tbl] ??= (bool)DB::World()->selectCell('SHOW TABLES LIKE %s', $tbl);
    }
}

?>
