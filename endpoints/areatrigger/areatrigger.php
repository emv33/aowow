<?php

namespace Aowow;

if (!defined('AOWOW_REVISION'))
    die('illegal access');


class AreatriggerBaseResponse extends TemplateResponse implements ICache
{
    use TrDetailPage, TrCache;

    protected  int    $cacheType         = CACHE_TYPE_DETAIL_PAGE;
    protected  int    $requiredUserGroup = U_GROUP_STAFF;

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
                $infobox[] = Lang::areatrigger('teleportsTo').Lang::main('colon').Lang::areatrigger('map', [$dest['mapId']]);

            $infobox[] = Lang::areatrigger('destination').Lang::main('colon').'[small class=q0]'.sprintf('%.1f, %.1f, %.1f', $dest['x'], $dest['y'], $dest['z']).'[/small]';

            if ($dest['reqLevel'])
                $infobox[] = Lang::main('_reqLevel').Lang::main('colon').$dest['reqLevel'];

            if ($dest['reqItem'])
            {
                $this->extendGlobalIds(Type::ITEM, $dest['reqItem']);
                $infobox[] = Lang::areatrigger('reqItem').Lang::main('colon').'[item='.$dest['reqItem'].']';
            }
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

    /** the requirement columns moved out of areatrigger_teleport at some point; try both spellings */
    private static function getTeleportTarget(int $id) : ?array
    {
        if (!self::hasTable('areatrigger_teleport'))
            return null;

        $r = DB::World()->selectRow(
           'SELECT `target_map` AS "mapId", `target_position_x` AS "x", `target_position_y` AS "y", `target_position_z` AS "z",
                   `required_level` AS "reqLevel", `required_item` AS "reqItem"
            FROM   areatrigger_teleport WHERE `ID` = %i', $id
        );

        if ($r === null)
            $r = DB::World()->selectRow(
               'SELECT `target_map` AS "mapId", `target_position_x` AS "x", `target_position_y` AS "y", `target_position_z` AS "z",
                       0 AS "reqLevel", 0 AS "reqItem"
                FROM   areatrigger_teleport WHERE `ID` = %i', $id
            );

        if (!$r)
            return null;

        $mapId = (int)$r['mapId'];

        return array(
            'mapId'    => $mapId,
            'areaId'   => (int)DB::Aowow()->selectCell('SELECT `id` FROM ::zones WHERE `mapId` = %i AND `parentArea` = 0 AND (`cuFlags` & %i) = 0 LIMIT 1', $mapId, CUSTOM_EXCLUDE_FOR_LISTVIEW),
            'x'        => (float)$r['x'],
            'y'        => (float)$r['y'],
            'z'        => (float)$r['z'],
            'reqLevel' => (int)$r['reqLevel'],
            'reqItem'  => (int)$r['reqItem']
        );
    }
    // aowow - custom end
}

?>
