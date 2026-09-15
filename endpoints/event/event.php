<?php

namespace Aowow;

if (!defined('AOWOW_REVISION'))
    die('illegal access');


class EventBaseResponse extends TemplateResponse implements ICache
{
    use TrDetailPage, TrCache;

    protected  int    $cacheType  = CACHE_TYPE_DETAIL_PAGE;

    protected  string $template   = 'detail-page-generic';
    protected  string $pageName   = 'event';
    protected ?int    $activeTab  = parent::TAB_DATABASE;
    protected  array  $breadcrumb = [0, 11];

    public int   $type   = Type::WORLDEVENT;
    public int   $typeId = 0;
    public array $dates  = [];

    private WorldEventList $subject;

    public function __construct(string $id)
    {
        parent::__construct($id);

        $this->typeId     = intVal($id);
        $this->contribute = Type::getClassAttrib($this->type, 'contribute') ?? CONTRIBUTE_NONE;
    }

    protected function generate() : void
    {
        $this->applyXRef();                                 // aowow - custom

        $this->subject = new WorldEventList(array(['id', $this->typeId]));
        if ($this->subject->error)
            $this->generateNotFound(Lang::game('event'), Lang::event('notFound'));

        $this->h1    = $this->subject->getField('name', true);
        $this->dates = array(
            'firstDate' => $this->subject->getField('startTime'),
            'lastDate'  => $this->subject->getField('endTime'),
            'length'    => $this->subject->getField('length'),
            'rec'       => $this->subject->getField('occurence')
        );

        $this->gPageInfo += array(
            'type'   => $this->type,
            'typeId' => $this->typeId,
            'name'   => $this->h1
        );

        $_holidayId = $this->subject->getField('holidayId');


        /*************/
        /* Menu Path */
        /*************/

        $this->breadcrumb[] = match ($this->subject->getField('scheduleType'))
        {
            -1      => 1,
             0, 1   => 2,
             2      => 3,
            ''      => 0,
            default => 0
        };


        /**************/
        /* Page Title */
        /**************/

        array_unshift($this->title, $this->h1, Util::ucWords(Lang::game('event')));


        /***********/
        /* Infobox */
        /***********/

        $infobox = Lang::getInfoBoxForFlags($this->subject->getField('cuFlags'));

        // boss
        if ($_ = $this->subject->getField('bossCreature'))
        {
            $this->extendGlobalIds(Type::NPC, $_);
            $infobox[] = Lang::npc('rank', 3).Lang::main('colon').'[npc='.$_.']';
        }

        // id
        $infobox[] = Lang::event('id') . $this->typeId;

        // display holiday id to staff
        if ($_holidayId && User::isInGroup(U_GROUP_STAFF))
            $infobox[] = 'Holiday ID'.Lang::main('colon').$_holidayId;

        // icon
        if ($_ = $this->subject->getField('iconId'))
        {
            $infobox[] = Util::ucFirst(Lang::game('icon')).Lang::main('colon').'[icondb='.$_.' name=true]';
            $this->extendGlobalIds(Type::ICON, $_);
        }

        // aowow - custom start: the two event tables no page read
        // `game_event_prerequisite` was the only one of them looked at, so an event's own progress
        // counters and the spawn pools it switches on were invisible
        $this->buildEventProgress();
        // aowow - custom end

        // original name
        if (Lang::getLocale() != Locale::EN)
            $infobox[] = Util::ucFirst(Lang::lang(Locale::EN->value) . Lang::main('colon')) . '[copy button=false]'.$this->subject->getField('name_loc0').'[/copy][/li]';

        if ($infobox)
            $this->infobox = new InfoboxMarkup($infobox, ['allow' => Markup::CLASS_STAFF, 'dbpage' => true], 'infobox-contents0');


        /****************/
        /* Main Content */
        /****************/

        // no entry in ::articles? use default HolidayDescription
        if ($_holidayId && empty($this->article))
            $this->article = new Markup($this->subject->getField('description', true), ['dbpage' => true]);

        if ($_holidayId)
            $this->wowheadLink = sprintf(WOWHEAD_LINK, Lang::getLocale()->domain(), 'event=', $_holidayId);

        $this->headIcons  = [$this->subject->getField('iconString')];
        $this->redButtons = array(
            BUTTON_WOWHEAD => $_holidayId > 0,
            BUTTON_LINKS   => ['type' => $this->type, 'typeId' => $this->typeId]
        );

        parent::generate();


        /**************/
        /* Extra Tabs */
        /**************/

        $this->lvTabs = new Tabs(['parent' => "\$\$WH.ge('tabs-generic')"], 'tabsRelated', true);

        // tab: npcs
        $creatures = null;
        if ($npcIds = DB::World()->selectCol('SELECT `id` AS ARRAY_KEY, IF(ec.`eventEntry` > 0, 1, 0) AS "added" FROM creature c, game_event_creature ec WHERE ec.`guid` = c.`guid` AND ABS(ec.`eventEntry`) = %i', $this->typeId))
        {
            $creatures = new CreatureList(array(['id', array_keys($npcIds)]));
            if (!$creatures->error)
            {
                $data = $creatures->getListviewData();
                foreach ($data as &$d)
                    $d['method'] = $npcIds[$d['id']];

                $tabData = ['data' => $data];

                if ($_holidayId && CreatureListFilter::getCriteriaIndex(38, $_holidayId))
                    $tabData['note'] = sprintf(Util::$filterResultString, '?npcs&filter=cr=38;crs='.$_holidayId.';crv=0');

                $this->result->addDataLoader('zones');      // req. by secondary tooltip in this tab
                $this->lvTabs->addListviewTab(new Listview($tabData, CreatureList::$brickFile));
            }
        }

        // tab: objects
        if ($objectIds = DB::World()->selectCol('SELECT `id` AS ARRAY_KEY, IF(eg.`eventEntry` > 0, 1, 0) AS "added" FROM gameobject g, game_event_gameobject eg WHERE eg.`guid` = g.`guid` AND ABS(eg.`eventEntry`) = %i', $this->typeId))
        {
            $objects = new GameObjectList(array(['id', array_keys($objectIds)]));
            if (!$objects->error)
            {
                $data = $objects->getListviewData();
                foreach ($data as &$d)
                    $d['method'] = $objectIds[$d['id']];

                $tabData = ['data' => $data];

                if ($_holidayId && GameObjectListFilter::getCriteriaIndex(16, $_holidayId))
                    $tabData['note'] = sprintf(Util::$filterResultString, '?objects&filter=cr=16;crs='.$_holidayId.';crv=0');

                $this->result->addDataLoader('zones');      // req. by secondary tooltip in this tab
                $this->lvTabs->addListviewTab(new Listview($tabData, GameObjectList::$brickFile));
            }
        }

        // tab: achievements
        $exclAcvs = [];
        if ($_ = $this->subject->getField('achievementCatOrId'))
        {
            $condition = $_ > 0 ? [['category', $_]] : [['id', -$_]];
            $acvs = new AchievementList($condition);
            if (!$acvs->error)
            {
                $this->extendGlobalData($acvs->getJSGlobals(GLOBALINFO_SELF | GLOBALINFO_RELATED));

                $tabData = array(
                    'data'        => $acvs->getListviewData(),
                    'visibleCols' => ['category']
                );

                // don't reuse for criteria-of tab
                $exclAcvs = array_keys($tabData['data']);

                if ($_holidayId && AchievementListFilter::getCriteriaIndex(11, $_holidayId))
                    $tabData['note'] = sprintf(Util::$filterResultString, '?achievements&filter=cr=11;crs='.$_holidayId.';crv=0');

                $this->lvTabs->addListviewTab(new Listview($tabData, AchievementList::$brickFile));
            }
        }

        $itemCnd = [];
        if ($_holidayId)
        {
            // tab: criteria-of
            if ($extraCrt = DB::World()->selectCol('SELECT `criteria_id` FROM achievement_criteria_data WHERE `type` = %i AND `value1` = %i', ACHIEVEMENT_CRITERIA_DATA_TYPE_HOLIDAY, $_holidayId))
            {
                $condition = array(['ac.id', $extraCrt]);
                if ($exclAcvs)
                    $condition[] = ['a.id', $exclAcvs, '!'];

                $crtOf = new AchievementList($condition);
                if (!$crtOf->error)
                {
                    $this->extendGlobalData($crtOf->getJSGlobals());

                    $this->lvTabs->addListviewTab(new Listview(array(
                        'data' => $crtOf->getListviewData(),
                        'name' => '$LANG.tab_criteriaof',
                        'id'   => 'criteria-of'
                    ), AchievementList::$brickFile));
                }
            }

            $itemCnd[] = ['eventId', $this->typeId];        // direct requirement on item
        }

        // tab: quests (by table, go & creature)
        $quests = new QuestList(array(['eventId', $this->typeId]));
        if (!$quests->error)
        {
            $this->extendGlobalData($quests->getJSGlobals(GLOBALINFO_SELF | GLOBALINFO_REWARDS));

            $tabData = ['data'=> $quests->getListviewData()];

            if (QuestListFilter::getCriteriaIndex(33, $_holidayId))
                $tabData['note'] = sprintf(Util::$filterResultString, '?quests&filter=cr=33;crs='.$_holidayId.';crv=0');

            $this->lvTabs->addListviewTab(new Listview($tabData, QuestList::$brickFile));

            $questItems = [];
            foreach (array_column($quests->rewards, Type::ITEM) as $arr)
                $questItems = array_merge($questItems, array_keys($arr));

            foreach (array_column($quests->choices, Type::ITEM) as $arr)
                $questItems = array_merge($questItems, array_keys($arr));

            foreach (array_column($quests->requires, Type::ITEM) as $arr)
                $questItems = array_merge($questItems, $arr);

            if ($questItems)
                $itemCnd[] = ['id', $questItems];
        }

        // items from creature
        if ($creatures && !$creatures->error)
        {
            // vendor
            $cIds = $creatures->getFoundIDs();
            if ($sells = DB::World()->selectCol(
               'SELECT     `item` FROM npc_vendor nv                                                               WHERE     `entry` IN %in UNION
                SELECT nv1.`item` FROM npc_vendor nv1             JOIN npc_vendor nv2 ON -nv1.`entry` = nv2.`item` WHERE nv2.`entry` IN %in UNION
                SELECT     `item` FROM game_event_npc_vendor genv JOIN creature   c   ON genv.`guid`  =   c.`guid` WHERE   c.`id`    IN %in',
                $cIds, $cIds, $cIds
            ))
                $itemCnd[] = ['id', $sells];
        }

        // tab: items
        // not checking for loot ... cant distinguish between eventLoot and fillerCrapLoot
        if ($itemCnd)
        {
            array_unshift($itemCnd, DB::OR);
            $eventItems = new ItemList($itemCnd);
            if (!$eventItems->error)
            {
                $this->extendGlobalData($eventItems->getJSGlobals(GLOBALINFO_SELF));

                $tabData = ['data'=> $eventItems->getListviewData()];

                if ($_holidayId && ItemListFilter::getCriteriaIndex(160, $_holidayId))
                    $tabData['note'] = sprintf(Util::$filterResultString, '?items&filter=cr=160;crs='.$_holidayId.';crv=0');

                $this->lvTabs->addListviewTab(new Listview($tabData, ItemList::$brickFile));
            }
        }

        // tab: see also (event conditions)
        if ($rel = DB::World()->selectCol('SELECT IF(`eventEntry` = `prerequisite_event`, NULL, IF(`eventEntry` = %i, `prerequisite_event`, -`eventEntry`)) FROM game_event_prerequisite WHERE `prerequisite_event` = %i OR `eventEntry` = %i', $this->typeId, $this->typeId, $this->typeId))
        {
            if (array_filter($rel, fn($x) => $x === null))
                trigger_error('game_event_prerequisite: this event has itself as prerequisite', E_USER_WARNING);

            if ($seeAlso = array_filter($rel, fn($x) => $x > 0))
            {
                $relEvents = new WorldEventList(array(['id', $seeAlso]));
                $this->extendGlobalData($relEvents->getJSGlobals());
                $relData   = $relEvents->getListviewData();
                foreach ($relEvents->getFoundIDs() as $id)
                    Conditions::extendListviewRow($relData[$id], Conditions::SRC_NONE, $this->typeId, [-Conditions::ACTIVE_EVENT, $this->typeId]);

                $this->extendGlobalData($this->subject->getJSGlobals());
                $d = $this->subject->getListviewData();
                foreach ($rel as $r)
                    if ($r > 0)
                        if (Conditions::extendListviewRow($d[$this->typeId], Conditions::SRC_NONE, $this->typeId, [-Conditions::ACTIVE_EVENT, $r]))
                            $this->extendGlobalIds(Type::WORLDEVENT, $r);

                $tabData = array(
                    'data'       => array_merge($relData, $d),
                    'id'         => 'see-also',
                    'name'       => '$LANG.tab_seealso',
                    'hiddenCols' => ['date'],
                    'extraCols'  => ['$Listview.extraCols.condition']
                );
                $this->lvTabs->addListviewTab(new Listview($tabData, WorldEventList::$brickFile));
            }
        }

        // tab: condition for
        $cnd = new Conditions();
        $cnd->getByCondition(Type::WORLDEVENT, $this->typeId)->prepare();
        if ($tab = $cnd->toListviewTab('condition-for', '$LANG.tab_condition_for'))
        {
            $this->extendGlobalData($cnd->getJSGlobals());
            $this->lvTabs->addDataTab(...$tab);
        }

        $this->result->registerDisplayHook('lvTabs',       [self::class, 'tabsHook']);
        $this->result->registerDisplayHook('infobox',      [self::class, 'infoboxHook']);
        $this->result->registerDisplayHook('metaTags',     [self::class, 'updateMetaHook'], true);
        $this->result->registerDisplayHook('ldIntangible', [self::class, 'updateMetaHook'], false);
    }

