<?php

namespace Aowow;

if (!defined('AOWOW_REVISION'))
    die('illegal access');


class WaypointpathBaseResponse extends TemplateResponse
{
    use TrDetailPage;

    protected  int    $requiredUserGroup = U_GROUP_STAFF;

    protected  string $template          = 'detail-page-generic';
    protected  string $pageName          = 'waypointpath';
    protected ?int    $activeTab         = parent::TAB_DATABASE;
    protected  array  $breadcrumb        = [0, 120];

    public int $type   = Type::WAYPOINT_PATH;
    public int $typeId = 0;

    private WaypointPathList $subject;

    public function __construct(string $id)
    {
        parent::__construct($id);

        $this->typeId = intVal($id);
    }

    protected function generate() : void
    {
        $this->subject = new WaypointPathList(array(['id', $this->typeId]));
        if ($this->subject->error)
            $this->generateNotFound(Lang::game('waypointpath'), Lang::waypointpath('notFound'));

        [$kind, $sourceId] = WaypointPathList::decodeId($this->typeId);

        $this->h1 = Lang::waypointpath('title', [$this->typeId]);

        $this->gPageInfo += array(
            'type'   => $this->type,
            'typeId' => $this->typeId,
            'name'   => $this->h1
        );


        /**************/
        /* Page Title */
        /**************/

        array_unshift($this->title, $this->h1, Util::ucFirst(Lang::game('waypointpath')));


        /****************/
        /* Main Content */
        /****************/

        // curTpl only carries the raw aggregate columns - numpoints/totalwait/npc are derived,
        // same as every listview row; reuse that instead of re-deriving the npc owner here
        $row   = $this->subject->getListviewData()[$this->typeId] ?? [];
        $npcId = $row['npc']  ?? 0;
        $guid  = $row['guid'] ?? 0;

        $infobox = array(
            Lang::waypointpath('id').Lang::main('colon').$this->typeId,
            Lang::waypointpath('kind').Lang::main('colon').Lang::waypointpath('kinds', $kind),
            Lang::waypointpath('points').Lang::main('colon').($row['numpoints'] ?? 0)
        );

        if ($_ = ($row['totalwait'] ?? 0))
            $infobox[] = Lang::waypointpath('totalWait').Lang::main('colon').DateTime::formatTimeElapsedFloat($_);

        if ($npcId)
        {
            $this->extendGlobalIds(Type::NPC, $npcId);
            $walkedBy = Lang::waypointpath('walkedBy').Lang::main('colon').'[npc='.$npcId.']';
            if ($guid)                                      // pinned to this one spawn, not every spawn of the npc
                $walkedBy .= ' [small class=q0](GUID: '.$guid.')[/small]';

            $infobox[] = $walkedBy;
        }

        $this->redButtons = [BUTTON_LINKS => false, BUTTON_WOWHEAD => false];

        // full route on the map - unlike the capped, per-NPC pins on an NPC's own spawn map
        // (dbtypelist.class.php spawnHelper::createFullSpawns()), every point of this one path
        // is shown, connected in walking order
        if ($points = WaypointPathList::getPoints($kind, $sourceId))
        {
            $data    = [];
            $areaIds = [];
            $prev    = null;

            foreach ($points as $point => $p)
            {
                if ($p['areaId'])
                    $areaIds[$p['areaId']] = $p['areaId'];

                // a creature on a map with no explorable world map (e.g. most instances) gets
                // its point placed with a zone but no coordinates - nothing to draw a pin at
                if (!$p['posX'] && !$p['posY'])
                {
                    $prev = null;
                    continue;
                }

                $label = [Lang::npc('waypoint').Lang::main('colon').$point];
                if ($p['wait'])
                    $label[] = Lang::npc('wait').Lang::main('colon').DateTime::formatTimeElapsedFloat($p['wait']);

                $opts = ['label' => "\0$<br /><span class=\"q0\">".implode('<br />', $label).'</span>'];

                if ($prev && $prev['areaId'] == $p['areaId'] && $prev['floor'] == $p['floor'])
                    $opts['lines'] = [[$prev['posX'], $prev['posY']]];

                $data[$p['areaId']][$p['floor']]['coords'][] = [$p['posX'], $p['posY'], $opts];
                $prev = $p;
            }

            if ($data)
            {
                foreach ($data as &$areas)
                    foreach ($areas as &$floor)
                        $floor['count'] = count($floor['coords']);
                unset($areas, $floor);

                $this->addDataLoader('zones');
                $this->map = array(
                    ['parent' => 'mapper-generic'],
                    $data,
                    null,
                    [Lang::waypointpath('foundIn')]
                );
                foreach ($data as $areaId => $__)
                    $this->map[3][$areaId] = ZoneList::getName($areaId);
            }
            else if ($areaIds)
            {
                // no world map to pin on at all (e.g. an instance) - name the zone(s) instead
                $this->extendGlobalIds(Type::ZONE, ...array_values($areaIds));
                $infobox[] = Lang::waypointpath('foundIn').Lang::main('colon').implode(', ', array_map(fn($a) => '[zone='.$a.']', $areaIds));
            }
        }

        $this->infobox = new InfoboxMarkup($infobox, ['allow' => Markup::CLASS_STAFF, 'dbpage' => true], 'infobox-contents0');


        /**************/
        /* Extra Tabs */
        /**************/

        $this->lvTabs = new Tabs(['parent' => "\$\$WH.ge('tabs-generic')"], 'tabsRelated', true);

        // tab: SmartAI scripts that reference this path (SmartAction::ACTION_WP_START)
        $byType = SmartAI::getOwnerOfReference(Type::WAYPOINT_PATH, $this->typeId);

        if ($_ = ($byType[Type::NPC] ?? []))
            $this->addListTab(new CreatureList([['id', array_values(array_unique($_))]]), CreatureList::$brickFile, Lang::waypointpath('usedByScript'), 'used-by-script-npc');

        if ($_ = ($byType[Type::OBJECT] ?? []))
            $this->addListTab(new GameObjectList([['id', array_values(array_unique($_))]]), GameObjectList::$brickFile, Lang::waypointpath('usedByScript'), 'used-by-script-object');

        parent::generate();
    }

    private function addListTab(DBTypeList $list, string $brick, string $name, string $id) : void
    {
        if ($list->error)
            return;

        $this->extendGlobalData($list->getJSGlobals());
        $this->addDataLoader('zones');
        $this->lvTabs->addListviewTab(new Listview(['data' => $list->getListviewData(), 'name' => $name, 'id' => $id], $brick));
    }
}

?>
