<?php

namespace Aowow;

if (!defined('AOWOW_REVISION'))
    die('illegal access');


class GameObjectList extends DBTypeList
{
    use listviewHelper, spawnHelper;

    public static int    $type      = Type::OBJECT;
    public static string $brickFile = 'object';
    public static string $dataTable = '::objects';

    protected string $queryBase = 'SELECT o.*, o.`id` AS ARRAY_KEY FROM ::objects o';
    protected array  $queryOpts = array(
                        'o'   => [['ft', 'qse']],
                        'nml' => ['j' => ['::objects_search nml ON nml.`id` = o.`id` AND nml.`locale` = DB_LOC_I']],
                        'ft'  => ['j' => ['::factiontemplate ft ON ft.`id` = o.`faction`', true], 's' => ', ft.`factionId`, IFNULL(ft.`A`, 0) AS "A", IFNULL(ft.`H`, 0) AS "H"'],
                        'qse' => ['j' => ['::quests_startend qse ON qse.`type` = 2 AND qse.`typeId` = o.id', true], 's' => ', IF(MIN(qse.`method`) = 1 OR MAX(qse.`method`) = 3, 1, 0) AS "startsQuests", IF(MIN(qse.`method`) = 2 OR MAX(qse.`method`) = 3, 1, 0) AS "endsQuests"', 'g' => 'o.`id`'],
                        'qt'  => ['j' => '::quests qt ON qse.`questId` = qt.`id`'],
                        's'   => ['j' => ['::spawns s ON s.`type` = 2 AND s.`typeId` = o.`id`', true]]   // aowow - custom: left join, so haslocation can match objects without spawns (upstream: inner join)
                    );

    public function __construct(array $conditions = [], array $miscData = [])
    {
        parent::__construct($conditions, $miscData);

        if ($this->error)
            return;

        // post processing
        foreach ($this->iterate() as $_id => &$curTpl)
        {
            if (!$curTpl['name_loc'.Lang::getLocale()->value])
                $curTpl['name_loc'.Lang::getLocale()->value] = Lang::gameObject('unnamed', [$_id]);

            // unpack miscInfo
            $curTpl['mStone']    =
            $curTpl['capture']   =
            $curTpl['lootStack'] = null;
            $curTpl['spells']    = [];

            if (in_array($curTpl['type'], [OBJECT_GOOBER, OBJECT_RITUAL, OBJECT_SPELLCASTER, OBJECT_FLAGSTAND, OBJECT_FLAGDROP, OBJECT_AURA_GENERATOR, OBJECT_TRAP]))
                $curTpl['spells'] = array_combine(['onUse', 'onSuccess', 'aura', 'triggered'], [$curTpl['onUseSpell'], $curTpl['onSuccessSpell'], $curTpl['auraSpell'], $curTpl['triggeredSpell']]);

            if (!$curTpl['miscInfo'])
                continue;

            switch ($curTpl['type'])
            {
                case OBJECT_CHEST:
                case OBJECT_FISHINGHOLE:
                    $curTpl['lootStack'] = explode(' ', $curTpl['miscInfo']);
                    break;
                case OBJECT_CAPTURE_POINT:
                    $curTpl['capture'] = explode(' ', $curTpl['miscInfo']);
                    break;
                case OBJECT_MEETINGSTONE:
                    $curTpl['mStone'] = explode(' ', $curTpl['miscInfo']);
                    break;
            }
        }
    }

    public function getListviewData() : array
    {
        $data = [];
        foreach ($this->iterate() as $__)
        {
            if ($location = $this->getSpawns(SPAWNINFO_ZONES))
                if (count($location) > 3)
                    array_splice($location, 3, replacement: -1);

            $data[$this->id] = array(
                'id'       => $this->id,
                'name'     => UIText::unescapeUISequences($this->getField('name', true), Lang::FMT_RAW),
                'type'     => $this->getField('typeCat'),
                'location' => $location
            );

            if (!empty($this->curTpl['reqSkill']))
                $data[$this->id]['skill'] = $this->curTpl['reqSkill'];

            if ($this->curTpl['startsQuests'])
                $data[$this->id]['hasQuests'] = 1;

            if ($_ = self::getModesFor($this->id))         // aowow - custom: difficulty mode pills, as on npcs
                $data[$this->id]['modes'] = $_;
        }

        return $data;
    }

