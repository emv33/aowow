<?php

namespace Aowow;

if (!defined('AOWOW_REVISION'))
    die('illegal access');


class TaxipathBaseResponse extends TemplateResponse implements ICache
{
    use TrDetailPage, TrCache;

    protected  int    $cacheType         = CACHE_TYPE_DETAIL_PAGE;
    protected  int    $requiredUserGroup = U_GROUP_STAFF;

    protected  string $template          = 'detail-page-generic';
    protected  string $pageName          = 'taxipath';
    protected ?int    $activeTab         = parent::TAB_DATABASE;
    protected  array  $breadcrumb        = [0, 110];

    public int $type   = -6;    // no Type:: entry - no DBTypeList backs this ad hoc table read; shared sentinel with TaxipathsBaseResponse
    public int $typeId = 0;

    private array $path = [];

    public function __construct(string $id)
    {
        parent::__construct($id);

        $this->typeId = intVal($id);
    }

    protected function generate() : void
    {
        $this->path = TaxiPath::getPaths([$this->typeId])[$this->typeId] ?? [];
        if (!$this->path)
            $this->generateNotFound(Lang::game('taxipath'), Lang::taxipath('notFound'));

        $this->h1 = Lang::taxipath('route', [$this->path['from'], $this->path['to']]);

        $this->gPageInfo += ['type' => 0, 'typeId' => $this->typeId, 'name' => $this->h1];


        /**************/
        /* Page Title */
        /**************/

        array_unshift($this->title, $this->h1, Util::ucFirst(Lang::game('taxipath')));


        /****************/
        /* Main Content */
        /****************/

        $src     = TaxiPath::getSources([$this->typeId])[$this->typeId] ?? [];
        $infobox = [Lang::taxipath('id').Lang::main('colon').$this->typeId];

        foreach ([['from', 'fromArea', 'fromNpc'], ['to', 'toArea', 'toNpc']] as $i => [$nk, $ak, $npck])
        {
            $line = Lang::taxipath($i ? 'endsAt' : 'startsAt').Lang::main('colon');

            if ($_ = $this->path[$ak])
            {
                $this->extendGlobalIds(Type::ZONE, $_);
                $line .= '[zone='.$_.']';
            }
            else
                $line .= $this->path[$nk];

            $infobox[] = $line;

            if ($_ = $this->path[$npck])
            {
                $this->extendGlobalIds(Type::NPC, $_);
                $infobox[] = Lang::taxipath('flightMaster').Lang::main('colon').'[npc='.$_.']';
            }
        }

        $this->infobox = new InfoboxMarkup($infobox, ['allow' => Markup::CLASS_STAFF, 'dbpage' => true], 'infobox-contents0');

        $this->redButtons = [BUTTON_LINKS => false, BUTTON_WOWHEAD => false];

        // start/end pins, connected by a line when both ends share a zone; a node with no
        // coords (e.g. a boat's dock) just drops its own pin rather than blocking the other
        $nodes = array(
            'from' => ['area' => $this->path['fromArea'], 'x' => $this->path['fromAreaX'], 'y' => $this->path['fromAreaY'], 'type' => 'start', 'label' => Lang::taxipath('startsAt')],
            'to'   => ['area' => $this->path['toArea'],   'x' => $this->path['toAreaX'],   'y' => $this->path['toAreaY'],   'type' => 'end',   'label' => Lang::taxipath('endsAt')]
        );

        $data = [];
        foreach ($nodes as $n)
        {
            if (!$n['area'] || (!$n['x'] && !$n['y']))
                continue;

            $opts = ['label' => "\0$<br /><span class=\"q0\">".$n['label'].'</span>', 'type' => $n['type']];

            if ($n['type'] == 'end' && $nodes['from']['area'] == $nodes['to']['area'] && ($nodes['from']['x'] || $nodes['from']['y']))
                $opts['lines'] = [[$nodes['from']['x'], $nodes['from']['y']]];

            $data[$n['area']][0]['coords'][] = [$n['x'], $n['y'], $opts];
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
                [Lang::taxipath('foundIn')]
            );
            foreach ($data as $areaId => $__)
                $this->map[3][$areaId] = ZoneList::getName($areaId);
        }


        /**************/
        /* Extra Tabs */
        /**************/

        $this->lvTabs = new Tabs(['parent' => "\$\$WH.ge('tabs-generic')"], 'tabsRelated', true);

        // tab: flight masters at either end
        if ($npcIds = array_filter([$this->path['fromNpc'], $this->path['toNpc']]))
            $this->addListTab(new CreatureList([['id', array_values($npcIds)]]), CreatureList::$brickFile, Lang::taxipath('flightMasters'), 'flightmasters');

        // tab: spells sending a player down this path
        if ($_ = ($src[TaxiPath::SRC_SPELL] ?? []))
            $this->addListTab(new SpellList([['id', array_values($_)]]), SpellList::$brickFile, Lang::taxipath('sentBySpell'), 'sent-by-spell');

        // tab: the transports running it
        if ($_ = ($src[TaxiPath::SRC_OBJECT] ?? []))
            $this->addListTab(new GameObjectList([['id', array_values($_)]]), GameObjectList::$brickFile, Lang::taxipath('usedByObject'), 'used-by-object');

        // tab: SmartAI scripts activating it - a script is owned by a creature, object or areatrigger
        if ($byScript = ($src[TaxiPath::SRC_SMARTAI] ?? []))
        {
            $byType = [];
            foreach ($byScript as [$srcType, $entry])
            {
                $t = match ($srcType)
                {
                    SmartAI::SRC_TYPE_CREATURE => Type::NPC,
                    SmartAI::SRC_TYPE_OBJECT   => Type::OBJECT,
                    default                    => 0
                };

                if ($t && $entry > 0)                       // a negative entryorguid is a guid, not a template entry
                    $byType[$t][$entry] = $entry;
            }

            if ($_ = ($byType[Type::NPC] ?? []))
                $this->addListTab(new CreatureList([['id', array_values($_)]]), CreatureList::$brickFile, Lang::taxipath('sentByScript'), 'sent-by-script-npc');

            if ($_ = ($byType[Type::OBJECT] ?? []))
                $this->addListTab(new GameObjectList([['id', array_values($_)]]), GameObjectList::$brickFile, Lang::taxipath('sentByScript'), 'sent-by-script-object');
        }

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
