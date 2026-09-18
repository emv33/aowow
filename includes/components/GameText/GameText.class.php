<?php

namespace Aowow;

if (!defined('AOWOW_REVISION'))
    die('illegal access');


/*
 * Search over the text the game itself speaks.
 *
 * Every one of the 28 search modules matches a name. What an NPC says, what a gossip window offers
 * and what a book page holds is rendered on the pages that own it and searchable nowhere, so
 * "who says 'You are not prepared'" had no answer.
 *
 * `broadcast_text` is not a source in its own right so much as the storage the other tables point
 * at: the core prefers it over `creature_text`.`Text` and over the `npc_text` columns whenever a
 * row names one. A hit there is therefore attributed to whatever references it, and only a line
 * nothing points at is reported as a broadcast text.
 *
 * All tables are read from DB::World() live and checked before they are touched.
 */
class GameText
{
    public const int SRC_CREATURE_TEXT = 1;
    public const int SRC_BROADCAST     = 2;
    public const int SRC_NPC_TEXT      = 3;
    public const int SRC_GOSSIP_OPTION = 4;
    public const int SRC_PAGE_TEXT     = 5;

    public const array SOURCES = array(
        self::SRC_CREATURE_TEXT => 'creature_text',
        self::SRC_BROADCAST     => 'broadcast_text',
        self::SRC_NPC_TEXT      => 'npc_text',
        self::SRC_GOSSIP_OPTION => 'gossip_menu_option',
        self::SRC_PAGE_TEXT     => 'page_text'
    );

    private static function hasTable(string $tbl) : bool
    {
        static $known = [];

        return $known[$tbl] ??= (bool)DB::World()->selectCell('SHOW TABLES LIKE %s', $tbl);
    }

    /**
     * @param  array $opts  query: string (empty lists every line), src: int (0 = every source)
     * @return array        list of [src, id, entry, text, ownerType, ownerId, ownerName]
     */
    public static function browse(array $opts = []) : array
    {
        $query = trim((string)($opts['query'] ?? ''));

        // no term lists the whole of it, the way the other world DB browsers do; the wildcards in
        // a term that has some are ours, so they must not be spent on it
        $like = $query === '' ? null : '%'.addcslashes($query, '%_\\').'%';

        $rows = array_merge(
            self::creatureText($like),
            self::broadcastText($like),
            self::npcText($query, $like),
            self::gossipOption($like),
            self::pageText($like)
        );

        // the same line is stored in two tables as often as not - once as itself and once as the
        // broadcast text the core actually reads - and both carry the same source and owner here
        $src  = intVal($opts['src'] ?? 0);
        $seen = [];
        $out  = [];
        foreach ($rows as $r)
        {
            $key = $r['src'].':'.$r['ownerType'].':'.$r['ownerId'].':'.$r['text'];
            if (isset($seen[$key]))
                continue;

            $seen[$key] = true;

            // filtered last: a broadcast text re-attributed to a gossip menu must answer to the
            // gossip filter rather than to the one naming the table it happens to live in
            if (!$src || $src == $r['src'])
                $out[] = $r;
        }

        return $out;
    }

    /** an `IN` over the given ids, or a condition that holds for every row when there are none */
    private static function inSet(string $column, ?array $ids) : array
    {
        return $ids === null ? ['1 = 1'] : [$column.' IN %in', $ids];
    }

    /**
     * game text is written for the client: |c colour codes, $B for a line break, $N for the
     * player's name - all of which UIText::format() resolves, as the mail page already does
     *
     * the guard matters beyond legibility. Util::toJSON() emits any string that begins with a $
     * as raw JavaScript - that is how the site passes expressions like $LANG.tab_npcs into
     * listview data - so a line opening with a text variable would be spliced into the page as
     * code and take the whole script with it. format() resolves the variables it knows; a $ that
     * survives it is one it does not, and a leading space keeps the value a string either way.
     */
    private static function excerpt(string $text) : string
    {
        // UIText::format() only turns the html subset page_text carries into bbcode under
        // Lang::FMT_MARKUP - under FMT_RAW a recognized tag (its <HTML>/<BODY>/<BR>, or any other
        // tag UIText considers valid) survives untouched, and this excerpt lands in the listing as
        // a plain text node, so anything still tag-shaped after formatting is stripped here first
        $text = strip_tags(UIText::format($text, Lang::FMT_RAW));
        $text = Lang::trimTextClean($text, 0);

        return $text !== '' && $text[0] == '$' ? ' '.$text : $text;
    }