    public function renderTooltip($interactive = false) : ?string
    {
        if (!$this->curTpl)
            return null;

        $x  = '<table>';

        # Name #

        $x .= '<tr><td><b class="q">'.UIText::unescapeUISequences($this->getField('name', true), Lang::FMT_HTML).'</b></td></tr>';

        // WH - non standard
        if ($this->curTpl['typeCat'])
            if ($_ = Lang::gameObject('type', $this->curTpl['typeCat']))
                $x .= '<tr><td>'.$_.'</td></tr>';

        # Locked #

        if ($this->curTpl['flags'] & GO_FLAG_LOCKED)
            $x .= '<tr><td>'.Lang::item('locked').'</td></tr>';

        foreach (Lang::getLocks($this->curTpl['lockId'] ?? 0) as [$type, , $str, $miscInfo])
        {
            $x .= '<tr><td>'.match($type)
            {
                LOCK_TYPE_ITEM  => Lang::item('lockedByItem',  [$str]),
                LOCK_TYPE_SPELL => Lang::item('lockedBySpell', [$str]),
                LOCK_TYPE_SKILL => Lang::item($miscInfo ? 'reqSkillValue' : 'reqSkill', [$str, $miscInfo])
            }.'</td></tr>';

            if ($type == Type::SPELL && $miscInfo && !($reagents = new ItemList(array(['id', array_keys($miscInfo)])))->error)
            {
                $reqReag  = [];
                foreach ($miscInfo as [$itemId, $qty])
                {
                    if ($item = $reagents->getEntry($itemId))
                        $_ = '<a href="?item='.$itemId.'">'.new LocString($item).'</a>';
                    else
                        $_ = 'Item #'.$itemId;

                    $reqReag[] = ($qty > 1) ? Lang::main('parensFmt', [$_, $qty]) : $_;
                }

                $x .= '<tr class="q1 whtt-reagents"><td><br />'.Lang::item('reqSkill', [Lang::concat($reqReag, Lang::CONCAT_NONE)]).'</td></tr>';
            }
        }

        # Quest Tracking Info #

        // unsure, if should be displayed
        // QUEST_TOOLTIP_REQUIREMENTS
        // QuestTitle cssClass: 'q'
        // " - ItemName" from gameobject_questitem
        // QUEST_OBJECTS_FOUND_NOPROGRESS
        // QUEST_MONSTERS_KILLED_NOPROGRESS
        // QUEST_ITEMS_NEEDED_NOPROGRESS

        $x .= '</table>';

        # Health Bar #

        // should not display (also the tooltip BG is rendered behind the progress bar, which it shouldn't)
        // if ($this->curTpl['type'] == OBJECT_DESTRUCTIBLE_BUILDING)
        //     $x .= '<div><a href="javascript:;" class="progressbar" style="width:auto;"><div class="progressbar-text"></div><div class="progressbar-green" style="width:100%;"> </div></a></div>';

        return $x;
    }

    public function getJSGlobals(int $addMask = 0) : array
    {
        $data = [];

        foreach ($this->iterate() as $__)
            $data[Type::OBJECT][$this->id] = ['name' => UIText::unescapeUISequences($this->getField('name', true), Lang::FMT_RAW)];

        return $data;
    }

    public function getSourceData(int $id = 0) : array
    {
        $data = [];

        foreach ($this->iterate() as $__)
        {
            if ($id && $id != $this->id)
                continue;

            $data[$this->id] = array(
                'n'  => $this->getField('name', true),
                't'  => Type::OBJECT,
                'ti' => $this->id
            );
        }

        return $data;
    }

    /**
     * aowow - custom start: transports
     * A GAMEOBJECT_TYPE_MO_TRANSPORT names a TaxiPath in `data0`, and that path resolves - through
     * the taxi tables the `taxi` setup step already writes - to the two nodes it runs between.
     * Nothing linked the two, so a boat's destination appeared nowhere.
     */
    public static function getTransportRoute(int $entry) : ?array
    {
        $pathId = (int)DB::World()->selectCell('SELECT `data0` FROM gameobject_template WHERE `entry` = %i AND `type` = %i', $entry, GO_TYPE_MO_TRANSPORT);

        return $pathId && ($route = self::getTransportRoutes([$pathId])[$pathId] ?? null) ? $route + ['pathId' => $pathId] : null;
    }

