<?php

namespace Aowow;

if (!defined('AOWOW_REVISION'))
    die('illegal access');


/*
 * A single summon group out of `creature_summon_groups` - see summongroups.php for the table's
 * story.
 *
 * The composite key (summonerId, summonerType, groupId) has no single column to expose as an id,
 * so the URL id is that triple joined with ':' - the same composite-string approach text.php
 * already uses for GameText's rows.
 */
class SummongroupBaseResponse extends TemplateResponse implements ICache
{
    use TrDetailPage, TrCache;

    protected  int    $cacheType         = CACHE_TYPE_DETAIL_PAGE;
    protected  int    $requiredViewGroup = U_GROUP_STAFF;

    protected  string $template          = 'detail-page-generic';
    protected  string $pageName          = 'summongroup';
    protected ?int    $activeTab         = parent::TAB_DATABASE;
    protected  array  $breadcrumb        = [0, 121];

    public int $type = -16;    // no Type:: entry - no DBTypeList backs this ad hoc table read; shared sentinel with SummongroupsBaseResponse

    private string $rowId = '';

    public function __construct(string $id)
    {
        parent::__construct($id);

        $this->rowId = $id;
    }

    // the id is a composite string ('summonerType:summonerId:groupId'), not a plain int typeId -
    // TrDetailPage's default key can't hold it, so it is folded into misc here instead
    public function getCacheKeyComponents() : array
    {
        return array($this->type, 0, User::$groups, md5($this->rowId));
    }

