<?php

namespace Aowow;

if (!defined('AOWOW_REVISION'))
    die('illegal access');


/*
 * The Spawns tab of an npc or object page: one row per `creature` / `gameobject` row of the entry.
 *
 * The map pins say where the spawns stand; nothing said what each one is. This lists every guid with
 * what the world DB holds about that one spawn - where, in which difficulties and phases, how fast it
 * comes back, its StringId - and every table keyed by its guid: a SmartAI script of its
 * own, spawn group, pool, world events, linked respawn. What only one of the two kinds has (a
 * creature's addon and formation, an object's state and rotation) comes from the page through
 * $typeSpecific.
 *
 * Some of that the infobox states for the whole entry (an event, a phase, an addon, equipment). It may
 * only do so when it holds for every spawn: getCommon() hands it the lines that are the same on every
 * spawn, and a line that is not stays on the spawns that have it.
 *
 * Built in two steps: the constructor reads everything, so the infobox can be filled from getCommon(),
 * and getListview() makes the tab once the page knows which SmartAI box shows which guid's own script.
 */
class SpawnTab
{
    // per kind: [spawn table, SmartAI source_type, spawn_group / pool_members spawn type, game_event table,
    //            linked_respawn linkTypes this spawn waits through => Type of the other end]
    private const array KINDS = array(
        Type::NPC    => ['creature',   SmartAI::SRC_TYPE_CREATURE, 0, 'game_event_creature',   [0 => Type::NPC,    1 => Type::OBJECT]],
        Type::OBJECT => ['gameobject', SmartAI::SRC_TYPE_OBJECT,   1, 'game_event_gameobject', [2 => Type::OBJECT, 3 => Type::NPC   ]]
    );

    private array $data    = [];                            // listview rows, guid => row
    private array $details = [];                            // guid => [markup line, ...], what only this spawn says
    private array $shared  = [];                            // guid => [key => markup line], what the infobox can say for all
    private array $ownSAI  = [];                            // guid => true, a SmartAI script of its own
    private array $tail    = [];                            // guid => [markup line, ...], after the SmartAI line
    private array $common  = [];                            // key => markup line, the same on every spawn
    private array $show    = ['phase' => false, 'mode' => false, 'event' => false, 'stringId' => false, 'movement' => false, 'state' => false];

    private static function hasTable(string $tbl) : bool
    {
        static $known = [];

        return $known[$tbl] ??= (bool)DB::World()->selectCell('SHOW TABLES LIKE %s', $tbl);
    }

    /** whether a zone has a map image to put a pin on - the same files the zone page looks for */
    private static function hasMapImage(int $zoneId) : bool
    {
        static $known = [];

        return $known[$zoneId] ??= (function () use ($zoneId) : bool
        {
            foreach ([Lang::getLocale()->json(), 'enus'] as $lang)
                foreach (['', '-1'] as $floor)
                    if (file_exists(sprintf('static/images/wow/maps/%s/%d%s.png', $lang, $zoneId, $floor)))
                        return true;

            return false;
        })();
    }

    /**
     * MapViewer's link to one point, as the [lightbox=map] tag builds it: zone[.floor]:pin, a pin being x and y in tenths of a percent;
     * null for a zone without a map image, which has nothing to show
     */
    public static function mapLink(int $areaId, int $floor, float $posX, float $posY) : ?string
    {
        if (!self::hasMapImage($areaId))
            return null;

        $pin = fn(float $v) : string => str_pad((string)min(999, max(0, (int)round($v * 10))), 3, '0', STR_PAD_LEFT);

        return $areaId.($floor > 1 ? '.'.($floor - 1) : '').':'.$pin($posX).$pin($posY);
    }

    /** world db text set into markup: a '[' of its own is escaped so Markup reads it as text */
    public static function esc(string $text) : string
    {
        return str_replace('[', '\\[', $text);
    }

