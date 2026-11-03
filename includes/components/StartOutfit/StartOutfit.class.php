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
 * plus `playercreateinfo` for the starting location and `playercreateinfo_skills` for the skill
 * lines whose abilities are learned with them.
 *
 * The world tables are read live. The DBC is written into the aowow DB by the `source` setup step
 * and is used when `dbc_charstartoutfit` is there, so nothing needs re-importing - but a setup run
 * with --delete leaves the table TEMPORARY, hence the guard.
 */
class StartOutfit
{
    public const int BY_CLASS = 1;                          // list every race of one class
    public const int BY_RACE  = 2;                          // list every class of one race

    // SkillLineAbility.dbc acquireMethod: 1 is "granted once the skill reaches the row's minimum value",
    // 2 is "granted when the skill line is learned"
    private const int ACQUIRE_ON_SKILL_VALUE = 1;
    private const int ACQUIRE_ON_SKILL_LEARN = 2;

    // worldserver.conf.dist defaults for StartPlayerLevel / StartDeathKnightPlayerLevel
    private const int START_LEVEL            = 1;
    private const int START_LEVEL_DK         = 55;

    // SkillRaceClassInfo.dbc flags: SKILL_FLAG_ALWAYS_MAX_VALUE
    private const int SKILL_FLAG_ALWAYS_MAX  = 0x10;

    // SkillLine.dbc categoryId: SKILL_CATEGORY_ARMOR / SKILL_CATEGORY_LANGUAGES
    private const int SKILL_CATEGORY_ARMOR   = 8;
    private const int SKILL_CATEGORY_LANG    = 10;

    private const int SKILL_UNARMED          = 162;
    private const int SKILL_FIST_WEAPONS     = 473;
    private const int SKILL_RUNEFORGING      = 776;

    // SkillLine.dbc categoryId 7 is the class skill lines - the spellbook's spec tabs. Weapon, armor,
    // language and racial lines sit in other categories and belong under General, as they do in game.
    private const int SKILL_CATEGORY_CLASS   = 7;

    // playercreateinfo_action.type - ACTION_BUTTON_SPELL / ACTION_BUTTON_ITEM
    private const int ACTION_TYPE_SPELL      = 0;
    private const int ACTION_TYPE_ITEM       = 128;

