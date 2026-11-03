<?php

namespace Aowow;

if (!defined('AOWOW_REVISION'))
    die('illegal access');


/*
 * A single spawn pool out of `pool_template` / `pool_members` - see Pool for how the core uses it
 * and spawnpools.php for the listing.
 *
 * The map draws every spawn point the pool (and any pool nested in it) can pick from; the infobox
 * says how many of them are live at once, which is what the map alone can't tell.
 */
class SpawnpoolBaseResponse extends TemplateResponse implements ICache
{
    use TrDetailPage, TrCache;

    protected  int    $cacheType         = CACHE_TYPE_DETAIL_PAGE;
    protected  int    $requiredViewGroup = U_GROUP_STAFF;

    protected  string $template          = 'detail-page-generic';
    protected  string $pageName          = 'spawnpool';
    protected ?int    $activeTab         = parent::TAB_DATABASE;
    protected  array  $breadcrumb        = [0, 122];

    public int $type   = -17;    // no Type:: entry - no DBTypeList backs this ad hoc table read; shared sentinel with SpawnpoolsBaseResponse
    public int $typeId = 0;

    public function __construct(string $id)
    {
        parent::__construct($id);

        $this->typeId = intVal($id);
    }

    protected function generate() : void
    {
        if ($this->typeId <= 0 || !($pool = Pool::getRows([$this->typeId])[$this->typeId] ?? null))
            $this->generateNotFound(Lang::pool('title'), Lang::pool('notFound'));

        // the spawns of this pool and of every pool nested in it, as the map and member tabs show both
        $spawns = [];                                       // [Type, guid, entry, chance]
        foreach (Pool::withChildren([$this->typeId]) as $pId)
            array_push($spawns, ...Pool::getSpawns($pId));

        $points = [];                                       // Type => entry => n
        $chance = [];                                       // guid => chance
        foreach ($spawns as [$type, $guid, $entry, $c])
        {
            $points[$type][$entry] = ($points[$type][$entry] ?? 0) + 1;
            if ($c > 0)
                $chance[$type][$guid] = $c;
        }


        /**************/
        /* Page Title */
        /**************/

        $this->h1 = $pool['name'] ?: Lang::pool('unnamed', [$this->typeId]);

        array_unshift($this->title, $this->h1, Util::ucFirst(Lang::pool('title')));


        /****************/
        /* Main Content */
        /****************/

        $this->redButtons = [BUTTON_LINKS => false, BUTTON_WOWHEAD => false];

        $infobox = [Lang::pool('id').Lang::main('colon').$this->typeId];

        $infobox[] = $pool['active'] ? Lang::pool('active', [$pool['active'], $pool['total']]) : Lang::pool('activeAll', [$pool['total']]);

        if ($pool['mother'])
        {
            $mName     = Pool::getRows([$pool['mother']])[$pool['mother']]['name'] ?? '';
            $infobox[] = Lang::pool('mother').Lang::main('colon').'[url=?spawnpool='.$pool['mother'].']'.($mName ?: Lang::pool('unnamed', [$pool['mother']])).'[/url]';
        }

        // a pool tied to a game event only spawns while the event runs; a nested pool goes with its mother's event
        if (DB::World()->selectCell('SHOW TABLES LIKE %s', 'game_event_pool'))
        {
            $events = array_map('intVal', DB::World()->selectCol('SELECT DISTINCT `eventEntry` FROM game_event_pool WHERE `pool_entry` IN %in', array_filter([$this->typeId, $pool['mother']])) ?: []);
            if ($events)
            {
                $this->extendGlobalIds(Type::WORLDEVENT, ...array_map('abs', $events));
                $infobox[] = Lang::pool('event').Lang::main('colon').Lang::concat(array_map(fn($e) => '[event='.abs($e).']', $events));
            }
        }

        if ($chance)
            $infobox[] = Lang::pool('chanced').Lang::main('colon').array_sum(array_map('count', $chance));

        $this->infobox = new InfoboxMarkup($infobox, ['allow' => Markup::CLASS_STAFF, 'dbpage' => true], 'infobox-contents0');


        /*******/
        /* Map */
        /*******/

        $names = [];                                        // Type => entry => name
        $lists = [];                                        // Type => DBTypeList
        foreach ($points as $type => $entries)
        {
            $list = Type::newList($type, [['id', array_keys($entries)]]);
            if (!$list || $list->error)
                continue;

            $lists[$type] = $list;
            foreach ($list->iterate() as $id => $__)
                $names[$type][$id] = $list->getField('name', true);
        }

        $data = [];
        foreach ([Type::NPC, Type::OBJECT] as $type)
        {
            $guids = array_column(array_filter($spawns, fn($s) => $s[0] == $type), 2, 1);
            if (!$guids)
                continue;

            foreach (DB::Aowow()->selectAssoc('SELECT `guid`, `areaId`, `floor`, `posX`, `posY` FROM ::spawns WHERE `type` = %i AND `guid` IN %in AND `posX` > 0 AND `posY` > 0', $type, array_keys($guids)) ?: [] as $s)
            {
                $entry = $guids[$s['guid']];
                $info  = ['GUID'.Lang::main('colon').$s['guid']];
                if (isset($chance[$type][$s['guid']]))
                    $info[] = '<span class="q0">'.Lang::pool('chance', [$chance[$type][$s['guid']]]).'</span>';

                $data[$s['areaId']][$s['floor']]['coords'][] = [$s['posX'], $s['posY'], array(
                    'tooltip' => [($names[$type][$entry] ?? '#'.$entry) => ['info' => $info]],
                    'url'     => '?'.Type::getFileString($type).'='.$entry,
                    'type'    => $type == Type::NPC ? 0 : 3
                )];
            }
        }

        if ($data)
        {
            foreach ($data as &$areas)
                foreach ($areas as &$floor)
                    $floor['count'] = count($floor['coords']);
            unset($areas, $floor);

            uasort($data, fn($a, $b) => current($b)['count'] <=> current($a)['count']);

            $this->addDataLoader('zones');
            $this->map = array(
                ['parent' => 'mapper-generic'],
                $data,
                null,
                [Lang::pool('spawnPoints')]
            );
            foreach ($data as $areaId => $__)
                $this->map[3][$areaId] = ZoneList::getName($areaId);
        }


        /**************/
        /* Extra Tabs */
        /**************/

        $this->lvTabs = new Tabs(['parent' => "\$\$WH.ge('tabs-generic')"], 'tabsRelated', true);

        // the members, with how many spawn points each has in the pool
        $spawnCol = "\$Listview.funcBox.createSimpleCol('spawns', LANG.spawnpool.points, '10%', 'spawns')";
        foreach ($lists as $type => $list)
        {
            $lvData = $list->getListviewData();
            foreach ($lvData as $id => &$row)
                $row['spawns'] = $points[$type][$id] ?? 0;
            unset($row);

            $this->extendGlobalData($list->getJSGlobals());
            $this->addDataLoader('zones');
            $this->lvTabs->addListviewTab(new Listview(array(
                'data'      => $lvData,
                'id'        => $type == Type::NPC ? 'npcs' : 'objects',
                'name'      => $type == Type::NPC ? '$LANG.tab_npcs' : '$LANG.tab_objects',
                'extraCols' => [$spawnCol],
                'sort'      => ['-spawns', 'name']
            ), $type == Type::NPC ? CreatureList::$brickFile : GameObjectList::$brickFile));
        }

        if ($children = Pool::getChildren($this->typeId))
        {
            $this->addDataLoader('zones');
            $this->lvTabs->addListviewTab(new Listview(array(
                'data'       => Pool::getRows($children),
                'name'       => Lang::pool('childPools'),
                'id'         => 'nested-pools',
                'hiddenCols' => ['mine']
            ), 'spawnpool', 'spawnpool'));
        }

        parent::generate();
    }
}

?>