    private static function row(int $src, string $id, int $entry, string $text, ?int $ownerType = null, int $ownerId = 0) : array
    {
        // a gossip menu has no name to look up, and a line nothing references has no page at all;
        // both would otherwise print as a bare number
        $ownerName = '';
        if ($ownerType == Type::GOSSIP)
            $ownerName = Lang::gossip('menu', [$ownerId]);
        else if (!$ownerType)
            $ownerName = Lang::gameText('sources', $src).' #'.$entry;

        return array(
            'src'       => $src,
            'id'        => $id,                             // listviews need a unique key; none of these tables has a single column one
            'entry'     => $entry,
            'text'      => self::excerpt($text),
            'ownerType' => $ownerType,
            'ownerId'   => $ownerId,
            'ownerName' => $ownerName
        );
    }

    /** what a creature says, yells or whispers - the speaker is the row's own key */
    private static function creatureText(?string $like) : array
    {
        if (!self::hasTable('creature_text'))
            return [];

        $where = $like === null ? [['`Text` <> %s', '']] : [['`Text` LIKE %s', $like]];

        $rows = DB::World()->selectAssoc(
           'SELECT `CreatureID`, `GroupID`, `ID`, `Text` FROM creature_text WHERE %and ORDER BY `CreatureID`, `GroupID`, `ID` ASC',
            $where
        ) ?: [];

        $out = [];
        foreach ($rows as $r)
            $out[] = self::row(self::SRC_CREATURE_TEXT, 'ct:'.$r['CreatureID'].':'.$r['GroupID'].':'.$r['ID'],
                               (int)$r['CreatureID'], (string)$r['Text'], Type::NPC, (int)$r['CreatureID']);

        return $out;
    }

    /**
     * the voiced lines the other tables point at, both genders
     *
     * spelled Text/Text1 on some revisions and MaleText/FemaleText on others
     */
    private static function broadcastText(?string $like) : array
    {
        if (!self::hasTable('broadcast_text'))
            return [];

        foreach ([['Text', 'Text1'], ['MaleText', 'FemaleText']] as [$male, $female])
        {
            $where = $like === null ? [[DB::OR, [['`'.$male.'` <> %s', ''], ['`'.$female.'` <> %s', '']]]]
                                    : [[DB::OR, [['`'.$male.'` LIKE %s', $like], ['`'.$female.'` LIKE %s', $like]]]];

            $rows = DB::World()->selectAssoc('SELECT `ID`, `'.$male.'` AS "Text", `'.$female.'` AS "Text1" FROM broadcast_text WHERE %and ORDER BY `ID` ASC', $where);
            if ($rows !== null)
                break;
        }

        if (!$rows)
            return [];

        // an unfiltered listing takes every referrer rather than naming fifty thousand ids
        $refs = self::broadcastReferrers($like === null ? null : array_map(fn($x) => (int)$x['ID'], $rows));

        $out = [];
        foreach ($rows as $r)
        {
            $id  = (int)$r['ID'];
            $txt = (string)$r['Text'] ?: (string)$r['Text1'];
            if ((string)$r['Text1'] && (string)$r['Text'] && $r['Text'] != $r['Text1'])
                $txt = $r['Text'].' / '.$r['Text1'];

            // reported as whatever speaks it; the table it lives in is an implementation detail
            if (!isset($refs[$id]))
            {
                $out[] = self::row(self::SRC_BROADCAST, 'bt:'.$id, $id, $txt);
                continue;
            }

            foreach ($refs[$id] as [$src, $ownerType, $ownerId])
                $out[] = self::row($src, 'bt:'.$id.':'.$src.':'.$ownerId, $id, $txt, $ownerType, $ownerId);
        }

        return $out;
    }

