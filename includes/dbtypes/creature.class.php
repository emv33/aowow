<?php

namespace Aowow;

if (!defined('AOWOW_REVISION'))
    die('illegal access');


class CreatureList extends DBTypeList
{
    use spawnHelper;

    public static int    $type      = Type::NPC;
    public static string $brickFile = 'npc';
    public static string $dataTable = '::creature';

    // aowow - custom start: ::creature_difficulty.kind - also the map type index of Lang::game('modes')
    public const int MODE_KIND_UNKNOWN = 0;                 // difficulty 1 could be dungeon heroic or raid 25 normal
    public const int MODE_KIND_DUNGEON = 1;
    public const int MODE_KIND_RAID    = 2;
    // aowow - custom end

    protected string $queryBase = 'SELECT ct.*, ct.`id` AS ARRAY_KEY FROM ::creature ct';
    public    array  $queryOpts = array(
                        'ct'   => [['ft', 'qse', 'dct1', 'dct2', 'dct3', 'cd'], 's' => ', IFNULL(dct1.`id`, IFNULL(dct2.`id`, IFNULL(dct3.`id`, 0))) AS "parentId", IFNULL(dct1.`name_loc0`, IFNULL(dct2.`name_loc0`, IFNULL(dct3.`name_loc0`, ""))) AS "parent_loc0", IFNULL(dct1.`name_loc2`, IFNULL(dct2.`name_loc2`, IFNULL(dct3.`name_loc2`, ""))) AS "parent_loc2", IFNULL(dct1.`name_loc3`, IFNULL(dct2.`name_loc3`, IFNULL(dct3.`name_loc3`, ""))) AS "parent_loc3", IFNULL(dct1.`name_loc4`, IFNULL(dct2.`name_loc4`, IFNULL(dct3.`name_loc4`, ""))) AS "parent_loc4", IFNULL(dct1.`name_loc6`, IFNULL(dct2.`name_loc6`, IFNULL(dct3.`name_loc6`, ""))) AS "parent_loc6", IFNULL(dct1.name_loc8, IFNULL(dct2.`name_loc8`, IFNULL(dct3.`name_loc8`, ""))) AS "parent_loc8", IF(dct1.`difficultyEntry1` = ct.`id`, 1, IF(dct2.`difficultyEntry2` = ct.`id`, 2, IF(dct3.`difficultyEntry3` = ct.`id`, 3, 0))) AS "difficultyMode"'],
                        'nml'  => ['j' => ['::creature_search nml ON nml.`id` = ct.`id` AND nml.`locale` = DB_LOC_I']],
                        'dct1' => ['j' => ['::creature dct1 ON ct.`cuFlags` & 0x02 AND dct1.`difficultyEntry1` = ct.`id`', true]],
                        'dct2' => ['j' => ['::creature dct2 ON ct.`cuFlags` & 0x02 AND dct2.`difficultyEntry2` = ct.`id`', true]],
                        'dct3' => ['j' => ['::creature dct3 ON ct.`cuFlags` & 0x02 AND dct3.`difficultyEntry3` = ct.`id`', true]],
                        'cd'   => ['j' => ['::creature_difficulty cd ON cd.`id` = ct.`id`', true], 's' => ', cd.`kind` AS "modeKind", cd.`modeMask`'],   // aowow - custom
                        'ft'   => ['j' => '::factiontemplate ft ON ft.`id` = ct.`faction`', 's' => ', ft.`factionId`, IFNULL(ft.`A`, 0) AS "A", IFNULL(ft.`H`, 0) AS "H", ft.`flags` AS "ftFlags"'],
                        'qse'  => ['j' => ['::quests_startend qse ON qse.`type` = 1 AND qse.`typeId` = ct.id', true], 's' => ', IF(MIN(qse.`method`) = 1 OR MAX(qse.`method`) = 3, 1, 0) AS "startsQuests", IF(MIN(qse.`method`) = 2 OR MAX(qse.`method`) = 3, 1, 0) AS "endsQuests"', 'g' => 'ct.`id`'],
                        'qt'   => ['j' => '::quests qt ON qse.`questId` = qt.`id`'],
                        's'    => ['j' => ['::spawns s ON s.`type` = 1 AND s.`typeId` = ct.`id`', true]]
                    );

    public function __construct(array $conditions = [], array $miscData = [])
    {
        parent::__construct($conditions, $miscData);

        if ($this->error)
            return;

        // post processing
        foreach ($this->iterate() as $_id => &$curTpl)
        {
            // check for attackspeeds
            if (!$curTpl['atkSpeed'])
                $curTpl['atkSpeed'] = 2.0;
            else
                $curTpl['atkSpeed'] /= 1000;

            if (!$curTpl['rngAtkSpeed'])
                $curTpl['rngAtkSpeed'] = 2.0;
            else
                $curTpl['rngAtkSpeed'] /= 1000;
        }
    }

    public function renderTooltip() : ?string
    {
        if (!$this->curTpl)
            return null;

        $level = '??';
        if (!($this->curTpl['typeFlags'] & NPC_TYPEFLAG_BOSS_MOB))
            $level = Util::createNumRange($this->curTpl['minLevel'], $this->curTpl['maxLevel']);

        // the client omits type for friendly targets and type 10 ('Not Specified')
        $type = $this->curTpl['type'];
        if (($this->curTpl['A'] == 1 && $this->curTpl['H'] == 1) || $type == NPC_TYPE_NOT_SPECIFIED)
            $type = NPC_TYPE_NONE;

        // s_classificationTokens says: ELITE and RARE_ELITE are displayed as ELITE
        // everything else is omitted
        $rank = match($this->curTpl['rank'])
        {
            NPC_RANK_ELITE,
            NPC_RANK_RARE_ELITE  => NPC_RANK_ELITE,
            default              => NPC_RANK_NORMAL
        };

        // yes, the actual npc rank does not matter here. It's strictly by the typeFlags.
        if ($this->curTpl['typeFlags'] & NPC_TYPEFLAG_BOSS_MOB)
            $rank = NPC_RANK_BOSS;

        if ($rank && !$type)
            $row3 = Lang::npc('level', 3, [$level, Lang::npc('rank', $rank)]); // TOOLTIP_UNIT_LEVEL_TYPE
        else if ($rank && $type)
            $row3 = Lang::npc('level', 2, [$level, Lang::game('ct', $type), Lang::npc('rank', $rank)]); // TOOLTIP_UNIT_LEVEL_CLASS_TYPE
        else if (!$rank && $type)
            $row3 = Lang::npc('level', 1, [$level, Lang::game('ct', $type)]); // TOOLTIP_UNIT_LEVEL_CLASS
        else
            $row3 = Lang::npc('level', 0, [$level]); // TOOLTIP_UNIT_LEVEL

        $x  = '<table>';

        # Name #

        $x .= '<tr><td><b class="q">'.Util::htmlEscape($this->getField('name', true)).'</b></td></tr>';

        # Subname #

        if ($sn = $this->getField('subname', true))
            $x .= '<tr><td>'.Util::htmlEscape($sn).'</td></tr>';

        # Summoned By #

        // cannot use
        // UNITNAME_SUMMON_TITLE\d

        # Level / Class / Type #

        $x .= '<tr><td>'.$row3.'</td></tr>';

        // WH non-standard
        if ($this->curTpl['type'] == NPC_TYPE_BEAST && ($fam = $this->curTpl['family']))
            $x .= '<tr><td>'.Lang::game('fa', $fam).'</td></tr>';

        # Faction #

        $fac = new FactionList(array([['cuFlags', Cfg::get('PUBLIC_ACCESS') ? 0 : CUSTOM_EXCLUDE_FOR_LISTVIEW, '&'], 0], ['id', $this->getField('factionId')]));
        if (!$fac->error)
            $x .= '<tr><td>'.$fac->getField('name', true).'</td></tr>';

        # PvP #

        // Bit is set from FactionTemplate.dbc/Flags & FACTION_TEMPLATE_FLAG_PVP and overridden by cta.PvPFlags which are weird.
        // npcs being simultaneously flagged as civillian and for pvp.
        $pvpFlags = $this->curTpl['pvpFlags'] ?: (($this->curTpl['ftFlags'] & FACTION_TEMPLATE_FLAG_PVP) ? 1 : 0);

        if ($pvpFlags & UNIT_BYTE2_FLAG_PVP)
            $x .= '<tr><td>'.Lang::game('pvp').'</td></tr>';

        # AddEmpathyText #

        // if dynFlags & 0x10 show more info (only set through TC, not TDB) (caused by Aura Effect: Beast Lore)
        //     SINGLE_DAMAGE_TEMPLATE / DAMAGE_TEMPLATE
        //     ARMOR_TEMPLATE
        //     HP_TEMPLATE
        //     RESISTANCE_TEMPLATE + RESISTANCE%d_NAME - per resistance
        //     PET_DIET_TEMPLATE - concat food items
        //     NOT_TAMEABLE / TAMEABLE_EXOTIC / TAMEABLE
        //     PET_SPELLS_TEMPLATE - concat spells with attributes & 0xC0 && !supercededBySpell && acquireMethod == 2

        # AddSkinnableText #

        // $this->isGatherable()UNIT_SKINNABLE_HERB
        // $this->isMineable() UNIT_SKINNABLE_ROCK
        // $this->isSalvageable() UNIT_SKINNABLE_BOLTS
        // else if skinLootId UNIT_SKINNABLE_LEATHER

        # PvP Leader #

        if ($this->curTpl['racialLeader'] && ($pvpFlags & UNIT_BYTE2_FLAG_PVP))
            $x .= '<tr><td>'.Lang::npc('pvpLeader').'</td></tr>';

        # Quest Tracking Info #

        // unsure, if should be displayed
        // QUEST_TOOLTIP_REQUIREMENTS
        // QuestTitle cssClass: 'q'
        // " - ItemName" from creature_questitem
        // QUEST_OBJECTS_FOUND_NOPROGRESS
        // QUEST_MONSTERS_KILLED_NOPROGRESS
        // QUEST_ITEMS_NEEDED_NOPROGRESS

        $x .= '</table>';

        # Health Bar #

        // no!

        return $x;
    }

