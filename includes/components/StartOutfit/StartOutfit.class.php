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

    private const string BASE_CSS = <<<CSS
        #start-outfit-generic .grid { clear:left; display: grid; }
        #start-outfit-generic .grid thead,
        #start-outfit-generic .grid tbody,
        #start-outfit-generic .grid tr { display: contents; }
    CSS;

    private array  $jsGlobals = [];
    private string $gridCss   = '';

    public function __construct(public readonly int $mode, public readonly int $id) { }

    private static function hasDBC() : bool
    {
        static $has = null;

        return $has ??= (bool)DB::Aowow()->selectCell('SHOW TABLES LIKE %s', 'dbc_charstartoutfit');
    }

    private static function hasTable(string $tbl) : bool
    {
        static $known = [];

        return $known[$tbl] ??= (bool)DB::World()->selectCell('SHOW TABLES LIKE %s', $tbl);
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
    private function spells(int $race, int $class) : array
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

        // a starting spell may be a hidden one - the passives a class is seeded with are not in the
        // spellbook. Drop those for players, the way CUSTOM_EXCLUDE_FOR_LISTVIEW is overridden for
        // staff everywhere else, rather than leaking them onto a public page
        if ($ids && !User::isInGroup(U_GROUP_STAFF))
            $ids = array_map('intVal', DB::Aowow()->selectCol(
               'SELECT `id` FROM ::spell WHERE `id` IN %in AND (`cuFlags` & %i) = 0', $ids, CUSTOM_EXCLUDE_FOR_LISTVIEW
            ) ?: []);

        return $ids;
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
        $body = '';

        foreach ($this->pairs() as $p)
        {
            // the varying half of the pair is what names each block
            $other = $this->mode == self::BY_CLASS ? $p['race'] : $p['class'];
            $head  = $this->mode == self::BY_CLASS ? '[race='.$p['race'].']' : '[class='.$p['class'].']';
            $this->jsGlobals[$this->mode == self::BY_CLASS ? Type::CHR_RACE : Type::CHR_CLASS][$other] = $other;

            $outfit = $this->outfitItems($p['race'], $p['class']);
            $extra  = $this->extraItems($p['race'], $p['class']);
            $spells = $this->spells($p['race'], $p['class']);

            if (!$outfit && !$extra && !$spells)
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

            if ($spells)
            {
                foreach ($spells as $id)
                    $this->jsGlobals[Type::SPELL][$id] = $id;

                $rows[] = [Lang::startOutfit('spells'), Lang::concat(array_map(fn($x) => '[spell='.$x.']', $spells), Lang::CONCAT_NONE)];
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
