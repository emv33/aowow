<?php

namespace Aowow;

if (!defined('AOWOW_REVISION'))
    die('illegal access');


// TrinityCore - gossip_menu / gossip_menu_option / npc_text
//
// A gossip menu is spread over two tables keyed by MenuID:
//   `gossip_menu`        (MenuID, TextID)   - the flavour text; several candidates per menu, picked by CONDITION_SOURCE_TYPE_GOSSIP_MENU
//   `gossip_menu_option` (MenuID, OptionID) - the clickable lines, gated by CONDITION_SOURCE_TYPE_GOSSIP_MENU_OPTION
// Both are read live from the world DB, as neither is imported into the aowow DB.

class Gossip
{
    public const int MAX_DEPTH = 3;                         // ActionMenuID chains are cyclic in live data; stop descending here

    private const string BASE_CSS = <<<CSS
        #gossip-generic .grid { clear:left; display: grid; }
        #gossip-generic .grid thead,
        #gossip-generic .grid tbody,
        #gossip-generic .grid tr { display: contents; }
        #gossip-generic .gossip-text { white-space: pre-wrap; }
        #gossip-generic .gossip-sub { padding-left: 15px; }
    CSS;

    public readonly string $uid;                            // id for the [toggler]/[div] pair, so multiple menus can coexist on one page
    public readonly string $title;                          // title appendix for the [toggler]
    public readonly int    $depth;                          // recursion level; referenced menus are rendered inline up to MAX_DEPTH

    private array  $texts     = [];
    private array  $options   = [];
    private array  $jsGlobals = [];
    private array  $subMenus  = [];                         // menuId => Gossip
    private array  $seen      = [];                         // menuIds already rendered by an ancestor; cycle guard
    private string $gridCss   = '';
    private string $textTbl   = '';
    private string $optionTbl = '';
    private int    $friendly  = 0;                          // aowow - custom: faction the npc borrows while this menu is open

    public function __construct(public readonly int $menuId, array $miscData = [])
    {
        $this->uid   = $miscData['uid']   ?? 'gossip-'.$this->menuId;
        $this->title = $miscData['title'] ?? '';
        $this->depth = $miscData['depth'] ?? 0;
        $this->seen  = $miscData['seen']  ?? [$this->menuId];

        $this->texts   = $this->fetchTexts();
        $this->options = $this->fetchOptions();
    }


    /**********/
    /* Lookup */
    /**********/

    public static function exists(int $menuId) : bool
    {
        if ($menuId <= 0)
            return false;

        return (bool)DB::World()->selectCell(
           'SELECT 1 FROM gossip_menu WHERE `MenuID` = %i UNION SELECT 1 FROM gossip_menu_option WHERE `MenuID` = %i LIMIT 1',
            $menuId, $menuId
        );
    }

    /**
     * all menus an NPC can open: the template default plus anything its SmartAI sends
     *
     * @param  int   $npcId  creature_template.entry
     * @return array         menuIds
     */
    public static function getMenusForNPC(int $npcId) : array
    {
        $menus = [];

        if ($_ = DB::World()->selectCell('SELECT `gossip_menu_id` FROM creature_template WHERE `entry` = %i', $npcId))
            $menus[] = (int)$_;

        $menus = array_unique(array_merge($menus, self::getScriptedMenus(SmartAI::SRC_TYPE_CREATURE, $npcId)));
        sort($menus);

        return $menus;
    }

    /**
     * all menus an object can open; which `data<n>` holds the menu depends on the object type
     *
     * @param  int   $objectId  gameobject_template.entry
     * @return array            menuIds
     */
    public static function getMenusForObject(int $objectId) : array
    {
        $menus = [];

        if ($row = DB::World()->selectRow('SELECT `type`, `data3`, `data18` FROM gameobject_template WHERE `entry` = %i', $objectId))
        {
            $_ = match ((int)$row['type'])
            {
                GO_TYPE_QUESTGIVER => (int)$row['data3'],
                GO_TYPE_GOOBER     => (int)$row['data18'],
                default            => 0
            };

            if ($_)
                $menus[] = $_;
        }

        $menus = array_unique(array_merge($menus, self::getScriptedMenus(SmartAI::SRC_TYPE_OBJECT, $objectId)));
        sort($menus);

        return $menus;
    }