    public function getRandomModelId() : int
    {
        // dwarf?? [null, 30754, 30753, 30755, 30736]
        // totems use hardcoded models, tauren model is base
        $totems = [null, 4589, 4588, 4587, 4590];           // slot => modelId
        $data   = [];

        for ($i = 1; $i < 5; $i++)
            if ($_ = $this->curTpl['displayId'.$i])
                $data[] = $_;

        if (count($data) == 1 && ($slotId = array_search($data[0], $totems)))
            $data = DB::World()->selectCol('SELECT `DisplayId` FROM player_totem_model WHERE `TotemSlot` = %i', $slotId);

        return !$data ? 0 : $data[array_rand($data)];
    }

    public function getBaseStats(string $type) : array
    {
        // Creature::CalculateMinMaxDamage(): BaseVariance / RangeVariance scale the attack power part of melee / ranged damage
        switch ($type)
        {
            case 'health':
                $hMin = $this->getField('healthMin');
                $hMax = $this->getField('healthMax');
                return [$hMin, $hMax];
            case 'power':
                $mMin = $this->getField('manaMin');
                $mMax = $this->getField('manaMax');
                return [$mMin, $mMax];
            case 'armor':
                $aMin = $this->getField('armorMin');
                $aMax = $this->getField('armorMax');
                return [$aMin, $aMax];
            case 'melee':
                $mleMin = ($this->getField('dmgMin')       + ($this->getField('mleAtkPwrMin') / 14) * $this->getField('mleVariance')) * $this->getField('dmgMultiplier') * $this->getField('atkSpeed');
                $mleMax = ($this->getField('dmgMax') * 1.5 + ($this->getField('mleAtkPwrMax') / 14) * $this->getField('mleVariance')) * $this->getField('dmgMultiplier') * $this->getField('atkSpeed');
                return [$mleMin, $mleMax];
            case 'ranged':
                $rngMin = ($this->getField('dmgMin')       + ($this->getField('rngAtkPwrMin') / 14) * $this->getField('rngVariance')) * $this->getField('dmgMultiplier') * $this->getField('rngAtkSpeed');
                $rngMax = ($this->getField('dmgMax') * 1.5 + ($this->getField('rngAtkPwrMax') / 14) * $this->getField('rngVariance')) * $this->getField('dmgMultiplier') * $this->getField('rngAtkSpeed');
                return [$rngMin, $rngMax];
            case 'resistance':
                $r = [];
                for ($i = SPELL_SCHOOL_HOLY; $i < SPELL_SCHOOL_ARCANE+1; $i++)
                    $r[$i] = $this->getField('resistance'.$i);

                return $r;
            default:
                return [];
        }
    }

    public function isBoss() : bool
    {
        return ($this->curTpl['cuFlags'] & NPC_CU_INSTANCE_BOSS) || ($this->curTpl['typeFlags'] & NPC_TYPEFLAG_BOSS_MOB && $this->curTpl['rank']);
    }

    public function isMineable() : bool
    {
        return $this->curTpl['skinLootId'] && ($this->curTpl['typeFlags'] & NPC_TYPEFLAG_SKIN_WITH_MINING);
    }

    public function isGatherable() : bool
    {
        return $this->curTpl['skinLootId'] && ($this->curTpl['typeFlags'] & NPC_TYPEFLAG_SKIN_WITH_HERBALISM);
    }

    public function isSalvageable() : bool
    {
        return $this->curTpl['skinLootId'] && ($this->curTpl['typeFlags'] & NPC_TYPEFLAG_SKIN_WITH_ENGINEERING);
    }

    public function getTameable() : ?int
    {
        if (!($this->curTpl['typeFlags'] & NPC_TYPEFLAG_TAMEABLE) || $this->curTpl['type'] != NPC_TYPE_BEAST)
            return null;

        return $this->curTpl['family'] ?: null;
    }

    /**
     * * `0x0010 - LISTVIEWINFO_MODEL`:
     * * `0x0020 - LISTVIEWINFO_TAMEABLE`: include texture
     * * `0x0040 - LISTVIEWINFO_REPUTATION`: include repreward
     */
    public function getListviewData(int $addInfoMask = 0x0) : array
    {
        $data   = [];
        $rewRep = [];

        if ($addInfoMask & LISTVIEWINFO_REPUTATION && $this->getFoundIDs())
        {
            $rewRep = DB::World()->selectCol(
               'SELECT `creature_id` AS ARRAY_KEY, `RewOnKillRepFaction1` AS ARRAY_KEY2, `RewOnKillRepValue1` FROM creature_onkill_reputation WHERE `creature_id` IN %in AND `RewOnKillRepFaction1` > 0 UNION
                SELECT `creature_id` AS ARRAY_KEY, `RewOnKillRepFaction2` AS ARRAY_KEY2, `RewOnKillRepValue2` FROM creature_onkill_reputation WHERE `creature_id` IN %in AND `RewOnKillRepFaction2` > 0',
                $this->getFoundIDs(),
                $this->getFoundIDs()
            );
        }


        foreach ($this->iterate() as $__)
        {
            if ($addInfoMask & LISTVIEWINFO_MODEL)
            {
                $texStr = strtolower($this->curTpl['textureString']);

                if (isset($data[$texStr]))
                {
                    if ($data[$texStr]['minLevel'] > $this->curTpl['minLevel'])
                        $data[$texStr]['minLevel'] = $this->curTpl['minLevel'];

                    if ($data[$texStr]['maxLevel'] < $this->curTpl['maxLevel'])
                        $data[$texStr]['maxLevel'] = $this->curTpl['maxLevel'];

                    $data[$texStr]['count']++;
                }
                else
                    $data[$texStr] = array(
                        'family'    => $this->curTpl['family'],
                        'minLevel'  => $this->curTpl['minLevel'],
                        'maxLevel'  => $this->curTpl['maxLevel'],
                        'modelId'   => $this->curTpl['modelId'],
                        'displayId' => $this->curTpl['displayId1'],
                        'skin'      => $texStr,
                        'count'     => 1
                    );
            }
            else
            {
                if ($location = $this->getSpawns(SPAWNINFO_ZONES))
                    if (count($location) > 3)
                        array_splice($location, 3, replacement: -1);

                $data[$this->id] = array(
                    'family'         => $this->curTpl['family'],
                    'minlevel'       => $this->curTpl['minLevel'],
                    'maxlevel'       => $this->curTpl['maxLevel'],
                    'id'             => $this->id,
                    'boss'           => $this->isBoss() ? 1 : 0,
                    'classification' => $this->curTpl['rank'],
                    'location'       => $location,
                    'name'           => $this->getField('name', true),
                    'type'           => $this->curTpl['type'],
                    'react'          => [$this->curTpl['A'], $this->curTpl['H']],
                );


                if ($this->getField('startsQuests'))
                    $data[$this->id]['hasQuests'] = 1;

                if ($_ = $this->getModes())          // aowow - custom
                    $data[$this->id]['modes'] = $_;

                if ($_ = $this->getField('subname', true))
                    $data[$this->id]['tag'] = $_;

                if ($addInfoMask & LISTVIEWINFO_TAMEABLE)   // only first skin of first model ... we're omitting potentially 11 skins here .. but the lv accepts only one .. w/e
                    $data[$this->id]['skin'] = $this->curTpl['textureString'];

                if ($addInfoMask & LISTVIEWINFO_REPUTATION)
                {
                    $data[$this->id]['reprewards'] = [];
                    if ($rewRep[$this->id])
                        foreach ($rewRep[$this->id] as $fac => $val)
                            $data[$this->id]['reprewards'][] = [$fac, $val];
                }
            }
        }

        ksort($data);
        return $data;
    }

