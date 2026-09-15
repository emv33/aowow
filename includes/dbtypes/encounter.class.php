<?php

namespace Aowow;

if (!defined('AOWOW_REVISION'))
    die('illegal access');


/*
 * Dungeon and raid encounters.
 *
 * The backbone is the world DB `instance_encounters`, which is what the core actually credits -
 * it is always present and needs no setup, so this list queries DB::World() directly, the way
 * GossipList and the loot classes do.
 *
 * DungeonEncounter.dbc carries the encounter's real name, its map, difficulty mode and the order
 * it appears in the journal. That DBC is not pulled in by any setup step, so it is treated as
 * pure enrichment: where `dbc_dungeonencounter` exists the good names and the map are used,
 * otherwise the `comment` column of instance_encounters stands in.
 *
 * Known limitation: an encounter that exists only in the DBC and is never credited server-side
 * has no `instance_encounters` row and is therefore not listed.
 */
class EncounterList extends DBTypeList
{
    public static int    $type       = Type::ENCOUNTER;
    public static string $brickFile  = 'encounter';
    public static string $dataTable  = '';                  // world DB type; nothing aowow-side to flag with cuFlags
    public static int    $contribute = CONTRIBUTE_NONE;

    protected array  $dbNames  = ['World'];

    public const int CREDIT_KILL_CREATURE = 0;
    public const int CREDIT_CAST_SPELL    = 1;

    private static ?bool $hasDBC = null;

    // note: `id` is a select alias for the listview only - conditions must target `ie.entry`, as MySQL
    // does not resolve select aliases in WHERE
    protected string $queryBase = 'SELECT ie.`entry` AS ARRAY_KEY, ie.`entry` AS "id", ie.* FROM instance_encounters ie';
    protected array  $queryOpts = array(
                        'ie' => ['o' => 'ie.`entry` ASC']
                    );

    public function __construct(array $conditions = [], array $miscData = [])
    {
        parent::__construct($conditions, $miscData);

        $dbc = self::fetchDBC(array_keys($this->templates));

        foreach ($this->iterate() as $id => &$_curTpl)
        {
            $_curTpl['creditType']  = (int)($_curTpl['creditType']  ?? 0);
            $_curTpl['creditEntry'] = (int)($_curTpl['creditEntry'] ?? 0);
            $_curTpl['lastBoss']    = (int)($_curTpl['lastEncounterDungeon'] ?? 0);
            $_curTpl['mapId']       = (int)($dbc[$id]['map']   ?? 0);
            $_curTpl['mode']        = (int)($dbc[$id]['mode']  ?? 0);
            $_curTpl['order']       = (int)($dbc[$id]['order'] ?? 0);

            // the DBC name is the one the client shows; `comment` is a build comment that happens to hold it too
            $name = (string)($dbc[$id]['name'] ?? '');
            if (!$name)
                $name = trim((string)($_curTpl['comment'] ?? ''));

            $_curTpl['name'] = $name ?: Lang::encounter('unnamed', [$id]);
        }
    }

    /** DungeonEncounter.dbc is optional; run `php aowow --dbc=dungeonencounter` to have it */
    private static function hasDBC() : bool
    {
        return self::$hasDBC ??= (bool)DB::Aowow()->selectCell('SHOW TABLES LIKE %s', 'dbc_dungeonencounter');
    }

    private static function fetchDBC(array $ids) : array
    {
        if (!$ids || !self::hasDBC())
            return [];

        $loc  = Lang::getLocale();
        $rows = DB::Aowow()->selectAssoc(
           'SELECT `id` AS ARRAY_KEY, `map`, `mode`, `order`, `name_loc0`, `name_loc'.$loc->value.'` AS "name_loc"
            FROM   dbc_dungeonencounter WHERE `id` IN %in',
            $ids
        ) ?: [];

        $out = [];
        foreach ($rows as $id => $r)
            $out[$id] = array(
                'map'   => (int)$r['map'],
                'mode'  => (int)$r['mode'],
                'order' => (int)$r['order'],
                'name'  => (string)($r['name_loc'] ?: $r['name_loc0'])
            );

        return $out;
    }

    public static function getName(int $id) : ?LocString
    {
        $el = new self(array(['ie.entry', $id]));         // `id` is a select alias only; filter on the real column
        if ($el->error)
            return null;

        $n = ['name_loc0'                         => $el->getField('name'),
              'name_loc'.Lang::getLocale()->value => $el->getField('name')];

        return new LocString($n);
    }

    /** the area an encounter's map corresponds to, so the listing can link the instance */
    public static function mapsToAreas(array $mapIds) : array
    {
        if (!$mapIds = array_filter(array_map('intVal', $mapIds)))
            return [];

        return DB::Aowow()->selectPairs(
           'SELECT `mapId`, `id` FROM ::zones WHERE `mapId` IN %in AND `parentArea` = 0 AND (`cuFlags` & %i) = 0 GROUP BY `mapId`',
            $mapIds, CUSTOM_EXCLUDE_FOR_LISTVIEW
        ) ?: [];
    }