    private static function getScriptedMenus(int $srcType, int $entry) : array
    {
        $tbl   = $srcType == SmartAI::SRC_TYPE_CREATURE ? 'creature' : 'gameobject';
        $guids = DB::World()->selectCol('SELECT `guid` FROM %n WHERE `id` = %i', $tbl, $entry) ?: [];

        // guid specific scripts are stored as negative entryorguid
        $entries = array_merge([$entry], array_map(fn($g) => -$g, $guids));

        $menus = DB::World()->selectCol(
           'SELECT DISTINCT `action_param1` FROM smart_scripts WHERE `source_type` = %i AND `entryorguid` IN %in AND `action_type` = %i AND `action_param1` > 0',
            $srcType, $entries, SmartAction::ACTION_SEND_GOSSIP_MENU
        ) ?: [];

        return array_map('intVal', $menus);
    }


    /************/
    /* Fetching */
    /************/

    private function fetchTexts() : array
    {
        $textIds = DB::World()->selectCol('SELECT `TextID` FROM gossip_menu WHERE `MenuID` = %i ORDER BY `TextID` ASC', $this->menuId) ?: [];
        if (!$textIds)
            return [];

        $loc     = Lang::getLocale();
        $rows    = DB::World()->selectAssoc('SELECT nt.`ID` AS ARRAY_KEY, nt.* FROM npc_text nt WHERE nt.`ID` IN %in', $textIds) ?: [];
        $locRows = [];
        if ($loc != Locale::EN)
            $locRows = DB::World()->selectAssoc('SELECT ntl.`ID` AS ARRAY_KEY, ntl.* FROM npc_text_locale ntl WHERE ntl.`ID` IN %in AND ntl.`Locale` = %s', $textIds, $loc->json()) ?: [];

        // a set BroadcastTextID overrides the npc_text columns, the same way the core resolves it
        $bctIds = [];
        foreach ($rows as $r)
            for ($i = 0; $i < GOSSIP_TEXT_SLOT_COUNT; $i++)
                if ($_ = (int)($this->col($r, 'BroadcastTextID'.$i) ?? 0))
                    $bctIds[$_] = $_;

        $bct   = $this->fetchBroadcastTexts($bctIds);
        $texts = [];

        foreach ($textIds as $textId)
        {
            if (!($row = $rows[$textId] ?? null))
            {
                trigger_error('Gossip::fetchTexts - menu #'.$this->menuId.' references missing npc_text #'.$textId, E_USER_WARNING);
                $texts[$textId] = ['id' => $textId, 'slots' => [], 'missing' => true];
                continue;
            }

            $locRow = $locRows[$textId] ?? [];
            $slots  = [];

            for ($i = 0; $i < GOSSIP_TEXT_SLOT_COUNT; $i++)
            {
                $bctId  = (int)($this->col($row, 'BroadcastTextID'.$i) ?? 0);
                $male   = (string)($this->col($row, 'text'.$i.'_0') ?? '');
                $female = (string)($this->col($row, 'text'.$i.'_1') ?? '');

                if ($bctId && isset($bct[$bctId]))
                {
                    $male   = $bct[$bctId]['text']  ?: $male;
                    $female = $bct[$bctId]['text1'] ?: $female;
                }
                else if ($locRow)
                {
                    $male   = (string)($this->col($locRow, 'Text'.$i.'_0') ?: $male);
                    $female = (string)($this->col($locRow, 'Text'.$i.'_1') ?: $female);
                }

                if (!$male && !$female)
                    continue;

                $emotes = [];
                for ($j = 0; $j < GOSSIP_TEXT_EMOTE_COUNT; $j++)
                {
                    $delay = (int)($this->col($row, 'em'.$i.'_'.($j * 2))     ?? 0);
                    $emote = (int)($this->col($row, 'em'.$i.'_'.($j * 2 + 1)) ?? 0);
                    if ($emote)
                        $emotes[] = [$emote, $delay];
                }

                $slots[$i] = array(
                    'male'   => $male,
                    'female' => $female,
                    'lang'   => (int)($this->col($row, 'lang'.$i) ?? 0),
                    'prob'   => (float)($this->col($row, 'Probability'.$i) ?? $this->col($row, 'prob'.$i) ?? 0),
                    'bct'    => $bctId,
                    'emotes' => $emotes
                );
            }

            $texts[$textId] = ['id' => $textId, 'slots' => $slots, 'missing' => false];
        }

        return $texts;
    }

