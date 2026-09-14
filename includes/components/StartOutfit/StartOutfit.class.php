<?php

namespace Aowow;

if (!defined('AOWOW_REVISION'))
    die('illegal access');


/*
 * What a character starts the game with.
 *
 * Three sources, none of which was read anywhere:
 *   CharStartOutfit.dbc          - the outfit the client equips; the actual starting gear
 *   `playercreateinfo_item`      - extra items the server hands out on top of the outfit
 *   `playercreateinfo_spell_custom` - the spells a new character knows
 * plus `playercreateinfo` for the starting location.
 *
 * The world tables are read live. The DBC is written into the aowow DB by the `source` setup step
 * and is used when `dbc_charstartoutfit` is there, so nothing needs re-importing - but a setup run
 * with --delete leaves the table TEMPORARY, hence the guard.
 */
class StartOutfit
{
    public const int BY_CLASS = 1;                          // list every race of one class
    public const int BY_RACE  = 2;                          // list every class of one race

    // SkillLineAbility.dbc acquireMethod: 2 is "granted when the skill line is learned", which for a
    // class or race skill line means at character creation
    private const int ACQUIRE_ON_SKILL_LEARN = 2;

    // SkillLine.dbc categoryId 7 is the class skill lines - the spellbook's spec tabs. Weapon, armor,
    // language and racial lines sit in other categories and belong under General, as they do in game.
    private const int SKILL_CATEGORY_CLASS   = 7;

    // playercreateinfo_action.type - ACTION_BUTTON_SPELL / ACTION_BUTTON_ITEM
    private const int ACTION_TYPE_SPELL      = 0;
    private const int ACTION_TYPE_ITEM       = 128;

    private const string BASE_CSS = <<<CSS
        #start-outfit-generic .grid { clear:left; display: grid; }
        #start-outfit-generic .grid thead,
        #start-outfit-generic .grid tbody,
        #start-outfit-generic .grid tr { display: contents; }
        #start-outfit-generic .so-tabs { display: grid; grid-template-columns: max-content auto; border: 1px solid #404040; }
        #start-outfit-generic .so-tabs > div { padding: 4px 8px; border-top: 1px solid #404040; }
        #start-outfit-generic .so-tabs > div:nth-child(-n+2) { border-top: 0; }
        #start-outfit-generic .so-tab { white-space: nowrap; background-color: #1a1a1a; border-right: 1px solid #404040; }
    CSS;

    private array  $jsGlobals = [];
    private string $gridCss   = '';

    public function __construct(public readonly int $mode, public readonly int $id) { }

    private static function hasDBC() : bool
    {
        static $has = null;

        return $has ??= (bool)DB::Aowow()->selectCell('SHOW TABLES LIKE %s', 'dbc_charstartoutfit');
    }

    private static function hasTable(string $tbl, bool $aowow = false) : bool
    {
        static $known = [];

        return $known[($aowow ? 'a:' : 'w:').$tbl] ??= (bool)($aowow ? DB::Aowow() : DB::World())->selectCell('SHOW TABLES LIKE %s', $tbl);
    }

    /**
     * What a character is actually created knowing.
     *
     * TrinityCore grants these from SkillLineAbility.dbc rather than from any world DB table, which
     * is why none of the playercreateinfo_* tables mention them. `acquireMethod` 2 marks a row as
     * granted when the skill line is learned - i.e. at character creation.
     *
     * A row must name this class or this race positively. Treating a 0 mask as "any" on both at once
     * matches every character alive, which is how every pet ability - both masks 0 - ends up in a
     * warlock's spellbook. A 0 on one mask alone is still "any", so racials (no class mask) and
     * class spells (no race mask) both survive, while the class specific variants of Arcane Torrent,
     * Blood Fury and Command - which carry both - are held to both.
     */
    private function learnedSpells(int $race, int $class) : array
    {
        if (!self::hasTable('dbc_skilllineability', true))
            return [];

        $classBit = 1 << ($class - 1);
        $raceBit  = 1 << ($race  - 1);

        $ids = DB::Aowow()->selectCol(
           'SELECT `spellId` FROM dbc_skilllineability
            WHERE  `acquireMethod` = %i
              AND  ((`reqClassMask` & %i) OR (`reqRaceMask` & %i))
              AND  (`reqClassMask` = 0 OR (`reqClassMask` & %i))
              AND  (`reqRaceMask`  = 0 OR (`reqRaceMask`  & %i))',
            self::ACQUIRE_ON_SKILL_LEARN, $classBit, $raceBit, $classBit, $raceBit
        ) ?: [];

        return $this->hideForPlayers(array_values(array_unique(array_filter(array_map('intVal', $ids)))));
    }