    /**
     * the three tables that resolve a line through `broadcast_text`, read backwards
     *
     * @return array  broadcastTextId => [[srcType, ownerType, ownerId], ...]
     */
    private static function broadcastReferrers(?array $ids) : array
    {
        $out = [];
        if ($ids === [])
            return $out;

        // a common word matches thousands of lines, so membership is a lookup rather than a scan
        $wanted = $ids === null ? null : array_flip($ids);
        $keep   = fn(int $bct) => $bct && ($wanted === null || isset($wanted[$bct]));

        if (self::hasTable('creature_text'))
            foreach (DB::World()->selectAssoc('SELECT `BroadcastTextId`, `CreatureID` FROM creature_text WHERE `BroadcastTextId` > 0 AND %and', [self::inSet('`BroadcastTextId`', $ids)]) ?: [] as $r)
                $out[(int)$r['BroadcastTextId']][] = [self::SRC_CREATURE_TEXT, Type::NPC, (int)$r['CreatureID']];

        if (self::hasTable('npc_text'))
        {
            $where = [];
            for ($i = 0; $i < GOSSIP_TEXT_SLOT_COUNT; $i++)
                $where[] = $ids === null ? ['`BroadcastTextID'.$i.'` > 0'] : ['`BroadcastTextID'.$i.'` IN %in', $ids];

            // read whole: the slot columns are absent on revisions old enough to predate them
            $rows  = DB::World()->selectAssoc('SELECT * FROM npc_text WHERE %or', $where) ?: [];
            $menus = self::menusForText(array_map(fn($x) => (int)$x['ID'], $rows));

            foreach ($rows as $r)
            {
                $lc = array_change_key_case($r, CASE_LOWER);
                for ($i = 0; $i < GOSSIP_TEXT_SLOT_COUNT; $i++)
                {
                    $bct = (int)($lc['broadcasttextid'.$i] ?? 0);
                    if (!$keep($bct))
                        continue;

                    foreach ($menus[(int)$lc['id']] ?? [0] as $menuId)
                        $out[$bct][] = [self::SRC_NPC_TEXT, $menuId ? Type::GOSSIP : null, $menuId];
                }
            }
        }

        if (self::hasTable('gossip_menu_option'))
        {
            $where = $ids === null
                   ? [[DB::OR, [['`OptionBroadcastTextID` > 0'], ['`BoxBroadcastTextID` > 0']]]]
                   : [[DB::OR, [['`OptionBroadcastTextID` IN %in', $ids], ['`BoxBroadcastTextID` IN %in', $ids]]]];

            $rows = DB::World()->selectAssoc('SELECT `MenuID`, `OptionBroadcastTextID`, `BoxBroadcastTextID` FROM gossip_menu_option WHERE %and', $where) ?: [];

            foreach ($rows as $r)
                foreach (['OptionBroadcastTextID', 'BoxBroadcastTextID'] as $col)
                    if ($keep($bct = (int)$r[$col]))
                        $out[$bct][] = [self::SRC_GOSSIP_OPTION, Type::GOSSIP, (int)$r['MenuID']];
        }

        return $out;
    }

