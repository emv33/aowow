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
     * @param  array $opts  query: string, src: int (0 = every source)
     * @return array        list of [src, id, entry, text, ownerType, ownerId, ownerName]
     */
    public static function browse(array $opts = []) : array
    {
        $query = trim((string)($opts['query'] ?? ''));
        if ($query === '')
            return [];

        // the wildcards are ours, so a term containing one must not spend it
        $like = '%'.addcslashes($query, '%_\\').'%';

        $rows = array_merge(
            self::creatureText($like),
            self::broadcastText($like),
            self::npcText($query),
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
        $text = Lang::trimTextClean(UIText::format($text, Lang::FMT_RAW), 0);

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
    private static function creatureText(string $like) : array
    {
        if (!self::hasTable('creature_text'))
            return [];

        $rows = DB::World()->selectAssoc(
           'SELECT `CreatureID`, `GroupID`, `ID`, `Text` FROM creature_text WHERE `Text` LIKE %s ORDER BY `CreatureID`, `GroupID`, `ID` ASC',
            $like
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
    private static function broadcastText(string $like) : array
    {
        if (!self::hasTable('broadcast_text'))
            return [];

        $rows = DB::World()->selectAssoc('SELECT `ID`, `Text`, `Text1` FROM broadcast_text WHERE `Text` LIKE %s OR `Text1` LIKE %s ORDER BY `ID` ASC', $like, $like);
        if ($rows === null)
            $rows = DB::World()->selectAssoc('SELECT `ID`, `MaleText` AS "Text", `FemaleText` AS "Text1" FROM broadcast_text WHERE `MaleText` LIKE %s OR `FemaleText` LIKE %s ORDER BY `ID` ASC', $like, $like) ?: [];

        if (!$rows)
            return [];

        $refs = self::broadcastReferrers(array_map(fn($x) => (int)$x['ID'], $rows));

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
    private static function broadcastReferrers(array $ids) : array
    {
        $out = [];
        if (!$ids)
            return $out;

        // a common word matches thousands of lines, so membership is a lookup rather than a scan
        $wanted = array_flip($ids);

        if (self::hasTable('creature_text'))
            foreach (DB::World()->selectAssoc('SELECT `BroadcastTextId`, `CreatureID` FROM creature_text WHERE `BroadcastTextId` IN %in', $ids) ?: [] as $r)
                $out[(int)$r['BroadcastTextId']][] = [self::SRC_CREATURE_TEXT, Type::NPC, (int)$r['CreatureID']];

        if (self::hasTable('npc_text'))
        {
            $where = [];
            for ($i = 0; $i < GOSSIP_TEXT_SLOT_COUNT; $i++)
                $where[] = ['`BroadcastTextID'.$i.'` IN %in', $ids];

            // read whole: the slot columns are absent on revisions old enough to predate them
            $rows  = DB::World()->selectAssoc('SELECT * FROM npc_text WHERE %or', $where) ?: [];
            $menus = self::menusForText(array_map(fn($x) => (int)$x['ID'], $rows));

            foreach ($rows as $r)
            {
                $lc = array_change_key_case($r, CASE_LOWER);
                for ($i = 0; $i < GOSSIP_TEXT_SLOT_COUNT; $i++)
                {
                    $bct = (int)($lc['broadcasttextid'.$i] ?? 0);
                    if (!$bct || !isset($wanted[$bct]))
                        continue;

                    foreach ($menus[(int)$lc['id']] ?? [0] as $menuId)
                        $out[$bct][] = [self::SRC_NPC_TEXT, $menuId ? Type::GOSSIP : null, $menuId];
                }
            }
        }

        if (self::hasTable('gossip_menu_option'))
        {
            $rows = DB::World()->selectAssoc(
               'SELECT `MenuID`, `OptionBroadcastTextID`, `BoxBroadcastTextID` FROM gossip_menu_option WHERE `OptionBroadcastTextID` IN %in OR `BoxBroadcastTextID` IN %in',
                $ids, $ids
            ) ?: [];

            foreach ($rows as $r)
                foreach (['OptionBroadcastTextID', 'BoxBroadcastTextID'] as $col)
                    if (($bct = (int)$r[$col]) && isset($wanted[$bct]))
                        $out[$bct][] = [self::SRC_GOSSIP_OPTION, Type::GOSSIP, (int)$r['MenuID']];
        }

        return $out;
    }

    /** the menus an npc_text is the flavour of - the only page such a row can be reached from */
    private static function menusForText(array $textIds) : array
    {
        if (!$textIds || !self::hasTable('gossip_menu'))
            return [];

        $out = [];
        foreach (DB::World()->selectAssoc('SELECT `MenuID`, `TextID` FROM gossip_menu WHERE `TextID` IN %in', $textIds) ?: [] as $r)
            $out[(int)$r['TextID']][] = (int)$r['MenuID'];

        return $out;
    }

    /**
     * gossip flavour text; eight variants per entry, each split by gender
     *
     * a slot that names a broadcast text is skipped: the core reads the broadcast text and never
     * these columns, so a hit here would report a string the game does not show
     */
    private static function npcText(string $query) : array
    {
        if (!self::hasTable('npc_text'))
            return [];

        $where = [];
        for ($i = 0; $i < GOSSIP_TEXT_SLOT_COUNT; $i++)
            foreach ([0, 1] as $g)
                $where[] = ['`text'.$i.'_'.$g.'` LIKE %s', '%'.addcslashes($query, '%_\\').'%'];

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
                    $txt = (string)($lc['text'.$i.'_'.$g] ?? '');
                    if ($txt === '' || mb_stripos($txt, $query) === false)
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
    private static function gossipOption(string $like) : array
    {
        if (!self::hasTable('gossip_menu_option'))
            return [];

        $rows = DB::World()->selectAssoc(
           'SELECT `MenuID`, `OptionID`, `OptionText`, `BoxText` FROM gossip_menu_option WHERE `OptionText` LIKE %s OR `BoxText` LIKE %s ORDER BY `MenuID`, `OptionID` ASC',
            $like, $like
        );

        if ($rows === null)
            $rows = DB::World()->selectAssoc(
               'SELECT `MenuID`, `OptionID`, `OptionText`, "" AS "BoxText" FROM gossip_menu_option WHERE `OptionText` LIKE %s ORDER BY `MenuID`, `OptionID` ASC',
                $like
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
    private static function pageText(string $like) : array
    {
        if (!self::hasTable('page_text'))
            return [];

        $rows = DB::World()->selectAssoc('SELECT `ID`, `Text` FROM page_text WHERE `Text` LIKE %s ORDER BY `ID` ASC', $like);
        if ($rows === null)
            $rows = DB::World()->selectAssoc('SELECT `entry` AS "ID", `text` AS "Text" FROM page_text WHERE `text` LIKE %s ORDER BY `entry` ASC', $like) ?: [];

        $ids     = array_map(fn($x) => (int)$x['ID'], $rows);
        $items   = $objects = [];
        if ($ids)
        {
            $items = DB::Aowow()->selectPairs('SELECT `pageTextId`, MIN(`id`) FROM ::items WHERE `pageTextId` IN %in GROUP BY `pageTextId`', $ids) ?: [];
            // a page can hang off a sign or a plaque rather than an item
            if ($rest = array_diff($ids, array_keys($items)))
                $objects = DB::Aowow()->selectPairs('SELECT `pageTextId`, MIN(`id`) FROM ::objects WHERE `pageTextId` IN %in GROUP BY `pageTextId`', $rest) ?: [];
        }

        $out = [];
        foreach ($rows as $r)
        {
            $id = (int)$r['ID'];
            if (isset($items[$id]))
                $out[] = self::row(self::SRC_PAGE_TEXT, 'pt:'.$id, $id, (string)$r['Text'], Type::ITEM, (int)$items[$id]);
            else if (isset($objects[$id]))
                $out[] = self::row(self::SRC_PAGE_TEXT, 'pt:'.$id, $id, (string)$r['Text'], Type::OBJECT, (int)$objects[$id]);
            else
                $out[] = self::row(self::SRC_PAGE_TEXT, 'pt:'.$id, $id, (string)$r['Text']);
        }

        return $out;
    }
}

?>
