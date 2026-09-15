<?php

namespace Aowow;

if (!defined('AOWOW_REVISION'))
    die('illegal access');


/*
 * Browser over the world DB `conditions` table.
 *
 * Until now conditions were only reachable from the page of the entity they gate - there was no
 * way to ask the inverse question ("what is gated on this spell / item / quest?"), and no way to
 * see which source types carry conditions at all. This enumerates the distinct sources; rendering
 * the conditions of one source stays with Conditions::getBySource() on that entity's own page.
 */
class ConditionsBaseResponse extends TemplateResponse
{
    use TrListPage;

    protected  int    $requiredUserGroup = U_GROUP_STAFF;

    protected  string $template          = 'conditions';
    protected  string $pageName          = 'conditions';
    protected ?int    $activeTab         = parent::TAB_DATABASE;
    protected  array  $breadcrumb        = [0, 105];

    protected  array  $expectedGET       = array(
        'src' => ['filter' => FILTER_VALIDATE_INT, 'flags' => FILTER_REQUIRE_SCALAR],
        'cnd' => ['filter' => FILTER_VALIDATE_INT, 'flags' => FILTER_REQUIRE_SCALAR],
        'val' => ['filter' => FILTER_VALIDATE_INT, 'flags' => FILTER_REQUIRE_SCALAR],
        'ent' => ['filter' => FILTER_VALIDATE_INT, 'flags' => FILTER_REQUIRE_SCALAR]
    );


    public array $srcTypeList = [];                         // for the form in the template
    public array $cndTypeList = [];
    public array $formValues  = [];

    protected function generate() : void
    {
        $this->h1 = Util::ucFirst(Lang::game('conditions'));


        /**************/
        /* Page Title */
        /**************/

        array_unshift($this->title, $this->h1);


        /****************/
        /* Main Content */
        /****************/

        $this->redButtons[BUTTON_WOWHEAD] = false;

        $this->srcTypeList = Lang::conditionBrowser('srcTypes');
        $this->cndTypeList = Lang::conditionBrowser('cndTypes');
        $this->formValues  = array(
            'src' => (int)($this->_get['src'] ?? 0),
            'cnd' => (int)($this->_get['cnd'] ?? 0),
            'val' => (int)($this->_get['val'] ?? 0),
            'ent' => (int)($this->_get['ent'] ?? 0)
        );

        $this->pageTemplate['filter'] = array_filter($this->formValues) ? 1 : 0;

        $rows = Conditions::browse(array(
            'srcType' => $this->formValues['src'] ? [$this->formValues['src']] : [],
            'cndType' => $this->formValues['cnd'],
            'value1'  => $this->formValues['val'],
            'entry'   => $this->formValues['ent'],
            'limit'   => Listview::DEFAULT_SIZE
        ));

        $tabData = ['data' => $this->buildListviewData($rows)];

        $this->lvTabs = new Tabs(['parent' => "\$\$WH.ge('tabs-generic')"]);
        $this->lvTabs->addListviewTab(new Listview($tabData, 'condition', 'condition'));

        parent::generate();
    }

    /** resolve group/entry to the entity they actually name, so the listing links instead of printing raw ids */
    private function buildListviewData(array $rows) : array
    {
        $srcTypes = Conditions::getSourceTypes();
        $jsg      = [];
        $data     = [];

        foreach ($rows as $i => $r)
        {
            [$grpType, $entryType, ] = $srcTypes[$r['srcType']] ?? [null, null, null];

            $row = array(
                'id'          => $i,                        // the listview needs a unique key; a condition source has no id of its own
                'srctype'     => $r['srcType'],
                'group'       => $r['group'],
                'entry'       => $r['entry'],
                'srcid'       => $r['srcId'],
                'nconditions' => $r['nConditions'],
                'cndtypes'    => $r['cndTypes']
            );

            // the listview cannot map a Type to its g_* lookup on its own, so name both here
            if (is_int($grpType) && $r['group'] > 0 && ($_ = Type::getJSGlobalString($grpType)))
            {
                $row['grouplookup'] = $_;
                $row['groupurl']    = Type::getFileString($grpType);
                $jsg[$grpType][$r['group']] = $r['group'];
            }

            if (is_int($entryType) && $r['entry'] > 0 && ($_ = Type::getJSGlobalString($entryType)))
            {
                $row['entrylookup'] = $_;
                $row['entryurl']    = Type::getFileString($entryType);
                $jsg[$entryType][$r['entry']] = $r['entry'];
            }

            $data[] = $row;
        }

        $this->extendGlobalData($jsg);

        return $data;
    }
}

?>