    protected function generateMetadata(bool $useArticle = true) : void
    {
        $this->metaTags[] = ['property' => 'og:title', 'content' => $this->h1];
        $this->metaTags[] = ['property' => 'og:type',  'content' => 'article'];

        $catName  = Lang::event('category', $this->breadcrumb[2]);
        $keywords = [$this->h1, Util::ucFirst(Lang::game('event'))];

        $desc = Lang::meta('description', 'genPage', [$this->h1, Util::ucFirst(Lang::game('event'))]);
        if ($catName)
        {
            $keywords[] = $catName;
            $desc      .= ' '.Lang::meta('inCategory', [$catName]);
        }

        array_unshift($this->metaTags, ['name' => 'keywords', 'content' => [...$keywords, ...Lang::meta('tags', 'generic')]]);

        $this->buildBasicMetadata($desc, $this->headIcons[0] != 'trade_engineering' ? $this->headIcons[0] : '');

        $this->buildLdJson();
    }

    // update dates to now()
    public static function tabsHook(Template\PageTemplate &$pt, Tabs &$lvTabs) : void
    {
        foreach ($lvTabs->iterate() as &$listview)
            if (is_object($listview) && $listview?->getTemplate() == 'holiday')
                WorldEventList::updateListview($listview);
    }

    /* finalize infobox */
    public static function infoboxHook(Template\PageTemplate &$pt, ?InfoboxMarkup &$markup) : void
    {
        WorldEventList::updateDates($pt->dates, $start, $end, $rec);
        $infobox = [];

        // start
        if ($start)
            $infobox[] = Lang::event('start').date(Lang::main('dateFmtLong'), $start);

        // end
        if ($end)
            $infobox[] = Lang::event('end').date(Lang::main('dateFmtLong'), $end);

        // interval
        if ($rec > 0)
            $infobox[] = Lang::event('interval').DateTime::formatTimeElapsed($rec * 1000);

        // in progress
        if ($start < time() && $end > time())
            $infobox[] = '[span class=q2]'.Lang::event('inProgress').'[/span]';

        if ($infobox && !$markup)
            $markup = new InfoboxMarkup($infobox, ['allow' => Markup::CLASS_STAFF, 'dbpage' => true], 'infobox-contents0');
        else if ($markup)
            foreach ($infobox as $ib)
                $markup->addItem($ib);
    }

