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
        $ally    = false;
        $horde   = false;
        $comment = '';

        foreach (DB::World()->selectAssoc('SELECT * FROM '.$zoneTbl.' WHERE `id` = %i', $this->typeId) ?: [] as $r)
        {
            $lc = array_change_key_case($r, CASE_LOWER);
            $zones[(int)($lc['ghostzone'] ?? $lc['ghost_zone'] ?? 0)] = true;

            // the link table stores the faction template id: 469 Alliance, 67 Horde, 0 any
            $faction = (int)($lc['faction'] ?? 0);
            if ($faction == 469)
                $ally = true;
            else if ($faction == 67)
                $horde = true;

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
        {
            $this->extendGlobalIds(Type::ZONE, ...$zones);
            $infobox[] = Lang::graveyard('resurrects').Lang::main('colon').implode(', ', array_map(fn($z) => '[zone='.$z.']', $zones));
        }

        // the side's emblem in front of its name, as on the other pages; both sides get both
        $sides = [];
        if ($ally)
            $sides[] = '[span class=icon-alliance]'.Lang::graveyard('alliance').'[/span]';
        if ($horde)
            $sides[] = '[span class=icon-horde]'.Lang::graveyard('horde').'[/span]';

        $factionLbl = $sides ? implode(Lang::main('comma'), $sides) : Lang::graveyard('neutral');

        $infobox[] = Lang::graveyard('faction').Lang::main('colon').$factionLbl;

        // a point only when the position table actually named one; the link-table-only case has
        // no coordinates at all, same gap the listing lives with
        if ($mapId !== null && ($posX || $posY) && ($pt = WorldPosition::toZonePos($mapId, $posX, $posY)))
        {
            $p      = $pt[0];
            $areaId = (int)$p['areaId'];

            $this->extendGlobalIds(Type::ZONE, $areaId);

            $this->addDataLoader('zones');
            $this->map = array(
                ['parent' => 'mapper-generic'],
                [$areaId => [(int)$p['floor'] => ['coords' => [[$p['posX'], $p['posY'], []]], 'count' => 1]]],
                null,
                [Lang::graveyard('foundIn')]            // triggers the auto-click in mapper.tpl.php that sets the
            );                                          // mapper's zone - without it the map defaults to zone 0 (black)
            $this->map[3][$areaId] = ZoneList::getName($areaId);
        }
        else if ($mapId !== null)
            $infobox[] = Lang::graveyard('map').Lang::main('colon').$mapId;

        $this->infobox = new InfoboxMarkup($infobox, ['allow' => Markup::CLASS_STAFF, 'dbpage' => true], 'infobox-contents0');

        parent::generate();
    }

    private static function hasTable(string $tbl) : bool
    {
        static $known = [];

        return $known[$tbl] ??= (bool)DB::World()->selectCell('SHOW TABLES LIKE %s', $tbl);
    }
}

?>