    private function fetchOptions() : array
    {
        $rows = DB::World()->selectAssoc(
           'SELECT `OptionID`, `OptionIcon`, `OptionText`, `OptionBroadcastTextID`, `OptionType`, `OptionNpcflag`,
                   `ActionMenuID`, `ActionPoiID`, `BoxCoded`, `BoxMoney`, `BoxText`, `BoxBroadcastTextID`
            FROM     gossip_menu_option
            WHERE    `MenuID` = %i
            ORDER BY `OptionID` ASC',
            $this->menuId
        ) ?: [];

        if (!$rows)
            return [];

        $loc     = Lang::getLocale();
        $locRows = [];
        if ($loc != Locale::EN)
            foreach (DB::World()->selectAssoc('SELECT `OptionID`, `OptionText`, `BoxText` FROM gossip_menu_option_locale WHERE `MenuID` = %i AND `Locale` = %s', $this->menuId, $loc->json()) ?: [] as $l)
                $locRows[$l['OptionID']] = $l;

        $bctIds = [];
        foreach ($rows as $r)
        {
            if ($_ = (int)$r['OptionBroadcastTextID'])
                $bctIds[$_] = $_;
            if ($_ = (int)$r['BoxBroadcastTextID'])
                $bctIds[$_] = $_;
        }

        $bct     = $this->fetchBroadcastTexts($bctIds);
        $options = [];

        foreach ($rows as $r)
        {
            $oId     = (int)$r['OptionID'];
            $optText = (string)$r['OptionText'];
            $boxText = (string)$r['BoxText'];

            if (($_ = (int)$r['OptionBroadcastTextID']) && !empty($bct[$_]['text']))
                $optText = $bct[$_]['text'];
            else if (!empty($locRows[$oId]['OptionText']))
                $optText = $locRows[$oId]['OptionText'];

            if (($_ = (int)$r['BoxBroadcastTextID']) && !empty($bct[$_]['text']))
                $boxText = $bct[$_]['text'];
            else if (!empty($locRows[$oId]['BoxText']))
                $boxText = $locRows[$oId]['BoxText'];

            $options[$oId] = array(
                'id'           => $oId,
                'icon'         => (int)$r['OptionIcon'],
                'text'         => $optText,
                'type'         => (int)$r['OptionType'],
                'npcFlag'      => (int)$r['OptionNpcflag'],
                'actionMenuId' => (int)$r['ActionMenuID'],
                'actionPoiId'  => (int)$r['ActionPoiID'],
                'boxCoded'     => (int)$r['BoxCoded'],
                'boxMoney'     => (int)$r['BoxMoney'],
                'boxText'      => $boxText
            );
        }

        return $options;
    }

    /**
     * options without an ActionMenuID/ActionPoiID are usually driven by a script
     * resolve SMART_EVENT_GOSSIP_SELECT so the action column says who handles them
     *
     * @return array optionId => [type => entries]
     */
    private function fetchSmartHandlers() : array
    {
        $out = [];

        foreach (DB::World()->selectAssoc(
           'SELECT DISTINCT `event_param2` AS "optionId", `source_type` AS "srcType", `entryorguid` AS "entry"
            FROM   smart_scripts
            WHERE  `event_type` = %i AND `event_param1` = %i',
            SmartEvent::EVENT_GOSSIP_SELECT, $this->menuId) ?: [] as $r)
        {
            $type = match ((int)$r['srcType'])
            {
                SmartAI::SRC_TYPE_CREATURE => Type::NPC,
                SmartAI::SRC_TYPE_OBJECT   => Type::OBJECT,
                default                    => 0
            };

            if (!$type)
                continue;

            $entry = (int)$r['entry'];
            if ($entry < 0)                                 // guid specific script; resolve back to the template
                $entry = (int)DB::World()->selectCell(
                    $type == Type::NPC ? 'SELECT `id` FROM creature WHERE `guid` = %i' : 'SELECT `id` FROM gameobject WHERE `guid` = %i',
                    -$entry
                );

            if ($entry > 0)
                $out[(int)$r['optionId']][$type][$entry] = $entry;
        }

        return $out;
    }