    // aowow - custom start: difficulty modes of the members of a difficulty set
    /** @return array|null [kind, modeMask] or null if the npc isn't part of a difficulty set */
    public function getModes() : ?array
    {
        if (($this->curTpl['modeKind'] ?? null) === null)
            return null;

        return [(int)$this->curTpl['modeKind'], (int)$this->curTpl['modeMask']];
    }

    /** @return array [difficulty => [short, long, cssClass]] */
    public static function modeLabels(int $kind, int $modeMask) : array
    {
        $result = [];
        for ($i = 0; $i < 4; $i++)
        {
            if (!($modeMask & (1 << $i)) || !($short = Lang::exist('npcExtra', 'modePills', $kind, $i)))
                continue;

            $class = match (true)
            {
                $kind == self::MODE_KIND_UNKNOWN && $i => 'u',
                $kind == self::MODE_KIND_DUNGEON && $i,
                $kind == self::MODE_KIND_RAID && $i > 1 => 'h',
                default                                 => 'n'
            };

            $result[$i] = [$short, Lang::game('modes', $kind, $i), $class];
        }

        return $result;
    }

    public static function modePills(int $kind, int $modeMask) : string
    {
        $buff = '';
        foreach (self::modeLabels($kind, $modeMask) as [$short, $long, $class])
            $buff .= ' <span class="mode-pill mode-pill-'.$class.'" title="'.Util::htmlEscape($long).'">'.Util::htmlEscape($short).'</span>';

        return $buff;
    }
    // aowow - custom end

    public function getJSGlobals(int $addMask = 0) : array
    {
        $data = [];

        foreach ($this->iterate() as $__)
            $data[Type::NPC][$this->id] = ['name' => $this->getField('name', true)];

        return $data;
    }

    public function getSourceData(int $id = 0) : array
    {
        $data = [];

        foreach ($this->iterate() as $__)
        {
            if ($id && $id != $this->id)
                continue;

            $data[$this->id] = array(
                'n'  => $this->getField('parentId') ? $this->getField('parent', true) : $this->getField('name', true),
                't'  => Type::NPC,
                'ti' => $this->getField('parentId') ?: $this->id
            );
        }

        return $data;
    }
}


class CreatureListFilter extends Filter
{
    protected string $type  = 'npcs';
    protected static array $enums = array(
         3 => parent::ENUM_FACTION,                         // faction
         6 => parent::ENUM_ZONE,                            // foundin
        42 => parent::ENUM_FACTION,                         // increasesrepwith
        43 => parent::ENUM_FACTION,                         // decreasesrepwith
        38 => parent::ENUM_EVENT,                           // relatedevent
        // aowow - custom start: VisibilityDistanceType of `creature_template_addon`, unit_class
       106 => [0, 1, 2, 3, 4, 5],                              // visibilityrange
       107 => [ChrClass::WARRIOR->value, ChrClass::PALADIN->value, ChrClass::ROGUE->value, ChrClass::MAGE->value], // npcclass: CLASSMASK_ALL_CREATURES
       113 => [SPELL_SCHOOL_NORMAL, SPELL_SCHOOL_HOLY, SPELL_SCHOOL_FIRE, SPELL_SCHOOL_NATURE, SPELL_SCHOOL_FROST, SPELL_SCHOOL_SHADOW, SPELL_SCHOOL_ARCANE], // meleeschool
       143 => [1, 2, 3, 4, 5, 6]                               // npcmode: keys of MODE_FILTER
        // aowow - custom end
    );