    // the client numbers action buttons in blocks of twelve; block 0 is the bar a character logs in
    // with, the rest are the other bars and the alternate pages
    private const int MAIN_BAR_SLOTS         = 12;

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
     * The skill lines a character is created with and the value each starts at, as [skillLineId => value]
     *
     * Player::LearnDefaultSkills walks the `playercreateinfo_skills` rows of the pair - the first row
     * of a skill line wins - and LearnDefaultSkill drops a line SkillRaceClassInfo.dbc has no row for
     * this pair on, using the first row that matches. The value follows GetSkillRangeType: a line with
     * a skill tier starts at 1 of that tier's rank (and not at all at rank 0), armor and Runeforging
     * at 1, languages at 300, the rest at 1 of the level's maximum. A death knight starts above 1, as
     * does a line flagged to always be at its maximum.
     */
    private function startingSkills(int $race, int $class) : array
    {
        if (!self::hasTable('dbc_skillraceclassinfo', true) || !self::hasTable('playercreateinfo_skills'))
            return [];

        $classBit = 1 << ($class - 1);
        $raceBit  = 1 << ($race  - 1);

        $ranks = [];
        foreach (DB::World()->selectAssoc(
           'SELECT `skill`, `rank` FROM playercreateinfo_skills
            WHERE  (`raceMask`  = 0 OR (`raceMask`  & %i))
              AND  (`classMask` = 0 OR (`classMask` & %i))',
            $raceBit, $classBit
        ) ?: [] as $r)
            $ranks[(int)$r['skill']] ??= (int)$r['rank'];

        if (!$ranks)
            return [];

        $rcInfo = [];
        foreach (DB::Aowow()->selectAssoc(
           'SELECT `skillLine`, `flags`, `skillTierId` FROM dbc_skillraceclassinfo
            WHERE  `skillLine` IN %in
              AND  (`raceMask`  = 0 OR (`raceMask`  & %i))
              AND  (`classMask` = 0 OR (`classMask` & %i))
            ORDER BY `id` ASC',
            array_keys($ranks), $raceBit, $classBit
        ) ?: [] as $r)
            $rcInfo[(int)$r['skillLine']] ??= $r;

        if (!$rcInfo)
            return [];

        $categories = DB::Aowow()->selectCol('SELECT `id` AS ARRAY_KEY, `categoryId` FROM ::skillline WHERE `id` IN %in', array_keys($rcInfo)) ?: [];

        $tiers = [];
        if ($tierIds = array_filter(array_map(fn($r) => (int)$r['skillTierId'], $rcInfo)))
            $tiers = DB::Aowow()->selectAssoc('SELECT * FROM ::skilltiers WHERE `id` IN %in', array_values(array_unique($tierIds))) ?: [];
        $tiers = array_column($tiers, null, 'id');

        $isDK     = $class == ChrClass::DEATHKNIGHT->value;
        $level    = $isDK ? self::START_LEVEL_DK : self::START_LEVEL;
        $levelMax = $level * 5;

        $value = function (int $skill) use ($rcInfo, $ranks, $categories, $tiers, $isDK, $level, $levelMax) : int
        {
            $rc    = $rcInfo[$skill];
            $start = fn(int $cap) => (int)$rc['flags'] & self::SKILL_FLAG_ALWAYS_MAX ? $cap : ($isDK ? min(max(1, ($level - 1) * 5), $cap) : 1);

            if (!isset($categories[$skill]))                // SKILL_RANGE_NONE
                return 0;

            if ($tier = $tiers[(int)$rc['skillTierId']] ?? null)
                return $ranks[$skill] ? $start((int)$tier['value'.$ranks[$skill]]) : 0;

            if ($skill == self::SKILL_RUNEFORGING || $categories[$skill] == self::SKILL_CATEGORY_ARMOR)
                return 1;

            if ($categories[$skill] == self::SKILL_CATEGORY_LANG)
                return 300;

            return $start($levelMax);
        };

        $out = [];
        foreach (array_keys($rcInfo) as $skill)
            if ($v = $value($skill))
                $out[$skill] = $v;

        // fist weapons start at the unarmed value, which only differs from 1 where unarmed is maxed
        if (isset($out[self::SKILL_FIST_WEAPONS]) && !$isDK && !((int)$rcInfo[self::SKILL_FIST_WEAPONS]['flags'] & self::SKILL_FLAG_ALWAYS_MAX))
            $out[self::SKILL_FIST_WEAPONS] = max(1, $out[self::SKILL_UNARMED] ?? 0);

        return $out;
    }

    /**
     * What a character is actually created knowing.
     *
     * TrinityCore grants these from SkillLineAbility.dbc rather than from any world DB table, which
     * is why none of the playercreateinfo_* tables mention them. Player::LearnSkillRewardedSpells
     * learns the abilities of each starting skill line whose race and class masks are 0 or match:
     * those with `acquireMethod` 2 outright, those with 1 once the skill's value reaches the row's
     * minimum.
     *
     * The masks alone say nothing about the skill line: the paladin Arcane Torrent sits on the Blood
     * Elf racial line with only a class mask, so it is the line that keeps it from a dwarf paladin.
     */
    private function learnedSpells(int $race, int $class) : array
    {
        if (!self::hasTable('dbc_skilllineability', true))
            return [];

        if (!$skills = $this->startingSkills($race, $class))
            return [];

        $rows = DB::Aowow()->selectAssoc(
           'SELECT `spellId`, `skillLineId`, `acquireMethod`, `reqSkillLevel` FROM dbc_skilllineability
            WHERE  `acquireMethod` IN %in
              AND  `skillLineId` IN %in
              AND  (`reqClassMask` = 0 OR (`reqClassMask` & %i))
              AND  (`reqRaceMask`  = 0 OR (`reqRaceMask`  & %i))',
            [self::ACQUIRE_ON_SKILL_VALUE, self::ACQUIRE_ON_SKILL_LEARN], array_keys($skills), 1 << ($class - 1), 1 << ($race - 1)
        ) ?: [];

        $ids = [];
        foreach ($rows as $r)
            if ((int)$r['acquireMethod'] == self::ACQUIRE_ON_SKILL_LEARN || $skills[(int)$r['skillLineId']] >= (int)$r['reqSkillLevel'])
                $ids[] = (int)$r['spellId'];

        return $this->hideForPlayers(array_values(array_unique(array_filter($ids))));
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

    /**
     * CharStartOutfit.dbc, merged over both genders - the two differ only in shirt/model items
     * an item `playercreateinfo_item` gives a negative amount is struck from the outfit
     */
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

        $removed = self::createItems($race, $class)[1];

        $items = [];
        foreach ($rows as $r)
            foreach ($r as $v)
                if (($v = (int)$v) > 0 && !in_array($v, $removed))
                    $items[$v] = $v;

        return array_values($items);
    }

