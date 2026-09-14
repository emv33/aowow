<?php

namespace Aowow;

if (!defined('AOWOW_REVISION'))
    die('illegal access');


class EncountersBaseResponse extends TemplateResponse implements ICache
{
    use TrListPage, TrCache;

    protected  int    $type              = Type::ENCOUNTER;
    protected  int    $cacheType         = CACHE_TYPE_LIST_PAGE;
    protected  int    $requiredUserGroup = U_GROUP_STAFF;

    protected  string $template          = 'encounters';
    protected  string $pageName          = 'encounters';
    protected ?int    $activeTab         = parent::TAB_DATABASE;
    protected  array  $breadcrumb        = [0, 107];

    protected  array  $scripts           = [[SC_JS_FILE, 'js/filters.js']];
    protected  array  $expectedGET       = ['filter' => ['filter' => FILTER_CALLBACK, 'options' => [self::class, 'sanitizeFilter']]];

    public function __construct(string $rawParam)
    {
        $this->getCategoryFromUrl($rawParam);               // encounters have no categories; this rejects a bogus ?encounters=<x>

        parent::__construct($rawParam);

        $this->filter = new EncounterListFilter($this->_get['filter'] ?? '');
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
        $this->h1 = Util::ucFirst(Lang::game('encounters'));


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

        $tabData    = [];
        $encounters = new EncounterList($conditions, ['calcTotal' => true]);
        if (!$encounters->error)
        {
            $this->extendGlobalData($encounters->getJSGlobals());

            $tabData['data'] = $encounters->getListviewData();

            // create note if search limit was exceeded; overwriting 'note' is intentional
            if ($encounters->getMatches() > Listview::DEFAULT_SIZE)
            {
                $tabData['note'] = sprintf(Util::$tryFilteringEntityString, $encounters->getMatches(), '"'.Lang::game('encounters').'"', Listview::DEFAULT_SIZE);
                $tabData['_truncated'] = 1;
            }
        }

        $this->lvTabs = new Tabs(['parent' => "\$\$WH.ge('tabs-generic')"]);

        $this->lvTabs->addListviewTab(new Listview($tabData, EncounterList::$brickFile, 'encounter'));

        parent::generate();
    }
}

?>
