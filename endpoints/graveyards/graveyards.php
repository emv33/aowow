<?php

namespace Aowow;

if (!defined('AOWOW_REVISION'))
    die('illegal access');


/*
 * Browser over the world DB graveyard tables.
 *
 * Cores that still ship a position table split the data over `game_graveyard` (where the
 * graveyard sits) and `graveyard_zone` / `game_graveyard_zone` (which zones resurrect there).
 * Cores without the position table keep everything in the link table: `Comment` carries the
 * name and the map is derived from the ghost zone.
 */
class GraveyardsBaseResponse extends TemplateResponse implements ICache
{
    use TrListPage, TrCache;

    protected  int    $type       = -2;    // no Type:: entry - no DBTypeList backs this ad hoc table read; shared sentinel with GraveyardBaseResponse
    protected  int    $cacheType  = CACHE_TYPE_LIST_PAGE;
    protected  string $template   = 'graveyards';
    protected  string $pageName   = 'graveyards';
    protected  int    $requiredViewGroup = U_GROUP_STAFF;

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
        $data = Graveyard::getListviewData(null, $zoneIds);
        if ($zoneIds)
            $this->extendGlobalIds(Type::ZONE, ...$zoneIds);

        $this->lvTabs->addListviewTab(new Listview(['data' => $data], 'graveyard', 'graveyard'));

        parent::generate();
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