    protected static array $genericFilter = array(
         1 => [parent::CR_CALLBACK, 'cbHealthMana',      'healthMax',                       'healthMin'], // health [num]
         2 => [parent::CR_CALLBACK, 'cbHealthMana',      'manaMin',                         'manaMax'  ], // mana [num]
         3 => [parent::CR_CALLBACK, 'cbFaction',         null,                               null      ], // faction [enum]
         5 => [parent::CR_FLAG,     'npcflag',           NPC_FLAG_REPAIRER                             ], // canrepair
         6 => [parent::CR_ENUM,     's.areaId',          false,                              true      ], // foundin
         7 => [parent::CR_CALLBACK, 'cbQuestRelation',   'startsQuests',                     0x1       ], // startsquest [enum]
         8 => [parent::CR_CALLBACK, 'cbQuestRelation',   'endsQuests',                       0x2       ], // endsquest [enum]
         9 => [parent::CR_BOOLEAN,  'lootId',                                                          ], // lootable
        10 => [parent::CR_CALLBACK, 'cbRegularSkinLoot', NPC_TYPEFLAG_SPECIALLOOT                      ], // skinnable [yn]
        11 => [parent::CR_BOOLEAN,  'pickpocketLootId',                                                ], // pickpocketable
        12 => [parent::CR_CALLBACK, 'cbMoneyDrop',       null,                               null      ], // averagemoneydropped [op] [int]
        13 => [parent::CR_FLAG,     'npcflag',           NPC_FLAG_GOSSIP                               ], // gossip
        15 => [parent::CR_CALLBACK, 'cbSpecialSkinLoot', NPC_TYPEFLAG_SKIN_WITH_HERBALISM,   null      ], // gatherable [yn]
        16 => [parent::CR_CALLBACK, 'cbSpecialSkinLoot', NPC_TYPEFLAG_SKIN_WITH_MINING,      null      ], // minable [yn]
        18 => [parent::CR_FLAG,     'npcflag',           NPC_FLAG_AUCTIONEER                           ], // auctioneer
        19 => [parent::CR_FLAG,     'npcflag',           NPC_FLAG_BANKER                               ], // banker
        20 => [parent::CR_FLAG,     'npcflag',           NPC_FLAG_BATTLEMASTER                         ], // battlemaster
        21 => [parent::CR_FLAG,     'npcflag',           NPC_FLAG_FLIGHT_MASTER                        ], // flightmaster
        22 => [parent::CR_FLAG,     'npcflag',           NPC_FLAG_GUILD_MASTER                         ], // guildmaster
        23 => [parent::CR_FLAG,     'npcflag',           NPC_FLAG_INNKEEPER                            ], // innkeeper
        24 => [parent::CR_FLAG,     'npcflag',           NPC_FLAG_CLASS_TRAINER                        ], // talentunlearner
        25 => [parent::CR_FLAG,     'npcflag',           NPC_FLAG_GUILD_MASTER                         ], // tabardvendor
        26 => [parent::CR_FLAG,     'npcflag',           NPC_FLAG_QUEST_GIVER                          ], // questgiver
        27 => [parent::CR_FLAG,     'npcflag',           NPC_FLAG_STABLE_MASTER                        ], // stablemaster
        28 => [parent::CR_FLAG,     'npcflag',           NPC_FLAG_TRAINER                              ], // trainer
        29 => [parent::CR_FLAG,     'npcflag',           NPC_FLAG_VENDOR                               ], // vendor
        31 => [parent::CR_FLAG,     'cuFlags',           CUSTOM_HAS_SCREENSHOT                         ], // hasscreenshots
        32 => [parent::CR_FLAG,     'cuFlags',           NPC_CU_INSTANCE_BOSS                          ], // instanceboss
        33 => [parent::CR_FLAG,     'cuFlags',           CUSTOM_HAS_COMMENT                            ], // hascomments
        34 => [parent::CR_NUMSTRING,'modelId',           NUM_CAST_INT                                  ], // usemodel [str]
        35 => [parent::CR_STRING,   'textureString'                                                    ], // useskin [str]
        36 => [parent::CR_FLAG,     'npcflag',           NPC_PROFESSION_TRAINER                        ], // professiontrainer
        37 => [parent::CR_NUMERIC,  'id',                NUM_CAST_INT,                       true      ], // id
        38 => [parent::CR_CALLBACK, 'cbRelEvent',        null,                               null      ], // relatedevent [enum]
        39 => [parent::CR_FLAG,     'npcflag',           NPC_FLAG_SPIRIT_HEALER                        ], // spirithealer
        40 => [parent::CR_FLAG,     'cuFlags',           CUSTOM_HAS_VIDEO                              ], // hasvideos
        41 => [parent::CR_CALLBACK, 'cbHasLocation'                                                    ], // haslocation [yn] [staff]
        42 => [parent::CR_CALLBACK, 'cbReputation',      '>',                                null      ], // increasesrepwith [enum]
        43 => [parent::CR_CALLBACK, 'cbReputation',      '<',                                null      ], // decreasesrepwith [enum]
        44 => [parent::CR_CALLBACK, 'cbSpecialSkinLoot', NPC_TYPEFLAG_SKIN_WITH_ENGINEERING, null      ], // salvageable [yn]
        45 => [parent::CR_FLAG,     'npcflag',           NPC_FLAG_SPIRIT_GUIDE                         ], // spiritguide
        46 => [parent::CR_FLAG,     'npcflag',           NPC_FLAG_VENDOR_AMMO                          ], // ammovendor
        47 => [parent::CR_FLAG,     'npcflag',           NPC_FLAG_VENDOR_FOOD                          ], // foodvendor
        48 => [parent::CR_FLAG,     'npcflag',           NPC_FLAG_VENDOR_POISON                        ], // poisonvendor
        49 => [parent::CR_FLAG,     'npcflag',           NPC_FLAG_VENDOR_REAGENT                       ], // reagentvendor
        50 => [parent::CR_FLAG,     'npcflag',           NPC_FLAG_PETITIONER                           ], // petitioner
        51 => [parent::CR_FLAG,     'npcflag',           NPC_FLAG_GUILD_BANK                           ], // guildbank
        52 => [parent::CR_FLAG,     'npcflag',           NPC_FLAG_SPELLCLICK                           ], // spellclick
        53 => [parent::CR_FLAG,     'npcflag',           NPC_FLAG_MAILBOX                              ], // mailbox
        // aowow - custom start: flags that are shown on the npc page but upstream does not let you filter by
        54 => [parent::CR_FLAG,     'flagsExtra',        CREATURE_FLAG_EXTRA_INSTANCE_BIND             ], // npcinstancebind
        55 => [parent::CR_FLAG,     'flagsExtra',        CREATURE_FLAG_EXTRA_CIVILIAN                  ], // npccivilian
        56 => [parent::CR_FLAG,     'flagsExtra',        CREATURE_FLAG_EXTRA_NO_PARRY                  ], // npcnoparry
        57 => [parent::CR_FLAG,     'flagsExtra',        CREATURE_FLAG_EXTRA_NO_PARRY_HASTEN           ], // npcnoparryhaste
        58 => [parent::CR_FLAG,     'flagsExtra',        CREATURE_FLAG_EXTRA_NO_BLOCK                  ], // npcnoblock
        59 => [parent::CR_FLAG,     'flagsExtra',        CREATURE_FLAG_EXTRA_NO_CRUSHING_BLOWS         ], // npcnocrushing
        60 => [parent::CR_FLAG,     'flagsExtra',        CREATURE_FLAG_EXTRA_NO_XP                     ], // npcnoxp
        61 => [parent::CR_FLAG,     'flagsExtra',        CREATURE_FLAG_EXTRA_TRIGGER                   ], // npctrigger
        62 => [parent::CR_FLAG,     'flagsExtra',        CREATURE_FLAG_EXTRA_NO_TAUNT                  ], // npcnotaunt
        63 => [parent::CR_FLAG,     'flagsExtra',        CREATURE_FLAG_EXTRA_GHOST_VISIBILITY          ], // npcspiritvisible
        64 => [parent::CR_FLAG,     'flagsExtra',        CREATURE_FLAG_EXTRA_USE_OFFHAND_ATTACK        ], // npcoffhand
        65 => [parent::CR_FLAG,     'flagsExtra',        CREATURE_FLAG_EXTRA_NO_SELL_VENDOR            ], // npcnoselltovendor
        66 => [parent::CR_FLAG,     'flagsExtra',        CREATURE_FLAG_EXTRA_IGNORE_COMBAT             ], // npcignorecombat
        67 => [parent::CR_FLAG,     'flagsExtra',        CREATURE_FLAG_EXTRA_WORLDEVENT                ], // npcworldevent
        68 => [parent::CR_FLAG,     'flagsExtra',        CREATURE_FLAG_EXTRA_GUARD                     ], // npcguard
        69 => [parent::CR_FLAG,     'flagsExtra',        CREATURE_FLAG_EXTRA_IGNORE_FEIGN_DEATH        ], // npcignorefeign
        70 => [parent::CR_FLAG,     'flagsExtra',        CREATURE_FLAG_EXTRA_NO_CRIT                   ], // npcnocrit
        71 => [parent::CR_FLAG,     'flagsExtra',        CREATURE_FLAG_EXTRA_NO_SKILL_GAINS            ], // npcnoskillgains
        72 => [parent::CR_FLAG,     'flagsExtra',        CREATURE_FLAG_EXTRA_OBEYS_TAUNT_DIMINISHING_RETURNS], // npctauntdr
        73 => [parent::CR_FLAG,     'flagsExtra',        CREATURE_FLAG_EXTRA_ALL_DIMINISH              ], // npcalldiminish
        74 => [parent::CR_FLAG,     'flagsExtra',        CREATURE_FLAG_EXTRA_NO_PLAYER_DAMAGE_REQ      ], // npcnodamagereq
        75 => [parent::CR_FLAG,     'flagsExtra',        CREATURE_FLAG_EXTRA_IGNORE_PATHFINDING        ], // npcignorepath
        76 => [parent::CR_FLAG,     'flagsExtra',        CREATURE_FLAG_EXTRA_IMMUNITY_KNOCKBACK        ], // npcknockbackimmune
        77 => [parent::CR_FLAG,     'unitFlags',         UNIT_FLAG_NON_ATTACKABLE | UNIT_FLAG_IMMUNE_TO_PC, true], // npcunattackable
        78 => [parent::CR_FLAG,     'unitFlags',         UNIT_FLAG_IMMUNE_TO_NPC                       ], // npcimmunenpc
        79 => [parent::CR_FLAG,     'unitFlags',         UNIT_FLAG_UNINTERACTIBLE                      ], // npcuntargetable
        80 => [parent::CR_FLAG,     'unitFlags',         UNIT_FLAG_CANNOT_SWIM                         ], // npccannotswim
        81 => [parent::CR_FLAG,     'unitFlags2',        UNIT_FLAG2_HIDE_BODY                          ], // npchidebody
        82 => [parent::CR_FLAG,     'unitFlags2',        UNIT_FLAG2_DO_NOT_FADE_IN                     ], // npcnofadein
        83 => [parent::CR_FLAG,     'unitFlags2',        UNIT_FLAG2_CANNOT_TURN                        ], // npccannotturn
        84 => [parent::CR_FLAG,     'typeFlags',         NPC_TYPEFLAG_VISIBLE_TO_GHOSTS                ], // npcvisibleghosts
        85 => [parent::CR_FLAG,     'typeFlags',         NPC_TYPEFLAG_SPELL_ATTACKABLE                 ], // npcspellattackable
        86 => [parent::CR_FLAG,     'typeFlags',         NPC_TYPEFLAG_INTERACT_WHILE_DEAD              ], // npcinteractdead
        87 => [parent::CR_FLAG,     'typeFlags',         NPC_TYPEFLAG_ALLOW_MOUNTED_COMBAT             ], // npcmountedcombat
        88 => [parent::CR_FLAG,     'typeFlags',         NPC_TYPEFLAG_CAN_ASSIST                       ], // npccanassist
        89 => [parent::CR_FLAG,     'typeFlags',         NPC_TYPEFLAG_NO_PET_BAR                       ], // npcnopetbar
        90 => [parent::CR_FLAG,     'typeFlags',         NPC_TYPEFLAG_ALLOW_INTERACTION_WHILE_IN_COMBAT], // npcinteractcombat
        91 => [parent::CR_FLAG,     'typeFlags',         NPC_TYPEFLAG_INTERACT_ONLY_WITH_CREATOR       ], // npccreatoronly
        92 => [parent::CR_FLAG,     'typeFlags',         NPC_TYPEFLAG_TREAT_AS_RAID_UNIT               ], // npcraidunit
        93 => [parent::CR_FLAG,     'typeFlags',         NPC_TYPEFLAG_FORCE_GOSSIP                     ], // npcforcegossip
        94 => [parent::CR_FLAG,     'typeFlags',         NPC_TYPEFLAG_DO_NOT_PLAY_WOUND_ANIM           ], // npcnowoundanim
        95 => [parent::CR_FLAG,     'typeFlags',         NPC_TYPEFLAG_NO_FACTION_TOOLTIP               ], // npcnofactiontip
        96 => [parent::CR_FLAG,     'typeFlags',         NPC_TYPEFLAG_MORE_AUDIBLE                     ], // npcmoreaudible
        97 => [parent::CR_FLAG,     'typeFlags',         NPC_TYPEFLAG_NO_DEATH_MESSAGE                 ], // npcnodeathmsg
        98 => [parent::CR_FLAG,     'typeFlags',         NPC_TYPEFLAG_USE_MODEL_COLLISION_SIZE         ], // npcmodelcollision
        99 => [parent::CR_FLAG,     'typeFlags',         NPC_TYPEFLAG_COLLIDE_WITH_MISSILES            ], // npcmissilecollide
        100 => [parent::CR_FLAG,    'typeFlags',         NPC_TYPEFLAG_NO_NAME_PLATE                    ], // npcnonameplate
        101 => [parent::CR_FLAG,    'typeFlags',         NPC_TYPEFLAG_LINK_ALL                         ], // npclinkall
        102 => [parent::CR_FLAG,    'typeFlags',         NPC_TYPEFLAG_DO_NOT_PLAY_UNIT_EVENT_SOUNDS    ], // npcnoeventsounds
        103 => [parent::CR_FLAG,    'typeFlags',         NPC_TYPEFLAG_HAS_NO_SHADOW_BLOB               ], // npcnoshadow
        104 => [parent::CR_FLAG,    'typeFlags',         NPC_TYPEFLAG_DO_NOT_SHEATHE                   ], // npcnosheathe
        106 => [parent::CR_CALLBACK, 'cbVisibilityRange', null,                              null      ], // visibilityrange [enum]
        107 => [parent::CR_ENUM,    'unitClass',         false,                              true      ], // npcclass [enum]
        108 => [parent::CR_FLAG,    'typeFlags',         NPC_TYPEFLAG_BOSS_MOB                         ], // npcbossmob
        109 => [parent::CR_CALLBACK, 'cbImmuneMask',     'mechanicImmuneMask',              1         ], // mechanicimmune [enum]
        110 => [parent::CR_CALLBACK, 'cbImmuneMask',     'schoolImmuneMask',                0         ], // schoolimmune [enum]
        111 => [parent::CR_BOOLEAN, 'vehicleId'                                                        ], // isvehicle
        112 => [parent::CR_BOOLEAN, 'racialLeader'                                                     ], // racialleader
        113 => [parent::CR_ENUM,    'dmgSchool',         false,                              true      ], // meleeschool [enum]
        114 => [parent::CR_NUMERIC, 'resistance1',       NUM_CAST_INT                                  ], // holres [num]
        115 => [parent::CR_NUMERIC, 'resistance2',       NUM_CAST_INT                                  ], // firres [num]
        116 => [parent::CR_NUMERIC, 'resistance3',       NUM_CAST_INT                                  ], // natres [num]
        117 => [parent::CR_NUMERIC, 'resistance4',       NUM_CAST_INT                                  ], // frores [num]
        118 => [parent::CR_NUMERIC, 'resistance5',       NUM_CAST_INT                                  ], // shares [num]
        119 => [parent::CR_NUMERIC, 'resistance6',       NUM_CAST_INT                                  ], // arcres [num]
        120 => [parent::CR_CALLBACK, 'cbHealthMana',     'armorMin',                        'armorMax' ], // armor [num]
        121 => [parent::CR_CALLBACK, 'cbMeleeDamage',    false,                              null      ], // meleemin [num]
        122 => [parent::CR_CALLBACK, 'cbMeleeDamage',    true,                               null      ], // meleemax [num]
        123 => [parent::CR_CALLBACK, 'cbStaffString',    'ScriptOrAI',                       null      ], // scriptname [str] [staff]
        124 => [parent::CR_CALLBACK, 'cbStaffString',    'StringId',                         null      ], // stringid [str] [staff]
        126 => [parent::CR_CALLBACK, 'cbTemplateNum',    '(2.5 * `speed_walk`)',             null      ], // walkspeed [num]
        127 => [parent::CR_CALLBACK, 'cbTemplateNum',    '(7.0 * `speed_run`)',              null      ], // runspeed [num]
        128 => [parent::CR_CALLBACK, 'cbTemplateNum',    '`scale`',                          null      ], // scale [num]
        129 => [parent::CR_CALLBACK, 'cbTemplateNum',    '`HoverHeight`',                    null      ], // hoverheight [num]
        130 => [parent::CR_CALLBACK, 'cbTemplateNum',    '`HealthModifier`',                 null      ], // healthmod [num]
        131 => [parent::CR_CALLBACK, 'cbTemplateNum',    '`ManaModifier`',                   null      ], // manamod [num]
        132 => [parent::CR_CALLBACK, 'cbTemplateNum',    '`ArmorModifier`',                  null      ], // armormod [num]
        133 => [parent::CR_NUMERIC, 'dmgMultiplier',     NUM_CAST_FLOAT                                ], // damagemod [num]
        134 => [parent::CR_CALLBACK, 'cbTemplateNum',    '`ExperienceModifier`',             null      ], // xpmod [num]
        135 => [parent::CR_CALLBACK, 'cbWorldIds',       'regen',                            null      ], // regenhealth [yn]
        136 => [parent::CR_CALLBACK, 'cbWorldIds',       'phased',                           null      ], // phased [yn]
        137 => [parent::CR_CALLBACK, 'cbEquipsItem',     null,                               null      ], // equipsitem [str]
        138 => [parent::CR_CALLBACK, 'cbWorldIds',       'follower',                         null      ], // formationfollower [yn]
        139 => [parent::CR_CALLBACK, 'cbWorldIds',       'leader',                           null      ], // formationleader [yn]
        140 => [parent::CR_CALLBACK, 'cbWorldIds',       'mounted',                          null      ], // ridesmount [yn]
        141 => [parent::CR_CALLBACK, 'cbWorldIds',       'mount',                            null      ], // usedasmount [yn]
        142 => [parent::CR_CALLBACK, 'cbWorldIds',       'questitems',                       null      ], // hasquestitems [yn]
        143 => [parent::CR_CALLBACK, 'cbMode',            null,                              null      ], // npcmode [enum]
        144 => [parent::CR_CALLBACK, 'cbDifficultySlot',  false,                             null      ], // hasdifficultyversions [yn]
        145 => [parent::CR_FLAG,     'cuFlags',           NPC_CU_DIFFICULTY_DUMMY                      ], // isdifficultyversion [yn] [staff]
        // creature_template_movement values that depart from the default
        146 => [parent::CR_CALLBACK, 'cbMovement',        'Ground',                          0         ], // npcnoground [yn]
        147 => [parent::CR_CALLBACK, 'cbMovement',        'Ground',                          2         ], // npchovers [yn]
        148 => [parent::CR_CALLBACK, 'cbMovement',        'Swim',                            0         ], // npcnoswim [yn]
        149 => [parent::CR_CALLBACK, 'cbMovement',        'Flight',                          1         ], // npcdisablegravity [yn]
        150 => [parent::CR_CALLBACK, 'cbMovement',        'Flight',                          2         ], // npcflies [yn]
        151 => [parent::CR_CALLBACK, 'cbMovement',        'Rooted',                          1         ], // npcrooted [yn]
        152 => [parent::CR_CALLBACK, 'cbMovement',        'Chase',                           1         ], // npcchasemaywalk [yn]
        153 => [parent::CR_CALLBACK, 'cbMovement',        'Chase',                           2         ], // npcchasewalks [yn]
        154 => [parent::CR_CALLBACK, 'cbMovement',        'Random',                          1         ], // npcidlemayrun [yn]
        155 => [parent::CR_CALLBACK, 'cbMovement',        'Random',                          2         ], // npcidleruns [yn]
        156 => [parent::CR_FLAG,     'dynamicFlags',      0x20                                         ], // npcappearsdead [yn] - UNIT_DYNFLAG_DEAD
        157 => [parent::CR_FLAG,     'dynamicFlags',      0x02                                         ], // npctracked [yn] - UNIT_DYNFLAG_TRACK_UNIT
        158 => [parent::CR_CALLBACK, 'cbCursor',          null,                              null      ], // npccursor [enum] - ::cursors
        159 => [parent::CR_CALLBACK, 'cbWorldIds',        'pause',                           null      ], // npcinteractionpause [yn]
        160 => [parent::CR_CALLBACK, 'cbWorldIds',        'sheath',                          null      ], // npcweaponsdrawn [yn]
        161 => [parent::CR_FLAG,     'pvpFlags',          UNIT_BYTE2_FLAG_FFA_PVP                      ], // npcffapvp [yn]
        162 => [parent::CR_FLAG,     'pvpFlags',          UNIT_BYTE2_FLAG_SANCTUARY                    ], // npcsanctuary [yn]
        163 => [parent::CR_CALLBACK, 'cbWorldIds',        'animtier',                        null      ], // npcanimtier [yn]
        164 => [parent::CR_CALLBACK, 'cbWorldIds',        'untrackable',                     null      ], // npcuntrackable [yn]
        165 => [parent::CR_BOOLEAN,  'petSpellDataId'                                                  ], // npcpetbar [yn]
        166 => [parent::CR_NUMERIC,  'mleVariance',       NUM_CAST_FLOAT                               ], // meleevariance [num]
        167 => [parent::CR_NUMERIC,  'rngVariance',       NUM_CAST_FLOAT                               ]  // rangedvariance [num]
        // aowow - custom end
    );

