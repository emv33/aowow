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
    protected  int    $requiredUserGroup = U_GROUP_NONE;

    protected  string $template          = 'smartai';
    protected  string $pageName          = 'smartai';
    protected ?int    $activeTab         = parent::TAB_DATABASE;
    protected  array  $breadcrumb        = [0, 106];

    protected  array  $scripts           = [[SC_JS_FILE, 'js/filters.js']];   // fi_toggle(), used to (un)collapse the search form

    protected  array  $expectedGET       = array(
        'filter' => ['filter' => FILTER_CALLBACK,     'options' => [self::class, 'sanitizeFilter']],
        // the search form used to be plain GET parameters; kept so existing links keep working
        'src'    => ['filter' => FILTER_VALIDATE_INT, 'flags' => FILTER_REQUIRE_SCALAR],
        'evt'    => ['filter' => FILTER_VALIDATE_INT, 'flags' => FILTER_REQUIRE_SCALAR],
        'act'    => ['filter' => FILTER_VALIDATE_INT, 'flags' => FILTER_REQUIRE_SCALAR],
        'ref'    => ['filter' => FILTER_VALIDATE_INT, 'flags' => FILTER_REQUIRE_SCALAR],
        'ent'    => ['filter' => FILTER_VALIDATE_INT, 'flags' => FILTER_REQUIRE_SCALAR]
    );


    public array $srcTypeList = [];                         // for the form in the template
    public array $evtTypeList = [];
    public array $actTypeList = [];

    public function __construct(string $rawParam)
    {
        parent::__construct($rawParam);

        // old-style parameters: translate into the equivalent filter and continue there
        // (FILTER_VALIDATE_INT yields false - not null - for the empty string the "Any" option submitted)
        $legacy = array_filter(array_map(fn($k) => is_int($this->_get[$k] ?? null) ? $this->_get[$k] : null, ['src' => 'src', 'evt' => 'evt', 'act' => 'act', 'ref' => 'ref', 'ent' => 'ent']), fn($v) => $v !== null);
        if (!isset($this->_get['filter']) && $legacy)
        {
            $parts = [];
            if (isset($legacy['src']))
                $parts[] = 'ty='.$legacy['src'];

            $cr = [];
            if (!empty($legacy['ent']))
                $cr[] = [SmartAIFilter::CR_ENTRY, 3, $legacy['ent']];
            if (!empty($legacy['ref']))
                $cr[] = [SmartAIFilter::CR_REF, 3, $legacy['ref']];
            if (isset($legacy['evt']))
                $cr[] = [SmartAIFilter::CR_EVENT, $legacy['evt'], 0];
            if (isset($legacy['act']))
                $cr[] = [SmartAIFilter::CR_ACTION, $legacy['act'], 0];

            if ($cr)
                array_push($parts, 'cr='.implode(':', array_column($cr, 0)), 'crs='.implode(':', array_column($cr, 1)), 'crv='.implode(':', array_column($cr, 2)));

            $this->forward('?smartai&filter='.implode(';', $parts));
        }

        $this->filter = new SmartAIFilter($this->_get['filter'] ?? '');
        if ($this->filter->shouldReload)
        {
            $_SESSION['error']['fi'] = $this->filter::class;
            $get = $this->filter->buildGETParam();
            $this->forward('?' . $this->pageName . ($get ? '&filter=' . $get : ''));
        }
        $this->filterError = $this->filter->error;
    }

    // no single Type:: - a browser over `smart_scripts`
    public function getCacheKeyComponents() : array
    {
        return array(-10, -1, User::$groups, md5($this->filter->buildGETParam()));
    }

    protected function generate() : void
    {
        $opts = $this->filter->getBrowseOpts();

        // filtered down to one creature's own scripts - name the page after it instead of the
        // generic listing title; a negative entry is a spawn guid, resolved the same way
        // buildListviewData()/resolveGuids() do for the same field on every row
        $this->h1 = Util::ucFirst(Lang::game('smartAI'));

        $entCr = array_values(array_filter($opts['criteria'], fn($cr) => $cr['kind'] == SmartAIFilter::CR_ENTRY && $cr['op'] == '='));
        if ($opts['srcType'] == [SmartAI::SRC_TYPE_CREATURE] && count($entCr) == 1)
        {
            $npcId = $entCr[0]['value'];
            if ($npcId < 0)
                $npcId = self::resolveGuids([['srcType' => SmartAI::SRC_TYPE_CREATURE, 'entry' => $npcId]])[SmartAI::SRC_TYPE_CREATURE][$npcId] ?? 0;

            if ($npcId > 0 && ($npcName = CreatureList::getName($npcId)))
                $this->h1 = Lang::smartaiBrowser('titleNpc', [(string)$npcName]);
        }


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

        $this->srcTypeList = Lang::smartaiBrowser('srcTypes');
        $this->evtTypeList = self::nameList(Lang::smartAI('events'), 'SmartEvent', 'EVENT_');
        $this->actTypeList = self::nameList(Lang::smartAI('actions'), 'SmartAction', 'ACTION_');

        $rows = SmartAI::browse($opts + ['limit' => Listview::DEFAULT_SIZE]);

        $tabData = ['data' => $this->buildListviewData($rows)];

        $this->lvTabs = new Tabs(['parent' => "\$\$WH.ge('tabs-generic')"]);
        $this->lvTabs->addListviewTab(new Listview($tabData, 'smartai', 'smartai'));

        parent::generate();
    }

    /**
     * type id => name for the filter's dropdowns, from the names of the defines ("EVENT_HEALTH_PCT" > "Health Pct"):
     * the texts in Lang::smartAI() are sentences with holes in them, and read as "At  Health" once those are cut out
     *
     * @param  array  $known   type ids the texts name, anything else is not a type
     * @param  string $class   SmartEvent / SmartAction
     * @param  string $prefix  EVENT_ / ACTION_
     */
    private static function nameList(array $known, string $class, string $prefix) : array
    {
        $names = [];
        foreach ((new \ReflectionClass(__NAMESPACE__.'\\'.$class))->getConstants() as $const => $id)
            if (is_int($id) && str_starts_with($const, $prefix) && !isset($names[$id]))
                $names[$id] = ucwords(strtolower(str_replace('_', ' ', substr($const, strlen($prefix)))));

        $out = [];
        foreach (array_keys($known) as $id)
            $out[$id] = $names[$id] ?? '#'.$id;

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

        // a script has no id of its own; source type + entry is the key. The entry is zero-padded to
        // the widest one of its source type, like the game text ids, so the keys order as numbers do
        $width = [];
        foreach ($rows as $r)
            $width[$r['srcType']] = max($width[$r['srcType']] ?? 0, strlen((string)abs($r['entry'])));

        foreach ($rows as $r)
        {
            $row = array(
                'id'          => $r['srcType'].':'.($r['entry'] < 0 ? '-' : '').str_pad((string)abs($r['entry']), $width[$r['srcType']], '0', STR_PAD_LEFT),
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
