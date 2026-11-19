<?php

namespace Aowow;

if (!defined('AOWOW_REVISION'))
    die('illegal access');


/*
 * The Entry requirements tab: one row per `access_requirement` row of the given maps, i.e. per map and
 * difficulty. MapManager::CanPlayerEnter() -> Player::Satisfy() checks it on every way into an instance map,
 * so the zone page of the instance and every areatrigger leading into it show the same rows.
 */
class AccessRequirements
{
    private static function hasTable() : bool
    {
        static $known = null;

        return $known ??= (bool)DB::World()->selectCell('SHOW TABLES LIKE %s', 'access_requirement');
    }

    public static function getListview(TemplateResponse $resp, array $mapIds) : ?Listview
    {
        $mapIds = array_values(array_unique(array_map('intval', $mapIds)));
        if (!$mapIds || !self::hasTable())
            return null;

        $rows = DB::World()->selectAssoc(
           'SELECT `mapId`, `difficulty`, `level_min`, `level_max`, `item_level`, `item`, `item2`, `quest_done_A`, `quest_done_H`, `completed_achievement`, `quest_failed_text`, `comment`
            FROM   access_requirement
            WHERE  `mapId` IN %in
            ORDER BY `mapId`, `difficulty`', $mapIds
        ) ?: [];
        if (!$rows)
            return null;

        // difficulty names only mean something for maps that have more than one; g_zone_instancetypes as set by the zones setup step
        $mapTypes = DB::Aowow()->selectCol('SELECT `mapIdBak` AS ARRAY_KEY, `type` FROM ::zones WHERE `mapIdBak` IN %in AND `parentArea` = 0', $mapIds) ?: [];
        $mapNames = WorldPosition::getMapNames($mapIds);

        $data = [];
        foreach ($rows as $r)
        {
            $mapId = (int)$r['mapId'];
            $row   = array(
                'id'          => $mapId.':'.(int)$r['difficulty'],
                'map'         => $mapNames[$mapId] ?? ('Map #'.$mapId),
                'difficulty'  => (int)$r['difficulty'],
                'mode'        => match ((int)($mapTypes[$mapId] ?? -1))
                {
                    MAP_TYPE_DUNGEON_HC                         => Lang::game('modes', 1, $r['difficulty']),
                    MAP_TYPE_MMODE_RAID, MAP_TYPE_MMODE_RAID_HC => Lang::game('modes', 2, $r['difficulty']),
                    default                                     => ''
                },
                'level'       => '',
                'levelMin'    => (int)$r['level_min'],
                'items'       => '',
                'quest'       => '',
                'achievement' => '',
                'itemLevel'   => (int)$r['item_level'] ?: null,
                'failText'    => trim((string)$r['quest_failed_text']),
                'comment'     => trim((string)$r['comment'])
            );

            if ($r['level_min'] && $r['level_max'])
                $row['level'] = Lang::areatrigger('reqLevelRange', [$r['level_min'], $r['level_max']]);
            else if ($r['level_min'])
                $row['level'] = Lang::areatrigger('reqLevelMin', [$r['level_min']]);
            else if ($r['level_max'])
                $row['level'] = Lang::areatrigger('reqLevelMax', [$r['level_max']]);

            // either of the two items will do
            if ($items = array_values(array_unique(array_filter([(int)$r['item'], (int)$r['item2']]))))
            {
                $resp->extendGlobalIds(Type::ITEM, ...$items);
                $row['items'] = Lang::concat($items, Lang::CONCAT_OR, fn($i) => '[item='.$i.']');
            }

            $qA = (int)$r['quest_done_A'];
            $qH = (int)$r['quest_done_H'];
            if ($qA && $qA == $qH)
            {
                $resp->extendGlobalIds(Type::QUEST, $qA);
                $row['quest'] = '[quest='.$qA.']';
            }
            else
            {
                $q = [];
                if ($qA)
                {
                    $resp->extendGlobalIds(Type::QUEST, $qA);
                    $q[] = '[span class=icon-alliance][quest='.$qA.'][/span]';
                }
                if ($qH)
                {
                    $resp->extendGlobalIds(Type::QUEST, $qH);
                    $q[] = '[span class=icon-horde][quest='.$qH.'][/span]';
                }
                $row['quest'] = implode('[br]', $q);
            }

            // checked on the group leader, not the player entering
            if ($a = (int)$r['completed_achievement'])
            {
                $resp->extendGlobalIds(Type::ACHIEVEMENT, $a);
                $row['achievement'] = Lang::areatrigger('reqAchievement', ['[achievement='.$a.']']);
            }

            $data[] = $row;
        }

        $hidden = [];
        if (count($mapIds) < 2 || count(array_unique(array_column($data, 'map'))) < 2)
            $hidden[] = 'map';

        foreach (['mode', 'level', 'items', 'quest', 'achievement', 'itemLevel', 'failText', 'comment'] as $col)
            if (!array_filter(array_column($data, $col)))
                $hidden[] = $col;

        return new Listview(array(
            'data'       => $data,
            'name'       => Lang::areatrigger('entryReqs'),
            'hiddenCols' => $hidden ?: null
        ), 'accessrequirement', 'accessrequirement');
    }
}

?>