    /**
     * @param \Closure|null $typeSpecific  fn(array $rows, int[] $guids) : array - guid => [column => value, 'details' => string[],
     *                                     'shared' => [key => string]]; `shared` lines are what the infobox would state for the
     *                                     whole entry and go there when every spawn has the same; the rows as read, keys lowercased
     */
    public function __construct(private TemplateResponse $resp, int $type, int $entry, ?\Closure $typeSpecific = null)
    {
        if (!isset(self::KINDS[$type]))
            return;

        [$table, $saiSrc, $spawnType, $eventTable, $linkTypes] = self::KINDS[$type];

        $rows = DB::World()->selectAssoc('SELECT * FROM %n WHERE `id` = %i ORDER BY `guid` ASC', $table, $entry) ?: [];
        if (!$rows)
            return;

        $rows    = array_map(fn($r) => array_change_key_case($r, CASE_LOWER), $rows);
        $guids   = array_map(fn($r) => (int)$r['guid'], $rows);
        $isStaff = User::canSee(U_GROUP_EMPLOYEE);         // StringId is staff data on these pages
        $byGuid  = fn(string $sql, ...$args) : array => DB::World()->selectAssoc($sql, ...$args) ?: [];

        // the zone and zone coordinates the map pins use; a spawn on several floors keeps its lowest
        $pos = [];
        foreach (DB::Aowow()->selectAssoc('SELECT `guid`, `areaId`, `floor`, `areaX`, `areaY` FROM %n WHERE `guid` IN %in ORDER BY `floor` DESC', WorldPosition::getStorageByType($type), $guids) ?: [] as $p)
            $pos[(int)$p['guid']] = $p;

        // spawnMask only means something on a map with several difficulties
        $maps     = array_values(array_unique(array_map(fn($r) => (int)$r['map'], $rows)));
        $mapTypes = DB::Aowow()->selectCol('SELECT `mapIdBak` AS ARRAY_KEY, `type` FROM ::zones WHERE `mapIdBak` IN %in AND `parentArea` = 0', $maps) ?: [];

        $this->ownSAI = array_fill_keys(array_map(fn($x) => -(int)$x, DB::World()->selectCol(
            'SELECT DISTINCT `entryorguid` FROM smart_scripts WHERE `source_type` = %i AND `entryorguid` IN %in', $saiSrc, array_map(fn($g) => -$g, $guids)
        ) ?: []), true);

        $groups = [];
        if (self::hasTable('spawn_group'))
            foreach ($byGuid('SELECT sg.`spawnId`, sg.`groupId`, sgt.`GroupName` FROM spawn_group sg LEFT JOIN spawn_group_template sgt ON sgt.`groupId` = sg.`groupId` WHERE sg.`spawnType` = %i AND sg.`spawnId` IN %in', $spawnType, $guids) as $g)
                $groups[(int)$g['spawnId']][] = self::esc((string)($g['GroupName'] ?: Lang::xRef('unnamedGroup', [(int)$g['groupId']])));

        $pools = [];
        if (self::hasTable('pool_members'))
            foreach ($byGuid('SELECT `spawnId`, `poolSpawnId`, `chance` FROM pool_members WHERE `type` = %i AND `spawnId` IN %in', $spawnType, $guids) as $p)
                $pools[(int)$p['spawnId']][] = '[url=?spawnpool='.(int)$p['poolSpawnId'].']#'.(int)$p['poolSpawnId'].'[/url]'.((float)$p['chance'] ? ' ('.Lang::nf((float)$p['chance'], 1).'%)' : '');

        // a negative event spawns the guid outside of the event, i.e. removes it while the event runs
        $events = [];
        if (self::hasTable($eventTable))
            foreach ($byGuid('SELECT `guid`, `eventEntry` FROM %n WHERE `guid` IN %in ORDER BY `eventEntry`', $eventTable, $guids) as $e)
            {
                $ev = (int)$e['eventEntry'];
                $resp->extendGlobalIds(Type::WORLDEVENT, abs($ev));
                $events[(int)$e['guid']][] = ($ev < 0 ? Lang::spawns('eventRemoved') : Lang::spawns('eventAdded')).' [event='.abs($ev).']';
            }

        // linked_respawn: this spawn (guid) waits on another (linkedGuid); the linkType says which kinds both ends are
        $links = [];
        if (self::hasTable('linked_respawn'))
            foreach ($byGuid('SELECT `guid`, `linkType`, `linkedGuid` FROM linked_respawn WHERE `linkType` IN %in AND `guid` IN %in', array_keys($linkTypes), $guids) as $l)
                $links[(int)$l['guid']] = [$linkTypes[(int)$l['linkType']], (int)$l['linkedGuid']];

        $linkedEntries = [];
        foreach ([Type::NPC => 'creature', Type::OBJECT => 'gameobject'] as $t => $tbl)
            if ($g = array_values(array_unique(array_map(fn($x) => $x[1], array_filter($links, fn($x) => $x[0] == $t)))))
                $linkedEntries[$t] = DB::World()->selectCol('SELECT `guid` AS ARRAY_KEY, `id` FROM %n WHERE `guid` IN %in', $tbl, $g) ?: [];

        // a spawn on a transport has the transport's own map (gameobject_template.data6 of a MO_TRANSPORT) as its map;
        // the transport's page lists it as a passenger, this links back. A transport map can be shared by several transports.
        $transports = [];
        foreach (DB::World()->selectAssoc('SELECT `entry`, `data6` FROM gameobject_template WHERE `type` = %i AND `data6` <> 0 AND `data6` IN %in', GO_TYPE_MO_TRANSPORT, $maps) ?: [] as $t)
            $transports[(int)$t['data6']][] = (int)$t['entry'];

        $specific = $typeSpecific ? $typeSpecific($rows, $guids) : [];

        foreach ($rows as $r)
        {
            $guid    = (int)$r['guid'];
            $respawn = (int)$r['spawntimesecs'];
            $row     = ['id' => $guid, 'respawn' => $respawn, 'respawnText' => $respawn ? Lang::formatTime(abs($respawn) * 1000) : '-', 'mode' => '', 'event' => '', 'stringId' => '', 'movement' => '', 'state' => ''];

            // where
            if ($p = $pos[$guid] ?? null)
            {
                $resp->extendGlobalIds(Type::ZONE, (int)$p['areaId']);
                $row['zone']     = (int)$p['areaId'];
                $row['location'] = '[zone='.(int)$p['areaId'].'] [small class=q0]('.Lang::nf((float)$p['areaX'], 1).', '.Lang::nf((float)$p['areaY'], 1).')[/small]';

                // a zone without a map image gets no link, so its row is not clickable
                if ($link = self::mapLink((int)$p['areaId'], (int)$p['floor'], (float)$p['areaX'], (float)$p['areaY']))
                    $row['map'] = $link;
            }
            else
                $row['location'] = WorldPosition::getMapNames([(int)$r['map']])[(int)$r['map']] ?? ('Map #'.(int)$r['map']);

            // difficulties
            $kind = match ((int)($mapTypes[(int)$r['map']] ?? -1))
            {
                MAP_TYPE_DUNGEON_HC                         => CreatureList::MODE_KIND_DUNGEON,
                MAP_TYPE_MMODE_RAID, MAP_TYPE_MMODE_RAID_HC => CreatureList::MODE_KIND_RAID,
                default                                     => null
            };
            if ($kind !== null && ($labels = CreatureList::modeLabels($kind, (int)$r['spawnmask'])))
                $row['mode'] = implode(', ', array_column($labels, 1));

            // the world events that add or remove this spawn
            if ($_ = $events[$guid] ?? null)
                $row['event'] = implode('[br]', $_);

            if (($ph = (int)$r['phasemask']) != 1)
                $row['phase'] = $ph;

            if ($isStaff)
                $row['stringId'] = trim((string)($r['stringid'] ?? ''));

            // world position: x, y, z, orientation - what .go xyz takes
            $row['position'] = implode(', ', array_map(fn($k) => Lang::nf((float)$r[$k], 2), ['position_x', 'position_y', 'position_z', 'orientation']));

            // details: everything else the guid carries
            $this->details[$guid] = [];
            $this->shared[$guid]  = [];

            foreach ($specific[$guid] ?? [] as $k => $v)
            {
                if ($k == 'details')
                    array_push($this->details[$guid], ...$v);
                else if ($k == 'shared')
                    $this->shared[$guid] = $v;
                else
                    $row[$k] = $v;
            }

            if ($_ = $transports[(int)$r['map']] ?? null)
            {
                $resp->extendGlobalIds(Type::OBJECT, ...$_);
                $this->shared[$guid]['transport'] = Util::ucFirst(Lang::game('transport')).Lang::main('colon').implode(', ', array_map(fn($x) => '[object='.$x.']', $_));
            }

            $tail = [];
            if ($_ = $groups[$guid] ?? null)
                $tail[] = Lang::xRef('spawnGroup').Lang::main('colon').implode(', ', $_);

            if ($_ = $pools[$guid] ?? null)
                $tail[] = Lang::spawns('pool').Lang::main('colon').implode(', ', $_);

            if ($l = $links[$guid] ?? null)
            {
                [$lType, $lGuid] = $l;
                $lEntry = (int)($linkedEntries[$lType][$lGuid] ?? 0);
                if ($lEntry)
                    $resp->extendGlobalIds($lType, $lEntry);

                $tail[] = Lang::xRef('respawnsWith').Lang::main('colon').($lEntry ? '['.Type::getFileString($lType).'='.$lEntry.'] ' : '').'[small class=q0](GUID: '.$lGuid.')[/small]';
            }

            $this->tail[$guid] = $tail;
            $this->data[$guid] = $row;
        }

        // the infobox lines that hold for every spawn: a shared line every spawn has the same of, and the event and phase columns
        // when every row reads the same - those leave the tab, as the infobox already says them
        $first = reset($this->shared);
        foreach ($first as $key => $line)
            if (!array_filter($this->shared, fn($s) => ($s[$key] ?? null) !== $line))
                $this->common[$key] = $line;

        $events = array_values(array_unique(array_column($this->data, 'event')));
        if (count($events) == 1 && $events[0] !== '')
            $this->common['event'] = $events[0];

        $phases = array_values(array_unique(array_map(fn($r) => $r['phase'] ?? 1, $this->data)));
        if (count($phases) == 1 && $phases[0] != 1)
            $this->common['phase'] = Lang::npcExtra('phases').Lang::main('colon').'0x'.strtoupper(dechex((int)$phases[0]));

        foreach (['event' => 'event', 'phase' => 'phase'] as $key => $col)
            if (!isset($this->common[$key]))
                foreach ($this->data as $row)
                    if (!empty($row[$col]) && ($col != 'phase' || $row[$col] != 1))
                        $this->show[$col] = true;

        foreach (['mode', 'stringId', 'movement', 'state'] as $col)
            foreach ($this->data as $row)
                if (($row[$col] ?? '') !== '')
                    $this->show[$col] = true;
    }