    /**
     * the menus an npc_text is the flavour of - the only page such a row can be reached from
     *
     * `gossip_menu` wires most of them, but a menu can also be sent by a script instead of being
     * written down, and a text nothing sends at all has no page to link to
     */
    private static function menusForText(array $textIds) : array
    {
        if (!$textIds)
            return [];

        $out = [];

        if (self::hasTable('gossip_menu'))
            foreach (DB::World()->selectAssoc('SELECT `MenuID`, `TextID` FROM gossip_menu WHERE `TextID` IN %in', $textIds) ?: [] as $r)
                $out[(int)$r['TextID']][] = (int)$r['MenuID'];

        // SMART_ACTION_SEND_GOSSIP_MENU names the menu in param1 and the text in param2
        if (($rest = array_diff($textIds, array_keys($out))) && self::hasTable('smart_scripts'))
        {
            $sent = DB::World()->selectAssoc(
               'SELECT `action_param1`, `action_param2` FROM smart_scripts WHERE `action_type` = %i AND `action_param1` > 0 AND `action_param2` IN %in',
                SmartAction::ACTION_SEND_GOSSIP_MENU, array_values($rest)
            ) ?: [];

            // a script may name a menu that was never written down; linking there is a dead page
            if ($sent && ($real = self::existingMenus(array_map(fn($x) => (int)$x['action_param1'], $sent))))
                foreach ($sent as $r)
                    if (isset($real[(int)$r['action_param1']]))
                        $out[(int)$r['action_param2']][] = (int)$r['action_param1'];
        }

        foreach ($out as &$menuIds)
            $menuIds = array_values(array_unique($menuIds));

        return $out;
    }

    /** menu ids that have a page: the Gossip component renders a menu with texts or with options */
    private static function existingMenus(array $menuIds) : array
    {
        $out = [];
        foreach ([['gossip_menu', 'MenuID'], ['gossip_menu_option', 'MenuID']] as [$table, $col])
        {
            if (!$menuIds || !self::hasTable($table))
                continue;

            foreach (DB::World()->selectCol('SELECT DISTINCT `'.$col.'` FROM %n WHERE `'.$col.'` IN %in', $table, array_values(array_unique($menuIds))) ?: [] as $id)
                $out[(int)$id] = (int)$id;
        }

        return $out;
    }

    /**
     * gossip flavour text; eight variants per entry, each split by gender
     *
     * a slot that names a broadcast text is skipped: the core reads the broadcast text and never
     * these columns, so a hit here would report a string the game does not show
     */
    private static function npcText(string $query, ?string $like) : array
    {
        if (!self::hasTable('npc_text'))
            return [];

        $where = [];
        for ($i = 0; $i < GOSSIP_TEXT_SLOT_COUNT; $i++)
            foreach ([0, 1] as $g)
                $where[] = $like === null ? ['`text'.$i.'_'.$g.'` <> %s', ''] : ['`text'.$i.'_'.$g.'` LIKE %s', $like];

        $rows  = DB::World()->selectAssoc('SELECT * FROM npc_text WHERE %or ORDER BY `ID` ASC', $where) ?: [];
        $menus = self::menusForText(array_map(fn($x) => (int)$x['ID'], $rows));

        $out = [];
        foreach ($rows as $r)
        {
            $lc = array_change_key_case($r, CASE_LOWER);
            $id = (int)$lc['id'];

            for ($i = 0; $i < GOSSIP_TEXT_SLOT_COUNT; $i++)
            {
                if ((int)($lc['broadcasttextid'.$i] ?? 0))
                    continue;

                foreach ([0, 1] as $g)
                {
                    // the row matched on some column; which of its sixteen is decided here
                    $txt = (string)($lc['text'.$i.'_'.$g] ?? '');
                    if ($txt === '' || ($query !== '' && mb_stripos($txt, $query) === false))
                        continue;

                    foreach ($menus[$id] ?? [0] as $menuId)
                        $out[] = self::row(self::SRC_NPC_TEXT, 'nt:'.$id.':'.$i.':'.$g.':'.$menuId, $id, $txt,
                                           $menuId ? Type::GOSSIP : null, $menuId);
                }
            }
        }

        return $out;
    }