    /**
     * without DungeonEncounter.dbc an encounter has no map, so the instance cannot be resolved
     * through mapsToAreas(); most bosses are credited by killing a creature, and the zone that
     * creature spawns in is the instance
     */
    private static function creditAreas(array $templates) : array
    {
        $byEntry = [];
        foreach ($templates as $id => $tpl)
        {
            if ((int)($tpl['creditType'] ?? 0) == self::CREDIT_KILL_CREATURE && ($entry = (int)($tpl['creditEntry'] ?? 0)))
                $byEntry[$entry] = (int)$id;
        }

        if (!$byEntry)
            return [];

        $rows = DB::Aowow()->selectAssoc(
           'SELECT s.`typeId` AS ARRAY_KEY, MIN(z.`id`) AS "areaId"
            FROM   ::spawns s
            JOIN   ::zones z ON z.`id` = s.`areaId` AND z.`parentArea` = 0
            WHERE  s.`type` = %i AND s.`typeId` IN %in AND s.`areaId` > 0
            GROUP BY s.`typeId`',
            Type::NPC, array_keys($byEntry)
        ) ?: [];

        $out = [];
        foreach ($rows as $entry => $r)
            if (isset($byEntry[(int)$entry]))
                $out[$byEntry[(int)$entry]] = (int)$r['areaId'];

        return $out;
    }

    /**
     * the other encounters sharing a map
     * the map only exists in DungeonEncounter.dbc, so without it there are no siblings to find
     */
    public static function getIdsForMap(int $mapId, int $exclude = 0) : array
    {
        if ($mapId <= 0 || !self::hasDBC())
            return [];

        $ids = DB::Aowow()->selectCol('SELECT `id` FROM dbc_dungeonencounter WHERE `map` = %i ORDER BY `order` ASC', $mapId) ?: [];
        $ids = array_map('intVal', $ids);

        return $exclude ? array_values(array_diff($ids, [$exclude])) : $ids;
    }

    public function getListviewData() : array
    {
        $areas    = self::mapsToAreas(array_column($this->templates, 'mapId'));
        $fallback = self::creditAreas($this->templates);
        $data     = [];

        foreach ($this->iterate() as $id => $__)
        {
            $data[$id] = array(
                'id'       => $id,
                'name'     => $this->curTpl['name'],
                'mode'     => $this->curTpl['mode'],
                'order'    => $this->curTpl['order'],
                'lastboss' => $this->curTpl['lastBoss'] ? 1 : 0
            );

            if ($_ = ($areas[$this->curTpl['mapId']] ?? $fallback[$id] ?? 0))
                $data[$id]['area'] = $_;

            if ($this->curTpl['creditEntry'])
            {
                $data[$id]['credittype']  = $this->curTpl['creditType'];
                $data[$id]['creditentry'] = $this->curTpl['creditEntry'];
            }
        }

        return $data;
    }

    public function getJSGlobals(int $addMask = GLOBALINFO_ANY) : array
    {
        $data     = [];
        $areas    = self::mapsToAreas(array_column($this->templates, 'mapId'));
        $fallback = self::creditAreas($this->templates);

        foreach ($this->iterate() as $id => $__)
        {
            if ($this->curTpl['creditEntry'])
            {
                $type = $this->curTpl['creditType'] == self::CREDIT_CAST_SPELL ? Type::SPELL : Type::NPC;
                $data[$type][$this->curTpl['creditEntry']] = $this->curTpl['creditEntry'];
            }

            if ($_ = ($areas[$this->curTpl['mapId']] ?? $fallback[$id] ?? 0))
                $data[Type::ZONE][$_] = $_;
        }

        return $data;
    }

    // the base implementation looks for an `::aowow_*` table in queryBase and would fall over on a world DB type
    public function getRandomId() : int
    {
        return (int)DB::World()->selectCell('SELECT `entry` FROM instance_encounters ORDER BY RAND() ASC LIMIT 1');
    }

    public function renderTooltip() : ?string { return null; }
}

class EncounterListFilter extends Filter
{
    protected string $type = 'encounter';

    protected static array $genericFilter = array(
        2 => [parent::CR_NUMERIC, 'ie.entry',       NUM_CAST_INT], // id
        3 => [parent::CR_NUMERIC, 'ie.creditEntry', NUM_CAST_INT]  // credited entity
    );

    // fieldId => [checkType, checkValue[, fieldIsArray]]
    protected static array $inputFields = array(
        'cr'  => [parent::V_LIST,  [2, 3],              true ], // criteria ids
        'crs' => [parent::V_RANGE, [1, 4987],           true ], // criteria operators
        'crv' => [parent::V_REGEX, parent::PATTERN_INT, true ], // criteria values - all criteria are numeric here
        'na'  => [parent::V_NAME,  false,               false], // name - matched against the build comment
        'ma'  => [parent::V_EQUAL, 1,                   false], // match any / all filter
        'lb'  => [parent::V_EQUAL, 1,                   false]  // last boss of its dungeon only
    );

    protected function createSQLForValues() : array
    {
        $parts = [];
        $_v    = &$this->values;

        // name [str] - the real name lives in a dbc that may not be imported; `comment` is the only searchable column
        if ($_v['na'])
            if ($_ = $this->buildLikeLookup([['na', 'ie.comment']]))
                $parts[] = $_;

        // last boss [bool]
        if ($_v['lb'])
            $parts[] = ['ie.lastEncounterDungeon', 0, '>'];

        return $parts;
    }
}

?>
