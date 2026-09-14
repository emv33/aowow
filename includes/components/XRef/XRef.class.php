<?php

namespace Aowow;

if (!defined('AOWOW_REVISION'))
    die('illegal access');


/*
 * What else in the world DB points at this entity.
 *
 * Detail pages answer "what does this thing do" well and "what mentions this thing" barely at all.
 * The conditions system is the exception - every page that can be a condition value already runs
 * Conditions::getByCondition() - so nothing here repeats it.
 *
 * Everything is read from DB::World() live and every table is checked before it is touched.
 */
class XRef
{
    private const string BASE_CSS = <<<CSS
        #xref-generic .grid { clear:left; display: grid; grid-template-columns: 200px auto; }
        #xref-generic .grid thead,
        #xref-generic .grid tbody,
        #xref-generic .grid tr { display: contents; }
    CSS;

    // enum LinkedRespawnType - the name reads dependent_TO_master, so `guid` waits on `linkedGuid`
    private const int CREATURE_TO_CREATURE = 0;
    private const int CREATURE_TO_GO       = 1;
    private const int GO_TO_GO             = 2;
    private const int GO_TO_CREATURE       = 3;

    private ?array $guids    = null;                        // spawns of this entity, looked up at most once
    private array $rows      = [];                          // [label, value]
    private array $jsGlobals = [];

    public function __construct(public readonly int $type, public readonly int $typeId) { }

    private static function hasTable(string $tbl) : bool
    {
        static $known = [];

        return $known[$tbl] ??= (bool)DB::World()->selectCell('SHOW TABLES LIKE %s', $tbl);
    }

    private function add(string $label, array $links) : void
    {
        if ($links)
            $this->rows[] = [$label, Lang::concat($links, Lang::CONCAT_NONE)];
    }

    /** [Type => ids[]] as a list of markup links, registering the globals each one needs */
    private function linksFor(array $byType) : array
    {
        $out = [];
        foreach ($byType as $type => $ids)
        {
            foreach (array_unique(array_filter(array_map('intVal', $ids))) as $id)
            {
                // not every type has a markup tag of its own
                if ($tag = Markup::getTagForType($type))
                {
                    $this->jsGlobals[$type][$id] = $id;
                    $out[] = '['.$tag.'='.$id.']';
                }
                else if ($type == Type::AREATRIGGER)
                    $out[] = '[url=?areatrigger='.$id.']'.Lang::areatrigger('unnamed', [$id]).'[/url]';
            }
        }

        return $out;
    }

    /**
     * every SmartAI script that names this entity in an event, action or target parameter
     * the four hand written getOwnerOf* lookups only ever covered summons, casts and sounds
     */
    private function smartAI() : void
    {
        $this->add(Lang::xRef('smartAI'), $this->linksFor(SmartAI::getOwnerOfReference($this->type, $this->typeId)));
    }

    /** `creature_summon_groups` - who this creature is a member of someone else's summon group */
    private function summonedBy() : void
    {
        if ($this->type != Type::NPC || !self::hasTable('creature_summon_groups'))
            return;

        $rows = DB::World()->selectAssoc('SELECT `summonerId`, `summonerType` FROM creature_summon_groups WHERE `entry` = %i', $this->typeId) ?: [];

        $byType = [];
        foreach ($rows as $r)
        {
            // summonerType 2 is a map, which has no entity to link to
            if ((int)$r['summonerType'] == SUMMONER_TYPE_CREATURE)
                $byType[Type::NPC][]    = (int)$r['summonerId'];
            else if ((int)$r['summonerType'] == SUMMONER_TYPE_GAMEOBJECT)
                $byType[Type::OBJECT][] = (int)$r['summonerId'];
        }

        $this->add(Lang::xRef('summonedBy'), $this->linksFor($byType));
    }

    /**
     * `vehicle_accessory` - the vehicle this creature rides on, seated by spawn rather than by entry
     * the by-entry table is already in the infobox, so only vehicles that exist nowhere else are
     * listed here
     */
    private function ridesOn() : void
    {
        if ($this->type != Type::NPC || !self::hasTable('vehicle_accessory'))
            return;

        $ids = DB::World()->selectCol(
           'SELECT c.`id` FROM vehicle_accessory va JOIN creature c ON c.`guid` = va.`guid` WHERE va.`accessory_entry` = %i',
            $this->typeId
        ) ?: [];

        if ($ids && self::hasTable('vehicle_template_accessory'))
            $ids = array_diff($ids, DB::World()->selectCol('SELECT `entry` FROM vehicle_template_accessory WHERE `accessory_entry` = %i', $this->typeId) ?: []);

        $this->add(Lang::xRef('ridesOn'), $this->linksFor([Type::NPC => $ids]));
    }

    /** every spawn of this creature or object, as both spawn tables below are keyed by guid */
    private function ownGuids() : array
    {
        return $this->guids ??= array_map('intVal', ($this->type == Type::NPC
            ? DB::World()->selectCol('SELECT `guid` FROM creature WHERE `id` = %i', $this->typeId)
            : DB::World()->selectCol('SELECT `guid` FROM gameobject WHERE `id` = %i', $this->typeId)) ?: []);
    }