    /** the clickable lines of a gossip window, and the confirmation box behind them */
    private static function gossipOption(?string $like) : array
    {
        if (!self::hasTable('gossip_menu_option'))
            return [];

        $cond  = fn(string $col) => $like === null ? ['`'.$col.'` <> %s', ''] : ['`'.$col.'` LIKE %s', $like];
        $where = [[DB::OR, [$cond('OptionText'), $cond('BoxText')]]];

        $rows = DB::World()->selectAssoc(
           'SELECT `MenuID`, `OptionID`, `OptionText`, `BoxText` FROM gossip_menu_option WHERE %and ORDER BY `MenuID`, `OptionID` ASC',
            $where
        );

        if ($rows === null)
            $rows = DB::World()->selectAssoc(
               'SELECT `MenuID`, `OptionID`, `OptionText`, "" AS "BoxText" FROM gossip_menu_option WHERE %and ORDER BY `MenuID`, `OptionID` ASC',
                [$cond('OptionText')]
            ) ?: [];

        $out = [];
        foreach ($rows as $r)
            foreach (['OptionText', 'BoxText'] as $col)
                if ($txt = (string)$r[$col])
                    $out[] = self::row(self::SRC_GOSSIP_OPTION, 'go:'.$r['MenuID'].':'.$r['OptionID'].':'.$col,
                                       (int)$r['MenuID'], $txt, Type::GOSSIP, (int)$r['MenuID']);

        return $out;
    }

    /** books, letters and plaques - the item or object holding the page is one join away */
    private static function pageText(?string $like) : array
    {
        if (!self::hasTable('page_text'))
            return [];

        foreach ([['ID', 'Text'], ['entry', 'text']] as [$idCol, $txtCol])
        {
            $where = $like === null ? [['`'.$txtCol.'` <> %s', '']] : [['`'.$txtCol.'` LIKE %s', $like]];

            $rows = DB::World()->selectAssoc('SELECT `'.$idCol.'` AS "ID", `'.$txtCol.'` AS "Text" FROM page_text WHERE %and ORDER BY `'.$idCol.'` ASC', $where);
            if ($rows !== null)
                break;
        }

        $rows ??= [];

        $owners = self::pageOwners(array_map(fn($x) => (int)$x['ID'], $rows));

        $out = [];
        foreach ($rows as $r)
        {
            $id = (int)$r['ID'];
            [$type, $entry] = $owners[$id] ?? [null, 0];

            $out[] = self::row(self::SRC_PAGE_TEXT, 'pt:'.$id, $id, (string)$r['Text'], $type, $entry);
        }

        return $out;
    }

    /**
     * what holds each page
     *
     * a book is a chain: the item or object names its first page only, and every page after it is
     * reached through NextPageID. Looking no further than the direct owner left every page but the
     * first of every multi-page book unlinked, which is most of the table.
     *
     * @return array  pageId => [Type, entryId]
     */
    private static function pageOwners(array $ids) : array
    {
        if (!$ids)
            return [];

        $out     = self::directPageOwners($ids);
        $pending = array_values(array_diff($ids, array_keys($out)));
        if (!$pending)
            return $out;

        // the column pair is spelled ID/NextPageID on current revisions and entry/next_page on older
        $cols = [['ID', 'NextPageID'], ['entry', 'next_page']];

        $parent   = [];                                     // page => the page that precedes it
        $seen     = array_flip($ids);                       // a corrupt chain can loop
        $frontier = $pending;

        while ($frontier)
        {
            $rows = null;
            foreach ($cols as [$idCol, $nextCol])
                if (($rows = DB::World()->selectAssoc('SELECT `'.$idCol.'` AS "id", `'.$nextCol.'` AS "next" FROM page_text WHERE `'.$nextCol.'` IN %in', $frontier)) !== null)
                    break;

            $next = [];
            foreach ($rows ?: [] as $r)
            {
                $p = (int)$r['id'];
                $c = (int)$r['next'];
                if (isset($parent[$c]))                     // keep the first predecessor found
                    continue;

                $parent[$c] = $p;
                if (!isset($seen[$p]))
                {
                    $seen[$p] = true;
                    $next[]   = $p;
                }
            }

            if ($next)
                $out += self::directPageOwners($next);

            $frontier = $next;
        }

        // every page inherits the owner of the first page above it that has one
        // the walk keeps its own path: two pages pointing at each other is a chain with no head,
        // and following it is an endless loop rather than a missing link
        foreach ($pending as $id)
        {
            $cur  = $id;
            $path = [$id => true];
            while (isset($parent[$cur]) && !isset($path[$parent[$cur]]))
            {
                $cur        = $parent[$cur];
                $path[$cur] = true;

                if (isset($out[$cur]))
                {
                    $out[$id] = $out[$cur];
                    break;
                }
            }
        }

        return $out;
    }

