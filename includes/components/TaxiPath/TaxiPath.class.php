<?php

namespace Aowow;

if (!defined('AOWOW_REVISION'))
    die('illegal access');


/*
 * Flight paths.
 *
 * `::taxipath` and `::taxinodes` are written by the `taxi` setup step and were already read in four
 * places - the flight master maps on a zone page, SPELL_EFFECT_SEND_TAXI, SMART_ACTION_ACTIVATE_TAXI
 * and the route of a MO_TRANSPORT - but a path itself had no page, so none of those could link to it
 * and there was no way to ask what triggers a given flight.
 *
 * Both tables live in the aowow DB; only the SmartAI and gameobject lookups touch the world DB.
 */
class TaxiPath
{
    public const int SRC_FLIGHTMASTER = 1;                  // the node itself is an NPC a player talks to
    public const int SRC_SPELL        = 2;                  // SPELL_EFFECT_SEND_TAXI
    public const int SRC_SMARTAI      = 3;                  // SMART_ACTION_ACTIVATE_TAXI
    public const int SRC_OBJECT       = 4;                  // a MO_TRANSPORT running the path

    /**
     * @param  array $pathIds  empty for every path
     * @return array           pathId => [from, fromArea, fromNpc, fromAreaX, fromAreaY, to, toArea, toNpc, toAreaX, toAreaY, mapId]
     */
    public static function getPaths(array $pathIds = []) : array
    {
        $loc  = Lang::getLocale()->value;
        $sql  = 'SELECT   tp.`id` AS ARRAY_KEY, tp.`id`,
                          n1.`name_loc0` AS "from0", n1.`name_loc'.$loc.'` AS "fromLoc", n1.`areaId` AS "fromArea", n1.`type` AS "fromType", n1.`typeId` AS "fromTypeId", n1.`mapId`, n1.`areaX` AS "fromAreaX", n1.`areaY` AS "fromAreaY",
                          n2.`name_loc0` AS "to0",   n2.`name_loc'.$loc.'` AS "toLoc",   n2.`areaId` AS "toArea",   n2.`type` AS "toType",   n2.`typeId` AS "toTypeId",             n2.`areaX` AS "toAreaX",   n2.`areaY` AS "toAreaY"
                 FROM     ::taxipath tp
                 JOIN     ::taxinodes n1 ON n1.`id` = tp.`startNodeId`
                 JOIN     ::taxinodes n2 ON n2.`id` = tp.`endNodeId`';

        // no id list means every path; keep the placeholder out of the query rather than faking one
        $args = $pathIds ? [$sql.' WHERE tp.`id` IN %in ORDER BY tp.`id` ASC', $pathIds]
                         : [$sql.' ORDER BY tp.`id` ASC'];

        $rows = DB::Aowow()->selectAssoc(...$args) ?: [];

        $out = [];
        foreach ($rows as $id => $r)
            $out[(int)$id] = array(
                'id'        => (int)$id,
                'from'      => (string)($r['fromLoc'] ?: $r['from0']),
                'fromArea'  => (int)$r['fromArea'],
                'fromNpc'   => $r['fromType'] == 'NPC' ? (int)$r['fromTypeId'] : 0,
                'fromAreaX' => (float)$r['fromAreaX'],
                'fromAreaY' => (float)$r['fromAreaY'],
                'to'        => (string)($r['toLoc'] ?: $r['to0']),
                'toArea'    => (int)$r['toArea'],
                'toNpc'     => $r['toType'] == 'NPC' ? (int)$r['toTypeId'] : 0,
                'toAreaX'   => (float)$r['toAreaX'],
                'toAreaY'   => (float)$r['toAreaY'],
                'mapId'     => (int)$r['mapId']
            );

        return $out;
    }

