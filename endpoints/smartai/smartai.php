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
class SmartaiBaseResponse extends TemplateResponse
{
    use TrListPage;

    protected  int    $requiredUserGroup = U_GROUP_STAFF;

    protected  string $template          = 'smartai';
    protected  string $pageName          = 'smartai';
    protected ?int    $activeTab         = parent::TAB_DATABASE;
    protected  array  $breadcrumb        = [0, 106];

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
        $jsg  = [];
        $data = [];

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

            // a negative entryorguid is a guid, not a template entry - there is nothing to link it to
            $type = match ($r['srcType'])
            {
                SmartAI::SRC_TYPE_CREATURE    => Type::NPC,
                SmartAI::SRC_TYPE_OBJECT      => Type::OBJECT,
                SmartAI::SRC_TYPE_AREATRIGGER => Type::AREATRIGGER,
                default                       => 0
            };

            if ($type && $r['entry'] > 0 && ($_ = Type::getJSGlobalString($type)))
            {
                $row['entrylookup'] = $_;
                $row['entryurl']    = Type::getFileString($type);
                $jsg[$type][$r['entry']] = $r['entry'];
            }

            $data[] = $row;
        }

        $this->extendGlobalData($jsg);

        return $data;
    }
}

?>
