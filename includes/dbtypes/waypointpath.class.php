<?php

namespace Aowow;

if (!defined('AOWOW_REVISION'))
    die('illegal access');


/*
 * A "path" here is one of two things `aowow_creature_waypoints` groups points under:
 *   kind 0 - a `waypoint_data` path (creature_addon/creature_template_addon.path_id, or a
 *            SmartAI ACTION_WP_START target) - `creatureOrPath` is -waypoint_data.id
 *   kind 1 - a `script_waypoint` escort path - `creatureOrPath` is -script_waypoint.entry
 *            (entry is conventionally the creature template id, see LegacyScript::SRC_ESCORT_PATH)
 *
 * Both are plain positive ids handed out independently by TrinityCore content, so they are not
 * safe to expose as one browsable id verbatim - encodeId()/decodeId() below fold kind into the
 * public id instead (kind 0 keeps its natural id, kind 1 is offset well past any real path id).
 */
class WaypointPathList extends DBTypeList
{
    public const int KIND_MOVEMENT = 0;
    public const int KIND_ESCORT   = 1;

    private const int KIND_OFFSET  = 1000000000;               // aowow - custom: see class doc comment

    public static int    $type       = Type::WAYPOINT_PATH;
    public static string $brickFile  = 'waypointpath';
    public static string $dataTable  = '';                     // no FLAG_DB_TYPE - id is synthetic, see decodeId()
    public static int    $contribute = CONTRIBUTE_NONE;

    protected string $queryBase =
       'SELECT x.*, x.id AS ARRAY_KEY FROM (
            SELECT (CASE `kind` WHEN 0 THEN -`creatureOrPath` ELSE '.self::KIND_OFFSET.' - `creatureOrPath` END) AS "id",
                   `kind`, -`creatureOrPath` AS "sourceId", COUNT(1) AS "numPoints", SUM(`wait`) AS "totalWait", MIN(NULLIF(`areaId`, 0)) AS "areaId"
            FROM   ::creature_waypoints
            GROUP BY `kind`, `creatureOrPath`
        ) x';

    public static function encodeId(int $kind, int $sourceId) : int
    {
        return $kind == self::KIND_ESCORT ? self::KIND_OFFSET + $sourceId : $sourceId;
    }

    /** @return array{0: int, 1: int} [kind, sourceId] */
    public static function decodeId(int $id) : array
    {
        return $id >= self::KIND_OFFSET ? [self::KIND_ESCORT, $id - self::KIND_OFFSET] : [self::KIND_MOVEMENT, $id];
    }

    /** ordered points of one path, for rendering its route on a map */
    public static function getPoints(int $kind, int $sourceId) : array
    {
        return DB::Aowow()->selectAssoc(
           'SELECT `point` AS ARRAY_KEY, `areaId`, `floor`, `posX`, `posY`, `wait`
            FROM   ::creature_waypoints
            WHERE  `kind` = %i AND `creatureOrPath` = %i
            ORDER BY `point` ASC',
            $kind, -$sourceId
        ) ?: [];
    }

    public function getListviewData() : array
    {
        $data     = [];
        $npcOwner = [];

        // kind 0 paths are walked by whichever creature(s) reference this path id as their default
        if ($sourceIds = array_column(array_filter($this->templates, fn($t) => $t['kind'] == self::KIND_MOVEMENT), 'sourceId'))
        {
            $rows = DB::World()->selectCol(
               'SELECT `path_id` AS ARRAY_KEY, `entry` FROM (
                    SELECT ca.`path_id`, c.`id` AS "entry" FROM creature_addon ca JOIN creature c ON c.`guid` = ca.`guid` WHERE ca.`path_id` IN %in UNION
                    SELECT cta.`path_id`, cta.`entry` FROM creature_template_addon cta WHERE cta.`path_id` IN %in
                ) x',
                $sourceIds, $sourceIds
            ) ?: [];

            foreach ($rows as $pathId => $entry)
                $npcOwner[self::KIND_MOVEMENT][$pathId] = (int)$entry;
        }

        foreach ($this->iterate() as $__)
        {
            $kind     = (int)$this->curTpl['kind'];
            $sourceId = (int)$this->curTpl['sourceId'];

            $data[$this->id] = array(
                'id'         => $this->id,
                'kind'       => $kind,
                'numpoints'  => (int)$this->curTpl['numPoints'],
                'totalwait'  => (int)$this->curTpl['totalWait'],
                'areaId'     => (int)$this->curTpl['areaId']
            );

            // kind 1 escort paths are keyed by the creature entry itself
            $npcId = $kind == self::KIND_ESCORT ? $sourceId : ($npcOwner[self::KIND_MOVEMENT][$sourceId] ?? 0);
            if ($npcId)
                $data[$this->id]['npc'] = $npcId;
        }

        return $data;
    }

    public function getJSGlobals(int $addMask = GLOBALINFO_ANY) : array { return []; }

    public function renderTooltip() : ?string { return null; }
}

class WaypointPathListFilter extends Filter
{
    protected string $type = 'waypointpath';

    protected static array $enums = array(
        4 => parent::ENUM_ZONE                              // foundin
    );

    protected static array $genericFilter = array(
        2 => [parent::CR_NUMERIC, 'id',      NUM_CAST_INT      ], // id
        3 => [parent::CR_NUMERIC, 'kind',    NUM_CAST_INT      ], // kind
        4 => [parent::CR_ENUM,    'areaId',  false,        true]  // foundin
    );

    // fieldId => [checkType, checkValue[, fieldIsArray]]
    protected static array $inputFields = array(
        'cr'  => [parent::V_LIST,  [2, 3, 4], true ], // criteria ids
        'crs' => [parent::V_RANGE, [1, 4987], true ], // criteria operators
        'crv' => [parent::V_REGEX, parent::PATTERN_INT, true], // criteria values - all criteria are numeric here
        'ma'  => [parent::V_EQUAL, 1,         false]  // match any / all filter
    );
}

?>
