<?php

namespace Aowow;

if (!defined('AOWOW_REVISION'))
    die('illegal access');


/*
 * Browser over `points_of_interest`.
 *
 * The Gossip component reads the rows a gossip option points at; this enumerates the whole table,
 * so every named point on a map - inns, mailboxes, flight masters - is browsable on its own.
 */
class PoiBaseResponse extends TemplateResponse
{
    use TrListPage;

    protected  string $template   = 'poi';
    protected  string $pageName   = 'poi';
    protected  int    $requiredUserGroup = U_GROUP_STAFF;

    protected ?int    $activeTab  = parent::TAB_DATABASE;
    protected  array  $breadcrumb = [0, 116];

    protected function generate() : void
    {
        $this->h1 = Util::ucFirst(Lang::poi('title'));


        /**************/
        /* Page Title */
        /**************/

        array_unshift($this->title, $this->h1);


        /****************/
        /* Main Content */
        /****************/

        $this->redButtons[BUTTON_WOWHEAD] = false;

        $this->lvTabs = new Tabs(['parent' => "\$\$WH.ge('tabs-generic')"]);
        $this->lvTabs->addListviewTab(new Listview(['data' => $this->buildListviewData()], 'poi', 'poi'));

        parent::generate();
    }

    private function buildListviewData() : array
    {
        if (!DB::World()->selectCell('SHOW TABLES LIKE %s', 'points_of_interest'))
            return [];

        // 3.3.5 spells the columns differently from newer cores; both are tried, as in Gossip
        $rows = DB::World()->selectAssoc('SELECT `ID`, `PositionX` AS "x", `PositionY` AS "y", `Icon` AS "icon", `Name` AS "name" FROM points_of_interest ORDER BY `ID` ASC');
        if (!$rows)
            $rows = DB::World()->selectAssoc('SELECT `entry` AS "ID", `x`, `y`, `icon`, `icon_name` AS "name" FROM points_of_interest ORDER BY `entry` ASC');

        $pois = [];
        foreach ($rows ?: [] as $r)
        {
            $name = trim((string)$r['name']);
            $pois[(int)$r['ID']] = array(
                'id'   => (int)$r['ID'],
                'name' => $name !== '' && $name[0] == '$' ? ' '.$name : $name,
                'x'    => (float)$r['x'],
                'y'    => (float)$r['y'],
                'icon' => (int)$r['icon']
            );
        }

        // the table carries no map id, so the map a point belongs to is reached through the
        // gossip menus whose options point at it
        $maps = $this->getPoiMaps(array_keys($pois));

        $jsg  = [];
        $data = [];
        foreach ($pois as $poi)
        {
            $mapId = $maps[$poi['id']] ?? 0;
            if ($mapId && ($pt = WorldPosition::toZonePos($mapId, $poi['x'], $poi['y'])))
            {
                $poi['zone']    = (int)$pt[0]['areaId'];
                $poi['maplink'] = '?maps='.$poi['zone'].':'.self::pinStr($pt[0]['posX']).self::pinStr($pt[0]['posY']);

                $jsg[Type::ZONE][$poi['zone']] = $poi['zone'];
            }

            $data[] = $poi;
        }

        $this->extendGlobalData($jsg);

        return $data;
    }

