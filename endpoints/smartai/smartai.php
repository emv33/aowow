<?php

namespace Aowow;

if (!defined('AOWOW_REVISION'))
    die('illegal access');


/*
 * Browser over the world DB `smart_scripts` table.
 *
 * SmartAI was only ever reachable from the page of the entity that owns the script. This adds the
 * inverse question ("which scripts cast this spell / summon this creature?") and a plain listing of
 * every scripted entity. Rendering a script stays with the SmartAI instance on that entity's page.
 */
class SmartaiBaseResponse extends TemplateResponse implements ICache
{
    use TrListPage, TrCache;

    protected  int    $cacheType         = CACHE_TYPE_LIST_PAGE;
    protected  int    $requiredUserGroup = U_GROUP_STAFF;

    protected  string $template          = 'smartai';
    protected  string $pageName          = 'smartai';
    protected ?int    $activeTab         = parent::TAB_DATABASE;
    protected  array  $breadcrumb        = [0, 106];

    protected  array  $scripts           = [[SC_JS_FILE, 'js/filters.js']];   // fi_toggle(), used to (un)collapse the search form

    protected  array  $expectedGET       = array(
        'src' => ['filter' => FILTER_VALIDATE_INT, 'flags' => FILTER_REQUIRE_SCALAR],
        'evt' => ['filter' => FILTER_VALIDATE_INT, 'flags' => FILTER_REQUIRE_SCALAR],
        'act' => ['filter' => FILTER_VALIDATE_INT, 'flags' => FILTER_REQUIRE_SCALAR],
        'ref' => ['filter' => FILTER_VALIDATE_INT, 'flags' => FILTER_REQUIRE_SCALAR],
        'ent' => ['filter' => FILTER_VALIDATE_INT, 'flags' => FILTER_REQUIRE_SCALAR]
    );


    public array $srcTypeList = [];                         // for the form in the template
    public array $evtTypeList = [];
    public array $actTypeList = [];
    public array $formValues  = [];

    // no single Type:: - a browser over `smart_scripts`, filtered by ?src/?evt/?act/?ref/?ent
    // rather than a Filter object that TrListPage's default key would pick up on its own
    public function getCacheKeyComponents() : array
    {
        return array(-10, -1, User::$groups, md5(serialize($this->_get)));
    }

    protected function generate() : void
    {
        $this->h1 = Util::ucFirst(Lang::game('smartAI'));


        /**************/
        /* Page Title */
        /**************/

        array_unshift($this->title, $this->h1);


        /****************/
        /* Main Content */
        /****************/

        $this->redButtons[BUTTON_WOWHEAD] = false;

        $this->srcTypeList = Lang::smartaiBrowser('srcTypes');
        $this->evtTypeList = self::nameList(Lang::smartAI('events'));
        $this->actTypeList = self::nameList(Lang::smartAI('actions'));
        // FILTER_VALIDATE_INT yields false - not null - for the empty string the "Any" option submits,
        // and false == 0 in a loose comparison, so anything but a real int has to become null here or
        // the template marks event/action type 0 as the selected one
        $asInt = fn(string $k) : ?int => is_int($this->_get[$k] ?? null) ? $this->_get[$k] : null;

        $this->formValues  = array(
            'src' => $asInt('src'),
            'evt' => $asInt('evt'),
            'act' => $asInt('act'),
            'ref' => $asInt('ref') ?? 0,
            'ent' => $asInt('ent') ?? 0
        );

        $this->pageTemplate['filter'] = ($this->formValues['src'] !== null || $this->formValues['evt'] !== null ||
            $this->formValues['act'] !== null || $this->formValues['ref'] || $this->formValues['ent']) ? 1 : 0;

        $rows = SmartAI::browse(array(
            'srcType'    => $this->formValues['src'] !== null ? [$this->formValues['src']] : [],
            'eventType'  => $this->formValues['evt'] ?? -1,
            'actionType' => $this->formValues['act'] ?? -1,
            'refId'      => $this->formValues['ref'],
            'entry'      => $this->formValues['ent'],
            'limit'      => Listview::DEFAULT_SIZE
        ));

        $tabData = ['data' => $this->buildListviewData($rows)];

        $this->lvTabs = new Tabs(['parent' => "\$\$WH.ge('tabs-generic')"]);
        $this->lvTabs->addListviewTab(new Listview($tabData, 'smartai', 'smartai'));

        parent::generate();
    }