    /** every transport template, with the route of those that run one */
    public static function getTransports() : array
    {
        $tpl = DB::World()->selectAssoc(
           'SELECT `entry` AS ARRAY_KEY, `entry`, `type`, `name`, `data0` FROM gameobject_template WHERE `type` IN %in ORDER BY `type`, `entry` ASC',
            [GO_TYPE_TRANSPORT, GO_TYPE_MO_TRANSPORT]
        ) ?: [];

        if (!$tpl)
            return [];

        // only a MO_TRANSPORT names a TaxiPath; a plain TRANSPORT is an elevator and `data0` is its pause timer
        $pathIds = [];
        foreach ($tpl as $t)
            if ((int)$t['type'] == GO_TYPE_MO_TRANSPORT && (int)$t['data0'] > 0)
                $pathIds[] = (int)$t['data0'];

        $routes  = self::getTransportRoutes($pathIds);
        $spawned = self::getSpawnedTransports(array_keys($tpl));

        $out = [];
        foreach ($tpl as $entry => $t)
        {
            $out[(int)$entry] = array(
                'entry'   => (int)$entry,
                'type'    => (int)$t['type'],
                'name'    => (string)$t['name'],
                'pathId'  => (int)$t['type'] == GO_TYPE_MO_TRANSPORT ? (int)$t['data0'] : 0,
                'route'   => $routes[(int)$t['data0']] ?? null,
                'spawned' => isset($spawned[(int)$entry])
            );
        }

        return $out;
    }

    private static function getTransportRoutes(array $pathIds) : array
    {
        if (!$pathIds = array_values(array_unique(array_filter($pathIds))))
            return [];

        $loc  = Lang::getLocale();
        $rows = DB::Aowow()->selectAssoc(
           'SELECT   tp.`id` AS ARRAY_KEY,
                     n1.`name_loc0` AS "fromName0", n1.`name_loc'.$loc->value.'` AS "fromName", n1.`areaId` AS "fromArea",
                     n2.`name_loc0` AS "toName0",   n2.`name_loc'.$loc->value.'` AS "toName",   n2.`areaId` AS "toArea"
            FROM     ::taxipath tp
            JOIN     ::taxinodes n1 ON n1.`id` = tp.`startNodeId`
            JOIN     ::taxinodes n2 ON n2.`id` = tp.`endNodeId`
            WHERE    tp.`id` IN %in',
            $pathIds
        ) ?: [];

        $out = [];
        foreach ($rows as $id => $r)
            $out[(int)$id] = array(
                'from'     => (string)($r['fromName'] ?: $r['fromName0']),
                'fromArea' => (int)$r['fromArea'],
                'to'       => (string)($r['toName'] ?: $r['toName0']),
                'toArea'   => (int)$r['toArea']
            );

        return $out;
    }

    /**
     * the two transport types are spawned by different mechanisms, and checking only one of them
     * marks every entry of the other type as never spawned
     *   MO_TRANSPORT - a row in `transports`, which not every core ships
     *   TRANSPORT    - an elevator, spawned through `gameobject` like any other object
     */
    private static function getSpawnedTransports(array $entries) : array
    {
        if (!$entries)
            return [];

        $spawned = [];

        foreach (DB::World()->selectCol('SELECT DISTINCT `id` FROM gameobject WHERE `id` IN %in', $entries) ?: [] as $id)
            $spawned[(int)$id] = true;

        if (DB::World()->selectCell('SHOW TABLES LIKE %s', 'transports'))
            foreach (DB::World()->selectCol('SELECT DISTINCT `entry` FROM transports WHERE `entry` IN %in', $entries) ?: [] as $id)
                $spawned[(int)$id] = true;

        return $spawned;
    }
    // aowow - custom end

    // aowow - custom start: difficulty modes and visibility range
    /** ::objectdifficulty, keyed by object: [mapType, modeMask]; a chest may sit in several rows (freya's gift) */
    private static function getDifficultyModes() : array
    {
        static $modes = null;

        if ($modes !== null)
            return $modes;

        $modes = [];
        foreach (DB::Aowow()->selectAssoc('SELECT `normal10`, `normal25`, `heroic10`, `heroic25`, `mapType` FROM ::objectdifficulty') ?: [] as $r)
        {
            foreach (['normal10', 'normal25', 'heroic10', 'heroic25'] as $i => $col)
            {
                if (!($id = (int)$r[$col]))
                    continue;

                $modes[$id] ??= [(int)$r['mapType'], 0];
                $modes[$id][1] |= 1 << $i;
            }
        }

        return $modes;
    }