    public function hasSpawns() : bool
    {
        return !empty($this->data);
    }

    /** @return array  key => markup line, what every spawn has the same of; the event as its own `event` key */
    public function getCommon() : array
    {
        return $this->common;
    }

    /** @param array $saiBoxes  guid => id of the page's SmartAI box with that guid's own script, to link it */
    public function getListview(array $saiBoxes = []) : ?Listview
    {
        if (!$this->data)
            return null;

        $data = [];
        foreach ($this->data as $guid => $row)
        {
            $d = $this->details[$guid];
            foreach ($this->shared[$guid] as $key => $line)
                if (!isset($this->common[$key]))
                    $d[] = $line;

            // spawn.tpl opens the box (it may be collapsed) and scrolls to it
            if (isset($this->ownSAI[$guid]))
                $d[] = isset($saiBoxes[$guid]) ? '[url=#'.$saiBoxes[$guid].']'.Lang::spawns('ownSmartAI').'[/url]' : Lang::spawns('ownSmartAI');

            array_push($d, ...$this->tail[$guid]);

            $row['details'] = implode('[br]', $d);
            $data[] = $row;
        }

        $hidden = array_keys(array_filter($this->show, fn($x) => !$x));
        if (!array_filter(array_column($data, 'details')))
            $hidden[] = 'details';

        return new Listview(array(
            'data'       => $data,
            'id'         => 'spawns',
            'name'       => Lang::spawns('title'),
            'hiddenCols' => $hidden ?: null
        ), 'spawn', 'spawn');
    }
}

?>