    /**
     * a point of interest carries no map id of its own; the map it belongs to is only reachable
     * through the menus whose options point at it (`gossip_menu_option`.`ActionPoiID`), and from
     * there to whichever NPCs/objects actually present that menu - either as their default
     * `gossip_menu_id`/`data3`/`data18`, sent explicitly by a SmartAI script, or as a submenu a
     * parent menu opens via its own option's `ActionMenuID` (most poi options sit several
     * submenus deep, not on an NPC's/object's own default menu). This mirrors
     * Gossip::getMenusForNPC()/getMenusForObject(), just followed backwards from menu to owner.
     *
     * @param int[] $poiIds
     * @return array<int, int> poiId => mapId
     */
    private function getPoiMaps(array $poiIds) : array
    {
        if (!DB::World()->selectCell('SHOW TABLES LIKE %s', 'gossip_menu_option'))
            return [];

        $optRows = DB::World()->selectAssoc(
           'SELECT `MenuID` AS "menu", `ActionPoiID` AS "poi" FROM gossip_menu_option WHERE `ActionPoiID` IN %in',
            $poiIds
        ) ?: [];

        if (!$optRows)
            return [];

        $poiToMenus = [];
        $menuIds    = [];
        foreach ($optRows as $r)
        {
            $menu = (int)$r['menu'];
            $poiToMenus[(int)$r['poi']][$menu] = $menu;
            $menuIds[$menu] = $menu;
        }

        // walk from each poi-carrying menu up to whatever parent menu opens it via
        // ActionMenuID, as far up as the tree goes, so an NPC/object several submenus
        // above the poi option is still found; menuParents keeps the edges so the walk
        // can later be redone per-poi to attribute a resolved map back to the right one
        $menuParents = [];                                  // childMenu => [parentMenu, ...]
        $allMenus    = $menuIds;
        $frontier    = $menuIds;

        for ($hop = 0; $frontier && $hop < 12; $hop++)
        {
            $rows = DB::World()->selectAssoc(
               'SELECT `MenuID` AS "parent", `ActionMenuID` AS "child" FROM gossip_menu_option WHERE `ActionMenuID` IN %in',
                $frontier
            ) ?: [];

            $frontier = [];
            foreach ($rows as $r)
            {
                $parent = (int)$r['parent'];
                $child  = (int)$r['child'];
                $menuParents[$child][$parent] = $parent;

                if (!isset($allMenus[$parent]))
                {
                    $allMenus[$parent] = $parent;
                    $frontier[$parent] = $parent;
                }
            }
        }

        $menuIds = $allMenus;

        // menuId => lowest map id any NPC/object presenting that menu is found on
        $menuToMap = [];
        $useMap    = function(int $menu, int $map) use (&$menuToMap) : void
        {
            if ($menu && (!isset($menuToMap[$menu]) || $map < $menuToMap[$menu]))
                $menuToMap[$menu] = $map;
        };

        if (DB::World()->selectCell('SHOW TABLES LIKE %s', 'creature_template') && DB::World()->selectCell('SHOW TABLES LIKE %s', 'creature'))
        {
            $rows = DB::World()->selectAssoc(
               'SELECT ct.`gossip_menu_id` AS "menu", MIN(c.`map`) AS "map"
                FROM   creature_template ct
                JOIN   creature c ON c.`id` = ct.`entry`
                WHERE  ct.`gossip_menu_id` IN %in
                GROUP BY ct.`gossip_menu_id`',
                $menuIds
            ) ?: [];

            foreach ($rows as $r)
                $useMap((int)$r['menu'], (int)$r['map']);
        }

        if (DB::World()->selectCell('SHOW TABLES LIKE %s', 'gameobject_template') && DB::World()->selectCell('SHOW TABLES LIKE %s', 'gameobject'))
        {
            $rows = DB::World()->selectAssoc(
               'SELECT
                    CASE gt.`type` WHEN %i THEN gt.`data3` WHEN %i THEN gt.`data18` END AS "menu",
                    MIN(g.`map`) AS "map"
                FROM   gameobject_template gt
                JOIN   gameobject g ON g.`id` = gt.`entry`
                WHERE  (gt.`type` = %i AND gt.`data3`  IN %in)
                   OR  (gt.`type` = %i AND gt.`data18` IN %in)
                GROUP BY menu',
                GO_TYPE_QUESTGIVER, GO_TYPE_GOOBER, GO_TYPE_QUESTGIVER, $menuIds, GO_TYPE_GOOBER, $menuIds
            ) ?: [];

            foreach ($rows as $r)
                $useMap((int)$r['menu'], (int)$r['map']);
        }

        if (DB::World()->selectCell('SHOW TABLES LIKE %s', 'smart_scripts'))
        {
            $rows = DB::World()->selectAssoc(
               'SELECT `source_type` AS "srcType", `entryorguid` AS "entry", `action_param1` AS "menu"
                FROM   smart_scripts
                WHERE  `action_type` = %i AND `action_param1` IN %in',
                SmartAction::ACTION_SEND_GOSSIP_MENU, $menuIds
            ) ?: [];

            $npcEntries = [];                               // entry => [menuId, ...]
            $goEntries  = [];
            $npcGuids   = [];                                // guid  => [menuId, ...]
            $goGuids    = [];

            foreach ($rows as $r)
            {
                $entry = (int)$r['entry'];
                $menu  = (int)$r['menu'];

                if ((int)$r['srcType'] == SmartAI::SRC_TYPE_CREATURE)
                {
                    if ($entry > 0)
                        $npcEntries[$entry][] = $menu;
                    else if ($entry < 0)                    // guid specific script; resolve back to the template
                        $npcGuids[-$entry][] = $menu;
                }
                else if ((int)$r['srcType'] == SmartAI::SRC_TYPE_OBJECT)
                {
                    if ($entry > 0)
                        $goEntries[$entry][] = $menu;
                    else if ($entry < 0)
                        $goGuids[-$entry][] = $menu;
                }
            }

            if ($npcGuids)
                foreach (DB::World()->selectAssoc('SELECT `guid`, `id` AS "entry" FROM creature WHERE `guid` IN %in', array_keys($npcGuids)) ?: [] as $r)
                    foreach ($npcGuids[(int)$r['guid']] as $menu)
                        $npcEntries[(int)$r['entry']][] = $menu;

            if ($goGuids)
                foreach (DB::World()->selectAssoc('SELECT `guid`, `id` AS "entry" FROM gameobject WHERE `guid` IN %in', array_keys($goGuids)) ?: [] as $r)
                    foreach ($goGuids[(int)$r['guid']] as $menu)
                        $goEntries[(int)$r['entry']][] = $menu;

            if ($npcEntries)
            {
                $maps = DB::World()->selectAssoc('SELECT `id` AS "entry", MIN(`map`) AS "map" FROM creature WHERE `id` IN %in GROUP BY `id`', array_keys($npcEntries)) ?: [];
                foreach ($maps as $r)
                    foreach ($npcEntries[(int)$r['entry']] as $menu)
                        $useMap($menu, (int)$r['map']);
            }

            if ($goEntries)
            {
                $maps = DB::World()->selectAssoc('SELECT `id` AS "entry", MIN(`map`) AS "map" FROM gameobject WHERE `id` IN %in GROUP BY `id`', array_keys($goEntries)) ?: [];
                foreach ($maps as $r)
                    foreach ($goEntries[(int)$r['entry']] as $menu)
                        $useMap($menu, (int)$r['map']);
            }
        }

        $out = [];
        foreach ($poiToMenus as $poi => $menus)
            foreach ($menus as $menu)
                foreach (self::menuAndAncestors($menu, $menuParents) as $m)
                    if (isset($menuToMap[$m]) && (!isset($out[$poi]) || $menuToMap[$m] < $out[$poi]))
                        $out[$poi] = $menuToMap[$m];

        return $out;
    }

    /** a menu plus every parent menu that can reach it through an ActionMenuID chain */
    private static function menuAndAncestors(int $menu, array $menuParents) : array
    {
        $seen  = [$menu => $menu];
        $stack = [$menu];

        while ($stack)
        {
            $m = array_pop($stack);
            foreach ($menuParents[$m] ?? [] as $parent)
            {
                if (!isset($seen[$parent]))
                {
                    $seen[$parent] = $parent;
                    $stack[] = $parent;
                }
            }
        }

        return $seen;
    }

    /** the three-digit pin block the Mapper link format uses per coordinate */
    private static function pinStr(float $coord) : string
    {
        return sprintf('%03d', (int)round($coord * 10));
    }

    protected function generateMetadata(bool $useArticle = true) : void
    {
        $this->metaTags[] = ['property' => 'og:title', 'content' => $this->h1];
        $this->metaTags[] = ['property' => 'og:type',  'content' => 'website'];

        array_unshift($this->metaTags, ['name' => 'keywords', 'content' => [$this->h1, ...Lang::meta('tags', 'generic')]]);

        $this->buildBasicMetadata(Lang::meta('description', 'genList', [$this->h1]));

        $this->buildLdJson();
    }
}

?>