    /** [mapType, modeMask] of an object listed in ::objectdifficulty, in the shape CreatureList::modeLabels() takes */
    public static function getModesFor(int $id) : ?array
    {
        return self::getDifficultyModes()[$id] ?? null;
    }

    /** objects used at difficulty $diff of $mapType; all listed objects if $mapType is 0 */
    public static function getIdsByMode(int $mapType = 0, int $diff = -1) : array
    {
        $ids = [];
        foreach (self::getDifficultyModes() as $id => [$type, $mask])
            if (!$mapType || ($type == $mapType && $mask & (1 << $diff)))
                $ids[] = $id;

        return $ids;
    }

    /** the first object of each ::objectdifficulty row that has more than one, like the parent of a creature difficulty set */
    public static function getDifficultyParents() : array
    {
        $ids = [];
        foreach (DB::Aowow()->selectAssoc('SELECT `normal10`, `normal25`, `heroic10`, `heroic25` FROM ::objectdifficulty') ?: [] as $r)
            if (count($set = array_values(array_filter(array_map('intVal', $r)))) > 1)
                $ids[$set[0]] = $set[0];

        return array_values($ids);
    }

    /**
     * GameObject::Create() overrides the map's visibility range with VisibilityDistanceType::Infinite for
     * GameObjectTemplate::IsInfiniteGameObject() and ::Large for IsLargeGameObject(); the `large` field
     * sits in a different data<n> column per type
     */
    public const array VISIBILITY_LARGE_FIELD = array(
        GO_TYPE_BUTTON        => 'data5',
        GO_TYPE_QUESTGIVER    => 'data9',
        GO_TYPE_GENERIC       => 'data3',
        GO_TYPE_TRAP          => 'data10',
        GO_TYPE_SPELL_FOCUS   => 'data5',
        GO_TYPE_GOOBER        => 'data13',
        GO_TYPE_SPELLCASTER   => 'data4',
        GO_TYPE_CAPTURE_POINT => 'data18'
    );
    public const array VISIBILITY_INFINITE_TYPES = [GO_TYPE_DOOR, GO_TYPE_FLAGSTAND, GO_TYPE_FLAGDROP, GO_TYPE_TRAPDOOR];
    public const int   VISIBILITY_LARGE    = 3;             // VisibilityDistanceType, as creature_template_addon uses them
    public const int   VISIBILITY_INFINITE = 5;
    public const array VISIBILITY_YARDS    = [self::VISIBILITY_LARGE => 200, self::VISIBILITY_INFINITE => 533];

    /** VisibilityDistanceType of a gameobject_template row; 0 is the map default */
    public static function getVisibilityType(array $tplRow) : int
    {
        $type = (int)$tplRow['type'];
        if (in_array($type, self::VISIBILITY_INFINITE_TYPES))
            return self::VISIBILITY_INFINITE;

        if (($col = self::VISIBILITY_LARGE_FIELD[$type] ?? null) && !empty($tplRow[$col]))
            return self::VISIBILITY_LARGE;

        return 0;
    }

    /** the same as getVisibilityType(), as an SQL expression over gameobject_template */
    public static function getVisibilityTypeSQL() : string
    {
        $large = [];
        foreach (self::VISIBILITY_LARGE_FIELD as $type => $col)
            $large[] = '(`type` = '.$type.' AND `'.$col.'` <> 0)';

        return 'IF(`type` IN ('.implode(', ', self::VISIBILITY_INFINITE_TYPES).'), '.self::VISIBILITY_INFINITE.', IF('.implode(' OR ', $large).', '.self::VISIBILITY_LARGE.', 0))';
    }
    // aowow - custom end
}


class GameObjectListFilter extends Filter
{
    protected string $type  = 'objects';
    protected static array $enums = array(
         1 => parent::ENUM_ZONE,
        16 => parent::ENUM_EVENT,
        // aowow - custom start
        51 => parent::ENUM_FACTION,                         // faction
        53 => [0, 1, 2, 3, 4, 5],                           // visibilityrange: VisibilityDistanceType, of which objects only reach 0, 3 and 5
        67 => [1, 2, 3, 4, 5, 6]                            // npcmode: keys of MODE_FILTER
        // aowow - custom end
    );

