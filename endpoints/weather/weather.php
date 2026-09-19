<?php

namespace Aowow;

if (!defined('AOWOW_REVISION'))
    die('illegal access');


/*
 * Browser over `game_weather`.
 *
 * The zone page summarises a zone's weather into one line; this enumerates every row, so the
 * seasonal chances can be compared across zones without opening each zone page.
 */
class WeatherBaseResponse extends TemplateResponse implements ICache
{
    use TrListPage, TrCache;

    protected  int    $type       = -15;   // no single Type:: - a plain browser over game_weather
    protected  int    $cacheType  = CACHE_TYPE_LIST_PAGE;
    protected  string $template   = 'weather';
    protected  string $pageName   = 'weather';
    protected  int    $requiredUserGroup = U_GROUP_STAFF;

    protected ?int    $activeTab  = parent::TAB_DATABASE;
    protected  array  $breadcrumb = [0, 114];

    protected function generate() : void
    {
        $this->h1 = Util::ucFirst(Lang::weather('title'));


        /**************/
        /* Page Title */
        /**************/

        array_unshift($this->title, $this->h1);


        /****************/
        /* Main Content */
        /****************/

        $this->redButtons[BUTTON_WOWHEAD] = false;

        $this->lvTabs = new Tabs(['parent' => "\$\$WH.ge('tabs-generic')"]);
        $this->lvTabs->addListviewTab(new Listview(['data' => $this->buildListviewData()], 'weather', 'weather'));

        parent::generate();
    }

    private function buildListviewData() : array
    {
        if (!DB::World()->selectCell('SHOW TABLES LIKE %s', 'game_weather'))
            return [];

        $rows = DB::World()->selectAssoc('SELECT * FROM game_weather ORDER BY `zone` ASC') ?: [];

        $jsg  = [];
        $data = [];
        foreach ($rows as $r)
        {
            $lc    = array_change_key_case($r, CASE_LOWER);
            $zone  = (int)$lc['zone'];
            $row   = ['id' => $zone, 'zone' => $zone, 'rain' => 0, 'snow' => 0, 'storm' => 0];

            foreach (['rain', 'snow', 'storm'] as $what)
            {
                $max = 0;
                foreach (['spring', 'summer', 'fall', 'winter'] as $season)
                    $max = max($max, (int)($lc[$season.'_'.$what.'_chance'] ?? 0));

                $row[$what] = $max;
            }

            $jsg[Type::ZONE][$zone] = $zone;
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
