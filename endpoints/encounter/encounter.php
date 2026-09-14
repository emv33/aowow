<?php

namespace Aowow;

if (!defined('AOWOW_REVISION'))
    die('illegal access');


class EncounterBaseResponse extends TemplateResponse implements ICache
{
    use TrDetailPage, TrCache;

    protected  int    $cacheType         = CACHE_TYPE_DETAIL_PAGE;
    protected  int    $requiredUserGroup = U_GROUP_STAFF;

    protected  string $template          = 'detail-page-generic';
    protected  string $pageName          = 'encounter';
    protected ?int    $activeTab         = parent::TAB_DATABASE;
    protected  array  $breadcrumb        = [0, 107];

    public int $type   = Type::ENCOUNTER;
    public int $typeId = 0;

    private EncounterList $subject;

    public function __construct(string $id)
    {
        parent::__construct($id);

        $this->typeId     = intVal($id);
        $this->contribute = Type::getClassAttrib($this->type, 'contribute') ?? CONTRIBUTE_NONE;
    }

    protected function generate() : void
    {
        $this->subject = new EncounterList(array(['ie.entry', $this->typeId]));   // `id` is a select alias only; filter on the real column
        if ($this->subject->error)
            $this->generateNotFound(Lang::game('encounter'), Lang::encounter('notFound'));

        $this->h1 = $this->subject->getField('name');

        $this->gPageInfo += array(
            'type'   => $this->type,
            'typeId' => $this->typeId,
            'name'   => $this->h1
        );


        /**************/
        /* Page Title */
        /**************/

        array_unshift($this->title, $this->h1, Util::ucFirst(Lang::game('encounter')));


        /****************/
        /* Main Content */
        /****************/

        $this->extendGlobalData($this->subject->getJSGlobals());

        $infobox = [];

        // the instance this encounter belongs to
        $mapId = $this->subject->getField('mapId');
        if ($areaId = (EncounterList::mapsToAreas([$mapId])[$mapId] ?? 0))
        {
            $this->extendGlobalIds(Type::ZONE, $areaId);
            $infobox[] = Lang::encounter('instance').Lang::main('colon').'[zone='.$areaId.']';
        }
        else if ($mapId)
            $infobox[] = Lang::encounter('instance').Lang::main('colon').'[b]'.$mapId.'[/b]';

        // difficulty mode, as spelled by DungeonEncounter.dbc
        if ($_ = Lang::encounter('modes', $this->subject->getField('mode')))
            $infobox[] = Lang::encounter('mode').Lang::main('colon').$_;

        if ($_ = $this->subject->getField('order'))
            $infobox[] = Lang::encounter('order').Lang::main('colon').($_ + 1);

        if ($this->subject->getField('lastBoss'))
            $infobox[] = '[span class=q2]'.Lang::encounter('lastBoss').'[/span]';

        // what the core actually credits
        if ($creditEntry = $this->subject->getField('creditEntry'))
        {
            $isSpell   = $this->subject->getField('creditType') == EncounterList::CREDIT_CAST_SPELL;
            $infobox[] = Lang::encounter($isSpell ? 'creditSpell' : 'creditKill').Lang::main('colon').
                         ($isSpell ? '[spell='.$creditEntry.']' : '[npc='.$creditEntry.']');
        }

        $this->infobox = $infobox ? new InfoboxMarkup($infobox, ['allow' => Markup::CLASS_STAFF, 'dbpage' => true], 'infobox-contents0') : null;

        $this->redButtons = array(
            BUTTON_LINKS   => ['type' => $this->type, 'typeId' => $this->typeId],
            BUTTON_WOWHEAD => false
        );


        /**************/
        /* Extra Tabs */
        /**************/

        $this->lvTabs = new Tabs(['parent' => "\$\$WH.ge('tabs-generic')"], 'tabsRelated', true);

        // tab: the creature that credits this encounter
        if ($creditEntry && $this->subject->getField('creditType') == EncounterList::CREDIT_KILL_CREATURE)
        {
            $npcs = new CreatureList(array(['id', $creditEntry]));
            if (!$npcs->error)
            {
                $this->extendGlobalData($npcs->getJSGlobals());

                $this->addDataLoader('zones');
                $this->lvTabs->addListviewTab(new Listview(array(
                    'data' => $npcs->getListviewData(),
                    'name' => Lang::encounter('creditedBy'),
                    'id'   => 'credited-by'
                ), CreatureList::$brickFile));
            }
        }

        // tab: the other encounters of the same instance
        // the map is a DBC field, so this tab is empty unless DungeonEncounter.dbc was imported
        if ($siblingIds = EncounterList::getIdsForMap($mapId, $this->typeId))
        {
            $siblings = new EncounterList(array(['ie.entry', $siblingIds]));
            if (!$siblings->error)
            {
                $this->extendGlobalData($siblings->getJSGlobals());
                $this->lvTabs->addListviewTab(new Listview(array(
                    'data' => $siblings->getListviewData(),
                    'name' => Lang::encounter('sameInstance'),
                    'id'   => 'same-instance'
                ), EncounterList::$brickFile, 'encounter'));
            }
        }

        parent::generate();
    }
}

?>