    protected static array $genericFilter = array(
         1 => [parent::CR_ENUM,     's.areaId',        false,                true], // foundin
         2 => [parent::CR_CALLBACK, 'cbQuestRelation', 'startsQuests',       0x1 ], // startsquest [side]
         3 => [parent::CR_CALLBACK, 'cbQuestRelation', 'endsQuests',         0x2 ], // endsquest [side]
         4 => [parent::CR_CALLBACK, 'cbOpenable',      null,                 null], // openable [yn]
         5 => [parent::CR_CALLBACK, 'cbMoneyContained', null,                null], // averagemoneycontained [op] [int] - aowow - custom: gameobject_template_addon holds it (upstream: CR_NYI_PH, matched against 0)
         7 => [parent::CR_NUMERIC,  'reqSkill',        NUM_CAST_INT              ], // requiredskilllevel
        11 => [parent::CR_FLAG,     'cuFlags',         CUSTOM_HAS_SCREENSHOT     ], // hasscreenshots
        13 => [parent::CR_FLAG,     'cuFlags',         CUSTOM_HAS_COMMENT        ], // hascomments
        15 => [parent::CR_NUMERIC,  'id',              NUM_CAST_INT              ], // id
        16 => [parent::CR_CALLBACK, 'cbRelEvent',      null,                 null], // relatedevent (ignore removed by event)
        18 => [parent::CR_FLAG,     'cuFlags',         CUSTOM_HAS_VIDEO          ], // hasvideos
        50 => [parent::CR_NUMERIC,  'spellFocusId',    NUM_CAST_INT              ], // spellfocus - dbc_spellfocusobject / ?spellfocus names the id, no fixed list needed
        // aowow - custom start
        51 => [parent::CR_CALLBACK, 'cbFaction',       null,                 null], // faction [enum]
        52 => [parent::CR_NUMERIC,  'displayId',       NUM_CAST_INT              ], // usemodel [num]
        53 => [parent::CR_CALLBACK, 'cbVisibilityRange', null,               null], // visibilityrange [enum]
        54 => [parent::CR_CALLBACK, 'cbHasLocation',   null,                 null], // haslocation [yn] [staff]
        55 => [parent::CR_CALLBACK, 'cbStaffString',   'ScriptOrAI',         null], // scriptname [str] [staff]
        56 => [parent::CR_CALLBACK, 'cbStaffString',   'StringId',           null], // stringid [str] [staff]
        57 => [parent::CR_BOOLEAN,  'lootId'                                     ], // lootable [yn]
        58 => [parent::CR_BOOLEAN,  'pageTextId'                                 ], // objreadable [yn]
        59 => [parent::CR_BOOLEAN,  'linkedTrap'                                 ], // objlinkedtrap [yn]
        60 => [parent::CR_BOOLEAN,  'reqQuest'                                   ], // objreqquest [yn]
        61 => [parent::CR_CALLBACK, 'cbWorldIds',      'phased',             null], // phased [yn]
        62 => [parent::CR_CALLBACK, 'cbWorldIds',      'questitems',         null], // objquestitems [yn]
        63 => [parent::CR_CALLBACK, 'cbWorldIds',      'gossip',             null], // objgossip [yn]
        64 => [parent::CR_CALLBACK, 'cbWorldIds',      'smartai',            null], // objsmartai [yn]
        65 => [parent::CR_CALLBACK, 'cbWorldIds',      'pooled',             null], // objpooled [yn]
        66 => [parent::CR_CALLBACK, 'cbTemplateNum',   '`size`',             null], // scale [num]
        67 => [parent::CR_CALLBACK, 'cbMode',          null,                 null], // npcmode [enum]
        68 => [parent::CR_CALLBACK, 'cbDifficultySlot', null,                null], // hasdifficultyversions [yn]
        69 => [parent::CR_CALLBACK, 'cbWorldIds',      'serveronly',         null], // objserveronly [yn]
        70 => [parent::CR_CALLBACK, 'cbWorldIds',      'mounted',            null], // objusablemounted [yn]
        71 => [parent::CR_FLAG,     'flags',           GO_FLAG_IN_USE            ], // goflaginuse [yn]
        72 => [parent::CR_FLAG,     'flags',           GO_FLAG_LOCKED            ], // goflaglocked [yn]
        73 => [parent::CR_FLAG,     'flags',           GO_FLAG_INTERACT_COND     ], // goflaginteractcond [yn]
        74 => [parent::CR_FLAG,     'flags',           GO_FLAG_TRANSPORT         ], // goflagtransport [yn]
        75 => [parent::CR_FLAG,     'flags',           GO_FLAG_NOT_SELECTABLE    ], // goflagnotselectable [yn]
        76 => [parent::CR_FLAG,     'flags',           GO_FLAG_NODESPAWN         ], // goflagnodespawn [yn]
        77 => [parent::CR_FLAG,     'flags',           GO_FLAG_AI_OBSTACLE       ], // goflagaiobstacle [yn]
        78 => [parent::CR_FLAG,     'flags',           GO_FLAG_FREEZE_ANIMATION  ], // goflagfreezeanim [yn]
        79 => [parent::CR_FLAG,     'flags',           GO_FLAG_DAMAGED           ], // goflagdamaged [yn]
        80 => [parent::CR_FLAG,     'flags',           GO_FLAG_DESTROYED         ]  // goflagdestroyed [yn]
        // aowow - custom end
    );