    protected static array $inputFields = array(
        'cr'    => [parent::V_LIST,     [[1, 3],[5, 13], 15, 16, [18, 29], [31, 104], [106, 124], [126, 167]],       true ], // criteria ids
        'crs'   => [parent::V_LIST,     [parent::ENUM_NONE, parent::ENUM_ANY, [0, 9999]],                         true ], // criteria operators
        'crv'   => [parent::V_REGEX,    parent::PATTERN_CRV,                                                      true ], // criteria values - only printable chars, no delimiter
        'na'    => [parent::V_NAME,     false,                                                                    false], // name / subname - only printable chars, no delimiter
        'ex'    => [parent::V_EQUAL,    'on',                                                                     false], // also match subname
        'ma'    => [parent::V_EQUAL,    1,                                                                        false], // match any / all filter
        'fa'    => [parent::V_CALLBACK, 'cbPetFamily',                                                            true ], // pet family [list]  -  cat[0] == 1
        'minle' => [parent::V_RANGE,    [0, 99],                                                                  false], // min level [int]
        'maxle' => [parent::V_RANGE,    [0, 99],                                                                  false], // max level [int]
        'cl'    => [parent::V_RANGE,    [0, 4],                                                                   true ], // classification [list]
        'ra'    => [parent::V_LIST,     [-1, 0, 1],                                                               false], // react alliance [int]
        'rh'    => [parent::V_LIST,     [-1, 0, 1],                                                               false]  // react horde [int]
    );

