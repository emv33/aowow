<?php

namespace Aowow;

if (!defined('AOWOW_REVISION'))
    die('illegal access');


class WaypointpathsBaseResponse extends TemplateResponse
{
    use TrListPage;

    protected  int    $type              = Type::WAYPOINT_PATH;
    protected  int    $requiredUserGroup = U_GROUP_STAFF;

    protected  string $template          = 'waypointpaths';
    protected  string $pageName          = 'waypointpaths';
    protected ?int    $activeTab         = parent::TAB_DATABASE;
    protected  array  $breadcrumb        = [0, 120];

    protected  array  $scripts           = [[SC_JS_FILE, 'js/filters.js']];
    protected  array  $expectedGET       = ['filter' => ['filter' => FILTER_CALLBACK, 'options' => [self::class, 'sanitizeFilter']]];

    public function __construct(string $rawParam)
    {
        parent::__construct($rawParam);

        $this->filter = new WaypointPathListFilter($this->_get['filter'] ?? '');
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
        $this->h1 = Util::ucFirst(Lang::game('waypointpaths'));


        /**************/
        /* Page Title */
        /**************/

        array_unshift($this->title, $this->h1);


        /****************/
        /* Main Content */
        /****************/

        $this->redButtons[BUTTON_WOWHEAD] = false;

        $conditions = [Listview::DEFAULT_SIZE];
        if ($_ = $this->filter->getConditions())
            $conditions[] = $_;

        $tabData = [];
        $paths   = new WaypointPathList($conditions, ['calcTotal' => true]);
        if (!$paths->error)
        {
            $tabData['data'] = $paths->getListviewData();

            if ($areaIds = array_unique(array_filter(array_column($tabData['data'], 'areaId'))))
                $this->extendGlobalIds(Type::ZONE, ...$areaIds);
            if ($npcIds = array_unique(array_filter(array_column($tabData['data'], 'npc'))))
                $this->extendGlobalIds(Type::NPC, ...$npcIds);

            if ($paths->getMatches() > Listview::DEFAULT_SIZE)
            {
                $tabData['note'] = sprintf(Util::$tryFilteringEntityString, $paths->getMatches(), '"'.Lang::game('waypointpaths').'"', Listview::DEFAULT_SIZE);
                $tabData['_truncated'] = 1;
            }
        }

        $this->lvTabs = new Tabs(['parent' => "\$\$WH.ge('tabs-generic')"]);
        $this->lvTabs->addListviewTab(new Listview($tabData, WaypointPathList::$brickFile, 'waypointpath'));

        parent::generate();
    }
}

?>
