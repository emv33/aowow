<?php

namespace Aowow;

if (!defined('AOWOW_REVISION'))
    die('illegal access');

if (!CLI)
    die('not in cli mode');


// aowow - custom: which map difficulties each entry of a difficulty set is used in
CLISetup::registerSetup("sql", new class extends SetupScript
{
    protected array $info = array(
        'creaturedifficulty' => [[], CLISetup::ARGV_PARAM, 'Compiles the difficulty modes of creatures with difficulty entries.']
    );

    protected array $dbcSourceFiles  = ['map', 'mapdifficulty'];
    protected array $worldDependency = ['creature_template', 'creature', 'creature_summon_groups', 'smart_scripts', 'spawn_group'];
    protected array $setupAfter      = [['creature', 'spawns', 'zones'], []];

    private const int ALL_MODES = 0xF;

    private array $maps = [];                               // instance maps: [mapId => [kind, modeMask]]

    public function generate() : bool
    {
        DB::Aowow()->qry('TRUNCATE ::creature_difficulty');

        // Map.dbc InstanceType: 1 - party, 2 - raid; MapDifficulty.dbc holds one row per difficulty the map can be entered in
        foreach (DB::Aowow()->selectAssoc('SELECT m.`id` AS ARRAY_KEY, m.`areaType`, BIT_OR(1 << md.`difficulty`) AS "modeMask" FROM dbc_map m JOIN dbc_mapdifficulty md ON md.`mapId` = m.`id` WHERE m.`areaType` IN (1, 2) GROUP BY m.`id`') as $mapId => $m)
            $this->maps[$mapId] = [$m['areaType'] == 1 ? CreatureList::MODE_KIND_DUNGEON : CreatureList::MODE_KIND_RAID, (int)$m['modeMask']];

        $sets = DB::Aowow()->selectAssoc('SELECT `id` AS ARRAY_KEY, `difficultyEntry1` AS "1", `difficultyEntry2` AS "2", `difficultyEntry3` AS "3" FROM ::creature WHERE `difficultyEntry1` > 0 OR `difficultyEntry2` > 0 OR `difficultyEntry3` > 0');
        if (!$sets)
            return true;

        // Creature::InitEntry() skips an entry whose template does not exist, as if the slot were empty
        $known = array_flip(DB::Aowow()->selectCol('SELECT `id` FROM ::creature WHERE `id` IN %in', array_merge(...array_map('array_values', array_values($sets)))) ?: []);
        foreach ($sets as $parentId => &$slots)
        {
            $slots = array_map(fn($x) => isset($known[$x]) ? (int)$x : 0, $slots);
            $slots[0] = $parentId;
        }
        unset($slots);

        $sets = array_filter($sets, fn($x) => $x[1] || $x[2] || $x[3]);
        if (!$sets)
            return true;

        $members = array_merge(...array_map(fn($x) => array_values(array_filter($x)), array_values($sets)));

        CLI::write('[creaturedifficulty] - finding the instance of '.count($sets).' difficulty sets');

        $spawnMaps = $this->spawnMaps(Type::NPC, $members);

        // only summoned members need their summoner looked up
        $summoned = array_flip(array_merge(
            DB::World()->selectCol('SELECT DISTINCT `action_param1` FROM smart_scripts WHERE `action_type` = %i AND `action_param1` IN %in', SmartAction::ACTION_SUMMON_CREATURE, $members) ?: [],
            DB::World()->selectCol('SELECT DISTINCT `entry` FROM creature_summon_groups WHERE `entry` IN %in', $members) ?: []
        ));
        $groupMaps = DB::World()->selectCol('SELECT `entry` AS ARRAY_KEY, `summonerId` AS ARRAY_KEY2, `summonerId` FROM creature_summon_groups WHERE `summonerType` = %i AND `entry` IN %in', SUMMONER_TYPE_MAP, $members) ?: [];

        $rows = [];
        foreach ($sets as $parentId => $slots)
        {
            $found = [];                                    // [mapId => modeMask]

            // the members' own spawns; creature.spawnMask says which difficulties of the map the spawn exists in
            foreach (array_filter($slots) as $id)
                foreach ($spawnMaps[$id] ?? [] as $mapId => $mask)
                    $found[$mapId] = ($found[$mapId] ?? 0) | $mask;

            // ... else whoever summons them
            if (!$found)
            {
                foreach (array_filter($slots) as $id)
                {
                    foreach ($groupMaps[$id] ?? [] as $mapId)
                        if (isset($this->maps[$mapId]))
                            $found[$mapId] = $this->maps[$mapId][1];

                    if (!isset($summoned[$id]))
                        continue;

                    foreach (SmartAI::getOwnerOfNPCSummon($id) as $type => $owners)
                        if ($type == Type::NPC || $type == Type::OBJECT)
                            foreach ($this->spawnMaps($type, $owners) as $ownerMaps)
                                foreach ($ownerMaps as $mapId => $mask)
                                    $found[$mapId] = ($found[$mapId] ?? 0) | $mask;
                }
            }

            [$kind, $modeMask] = $this->resolveKind($found, $slots);

            foreach ($this->assignModes($slots, $kind, $modeMask) as $slot => $mask)
                $rows[$slots[$slot]] ??= [$slots[$slot], $parentId, $slot, $kind, $mask];
        }

        foreach (array_chunk(array_values($rows), CLISetup::SQL_BATCH) as $batch)
            DB::Aowow()->qry('INSERT INTO ::creature_difficulty %m', array(
                'id'       => array_column($batch, 0),
                'parentId' => array_column($batch, 1),
                'slot'     => array_column($batch, 2),
                'kind'     => array_column($batch, 3),
                'modeMask' => array_column($batch, 4)
            ));

        return true;
    }

    /** @return array [typeId => [mapId => modeMask]] for the instance maps the entries are spawned on */
    private function spawnMaps(int $type, array $ids) : array
    {
        if (!$ids)
            return [];

        $result = [];
        $spawns = DB::Aowow()->selectAssoc(
           'SELECT   s.`typeId`, z.`mapIdBak` AS "mapId", BIT_OR(IF(s.`spawnMask` = 0, %i, s.`spawnMask`)) AS "spawnMask"
            FROM     ::spawns s
            JOIN     ::zones z ON z.`id` = s.`areaId`
            WHERE    s.`type` = %i AND s.`typeId` IN %in
            GROUP BY s.`typeId`, z.`mapIdBak`',
            self::ALL_MODES, $type, $ids
        );

        foreach ($spawns as ['typeId' => $id, 'mapId' => $mapId, 'spawnMask' => $mask])
            if (isset($this->maps[$mapId]) && ($m = (int)$mask & $this->maps[$mapId][1]))
                $result[$id][$mapId] = ($result[$id][$mapId] ?? 0) | $m;

        return $result;
    }

    /** @return array [kind, modeMask] of the instance a set lives in */
    private function resolveKind(array $found, array $slots) : array
    {
        $kinds = [];
        foreach ($found as $mapId => $mask)
            $kinds[$this->maps[$mapId][0]] = ($kinds[$this->maps[$mapId][0]] ?? 0) | $mask;

        if (count($kinds) == 1)
            return [key($kinds), current($kinds)];

        // only a raid has difficulties 2 and 3 (10/25 heroic), so a set using those slots is one
        if ($slots[2] || $slots[3])
            return [CreatureList::MODE_KIND_RAID, $kinds[CreatureList::MODE_KIND_RAID] ?? self::ALL_MODES];

        // difficulty 1 is dungeon heroic or raid 25 normal; can't tell
        return [CreatureList::MODE_KIND_UNKNOWN, 0x3];
    }

    /** @return array [slot => modeMask], walking Creature::InitEntry()'s fallback for every difficulty the set is used in */
    private function assignModes(array $slots, int $kind, int $modeMask) : array
    {
        $result = array_fill_keys(array_keys(array_filter($slots)), 0);

        for ($difficulty = 0; $difficulty < 4; $difficulty++)
        {
            if (!($modeMask & (1 << $difficulty)))
                continue;

            $diff = $difficulty;
            while ($diff > 0 && !$slots[$diff])
                $diff -= ($diff >= 2 && $kind == CreatureList::MODE_KIND_RAID) ? 2 : 1;     // heroic raid falls back to normal of its size

            $result[$diff] |= 1 << $difficulty;
        }

        return $result;
    }
});

?>
