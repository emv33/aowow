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
class GraveyardBaseResponse extends TemplateResponse
{
    use TrDetailPage;

    protected  int    $requiredUserGroup = U_GROUP_STAFF;

    protected  string $template          = 'detail-page-generic';
    protected  string $pageName          = 'graveyard';
    protected ?int    $activeTab         = parent::TAB_DATABASE;
    protected  array  $breadcrumb        = [0, 113];

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
        $mapId = 0;
        $posX  = 0.0;
        $posY  = 0.0;

        if (self::hasTable('game_graveyard'))
        {
            $pos = DB::World()->selectRow('SELECT * FROM game_graveyard WHERE `id` = %i', $this->typeId);
            if ($pos)
            {
                $lc    = array_change_key_case($pos, CASE_LOWER);
                $name  = trim((string)($lc['comment'] ?? $lc['name'] ?? ''));
                $mapId = (int)($lc['map'] ?? $lc['mapid'] ?? $lc['map_id'] ?? 0);
                $posX  = (float)($lc['position_x'] ?? $lc['x'] ?? 0);
                $posY  = (float)($lc['position_y'] ?? $lc['y'] ?? 0);
            }
        }

        if ($name === '')
            $name = $comment;

        if (!$mapId && $zones)
        {
            $mapByZone = DB::Aowow()->selectCell('SELECT `mapId` FROM ::zones WHERE `id` = %i', $zones[0]);
            $mapId     = (int)$mapByZone;
        }


        /**************/
        /* Page Title */
        /**************/

        $this->h1 = $name !== '' ? ($name[0] == '$' ? ' '.$name : $name) : ('Graveyard #'.$this->typeId);

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

        if ($ally && $horde)
            $factionLbl = Lang::graveyard('both');
        else if ($ally)
            $factionLbl = Lang::graveyard('alliance');
        else if ($horde)
            $factionLbl = Lang::graveyard('horde');
        else
            $factionLbl = Lang::graveyard('neutral');

        $infobox[] = Lang::graveyard('faction').Lang::main('colon').$factionLbl;

        // a point only when the position table actually named one; the link-table-only case has
        // no coordinates at all, same gap the listing lives with
        if ($mapId && ($posX || $posY) && ($pt = WorldPosition::toZonePos($mapId, $posX, $posY)))
        {
            $p      = $pt[0];
            $areaId = (int)$p['areaId'];

            $this->extendGlobalIds(Type::ZONE, $areaId);

            $this->addDataLoader('zones');
            $this->map = array(
                ['parent' => 'mapper-generic'],
                [$areaId => [(int)$p['floor'] => ['coords' => [[$p['posX'], $p['posY'], []]], 'count' => 1]]],
                null,
                null
            );
        }
        else if ($mapId)
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