    public array $extraOpts = [];

    protected function createSQLForValues() : array
    {
        $parts = [];
        $_v    = &$this->values;

        // name [str]
        if ($_v['na'])
        {
            $f = [['na', ['nml.nName', 'nml.nSubname']]];
            if ($_v['ex'] != 'on')
                $f = [['na', 'nml.nName']];

            if ($_ = $this->buildMatchLookup($f))
                $parts[] = $_;
            else
            {
                $f = [['na', 'name_loc'.Lang::getLocale()->value], ['na', 'subname_loc'.Lang::getLocale()->value]];
                if ($_v['ex'] != 'on')
                    $f = [$f[0]];

                if ($_ = $this->buildLikeLookup($f))
                    $parts[] = $_;
            }
        }

        // pet family [list]
        if ($_v['fa'])
            $parts[] = ['family', $_v['fa']];

        // creatureLevel min [int]
        if ($_v['minle'])
            $parts[] = ['minLevel', $_v['minle'], '>='];

        // creatureLevel max [int]
        if ($_v['maxle'])
            $parts[] = ['maxLevel', $_v['maxle'], '<='];

        // classification [list]
        if ($_v['cl'])
            $parts[] = ['rank', $_v['cl']];

        // react Alliance [int]
        if (!is_null($_v['ra']))
            $parts[] = ['ft.A', $_v['ra']];

        // react Horde [int]
        if (!is_null($_v['rh']))
            $parts[] = ['ft.H', $_v['rh']];

        return $parts;
    }

    protected function cbPetFamily(string &$val) : bool
    {
        if (!$this->parentCats || $this->parentCats[0] != 1)
            return false;

        if (!Util::checkNumeric($val, NUM_CAST_INT))
            return false;

        $type  = parent::V_LIST;
        $valid = [[1, 9], 11, 12, 20, 21, [24, 27], [30, 35], [37, 39], [41, 46]];

        return $this->checkInput($type, $valid, $val);
    }

    protected function cbRelEvent(int $cr, int $crs, string $crv) : ?array
    {
        if ($crs == parent::ENUM_ANY)
        {
            if ($eventIds = DB::Aowow()->selectCol('SELECT `id` FROM ::events WHERE `holidayId` <> 0'))
                if ($cGuids   = DB::World()->selectCol('SELECT DISTINCT `guid` FROM game_event_creature WHERE `eventEntry` IN %in', $eventIds))
                    return ['s.guid', $cGuids];

            return [0];
        }
        else if ($crs == parent::ENUM_NONE)
        {
            if ($eventIds = DB::Aowow()->selectCol('SELECT `id` FROM ::events WHERE `holidayId` <> 0'))
                if ($cGuids   = DB::World()->selectCol('SELECT DISTINCT `guid` FROM game_event_creature WHERE `eventEntry` IN %in', $eventIds))
                    return [DB::OR, ['s.guid', $cGuids, '!'], ['s.guid', null]];

            return [0];
        }
        else if (in_array($crs, self::$enums[$cr]))
        {
            if ($eventIds = DB::Aowow()->selectCol('SELECT `id` FROM ::events WHERE `holidayId` = %i', $crs))
                if ($cGuids   = DB::World()->selectCol('SELECT DISTINCT `guid` FROM `game_event_creature` WHERE `eventEntry` IN %in', $eventIds))
                    return ['s.guid', $cGuids];

            return [0];
        }

        return null;
    }

    protected function cbMoneyDrop(int $cr, int $crs, string $crv) : ?array
    {
        if (!Util::checkNumeric($crv, NUM_CAST_INT) || !$this->int2Op($crs))
            return null;

        return [DB::AND, ['((minGold + maxGold) / 2)', $crv, $crs]];
    }