    /**
     * everything that can put a player on a given flight
     *
     * @return array pathId => [SRC_* => [ids]]; the SmartAI entry is [srcType, entryorguid]
     */
    public static function getSources(array $pathIds) : array
    {
        if (!$pathIds = array_values(array_filter(array_map('intVal', $pathIds))))
            return [];

        $out = [];

        // the flight master standing on the start node
        foreach (self::getPaths($pathIds) as $id => $p)
            if ($p['fromNpc'])
                $out[$id][self::SRC_FLIGHTMASTER][$p['fromNpc']] = $p['fromNpc'];

        // SPELL_EFFECT_SEND_TAXI - the path id is the effect's misc value
        $eff = [];
        for ($i = 1; $i <= 3; $i++)
            $eff[] = ['(`effect'.$i.'Id` = %i AND `effect'.$i.'MiscValue` IN %in)', SPELL_EFFECT_SEND_TAXI, $pathIds];

        foreach (DB::Aowow()->selectAssoc(
           'SELECT `id`, `effect1Id`, `effect1MiscValue`, `effect2Id`, `effect2MiscValue`, `effect3Id`, `effect3MiscValue`
            FROM   ::spell WHERE %or', $eff) ?: [] as $r)
        {
            for ($i = 1; $i <= 3; $i++)
                if ((int)$r['effect'.$i.'Id'] == SPELL_EFFECT_SEND_TAXI && in_array($_ = (int)$r['effect'.$i.'MiscValue'], $pathIds))
                    $out[$_][self::SRC_SPELL][(int)$r['id']] = (int)$r['id'];
        }

        // SMART_ACTION_ACTIVATE_TAXI
        foreach (DB::World()->selectAssoc(
           'SELECT `entryorguid`, `source_type`, `action_param1` FROM smart_scripts WHERE `action_type` = %i AND `action_param1` IN %in',
            SmartAction::ACTION_ACTIVATE_TAXI, $pathIds) ?: [] as $r)
            $out[(int)$r['action_param1']][self::SRC_SMARTAI][] = [(int)$r['source_type'], (int)$r['entryorguid']];

        // a MO_TRANSPORT running the path - the boat is the flight, there is no flight master
        foreach (DB::World()->selectAssoc(
           'SELECT `entry`, `data0` FROM gameobject_template WHERE `type` = %i AND `data0` IN %in',
            GO_TYPE_MO_TRANSPORT, $pathIds) ?: [] as $r)
            $out[(int)$r['data0']][self::SRC_OBJECT][(int)$r['entry']] = (int)$r['entry'];

        return $out;
    }

    /**
     * listview rows for a set of paths, shared by ?taxipaths and the flight master tab on an NPC
     *
     * @param  array  $pathIds    empty for every path
     * @param  array &$jsGlobals  receives the zones and flight masters referenced
     */
    public static function getListviewData(array $pathIds, ?array &$jsGlobals = []) : array
    {
        $paths   = self::getPaths($pathIds);
        $sources = self::getSources(array_keys($paths));
        $jsg     = [];
        $data    = [];

        foreach ($paths as $id => $p)
        {
            $row = array(
                'id'       => $id,
                'from'     => $p['from'],
                'fromarea' => $p['fromArea'],
                'to'       => $p['to'],
                'toarea'   => $p['toArea']
            );

            foreach (['fromArea', 'toArea'] as $k)
                if ($_ = $p[$k])
                    $jsg[Type::ZONE][$_] = $_;

            $src = $sources[$id] ?? [];

            if ($_ = ($src[self::SRC_FLIGHTMASTER] ?? []))
            {
                $row['flightmaster'] = array_values($_);
                foreach ($_ as $npcId)
                    $jsg[Type::NPC][$npcId] = $npcId;
            }

            // what else can start this flight, as counts; the detail page names them
            $row['nspells']  = count($src[self::SRC_SPELL]   ?? []);
            $row['nscripts'] = count($src[self::SRC_SMARTAI] ?? []);
            $row['nobjects'] = count($src[self::SRC_OBJECT]  ?? []);

            $data[] = $row;
        }

        Util::mergeJsGlobals($jsGlobals, $jsg);

        return $data;
    }

    /** the flights a given creature offers or receives, so a flight master links to its routes */
    public static function getPathIdsForNPC(int $npcId) : array
    {
        if ($npcId <= 0)
            return [];

        $ids = DB::Aowow()->selectCol(
           'SELECT tp.`id`
            FROM   ::taxipath tp
            JOIN   ::taxinodes n ON n.`id` = tp.`startNodeId` OR n.`id` = tp.`endNodeId`
            WHERE  n.`type` = %s AND n.`typeId` = %i',
            'NPC', $npcId
        ) ?: [];

        return array_values(array_unique(array_map('intVal', $ids)));
    }
}

?>
