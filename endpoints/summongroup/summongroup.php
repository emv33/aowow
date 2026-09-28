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
    protected  int    $requiredUserGroup = U_GROUP_NONE;

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
           'SELECT `entry`, `position_x`, `position_y`, `summonType`, `summonTime`, COUNT(*) AS "n"
            FROM creature_summon_groups
            WHERE `summonerId` = %i AND `summonerType` = %i AND `groupId` = %i
            GROUP BY `entry`, `position_x`, `position_y`, `summonType`, `summonTime`',
            $sId, $sType, $gId
        ) ?: [];

        if (!$rows)
            $this->generateNotFound(Lang::summongroup('title'), Lang::summongroup('notFound'));

        // members grouped by entry (count summed across duplicate spawn points), every distinct
        // spawn point kept separately for the map below
        $members    = [];                                   // entry => n
        $points     = [];                                   // [entry, x, y][]
        $summonType = (int)$rows[0]['summonType'];
        $summonTime = (int)$rows[0]['summonTime'];

        foreach ($rows as $r)
        {
            $entry = (int)$r['entry'];
            $members[$entry] = ($members[$entry] ?? 0) + (int)$r['n'];
            $points[]         = [$entry, (float)$r['position_x'], (float)$r['position_y']];
        }

        $mapId = match ($sType)
        {
            SUMMONER_TYPE_CREATURE   => (int)(DB::World()->selectCell('SELECT `map` FROM creature WHERE `id` = %i', $sId) ?? 0),
            SUMMONER_TYPE_GAMEOBJECT => (int)(DB::World()->selectCell('SELECT `map` FROM gameobject WHERE `id` = %i', $sId) ?? 0),
            default                  => $sId                // SUMMONER_TYPE_MAP - summonerId is the map itself
        };


        /**************/
        /* Page Title */
        /**************/

        $summonerLabel = match ($sType)
        {
            SUMMONER_TYPE_CREATURE   => (string)(CreatureList::getName($sId) ?? ('#'.$sId)),
            SUMMONER_TYPE_GAMEOBJECT => (string)(GameObjectList::getName($sId) ?? ('#'.$sId)),
            default                  => self::mapName($sId)
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
        $infobox[] = Lang::summongroup('summonType').Lang::main('colon').(Lang::smartAI('summonTypes', $summonType) ?? Lang::smartAI('summonTypeUNK', [$summonType]));

        if ($summonTime)
            $infobox[] = Lang::summongroup('summonTime').Lang::main('colon').DateTime::formatTimeElapsedFloat($summonTime);

        $memberIds = array_keys($members);
        $this->extendGlobalIds(Type::NPC, ...$memberIds);
        $infobox[] = Lang::summongroup('members').Lang::main('colon').Lang::concat(
            array_map(fn($id) => $members[$id].'x [npc='.$id.']', $memberIds)
        );

        $this->infobox = new InfoboxMarkup($infobox, ['allow' => Markup::CLASS_STAFF, 'dbpage' => true], 'infobox-contents0');


        /*******/
        /* Map */
        /*******/

        if ($mapId)
        {
            $data = [];
            foreach ($points as [$entry, $x, $y])
            {
                if (!$x && !$y)
                    continue;

                $pt = WorldPosition::toZonePos($mapId, $x, $y);
                if (!$pt)
                    continue;

                $p    = $pt[0];
                $name = (string)(CreatureList::getName($entry) ?? ('#'.$entry));

                $data[$p['areaId']][$p['floor']]['coords'][] = [$p['posX'], $p['posY'], ['label' => "\0$<br /><span class=\"q0\">".htmlspecialchars($name).'</span>']];
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
                    [Lang::summongroup('members')]
                );
                foreach ($data as $areaId => $__)
                    $this->map[3][$areaId] = ZoneList::getName($areaId);

                $this->extendGlobalIds(Type::ZONE, ...array_keys($data));
            }
        }

        parent::generate();
    }

    /** SUMMONER_TYPE_MAP has no owning entity - name the map instead, same fallback graveyards.php uses */
    private static function mapName(int $mapId) : string
    {
        if (DB::Aowow()->selectCell('SHOW TABLES LIKE %s', 'dbc_map'))
        {
            $name = DB::Aowow()->selectCell('SELECT `name_loc'.Lang::getLocale()->value.'` FROM dbc_map WHERE `id` = %i', $mapId);
            if ($name !== null && $name !== '')
                return (string)$name;
        }

        return match ($mapId)
        {
            0   => Lang::maps('EasternKingdoms'),
            1   => Lang::maps('Kalimdor'),
            530 => Lang::maps('Outland'),
            571 => Lang::maps('Northrend'),
            default => '#'.$mapId
        };
    }
}

?>
