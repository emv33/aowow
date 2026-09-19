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
                   `kind`, -`creatureOrPath` AS "sourceId", COUNT(1) AS "numPoints", SUM(`wait`) AS "totalWait", MIN(NULLIF(`areaId`, 0)) AS "areaId", MAX(`viaSmartAI`) AS "viaSmartAI"
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

    /** every path (movement or escort) tied to a given creature, so its own page can link to them */
    public static function getPathIdsForNPC(int $npcId) : array
    {
        if ($npcId <= 0)
            return [];

        $ids = DB::World()->selectCol(
           'SELECT DISTINCT ca.`path_id`  FROM creature_addon ca JOIN creature c ON c.`guid` = ca.`guid` WHERE c.`id` = %i AND ca.`path_id` > 0 UNION
            SELECT DISTINCT cta.`path_id` FROM creature_template_addon cta WHERE cta.`entry` = %i AND cta.`path_id` > 0',
            $npcId, $npcId
        ) ?: [];

        // covers every ACTION_WP_START assignment - guid-pinned or not, direct or through a timed
        // action list - see SmartAI::getWaypointStartOwners()
        foreach (SmartAI::getWaypointStartOwners() as $pathId => $owner)
            if ($owner['entry'] == $npcId)
                $ids[] = $pathId;

        $out = array_values(array_unique(array_map('intVal', $ids)));

        if (LegacyScript::exists(LegacyScript::SRC_ESCORT_PATH, $npcId))
            $out[] = self::encodeId(self::KIND_ESCORT, $npcId);

        return $out;
    }

    public function getListviewData() : array
    {
        $data     = [];
        $npcOwner = [];

        // kind 0 paths are walked by whichever creature(s) reference this path id as their default
        // (*_addon), or that a SmartAI ACTION_WP_START assigns it to at runtime - directly or
        // through a timed action list it calls (SmartAI::getWaypointStartOwners(), shared with
        // spawns.ss.php's waypoints() step and ::getPathIdsForNPC())
        //
        // `guid` is only non-zero for a source that pins the path to one specific spawn
        // (creature_addon, or a SmartAI entry tied to a negative entryorguid) rather than every
        // spawn of the entry (creature_template_addon, a positive entryorguid, or a timed action
        // list call) - worth surfacing, since the latter reads as "every X walks this" when only
        // one does
        if ($sourceIds = array_column(array_filter($this->templates, fn($t) => $t['kind'] == self::KIND_MOVEMENT), 'sourceId'))
        {
            $rows = DB::World()->selectAssoc(
               'SELECT `path_id` AS ARRAY_KEY, `entry`, `guid` FROM (
                    SELECT ca.`path_id`, c.`id` AS "entry", c.`guid` AS "guid" FROM creature_addon ca JOIN creature c ON c.`guid` = ca.`guid` WHERE ca.`path_id` IN %in UNION
                    SELECT cta.`path_id`, cta.`entry`, 0 AS "guid" FROM creature_template_addon cta WHERE cta.`path_id` IN %in
                ) x',
                $sourceIds, $sourceIds
            ) ?: [];

            foreach ($rows as $pathId => $row)
                $npcOwner[self::KIND_MOVEMENT][$pathId] = ['npc' => (int)$row['entry'], 'guid' => (int)$row['guid']];

            // a SmartAI assignment on the same pathId wins over a stale *_addon default, same
            // priority spawns.ss.php's REPLACE INTO gives it
            foreach (array_intersect_key(SmartAI::getWaypointStartOwners(), array_flip($sourceIds)) as $pathId => $o)
                $npcOwner[self::KIND_MOVEMENT][$pathId] = ['npc' => $o['entry'], 'guid' => $o['guid']];
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
                'areaId'     => (int)$this->curTpl['areaId'],
                'viaSmartAI' => (int)$this->curTpl['viaSmartAI']
            );

            // kind 1 escort paths are keyed by the creature entry itself
            $npcId = $kind == self::KIND_ESCORT ? $sourceId : ($npcOwner[self::KIND_MOVEMENT][$sourceId]['npc'] ?? 0);
            if ($npcId)
                $data[$this->id]['npc'] = $npcId;

            if ($kind == self::KIND_MOVEMENT && ($guid = $npcOwner[self::KIND_MOVEMENT][$sourceId]['guid'] ?? 0))
                $data[$this->id]['guid'] = $guid;
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
        2 => [parent::CR_NUMERIC, 'id',         NUM_CAST_INT      ], // id
        3 => [parent::CR_NUMERIC, 'numPoints',  NUM_CAST_INT      ], // points
        4 => [parent::CR_ENUM,    'areaId',     false,        true], // foundin
        5 => [parent::CR_BOOLEAN, 'viaSmartAI'                    ]  // smartai
    );

    // fieldId => [checkType, checkValue[, fieldIsArray]]
    protected static array $inputFields = array(
        'cr'  => [parent::V_LIST,  [2, 3, 4, 5], true ], // criteria ids
        'crs' => [parent::V_RANGE, [1, 4987],    true ], // criteria operators
        'crv' => [parent::V_REGEX, parent::PATTERN_INT, true], // criteria values - all criteria are numeric here
        'ma'  => [parent::V_EQUAL, 1,         false]  // match any / all filter
    );

    // id/points/foundin/smartai are all plain criteria rows handled generically via $genericFilter
    // above - there are no dedicated form fields (e.g. a name search box) of their own to translate here
    protected function createSQLForValues() : array
    {
        return [];
    }
}

?>