    /** Lang::smartAI() returns [format, formatRepeat] pairs; the form only needs the first line of each */
    private static function nameList(array $src) : array
    {
        $out = [];
        foreach ($src as $id => $fmt)
        {
            $name = is_array($fmt) ? ($fmt[0] ?? '') : (string)$fmt;
            $name = trim(preg_replace('/\[[^\]]*\]|\([^)]*\)\?.*|%\d*\$?[a-z.\d]*/i', '', $name));

            $out[$id] = $name !== '' ? $name : '#'.$id;
        }

        return $out;
    }

    private function buildListviewData(array $rows) : array
    {
        $jsg        = [];
        $data       = [];
        $guidLookup = self::resolveGuids($rows);            // negative entryorguid -> owning entry, keyed [srcType][entryorguid]

        // a timed action list has no entity or page of its own - it only ever renders as an extra
        // tab on whichever creature/object/areatrigger script calls it; look up all of them up front
        $talIds    = array_column(array_filter($rows, fn($r) => $r['srcType'] == SmartAI::SRC_TYPE_ACTIONLIST), 'entry');
        $talOwners = $talIds ? SmartAI::getActionListOwners($talIds) : [];

        foreach ($rows as $i => $r)
        {
            $row = array(
                'id'          => $i,                        // a script has no id of its own; source type + entry is the key
                'srctype'     => $r['srcType'],
                'entry'       => $r['entry'],
                'nrows'       => $r['nRows'],
                'eventtypes'  => $r['eventTypes'],
                'actiontypes' => $r['actionTypes']
            );

            if ($r['srcType'] == SmartAI::SRC_TYPE_ACTIONLIST)
            {
                $owners = [];
                foreach ($talOwners[$r['entry']] ?? [] as [$ownerType, $ownerEntry])
                    $owners[] = ['id' => $ownerEntry] + $this->linkFields($ownerType, $ownerEntry, $jsg);

                if ($owners)
                    $row['owners'] = $owners;
            }
            else
            {
                $type = match ($r['srcType'])
                {
                    SmartAI::SRC_TYPE_CREATURE    => Type::NPC,
                    SmartAI::SRC_TYPE_OBJECT      => Type::OBJECT,
                    SmartAI::SRC_TYPE_AREATRIGGER => Type::AREATRIGGER,
                    default                       => 0
                };

                // a negative entryorguid is a spawn guid, not a template entry; resolve it through
                // ::spawns so a guid-scoped script still links to the creature/object it overrides
                $linkId = $r['entry'] > 0 ? $r['entry'] : ($guidLookup[$r['srcType']][$r['entry']] ?? 0);

                if ($type && $linkId > 0)
                    $row = array('linkid' => $linkId) + $this->linkFields($type, $linkId, $jsg) + $row;
            }

            $data[] = $row;
        }

        $this->extendGlobalData($jsg);

        return $data;
    }

    /** @return array {url, lookup?, name?} - lookup keys into a window g_* global, name is a pre-resolved fallback for types (e.g. areatrigger) that carry none */
    private function linkFields(int $type, int $entry, array &$jsg) : array
    {
        $out = ['url' => Type::getFileString($type)];

        // AreaTriggerList::getJSGlobals() is a stub (Type::AREATRIGGER has no g_* window global to
        // pull a name from) - resolve the name here instead of client-side
        if ($type == Type::AREATRIGGER)
            $out['name'] = (string)AreaTriggerList::getName($entry);
        else if ($_ = Type::getJSGlobalString($type))
        {
            $out['lookup']  = $_;
            $jsg[$type][$entry] = $entry;
        }

        return $out;
    }

    /** @return array [srcType][entryorguid (negative)] => resolved template entry */
    private static function resolveGuids(array $rows) : array
    {
        $guidsByType = [];                                  // Type::NPC|Type::OBJECT => guid[] (positive)
        foreach ($rows as $r)
        {
            if ($r['entry'] >= 0)
                continue;

            if ($r['srcType'] == SmartAI::SRC_TYPE_CREATURE)
                $guidsByType[Type::NPC][] = -$r['entry'];
            else if ($r['srcType'] == SmartAI::SRC_TYPE_OBJECT)
                $guidsByType[Type::OBJECT][] = -$r['entry'];
        }

        $out = [];
        foreach ($guidsByType as $type => $guids)
        {
            $srcType = $type == Type::NPC ? SmartAI::SRC_TYPE_CREATURE : SmartAI::SRC_TYPE_OBJECT;
            foreach (DB::Aowow()->selectCol('SELECT `guid` AS ARRAY_KEY, `typeId` FROM ::spawns WHERE `type` = %i AND `guid` IN %in', $type, $guids) ?: [] as $guid => $typeId)
                $out[$srcType][-$guid] = $typeId;
        }

        return $out;
    }
}

?>