    protected function cbQuestRelation(int $cr, int $crs, string $crv, $field, $val) : ?array
    {
        switch ($crs)
        {
            case 1:                                 // any
                return [DB::AND, ['qse.method', $val, '&'], ['qse.questId', null, '!']];
            case 2:                                 // alliance
                return [DB::AND, ['qse.method', $val, '&'], ['qse.questId', null, '!'], [['qt.reqRaceMask', ChrRace::MASK_HORDE, '&'], 0], ['qt.reqRaceMask', ChrRace::MASK_ALLIANCE, '&']];
            case 3:                                 // horde
                return [DB::AND, ['qse.method', $val, '&'], ['qse.questId', null, '!'], [['qt.reqRaceMask', ChrRace::MASK_ALLIANCE, '&'], 0], ['qt.reqRaceMask', ChrRace::MASK_HORDE, '&']];
            case 4:                                 // both
                return [DB::AND, ['qse.method', $val, '&'], ['qse.questId', null, '!'], [DB::OR, [DB::AND, ['qt.reqRaceMask', ChrRace::MASK_ALLIANCE, '&'], ['qt.reqRaceMask', ChrRace::MASK_HORDE, '&']], ['qt.reqRaceMask', 0]]];
            case 5:                                 // none
                $this->extraOpts['ct']['h'][] = $field.' = 0';
                return [1];
        }

        return null;
    }

    protected function cbHealthMana(int $cr, int $crs, string $crv, $minField, $maxField) : ?array
    {
        if (!Util::checkNumeric($crv, NUM_CAST_INT) || !$this->int2Op($crs))
            return null;

        // remap OP for this special case
        switch ($crs)
        {
            case '=':                               // min > max is totally possible
                $this->extraOpts['ct']['h'][] = $minField.' = '.$maxField.' AND '.$minField.' = '.$crv;
                break;
            case '>':
            case '>=':
            case '<':
            case '<=':
                $this->extraOpts['ct']['h'][] = 'IF('.$minField.' > '.$maxField.', '.$maxField.', '.$minField.') '.$crs.' '.$crv;
                break;
        }


        return [1];                                 // always true, use post-filter
    }

    protected function cbSpecialSkinLoot(int $cr, int $crs, string $crv, $typeFlag) : ?array
    {
        if (!$this->int2Bool($crs))
            return null;


        if ($crs)
            return [DB::AND, ['skinLootId', 0, '>'], ['typeFlags', $typeFlag, '&']];
        else
            return [DB::OR, ['skinLootId', 0], [['typeFlags', $typeFlag, '&'], 0]];
    }

    protected function cbRegularSkinLoot(int $cr, int $crs, string $crv, $typeFlag) : ?array
    {
        if (!$this->int2Bool($crs))
            return null;

        if ($crs)
            return [DB::AND, ['skinLootId', 0, '>'], [['typeFlags', $typeFlag, '&'], 0]];
        else
            return [DB::OR, ['skinLootId', 0], ['typeFlags', $typeFlag, '&']];
    }

    protected function cbReputation(int $cr, int $crs, string $crv, $op) : ?array
    {
        if (!in_array($crs, self::$enums[$cr]))
            return null;

        if ($_ = DB::Aowow()->selectRow('SELECT * FROM ::factions WHERE `id` = %i', $crs))
            $this->fiReputationCols[] = [$crs, Util::localizedString($_, 'name')];

        if ($cIds = DB::World()->selectCol('SELECT `creature_id` FROM creature_onkill_reputation WHERE (`RewOnKillRepFaction1` = %i AND `RewOnKillRepValue1` '.$op.' 0) OR (`RewOnKillRepFaction2` = %i AND `RewOnKillRepValue2` '.$op.' 0)', $crs, $crs))
            return ['id', $cIds];
        else
            return [0];
    }

    protected function cbFaction(int $cr, int $crs, string $crv) : ?array
    {
        if (!Util::checkNumeric($crs, NUM_CAST_INT))
            return null;

        if (!in_array($crs, self::$enums[$cr]))
            return null;

        $facTpls = [];
        $facs    = new FactionList(array(DB::OR, ['parentFactionId', $crs], ['id', $crs]));
        foreach ($facs->iterate() as $__)
            $facTpls = array_merge($facTpls, $facs->getField('templateIds'));

        return $facTpls ? ['faction', $facTpls] : [0];
    }

    // aowow - custom start: `VisibilityDistanceType` lives in `creature_template_addon`, not in the generated creature table
    protected function cbVisibilityRange(int $cr, int $crs, string $crv) : ?array
    {
        if ($crs == parent::ENUM_ANY)
            $ids = DB::World()->selectCol('SELECT `entry` FROM creature_template_addon WHERE `VisibilityDistanceType` > 0');
        else if ($crs == 0)                                 // map default: no addon row at all counts too
        {
            $ids = DB::World()->selectCol('SELECT `entry` FROM creature_template_addon WHERE `VisibilityDistanceType` > 0');

            return $ids ? ['id', $ids, '!'] : ['id', 0, '>'];
        }
        else if (in_array($crs, self::$enums[$cr]))
            $ids = DB::World()->selectCol('SELECT `entry` FROM creature_template_addon WHERE `VisibilityDistanceType` = %i', $crs);
        else
            return null;

        return $ids ? ['id', $ids] : [0];
    }

    // npcmode option => [::creature_difficulty.kind, difficulty]
    private const array MODE_FILTER = array(
        1 => [CreatureList::MODE_KIND_DUNGEON, 0],          // normal
        2 => [CreatureList::MODE_KIND_DUNGEON, 1],          // heroic
        3 => [CreatureList::MODE_KIND_RAID,    0],          // normal 10
        4 => [CreatureList::MODE_KIND_RAID,    1],          // normal 25
        5 => [CreatureList::MODE_KIND_RAID,    2],          // heroic 10
        6 => [CreatureList::MODE_KIND_RAID,    3]           // heroic 25
    );

    // used in that difficulty; a set of unknown instance type matches both readings of its difficulty
    protected function cbMode(int $cr, int $crs, string $crv) : ?array
    {
        if ($crs == parent::ENUM_ANY)
            return ['cd.id', null, '!'];
        if ($crs == parent::ENUM_NONE)
            return ['cd.id', null];

        if (!isset(self::MODE_FILTER[$crs]))
            return null;

        [$kind, $diff] = self::MODE_FILTER[$crs];
        $bit = 1 << $diff;

        if ($diff > 1)
            return [DB::AND, ['cd.kind', $kind], [['cd.modeMask', $bit, '&'], $bit]];

        return [DB::AND, ['cd.kind', [$kind, CreatureList::MODE_KIND_UNKNOWN]], [['cd.modeMask', $bit, '&'], $bit]];
    }

    // the parent of a difficulty set: the entry holding the difficulty_entry_* columns
    protected function cbDifficultySlot(int $cr, int $crs, string $crv) : ?array
    {
        if (!$this->int2Bool($crs))
            return null;

        return $crs ? ['cd.slot', 0] : [DB::OR, ['cd.id', null], ['cd.slot', 0, '!']];
    }

    private static function worldHasTable(string $tbl) : bool
    {
        static $known = [];

        return $known[$tbl] ??= (bool)DB::World()->selectCell('SHOW TABLES LIKE %s', $tbl);
    }

    // mechanicImmuneMask holds mechanic N at bit N-1 (see the npc page), schoolImmuneMask holds school N at bit N
    protected function cbImmuneMask(int $cr, int $crs, string $crv, string $col, int $offset) : ?array
    {
        if ($crs == parent::ENUM_ANY)
            return [$col, 0, '!'];
        if ($crs == parent::ENUM_NONE)
            return [$col, 0];

        $max = $offset ? 31 : SPELL_SCHOOL_ARCANE;
        if ($crs < $offset || $crs > $max)
            return null;

        $bit = 1 << ($crs - $offset);

        return [[$col, $bit, '&'], $bit];
    }

