<?php

namespace Aowow;

if (!defined('AOWOW_REVISION'))
    die('illegal access');


/*
 * A single graveyard out of `game_graveyard` / `graveyard_zone` - see graveyards.php for how the
 * two tables (and their renamed counterparts) split the data.
 *
 * The listing sent every row to the zone page of its first ghost zone; a graveyard resurrecting
 * several zones has no single zone to represent it, and the zone page it did land on had no way
 * to say which of its (possibly several) graveyards this even was. This gives the row its own page.
 */
class GraveyardBaseResponse extends TemplateResponse implements ICache
{
    use TrDetailPage, TrCache;

    protected  int    $cacheType         = CACHE_TYPE_DETAIL_PAGE;
    protected  int    $requiredUserGroup = U_GROUP_NONE;

    protected  string $template          = 'detail-page-generic';
    protected  string $pageName          = 'graveyard';
    protected ?int    $activeTab         = parent::TAB_DATABASE;
    protected  array  $breadcrumb        = [0, 113];

    public int $type   = -2;    // no Type:: entry - no DBTypeList backs this ad hoc table read; shared sentinel with GraveyardsBaseResponse
    public int $typeId = 0;

    public function __construct(string $id)
    {
        parent::__construct($id);

        $this->typeId = intVal($id);
    }

