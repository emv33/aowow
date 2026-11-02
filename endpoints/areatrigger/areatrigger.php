<?php

namespace Aowow;

if (!defined('AOWOW_REVISION'))
    die('illegal access');


class AreatriggerBaseResponse extends TemplateResponse implements ICache
{
    use TrDetailPage, TrCache;

    protected  int    $cacheType         = CACHE_TYPE_DETAIL_PAGE;
    protected  int    $requiredUserGroup = U_GROUP_NONE;

    protected  string $template          = 'detail-page-generic';
    protected  string $pageName          = 'areatrigger';
    protected ?int    $activeTab         = parent::TAB_DATABASE;
    protected  array  $breadcrumb        = [0, 102];

    public int $type   = Type::AREATRIGGER;
    public int $typeId = 0;

    private AreaTriggerList $subject;

    public function __construct(string $id)
    {
        parent::__construct($id);

        $this->typeId     = intVal($id);
        $this->contribute = Type::getClassAttrib($this->type, 'contribute') ?? CONTRIBUTE_NONE;
    }

    protected function generate() : void
    {
        $this->applyXRef();                                 // aowow - custom

        $this->subject = new AreaTriggerList(array(['id', $this->typeId]));
        if ($this->subject->error)
            $this->generateNotFound(Lang::game('areatrigger'), Lang::areatrigger('notFound'));

        $this->h1 = $this->subject->getField('name') ?: 'Areatrigger #'.$this->typeId;

        $this->gPageInfo += array(
            'type'   => $this->type,
            'typeId' => $this->typeId,
            'name'   => $this->h1
        );


        /*************/
        /* Menu Path */
        /*************/

        $this->breadcrumb[] = $this->subject->getField('type');


        /**************/
        /* Page Title */
        /**************/

        array_unshift($this->title, $this->h1, Util::ucFirst(Lang::game('areatrigger')));


        /****************/
        /* Main Content */
        /****************/

        $_type = $this->subject->getField('type');

        // get spawns
        if ($spawns = $this->subject->getSpawns(SPAWNINFO_FULL))
        {
            $this->addDataLoader('zones');
            $this->map = array(
                ['parent' => 'mapper-generic'],             // Mapper
                $spawns,                                    // mapperData
                null,                                       // ShowOnMap
                [Lang::areatrigger('foundIn')]              // foundIn
            );
            foreach ($spawns as $areaId => $_)
                $this->map[3][$areaId] = ZoneList::getName($areaId);
        }

        // aowow - custom start: spawns without a drawable point (e.g. an instance with no extracted floor map) - name the zone/instance anyway
        foreach ($this->subject->getSpawns(SPAWNINFO_ZONES) as $areaId)
            if (!isset($spawns[$areaId]) && ($name = ZoneList::getName($areaId)))
                $this->foundInZones[$areaId] = $name;

        if ($this->foundInZones)
            $this->foundInZones = [Lang::areatrigger('foundIn')] + $this->foundInZones;
        // aowow - custom end

        // Smart AI
        if ($_type == AT_TYPE_SMART)
        {
            $sai = new SmartAI(SmartAI::SRC_TYPE_AREATRIGGER, $this->typeId, ['teleportTargetArea' => $this->subject->getField('areaId')]);
            if ($sai->prepare())
            {
                $this->extendGlobalData($sai->getJSGlobals());
                $this->smartAI = $sai->getMarkup();
            }
        }

        // aowow - custom start: infobox
        // the `areatrigger` setup step reads areatrigger_tavern, areatrigger_scripts and
        // areatrigger_teleport only to classify the trigger and name it - what those tables
        // actually say was never shown
        $infobox = [Lang::areatrigger('type').Lang::main('colon').Lang::areatrigger('types', $_type)];

        if ($_type == AT_TYPE_TAVERN)
            $infobox[] = '[span class=q2]'.Lang::areatrigger('isTavern').'[/span]';

        if ($_ = self::getScriptName($this->typeId))
            $infobox[] = Lang::areatrigger('scriptName').Lang::main('colon').$_;

        if ($dest = self::getTeleportTarget($this->typeId))
        {
            if ($dest['areaId'])
            {
                $this->extendGlobalIds(Type::ZONE, $dest['areaId']);
                $infobox[] = Lang::areatrigger('teleportsTo').Lang::main('colon').'[zone='.$dest['areaId'].']';
            }
            else
                $infobox[] = Lang::areatrigger('teleportsTo').Lang::main('colon').(WorldPosition::getMapNames([$dest['mapId']])[$dest['mapId']] ?? Lang::areatrigger('map', [$dest['mapId']]));

            $infobox[] = Lang::areatrigger('destination').Lang::main('colon').sprintf('%.1f, %.1f, %.1f', $dest['x'], $dest['y'], $dest['z']);
        }

        // entering an instance map, however the trigger gets the player there, runs MapManager::CanPlayerEnter() -> Player::Satisfy()
        // against access_requirement of the target map
        $targetMaps = self::getSmartTeleportMaps($this->typeId);
        if ($dest)
            $targetMaps[] = $dest['mapId'];

        if ($_ = $this->getAccessRequirements(array_unique($targetMaps)))
            $infobox[] = Lang::areatrigger('entryReqs').Lang::main('colon').'[ul][li]'.implode('[/li][li]', $_).'[/li][/ul]';

        $infobox[] = Lang::areatrigger('position').Lang::main('colon').sprintf('%.1f, %.1f, %.1f', $this->subject->getField('posX'), $this->subject->getField('posY'), $this->subject->getField('posZ'));

        // Player::IsInAreaTriggerRadius(): a radius makes it a sphere, otherwise it is a box of full length / width / height turned by `orientation`
        if ($_ = $this->subject->getField('radius'))
            $infobox[] = Lang::areatrigger('shape').Lang::main('colon').Lang::areatrigger('sphere', [Lang::nf($_, 1)]);
        else if ($this->subject->getField('boxLength') || $this->subject->getField('boxWidth') || $this->subject->getField('boxHeight'))
        {
            $box = Lang::areatrigger('box', [Lang::nf($this->subject->getField('boxLength'), 1), Lang::nf($this->subject->getField('boxWidth'), 1), Lang::nf($this->subject->getField('boxHeight'), 1)]);
            if ($yaw = round(rad2deg($this->subject->getField('orientation'))))
                $box .= Lang::areatrigger('boxYaw', [$yaw]);

            $infobox[] = Lang::areatrigger('shape').Lang::main('colon').$box;
        }

        $infobox[] = Lang::areatrigger('id').Lang::main('colon').$this->typeId;

        $this->infobox = new InfoboxMarkup($infobox, ['allow' => Markup::CLASS_STAFF, 'dbpage' => true], 'infobox-contents0');
        // aowow - custom end

        $this->redButtons = array(
            BUTTON_LINKS   => false,
            BUTTON_WOWHEAD => false
        );


        /**************/
        /* Extra Tabs */
        /**************/

        $this->lvTabs = new Tabs(['parent' => "\$\$WH.ge('tabs-generic')"], 'tabsRelated', true);

        // tab: conditions
        $cnd = new Conditions();
        $cnd->getBySource(Conditions::SRC_AREATRIGGER_CLIENT, entry: $this->typeId)->prepare();
        if ($tab = $cnd->toListviewTab())
        {
            $this->extendGlobalData($cnd->getJSGlobals());
            $this->lvTabs->addDataTab(...$tab);
        }

        if ($_type == AT_TYPE_OBJECTIVE)
        {
            $relQuest = new QuestList(array(['id', $this->subject->getField('quest')]));
            if (!$relQuest->error)
            {
                $this->extendGlobalData($relQuest->getJSGlobals(GLOBALINFO_SELF | GLOBALINFO_REWARDS));
                $this->lvTabs->addListviewTab(new Listview(['data' => $relQuest->getListviewData()], QuestList::$brickFile));
            }
        }
        else if ($_type == AT_TYPE_TELEPORT)
        {
            $relZone = new ZoneList(array(['id', $this->subject->getField('areaId')]));
            if (!$relZone->error)
                $this->lvTabs->addListviewTab(new Listview(['data' => $relZone->getListviewData()], ZoneList::$brickFile));
        }
        else if ($_type == AT_TYPE_SCRIPT)
        {
            $relTrigger = new AreaTriggerList(array(['id', $this->typeId, '!'], ['name', $this->subject->getField('name')]));
            if (!$relTrigger->error)
                $this->lvTabs->addListviewTab(new Listview(['data' => $relTrigger->getListviewData(), 'name' => Util::ucFirst(Lang::game('areatrigger'))]), AreaTriggerList::$brickFile, 'areatrigger');
        }

        parent::generate();
    }