    private function fetchBroadcastTexts(array $ids) : array
    {
        if (!$ids)
            return [];

        $loc = Lang::getLocale();
        $out = [];

        foreach (DB::World()->selectAssoc('SELECT `ID`, `Text`, `Text1` FROM broadcast_text WHERE `ID` IN %in', $ids) ?: [] as $r)
            $out[$r['ID']] = ['text' => (string)$r['Text'], 'text1' => (string)$r['Text1']];

        if ($loc != Locale::EN)
            foreach (DB::World()->selectAssoc('SELECT `ID`, `Text`, `Text1` FROM broadcast_text_locale WHERE `ID` IN %in AND `locale` = %s', $ids, $loc->json()) ?: [] as $r)
            {
                if (($_ = (string)$r['Text']) && isset($out[$r['ID']]))
                    $out[$r['ID']]['text'] = $_;
                if (($_ = (string)$r['Text1']) && isset($out[$r['ID']]))
                    $out[$r['ID']]['text1'] = $_;
            }

        return $out;
    }

    private function fetchPOIs(array $ids) : array
    {
        if (!$ids)
            return [];

        // column names differ between TC revisions; try the current schema first
        $rows = DB::World()->selectAssoc('SELECT `ID`, `PositionX` AS "x", `PositionY` AS "y", `Icon` AS "icon", `Name` AS "name" FROM points_of_interest WHERE `ID` IN %in', $ids);
        if ($rows === null)
            $rows = DB::World()->selectAssoc('SELECT `entry` AS "ID", `x`, `y`, `icon`, `icon_name` AS "name" FROM points_of_interest WHERE `entry` IN %in', $ids) ?: [];

        $out = [];
        foreach ($rows as $r)
            $out[$r['ID']] = ['x' => (float)$r['x'], 'y' => (float)$r['y'], 'icon' => (int)$r['icon'], 'name' => (string)$r['name']];

        return $out;
    }

    /** case insensitive column access; npc_text spells its columns differently across TC revisions */
    private function col(array $row, string $name) : mixed
    {
        static $maps = [];

        $key = md5(implode('|', array_keys($row)));
        $maps[$key] ??= array_combine(array_map('strtolower', array_keys($row)), array_keys($row));

        $realName = $maps[$key][strtolower($name)] ?? null;

        return $realName !== null ? $row[$realName] : null;
    }


    /*************/
    /* Rendering */
    /*************/

    public function prepare() : bool
    {
        if (!$this->texts && !$this->options)
            return false;

        if ($this->textTbl || $this->optionTbl)
            return true;

        $this->friendly  = $this->fetchFriendlyFaction();    // aowow - custom
        $this->textTbl   = $this->buildTextTable();
        $this->optionTbl = $this->buildOptionTable();

        // instantiate referenced menus, so a whole conversation is visible on one page
        foreach (array_keys($this->subMenus) as $subId)
        {
            $sub = new Gossip($subId, array(
                'depth' => $this->depth + 1,
                'uid'   => $this->uid.'-'.$subId,
                'seen'  => array_merge($this->seen, [$subId])
            ));

            if (!$sub->prepare())
            {
                unset($this->subMenus[$subId]);
                continue;
            }

            $this->subMenus[$subId] = $sub;
            $this->gridCss .= $sub->getGridCss();
            Util::mergeJsGlobals($this->jsGlobals, $sub->getJSGlobals());
        }

        return true;
    }

