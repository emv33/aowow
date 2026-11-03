<?php

namespace Aowow;

if (!defined('AOWOW_REVISION'))
    die('illegal access');


/*
 * The spawn pools of `pool_template` / `pool_members` - see Pool for how the core uses them.
 *
 * A pool was only visible from the npcs and objects it holds, one entry at a time; this lists every
 * pool with how many of its members are live at once, and gives each its own page.
 */
class SpawnpoolsBaseResponse extends TemplateResponse implements ICache
{
    use TrListPage, TrCache;

    protected  int    $type              = -17;    // no Type:: entry - no DBTypeList backs this ad hoc table read; shared sentinel with SpawnpoolBaseResponse
    protected  int    $cacheType         = CACHE_TYPE_LIST_PAGE;
    protected  string $template          = 'spawnpools';
    protected  string $pageName          = 'spawnpools';
    protected  int    $requiredViewGroup = U_GROUP_STAFF;

    protected ?int    $activeTab         = parent::TAB_DATABASE;
    protected  array  $breadcrumb        = [0, 122];

    protected function generate() : void
    {
        $this->h1 = Util::ucFirst(Lang::pool('title'));


        /**************/
        /* Page Title */
        /**************/

        array_unshift($this->title, $this->h1);


        /****************/
        /* Main Content */
        /****************/

        $this->redButtons[BUTTON_WOWHEAD] = false;

        $this->addDataLoader('zones');

        $this->lvTabs = new Tabs(['parent' => "\$\$WH.ge('tabs-generic')"]);
        $this->lvTabs->addListviewTab(new Listview(array(
            'data'       => Pool::getRows(),
            'hiddenCols' => ['mine']
        ), 'spawnpool', 'spawnpool'));

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
