<?php

namespace Aowow;

if (!defined('AOWOW_REVISION'))
    die('illegal access');


/*
 * Browser over the world DB graveyard tables.
 *
 * The zone page names the graveyards that serve it, but the graveyards themselves were never
 * enumerable: where they sit, which faction uses them, and every zone that resurrects there.
 *
 * The tables split into a position row (`game_graveyard`) and the zone links (`graveyard_zone`
 * on 3.3.5, `game_graveyard_zone` on newer cores). Both halves are read whole and matched
 * lowercased, so the browser keeps working when a core renames columns or drops the position
 * table entirely - in that case the rows are derived from the zone links alone.
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
        $positions = [];

        if (self::hasTable('game_graveyard'))
        {
            foreach (DB::World()->selectAssoc('SELECT * FROM game_graveyard') ?: [] as $r)
            {
                $lc = array_change_key_case($r, CASE_LOWER);
                $id = (int)($lc['id'] ?? 0);

                $positions[$id] = array(
                    'id'   => $id,
                    'name' => trim((string)($lc['comment'] ?? '')),
                    'map'  => (int)($lc['map'] ?? $lc['mapid'] ?? 0),
                    'x'    => (float)($lc['x'] ?? 0),
                    'y'    => (float)($lc['y'] ?? 0)
                );
            }
        }

        $links = [];
        $zoneTbl = self::hasTable('graveyard_zone')
            ? 'graveyard_zone'
            : (self::hasTable('game_graveyard_zone') ? 'game_graveyard_zone' : null);

        if ($zoneTbl)
        {
            foreach (DB::World()->selectAssoc('SELECT * FROM '.$zoneTbl) ?: [] as $r)
            {
                $lc = array_change_key_case($r, CASE_LOWER);
                $id = (int)($lc['id'] ?? 0);

                $links[$id][] = array(
                    'zone'    => (int)($lc['ghostzone'] ?? $lc['ghost_zone'] ?? 0),
                    'faction' => (int)($lc['faction'] ?? 0)
                );
            }
        }

        $ids = array_unique(array_merge(array_keys($positions), array_keys($links)));
        sort($ids);

        $data = [];
        foreach ($ids as $id)
        {
            $pos  = $positions[$id] ?? ['id' => $id, 'name' => '', 'map' => 0, 'x' => 0, 'y' => 0];
            $name = (string)$pos['name'];

            $row = array(
                'id'      => $id,
                'name'    => $name !== '' && $name[0] == '$' ? ' '.$name : $name,
                'map'     => $pos['map'],
                'x'       => $pos['x'],
                'y'       => $pos['y'],
                'zones'   => [],
                'faction' => 0
            );

            foreach ($links[$id] ?? [] as $link)
            {
                $row['zones'][] = $link['zone'];

                // one graveyard can serve both factions through different ghost zones; the row
                // keeps the first, the zone links themselves stay per-row
                if (!$row['faction'])
                    $row['faction'] = $link['faction'];
            }

            $data[] = $row;
        }

        return $data;
    }

    private static function hasTable(string $tbl) : bool
    {
        static $known = [];

        return $known[$tbl] ??= (bool)DB::World()->selectCell('SHOW TABLES LIKE %s', $tbl);
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