    protected static array $inputFields = array(
        'cr'  => [parent::V_LIST,  [[1, 5], 7, 11, 13, 15, 16, 18, [50, 80]],        true ], // criteria ids - aowow - custom: 51-80 (upstream: 50)
        'crs' => [parent::V_LIST,  [parent::ENUM_NONE, parent::ENUM_ANY, [0, 5000]], true ], // criteria operators
        'crv' => [parent::V_REGEX, parent::PATTERN_CRV,                              true ], // criteria values - aowow - custom: script name / string id are text (upstream: PATTERN_INT)
        'na'  => [parent::V_NAME,  false,                                            false], // name - only printable chars, no delimiter
        'ma'  => [parent::V_EQUAL, 1,                                                false]  // match any / all filter
    );

    public array $extraOpts = [];

    protected function createSQLForValues() : array
    {
        $parts = [];
        $_v    = $this->values;

        // name
        if ($_v['na'])
        {
            if ($_ = $this->buildMatchLookup([['na', 'nml.nName']]))
                $parts[] = $_;
            else if ($_ = $this->buildLikeLookup([['na', 'name_loc'.Lang::getLocale()->value]]))
                $parts[] = $_;
        }

        return $parts;
    }

    protected function cbOpenable(int $cr, int $crs, string $crv) : ?array
    {
        if ($this->int2Bool($crs))
            return $crs ? [DB::OR, ['flags', 0x2, '&'], ['type', 3]] : [DB::AND, [['flags', 0x2, '&'], 0], ['type', 3, '!']];

        return null;
    }

    protected function cbQuestRelation(int $cr, int $crs, string $crv, $field, $value) : ?array
    {
        switch ($crs)
        {
            case 1:                                 // any
                return [DB::AND, ['qse.method', $value, '&'], ['qse.questId', null, '!']];
            case 2:                                 // alliance only
                return [DB::AND, ['qse.method', $value, '&'], ['qse.questId', null, '!'], [['qt.reqRaceMask', ChrRace::MASK_HORDE, '&'], 0], ['qt.reqRaceMask', ChrRace::MASK_ALLIANCE, '&']];
            case 3:                                 // horde only
                return [DB::AND, ['qse.method', $value, '&'], ['qse.questId', null, '!'], [['qt.reqRaceMask', ChrRace::MASK_ALLIANCE, '&'], 0], ['qt.reqRaceMask', ChrRace::MASK_HORDE, '&']];
            case 4:                                 // both
                return [DB::AND, ['qse.method', $value, '&'], ['qse.questId', null, '!'], [DB::OR, [DB::AND, ['qt.reqRaceMask', ChrRace::MASK_ALLIANCE, '&'], ['qt.reqRaceMask', ChrRace::MASK_HORDE, '&']], ['qt.reqRaceMask', 0]]];
            case 5:                                 // none         todo (low): broken, if entry starts and ends quests...
                $this->extraOpts['o']['h'][] = $field.' = 0';
                return [1];
        }

        return null;
    }

