<?php

namespace Aowow;

if (!defined('AOWOW_REVISION'))
    die('illegal access');


class GossipsBaseResponse extends TemplateResponse implements ICache
{
    use TrListPage, TrCache;

    protected  int    $type              = Type::GOSSIP;
    protected  int    $cacheType         = CACHE_TYPE_LIST_PAGE;
    protected  int    $requiredUserGroup = U_GROUP_STAFF;

    protected  string $template          = 'gossips';
    protected  string $pageName          = 'gossips';
    protected ?int    $activeTab         = parent::TAB_DATABASE;
    protected  array  $breadcrumb        = [0, 104];

    protected  array  $scripts           = [[SC_JS_FILE, 'js/filters.js']];
    protected  array  $expectedGET       = ['filter' => ['filter' => FILTER_CALLBACK, 'options' => [self::class, 'sanitizeFilter']]];

    public function __construct(string $rawParam)
    {
        $this->getCategoryFromUrl($rawParam);               // menus have no categories; this rejects a bogus ?gossips=<x>

        parent::__construct($rawParam);

        $this->filter = new GossipListFilter($this->_get['filter'] ?? '');
        if ($this->filter->shouldReload)
        {
            $_SESSION['error']['fi'] = $this->filter::class;
            $get = $this->filter->buildGETParam();
            $this->forward('?' . $this->pageName . ($get ? '&filter=' . $get : ''));
        }
        $this->filterError = $this->filter->error;
    }

    protected function generate() : void
    {
        $this->h1 = Util::ucFirst(Lang::game('gossips'));


        /**************/
        /* Page Title */
        /**************/

        array_unshift($this->title, $this->h1);


        /****************/
        /* Main Content */
        /****************/

        $this->redButtons[BUTTON_WOWHEAD] = false;

        if ($fiQuery = $this->filter->buildGETParam())
            $this->fiMenuExtension = $fiQuery;

        $conditions = [Listview::DEFAULT_SIZE];
        if ($_ = $this->filter->getConditions())
            $conditions[] = $_;

        $tabData = [];
        $menus   = new GossipList($conditions, ['calcTotal' => true]);
        if (!$menus->error)
        {
            $this->extendGlobalData($menus->getJSGlobals());

            $tabData['data'] = $menus->getListviewData();

            // create note if search limit was exceeded; overwriting 'note' is intentional
            if ($menus->getMatches() > Listview::DEFAULT_SIZE)
            {
                $tabData['note'] = sprintf(Util::$tryFilteringEntityString, $menus->getMatches(), '"'.Lang::game('gossips').'"', Listview::DEFAULT_SIZE);
                $tabData['_truncated'] = 1;
            }
        }

        $this->lvTabs = new Tabs(['parent' => "\$\$WH.ge('tabs-generic')"]);

        $this->lvTabs->addListviewTab(new Listview($tabData, GossipList::$brickFile, 'gossip'));

        parent::generate();
    }
}

?>
