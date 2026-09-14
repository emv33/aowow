<?php

namespace Aowow;

if (!defined('AOWOW_REVISION'))
    die('illegal access');


class GossipBaseResponse extends TemplateResponse implements ICache
{
    use TrDetailPage, TrCache;

    protected  int    $cacheType         = CACHE_TYPE_DETAIL_PAGE;
    protected  int    $requiredUserGroup = U_GROUP_STAFF;

    protected  string $template          = 'detail-page-generic';
    protected  string $pageName          = 'gossip';
    protected ?int    $activeTab         = parent::TAB_DATABASE;
    protected  array  $breadcrumb        = [0, 104];

    public int $type   = Type::GOSSIP;
    public int $typeId = 0;

    private Gossip $subject;

    public function __construct(string $id)
    {
        parent::__construct($id);

        $this->typeId     = intVal($id);
        $this->contribute = Type::getClassAttrib($this->type, 'contribute') ?? CONTRIBUTE_NONE;
    }

    protected function generate() : void
    {
        // a menu may exist as options only (built by a script), so don't gate this on `gossip_menu` alone
        if (!Gossip::exists($this->typeId))
            $this->generateNotFound(Lang::game('gossip'), Lang::gossip('notFound'));

        $this->subject = new Gossip($this->typeId);
        if (!$this->subject->prepare())
            $this->generateNotFound(Lang::game('gossip'), Lang::gossip('notFound'));

        $this->h1 = Lang::gossip('menu', [$this->typeId]);

        $this->gPageInfo += array(
            'type'   => $this->type,
            'typeId' => $this->typeId,
            'name'   => $this->h1
        );


        /**************/
        /* Page Title */
        /**************/

        array_unshift($this->title, $this->h1, Util::ucFirst(Lang::game('gossip')));


        /****************/
        /* Main Content */
        /****************/

        $this->extendGlobalData($this->subject->getJSGlobals());
        $this->gossip = $this->subject->getMarkup();

        $this->redButtons = array(
            BUTTON_LINKS   => ['type' => $this->type, 'typeId' => $this->typeId],
            BUTTON_WOWHEAD => false
        );


        /**************/
        /* Extra Tabs */
        /**************/

        $this->lvTabs = new Tabs(['parent' => "\$\$WH.ge('tabs-generic')"], 'tabsRelated', true);

        $sources = GossipList::getSourcesFor([$this->typeId])[$this->typeId] ?? [];

        // tab: opened by [NPC]
        if ($npcIds = ($sources[Type::NPC] ?? []))
        {
            $npcs = new CreatureList(array(['id', array_values($npcIds)]));
            if (!$npcs->error)
            {
                $this->extendGlobalData($npcs->getJSGlobals());

                $this->addDataLoader('zones');
                $this->lvTabs->addListviewTab(new Listview(array(
                    'data' => $npcs->getListviewData(),
                    'name' => Lang::gossip('openedBy'),
                    'id'   => 'opened-by-npc'
                ), CreatureList::$brickFile));
            }
        }

        // tab: opened by [Object]
        if ($objIds = ($sources[Type::OBJECT] ?? []))
        {
            $objects = new GameObjectList(array(['id', array_values($objIds)]));
            if (!$objects->error)
            {
                $this->extendGlobalData($objects->getJSGlobals());

                $this->addDataLoader('zones');
                $this->lvTabs->addListviewTab(new Listview(array(
                    'data' => $objects->getListviewData(),
                    'name' => Lang::gossip('openedBy'),
                    'id'   => 'opened-by-object'
                ), GameObjectList::$brickFile));
            }
        }

        // tab: referenced by [Gossip Menu]
        $parents = DB::World()->selectCol('SELECT DISTINCT `MenuID` FROM gossip_menu_option WHERE `ActionMenuID` = %i AND `MenuID` <> %i', $this->typeId, $this->typeId) ?: [];
        if ($parents)
        {
            $menus = new GossipList(array(['gm.MenuID', array_map('intVal', $parents)]));   // `id` is a select alias only; filter on the real column
            if (!$menus->error)
            {
                $this->extendGlobalData($menus->getJSGlobals());
                $this->lvTabs->addListviewTab(new Listview(array(
                    'data' => $menus->getListviewData(),
                    'name' => Lang::gossip('referencedBy'),
                    'id'   => 'referenced-by'
                ), GossipList::$brickFile, 'gossip'));
            }
        }

        // tab: conditions
        $cnd = new Conditions();
        $cnd->getBySource([Conditions::SRC_GOSSIP_MENU, Conditions::SRC_GOSSIP_MENU_OPTION], group: $this->typeId)->prepare();
        if ($tab = $cnd->toListviewTab())
        {
            $this->extendGlobalData($cnd->getJSGlobals());
            $this->lvTabs->addDataTab(...$tab);
        }

        parent::generate();
    }
}

?>