    /**
     * aowow - custom: `gossip_menu_addon` was read nowhere
     *
     * a menu can hand the npc a friendly faction template for as long as its window is open, which
     * is how a hostile or neutral creature can be talked to at all; the column names a
     * FactionTemplate.dbc row rather than a faction, so it is resolved before it is linked
     */
    private function fetchFriendlyFaction() : int
    {
        if (!DB::World()->selectCell('SHOW TABLES LIKE %s', 'gossip_menu_addon'))
            return 0;

        if (!($tplId = (int)DB::World()->selectCell('SELECT `FriendlyFaction` FROM gossip_menu_addon WHERE `MenuID` = %i', $this->menuId)))
            return 0;

        if (!($factionId = (int)DB::Aowow()->selectCell('SELECT `factionId` FROM ::factiontemplate WHERE `id` = %i', $tplId)))
            return 0;

        // registered here rather than where it is rendered: buildMarkupFor() collects the globals
        // before it asks for the body
        $this->jsGlobals[Type::FACTION][$factionId] = $factionId;

        return $factionId;
    }

    private function buildTextTable() : string
    {
        if (!$this->texts)
            return '';

        $hasCnd  = false;
        $hasProb = false;
        $rows    = [];

        foreach ($this->texts as $textId => $text)
        {
            $cnd = new Conditions();
            $cnd->getBySource(Conditions::SRC_GOSSIP_MENU, group: $this->menuId, entry: $textId)->prepare();
            $cndTag = $cnd->toMarkupTag();
            if ($cndTag)
            {
                $hasCnd = true;
                Util::mergeJsGlobals($this->jsGlobals, $cnd->getJSGlobals());
            }

            if ($text['missing'])
            {
                $rows[] = ['#[b]'.$textId.'[/b]', '[span class=q10]'.Lang::gossip('missingText', [$textId]).'[/span]', '', $cndTag];
                continue;
            }

            if (!$text['slots'])
            {
                $rows[] = ['#[b]'.$textId.'[/b]', '[i][small class=q0]'.Lang::gossip('emptyText').'[/small][/i]', '', $cndTag];
                continue;
            }

            $first = true;
            foreach ($text['slots'] as $i => $slot)
            {
                if ($slot['prob'] && $slot['prob'] != 1.0)
                    $hasProb = true;

                $body = [];
                if ($slot['male'] && $slot['female'] && $slot['male'] != $slot['female'])
                {
                    $body[] = '[small class=q0]'.Lang::gossip('male').'[/small][br][span class=gossip-text]'.UIText::format($slot['male'], Lang::FMT_MARKUP).'[/span]';
                    $body[] = '[small class=q0]'.Lang::gossip('female').'[/small][br][span class=gossip-text]'.UIText::format($slot['female'], Lang::FMT_MARKUP).'[/span]';
                }
                else
                    $body[] = '[span class=gossip-text]'.UIText::format($slot['male'] ?: $slot['female'], Lang::FMT_MARKUP).'[/span]';

                if ($slot['emotes'])
                {
                    $em = [];
                    foreach ($slot['emotes'] as [$emote, $delay])
                        $em[] = Lang::gossip('emote', [$emote]) . ($delay ? Lang::main('parensFmt', ['', Lang::gossip('emoteDelay', [$delay])]) : '');

                    $body[] = '[i][small class=q0]'.Lang::gossip('emotes').Lang::main('colon').Lang::concat($em).'[/small][/i]';
                }

                // the same ct:/bt:/nt: composite id GameText::browse() hands out for this exact
                // slot - a plain [url], not a tag: neither of these is a Type Markup knows about
                $gender     = $slot['male'] ? 0 : 1;
                $textLinkId = $slot['bct']
                    ? 'bt:'.$slot['bct'].':'.GameText::SRC_NPC_TEXT.':'.$this->menuId
                    : 'nt:'.$textId.':'.$i.':'.$gender.':'.$this->menuId;

                if ($slot['bct'])
                    $body[] = '[i][small class=q0][url=?text='.$textLinkId.']'.Lang::gossip('fromBroadcastText', [$slot['bct']]).'[/url][/small][/i]';

                $rows[] = array(
                    $first ? '[url=?text='.$textLinkId.']#[b]'.$textId.'[/b][/url]' : '',
                    implode('[br]', $body),
                    $slot['prob'] ? self::formatWeight($slot['prob']) : '',
                    $first ? $cndTag : ''
                );

                $first = false;
            }
        }

        $th = array(
            [Lang::gossip('textId'),    '90px'],
            [Lang::gossip('text'),      'auto'],
            [Lang::gossip('chance'),    '60px'],
            [Lang::gossip('condition'), 'auto']
        );

        $drop = [];
        if (!$hasProb)
            $drop[] = 2;
        if (!$hasCnd)
            $drop[] = 3;

        return $this->renderGrid($th, $rows, $drop);
    }