    protected function cbRelEvent(int $cr, int $crs, string $crv) : ?array
    {
        if ($crs == parent::ENUM_ANY)
        {
            if ($eventIds = DB::Aowow()->selectCol('SELECT `id` FROM ::events WHERE `holidayId` <> 0'))
                if ($goGuids  = DB::World()->selectCol('SELECT DISTINCT `guid` FROM game_event_gameobject WHERE `eventEntry` IN %in', $eventIds))
                    return ['s.guid', $goGuids];

            return [0];
        }
        else if ($crs == parent::ENUM_NONE)
        {
            if ($eventIds = DB::Aowow()->selectCol('SELECT `id` FROM ::events WHERE `holidayId` <> 0'))
                if ($goGuids  = DB::World()->selectCol('SELECT DISTINCT `guid` FROM game_event_gameobject WHERE `eventEntry` IN %in', $eventIds))
                    return [DB::OR, ['s.guid', $goGuids, '!'], ['s.guid', null]];

            return [0];
        }
        else if (in_array($crs, self::$enums[$cr]))
        {
            if ($eventIds = DB::Aowow()->selectCol('SELECT `id` FROM ::events WHERE `holidayId` = %i', $crs))
                if ($goGuids  = DB::World()->selectCol('SELECT DISTINCT `guid` FROM game_event_gameobject WHERE `eventEntry` IN %in', $eventIds))
                    return ['s.guid', $goGuids];

            return [0];
        }

        return null;
    }

    // aowow - custom start
    private static function worldHasTable(string $tbl) : bool
    {
        static $known = [];

        return $known[$tbl] ??= (bool)DB::World()->selectCell('SHOW TABLES LIKE %s', $tbl);
    }

    // gameobject_template_addon holds it; the object page shows the same average as "Worth"
    protected function cbMoneyContained(int $cr, int $crs, string $crv) : ?array
    {
        if (!Util::checkNumeric($crv, NUM_CAST_INT) || !$this->int2Op($crs))
            return null;

        $ids = DB::World()->selectCol('SELECT `entry` FROM gameobject_template_addon WHERE FLOOR((`mingold` + `maxgold`) / 2) '.$crs.' %i', $crv);

        return $ids ? ['id', $ids] : [0];
    }

    // the same lookup the npc filter does: a faction and its children, resolved to their templates
    protected function cbFaction(int $cr, int $crs, string $crv) : ?array
    {
        if (!Util::checkNumeric($crs, NUM_CAST_INT) || !in_array($crs, self::$enums[$cr]))
            return null;

        $facTpls = [];
        $facs    = new FactionList(array(DB::OR, ['parentFactionId', $crs], ['id', $crs]));
        foreach ($facs->iterate() as $__)
            $facTpls = array_merge($facTpls, $facs->getField('templateIds'));

        return $facTpls ? ['faction', $facTpls] : [0];
    }

    protected function cbVisibilityRange(int $cr, int $crs, string $crv) : ?array
    {
        $expr = GameObjectList::getVisibilityTypeSQL();

        if ($crs == parent::ENUM_ANY)
            $ids = DB::World()->selectCol('SELECT `entry` FROM gameobject_template WHERE '.$expr.' > 0');
        else if (in_array($crs, self::$enums[$cr]))
            $ids = DB::World()->selectCol('SELECT `entry` FROM gameobject_template WHERE '.$expr.' = %i', $crs);
        else
            return null;

        return $ids ? ['id', $ids] : [0];
    }

    protected function cbHasLocation(int $cr, int $crs, string $crv) : ?array
    {
        if (!$this->int2Bool($crs))
            return null;

        return ['s.typeId', null, $crs ? '!' : null];
    }

    // ScriptName / AI and StringId are only shown to staff on the object page
    protected function cbStaffString(int $cr, int $crs, string $crv, string $col) : ?array
    {
        if (!User::canSee(U_GROUP_EMPLOYEE))
            return [1];

        $crv = trim($crv);
        if ($crv === '')
            return null;

        return [$col, $crv, 'LIKE'];
    }

    // gameobject_template columns the setup step does not import
    protected function cbTemplateNum(int $cr, int $crs, string $crv, string $expr) : ?array
    {
        if (!Util::checkNumeric($crv, NUM_CAST_FLOAT) || !$this->int2Op($crs))
            return null;

        $ids = DB::World()->selectCol('SELECT `entry` FROM gameobject_template WHERE ROUND('.$expr.', 2) '.$crs.' %f', round($crv, 2));

        return $ids ? ['id', $ids] : [0];
    }

