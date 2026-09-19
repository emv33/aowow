<?php

namespace Aowow;

if (!defined('AOWOW_REVISION'))
    die('illegal access');

if (!CLI)
    die('not in cli mode');


// requires https://github.com/TrinityCore/TrinityCore/commit/f989c7182c4cc30f1d0ffdc566c7624a5e108a2f to have been used at least once

CLISetup::registerSetup("sql", new class extends SetupScript
{
    protected array $info = array(
        'spawns'       => [[   ], CLISetup::ARGV_PARAM,    'Compiles map points from dbc and world db.'       ],
        'creatures'    => [['1'], CLISetup::ARGV_OPTIONAL, '...just the creature positions.'                  ],
        'objects'      => [['2'], CLISetup::ARGV_OPTIONAL, '...just the gameobject positions.'                ],
        'soundemitter' => [['3'], CLISetup::ARGV_OPTIONAL, '...just the soundemitter positions.'              ],
        'areatrigger'  => [['4'], CLISetup::ARGV_OPTIONAL, '...just the areatrigger and teleporter positions.'],
        'instances'    => [['5'], CLISetup::ARGV_OPTIONAL, '...just the instance portal positions.'           ],
        'waypoints'    => [['6'], CLISetup::ARGV_OPTIONAL, '...just the creature waypoints.'                  ]
   );

    protected array $dbcSourceFiles  = ['worldmaparea', 'map', 'taxipathnode', 'soundemitters', 'areatrigger', 'areatable'];
    protected array $worldDependency = ['creature', 'creature_addon', 'creature_template_addon', 'gameobject', 'gameobject_template', 'vehicle_accessory', 'vehicle_accessory_template', 'waypoint_data', 'script_waypoint', 'smart_scripts', 'areatrigger_teleport'];
    protected array $setupAfter      = [['dungeonmap', 'worldmaparea', 'zones'], ['img-maps']];

    private array $transports   = [];
    private array $overrideData = [];
    private array $mapToArea    = [];
    private array $areaParents  = [];

    private array $steps = array(
        0x01 => ['creature',     Type::NPC,         false, '`creature` spawns',                                ],
        0x02 => ['gameobject',   Type::OBJECT,      false, '`gameobject` spawns',                              ],
        0x04 => ['soundemitter', Type::SOUND,       false, 'SoundEmitters.dbc positions',                      ],
        0x08 => ['areatrigger',  Type::AREATRIGGER, false, 'AreaTrigger.dbc positions and teleporter endpoints'],
        0x10 => ['instances',    Type::ZONE,        false, 'Map.dbc instance portals positions'                ],
        0x20 => ['waypoints',    Type::NPC,         true,  'NPC waypoints from `waypoint_data`'                ]
    );

    public function generate() : bool
    {
        /*****************************/
        /* find out what to generate */
        /*****************************/

        $opts     = array_slice(array_keys($this->info), 1);
        $getOpt   = CLISetup::getOpt(...$opts);
        $todoMask = 0x0;

        if ($getOpt['creatures'])
            $todoMask |= 0x01;
        if ($getOpt['objects'])
            $todoMask |= 0x02;
        if ($getOpt['soundemitter'])
            $todoMask |= 0x04;
        if ($getOpt['areatrigger'])
            $todoMask |= 0x08;
        if ($getOpt['instances'])
            $todoMask |= 0x10;
        if ($getOpt['waypoints'])
            $todoMask |= 0x20;

        if ($todoMask)
            foreach ($this->steps as $idx => $_)
                if (!($todoMask & $idx))
                    unset($this->steps[$idx]);


        /*********************************/
        /* selectively truncate old data */
        /*********************************/

        if (!$todoMask || ($todoMask & 0x1F) == 0x1F)
            DB::Aowow()->qry('TRUNCATE TABLE ::spawns');
        else
            foreach ($this->steps as $idx => [, $type, $isWP, ])
                if (($idx & $todoMask) && !$isWP)
                    DB::Aowow()->qry('DELETE FROM ::spawns WHERE `type` = %i', $type);

        if (!$todoMask || ($todoMask & 0x20))
            DB::Aowow()->qry('TRUNCATE TABLE ::creature_waypoints');


        /**************************/
        /* offsets for transports */
        /**************************/

        $this->transports = DB::World()->selectCol('SELECT `data0` AS `pathId`, `data6` AS ARRAY_KEY FROM gameobject_template WHERE `type` = %i AND `data6` <> 0', OBJECT_MO_TRANSPORT);
        foreach ($this->transports as &$t)
            $t = DB::Aowow()->selectRow('SELECT `posX`, `posY`, `mapId` FROM dbc_taxipathnode tpn WHERE tpn.`pathId` = %i AND `nodeIdx` = 0', $t);


        /*********************/
        /* get override data */
        /*********************/

        $this->overrideData = DB::Aowow()->selectAssoc('SELECT `type` AS ARRAY_KEY, `typeGuid` AS ARRAY_KEY2, `areaId`, `floor` FROM ::spawns_override');
        $this->mapToArea    = DB::Aowow()->selectCol('SELECT `mapId` AS ARRAY_KEY, `id` FROM ::zones WHERE `parentArea` = 0 AND (`cuFlags` & %i) = 0', CUSTOM_EXCLUDE_FOR_LISTVIEW);
        $this->areaParents  = DB::Aowow()->selectCol('SELECT `id` AS ARRAY_KEY, IF(`parentArea`, `parentArea`, `id`) FROM ::zones');


        /**************/
        /* perform... */
        /**************/

        foreach (array_values($this->steps) as $i => [$generator, $type, $isWP, $comment])
        {
            $time          = new Timer(500);
            $sum           = 0;
            $lastOverride  = 0;
            $insertData    = [];
            $nSteps        = count($this->steps);
            $queryResult   = $this->$generator();
            $queryTotal    = count($queryResult);
            $queryTotalLen = strlen($queryTotal);

            CLI::write(' - '.$generator);

            foreach ($queryResult as $spawn)
            {
                $notice = '';
                $sum++;
                if ($time->update())
                    CLI::write(' * '.($i + 1).'/'.$nSteps.': '. CLI::bold($comment).' - '.sprintf('%'.$queryTotalLen.'d / %d (%4.1f%%)', $sum,  $queryTotal, round(100 * $sum / $queryTotal, 1)), CLI::LOG_BLANK, true, true);

                $point = $this->transformPoint($spawn, $type, $notice);

                if ($notice && $lastOverride != $spawn['guid'])
                {
                    CLI::write($notice, CLI::LOG_INFO);
                    $time->reset();
                    $lastOverride = $spawn['guid'];   // don't spam this for waypoints
                }

                if (!$point)
                {
                    CLI::write('[points] '.str_pad('['.$spawn['guid'].']', 9).' '.(isset($spawn['point']) ? 'with path/point ['.$spawn['creatureOrPath'].'; '.$spawn['point'].'] ' : '').'could not be matched to displayable area [A:'.($spawn['areaId'] ?? 0).'; X:'.$spawn['posY'].'; Y:'.$spawn['posX'].']', CLI::LOG_WARN);
                    $time->reset();
                    continue;
                }

                [
                    $insertData['areaId'][],
                    $insertData['posX'][],
                    $insertData['posY'][],
                    $insertData['floor'][]
                ] = $point; // [areaId, posX, posY, floor]

                unset($spawn['map'], $spawn['posX'], $spawn['posY'], $spawn['areaId']);
                foreach ($spawn as $k => $v)
                    $insertData[$k][] = $v;

                if (!($sum % 1000) || $sum == $queryTotal)
                {
                    if (!$isWP)                             // REPLACE: because there is bogus data where one path may be assigned to multiple npcs
                        DB::Aowow()->qry('REPLACE INTO ::spawns %m', $insertData);
                    else
                    {
                        unset($insertData['guid']);
                        DB::Aowow()->qry('REPLACE INTO ::creature_waypoints %m', $insertData);
                    }

                    $insertData = [];
                }
            }
        }


        /*****************************/
        /* spawn vehicle accessories */
        /*****************************/

        if ($todoMask & 0x01)                               // only when creature is set
        {
            // get vehicle template accessories
            $accessories = DB::World()->selectAssoc(
               'SELECT vta.`accessory_entry` AS `typeId`,  c.`guid`,  vta.`entry`, COUNT(1) AS `nSeats` FROM vehicle_template_accessory vta LEFT JOIN creature c ON c.`id` = vta.`entry` GROUP BY `accessory_entry`,  c.`guid` UNION
                SELECT  va.`accessory_entry` AS `typeId`, va.`guid`, 0 AS `entry`, COUNT(1) AS `nSeats` FROM vehicle_accessory           va                                              GROUP BY `accessory_entry`, va.`guid`'
            );

            // accessories may also be vehicles (e.g. "Kor'kron Infiltrator" is seated on "Kor'kron Suppression Turret" is seated on "Kor'kron Troop Transport")
            // so we will retry finding a spawned vehicle if none were found on the previous pass and a change occured
            $vGuid   = 0;                                   // not really used, but we need some kind of index
            $n       = 0;
            $matches = -1;
            while ($matches)
            {
                $matches = 0;
                foreach ($accessories as $idx => $data)
                {
                    $vehicles = [];
                    if ($data['guid'])                      // vehicle already spawned
                        $vehicles = DB::Aowow()->selectAssoc('SELECT s.`areaId`, s.`posX`, s.`posY`, s.`floor` FROM ::spawns s WHERE s.`guid`   = %i AND s.`type` = %i', $data['guid'], Type::NPC);
                    else if ($data['entry'])                // vehicle on unspawned vehicle action
                        $vehicles = DB::Aowow()->selectAssoc('SELECT s.`areaId`, s.`posX`, s.`posY`, s.`floor` FROM ::spawns s WHERE s.`typeId` = %i AND s.`type` = %i', $data['entry'], Type::NPC);

                    if ($vehicles)
                    {
                        $matches++;
                        foreach ($vehicles as $v)           // if there is more than one vehicle, its probably due to overlapping zones
                            for ($i = 0; $i < $data['nSeats']; $i++)
                                DB::Aowow()->qry('INSERT INTO ::spawns (`guid`, `type`, `typeId`, `respawn`, `spawnMask`, `phaseMask`, `areaId`, `floor`, `posX`, `posY`, `pathId`) VALUES (%i, %i, %i, 0, 0, 1, %i, %i, %f, %f, 0)',
                                    --$vGuid, Type::NPC, $data['typeId'], $v['areaId'], $v['floor'], $v['posX'], $v['posY']);

                        unset($accessories[$idx]);
                    }
                }
                if ($matches)
                    CLI::write(' * assigned '.$matches.' accessories on '.++$n.'. pass on vehicle accessories', CLI::LOG_BLANK, true, true);
            }
            if ($accessories)
                CLI::write('[spawns] - '.count($accessories).' accessories could not be fitted onto a spawned vehicle.', CLI::LOG_WARN);
        }


        /********************************/
        /* restrict difficulty displays */
        /********************************/

        DB::Aowow()->qry('UPDATE ::spawns s, dbc_worldmaparea wma, dbc_map m SET s.`spawnMask` = 0 WHERE s.`areaId` = wma.`areaId` AND wma.`mapId` = m.`id` AND m.`areaType` IN (0, 3, 4)');

        return true;
    }

    private function creature() : array
    {
        // [guid, type, typeId, map, posX, posY [, respawn, spawnMask, phaseMask, areaId, floor, pathId, ScriptName, StringId]]
        return DB::World()->selectAssoc(
           'SELECT    c.`guid`, %i AS `type`, c.`id` AS `typeId`, c.`map`, c.`position_x` AS `posX`, c.`position_y` AS `posY`, c.`spawntimesecs` AS `respawn`, c.`spawnMask`, c.`phaseMask`, c.`zoneId` AS `areaId`, IFNULL(ca.`path_id`, IFNULL(cta.`path_id`, 0)) AS `pathId`, NULLIF(`ScriptName`, "") AS "ScriptName", NULLIF(`StringId`, "") AS "StringId"
            FROM      creature c
            LEFT JOIN creature_addon ca           ON ca.guid   = c.guid
            LEFT JOIN creature_template_addon cta ON cta.entry = c.id',
            Type::NPC
        );
    }

    private function gameobject() : array
    {
        // [guid, type, typeId, map, posX, posY [, respawn, spawnMask, phaseMask, areaId, floor, pathId, ScriptName, StringId]]
        return DB::World()->selectAssoc(
           'SELECT `guid`, %i AS `type`, `id` AS `typeId`, `map`, `position_x` AS `posX`, `position_y` AS `posY`, `spawntimesecs` AS `respawn`, `spawnMask`, `phaseMask`, `zoneId` AS `areaId`, NULLIF(`ScriptName`, "") AS "ScriptName", NULLIF(`StringId`, "") AS "StringId"
            FROM   gameobject',
            Type::OBJECT
        );
    }

    private function soundemitter() : array
    {
        // [guid, type, typeId, map, posX, posY [, respawn, spawnMask, phaseMask, areaId, floor, pathId, ScriptName, StringId]]
        return DB::Aowow()->selectAssoc(
           'SELECT `id` AS `guid`, %i AS `type`, `soundId` AS `typeId`, `mapId` AS `map`, `posX`, `posY`
            FROM   dbc_soundemitters',
            Type::SOUND
        );
    }

    private function areatrigger() : array
    {
        // [guid, type, typeId, map, posX, posY [, respawn, spawnMask, phaseMask, areaId, floor, pathId, ScriptName, StringId]]
        $base = DB::Aowow()->selectAssoc(
           'SELECT `id` AS `guid`, %i AS `type`, `id` AS `typeId`, `mapId` AS `map`, `posX`, `posY`
            FROM   dbc_areatrigger',
            Type::AREATRIGGER
        );

        $addData = DB::World()->selectAssoc(
           'SELECT -`ID`          AS `guid`, %i AS `type`, ID          AS `typeId`,    `target_map` AS `map`, `target_position_x` AS `posX`, `target_position_y` AS `posY`
            FROM   areatrigger_teleport UNION
            SELECT -`entryorguid` AS `guid`, %i AS `type`, entryorguid AS `typeId`, `action_param1` AS `map`, `target_x`          AS `posX`, `target_y`          AS `posY`
            FROM   smart_scripts
            WHERE `source_type` = %i AND `action_type` = %i',
            Type::AREATRIGGER, Type::AREATRIGGER, SmartAI::SRC_TYPE_AREATRIGGER, SmartAction::ACTION_TELEPORT
        );

        return array_merge($base, $addData);
    }

    private function instances() : array
    {
        // maps with set graveyard
        return DB::Aowow()->selectAssoc(
           'SELECT -`id` AS `guid`, %i AS `type`, `id` AS `typeId`, `parentMapId` AS `map`, `parentX` AS `posX`, `parentY` AS `posY`
            FROM   ::zones
            WHERE `parentX` <> 0 AND `parentY` <> 0 AND `parentArea` = 0 AND (`cuFlags` & %i) = 0',
            Type::ZONE, CUSTOM_EXCLUDE_FOR_LISTVIEW
        );
    }

    private function waypoints() : array
    {
        // assume that creature_template_addon data isn't stupid and only creatures with a single spawn are referenced here
        //
        // `kind` 0 rows are `waypoint_data` paths: a creature's default path (via *_addon), or one
        // a SmartAI ACTION_WP_START assigns it at runtime - directly, or from inside a timed
        // action list the creature calls (resolved further down via getActionListOwners(), since
        // a SRC_TYPE_ACTIONLIST script isn't itself tied to any `entryorguid` this query can join
        // on) - all share the `waypoint_data.id` id space, so REPLACE INTO dedupes a path that
        // happens to be assigned more than one of these ways.
        // `kind` 1 rows are `script_waypoint` escort paths, keyed by the creature entry itself
        // (see LegacyScript::SRC_ESCORT_PATH) - a distinct id space from `waypoint_data.id`.
        // some cores/TDBs no longer ship `script_waypoint` at all (LegacyScript::tableExists()
        // guards the same table for the same reason), so that branch is skipped if it's missing.
        //
        // in every case only one representative creature is used to resolve the map/zone a path
        // renders on - `creature_template_addon`/direct-guid cases already pin down exactly one,
        // the others (a shared template entry) pick an arbitrary spawn of it via MIN(`guid`).
        $sql =
           'SELECT c.`guid`, -w.`id` AS `creatureOrPath`, 0 AS `kind`, w.`point`, c.`zoneId` AS `areaId`, c.`map`, w.`delay` AS `wait`, w.`position_x` AS `posX`, w.`position_y` AS `posY`
            FROM   creature c
            JOIN   creature_addon ca ON ca.`guid` = c.`guid`
            JOIN   waypoint_data w ON w.`id` = ca.`path_id`
            WHERE  ca.`path_id` <> 0 UNION
            SELECT  c.`guid`, -w.`id` AS `creatureOrPath`, 0 AS `kind`, w.`point`, c.`zoneId` AS `areaId`, c.`map`, w.`delay` AS `wait`, w.`position_x` AS `posX`, w.`position_y` AS `posY`
            FROM   creature c
            JOIN   creature_template_addon cta ON cta.`entry` = c.`id`
            JOIN   waypoint_data w ON w.`id` = cta.`path_id`
            WHERE  cta.`path_id` <> 0 UNION
            SELECT c.`guid`, -w.`id` AS `creatureOrPath`, 0 AS `kind`, w.`point`, c.`zoneId` AS `areaId`, c.`map`, w.`delay` AS `wait`, w.`position_x` AS `posX`, w.`position_y` AS `posY`
            FROM   (SELECT `id`, MIN(`guid`) AS `guid`, MIN(`map`) AS `map`, MIN(`zoneId`) AS `zoneId` FROM creature GROUP BY `id`) c
            JOIN   (SELECT DISTINCT `entryorguid`, `action_param2` AS `pathId` FROM smart_scripts WHERE `source_type` = '.SmartAI::SRC_TYPE_CREATURE.' AND `action_type` = '.SmartAction::ACTION_WP_START.' AND `action_param2` > 0 AND `entryorguid` > 0) ss ON ss.`entryorguid` = c.`id`
            JOIN   waypoint_data w ON w.`id` = ss.`pathId` UNION
            SELECT c.`guid`, -w.`id` AS `creatureOrPath`, 0 AS `kind`, w.`point`, c.`zoneId` AS `areaId`, c.`map`, w.`delay` AS `wait`, w.`position_x` AS `posX`, w.`position_y` AS `posY`
            FROM   creature c
            JOIN   (SELECT DISTINCT `entryorguid`, `action_param2` AS `pathId` FROM smart_scripts WHERE `source_type` = '.SmartAI::SRC_TYPE_CREATURE.' AND `action_type` = '.SmartAction::ACTION_WP_START.' AND `action_param2` > 0 AND `entryorguid` < 0) ss ON c.`guid` = -ss.`entryorguid`
            JOIN   waypoint_data w ON w.`id` = ss.`pathId`';

        if ($this->hasTable('script_waypoint'))
            $sql .=
               ' UNION
                SELECT c.`guid`, -sw.`entry` AS `creatureOrPath`, 1 AS `kind`, sw.`pointid` AS `point`, c.`zoneId` AS `areaId`, c.`map`, sw.`waittime` AS `wait`, sw.`location_x` AS `posX`, sw.`location_y` AS `posY`
                FROM   (SELECT `id`, MIN(`guid`) AS `guid`, MIN(`map`) AS `map`, MIN(`zoneId`) AS `zoneId` FROM creature GROUP BY `id`) c
                JOIN   script_waypoint sw ON sw.`entry` = c.`id`';

        $rows = DB::World()->selectAssoc($sql) ?: [];

        // a WP_START issued from inside a timed action list (SRC_TYPE_ACTIONLIST) is invisible to
        // the direct source_type = SRC_TYPE_CREATURE joins above - resolve it through whichever
        // creature(s) actually call that list; SmartAI::getActionListOwners() already accounts for
        // ACTION_CALL_TIMED_ACTIONLIST and its ACTION_CALL_RANDOM(_RANGE)_TIMED_ACTIONLIST siblings
        $talActions = DB::World()->selectAssoc(
           'SELECT `entryorguid` AS `talId`, `action_param2` AS `pathId`
            FROM   smart_scripts
            WHERE  `source_type` = %i AND `action_type` = %i AND `action_param2` > 0',
            SmartAI::SRC_TYPE_ACTIONLIST, SmartAction::ACTION_WP_START
        ) ?: [];

        if ($talActions)
        {
            $owners = SmartAI::getActionListOwners(array_unique(array_column($talActions, 'talId')));

            $entries = [];
            foreach ($owners as $pairs)
                foreach ($pairs as [$srcType, $entry])
                    if ($srcType == Type::NPC)
                        $entries[$entry] = $entry;

            $pathIds = array_unique(array_column($talActions, 'pathId'));

            if ($entries && $pathIds)
            {
                $creatures = [];
                foreach (DB::World()->selectAssoc(
                   'SELECT `id`, MIN(`guid`) AS `guid`, MIN(`map`) AS `map`, MIN(`zoneId`) AS `zoneId`
                    FROM   creature WHERE `id` IN %in GROUP BY `id`',
                    array_values($entries)
                ) ?: [] as $c)
                    $creatures[$c['id']] = $c;

                $points = [];
                foreach (DB::World()->selectAssoc(
                   'SELECT `id`, `point`, `delay` AS `wait`, `position_x` AS `posX`, `position_y` AS `posY`
                    FROM   waypoint_data WHERE `id` IN %in',
                    array_values($pathIds)
                ) ?: [] as $w)
                    $points[$w['id']][] = $w;

                foreach ($talActions as ['talId' => $talId, 'pathId' => $pathId])
                {
                    if (empty($points[$pathId]))
                        continue;

                    foreach ($owners[$talId] ?? [] as [$srcType, $entry])
                    {
                        if ($srcType != Type::NPC || empty($creatures[$entry]))
                            continue;

                        $c = $creatures[$entry];
                        foreach ($points[$pathId] as $w)
                            $rows[] = [
                                'guid'           => $c['guid'],
                                'creatureOrPath' => -$pathId,
                                'kind'           => 0,
                                'point'          => $w['point'],
                                'areaId'         => $c['zoneId'],
                                'map'            => $c['map'],
                                'wait'           => $w['wait'],
                                'posX'           => $w['posX'],
                                'posY'           => $w['posY'],
                            ];
                    }
                }
            }
        }

        return $rows;
    }

    /** some cores/TDBs don't ship every optional table (see LegacyScript::tableExists() for the same check) */
    private function hasTable(string $tbl) : bool
    {
        static $known = [];

        return $known[$tbl] ??= (bool)DB::World()->selectCell('SHOW TABLES LIKE %s', $tbl);
    }

    private function transformPoint(array $point, int $type, ?string &$notice = '') : ?array
    {
        // npc/object is on a transport -> apply offsets to path of transport
        // note, that transport DO spawn outside of displayable area maps .. another todo i guess..
        if (isset($this->transports[$point['map']]))
        {
            $point['posX'] += $this->transports[$point['map']]['posX'];
            $point['posY'] += $this->transports[$point['map']]['posY'];
            $point['map']   = $this->transports[$point['map']]['mapId'];
        }

        $area  = $point['areaId'] ?? 0;
        $floor = -1;
        if (isset($this->overrideData[$type][$point['guid']]))
        {
            $area   = $this->overrideData[$type][$point['guid']]['areaId'];
            $floor  = $this->overrideData[$type][$point['guid']]['floor'];
            $notice = '[points] '.str_pad('['.$point['guid'].']', 9).' manually moved to [A:'.($point['areaId'] ?? 0).' => '.$area.'; F: '.$floor.']';
        }

        if ($points = WorldPosition::toZonePos($point['map'], $point['posX'], $point['posY'], $area, $floor))
        {
            // if areaId is set and we match it .. we're fine .. mostly
            if (count($points) == 1 && $area == $points[0]['areaId'])
                return [$points[0]['areaId'], $points[0]['posX'], $points[0]['posY'], $points[0]['floor']];

            $point = WorldPosition::checkZonePos($points);  // try to determine best found point by alphamap
            return [$point['areaId'], $point['posX'], $point['posY'], $point['floor']];
        }

        // cannot be placed on a map, try to reuse TC assigned areaId (note: area has been invalid in the past)
        if ($area && isset($this->areaParents[$area]))
            return [$this->areaParents[$area], 0, 0, 0];

        // we know the instanced map; try to assign a zone this way
        if (!in_array($point['map'], [0, 1, 530, 571]) && isset($this->mapToArea[$point['map']]))
            return [$this->mapToArea[$point['map']], 0, 0, 0];

        return null;
    }
});

?>