    /** the item or object that names these pages directly */
    private static function directPageOwners(array $ids) : array
    {
        $out = [];
        if (!$ids)
            return $out;

        foreach (DB::Aowow()->selectPairs('SELECT `pageTextId`, MIN(`id`) FROM ::items WHERE `pageTextId` IN %in GROUP BY `pageTextId`', $ids) ?: [] as $page => $entry)
            $out[(int)$page] = [Type::ITEM, (int)$entry];

        // a page can hang off a sign or a plaque rather than an item
        if ($rest = array_diff($ids, array_keys($out)))
            foreach (DB::Aowow()->selectPairs('SELECT `pageTextId`, MIN(`id`) FROM ::objects WHERE `pageTextId` IN %in GROUP BY `pageTextId`', array_values($rest)) ?: [] as $page => $entry)
                $out[(int)$page] = [Type::OBJECT, (int)$entry];

        return $out;
    }

    /**
     * one row of browse(), fetched directly by its composite id rather than by scanning every
     * source table - the ?text=<id> detail page's only query.
     *
     * @param  string $id  the composite key browse() hands out: 'ct:creature:group:row',
     *                     'bt:id[:src:ownerId]', 'nt:id:slot:gender:menu', 'go:menu:option:col',
     *                     'pt:id'
     */
    public static function getOne(string $id) : ?array
    {
        $pos = strpos($id, ':');
        if ($pos === false)
            return null;

        $kind = substr($id, 0, $pos);
        $rest = explode(':', substr($id, $pos + 1));

        return match ($kind)
        {
            'ct'    => self::oneCreatureText($id, $rest),
            'bt'    => self::oneBroadcastText($id, $rest),
            'nt'    => self::oneNpcText($id, $rest),
            'go'    => self::oneGossipOption($id, $rest),
            'pt'    => self::onePageText($id, $rest),
            default => null
        };
    }

    private static function oneCreatureText(string $id, array $p) : ?array
    {
        if (count($p) != 3 || !self::hasTable('creature_text'))
            return null;

        [$creatureId, $groupId, $rowId] = array_map('intval', $p);

        $txt = DB::World()->selectCell(
           'SELECT `Text` FROM creature_text WHERE `CreatureID` = %i AND `GroupID` = %i AND `ID` = %i',
            $creatureId, $groupId, $rowId
        );

        if ($txt === null)
            return null;

        return self::row(self::SRC_CREATURE_TEXT, $id, $creatureId, (string)$txt, Type::NPC, $creatureId);
    }

    private static function oneBroadcastText(string $id, array $p) : ?array
    {
        if (!$p || !self::hasTable('broadcast_text'))
            return null;

        $btId = (int)$p[0];
        if (!$btId)
            return null;

        // spelled Text/Text1 on some revisions and MaleText/FemaleText on others, as browse() tries
        $row = null;
        foreach ([['Text', 'Text1'], ['MaleText', 'FemaleText']] as [$male, $female])
        {
            $row = DB::World()->selectRow('SELECT `'.$male.'` AS "Text", `'.$female.'` AS "Text1" FROM broadcast_text WHERE `ID` = %i', $btId);
            if ($row !== null)
                break;
        }

        if (!$row)
            return null;

        $txt = (string)$row['Text'] ?: (string)$row['Text1'];
        if ((string)$row['Text1'] && (string)$row['Text'] && $row['Text'] != $row['Text1'])
            $txt = $row['Text'].' / '.$row['Text1'];

        // a specific referrer was encoded into the id; reconstruct its owner rather than re-walking
        // every referrer table for the one that matches
        if (count($p) >= 3)
        {
            $encSrc    = (int)$p[1];
            $ownerId   = (int)$p[2];
            $ownerType = match ($encSrc)
            {
                self::SRC_CREATURE_TEXT => Type::NPC,
                self::SRC_NPC_TEXT      => ($ownerId ? Type::GOSSIP : null),
                self::SRC_GOSSIP_OPTION => Type::GOSSIP,
                default                 => null
            };

            return self::row($encSrc, $id, $btId, $txt, $ownerType, $ownerId);
        }

        return self::row(self::SRC_BROADCAST, $id, $btId, $txt);
    }