    private function buildOptionTable() : string
    {
        if (!$this->options)
            return '';

        $pois     = $this->fetchPOIs(array_filter(array_column($this->options, 'actionPoiId')));
        $handlers = $this->fetchSmartHandlers();
        $hasCnd   = false;
        $hasBox   = false;
        $hasAct   = false;
        $rows     = [];

        foreach ($this->options as $oId => $o)
        {
            $cnd = new Conditions();
            $cnd->getBySource(Conditions::SRC_GOSSIP_MENU_OPTION, group: $this->menuId, entry: $oId)->prepare();
            $cndTag = $cnd->toMarkupTag();
            if ($cndTag)
            {
                $hasCnd = true;
                Util::mergeJsGlobals($this->jsGlobals, $cnd->getJSGlobals());
            }

            // icon + text, reusing the sprites SmartEvent already ships
            $text    = $o['text'] ? UIText::format($o['text'], Lang::FMT_MARKUP) : '[i][small class=q0]'.Lang::gossip('emptyOption').'[/small][/i]';
            $optCell = '[span class="gossip'.self::iconToCSS($o['icon']).'"]'.$text.'[/span]';

            // what the option does
            $action = [];
            if ($o['type'] != GOSSIP_OPTION_NONE && $o['type'] != GOSSIP_OPTION_GOSSIP)
                if ($_ = Lang::gossip('optionTypes', $o['type']))
                    $action[] = '[b]'.$_.'[/b]';

            if ($o['actionMenuId'])
            {
                $action[] = Lang::gossip('opensMenu', [$o['actionMenuId'], $o['actionMenuId']]);

                if ($this->depth < self::MAX_DEPTH && !in_array($o['actionMenuId'], $this->seen))
                    $this->subMenus[$o['actionMenuId']] = $o['actionMenuId'];
            }

            if ($o['actionPoiId'])
            {
                $poi      = $pois[$o['actionPoiId']] ?? null;
                $poiName  = $poi && $poi['name'] ? $poi['name'] : Lang::gossip('unnamedPoi', [$o['actionPoiId']]);
                $action[] = Lang::gossip('marksPoi', [$poiName]) . ($poi ? ' [i][small class=q0]'.sprintf('%.1f, %.1f', $poi['x'], $poi['y']).'[/small][/i]' : '');
            }

            if ($byScript = ($handlers[$oId] ?? []))
            {
                $links = [];
                foreach ($byScript as $type => $entries)
                    foreach ($entries as $e)
                    {
                        $links[] = $type == Type::NPC ? '[npc='.$e.']' : '[object='.$e.']';
                        $this->jsGlobals[$type][$e] = $e;
                    }

                $action[] = Lang::gossip('handledBySmart', [Lang::concat($links)]);
            }

            if ($action)
                $hasAct = true;

            // confirmation box
            $box = [];
            if ($o['boxText'])
                $box[] = '[span class=gossip-text]'.UIText::format($o['boxText'], Lang::FMT_MARKUP).'[/span]';
            if ($o['boxMoney'])
                $box[] = Lang::gossip('boxMoney').Lang::main('colon').'[money='.$o['boxMoney'].']';
            if ($o['boxCoded'])
                $box[] = Lang::gossip('boxCoded');

            if ($box)
                $hasBox = true;

            $rows[] = array(
                '#[b]'.$oId.'[/b]',
                $optCell,
                $action ? implode('[br]', $action) : '',
                implode('[br]', $box),
                $cndTag
            );
        }

        $th = array(
            [Lang::gossip('optionId'),  '90px'],
            [Lang::gossip('option'),    'auto'],
            [Lang::gossip('action'),    'auto'],
            [Lang::gossip('box'),       'auto'],
            [Lang::gossip('condition'), 'auto']
        );

        $drop = [];
        if (!$hasAct)
            $drop[] = 2;
        if (!$hasBox)
            $drop[] = 3;
        if (!$hasCnd)
            $drop[] = 4;

        return $this->renderGrid($th, $rows, $drop);
    }

