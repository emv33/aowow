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
        $this->formValues  = array(
            'src' => (int)($this->_get['src'] ?? 0),
            'cnd' => (int)($this->_get['cnd'] ?? 0),
            'val' => (int)($this->_get['val'] ?? 0),
            'ent' => (int)($this->_get['ent'] ?? 0)
        );

        // filtered to one entity's own gating conditions - name the page after it when that entity
        // is a creature; getSourceTypes() is the same [group, entry] type mapping buildListviewData()
        // below already trusts to link a row, so this only ever fires where that same lookup would.
        // ?ent matches either SourceGroup or SourceEntry (browse()'s WHERE is an OR of both), so
        // either side resolving to Type::NPC is enough - SRC_SMART_EVENT is excluded since its
        // owner type comes from SourceId, not from this mapping
        $this->h1 = Util::ucFirst(Lang::game('conditions'));

        if ($this->formValues['src'] && $this->formValues['ent'] && $this->formValues['src'] != Conditions::SRC_SMART_EVENT)
        {
            [$grpType, $entryType] = Conditions::getSourceTypes()[$this->formValues['src']] ?? [null, null, null];

            if (($grpType == Type::NPC || $entryType == Type::NPC) && ($npcName = CreatureList::getName($this->formValues['ent'])))
                $this->h1 = Lang::conditionBrowser('titleNpc', [(string)$npcName]);
        }


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

        // Conditions::GROUP_NEEDS_LOOKUP sources don't name their owner directly - SourceGroup is
        // an internal loot-template id, resolved only through Conditions::resolveGroupOwners()'s
        // own lookup (a raw id treated as e.g. a direct npc entry would as often as not resolve to
        // some unrelated real npc rather than fail outright - see ?condition's identical fix);
        // batched here by source type rather than run once per row
        $lookupGroups = [];
        foreach ($rows as $r)
            if (in_array($r['srcType'], Conditions::GROUP_NEEDS_LOOKUP, true) && $r['group'] > 0)
                $lookupGroups[$r['srcType']][] = $r['group'];

        $lookupOwners = [];                                     // srcType => [Type::, [group => ownerIds[]]]
        foreach ($lookupGroups as $srcType => $groups)
            $lookupOwners[$srcType] = Conditions::resolveGroupOwnersBatch($srcType, $groups);

        foreach ($rows as $r)
        {
            [$grpType, $entryType, ] = $srcTypes[$r['srcType']] ?? [null, null, null];

            // a condition source has no id column of its own, but the four raw key columns together
            // are one - same composite-id approach GameText::browse() already uses for its own
            // owner-less rows; taken from $r before 'entry' below is possibly overwritten for
            // display (SRC_SMART_EVENT), since the resolved, positive display value wouldn't look
            // up the right row (or any) once ?condition re-queries `conditions` with it
            $row = array(
                'id'          => $r['srcType'].':'.$r['group'].':'.$r['entry'].':'.$r['srcId'],
                'srctype'     => $r['srcType'],
                'group'       => $r['group'],
                'entry'       => $r['entry'],
                'srcid'       => $r['srcId'],
                'nconditions' => $r['nConditions'],
                'cndtypes'    => $r['cndTypes']
            );

            if (in_array($r['srcType'], Conditions::GROUP_NEEDS_LOOKUP, true))
            {
                // resolved server-side above, same as the gossip case below - there is no g_* lookup
                // to point the client at, since the id shown is never SourceGroup itself
                [$ownerType, $ownersByGroup] = $lookupOwners[$r['srcType']] ?? [0, []];
                $owners = $ownersByGroup[$r['group']] ?? [];

                $names = [];
                foreach (array_slice($owners, 0, 3) as $ownerId)
                    if ($name = Conditions::nameForType($ownerType, $ownerId))
                        $names[] = $name;

                if ($names)
                {
                    $row['groupname'] = implode(', ', $names) . (count($owners) > 3 ? ' '.Lang::condition('andMore', [count($owners) - 3]) : '');
                    $row['groupurl']  = Type::getFileString($ownerType);
                    $row['group']     = $owners[0];             // link target - the owner named first
                }
            }
            // the listview cannot map a Type to its g_* lookup on its own, so name both here
            else if (is_int($grpType) && $r['group'] > 0 && ($_ = Type::getJSGlobalString($grpType)))
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
                if ($type && $entry > 0)
                {
                    $row['entry'] = $entry;                 // overwrite the raw guid with the entity it resolves to

                    // areatrigger has no g_* lookup (Type::getJSGlobalString() is empty for it) -
                    // resolve the name here instead, same as SmartaiBaseResponse::linkFields() already does
                    if ($type == Type::AREATRIGGER)
                    {
                        if ($name = (string)AreaTriggerList::getName($entry))
                        {
                            $row['entryname'] = $name;
                            $row['entryurl']  = Type::getFileString($type);
                        }
                    }
                    else if ($_ = Type::getJSGlobalString($type))
                    {
                        $row['entrylookup'] = $_;
                        $row['entryurl']    = Type::getFileString($type);
                        $jsg[$type][$entry] = $entry;
                    }
                }
            }
            // ditto - a bare CND_SRC_AREATRIGGER_CLIENT row hits the same gap the generic branch
            // below would otherwise fall into
            else if ($r['srcType'] == Conditions::SRC_AREATRIGGER_CLIENT && $r['entry'] > 0)
            {
                if ($name = (string)AreaTriggerList::getName($r['entry']))
                {
                    $row['entryname'] = $name;
                    $row['entryurl']  = Type::getFileString(Type::AREATRIGGER);
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