    /** the race/class pairs that actually exist, from the server's own create info */
    private function pairs() : array
    {
        $where = $this->mode == self::BY_CLASS ? ['`class` = %i', $this->id] : ['`race` = %i', $this->id];

        $rows = DB::World()->selectAssoc(
           'SELECT `race`, `class`, `map`, `zone` FROM playercreateinfo WHERE '.$where[0].' ORDER BY `race`, `class` ASC',
            $where[1]
        ) ?: [];

        $out = [];
        foreach ($rows as $r)
            $out[] = array(
                'race'  => (int)$r['race'],
                'class' => (int)$r['class'],
                'map'   => (int)$r['map'],
                'zone'  => (int)$r['zone']
            );

        return $out;
    }

    /** CharStartOutfit.dbc, merged over both genders - the two differ only in shirt/model items */
    private function outfitItems(int $race, int $class) : array
    {
        if (!self::hasDBC())
            return [];

        $cols = [];
        for ($i = 1; $i <= 20; $i++)
            $cols[] = '`item'.$i.'`';

        $rows = DB::Aowow()->selectAssoc(
           'SELECT '.implode(', ', $cols).' FROM dbc_charstartoutfit WHERE `raceId` = %i AND `classId` = %i',
            $race, $class
        ) ?: [];

        $items = [];
        foreach ($rows as $r)
            foreach ($r as $v)
                if (($v = (int)$v) > 0)
                    $items[$v] = $v;

        return array_values($items);
    }

    private function extraItems(int $race, int $class) : array
    {
        $rows = DB::World()->selectAssoc('SELECT `itemid`, `amount` FROM playercreateinfo_item WHERE `race` = %i AND `class` = %i', $race, $class) ?: [];

        $out = [];
        foreach ($rows as $r)
            if (($id = (int)$r['itemid']) > 0)
                $out[$id] = max(1, (int)$r['amount']);

        return $out;
    }

    /**
     * TC moved this table from race/class columns to bitmasks; support both spellings
     *
     * Pick the table by checking it exists, never by letting a failed query fall through: a column
     * name this core spells differently would otherwise silently reroute to the other table, and
     * `playercreateinfo_spell` is a legacy table that on many DBs holds far more than the spells a
     * character is actually created with.
     */
    private function customSpells(int $race, int $class) : array
    {
        if (self::hasTable('playercreateinfo_spell_custom'))
            $ids = DB::World()->selectCol(
               'SELECT `Spell` FROM playercreateinfo_spell_custom WHERE (`racemask` = 0 OR (`racemask` & %i)) AND (`classmask` = 0 OR (`classmask` & %i))',
                1 << ($race - 1), 1 << ($class - 1)
            ) ?: [];
        else if (self::hasTable('playercreateinfo_spell'))
            $ids = DB::World()->selectCol('SELECT `Spell` FROM playercreateinfo_spell WHERE `race` = %i AND `class` = %i', $race, $class) ?: [];
        else
            $ids = [];

        $ids = array_values(array_unique(array_filter(array_map('intVal', $ids))));

        return $this->hideForPlayers($ids);
    }

    /**
     * a starting spell may be a hidden one - the passives a class is seeded with are not in the
     * spellbook. Drop those for players, the way CUSTOM_EXCLUDE_FOR_LISTVIEW is overridden for
     * staff everywhere else, rather than leaking them onto a public page
     */
    private function hideForPlayers(array $ids) : array
    {
        if (!$ids || User::isInGroup(U_GROUP_STAFF))
            return $ids;

        return array_map('intVal', DB::Aowow()->selectCol(
           'SELECT `id` FROM ::spell WHERE `id` IN %in AND (`cuFlags` & %i) = 0', $ids, CUSTOM_EXCLUDE_FOR_LISTVIEW
        ) ?: []);
    }

    /**
     * `playercreateinfo_cast_spell` - spells cast on a new character rather than learned
     *
     * This is where the hidden passives a class is seeded with live; they never appear in
     * playercreateinfo_spell_custom. The mask columns are spelled raceMask/classMask on some
     * revisions and racemask/classmask on others, so the row is read whole and the keys matched
     * case insensitively rather than guessing at a spelling.
     */
    private function castSpells(int $race, int $class) : array
    {
        if (!self::hasTable('playercreateinfo_cast_spell'))
            return [];

        $rows = DB::World()->selectAssoc('SELECT * FROM playercreateinfo_cast_spell') ?: [];
        if (!$rows)
            return [];

        $raceBit  = 1 << ($race  - 1);
        $classBit = 1 << ($class - 1);

        $ids = [];
        foreach ($rows as $r)
        {
            $lc = array_change_key_case($r, CASE_LOWER);

            $rm = (int)($lc['racemask']  ?? 0);
            $cm = (int)($lc['classmask'] ?? 0);
            $sp = (int)($lc['spell']     ?? 0);

            if ($sp > 0 && (!$rm || ($rm & $raceBit)) && (!$cm || ($cm & $classBit)))
                $ids[$sp] = $sp;
        }

        return $this->hideForPlayers(array_values($ids));
    }