    private function extraItems(int $race, int $class) : array
    {
        return self::createItems($race, $class)[0];
    }

    /**
     * `playercreateinfo_item` for one race/class pair as [[itemId => amount], removedItemIds]
     * ObjectMgr::LoadPlayerInfo reads a race or class of 0 as every race/class, and
     * PlayerCreateInfoAddItemHelper takes a negative amount as "remove from CharStartOutfit" -
     * independent of any positive row, which hands the item out on top
     */
    private static function createItems(int $race, int $class) : array
    {
        $rows = DB::World()->selectAssoc(
           'SELECT `itemid`, `amount` FROM playercreateinfo_item WHERE `race` IN (0, %i) AND `class` IN (0, %i)',
            $race, $class
        ) ?: [];

        $add = $del = [];
        foreach ($rows as $r)
        {
            if (($id = (int)$r['itemid']) <= 0)
                continue;

            if (($n = (int)$r['amount']) > 0)
                $add[$id] = ($add[$id] ?? 0) + $n;
            else if ($n < 0)
                $del[$id] = $id;
        }

        return [$add, array_values($del)];
    }

    /**
     * the reverse lookup: every race/class pair that starts with this item
     *
     * @return array ['outfit' => [[race, class], ..], 'extra' => [[race, class], ..], 'all' => [class => races[]]]
     */
    public static function pairsForItem(int $itemId) : array
    {
        $out = ['outfit' => [], 'extra' => [], 'all' => []];

        $pairs = DB::World()->selectAssoc('SELECT `race`, `class` FROM playercreateinfo ORDER BY `class`, `race` ASC') ?: [];
        if (!$pairs)
            return $out;

        $outfit = [];
        if (self::hasDBC())
        {
            $cols = [];
            for ($i = 1; $i <= 20; $i++)
                $cols[] = '`item'.$i.'`';

            foreach (DB::Aowow()->selectAssoc('SELECT DISTINCT `raceId`, `classId` FROM dbc_charstartoutfit WHERE %i IN ('.implode(', ', $cols).')', $itemId) ?: [] as $r)
                $outfit[$r['raceId'].':'.$r['classId']] = true;
        }

        $create = DB::World()->selectAssoc('SELECT `race`, `class`, `amount` FROM playercreateinfo_item WHERE `itemid` = %i', $itemId) ?: [];

        foreach ($pairs as $p)
        {
            $race  = (int)$p['race'];
            $class = (int)$p['class'];

            $out['all'][$class][] = $race;

            $add = $del = false;
            foreach ($create as $c)
            {
                if (((int)$c['race'] && (int)$c['race'] != $race) || ((int)$c['class'] && (int)$c['class'] != $class))
                    continue;

                $add = $add || (int)$c['amount'] > 0;
                $del = $del || (int)$c['amount'] < 0;
            }

            if (isset($outfit[$race.':'.$class]) && !$del)
                $out['outfit'][] = [$race, $class];

            if ($add)
                $out['extra'][] = [$race, $class];
        }

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
        if (!$ids || User::canSee(U_GROUP_STAFF))
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

        // only the first block is read. TDB mirrors part of the starting bar onto the alternate
        // pages - every death knight carries Attack on buttons 0, 72, 84, 96 and 108 - and those
        // copies used to be listed as if they were extra abilities
        $rows = DB::World()->selectAssoc(
           'SELECT `button`, `action`, `type` FROM playercreateinfo_action WHERE `race` = %i AND `class` = %i AND `button` < %i ORDER BY `button` ASC',
            $race, $class, self::MAIN_BAR_SLOTS
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
        $isStaff = User::canSee(U_GROUP_STAFF);

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