    // the same formula CreatureList::getBaseStats('melee') shows, run as a post-filter as it needs IF()
    protected function cbMeleeDamage(int $cr, int $crs, string $crv, bool $isMax) : ?array
    {
        if (!Util::checkNumeric($crv, NUM_CAST_FLOAT) || !$this->int2Op($crs))
            return null;

        $speed = 'IF(atkSpeed > 0, atkSpeed / 1000, 2)';
        $dmg   = $isMax ? '(dmgMax * 1.5 + mleAtkPwrMax / 14 * mleVariance)' : '(dmgMin + mleAtkPwrMin / 14 * mleVariance)';

        $this->extraOpts['ct']['h'][] = 'ROUND('.$dmg.' * dmgMultiplier * '.$speed.') '.$crs.' '.round($crv);

        return [1];
    }

    // ScriptName / AI and StringId are only shown to staff on the npc page
    protected function cbStaffString(int $cr, int $crs, string $crv, string $col) : ?array
    {
        if (!User::canSee(U_GROUP_EMPLOYEE))
            return [1];

        $crv = trim($crv);
        if ($crv === '')
            return null;

        return [$col, $crv, 'LIKE'];
    }

    // creature_template columns the setup step does not import (the npc page reads them from the world db)
    protected function cbTemplateNum(int $cr, int $crs, string $crv, string $expr) : ?array
    {
        if (!Util::checkNumeric($crv, NUM_CAST_FLOAT) || !$this->int2Op($crs))
            return null;

        $ids = DB::World()->selectCol('SELECT `entry` FROM creature_template WHERE ROUND('.$expr.', 2) '.$crs.' %f', round($crv, 2));

        return $ids ? ['id', $ids] : [0];
    }

    protected function cbMovement(int $cr, int $crs, string $crv, string $col, int $val) : ?array
    {
        if (!$this->int2Bool($crs))
            return null;

        $ids = self::worldHasTable('creature_template_movement') ? DB::World()->selectCol('SELECT `CreatureId` FROM creature_template_movement WHERE %n = %i', $col, $val) : [];

        if ($crs)
            return $ids ? ['id', $ids] : [0];

        return $ids ? ['id', $ids, '!'] : [1];
    }

    protected function cbEquipsItem(int $cr, int $crs, string $crv) : ?array
    {
        if (!Util::checkNumeric($crv, NUM_CAST_INT) || $crv <= 0)
            return null;

        if (!self::worldHasTable('creature_equip_template'))
            return [0];

        $ids = DB::World()->selectCol('SELECT DISTINCT `CreatureID` FROM creature_equip_template WHERE `ItemID1` = %i OR `ItemID2` = %i OR `ItemID3` = %i', $crv, $crv, $crv);

        return $ids ? ['id', $ids] : [0];
    }

    // [yn] criteria answered by a list of creature entries from the world db, matching what the npc page shows
    protected function cbWorldIds(int $cr, int $crs, string $crv, string $what) : ?array
    {
        if (!$this->int2Bool($crs))
            return null;

        $ids = match ($what)
        {
            // 'does not regenerate health' is the line the page shows, so 'yes' is RegenHealth = 1
            'regen'      => DB::World()->selectCol('SELECT `entry` FROM creature_template WHERE `RegenHealth` = 0'),
            'phased'     => DB::World()->selectCol('SELECT DISTINCT `id` FROM creature WHERE `phaseMask` NOT IN (0, 1)'),
            'follower'   => self::worldHasTable('creature_formations') ? DB::World()->selectCol(
                               'SELECT DISTINCT mc.`id` FROM creature_formations cf JOIN creature mc ON mc.`guid` = cf.`memberGUID` JOIN creature lc ON lc.`guid` = cf.`leaderGUID` WHERE lc.`id` <> mc.`id`'
                            ) : [],
            'leader'     => self::worldHasTable('creature_formations') ? DB::World()->selectCol(
                               'SELECT DISTINCT lc.`id` FROM creature_formations cf JOIN creature mc ON mc.`guid` = cf.`memberGUID` JOIN creature lc ON lc.`guid` = cf.`leaderGUID` WHERE lc.`id` <> mc.`id`'
                            ) : [],
            'mounted'    => array_merge(
                                self::worldHasTable('creature_template_addon') ? DB::World()->selectCol('SELECT `entry` FROM creature_template_addon WHERE `mount` > 0') : [],
                                self::worldHasTable('creature_addon') ? DB::World()->selectCol('SELECT DISTINCT c.`id` FROM creature_addon ca JOIN creature c ON c.`guid` = ca.`guid` WHERE ca.`mount` > 0') : []
                            ),
            // `mount` is a CreatureDisplayID, matched against the rider's model like the npc page's "Mount of:" line
            'mount'      => array_merge(
                                self::worldHasTable('creature_template_addon') ? DB::World()->selectCol(
                                   'SELECT DISTINCT ct.`entry` FROM creature_template ct JOIN creature_template_addon cta ON cta.`mount` > 0 AND cta.`mount` IN (ct.`modelid1`, ct.`modelid2`, ct.`modelid3`, ct.`modelid4`) WHERE cta.`entry` <> ct.`entry`'
                                ) : [],
                                self::worldHasTable('creature_addon') ? DB::World()->selectCol(
                                   'SELECT DISTINCT ct.`entry` FROM creature_template ct JOIN creature_addon ca ON ca.`mount` > 0 AND ca.`mount` IN (ct.`modelid1`, ct.`modelid2`, ct.`modelid3`, ct.`modelid4`) JOIN creature c ON c.`guid` = ca.`guid` WHERE c.`id` <> ct.`entry`'
                                ) : []
                            ),
            'questitems' => self::worldHasTable('creature_questitem') ? DB::World()->selectCol('SELECT DISTINCT `CreatureEntry` FROM creature_questitem WHERE `ItemId` > 0') : [],
            'pause'      => self::worldHasTable('creature_template_movement') ? DB::World()->selectCol('SELECT `CreatureId` FROM creature_template_movement WHERE `InteractionPauseTimer` IS NOT NULL') : [],
            // Creature::LoadCreaturesAddon(); older revisions pack these into `bytes1` / `bytes2`
            'sheath'      => self::addonIds('SheathState', '(`bytes2` & 255)', '> 0'),
            'animtier'    => self::addonIds('AnimTier', '((`bytes1` >> 24) & 255)', '> 0'),
            'untrackable' => self::addonIds('VisFlags', '((`bytes1` >> 16) & 255)', '& '.UNIT_VIS_FLAGS_UNTRACKABLE),
            default      => null
        };

        if ($ids === null)
            return null;

        $ids = array_values(array_unique(array_map('intVal', $ids ?: [])));

        if ($what == 'regen')                               // the list holds the 'no' side
            $crs = !$crs;

        if ($crs)
            return $ids ? ['id', $ids] : [0];

        return $ids ? ['id', $ids, '!'] : [1];
    }
    // aowow - custom end

    // aowow - custom start
    /** creature entries whose creature_template_addon or creature_addon column, or its packed older spelling, matches $test */
    private static function addonIds(string $col, string $packed, string $test) : array
    {
        $ids = [];
        foreach (['creature_template_addon' => 'SELECT `entry` FROM creature_template_addon WHERE %s %s', 'creature_addon' => 'SELECT DISTINCT c.`id` FROM creature_addon ca JOIN creature c ON c.`guid` = ca.`guid` WHERE %s %s'] as $tbl => $qry)
        {
            if (!self::worldHasTable($tbl))
                continue;

            $expr = DB::World()->selectCell('SHOW COLUMNS FROM %n LIKE %s', $tbl, $col) ? '`'.$col.'`' : $packed;
            $ids  = array_merge($ids, DB::World()->selectCol(sprintf($qry, $expr, $test)) ?: []);
        }

        return $ids;
    }

    // creature_template.IconName, the cursor the client shows; not imported, so the dropdown value is a ::cursors id
    protected function cbCursor(int $cr, int $crs, string $crv) : ?array
    {
        return $this->cursorCondition($crs, 'creature_template');
    }
    // aowow - custom end

    protected function cbHasLocation(int $cr, int $crs, string $crv) : ?array
    {
        if (!$this->int2Bool($crs))
            return null;

        return ['s.typeId', null, $crs ? '!' : null];
    }
}

?>
