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

        $data = [];
        foreach ($pois as $poi)
        {
            $mapId = $maps[$poi['id']] ?? 0;
            if ($mapId && ($pt = WorldPosition::toZonePos($mapId, $poi['x'], $poi['y'])))
            {
                $poi['zone']    = (int)$pt[0]['areaId'];
                $poi['maplink'] = '?maps='.$poi['zone'].':'.self::pinStr($pt[0]['posX']).self::pinStr($pt[0]['posY']);
            }

            $data[] = $poi;
        }

        return $data;
    }

    /**
     * @param int[] $poiIds
     * @return array<int, int> poiId => mapId
     */
    private function getPoiMaps(array $poiIds) : array
    {
        foreach (['gossip_menu', 'gossip_menu_option', 'creature_template', 'creature'] as $tbl)
            if (!DB::World()->selectCell('SHOW TABLES LIKE %s', $tbl))
                return [];

        // a menu can be shared by creatures on several maps; the lowest map id wins, which is
        // enough to open a map the point is actually drawn on
        $rows = DB::World()->selectAssoc(
           'SELECT gmo.`ActionPoiID` AS "poi", MIN(c.`map`) AS "map"
            FROM   gossip_menu_option gmo
            JOIN   gossip_menu gm ON gm.`MenuID` = gmo.`MenuID`
            JOIN   creature_template ct ON ct.`gossip_menu_id` = gm.`MenuID`
            JOIN   creature c ON c.`id` = ct.`entry`
            WHERE  gmo.`ActionPoiID` IN %in
            GROUP BY gmo.`ActionPoiID`', $poiIds
        ) ?: [];

        $out = [];
        foreach ($rows as $r)
            $out[(int)$r['poi']] = (int)$r['map'];

        return $out;
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