    protected function generate() : void
    {
        $zoneTbl = self::hasTable('graveyard_zone')
            ? 'graveyard_zone'
            : (self::hasTable('game_graveyard_zone') ? 'game_graveyard_zone' : null);

        if (!$this->typeId || !$zoneTbl)
            $this->generateNotFound(Lang::graveyard('title'), Lang::graveyard('notFound'));

        $zones   = [];
        $sides   = [];                                      // zoneId => 1 Alliance, 2 Horde, 3 both - none: open to everyone
        $comment = '';

        foreach (DB::World()->selectAssoc('SELECT * FROM '.$zoneTbl.' WHERE `id` = %i', $this->typeId) ?: [] as $r)
        {
            $lc = array_change_key_case($r, CASE_LOWER);
            $zone = (int)($lc['ghostzone'] ?? $lc['ghost_zone'] ?? 0);
            $zones[$zone] = true;

            // the link table stores the faction template id: 469 Alliance, 67 Horde, 0 any
            $faction = (int)($lc['faction'] ?? 0);
            if ($faction == 469)
                $sides[$zone] = ($sides[$zone] ?? 0) | 1;
            else if ($faction == 67)
                $sides[$zone] = ($sides[$zone] ?? 0) | 2;

            if (($_ = trim((string)($lc['comment'] ?? ''))) !== '')
                $comment = $_;
        }

        if (!$zones)
            $this->generateNotFound(Lang::graveyard('title'), Lang::graveyard('notFound'));

        unset($zones[0]);
        $zones = array_keys($zones);
        sort($zones);

        // optional position table on cores that still ship it; its name, map and exact position win where present
        $name  = '';
        $mapId = null;                                      // map 0 is Eastern Kingdoms, so "unknown" is null
        $posX  = 0.0;
        $posY  = 0.0;

        if (self::hasTable('game_graveyard'))
        {
            $pos = DB::World()->selectRow('SELECT * FROM game_graveyard WHERE `id` = %i', $this->typeId);
            if ($pos)
            {
                $lc    = array_change_key_case($pos, CASE_LOWER);
                $name  = trim((string)($lc['comment'] ?? $lc['name'] ?? ''));
                $mapId = $lc['map'] ?? $lc['mapid'] ?? $lc['map_id'] ?? null;
                $mapId = $mapId === null ? null : (int)$mapId;
                $posX  = (float)($lc['position_x'] ?? $lc['x'] ?? 0);
                $posY  = (float)($lc['position_y'] ?? $lc['y'] ?? 0);
            }
        }

        // the client's WorldSafeLocs.dbc has the position of every graveyard, under the id the link table uses
        if (!$posX && !$posY && DB::Aowow()->selectCell('SHOW TABLES LIKE %s', 'dbc_worldsafelocs'))
            if ($loc = DB::Aowow()->selectRow('SELECT `mapId`, `posX`, `posY` FROM dbc_worldsafelocs WHERE `id` = %i', $this->typeId))
            {
                $mapId = (int)$loc['mapId'];
                $posX  = (float)$loc['posX'];
                $posY  = (float)$loc['posY'];
            }

        if ($name === '')
            $name = $comment;

        if ($mapId === null && $zones)
        {
            $mapByZone = DB::Aowow()->selectCell('SELECT `mapId` FROM ::zones WHERE `id` = %i', $zones[0]);
            $mapId     = $mapByZone === null || $mapByZone === false ? null : (int)$mapByZone;
        }


        /**************/
        /* Page Title */
        /**************/

        // a graveyard can resurrect several zones at once - unlike taxipath/teleport's single zone,
        // an unnamed row here falls back to the whole list rather than picking just one
        if ($name !== '')
            $this->h1 = $name[0] == '$' ? ' '.$name : $name;
        else
        {
            $zoneNames = array_values(array_filter(array_map(fn($z) => (string)(ZoneList::getName($z) ?? ''), $zones)));
            $this->h1  = $zoneNames ? Lang::graveyard('unnamed', [implode(', ', $zoneNames)]) : Lang::zone('graveyardUnnamed', [$this->typeId]);
        }

        array_unshift($this->title, $this->h1, Util::ucFirst(Lang::graveyard('title')));


        /****************/
        /* Main Content */
        /****************/

        $this->redButtons = [BUTTON_LINKS => false, BUTTON_WOWHEAD => false];

        $infobox = [Lang::graveyard('id').Lang::main('colon').$this->typeId];

        if ($zones)
            $this->extendGlobalIds(Type::ZONE, ...$zones);

        // the position, on the map of the zone it lies in; a point in no zone is still somewhere on a continent,
        // and an instance has neither
        if ($mapId !== null && ($posX || $posY))
        {
            $mapData = [];
            $names   = [];

            if ($pt = WorldPosition::toZonePos($mapId, $posX, $posY))
            {
                $p      = $pt[0];
                $areaId = (int)$p['areaId'];

                $this->extendGlobalIds(Type::ZONE, $areaId);
                $mapData[$areaId][(int)$p['floor']] = ['coords' => [[$p['posX'], $p['posY'], []]], 'count' => 1];
                $names[$areaId] = ZoneList::getName($areaId);
            }

            // only for a point in no zone, the zone's own map shows it larger
            if (!$mapData && ($cp = WorldPosition::toContinentPos($mapId, $posX, $posY)))
            {
                $mapData[$cp[0]][0] = ['coords' => [[$cp[1], $cp[2], []]], 'count' => 1];
                $names[$cp[0]]      = array(-3 => Lang::maps('EasternKingdoms'), -6 => Lang::maps('Kalimdor'), -2 => Lang::maps('Outland'), -5 => Lang::maps('Northrend'))[$cp[0]];
            }

            if ($mapData)
            {
                $this->addDataLoader('zones');
                $this->map = array(
                    ['parent' => 'mapper-generic'],
                    $mapData,
                    null,
                    [Lang::graveyard('foundIn')]            // triggers the auto-click in mapper.tpl.php that sets the
                );                                          // mapper's zone - without it the map defaults to zone 0 (black)
                $this->map[3] += $names;
            }
        }

        if ($mapId !== null)
            $infobox[] = Lang::graveyard('map').Lang::main('colon').(WorldPosition::getMapNames([$mapId])[$mapId] ?? $mapId);

        $this->infobox = new InfoboxMarkup($infobox, ['allow' => Markup::CLASS_STAFF, 'dbpage' => true], 'infobox-contents0');


        /**************/
        /* Extra Tabs */
        /**************/

        $this->lvTabs = new Tabs(['parent' => "\$\$WH.ge('tabs-generic')"], 'tabsRelated', true);

        // the zones whose dead it resurrects, with the side each link is restricted to
        if ($zones && !($zl = new ZoneList([['id', $zones]]))->error)
        {
            $this->extendGlobalData($zl->getJSGlobals());
            $this->addDataLoader('zones');

            // aowow - custom start: links the core never sends a ghost through, and the graveyard it picks instead
            $checkUse = $zoneTbl == 'graveyard_zone' && DB::Aowow()->selectCell('SHOW TABLES LIKE %s', 'dbc_worldsafelocs') && DB::Aowow()->selectCell('SHOW TABLES LIKE %s', 'dbc_map');
            $winNames = [];
            // aowow - custom end

            $data = $zl->getListviewData();
            foreach ($data as &$row)
            {
                $row['side'] = $sides[$row['id']] ?? 0;

                // aowow - custom start
                if (!$checkUse)
                    continue;

                $unused = [];                               // winner => side bits
                foreach (Graveyard::supersededBy($this->typeId, $row['id']) as $side => $winner)
                    $unused[$winner] = ($unused[$winner] ?? 0) | $side;

                foreach ($unused as $winner => $bits)
                {
                    if ($winner && !isset($winNames[$winner]))
                        $winNames[$winner] = trim((string)DB::World()->selectCell('SELECT `Comment` FROM graveyard_zone WHERE `ID` = %i AND `Comment` <> "" LIMIT 1', $winner)) ?: Lang::zone('graveyardUnnamed', [$winner]);

                    $row['unused'][] = [$bits, $winner, $winner ? $winNames[$winner] : ''];
                }
                // aowow - custom end
            }
            unset($row);

            $this->lvTabs->addListviewTab(new Listview(array(
                'data'      => $data,
                'name'      => Lang::graveyard('resurrects'),
                'id'        => 'resurrects',
                'extraCols' => ['$Listview.extraCols.graveyardSide'],
                'onBeforeCreate' => '$Listview.funcBox.beforeGraveyardZones'   // aowow - custom: notes on unused links
            ), ZoneList::$brickFile, 'graveyardSideCol'));
        }

        parent::generate();
    }

    private static function hasTable(string $tbl) : bool
    {
        static $known = [];

        return $known[$tbl] ??= (bool)DB::World()->selectCell('SHOW TABLES LIKE %s', $tbl);
    }
}

?>