    /**
     * split a set of spells into the tabs the in-game spellbook uses
     *
     * The tabs are skill lines: category 7 holds the class lines - the spec tabs - while weapon,
     * armor, language and racial lines all belong under General. `skillLine1` on the spell is the
     * line it is filed under, which is what the client groups by.
     *
     * @return array ordered [label => spellIds], General first
     */
    private function bySkillLine(array $spellIds) : array
    {
        if (!$spellIds)
            return [];

        $loc  = Lang::getLocale();
        $rows = DB::Aowow()->selectAssoc(
           'SELECT   s.`id` AS ARRAY_KEY, s.`skillLine1`, sl.`categoryId`, sl.`name_loc0`, sl.`name_loc'.$loc->value.'` AS "name_loc"
            FROM     ::spell s
            LEFT JOIN ::skillline sl ON sl.`id` = s.`skillLine1`
            WHERE    s.`id` IN %in',
            $spellIds
        ) ?: [];

        $general = [];
        $tabs    = [];                                      // skillLineId => [label, ids]

        foreach ($spellIds as $id)                          // keep the caller's order within a tab
        {
            $r = $rows[$id] ?? null;

            if (!$r || (int)$r['categoryId'] != self::SKILL_CATEGORY_CLASS || !(int)$r['skillLine1'])
            {
                $general[] = $id;
                continue;
            }

            $slId = (int)$r['skillLine1'];
            if (!isset($tabs[$slId]))
                $tabs[$slId] = [(string)($r['name_loc'] ?: $r['name_loc0']), []];

            $tabs[$slId][1][] = $id;
        }

        uasort($tabs, fn($a, $b) => strcmp($a[0], $b[0]));

        $out = [];
        if ($general)
            $out[Lang::startOutfit('general')] = $general;

        foreach ($tabs as $slId => [$label, $ids])
        {
            // without the global the markup has no name to resolve and prints "(Skill #<id>)"
            $this->jsGlobals[Type::SKILL][$slId] = $slId;
            $out['[skill='.$slId.']'] = $ids;
        }

        return $out;
    }

    /**
     * one grid row whose value cell holds the spellbook tabs as a nested two column grid
     * a set that only ever lands in one tab is not worth nesting, so it stays a plain list
     */
    private function spellRows(string $heading, array $spellIds) : array
    {
        if (!$spellIds)
            return [];

        foreach ($spellIds as $id)
            $this->jsGlobals[Type::SPELL][$id] = $id;

        $tabs = $this->bySkillLine($spellIds);
        $link = fn(array $ids) => Lang::concat(array_map(fn($x) => '[spell='.$x.']', $ids), Lang::CONCAT_NONE);

        if (count($tabs) < 2)
            return [[$heading, $link(reset($tabs) ?: $spellIds)]];

        $cell = '';
        foreach ($tabs as $label => $ids)
            $cell .= '[div class=so-tab]'.$label.'[/div][div]'.$link($ids).'[/div]';

        return [[$heading, '[div class=so-tabs]'.$cell.'[/div]']];
    }

    /**
     * `playercreateinfo_action` - the action bar a character logs in with
     * `type` says what the slot holds: 0 a spell, 128 an item, 64 a macro
     */
    private function actionBar(int $race, int $class) : array
    {
        if (!self::hasTable('playercreateinfo_action'))
            return [];

        $rows = DB::World()->selectAssoc(
           'SELECT `button`, `action`, `type` FROM playercreateinfo_action WHERE `race` = %i AND `class` = %i ORDER BY `button` ASC',
            $race, $class
        ) ?: [];

        $out = [];
        foreach ($rows as $r)
        {
            $act = (int)$r['action'];
            if ($act <= 0)
                continue;

            $out[] = match ((int)$r['type'])
            {
                self::ACTION_TYPE_ITEM  => ['type' => Type::ITEM,  'id' => $act],
                self::ACTION_TYPE_SPELL => ['type' => Type::SPELL, 'id' => $act],
                default                 => ['type' => 0,           'id' => $act]
            };
        }

        return $out;
    }