    public static function updateMetaHook(Template\PageTemplate &$pt, array &$var, bool $isMeta) : void
    {
        $desc = null;
        if ($isMeta && ($k = array_find_key($var, fn($x) => ($x['name'] ?? '') == 'description')) !== null)
            $desc = &$var[$k]['content'];
        else if (!$isMeta && isset($var['description']))
            $desc = &$var['description'];

        if (!$desc)
            return;

        WorldEventList::updateDates($pt->dates, $start, $end);

        // not in progress
        if ($start > time())
            return;

        // Intl as baseline does not unterstand day-ordinals. So we have to invoke date() for Locale::EN .. fun
        $p = Lang::meta('eventEndsFmt');
        if (Lang::getLocale() == Locale::EN)
            $p = sprintf($p, date("'jS'", $end));           // single quoted so the string is escaped for Intl

        if ($endStr = \IntlDateFormatter::create(Lang::getLocale()->hreflang(), pattern: $p)?->format($end))
            $desc .= ' ' . $endStr;
    }

    // aowow - custom start: progress conditions and spawn pools
    private static function hasTable(string $tbl) : bool
    {
        static $known = [];

        return $known[$tbl] ??= (bool)DB::World()->selectCell('SHOW TABLES LIKE %s', $tbl);
    }

    /**
     * `game_event_condition` is how a staged event measures its own progress - the Ahn'Qiraj war
     * effort turn-ins and the Scourge Invasion counters live here and nowhere else
     * `game_event_pool` switches whole spawn pools on for the duration of the event
     */
    private function buildEventProgress() : void
    {
        // staff gated like the other world DB blocks - the world state ids are core internals
        if (!User::isInGroup(U_GROUP_STAFF))
            return;

        $rows = [];

        if (self::hasTable('game_event_condition'))
        {
            $cnd = DB::World()->selectAssoc(
               'SELECT `condition_id`, `req_num`, `max_world_state`, `done_world_state`, `description`
                FROM   game_event_condition WHERE `eventEntry` = %i ORDER BY `condition_id` ASC',
                $this->typeId
            ) ?: [];

            foreach ($cnd as $c)
            {
                $label = (string)$c['description'] ?: Lang::eventExtra('unnamedCondition', [(int)$c['condition_id']]);
                $value = Lang::eventExtra('required', [(string)(float)$c['req_num']]);

                // the two world states are what the client's progress bar actually reads
                $maxWS  = (int)$c['max_world_state'];
                $doneWS = (int)$c['done_world_state'];
                if ($maxWS || $doneWS)
                    $value .= ' [small class=q0]'.Lang::eventExtra('worldStates', [$doneWS, $maxWS]).'[/small]';

                $rows[] = [$label, $value];
            }
        }

        if (self::hasTable('game_event_pool'))
        {
            $poolIds = array_map('intVal', DB::World()->selectCol('SELECT `pool_entry` FROM game_event_pool WHERE `eventEntry` = %i', $this->typeId) ?: []);
            $nPools  = count($poolIds);

            // an event usually switches on a mother pool, whose members are further pools rather
            // than spawns, so the tree is walked before its leaves are read
            if ($poolIds && self::hasTable('pool_pool'))
            {
                $open = $poolIds;
                while ($open)
                {
                    $children = array_map('intVal', DB::World()->selectCol('SELECT `pool_id` FROM pool_pool WHERE `mother_pool` IN %in', $open) ?: []);
                    $open     = array_values(array_diff($children, $poolIds));
                    $poolIds  = array_merge($poolIds, $open);
                }
            }

            $links = [];
            foreach (Pool::getMembers($poolIds) as $type => $ids)
            {
                $this->extendGlobalIds($type, ...$ids);
                foreach ($ids as $id)
                    $links[] = '['.Markup::getTagForType($type).'='.$id.']';
            }

            if ($links)
                $rows[] = [Lang::eventExtra('pools', [$nPools]), Lang::concat($links, Lang::CONCAT_NONE)];
        }

        // the holiday half of the event system, none of which was read anywhere
        if (self::hasTable('game_event_seasonal_questrelation'))
        {
            $questIds = array_map('intVal', DB::World()->selectCol('SELECT `questId` FROM game_event_seasonal_questrelation WHERE `eventEntry` = %i', $this->typeId) ?: []);
            if ($questIds)
            {
                $this->extendGlobalIds(Type::QUEST, ...$questIds);
                $rows[] = [Lang::eventExtra('seasonalQuests'), Lang::concat(array_map(fn($x) => '[quest='.$x.']', $questIds), Lang::CONCAT_NONE)];
            }
        }

        if (self::hasTable('game_event_quest_condition'))
        {
            $cndRows = DB::World()->selectAssoc('SELECT * FROM game_event_quest_condition WHERE `eventEntry` = %i', $this->typeId) ?: [];
            foreach ($cndRows as $r)
            {
                $lc = array_change_key_case($r, CASE_LOWER);
                $q  = (int)($lc['quest'] ?? $lc['questid'] ?? 0);
                if (!$q)
                    continue;

                $this->extendGlobalIds(Type::QUEST, $q);
                $value = Lang::eventExtra('questCondition', [
                    (int)($lc['condition_id'] ?? 0),
                    (string)(float)($lc['num'] ?? 0)
                ]);

                $rows[] = ['[quest='.$q.']', $value];
            }
        }

        if (self::hasTable('game_event_model_equip'))
        {
            $eqRows = DB::World()->selectAssoc('SELECT * FROM game_event_model_equip WHERE `eventEntry` = %i', $this->typeId) ?: [];
            if ($eqRows)
            {
                $parts = [];
                foreach ($eqRows as $r)
                {
                    $lc = array_change_key_case($r, CASE_LOWER);
                    $parts[] = Lang::eventExtra('modelEquipLine', [
                        (int)($lc['modelid'] ?? 0),
                        (int)($lc['equipment_id'] ?? 0)
                    ]);
                }

                $rows[] = [Lang::eventExtra('modelEquip'), implode(', ', $parts)];
            }
        }

        if (self::hasTable('game_event_mail'))
        {
            $mailRows = DB::World()->selectAssoc('SELECT * FROM game_event_mail WHERE `eventEntry` = %i', $this->typeId) ?: [];
            foreach ($mailRows as $r)
            {
                $lc = array_change_key_case($r, CASE_LOWER);
                $q  = (int)($lc['quest'] ?? 0);
                $m  = (int)($lc['mailtemplateid'] ?? 0);
                if (!$m)
                    continue;

                $value = '[url=?mail='.$m.']'.Lang::mail('untitled', [$m]).'[/url]';

                if ($sender = (int)($lc['senderentry'] ?? 0))
                {
                    $this->extendGlobalIds(Type::NPC, $sender);
                    $value .= ' '.Lang::eventExtra('mailFrom').' [npc='.$sender.']';
                }

                if ($q)
                {
                    $this->extendGlobalIds(Type::QUEST, $q);
                    $rows[] = ['[quest='.$q.']', $value];
                }
                else
                    $rows[] = [Lang::eventExtra('eventMail'), $value];
            }
        }

        if (self::hasTable('game_event_npcflag'))
        {
            $flagRows = DB::World()->selectAssoc('SELECT * FROM game_event_npcflag WHERE `eventEntry` = %i', $this->typeId) ?: [];
            if ($flagRows)
            {
                $nSpawns = count($flagRows);
                $flags   = array_unique(array_map(fn($r) => (int)(array_change_key_case($r, CASE_LOWER)['npcflag'] ?? 0), $flagRows));
                $rows[]  = [Lang::eventExtra('npcFlagSwap'), Lang::eventExtra('npcFlagSwapValue', [$nSpawns, implode(', ', array_map(fn($x) => '0x'.dechex($x), $flags))])];
            }
        }

        if (self::hasTable('game_event_npc_vendor'))
        {
            $vRows = DB::World()->selectAssoc('SELECT * FROM game_event_npc_vendor WHERE `eventEntry` = %i', $this->typeId) ?: [];
            if ($vRows)
            {
                $nItems   = count($vRows);
                $nVendors = count(array_unique(array_map(fn($r) => (int)(array_change_key_case($r, CASE_LOWER)['guid'] ?? 0), $vRows)));
                $rows[]   = [Lang::eventExtra('eventVendor'), Lang::eventExtra('eventVendorValue', [$nItems, $nVendors])];
            }
        }

        if (!$rows)
            return;

        $tbl = '';
        foreach ($rows as [$label, $value])
            $tbl .= '[tr][td][b]'.$label.'[/b][/td][td]'.$value.'[/td][/tr]';

        $css = '#event-progress-generic .grid { clear:left; display: grid; grid-template-columns: 280px auto; } ' .
               '#event-progress-generic .grid thead, #event-progress-generic .grid tbody, #event-progress-generic .grid tr { display: contents; }';

        $this->eventProgress = new Markup(
            '[style]'.$css.'[/style][pad][h3][toggler id=event-progress]'.Lang::eventExtra('title').'[/toggler][/h3]' .
            '[div id=event-progress clear=left][table class=grid]'.$tbl.'[/table][/div]',
            ['allow' => Markup::CLASS_ADMIN], 'event-progress-generic'
        );
    }
    // aowow - custom end
}

?>
