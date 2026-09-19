<?php

namespace Aowow;

if (!defined('AOWOW_REVISION'))
    die('illegal access');


/*
 * A single row of `points_of_interest` - see pois.php for the table's story.
 *
 * The listing could only pin the point on the general maps page; this gives it its own address,
 * and adds what the listing left out - which NPCs/objects actually present a gossip menu pointing
 * at it, found the same way pois.php resolves a map for the listing (menu -> ancestor menus ->
 * whichever creature/object/SmartAI script sends one of them).
 */
class PoiBaseResponse extends TemplateResponse implements ICache
{
    use TrDetailPage, TrCache;

    protected  int    $cacheType         = CACHE_TYPE_DETAIL_PAGE;
    protected  int    $requiredUserGroup = U_GROUP_STAFF;

    protected  string $template          = 'detail-page-generic';
    protected  string $pageName          = 'poi';
    protected ?int    $activeTab         = parent::TAB_DATABASE;
    protected  array  $breadcrumb        = [0, 116];

    public int $type   = -4;    // no Type:: entry - no DBTypeList backs this ad hoc table read; shared sentinel with PoisBaseResponse
    public int $typeId = 0;

    public function __construct(string $id)
    {
        parent::__construct($id);

        $this->typeId = intVal($id);
    }

    protected function generate() : void
    {
        if (!$this->typeId || !DB::World()->selectCell('SHOW TABLES LIKE %s', 'points_of_interest'))
            $this->generateNotFound(Lang::poi('title'), Lang::poi('notFound'));

        // 3.3.5 spells the columns differently from newer cores; both are tried, as pois.php does
        $row = DB::World()->selectRow(
           'SELECT `ID`, `PositionX` AS "x", `PositionY` AS "y", `Icon` AS "icon", `Name` AS "name" FROM points_of_interest WHERE `ID` = %i',
            $this->typeId
        );
        if (!$row)
            $row = DB::World()->selectRow(
               'SELECT `entry` AS "ID", `x`, `y`, `icon`, `icon_name` AS "name" FROM points_of_interest WHERE `entry` = %i',
                $this->typeId
            );

        if (!$row)
            $this->generateNotFound(Lang::poi('title'), Lang::poi('notFound'));

        $name = trim((string)$row['name']);
        $posX = (float)$row['x'];
        $posY = (float)$row['y'];
        $icon = (int)$row['icon'];

        [$mapId, $npcs, $objects] = $this->findOwners($this->typeId);


        /**************/
        /* Page Title */
        /**************/

        $this->h1 = $name !== '' ? ($name[0] == '$' ? ' '.$name : $name) : ('Point of interest #'.$this->typeId);

        array_unshift($this->title, $this->h1, Util::ucFirst(Lang::poi('title')));


        /****************/
        /* Main Content */
        /****************/

        $this->redButtons = [BUTTON_LINKS => false, BUTTON_WOWHEAD => false];

        $infobox = [Lang::poi('id').Lang::main('colon').$this->typeId];

        if ($npcs)
        {
            $this->extendGlobalIds(Type::NPC, ...$npcs);
            $infobox[] = Lang::poi('presentedBy').Lang::main('colon').implode(', ', array_map(fn($e) => '[npc='.$e.']', $npcs));
        }

        if ($objects)
        {
            $this->extendGlobalIds(Type::OBJECT, ...$objects);
            $infobox[] = Lang::poi('presentedBy').Lang::main('colon').implode(', ', array_map(fn($e) => '[object='.$e.']', $objects));
        }

        if ($icon)
            $infobox[] = Lang::poi('icon').Lang::main('colon').$icon;

        if ($mapId && ($pt = WorldPosition::toZonePos($mapId, $posX, $posY)))
        {
            $p      = $pt[0];
            $areaId = (int)$p['areaId'];

            $this->extendGlobalIds(Type::ZONE, $areaId);
            $infobox[] = Lang::poi('zone').Lang::main('colon').'[zone='.$areaId.']';
            $infobox[] = Lang::poi('position').Lang::main('colon').sprintf('%.1f, %.1f', $p['posX'], $p['posY']);

            $this->addDataLoader('zones');
            $this->map = array(
                ['parent' => 'mapper-generic'],
                [$areaId => [(int)$p['floor'] => ['coords' => [[$p['posX'], $p['posY'], []]], 'count' => 1]]],
                null,
                null
            );
        }

        $this->infobox = new InfoboxMarkup($infobox, ['allow' => Markup::CLASS_STAFF, 'dbpage' => true], 'infobox-contents0');

        parent::generate();
    }