    private function renderGrid(array $th, array $rows) : string
    {
        $tblId = Util::createHash(12);
        $this->gridCss .= "\n#tbl-".$tblId." { grid-template-columns: ".implode(' ', array_column($th, 1))."; }";

        $tbl = '[tr]' . array_reduce(array_column($th, 0), fn($out, $n) => $out .= '[td header]'.$n.'[/td]', '') . '[/tr]';
        foreach ($rows as $r)
            $tbl .= '[tr][td]'.implode('[/td][td]', $r).'[/td][/tr]';

        return '[table id=tbl-'.$tblId.' class=grid]'.$tbl.'[/table]';
    }

    public function getMarkup() : ?Markup
    {
        $body    = '';
        $isStaff = User::isInGroup(U_GROUP_STAFF);

        foreach ($this->pairs() as $p)
        {
            // the varying half of the pair is what names each block
            $other = $this->mode == self::BY_CLASS ? $p['race'] : $p['class'];
            $head  = $this->mode == self::BY_CLASS ? '[race='.$p['race'].']' : '[class='.$p['class'].']';
            $this->jsGlobals[$this->mode == self::BY_CLASS ? Type::CHR_RACE : Type::CHR_CLASS][$other] = $other;

            $outfit  = $this->outfitItems($p['race'], $p['class']);
            $extra   = $this->extraItems($p['race'], $p['class']);
            $learned = $this->learnedSpells($p['race'], $p['class']);
            $cast    = array_values(array_diff($this->castSpells($p['race'], $p['class']), $learned));

            // `playercreateinfo_spell_custom` is not the starting set - in stock TDB it is a full
            // max rank spellbook per class - so it is staff only and labelled apart from the rest
            $custom = $isStaff ? array_values(array_diff($this->customSpells($p['race'], $p['class']), $learned, $cast)) : [];

            if (!$outfit && !$extra && !$learned && !$cast && !$custom)
                continue;

            $rows = [];

            if ($outfit)
            {
                foreach ($outfit as $id)
                    $this->jsGlobals[Type::ITEM][$id] = $id;

                $rows[] = [Lang::startOutfit('gear'), Lang::concat(array_map(fn($x) => '[item='.$x.']', $outfit), Lang::CONCAT_NONE)];
            }

            if ($extra)
            {
                foreach (array_keys($extra) as $id)
                    $this->jsGlobals[Type::ITEM][$id] = $id;

                $rows[] = [Lang::startOutfit('extra'), Lang::concat(array_map(fn($id, $n) => ($n > 1 ? $n.'x ' : '').'[item='.$id.']', array_keys($extra), $extra), Lang::CONCAT_NONE)];
            }

            $rows = array_merge($rows, $this->spellRows(Lang::startOutfit('spells'), $learned));

            $rows = array_merge($rows, $this->spellRows(Lang::startOutfit('castSpells'), $cast));

            $rows = array_merge($rows, $this->spellRows(Lang::startOutfit('customSpells'), $custom));

            if ($bar = $this->actionBar($p['race'], $p['class']))
            {
                $cells = [];
                foreach ($bar as $slot)
                {
                    if ($slot['type'])
                    {
                        $this->jsGlobals[$slot['type']][$slot['id']] = $slot['id'];
                        $cells[] = '['.Type::getFileString($slot['type']).'='.$slot['id'].']';
                    }
                    else
                        $cells[] = '[small class=q0]'.$slot['id'].'[/small]';
                }

                $rows[] = [Lang::startOutfit('actionBar'), Lang::concat($cells, Lang::CONCAT_NONE)];
            }

            if ($p['zone'])
            {
                $this->jsGlobals[Type::ZONE][$p['zone']] = $p['zone'];
                $rows[] = [Lang::startOutfit('startsIn'), '[zone='.$p['zone'].']'];
            }

            $uid   = 'start-outfit-'.$p['race'].'-'.$p['class'];
            $body .= '[pad][h3][toggler id='.$uid.']'.$head.'[/toggler][/h3][div id='.$uid.' clear=left]'.
                     $this->renderGrid([[Lang::startOutfit('what'), '160px'], [Lang::startOutfit('value'), 'auto']], $rows).'[/div]';
        }

        if (!$body)
            return null;

        return new Markup('[style]'.strtr(self::BASE_CSS.$this->gridCss, "\n", ' ').'[/style][h2]'.Lang::startOutfit('title').'[/h2]'.$body,
                          ['allow' => Markup::CLASS_ADMIN], 'start-outfit-generic');
    }

    public function getJSGlobals() : array
    {
        return $this->jsGlobals;
    }
}

?>
