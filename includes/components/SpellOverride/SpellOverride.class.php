<?php

namespace Aowow;

if (!defined('AOWOW_REVISION'))
    die('illegal access');


/*
 * Server side overrides of a spell.
 *
 * The spell page renders what Spell.dbc says. The core then changes a good deal of that from the
 * world DB, and the spell page already reads four of those tables piecemeal - spell_proc,
 * spell_bonus_data, spell_linked_spell and spell_script_names. The rest were read nowhere, so a
 * spell could teleport you, require another spell, or be forbidden from stacking, and the page
 * would say nothing about it.
 *
 * Everything here is read from DB::World() live and every table is checked before it is touched,
 * as which of them a core ships varies.
 */
class SpellOverride
{
    private const string BASE_CSS = <<<CSS
        #spell-override-generic .grid { clear:left; display: grid; grid-template-columns: 200px auto; }
        #spell-override-generic .grid thead,
        #spell-override-generic .grid tbody,
        #spell-override-generic .grid tr { display: contents; }
    CSS;

    private array $rows      = [];                          // [label, value]
    private array $jsGlobals = [];

    public function __construct(public readonly int $spellId) { }

    private static function hasTable(string $tbl) : bool
    {
        static $known = [];

        return $known[$tbl] ??= (bool)DB::World()->selectCell('SHOW TABLES LIKE %s', $tbl);
    }

    private function add(string $label, string $value) : void
    {
        if ($value !== '')
            $this->rows[] = [$label, $value];
    }

    /** where a teleport actually drops the player; column names differ between TC revisions */
    private function targetPosition() : void
    {
        if (!self::hasTable('spell_target_position'))
            return;

        $rows = DB::World()->selectAssoc('SELECT `EffectIndex`, `MapID`, `PositionX`, `PositionY`, `PositionZ` FROM spell_target_position WHERE `ID` = %i', $this->spellId);
        if ($rows === null)
            $rows = DB::World()->selectAssoc('SELECT 0 AS "EffectIndex", `target_map` AS "MapID", `target_position_x` AS "PositionX", `target_position_y` AS "PositionY", `target_position_z` AS "PositionZ" FROM spell_target_position WHERE `id` = %i', $this->spellId) ?: [];

        $out = [];
        foreach ($rows as $r)
        {
            $pos = sprintf('%.1f, %.1f, %.1f', (float)$r['PositionX'], (float)$r['PositionY'], (float)$r['PositionZ']);

            if ($areaId = (int)DB::Aowow()->selectCell('SELECT `id` FROM ::zones WHERE `mapId` = %i AND `parentArea` = 0 AND (`cuFlags` & %i) = 0 LIMIT 1', (int)$r['MapID'], CUSTOM_EXCLUDE_FOR_LISTVIEW))
            {
                $this->jsGlobals[Type::ZONE][$areaId] = $areaId;
                $out[] = '[zone='.$areaId.'] [small class=q0]'.$pos.'[/small]';
            }
            else
                $out[] = Lang::spellOverride('map', [(int)$r['MapID']]).' [small class=q0]'.$pos.'[/small]';
        }

        $this->add(Lang::spellOverride('teleportsTo'), implode('[br]', $out));
    }

    private function threat() : void
    {
        if (!self::hasTable('spell_threat'))
            return;

        if (!($r = DB::World()->selectRow('SELECT `flatMod`, `pctMod`, `apPctMod` FROM spell_threat WHERE `entry` = %i', $this->spellId)))
            return;

        $parts = [];
        if ($_ = (int)$r['flatMod'])
            $parts[] = Lang::spellOverride('threatFlat', [$_]);
        if (($_ = (float)$r['pctMod']) && $_ != 1.0)
            $parts[] = Lang::spellOverride('threatPct', [$_ * 100]);
        if ($_ = (float)$r['apPctMod'])
            $parts[] = Lang::spellOverride('threatAP', [$_ * 100]);

        $this->add(Lang::spellOverride('threat'), implode(', ', $parts));
    }