    // aowow - custom start: lookups for the infobox above

    private static function hasTable(string $tbl) : bool
    {
        static $known = [];

        return $known[$tbl] ??= (bool)DB::World()->selectCell('SHOW TABLES LIKE %s', $tbl);
    }

    private static function getScriptName(int $id) : string
    {
        if (!self::hasTable('areatrigger_scripts'))
            return '';

        return (string)DB::World()->selectCell('SELECT `ScriptName` FROM areatrigger_scripts WHERE `entry` = %i', $id);
    }

    private static function getTeleportTarget(int $id) : ?array
    {
        if (!self::hasTable('areatrigger_teleport'))
            return null;

        $r = DB::World()->selectRow(
           'SELECT `target_map` AS "mapId", `target_position_x` AS "x", `target_position_y` AS "y", `target_position_z` AS "z"
            FROM   areatrigger_teleport WHERE `ID` = %i', $id
        );

        if (!$r)
            return null;

        $mapId = (int)$r['mapId'];
        $x     = (float)$r['x'];
        $y     = (float)$r['y'];

        // the zone actually containing the destination point, not just some zone of the map -
        // a map can (and mostly does) hold several
        if ($points = WorldPosition::toZonePos($mapId, $x, $y))
            $areaId = (int)(count($points) > 1 ? WorldPosition::checkZonePos($points) : $points[0])['areaId'];
        else
            $areaId = (int)DB::Aowow()->selectCell('SELECT `id` FROM ::zones WHERE `mapId` = %i AND `parentArea` = 0 AND (`cuFlags` & %i) = 0 LIMIT 1', $mapId, CUSTOM_EXCLUDE_FOR_LISTVIEW);

        return array(
            'mapId'    => $mapId,
            'areaId'   => $areaId,
            'x'        => $x,
            'y'        => $y,
            'z'        => (float)$r['z']
        );
    }