    private function renderGrid(array $th, array $rows, array $dropCols) : string
    {
        foreach ($dropCols as $i)
        {
            unset($th[$i]);
            foreach ($rows as &$r)
                unset($r[$i]);

            unset($r);
        }

        $tblId = Util::createHash(12);
        $this->gridCss .= "\n#tbl-".$tblId." { grid-template-columns: ".implode(' ', array_column($th, 1))."; }";

        $tbl = '[tr]' . array_reduce(array_column($th, 0), fn($out, $n) => $out .= '[td header]'.$n.'[/td]', '') . '[/tr]';
        foreach ($rows as $r)
            $tbl .= '[tr][td]'.implode('[/td][td]', $r).'[/td][/tr]';

        return '[table id=tbl-'.$tblId.' class=grid]'.$tbl.'[/table]';
    }

    private static function formatWeight(float $w) : string
    {
        return rtrim(rtrim(number_format($w, 2, '.', ''), '0'), '.');
    }

    public static function iconToCSS(int $icon) : string
    {
        return match ($icon)
        {
            GOSSIP_ICON_CHAT, 11, 12, 13,
            16, 17, 18, 19         => ' gossip-gossip',       // white chat bubble
            GOSSIP_ICON_VENDOR     => ' gossip-vendor',       // brown bag
            GOSSIP_ICON_TAXI       => ' gossip-taxi',         // flightmarker (paperplane)
            GOSSIP_ICON_TRAINER    => ' gossip-trainer',      // brown book
            GOSSIP_ICON_INTERACT_1 => ' gossip-healer',       // golden/red-ish interaction wheel
            GOSSIP_ICON_INTERACT_2 => ' gossip-binder',       // golden interaction wheel
            GOSSIP_ICON_MONEY_BAG  => ' gossip-banker',       // brown bag with gold coin
            GOSSIP_ICON_TALK       => ' gossip-petition',     // white chat bubble with "..."
            GOSSIP_ICON_TABARD     => ' gossip-tabard',       // white tabard
            GOSSIP_ICON_BATTLE     => ' gossip-battlemaster', // two crossed swords
            GOSSIP_ICON_DOT        => '',                     // yellow dot - 'auctioneer' @ 0x00ACEF48; icon not in client
            default                => ''                      // 14, 15 NULL in client
        };
    }

    public function getGridCss() : string
    {
        return $this->gridCss;
    }

    // raw markup body for this instance only; lets a caller combine several menus into a single Markup
    public function getMarkupBody(bool $collapsed = false) : ?string
    {
        if (!$this->textTbl && !$this->optionTbl)
            return null;

        $body = $this->textTbl . $this->optionTbl;

        // aowow - custom
        if ($this->friendly)
            $body = '[i][small class=q0]'.Lang::gossip('friendlyFaction', ['[faction='.$this->friendly.']']).'[/small][/i][br]'.$body;

        foreach ($this->subMenus as $sub)
            if ($_ = $sub->getMarkupBody(true))
                $body .= '[div class=gossip-sub]'.$_.'[/div]';

        $state = $collapsed ? '=hidden' : '';
        $head  = Lang::gossip('menu', [$this->menuId]).$this->title;
        $out   = '[pad][h3][toggler'.$state.' id='.$this->uid.']'.$head.'[/toggler][/h3][div'.$state.' id='.$this->uid.' clear=left]'.$body.'[/div]';

        if ($this->depth)                                   // my parent emits the [style] block for the whole tree
            return $out;

        return '[style]'.strtr(self::BASE_CSS.$this->gridCss, "\n", ' ').'[/style]'.$out;
    }