    /** `spawn_group` - the groups this entity's spawns are part of; a group has no page to link to */
    private function spawnGroups() : void
    {
        if (($this->type != Type::NPC && $this->type != Type::OBJECT) || !self::hasTable('spawn_group'))
            return;

        if (!($guids = $this->ownGuids()))
            return;

        $rows = DB::World()->selectAssoc(
           'SELECT   sg.`groupId`, sgt.`GroupName`
            FROM     spawn_group sg
            LEFT JOIN spawn_group_template sgt ON sgt.`groupId` = sg.`groupId`
            WHERE    sg.`spawnType` = %i AND sg.`spawnId` IN %in
            GROUP BY sg.`groupId`, sgt.`GroupName`',
            $this->type == Type::NPC ? SUMMONER_TYPE_CREATURE : SUMMONER_TYPE_GAMEOBJECT, array_map('intVal', $guids)
        ) ?: [];

        $out = [];
        foreach ($rows as $r)
            // no escaping: the name is substituted into markup, and Markup::cleanText() json_encodes the whole body
            $out[] = $r['GroupName'] ?: Lang::xRef('unnamedGroup', [(int)$r['groupId']]);

        $this->add(Lang::xRef('spawnGroup'), $out);
    }

    /** `creature_equip_template` - the creatures that carry this item as a weapon or shield */
    private function equippedBy() : void
    {
        if ($this->type != Type::ITEM || !self::hasTable('creature_equip_template'))
            return;

        $ids = DB::World()->selectCol('SELECT `CreatureID` FROM creature_equip_template WHERE `ItemID1` = %i OR `ItemID2` = %i OR `ItemID3` = %i', $this->typeId, $this->typeId, $this->typeId);
        if ($ids === null)                                  // renamed from entry/itemEntry<n> in 2016
            $ids = DB::World()->selectCol('SELECT `entry` FROM creature_equip_template WHERE `itemEntry1` = %i OR `itemEntry2` = %i OR `itemEntry3` = %i', $this->typeId, $this->typeId, $this->typeId) ?: [];

        $this->add(Lang::xRef('equippedBy'), $this->linksFor([Type::NPC => $ids]));
    }

    /**
     * `linked_respawn` - spawns whose respawn is tied to another spawn's, read nowhere until now
     *
     * an instance ties its trash to the boss through this table, so a creature that only comes back
     * once something else dies looked exactly like one on a plain timer
     */
    private function linkedRespawn() : void
    {
        if (($this->type != Type::NPC && $this->type != Type::OBJECT) || !self::hasTable('linked_respawn'))
            return;

        $isNPC = $this->type == Type::NPC;
        $guids = $this->ownGuids();
        if (!$guids)
            return;

        // which end of the link our own spawns sit on decides both the row filter and what the other end is
        $waitsOn  = $isNPC ? [self::CREATURE_TO_CREATURE => Type::NPC, self::CREATURE_TO_GO => Type::OBJECT]
                           : [self::GO_TO_GO => Type::OBJECT, self::GO_TO_CREATURE => Type::NPC];
        $waitedOn = $isNPC ? [self::CREATURE_TO_CREATURE => Type::NPC, self::GO_TO_CREATURE => Type::OBJECT]
                           : [self::CREATURE_TO_GO => Type::NPC, self::GO_TO_GO => Type::OBJECT];

        foreach ([['linkedGuid', 'guid', $waitsOn, 'respawnsWith'], ['guid', 'linkedGuid', $waitedOn, 'respawnGates']] as [$take, $match, $types, $label])
        {
            $rows = DB::World()->selectAssoc(
               'SELECT `'.$take.'` AS "guid", `linkType` FROM linked_respawn WHERE `'.$match.'` IN %in AND `linkType` IN %in',
                $guids, array_keys($types)
            ) ?: [];

            $byGuid = [];
            foreach ($rows as $r)
                $byGuid[$types[(int)$r['linkType']]][] = (int)$r['guid'];

            $byType = [];
            foreach ($byGuid as $type => $ids)
                $byType[$type] = $type == Type::NPC
                               ? DB::World()->selectCol('SELECT `id` FROM creature WHERE `guid` IN %in', $ids)
                               : DB::World()->selectCol('SELECT `id` FROM gameobject WHERE `guid` IN %in', $ids);

            $this->add(Lang::xRef($label), $this->linksFor($byType));
        }
    }

    public function getMarkup() : ?Markup
    {
        $this->smartAI();
        $this->summonedBy();
        $this->ridesOn();
        $this->spawnGroups();
        $this->linkedRespawn();
        $this->equippedBy();

        if (!$this->rows)
            return null;

        $tbl = '';
        foreach ($this->rows as [$label, $value])
            $tbl .= '[tr][td][b]'.$label.'[/b][/td][td]'.$value.'[/td][/tr]';

        $body = '[style]'.strtr(self::BASE_CSS, "\n", ' ').'[/style]'.
                '[pad][h3][toggler id=xref]'.Lang::xRef('title').'[/toggler][/h3]'.
                '[div id=xref clear=left][table class=grid]'.$tbl.'[/table][/div]';

        return new Markup($body, ['allow' => Markup::CLASS_ADMIN], 'xref-generic');
    }

    public function getJSGlobals() : array
    {
        return $this->jsGlobals;
    }
}

?>