    // npcmode option => [::objectdifficulty.mapType, difficulty]
    private const array MODE_FILTER = array(
        1 => [1, 0],                                        // normal
        2 => [1, 1],                                        // heroic
        3 => [2, 0],                                        // normal 10
        4 => [2, 1],                                        // normal 25
        5 => [2, 2],                                        // heroic 10
        6 => [2, 3]                                         // heroic 25
    );

    protected function cbMode(int $cr, int $crs, string $crv) : ?array
    {
        if ($crs == parent::ENUM_ANY || $crs == parent::ENUM_NONE)
        {
            $ids = GameObjectList::getIdsByMode();
            if ($crs == parent::ENUM_NONE)
                return $ids ? ['id', $ids, '!'] : [1];
        }
        else if (isset(self::MODE_FILTER[$crs]))
            $ids = GameObjectList::getIdsByMode(...self::MODE_FILTER[$crs]);
        else
            return null;

        return $ids ? ['id', $ids] : [0];
    }

    protected function cbDifficultySlot(int $cr, int $crs, string $crv) : ?array
    {
        if (!$this->int2Bool($crs))
            return null;

        $ids = GameObjectList::getDifficultyParents();

        if ($crs)
            return $ids ? ['id', $ids] : [0];

        return $ids ? ['id', $ids, '!'] : [1];
    }

    // [yn] criteria answered by a list of gameobject entries from the world db, matching what the object page shows
    protected function cbWorldIds(int $cr, int $crs, string $crv, string $what) : ?array
    {
        if (!$this->int2Bool($crs))
            return null;

        $ids = match ($what)
        {
            'phased'     => DB::World()->selectCol('SELECT DISTINCT `id` FROM gameobject WHERE `phaseMask` NOT IN (0, 1)'),
            'questitems' => self::worldHasTable('gameobject_questitem') ? DB::World()->selectCol('SELECT DISTINCT `GameObjectEntry` FROM gameobject_questitem WHERE `ItemId` > 0') : [],
            // GameObjectTemplate::GetGossipMenuId()
            'gossip'     => DB::World()->selectCol('SELECT `entry` FROM gameobject_template WHERE (`type` = %i AND `data3` > 0) OR (`type` = %i AND `data19` > 0)', GO_TYPE_QUESTGIVER, GO_TYPE_GOOBER),
            'smartai'    => DB::World()->selectCol('SELECT DISTINCT `entryorguid` FROM smart_scripts WHERE `source_type` = %i AND `entryorguid` > 0', SmartAI::SRC_TYPE_OBJECT),
            'pooled'     => self::worldHasTable('pool_members') ? DB::World()->selectCol('SELECT DISTINCT g.`id` FROM pool_members pm JOIN gameobject g ON g.`guid` = pm.`spawnId` WHERE pm.`type` = 1') : [],
            // GameObjectTemplate::GetServerOnly()
            'serveronly' => DB::World()->selectCol(
                               'SELECT `entry` FROM gameobject_template WHERE (`type` = %i AND `data2` > 0) OR (`type` = %i AND `data8` > 0) OR (`type` = %i AND `data3` > 0) OR (`type` = %i AND `data6` > 0)',
                                GO_TYPE_GENERIC, GO_TYPE_TRAP, GO_TYPE_SPELL_FOCUS, GO_TYPE_AURA_GENERATOR
                            ),
            // GameObjectTemplate::IsUsableMounted()
            'mounted'    => DB::World()->selectCol(
                               'SELECT `entry` FROM gameobject_template WHERE `type` = %i OR (`type` = %i AND `data8` > 0) OR (`type` = %i AND `data3` > 0) OR (`type` = %i AND `data17` > 0) OR (`type` = %i AND `data3` > 0)',
                                GO_TYPE_MAILBOX, GO_TYPE_QUESTGIVER, GO_TYPE_TEXT, GO_TYPE_GOOBER, GO_TYPE_SPELLCASTER
                            ),
            default      => null
        };

        if ($ids === null)
            return null;

        $ids = array_values(array_unique(array_map('intVal', $ids ?: [])));

        if ($crs)
            return $ids ? ['id', $ids] : [0];

        return $ids ? ['id', $ids, '!'] : [1];
    }
    // aowow - custom end
}

?>
