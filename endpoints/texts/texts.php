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
class TextsBaseResponse extends TemplateResponse
{
    use TrListPage;

    protected  int    $requiredUserGroup = U_GROUP_STAFF;

    protected  string $template          = 'texts';
    protected  string $pageName          = 'texts';
    protected ?int    $activeTab         = parent::TAB_DATABASE;
    protected  array  $breadcrumb        = [0, 109];

    protected  array  $expectedGET       = array(
        'q'   => ['filter' => FILTER_CALLBACK,     'options' => [self::class, 'checkTextLine']],
        'src' => ['filter' => FILTER_VALIDATE_INT, 'flags' => FILTER_REQUIRE_SCALAR]
    );

    public array  $srcList    = [];                         // for the form in the template
    public array  $formValues = [];
    public string $notice     = '';

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

        $this->srcList    = Lang::gameText('sources');
        $this->formValues = array(
            'q'   => trim((string)($this->_get['q'] ?? '')),
            'src' => (int)($this->_get['src'] ?? 0)
        );

        // same floor the site's own search uses - two characters match half the game
        if ($this->formValues['q'] && mb_strlen($this->formValues['q']) < 3 && !Lang::getLocale()->isLogographic())
        {
            $this->notice = Lang::gameText('tooShort');
            $rows = [];
        }
        else
            $rows = GameText::browse(['query' => $this->formValues['q'], 'src' => $this->formValues['src']]);

        $tabData = ['data' => $this->buildListviewData($rows)];

        $this->lvTabs = new Tabs(['parent' => "\$\$WH.ge('tabs-generic')"]);
        $this->lvTabs->addListviewTab(new Listview($tabData, 'text', 'text'));

        parent::generate();
    }

    private function buildListviewData(array $rows) : array
    {
        $jsg  = [];
        $data = [];

        foreach ($rows as $r)
        {
            $row = array(
                'id'    => $r['id'],
                'src'   => $r['src'],
                'entry' => $r['entry'],
                'text'  => $r['text']
            );

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