    protected function generate() : void
    {
        $parts = explode(':', $this->rowId);
        if (count($parts) != 3 || !DB::World()->selectCell('SHOW TABLES LIKE %s', 'creature_summon_groups'))
            $this->generateNotFound(Lang::summongroup('title'), Lang::summongroup('notFound'));

        [$sType, $sId, $gId] = array_map('intval', $parts);

        $rows = DB::World()->selectAssoc(
           'SELECT `entry`, `position_x`, `position_y`, `position_z`, `orientation`, `summonType`, `summonTime`, TRIM(IFNULL(`Comment`, "")) AS "comment"
            FROM creature_summon_groups
            WHERE `summonerId` = %i AND `summonerType` = %i AND `groupId` = %i',
            $sId, $sType, $gId
        ) ?: [];

        if (!$rows)
            $this->generateNotFound(Lang::summongroup('title'), Lang::summongroup('notFound'));

        $mapId = match ($sType)
        {
            SUMMONER_TYPE_CREATURE   => DB::World()->selectCell('SELECT `map` FROM creature WHERE `id` = %i', $sId),
            SUMMONER_TYPE_GAMEOBJECT => DB::World()->selectCell('SELECT `map` FROM gameobject WHERE `id` = %i', $sId),
            default                  => $sId                // SUMMONER_TYPE_MAP - summonerId is the map itself
        };
        $mapId = $mapId === null ? null : (int)$mapId;


        /**************/
        /* Page Title */
        /**************/

        $summonerLabel = match ($sType)
        {
            SUMMONER_TYPE_CREATURE   => (string)(CreatureList::getName($sId) ?? ('#'.$sId)),
            SUMMONER_TYPE_GAMEOBJECT => (string)(GameObjectList::getName($sId) ?? ('#'.$sId)),
            default                  => (WorldPosition::getMapNames([$sId])[$sId] ?? '#'.$sId)
        };

        $this->h1 = Lang::summongroup('titleOf', [$summonerLabel, $gId]);

        array_unshift($this->title, $this->h1, Util::ucFirst(Lang::summongroup('title')));


        /****************/
        /* Main Content */
        /****************/

        $this->redButtons = [BUTTON_LINKS => false, BUTTON_WOWHEAD => false];

        $infobox = [];

        if ($sType == SUMMONER_TYPE_CREATURE)
        {
            $this->extendGlobalIds(Type::NPC, $sId);
            $infobox[] = Lang::summongroup('summoner').Lang::main('colon').'[npc='.$sId.']';
        }
        else if ($sType == SUMMONER_TYPE_GAMEOBJECT)
        {
            $this->extendGlobalIds(Type::OBJECT, $sId);
            $infobox[] = Lang::summongroup('summoner').Lang::main('colon').'[object='.$sId.']';
        }
        else
            $infobox[] = Lang::summongroup('summoner').Lang::main('colon').$summonerLabel;

        $infobox[] = Lang::summongroup('group').Lang::main('colon').$gId;

        $this->infobox = new InfoboxMarkup($infobox, ['allow' => Markup::CLASS_STAFF, 'dbpage' => true], 'infobox-contents0');


        /***********/
        /* Members */
        /***********/

        // one row per creature_summon_groups row - what the core summons, where, and for how long; laid out like an npc's Spawns tab
        $lvData = [];
        $mapPts = [];                                       // areaId => floor => coords, for the map above
        foreach ($rows as $i => $r)
        {
            $entry = (int)$r['entry'];
            $type  = (int)$r['summonType'];
            $time  = (int)$r['summonTime'];
            $x     = (float)$r['position_x'];
            $y     = (float)$r['position_y'];

            $this->extendGlobalIds(Type::NPC, $entry);

            $row = array(
                'id'              => $i + 1,
                'npc'             => $entry,
                'position'        => implode(', ', array_map(fn($k) => Lang::nf((float)$r[$k], 2), ['position_x', 'position_y', 'position_z', 'orientation'])),
                'summonType'      => $type,
                'summonTypeLabel' => Lang::smartAI('summonTypes', $type) ?? ('#'.$type),
                'summonTime'      => $time,
                'comment'         => (string)$r['comment']
            );

            // map 0 is Eastern Kingdoms, so "unknown" is null
            $pt = $mapId !== null && ($x || $y) ? (WorldPosition::toZonePos($mapId, $x, $y)[0] ?? null) : null;
            if ($pt)
            {
                $this->extendGlobalIds(Type::ZONE, (int)$pt['areaId']);
                $row['zone']     = (int)$pt['areaId'];
                $row['location'] = '[zone='.(int)$pt['areaId'].'] [small class=q0]('.Lang::nf((float)$pt['posX'], 1).', '.Lang::nf((float)$pt['posY'], 1).')[/small]';
                if ($link = SpawnTab::mapLink((int)$pt['areaId'], (int)$pt['floor'], (float)$pt['posX'], (float)$pt['posY']))
                    $row['map'] = $link;

                $name = (string)(CreatureList::getName($entry) ?? ('#'.$entry));
                $mapPts[$pt['areaId']][$pt['floor']]['coords'][] = [$pt['posX'], $pt['posY'], ['label' => "\0$<br /><span class=\"q0\">".htmlspecialchars($name).'</span>']];
            }
            else if ($mapId !== null)
                $row['location'] = WorldPosition::getMapNames([$mapId])[$mapId] ?? ('Map #'.$mapId);
            else
                $row['location'] = '';

            $lvData[] = $row;
        }

        $hidden = [];
        if (!array_filter(array_column($lvData, 'summonTime')))
            $hidden[] = 'summonTime';
        if (!array_filter(array_column($lvData, 'comment')))
            $hidden[] = 'comment';
        if (!array_filter(array_column($lvData, 'location')))
            $hidden[] = 'location';

        $this->lvTabs = new Tabs(['parent' => "\$\$WH.ge('tabs-generic')"], 'tabsRelated', true);
        $this->lvTabs->addListviewTab(new Listview(array(
            'data'       => $lvData,
            'id'         => 'members',
            'name'       => Lang::summongroup('members'),
            'hiddenCols' => $hidden ?: null
        ), 'summongroupmember', 'summongroupmember'));


        /*******/
        /* Map */
        /*******/

        if ($mapPts)
        {
            foreach ($mapPts as &$areas)
                foreach ($areas as &$floor)
                    $floor['count'] = count($floor['coords']);
            unset($areas, $floor);

            $this->addDataLoader('zones');
            $this->map = array(
                ['parent' => 'mapper-generic'],
                $mapPts,
                null,
                [Lang::summongroup('members')]
            );
            foreach ($mapPts as $areaId => $__)
                $this->map[3][$areaId] = ZoneList::getName($areaId);

            $this->extendGlobalIds(Type::ZONE, ...array_keys($mapPts));
        }

        parent::generate();
    }
}

?>