    /**
     * build one Markup for every menu an entity can open
     * several root menus are collapsed by default; a lone menu stays open
     *
     * @param  array   $menuIds    menus to render
     * @param  string  $uidPrefix  keeps the [toggler]/[div] ids unique across entities on one page
     * @param  array  &$jsGlobals  receives the globals referenced by the rendered conditions
     * @return ?Markup             null when none of the menus hold any data
     */
    public static function buildMarkupFor(array $menuIds, string $uidPrefix, ?array &$jsGlobals = []) : ?Markup
    {
        $found = [];
        foreach ($menuIds as $menuId)
        {
            $g = new Gossip($menuId, ['uid' => $uidPrefix.'-'.$menuId]);
            if ($g->prepare())
                $found[] = $g;
        }

        if (!$found)
            return null;

        $collapsed = count($found) > 1;
        $markup    = null;

        foreach ($found as $g)
        {
            Util::mergeJsGlobals($jsGlobals, $g->getJSGlobals());

            $body = $g->getMarkupBody($collapsed);
            if (!$markup)
                $markup = new Markup($body, ['allow' => Markup::CLASS_ADMIN], 'gossip-generic');
            else
                $markup->append($body);
        }

        return $markup;
    }

    /**
     * aowow - custom: `quest_greeting` was read nowhere
     *
     * the line an npc or object opens with when it holds more than one quest, which is neither a
     * gossip menu nor quest text and so appeared on no page at all
     *
     * @param  int $type   Type::NPC or Type::OBJECT
     * @param  int $entry  creature or gameobject entry
     */
    public static function buildGreetingFor(int $type, int $entry) : ?Markup
    {
        if ($entry <= 0 || !DB::World()->selectCell('SHOW TABLES LIKE %s', 'quest_greeting'))
            return null;

        // 0 is a creature, 1 a gameobject - the same numbering `quest_greeting` has always used
        $qgType = match ($type)
        {
            Type::NPC    => 0,
            Type::OBJECT => 1,
            default      => -1
        };

        if ($qgType < 0)
            return null;

        // the columns were renamed across TC revisions, so the row is read whole
        $r = DB::World()->selectRow('SELECT * FROM quest_greeting WHERE `ID` = %i AND `Type` = %i', $entry, $qgType);
        if ($r === null)
            $r = DB::World()->selectRow('SELECT * FROM quest_greeting WHERE `entry` = %i AND `type` = %i', $entry, $qgType);

        if (!$r)
            return null;

        $r    = array_change_key_case($r, CASE_LOWER);
        $text = trim((string)($r['greeting'] ?? $r['greeting_text'] ?? ''));
        if ($text === '')
            return null;

        $body = '[span class=gossip-text]'.UIText::format($text, Lang::FMT_MARKUP).'[/span]';

        if ($emote = (int)($r['greetemotetype'] ?? $r['emote'] ?? 0))
            $body .= '[br][i][small class=q0]'.Lang::gossip('emote', [$emote]).'[/small][/i]';

        return new Markup(
            '[style]'.strtr(self::BASE_CSS, "\n", ' ').'[/style]' .
            '[pad][h3][toggler id=quest-greeting]'.Lang::gossip('questGreeting').'[/toggler][/h3]' .
            '[div id=quest-greeting clear=left]'.$body.'[/div]',
            ['allow' => Markup::CLASS_ADMIN], 'gossip-generic'
        );
    }

    public function getMarkup(bool $collapsed = false) : ?Markup
    {
        if (($body = $this->getMarkupBody($collapsed)) === null)
            return null;

        return new Markup($body, ['allow' => Markup::CLASS_ADMIN], 'gossip-generic');
    }

    public function getJSGlobals() : array
    {
        return $this->jsGlobals;
    }

    public function getOptions() : array
    {
        return $this->options;
    }

    public function getTextIds() : array
    {
        return array_keys($this->texts);
    }
}

?>