    private function required() : void
    {
        if (!self::hasTable('spell_required'))
            return;

        $ids = DB::World()->selectCol('SELECT `req_spell` FROM spell_required WHERE `spell_id` = %i', $this->spellId) ?: [];
        $ids = array_values(array_filter(array_map('intVal', $ids)));

        foreach ($ids as $id)
            $this->jsGlobals[Type::SPELL][$id] = $id;

        $this->add(Lang::spellOverride('requires'), Lang::concat(array_map(fn($x) => '[spell='.$x.']', $ids), Lang::CONCAT_AND));
    }

    private function petAuras() : void
    {
        if (!self::hasTable('spell_pet_auras'))
            return;

        $rows = DB::World()->selectAssoc('SELECT `pet`, `aura` FROM spell_pet_auras WHERE `spell` = %i', $this->spellId) ?: [];

        $out = [];
        foreach ($rows as $r)
        {
            if (!($aura = (int)$r['aura']))
                continue;

            $this->jsGlobals[Type::SPELL][$aura] = $aura;

            // pet 0 means "any pet"; anything else is a creature entry
            if ($pet = (int)$r['pet'])
            {
                $this->jsGlobals[Type::NPC][$pet] = $pet;
                $out[] = Lang::spellOverride('petAuraFor', ['[npc='.$pet.']', '[spell='.$aura.']']);
            }
            else
                $out[] = Lang::spellOverride('petAuraAny', ['[spell='.$aura.']']);
        }

        $this->add(Lang::spellOverride('petAura'), implode('[br]', $out));
    }

    /** spells in a group cannot stack with one another; the rule lives in a second table */
    private function groups() : void
    {
        if (!self::hasTable('spell_group'))
            return;

        $groupIds = DB::World()->selectCol('SELECT `id` FROM spell_group WHERE `spell_id` = %i', $this->spellId) ?: [];
        $groupIds = array_values(array_filter(array_map('intVal', $groupIds)));
        if (!$groupIds)
            return;

        $rules = self::hasTable('spell_group_stack_rules')
               ? (DB::World()->selectPairs('SELECT `group_id`, `stack_rule` FROM spell_group_stack_rules WHERE `group_id` IN %in', $groupIds) ?: [])
               : [];

        $out = [];
        foreach ($groupIds as $gId)
        {
            $peers = DB::World()->selectCol('SELECT `spell_id` FROM spell_group WHERE `id` = %i AND `spell_id` <> %i', $gId, $this->spellId) ?: [];
            $peers = array_values(array_filter(array_map('intVal', $peers), fn($x) => $x > 0));

            foreach ($peers as $id)
                $this->jsGlobals[Type::SPELL][$id] = $id;

            $rule = Lang::spellOverride('stackRules', (int)($rules[$gId] ?? 0)) ?: Lang::spellOverride('stackRules', 0);
            $out[] = Lang::spellOverride('groupLine', [$gId, $rule]) .
                     ($peers ? ' ' . Lang::concat(array_map(fn($x) => '[spell='.$x.']', $peers), Lang::CONCAT_NONE) : '');
        }

        $this->add(Lang::spellOverride('group'), implode('[br]', $out));
    }

    private function customAttr() : void
    {
        if (!self::hasTable('spell_custom_attr'))
            return;

        if ($_ = (int)DB::World()->selectCell('SELECT `attributes` FROM spell_custom_attr WHERE `entry` = %i', $this->spellId))
            $this->add(Lang::spellOverride('customAttr'), '0x'.strtoupper(str_pad(dechex($_), 8, '0', STR_PAD_LEFT)));
    }

    public function getMarkup() : ?Markup
    {
        $this->targetPosition();
        $this->threat();
        $this->required();
        $this->petAuras();
        $this->groups();
        $this->customAttr();

        if (!$this->rows)
            return null;

        $tbl = '';
        foreach ($this->rows as [$label, $value])
            $tbl .= '[tr][td][b]'.$label.'[/b][/td][td]'.$value.'[/td][/tr]';

        $body = '[style]'.strtr(self::BASE_CSS, "\n", ' ').'[/style]'.
                '[pad][h3][toggler id=spell-override]'.Lang::spellOverride('title').'[/toggler][/h3]'.
                '[div id=spell-override clear=left][table class=grid]'.$tbl.'[/table][/div]';

        return new Markup($body, ['allow' => Markup::CLASS_ADMIN], 'spell-override-generic');
    }

    public function getJSGlobals() : array
    {
        return $this->jsGlobals;
    }
}

?>