    private static function oneNpcText(string $id, array $p) : ?array
    {
        if (count($p) != 4 || !self::hasTable('npc_text'))
            return null;

        $ntId   = (int)$p[0];
        $slot   = (int)$p[1];
        $gender = (int)$p[2];
        $menuId = (int)$p[3];

        // $slot/$gender pick the column name and are attacker-controlled (url param), so they are
        // bound-checked before ever touching the query text rather than passed through as a value
        if ($slot < 0 || $slot >= GOSSIP_TEXT_SLOT_COUNT || ($gender != 0 && $gender != 1))
            return null;

        $txt = DB::World()->selectCell('SELECT `text'.$slot.'_'.$gender.'` FROM npc_text WHERE `ID` = %i', $ntId);
        if ($txt === null || (string)$txt === '')
            return null;

        return self::row(self::SRC_NPC_TEXT, $id, $ntId, (string)$txt, $menuId ? Type::GOSSIP : null, $menuId);
    }

    private static function oneGossipOption(string $id, array $p) : ?array
    {
        if (count($p) != 3 || !self::hasTable('gossip_menu_option'))
            return null;

        $menuId = (int)$p[0];
        $optId  = (int)$p[1];
        $col    = $p[2];

        // $col is attacker-controlled (url param) and lands in the query text, not a value - a
        // strict whitelist keeps it to the two real columns rather than any world DB column at all
        if ($col !== 'OptionText' && $col !== 'BoxText')
            return null;

        $txt = DB::World()->selectCell('SELECT `'.$col.'` FROM gossip_menu_option WHERE `MenuID` = %i AND `OptionID` = %i', $menuId, $optId);
        if ($txt === null || (string)$txt === '')
            return null;

        return self::row(self::SRC_GOSSIP_OPTION, $id, $menuId, (string)$txt, Type::GOSSIP, $menuId);
    }

    private static function onePageText(string $id, array $p) : ?array
    {
        if (!$p || !self::hasTable('page_text'))
            return null;

        $ptId = (int)$p[0];
        if (!$ptId)
            return null;

        // the locale table too, same as Game::getBook() - a book page rendered here should read
        // exactly like the one on the item/object page that owns it
        $row = null;
        foreach ([['ID', 'Text'], ['entry', 'text']] as [$idCol, $txtCol])
        {
            $row = DB::World()->selectRow(
               'SELECT pt.`'.$txtCol.'` AS "Text", ptl.`Text` AS "Text_loc'.Lang::getLocale()->value.'"
                FROM   page_text pt LEFT JOIN page_text_locale ptl ON pt.`'.$idCol.'` = ptl.`ID` AND ptl.`locale` = %s
                WHERE  pt.`'.$idCol.'` = %i',
                Lang::getLocale()->json(), $ptId
            );
            if ($row !== null)
                break;
        }

        if (!$row)
            return null;

        $raw = Util::localizedString($row, 'Text');

        [$type, $entry] = self::pageOwners([$ptId])[$ptId] ?? [null, 0];

        $out = self::row(self::SRC_PAGE_TEXT, $id, $ptId, $raw, $type, $entry);

        // the excerpt() plain-text version above is fine for the infobox line, but the body needs
        // the untouched original - it goes through UIText::format(..., Lang::FMT_HTML) instead,
        // the same pipeline Book already uses, rather than the FMT_RAW one browse() excerpts with
        $out['raw'] = $raw;

        return $out;
    }
}

?>
