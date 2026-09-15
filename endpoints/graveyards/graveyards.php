<?php

namespace Aowow;

if (!defined('AOWOW_REVISION'))
    die('illegal access');


/*
 * Browser over `game_graveyard` / `graveyard_zone`.
 *
 * The zone page names the graveyards that serve it, but the graveyards themselves were never
 * enumerable: where they sit, which faction uses them, and every zone that resurrects there.
 */
class GraveyardsBaseResponse extends TemplateResponse
{
    use TrListPage;

    protected  string $template   = 'graveyards';
    protected  string $pageName   = 'graveyards';
    protected  int    $requiredUserGroup = U_GROUP_STAFF;

    protected ?int    $activeTab  = parent::TAB_DATABASE;
    protected  array  $breadcrumb = [0, 113];

    protected function generate() : void
    {
        $this->h1 = Util::ucFirst(Lang::graveyard('title'));


        /**************/
        /* Page Title */
        /**************/

        array_unshift($this->title, $this->h1);


        /****************/
        /* Main Content */
        /****************/

        $this->redButtons[BUTTON_WOWHEAD] = false;

        $this->lvTabs = new Tabs(['parent' => "\$\$WH.ge('tabs-generic')"]);
        $this->lvTabs->addListviewTab(new Listview(['data' => $this->buildListviewData()], 'graveyard', 'graveyard'));

        parent::generate();
    }

    private function buildListviewData() : array
    {
        if (!DB::World()->selectCell('SHOW TABLES LIKE %s', 'game_graveyard'))
            return [];

        // the link table is spelled graveyard_zone on 3.3.5 and game_graveyard_zone on newer cores
        $zoneTbl = DB::World()->selectCell('SHOW TABLES LIKE %s', 'graveyard_zone')
            ? 'graveyard_zone'
            : (DB::World()->selectCell('SHOW TABLES LIKE %s', 'game_graveyard_zone') ? 'game_graveyard_zone' : null);

        $rows = DB::World()->selectAssoc('SELECT `ID`, `Map`, `Comment`, `x`, `y`, `z` FROM game_graveyard ORDER BY `ID` ASC') ?: [];

        $zones = [];
        if ($zoneTbl)
        {
            $zoneRows = DB::World()->selectAssoc('SELECT `ID`, `GhostZone`, `Faction` FROM '.$zoneTbl.' ORDER BY `ID` ASC, `GhostZone` ASC') ?: [];
            foreach ($zoneRows as $zr)
                $zones[(int)$zr['ID']][] = ['zone' => (int)$zr['GhostZone'], 'faction' => (int)$zr['Faction']];
        }

        $jsg = [];
        $data = [];
        foreach ($rows as $r)
        {
            $id   = (int)$r['ID'];
            $name = trim((string)$r['Comment']);

            $row = array(
                'id'     => $id,
                'name'   => $name !== '' && $name[0] == '$' ? ' '.$name : $name,
                'map'    => (int)$r['Map'],
                'x'      => (float)$r['x'],
                'y'      => (float)$r['y'],
                'zones'  => [],
                'faction'=> 0
            );

            foreach ($zones[$id] ?? [] as $z)
            {
                $row['zones'][] = $z['zone'];
                $jsg[Type::ZONE][$z['zone']] = $z['zone'];

                // one graveyard can serve both factions through different ghost zones; the row
                // keeps the first, the zone links themselves stay per-row
                if (!$row['faction'])
                    $row['faction'] = $z['faction'];
            }

            $data[] = $row;
        }

        $this->extendGlobalData($jsg);

        return $data;
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