    /**
     * the same menu-ancestor walk pois.php's getPoiMaps() does for the whole table, scoped to one
     * point and collecting the actual owners rather than just the map they resolve to
     *
     * @return array [mapId, npcEntries, objectEntries]
     */
    private function findOwners(int $poiId) : array
    {
        if (!self::hasTable('gossip_menu_option'))
            return [0, [], []];

        $menus = DB::World()->selectCol('SELECT DISTINCT `MenuID` FROM gossip_menu_option WHERE `ActionPoiID` = %i', $poiId) ?: [];
        if (!$menus)
            return [0, [], []];

        $allMenus = [];
        foreach ($menus as $m)
            $allMenus[(int)$m] = (int)$m;

        $frontier = array_values($allMenus);

        for ($hop = 0; $frontier && $hop < 12; $hop++)
        {
            $rows = DB::World()->selectCol('SELECT DISTINCT `MenuID` FROM gossip_menu_option WHERE `ActionMenuID` IN %in', $frontier) ?: [];

            $frontier = [];
            foreach ($rows as $m)
            {
                $m = (int)$m;
                if (!isset($allMenus[$m]))
                {
                    $allMenus[$m] = $m;
                    $frontier[]   = $m;
                }
            }
        }

        $menuIds = array_values($allMenus);
        $npcs    = [];
        $objects = [];

        if (self::hasTable('creature_template'))
            $npcs = DB::World()->selectCol('SELECT DISTINCT `entry` FROM creature_template WHERE `gossip_menu_id` IN %in', $menuIds) ?: [];

        if (self::hasTable('gameobject_template'))
            $objects = DB::World()->selectCol(
               'SELECT DISTINCT `entry` FROM gameobject_template
                WHERE (`type` = %i AND `data3` IN %in) OR (`type` = %i AND `data18` IN %in)',
                GO_TYPE_QUESTGIVER, $menuIds, GO_TYPE_GOOBER, $menuIds
            ) ?: [];

        if (self::hasTable('smart_scripts'))
        {
            $rows = DB::World()->selectAssoc(
               'SELECT `source_type` AS "srcType", `entryorguid` AS "entry"
                FROM   smart_scripts
                WHERE  `action_type` = %i AND `action_param1` IN %in',
                SmartAction::ACTION_SEND_GOSSIP_MENU, $menuIds
            ) ?: [];

            foreach ($rows as $r)
            {
                $entry = (int)$r['entry'];
                if ($entry <= 0)                                // guid-scoped scripts are left to the listing's own gap
                    continue;

                if ((int)$r['srcType'] == SmartAI::SRC_TYPE_CREATURE)
                    $npcs[] = $entry;
                else if ((int)$r['srcType'] == SmartAI::SRC_TYPE_OBJECT)
                    $objects[] = $entry;
            }
        }

        $npcs    = array_values(array_unique(array_map('intval', $npcs)));
        $objects = array_values(array_unique(array_map('intval', $objects)));

        $mapId = 0;
        if ($npcs && self::hasTable('creature'))
            $mapId = (int)DB::World()->selectCell('SELECT MIN(`map`) FROM creature WHERE `id` IN %in', $npcs);

        if (!$mapId && $objects && self::hasTable('gameobject'))
            $mapId = (int)DB::World()->selectCell('SELECT MIN(`map`) FROM gameobject WHERE `id` IN %in', $objects);

        return [$mapId, $npcs, $objects];
    }

    private static function hasTable(string $tbl) : bool
    {
        static $known = [];

        return $known[$tbl] ??= (bool)DB::World()->selectCell('SHOW TABLES LIKE %s', $tbl);
    }
}

?>
