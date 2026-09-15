<?php

namespace Aowow;

if (!defined('AOWOW_REVISION'))
    die('illegal access');


/*
 * Browser over the aowow `achievementcriteria` table.
 *
 * Achievement detail pages list their criteria, but the criteria themselves are only
 * addressable through the achievement. This enumerates them by id and links each row
 * back to the achievement it belongs to.
 */
class AchievementcriteriaBaseResponse extends TemplateResponse
{
    use TrListPage;

    protected  string $template   = 'achievement-criteria';
    protected  string $pageName   = 'achievement-criteria';
    protected ?int    $activeTab  = parent::TAB_DATABASE;
    protected  array  $breadcrumb = [0, 110];

    protected  array  $expectedGET = array(
        'ac' => ['filter' => FILTER_VALIDATE_INT, 'flags' => FILTER_REQUIRE_SCALAR],
        'id' => ['filter' => FILTER_VALIDATE_INT, 'flags' => FILTER_REQUIRE_SCALAR],
        'ty' => ['filter' => FILTER_VALIDATE_INT, 'flags' => FILTER_REQUIRE_SCALAR],
        'fl' => ['filter' => FILTER_VALIDATE_INT, 'flags' => FILTER_REQUIRE_SCALAR],
        'na' => ['filter' => FILTER_CALLBACK, 'options' => [self::class, 'checkTextLine']]
    );

    public array $formValues = [];                          // for the search form

    public function __construct(string $rawParam)
    {
        parent::__construct($rawParam);
    }

    protected function generate() : void
    {
        $this->h1 = Util::ucFirst(Lang::game('achievements')) . ' - ' . Lang::achievement('criteria');


        /**************/
        /* Page Title */
        /**************/

        array_unshift($this->title, $this->h1);


        /****************/
        /* Main Content */
        /****************/

        $this->redButtons[BUTTON_WOWHEAD] = false;

        $this->formValues = array(
            'id' => (int)($this->_get['id'] ?? 0),
            'ac' => (int)($this->_get['ac'] ?? 0),
            'ty' => (int)($this->_get['ty'] ?? 0),
            'fl' => (int)($this->_get['fl'] ?? 0),
            'na' => (string)($this->_get['na'] ?? '')
        );

        $conditions = [Listview::DEFAULT_SIZE];
        if ($this->formValues['id'])
            $conditions[] = ['id', $this->formValues['id']];
        if ($this->formValues['ac'])
            $conditions[] = ['refAchievementId', $this->formValues['ac']];
        if ($this->formValues['ty'])
            $conditions[] = ['type', $this->formValues['ty']];
        if ($this->formValues['fl'])
            $conditions[] = [['completionFlags', $this->formValues['fl'], '&'], $this->formValues['fl']];
        if ($this->formValues['na'])
            $conditions[] = ['name_loc'.Lang::getLocale()->value, '%'.$this->formValues['na'].'%', 'LIKE'];

        $tabData = [];
        $crtList = new AchievementCriteriaList($conditions);
        if (!$crtList->error)
        {
            $tabData['data'] = $crtList->getListviewData();
            $this->extendGlobalData($crtList->getJSGlobals());
        }

        $this->lvTabs = new Tabs(['parent' => "\$\$WH.ge('tabs-generic')"]);
        $this->lvTabs->addListviewTab(new Listview($tabData, 'achievementcriteria', 'achievementcriteria'));

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
