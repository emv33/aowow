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
class ConditionsBaseResponse extends TemplateResponse implements ICache
{
    use TrListPage, TrCache;

    protected  int    $cacheType         = CACHE_TYPE_LIST_PAGE;
    protected  int    $requiredUserGroup = U_GROUP_STAFF;

    protected  string $template          = 'conditions';
    protected  string $pageName          = 'conditions';
    protected ?int    $activeTab         = parent::TAB_DATABASE;
    protected  array  $breadcrumb        = [0, 105];

    protected  array  $scripts           = [[SC_JS_FILE, 'js/filters.js']];   // fi_toggle(), used to (un)collapse the search form

    protected  array  $expectedGET       = array(
        'src' => ['filter' => FILTER_VALIDATE_INT, 'flags' => FILTER_REQUIRE_SCALAR],
        'cnd' => ['filter' => FILTER_VALIDATE_INT, 'flags' => FILTER_REQUIRE_SCALAR],
        'val' => ['filter' => FILTER_VALIDATE_INT, 'flags' => FILTER_REQUIRE_SCALAR],
        'ent' => ['filter' => FILTER_VALIDATE_INT, 'flags' => FILTER_REQUIRE_SCALAR]
    );


    public array $srcTypeList = [];                         // for the form in the template
    public array $cndTypeList = [];
    public array $formValues  = [];

    // no single Type:: - a browser over `conditions`, filtered by ?src/?cnd/?val/?ent rather than
    // a Filter object that TrListPage's default key would pick up on its own
    public function getCacheKeyComponents() : array
    {
        return array(-9, -1, User::$groups, md5(serialize($this->_get)));
    }

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

        // CND_SRC_SMART_EVENT's entry is smart_scripts.entryorguid; a negative one is a spawn guid rather
        // than a template entry - resolve every one of them through ::spawns up front, batched by type,
        // same as Conditions::smartEventOwner() does per-row for the entity's own Conditions tab
        $guidsByType = [];
        foreach ($rows as $r)
            if ($r['srcType'] == Conditions::SRC_SMART_EVENT && $r['entry'] < 0 && $r['srcId'] <= 1)
            {
                $type = $r['srcId'] == 0 ? Type::NPC : Type::OBJECT;
                $guidsByType[$type][-$r['entry']] = -$r['entry'];
            }

        $guidLookup = [];
        foreach ($guidsByType as $type => $guids)
            $guidLookup[$type] = DB::Aowow()->selectCol('SELECT `guid` AS ARRAY_KEY, `typeId` FROM ::spawns WHERE `type` = %i AND `guid` IN %in', $type, array_values($guids)) ?: [];

        foreach ($rows as $i => $r)
        {
            [$grpType, $entryType, ] = $srcTypes[$r['srcType']] ?? [null, null, null];

            $row = array(
                'id'          => $i + 1,                    // the listview needs a unique key; a condition source has no id of its own - count from 1 so the debug id column renders the first row (index 0 is falsy in JS)
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

            if ($r['srcType'] == Conditions::SRC_SMART_EVENT)
            {
                // SourceId names the owner's type (0 creature, 1 gameobject, 2 areatrigger); anything else
                // (action list, gossip, quest, spell, ...) has no entity page of its own to link
                $type = match ($r['srcId'])
                {
                    0       => Type::NPC,
                    1       => Type::OBJECT,
                    2       => Type::AREATRIGGER,
                    default => 0
                };

                $entry = $r['entry'] > 0 ? $r['entry'] : ($guidLookup[$type][-$r['entry']] ?? 0);
                if ($type && $entry > 0 && ($_ = Type::getJSGlobalString($type)))
                {
                    $row['entry']       = $entry;           // overwrite the raw guid with the entity it resolves to
                    $row['entrylookup'] = $_;
                    $row['entryurl']    = Type::getFileString($type);
                    $jsg[$type][$entry] = $entry;
                }
            }
            else if (is_int($entryType) && $r['entry'] > 0 && ($_ = Type::getJSGlobalString($entryType)))
            {
                $row['entrylookup'] = $_;
                $row['entryurl']    = Type::getFileString($entryType);
                $jsg[$entryType][$r['entry']] = $r['entry'];
            }

            // a gossip menu has no name of its own and no g_* lookup, same as GameText::row() already handles it
            if (($r['srcType'] == Conditions::SRC_GOSSIP_MENU || $r['srcType'] == Conditions::SRC_GOSSIP_MENU_OPTION) && $r['group'] > 0)
            {
                $row['groupname'] = (string)Lang::gossip('menu', [$r['group']]);
                $row['groupurl']  = Type::getFileString(Type::GOSSIP);
            }

            $data[] = $row;
        }

        $this->extendGlobalData($jsg);

        return $data;
    }
}

?>
