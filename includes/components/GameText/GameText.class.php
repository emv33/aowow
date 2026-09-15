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
 * All five tables are read from DB::World() live and checked before they are touched.
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
        if (!$query)
            return [];

        // the wildcards are ours, so a term containing one must not spend it
        $like = '%'.addcslashes($query, '%_\\').'%';
        $src  = intVal($opts['src'] ?? 0);

        $out = [];
        foreach (self::SOURCES as $srcId => $table)
        {
            if (($src && $src != $srcId) || !self::hasTable($table))
                continue;

            $out = array_merge($out, match ($srcId)
            {
                self::SRC_CREATURE_TEXT => self::creatureText($like),
                self::SRC_BROADCAST     => self::broadcastText($like),
                self::SRC_NPC_TEXT      => self::npcText($like),
                self::SRC_GOSSIP_OPTION => self::gossipOption($like),
                self::SRC_PAGE_TEXT     => self::pageText($like)
            });
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

    private static function row(int $src, string $id, int $entry, string $text, ?int $ownerType = null, int $ownerId = 0, string $ownerName = '') : array
    {
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
     * the voiced lines gossip, creature_text and the quest system all point at
     * the two genders are separate columns, spelled Text/Text1 on some revisions and
     * MaleText/FemaleText on others
     */
    private static function broadcastText(string $like) : array
    {
        $rows = DB::World()->selectAssoc('SELECT `ID`, `Text`, `Text1` FROM broadcast_text WHERE `Text` LIKE %s OR `Text1` LIKE %s ORDER BY `ID` ASC', $like, $like);
        if ($rows === null)
            $rows = DB::World()->selectAssoc('SELECT `ID`, `MaleText` AS "Text", `FemaleText` AS "Text1" FROM broadcast_text WHERE `MaleText` LIKE %s OR `FemaleText` LIKE %s ORDER BY `ID` ASC', $like, $like) ?: [];

        // a broadcast text is not owned by anything; the creature that speaks it is one join away
        $speakers = [];
        if ($rows && self::hasTable('creature_text'))
            $speakers = DB::World()->selectPairs('SELECT `BroadcastTextId`, `CreatureID` FROM creature_text WHERE `BroadcastTextId` IN %in', array_map(fn($x) => (int)$x['ID'], $rows)) ?: [];

        $out = [];
        foreach ($rows as $r)
        {
            $id  = (int)$r['ID'];
            $txt = (string)$r['Text'] ?: (string)$r['Text1'];
            if ((string)$r['Text1'] && (string)$r['Text'] && $r['Text'] != $r['Text1'])
                $txt = $r['Text'].' / '.$r['Text1'];

            $out[] = self::row(self::SRC_BROADCAST, 'bt:'.$id, $id, $txt, isset($speakers[$id]) ? Type::NPC : null, (int)($speakers[$id] ?? 0));
        }

        return $out;
    }

    /** gossip flavour text; eight variants per entry, each split by gender */
    private static function npcText(string $like) : array
    {
        $cols  = [];
        $where = [];
        for ($i = 0; $i < 8; $i++)
        {
            foreach ([0, 1] as $g)
            {
                $cols[]  = '`text'.$i.'_'.$g.'`';
                $where[] = ['`text'.$i.'_'.$g.'` LIKE %s', $like];
            }
        }

        $rows = DB::World()->selectAssoc('SELECT `ID`, '.implode(', ', $cols).' FROM npc_text WHERE %or ORDER BY `ID` ASC', $where) ?: [];

        // the menus this text is the flavour of - the only page an npc_text row can be reached from
        $menus = [];
        if ($rows && self::hasTable('gossip_menu'))
            $menus = DB::World()->selectPairs('SELECT `TextID`, MIN(`MenuID`) FROM gossip_menu WHERE `TextID` IN %in GROUP BY `TextID`', array_map(fn($x) => (int)$x['ID'], $rows)) ?: [];

        $out = [];
        foreach ($rows as $r)
        {
            $id = (int)$r['ID'];
            foreach ($r as $col => $txt)
                if ($col != 'ID' && $txt)
                    $out[] = self::row(self::SRC_NPC_TEXT, 'nt:'.$id.':'.$col, $id, (string)$txt,
                                       isset($menus[$id]) ? Type::GOSSIP : null, (int)($menus[$id] ?? 0));
        }

        return $out;
    }

    /** the clickable lines of a gossip window */
    private static function gossipOption(string $like) : array
    {
        $rows = DB::World()->selectAssoc(
           'SELECT `MenuID`, `OptionID`, `OptionText` FROM gossip_menu_option WHERE `OptionText` LIKE %s ORDER BY `MenuID`, `OptionID` ASC',
            $like
        ) ?: [];

        $out = [];
        foreach ($rows as $r)
            $out[] = self::row(self::SRC_GOSSIP_OPTION, 'go:'.$r['MenuID'].':'.$r['OptionID'],
                               (int)$r['MenuID'], (string)$r['OptionText'], Type::GOSSIP, (int)$r['MenuID']);

        return $out;
    }

    /** books, letters and plaques - the item or object holding the page is one join away */
    private static function pageText(string $like) : array
    {
        $rows = DB::World()->selectAssoc('SELECT `ID`, `Text` FROM page_text WHERE `Text` LIKE %s ORDER BY `ID` ASC', $like);
        if ($rows === null)
            $rows = DB::World()->selectAssoc('SELECT `entry` AS "ID", `text` AS "Text" FROM page_text WHERE `text` LIKE %s ORDER BY `entry` ASC', $like) ?: [];

        $ids     = array_map(fn($x) => (int)$x['ID'], $rows);
        $items   = $objects = [];
        if ($ids)
        {
            $items   = DB::Aowow()->selectPairs('SELECT `pageTextId`, MIN(`id`) FROM ::items WHERE `pageTextId` IN %in GROUP BY `pageTextId`', $ids) ?: [];
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
