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
class AchievementcriteriaBaseResponse extends TemplateResponse implements ICache
{
    use TrListPage, TrCache;

    protected  int    $cacheType  = CACHE_TYPE_LIST_PAGE;
    protected  string $template   = 'achievement-criteria';
    protected  string $pageName   = 'achievement-criteria';
    protected ?int    $activeTab  = parent::TAB_DATABASE;
    protected  array  $breadcrumb = [0, 118];

    protected  array  $scripts    = [[SC_JS_FILE, 'js/filters.js']];

    protected  array  $expectedGET = array(
        'filter' => ['filter' => FILTER_CALLBACK,     'options' => [self::class, 'sanitizeFilter']],
        // the search form used to be plain GET parameters (the listing's type links still use ?ty=); kept so existing links keep working
        'id'     => ['filter' => FILTER_VALIDATE_INT, 'flags' => FILTER_REQUIRE_SCALAR],
        'ac'     => ['filter' => FILTER_CALLBACK,     'options' => [self::class, 'checkTextLine']],
        'ty'     => ['filter' => FILTER_VALIDATE_INT, 'flags' => FILTER_REQUIRE_SCALAR],
        'fl'     => ['filter' => FILTER_VALIDATE_INT, 'flags' => FILTER_REQUIRE_SCALAR],
        'na'     => ['filter' => FILTER_CALLBACK,     'options' => [self::class, 'checkTextLine']]
    );

    public array $typeList = [];                            // for the form in the template

    public function __construct(string $rawParam)
    {
        parent::__construct($rawParam);

        // old-style parameters: translate into the equivalent filter and continue there
        if (!isset($this->_get['filter']) && (($this->_get['id'] ?? 0) || ($this->_get['ac'] ?? '') !== '' || is_int($this->_get['ty'] ?? null) || ($this->_get['fl'] ?? 0) || ($this->_get['na'] ?? '') !== ''))
        {
            $legacy = [];
            if (is_int($this->_get['ty'] ?? null))
                $legacy[] = 'ty='.$this->_get['ty'];
            if (($this->_get['na'] ?? '') !== '')
                $legacy[] = 'na='.$this->_get['na'];

            $cr = [];
            if ($_ = (int)($this->_get['id'] ?? 0))
                $cr[] = [AchievementCriteriaListFilter::CR_ID, 3, $_];
            if (($ac = (string)($this->_get['ac'] ?? '')) !== '')
                $cr[] = ctype_digit($ac) ? [AchievementCriteriaListFilter::CR_ACHIEVEMENT, 3, $ac] : [AchievementCriteriaListFilter::CR_ACH_NAME, 0, $ac];
            foreach ([AchievementCriteriaListFilter::CR_PROGRESS_BAR => ACHIEVEMENT_CRITERIA_FLAG_SHOW_PROGRESS_BAR, AchievementCriteriaListFilter::CR_HIDDEN => ACHIEVEMENT_CRITERIA_FLAG_HIDDEN, AchievementCriteriaListFilter::CR_MONEY_COUNTER => ACHIEVEMENT_CRITERIA_FLAG_MONEY_COUNTER] as $crId => $bit)
                if ((int)($this->_get['fl'] ?? 0) & $bit)
                    $cr[] = [$crId, 1, 0];

            if ($cr)
                array_push($legacy, 'cr='.implode(':', array_column($cr, 0)), 'crs='.implode(':', array_column($cr, 1)), 'crv='.implode(':', array_column($cr, 2)));

            $this->forward('?achievement-criteria&filter='.implode(';', $legacy));
        }

        $this->filter = new AchievementCriteriaListFilter($this->_get['filter'] ?? '');
        if ($this->filter->shouldReload)
        {
            $_SESSION['error']['fi'] = $this->filter::class;
            $get = $this->filter->buildGETParam();
            $this->forward('?' . $this->pageName . ($get ? '&filter=' . $get : ''));
        }
        $this->filterError = $this->filter->error;
    }

    // no single Type:: - a browser over the aowow `achievementcriteria` table
    public function getCacheKeyComponents() : array
    {
        return array(-11, -1, User::$groups, md5($this->filter->buildGETParam()));
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

        if ($fiQuery = $this->filter->buildGETParam())
            $this->fiMenuExtension = $fiQuery;

        // aowow - custom: don't limit the list to the types TYPE_NAMES has a name for - fall back to the
        // same generic 'Criteria type #n' label the listview uses, so every type actually in use is selectable
        foreach (DB::Aowow()->selectCol('SELECT DISTINCT `type` FROM ::achievementcriteria ORDER BY `type` ASC') ?: [] as $_type)
            $this->typeList[$_type] = AchievementCriteriaList::TYPE_NAMES[$_type] ?? 'Criteria type #'.$_type;

        $conditions = [Listview::DEFAULT_SIZE];
        if ($_ = $this->filter->getConditions())
            $conditions[] = $_;

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