    /** maps a SmartAI script on this trigger teleports to */
    private static function getSmartTeleportMaps(int $id) : array
    {
        return array_map('intval', DB::World()->selectCol(
           'SELECT DISTINCT `action_param1` FROM smart_scripts WHERE `source_type` = %i AND `entryorguid` = %i AND `action_type` = %i',
            SmartAI::SRC_TYPE_AREATRIGGER, $id, SmartAction::ACTION_TELEPORT
        ));
    }

    /** access_requirement per target map and difficulty, as Player::Satisfy() and LFGMgr read it */
    private function getAccessRequirements(array $mapIds) : array
    {
        if (!$mapIds || !self::hasTable('access_requirement'))
            return [];

        $rows = DB::World()->selectAssoc(
           'SELECT `mapId`, `difficulty`, `level_min`, `level_max`, `item_level`, `item`, `item2`, `quest_done_A`, `quest_done_H`, `completed_achievement`, `quest_failed_text`
            FROM   access_requirement
            WHERE  `mapId` IN %in
            ORDER BY `mapId`, `difficulty`', $mapIds
        );
        if (!$rows)
            return [];

        // difficulty names only mean something for maps that have more than one; g_zone_instancetypes as set by the zones setup step
        $mapTypes = DB::Aowow()->selectCol('SELECT `mapIdBak` AS ARRAY_KEY, `type` FROM ::zones WHERE `mapIdBak` IN %in AND `parentArea` = 0', $mapIds);
        $mapNames = count(array_unique(array_column($rows, 'mapId'))) > 1 ? WorldPosition::getMapNames($mapIds) : [];

        $out = [];
        foreach ($rows as $r)
        {
            $reqs = [];

            if ($r['level_min'] && $r['level_max'])
                $reqs[] = Lang::areatrigger('reqLevelRange', [$r['level_min'], $r['level_max']]);
            else if ($r['level_min'])
                $reqs[] = Lang::areatrigger('reqLevelMin', [$r['level_min']]);
            else if ($r['level_max'])
                $reqs[] = Lang::areatrigger('reqLevelMax', [$r['level_max']]);

            // either of the two items will do
            if ($items = array_values(array_unique(array_filter([$r['item'], $r['item2']]))))
            {
                foreach ($items as $i)
                    $this->extendGlobalIds(Type::ITEM, $i);

                $reqs[] = Lang::concat($items, Lang::CONCAT_OR, fn($i) => '[item='.$i.']');
            }

            if ($r['quest_done_A'] && $r['quest_done_A'] == $r['quest_done_H'])
            {
                $this->extendGlobalIds(Type::QUEST, $r['quest_done_A']);
                $reqs[] = Lang::areatrigger('reqQuest', ['[quest='.$r['quest_done_A'].']']);
            }
            else
            {
                if ($r['quest_done_A'])
                {
                    $this->extendGlobalIds(Type::QUEST, $r['quest_done_A']);
                    $reqs[] = '[span class=icon-alliance]'.Lang::areatrigger('reqQuest', ['[quest='.$r['quest_done_A'].']']).'[/span]';
                }
                if ($r['quest_done_H'])
                {
                    $this->extendGlobalIds(Type::QUEST, $r['quest_done_H']);
                    $reqs[] = '[span class=icon-horde]'.Lang::areatrigger('reqQuest', ['[quest='.$r['quest_done_H'].']']).'[/span]';
                }
            }

            // checked on the group leader, not the player entering
            if ($r['completed_achievement'])
            {
                $this->extendGlobalIds(Type::ACHIEVEMENT, $r['completed_achievement']);
                $reqs[] = Lang::areatrigger('reqAchievement', ['[achievement='.$r['completed_achievement'].']']);
            }

            // only the Dungeon Finder checks this one
            if ($r['item_level'])
                $reqs[] = Lang::areatrigger('reqItemLevel', [$r['item_level']]);

            if ($r['quest_failed_text'])
                $reqs[] = Lang::areatrigger('failText', [strtr($r['quest_failed_text'], ['[' => '(', ']' => ')'])]);

            if (!$reqs)
                continue;

            $label = match ((int)($mapTypes[$r['mapId']] ?? -1))
            {
                MAP_TYPE_DUNGEON_HC                          => Lang::game('modes', 1, $r['difficulty']),
                MAP_TYPE_MMODE_RAID, MAP_TYPE_MMODE_RAID_HC  => Lang::game('modes', 2, $r['difficulty']),
                default                                      => ''
            };

            if (isset($mapNames[$r['mapId']]))
                $label = $mapNames[$r['mapId']].($label ? ' ('.$label.')' : '');

            $out[] = ($label ? $label.Lang::main('colon') : '').implode(', ', $reqs);
        }

        return $out;
    }
    // aowow - custom end
}

?>
