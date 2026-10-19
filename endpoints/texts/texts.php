<?php

namespace Aowow;

if (!defined('AOWOW_REVISION'))
    die('illegal access');


/*
 * Search over the text the game speaks - creature lines, gossip, broadcast text and book pages.
 *
 * The site's own search matches names only, across all 28 of its modules, so none of this was
 * reachable by what it says. Kept apart from that search rather than added as a 29th module: the
 * rows have no name, no icon and no quality, and a hit is the text itself rather than an entity.
 */
class TextsBaseResponse extends TemplateResponse implements ICache
{
    use TrListPage, TrCache;

    protected  int    $cacheType         = CACHE_TYPE_LIST_PAGE;
    protected  int    $requiredUserGroup = U_GROUP_NONE;

    protected  string $template          = 'texts';
    protected  string $pageName          = 'texts';
    protected ?int    $activeTab         = parent::TAB_DATABASE;
    protected  array  $breadcrumb        = [0, 109];

    protected  array  $scripts           = [[SC_JS_FILE, 'js/filters.js']];   // fi_toggle(), used to (un)collapse the search form

    protected  array  $expectedGET       = array(
        'filter' => ['filter' => FILTER_CALLBACK, 'options' => [self::class, 'sanitizeFilter']],
        // the search form used to be a plain GET parameter; kept so existing links keep working
        'q'      => ['filter' => FILTER_CALLBACK, 'options' => [self::class, 'checkTextLine']]
    );

    // the listview id doubles as the tab's #anchor; named after the label rather than the table
    private const array TAB_IDS = array(
        GameText::SRC_CREATURE_TEXT => 'creature-text',
        GameText::SRC_BROADCAST     => 'broadcast-text',
        GameText::SRC_NPC_TEXT      => 'gossip-text',
        GameText::SRC_GOSSIP_OPTION => 'gossip-option',
        GameText::SRC_PAGE_TEXT     => 'page-text'
    );

    public string $notice = '';

    public function __construct(string $rawParam)
    {
        parent::__construct($rawParam);

        // old-style parameter: translate into the equivalent filter and continue there
        if (!isset($this->_get['filter']) && trim((string)($this->_get['q'] ?? '')) !== '')
            $this->forward('?texts&filter=na='.trim($this->_get['q']));

        $this->filter = new TextsFilter($this->_get['filter'] ?? '');
        if ($this->filter->shouldReload)
        {
            $_SESSION['error']['fi'] = $this->filter::class;
            $get = $this->filter->buildGETParam();
            $this->forward('?' . $this->pageName . ($get ? '&filter=' . $get : ''));
        }
        $this->filterError = $this->filter->error;
    }

    // no Type:: entry - the result set is driven by the filter
    public function getCacheKeyComponents() : array
    {
        return array(-8, -1, User::$groups, md5($this->filter->buildGETParam()));
    }

    protected function generate() : void
    {
        $this->h1 = Util::ucFirst(Lang::gameText('title'));


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

        $opts = $this->filter->getBrowseOpts();

        // same floor the site's own search uses - two characters match half the game
        if ($opts['query'] && mb_strlen($opts['query']) < 3 && !Lang::getLocale()->isLogographic())
        {
            $this->notice = Lang::gameText('tooShort');
            $rows = [];
        }
        else
            $rows = GameText::browse(['query' => $opts['query']]);

        // none picked searches every kind of text
        if ($opts['src'])
            $rows = array_filter($rows, fn($r) => in_array($r['src'], $opts['src']));

        // one tab per kind of text; kinds without a hit get no tab, but the page keeps one
        // (empty) so the listview's own "no results" state still shows.
        // A broadcast text is one kind of text however it is reached: the ones some other table
        // points at (row kind 'br', src = that table) share the tab with the unreferenced ones, and
        // the source and owner columns say what speaks them
        $bySrc = [];
        foreach ($rows as $r)
            $bySrc[$r['kind'] == 'br' ? GameText::SRC_BROADCAST : $r['src']][] = $r;

        $this->lvTabs = new Tabs(['parent' => "\$\$WH.ge('tabs-generic')"]);
        $first = true;
        foreach (GameText::SOURCES as $src => $_)
        {
            if (!$bySrc && $src != GameText::SRC_CREATURE_TEXT)
                continue;
            if ($bySrc && empty($bySrc[$src]))
                continue;

            $this->lvTabs->addListviewTab(new Listview(array(
                'id'         => self::TAB_IDS[$src],
                'name'       => Lang::gameText('sources', $src),
                'data'       => $this->buildListviewData($bySrc[$src] ?? []),
                'hiddenCols' => $src == GameText::SRC_BROADCAST ? [] : ['src']   // elsewhere the tab says it already
            ), 'text', $first ? 'text' : ''));              // the template only needs defining once
            $first = false;
        }

        parent::generate();
    }

    private function buildListviewData(array $rows) : array
    {
        $jsg  = [];
        $data = [];

        foreach ($rows as $r)
        {
            $row = array(
                'id'    => substr($r['id'], strlen($r['kind']) + 1),   // the tab is one kind, the prefix is its 'kind'
                'kind'  => $r['kind'],
                'src'   => $r['src'],
                'entry' => $r['entry'],
                'text'  => $r['text']
            );

            // a gossip menu has no name of its own and a line nothing references has no page at
            // all, so the component hands over a label for the cases a g_* lookup cannot cover
            if ($r['ownerName'])
                $row['ownername'] = $r['ownerName'];

            if ($r['ownerType'] && $r['ownerId'] > 0)
            {
                $row['ownerid']  = $r['ownerId'];
                $row['ownerurl'] = Type::getFileString($r['ownerType']);

                // the listview cannot map a Type to its g_* lookup on its own, so name it here
                if ($_ = Type::getJSGlobalString($r['ownerType']))
                {
                    $row['ownerlookup'] = $_;
                    $jsg[$r['ownerType']][$r['ownerId']] = $r['ownerId'];
                }
            }

            $data[] = $row;
        }

        $this->extendGlobalData($jsg);

        return $data;
    }
}

?>
