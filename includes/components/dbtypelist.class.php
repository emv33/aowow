<?php

namespace Aowow;

if (!defined('AOWOW_REVISION'))
    die("illegal access");


abstract class DBTypeList
{
    protected array  $templates = [];
    protected array  $curTpl    = [];
    protected int    $matches   = 0;                        // total matches unaffected by sqlLimit in config

    protected array  $dbNames   = ['Aowow'];                // multiple DBs in profiler
    protected string $queryBase = '';
    protected array  $queryOpts = [];

    private array $itrStack = [];
    private array $prefixes = [];

    public static int        $type;
    public static int        $contribute = CONTRIBUTE_ANY;
    public static string     $dataTable;
    public        string|int $id         = 0;               // sooo .. everything is int, except profiler related stuff, whose keys are <realmId>:<subjectGUID>
    public        bool       $error      = true;

    /*
    *   condition as array [expression, value, operator]
    *       expression:    str   - must match fieldname;
    *                      int   - 1: select everything; 0: select nothing
    *                      array - another condition array
    *       value:         str   - operator defaults to: = <val>
    *                      num   - operator defaults to: = <val>
    *                      array - operator defaults to: IN (<val>)
    *                      null  - operator defaults to: IS [NULL]
    *       operator:      modifies/overrides default
    *                      ! - negated default value (NOT LIKE; <>; NOT IN)
    *                      MATCH - creates fulltext search ('value' must be array; column must have fulltext index)
    *                      LIKE / NOT LIKE - partial string matching ('value' must be string (*d'uh*))
    *   condition as str
    *       defines linking (AND || OR)
    *   condition as int
    *       defines LIMIT
    *
    *   example:
    *       array(
    *           ['id', 45],
    *           ['name', 'test%', '!'],
    *           [
    *               DB::AND,
    *               ['flags', 0xFF, '&'],
    *               ['flags2', 0xF, '&'],
    *           ]
    *           [['mask', 0x3, '&'], 0],
    *           ['nameField', ['+contains*', '-excludes'], 'MATCH],
    *           ['joinedTbl.field', NULL]                   // NULL must be explicitly specified "['joinedTbl.field']" would be skipped as erroneous definition (only really usefull when left-joining)
    *           DB::OR,
    *           5
    *       )
    *   results in
    *       WHERE ((`id` = 45) OR (`name` NOT LIKE "test%") OR ((`flags` & 255) AND (`flags2` & 15)) OR ((`mask` & 3) = 0)) OR (MATCH(`nameField`) AGAINST("+contains* -excludes" IN BOOLEAN MODE)) OR (`joinedTbl`.`field` IS NULL) LIMIT 5
    */
    public function __construct(array $conditions = [], array $miscData = [])
    {
        $where   = [];
        $linking = DB::AND;
        $limit   = 0;

        $calcTotal  = false;
        $totalQuery = '';

        if (!$this->queryBase || $conditions === null)
            return;

        if (preg_match('/FROM (?:::)?[\w\_]+( AS)?\s?`?(\w+)`?$/i', $this->queryBase, $match))
            $this->prefixes['base'] = $match[2];
        else
            $this->prefixes['base'] = '';

        if (!empty($miscData['extraOpts']))
            $this->extendQueryOpts($miscData['extraOpts']);

        if (!empty($miscData['calcTotal']))
            $calcTotal = true;

        foreach ($conditions as $i => $c)
        {
            switch (getType($c))
            {
                case 'array':
                    break;
                case 'string':
                case 'integer':
                    if (is_numeric($c))
                        $limit = max(0, (int)$c);
                    else if ($c === DB::AND)
                        $linking = DB::AND;
                    else if ($c === DB::OR)
                        $linking = DB::OR;
                default:
                    unset($conditions[$i]);
            }
        }

        foreach ($conditions as $c)
            if ($x = $this->resolveCondition($c, $linking))
                $where[] = $x;

        // optional query parts may require other optional parts to work
        foreach ($this->prefixes as $pre)
            if (isset($this->queryOpts[$pre][0]))
                foreach ($this->queryOpts[$pre][0] as $req)
                    if (!in_array($req, $this->prefixes))
                        $this->prefixes[] = $req;

        // remove optional query parts, that are not required
        foreach ($this->queryOpts as $k => $arr)
            if (!in_array($k, $this->prefixes))
                unset($this->queryOpts[$k]);

        // prepare usage of guids if using multiple realms (which have non-zoro indizes)
        if (key($this->dbNames) != 0)
            $this->queryBase = preg_replace('/\s([^\s]+)\sAS\sARRAY_KEY/i', ' CONCAT("DB_IDX", ":", \1) AS ARRAY_KEY', $this->queryBase);

        // insert additional selected fields
        if ($s = array_column($this->queryOpts, 's'))
            $this->queryBase = str_replace('ARRAY_KEY', 'ARRAY_KEY '.implode('', $s), $this->queryBase);

        // append joins
        if ($j = array_column($this->queryOpts, 'j'))
            foreach ($j as $_)
                $this->queryBase .= is_array($_) ? (empty($_[1]) ? ' JOIN ' : ' LEFT JOIN ').$_[0] : ' JOIN '.$_;

        // append conditions
        if ($where)
            $this->queryBase .= ' WHERE '.$linking;

        // append grouping
        if ($g = array_filter(array_column($this->queryOpts, 'g')))
            $this->queryBase .= ' GROUP BY '.implode(', ', $g);

        // append post filtering
        if ($h = array_filter(array_column($this->queryOpts, 'h')))
            $this->queryBase .= ' HAVING '.implode(' AND ', $h);

        // fill in locale
        $this->queryBase = str_replace(['DB_LOC_I', 'DB_LOC_S'], [Lang::getLocale()->value, '"'.Lang::getLocale()->json().'"'], $this->queryBase);

        // without applied LIMIT and ORDER
        if ($calcTotal)
            $totalQuery = $this->queryBase;

        // append ordering
        if ($o = array_filter(array_column($this->queryOpts, 'o')))
            $this->queryBase .= ' ORDER BY '.implode(', ', $o);

        // apply limit
        if ($limit)
            $this->queryBase .= ' LIMIT '.$limit;

        // execute query (finally)
        // this is purely because of multiple realms per server
        foreach ($this->dbNames as $dbIdx => $n)
        {
            try                                             // does not go through the compatibility layer as we need to be able to fetch individual rows here
            {
                $query  = str_replace('DB_IDX', $dbIdx, $this->queryBase);
                $result = DB::{$n}($dbIdx)->query($query, $where);

                if ($calcTotal && $result->getRowCount())
                {
                    // hackfix the inner items query to not contain duplicate column names
                    // yes i know the real solution would be to not have items and item_stats share column names
                    // soon™....
                    if (get_class($this) == ItemList::class)
                        $totalQuery = str_replace([', `is`.*', ', i.`id` AS "id"'], '', $totalQuery);

                    $this->matches += DB::{$n}($dbIdx)->selectCell('SELECT COUNT(*) FROM ('.$totalQuery.') x', $where);
                }

                foreach ($result->getIterator() as $row)
                {
                    // just .. roll with the unparsed, deprecated ARRAY_KEY, hmk?
                    if (isset($this->templates[$row['ARRAY_KEY']]))
                        trigger_error('GUID for List already in use #'.$row['ARRAY_KEY'].'. Additional occurrence omitted!', E_USER_WARNING);
                    else
                        $this->templates[$row['ARRAY_KEY']] = (array)$row;
                }

                $result->free();
            }
            catch (\Exception $e) {}                        // logged via \Dibi\Event in DB::errorLogger
        }

        if (!$this->templates)
            return;

        // push first element for instant use
        $this->reset();

        // all clear
        $this->error = false;
    }

    private function resolveCondition(array $c, string $supLink) : ?array
    {
        if (!$c)
            return null;

        // i am recursive subcondition
        if ($subLink = array_find($c, fn($x) => $x === DB::AND || $x === DB::OR))
        {
            $sql = [];

            foreach ($c as $foo)
                if (is_array($foo))
                    if ($x = $this->resolveCondition($foo, $supLink))
                        $sql[] = $x;

            return $sql ? [$subLink, $sql] : null;
        }

        [$expOrField, $value, $op] = array_pad($c, 3, null);

        if (is_numeric($expOrField))
            return [$expOrField ? 1 : 0];                   // [1] / [0]
        if (!$expOrField)                                   // '', null, []
            return null;

        $literal = false;

        if (is_array($expOrField) && $op != 'MATCH')
            $field = $this->resolveCondition($expOrField, $supLink);
        else
        {
            // basic formulas ex: [((minGold + maxGold) / 2), 0, '>']
            if (is_string($expOrField) && preg_match('/^\([\s\+\-\*\/\w\(\)\.]+\)$/i', strtr($expOrField, ['`' => '', '´' => '', '--' => ''])))
            {
                $field   = preg_replace_callback('/[\w\]*\.?[\w]+/i', fn($m) => $this->setColPrefix($m[0]), $expOrField);
                $literal = true;
            }
            else
                $field = $this->setColPrefix($expOrField);

            if (!$field)
                return null;
        }

        $neg  = $op === '!';
        $expr = match (gettype($value))
        {
            'integer' => ($neg ? '<>'     : ($op ?: '=')) . ' %i',
            'double'  => ($neg ? '<>'     : ($op ?: '=')) . ' %f',
            'string'  => ($neg ? '<>'     : ($op ?: '=')) . ' %s',
            'NULL'    => ($neg ? 'IS NOT' : 'IS')         . ' %sN',
            'array'   => ($neg ? 'NOT IN' : 'IN')         . ' %in',
            default   => null
        };

        if (!$expr)
            return null;

        if ($op == 'MATCH' && gettype($value) == 'array')
            return ['MATCH(%n)', $field, 'AGAINST(%s IN BOOLEAN MODE)', DB::Aowow()->translate($value)];
        if (($op == 'LIKE' || $op == 'NOT LIKE') && gettype($value) == 'string')
            return ['%n', $field, $op, '%~like~', $value];
        if (is_array($field))                               // $field is expression: [[flags, 0x4, '&'], 0] -> (`flags` & 4) = 0
            return [...$field, $expr, $value];

        return [$literal ? '%SQL' : '%n', $field, $expr, $value];
    }

    private function setColPrefix(mixed $colName) : null|array|string
    {
        if (is_array($colName))
            return array_filter(array_map($this->setColPrefix(...), $colName)) ?: null;

        // numeric allows for formulas e.g. (1 < 3)
        if (Util::checkNumeric($colName))
            return $colName;

        // skip condition if fieldName contains illegal chars
        if (preg_match('/[^\d\w\.\_]/i', $colName))
            return null;

        [$prefix, $col, $err] = array_pad(explode('.', $colName), 3, null);

        if ($err)                                           // more than one period
            return null;
        if (!$col)                                          // prefix not set, so everything is shifted to the left :/
            return $this->prefixes['base'].'.'.$prefix;

        if (!in_array($prefix, $this->prefixes))
        {
            // choose table to join or return null if prefix does not exist
            if (!in_array($prefix, array_keys($this->queryOpts)))
                return null;

            $this->prefixes[] = $prefix;
        }

        return $prefix.'.'.$col;
    }

    /**
     * iterate over fetched templates
     *
     * @return \Generator the current template
     */
    public function &iterate() : \Generator
    {
        if (!$this->templates)
            return;

        $this->itrStack[] = $this->id;

        // reset on __construct
        $this->reset();

        foreach ($this->templates as $id => $__)
        {
            $this->id     = $id;
            $this->curTpl = &$this->templates[$id];         // do not use $tpl from each(), as we want to be referenceable

            yield $id => $this->curTpl;

            unset($this->curTpl);                           // kill reference or it will 'bleed' into the next iteration
        }

        // fforward to old index
        $this->reset();
        $oldIdx = array_pop($this->itrStack);
        do
        {
            if (key($this->templates) != $oldIdx)
                continue;

            $this->curTpl = current($this->templates);
            $this->id     = key($this->templates);
            next($this->templates);
            break;
        }
        while (next($this->templates));
    }

    protected function reset() : void
    {
        unset($this->curTpl);                               // kill reference or strange stuff will happen
        if (!$this->templates)
            return;

        $this->curTpl = reset($this->templates);
        $this->id     = key($this->templates);
    }

    // read-access to templates
    public function getEntry(string|int $id) : ?array
    {
        if (isset($this->templates[$id]))
        {
            unset($this->curTpl);                           // kill reference or strange stuff will happen
            $this->curTpl = $this->templates[$id];
            $this->id     = $id;
            return $this->templates[$id];
        }

        return null;
    }

    public function getField(string $field, bool $localized = false, bool $silent = false) : mixed
    {
        if (!$this->curTpl || (!$localized && !isset($this->curTpl[$field])))
            return '';

        if ($localized)
            return Util::localizedString($this->curTpl, $field, $silent);

        $value = $this->curTpl[$field];
        Util::checkNumeric($value);

        return $value;
    }

    public function getAllFields(string $field, bool $localized = false, bool $silent = false) : array
    {
        $data = [];

        foreach ($this->iterate() as $__)
            $data[$this->id] = $this->getField($field, $localized, $silent);

        return $data;
    }

    public function getRandomId() : int
    {
        if (preg_match('/SELECT .*? FROM (::[\w_-]+) /i', $this->queryBase, $m))
            return DB::Aowow()->selectCell('SELECT `id` FROM %n WHERE (`cuFlags` & %i) = 0  ORDER BY RAND() ASC LIMIT 1', $m[1], User::canSee(U_GROUP_EMPLOYEE) ? 0 : CUSTOM_EXCLUDE_FOR_LISTVIEW) ?: 0;

        return 0;
    }

    public function getFoundIDs() : array
    {
        return array_keys($this->templates);
    }

    public function getMatches() : int
    {
        return $this->matches;
    }

    protected function extendQueryOpts(array $extra) : void // needs to be called from __construct
    {
        foreach ($extra as $tbl => $sets)
        {
            foreach ($sets as $module => $value)
            {
                if (!$value || !is_array($value))
                    continue;

                switch ($module)
                {
                    // additional (str)
                    case 'g':                               // group by
                    case 's':                               // select
                        if (!empty($this->queryOpts[$tbl][$module]))
                            $this->queryOpts[$tbl][$module] .= implode(' ', $value);
                        else
                            $this->queryOpts[$tbl][$module] = implode(' ', $value);

                        break;
                    case 'h':                               // having
                        if (!empty($this->queryOpts[$tbl][$module]))
                            $this->queryOpts[$tbl][$module] .= implode(' AND ', $value);
                        else
                            $this->queryOpts[$tbl][$module] = implode(' AND ', $value);

                        break;
                    // additional (arr)
                    case 'j':                               // join
                        if (!empty($this->queryOpts[$tbl][$module]) && is_array($this->queryOpts[$tbl][$module]))
                            $this->queryOpts[$tbl][$module][0][] = $value;
                        else
                            $this->queryOpts[$tbl][$module] = $value;

                        break;
                    // replacement (str)
                    case 'l':                               // limit
                    case 'o':                               // order by
                        $this->queryOpts[$tbl][$module] = $value[0];
                        break;
                }
            }
        }
    }

    public static function getName(int $id) : ?LocString
    {
        if ($n = DB::Aowow()->SelectRow('SELECT `name_loc0`, `name_loc2`, `name_loc3`, `name_loc4`, `name_loc6`, `name_loc8` FROM %n WHERE `id` = %i', static::$dataTable, $id))
            return new LocString($n);
        return null;
    }

    public static function makeLink(int $id, int $fmt = Lang::FMT_HTML, string $cssClass = '') : string
    {
        if ($n = static::getName($id))
            return Lang::makeLink(static::$type, $id, $n, $fmt, $cssClass);

        return '';
    }

    /* source More .. keys seen used
         'n':   name [always set]
         't':   type [always set]
        'ti':   typeId [always set]
        'bd':   BossDrop [0; 1] [Creature / GO]
        'dd':   DungeonDifficulty [-2: DungeonHC; -1: DungeonNM; 1: Raid10NM; 2:Raid25NM; 3:Raid10HM; 4: Raid25HM; 99: filler trash] [Creature / GO]
         'q':   cssQuality [Items]
         'z':   zone [set when all happens in here]
         'p':   PvP [pvpSourceId]
         's':   Type::TITLE: side; Type::SPELL: skillId (yeah, double use. Ain't life just grand)
         'c':   category [Spells / Quests]
        'c2':   subCat [Quests]
      'icon':   iconString
    */
    public function getSourceData(int $id = 0) : array { return []; }

    // should return data required to display a listview of any kind
    // this is a rudimentary example, that will not suffice for most Types
    abstract public function getListviewData() : array;

    // should return data to extend global js variables for a certain type (e.g. g_items)
    abstract public function getJSGlobals(int $addMask = GLOBALINFO_ANY) : array;

    // NPC, GO, Item, Quest, Spell, Achievement, Profile would require this
    abstract public function renderTooltip() : ?string;
}

trait listviewHelper
{
    public function hasSetFields(?string ...$fields) : int
    {
        $result = 0x0;

        foreach ($this->iterate() as $__)
        {
            foreach ($fields as $k => $str)
            {
                if (!$str)
                {
                    unset($fields[$k]);
                    continue;
                }

                if ($this->getField($str))
                {
                    $result |= 1 << $k;
                    unset($fields[$k]);
                }
            }

            if (empty($fields))                             // all set .. return early
            {
                $this->reset();                             // Generators have no __destruct, reset manually, when not doing a full iteration
                return $result;
            }
        }

        return $result;
    }

    public function hasDiffFields(?string ...$fields) : int
    {
        $base   = [];
        $result = 0x0;

        foreach ($fields as $k => $str)
            $base[$str] = $this->getField($str);

        foreach ($this->iterate() as $__)
        {
            foreach ($fields as $k => $str)
            {
                if (!$str)
                {
                    unset($fields[$k]);
                    continue;
                }

                if ($base[$str] != $this->getField($str))
                {
                    $result |= 1 << $k;
                    unset($fields[$k]);
                }
            }

            if (empty($fields))                             // all fields diff .. return early
            {
                $this->reset();                             // Generators have no __destruct, reset manually, when not doing a full iteration
                return $result;
            }
        }

        return $result;
    }

    public function hasAnySource() : bool
    {
        if (!isset($this->sources))
            return false;

        foreach ($this->sources as $src)
        {
            if (!is_array($src))
                continue;

            if (!empty($src))
                return true;
        }

        return false;
    }
}

/*
    !IMPORTANT!
    It is flat out impossible to distinguish between floors for multi-level areas, if the floors overlap each other!
    The coordinates generated by the script WILL be on every level and will have to be removed MANUALLY!

    impossible := you are not keen on reading wmo-data;
*/
trait spawnHelper
{
    private string $spawnsTable = '';
    private array  $spawnResult = array(
        SPAWNINFO_FULL  => null,
        SPAWNINFO_SHORT => null,
        SPAWNINFO_ZONES => null,
        SPAWNINFO_QUEST => null
    );

    private function setSpawnsTable() : bool
    {
        if (empty(self::$type))
            return false;

        return !!($this->spawnsTable = WorldPosition::getStorageByType(self::$type));
    }

    private function createShortSpawns() : void             // [zoneId, floor, [[x1, y1], [x2, y2], ..]] as tooltip2 if enabled by <a rel="map" ...> or anchor #map (one area, one floor, one creature, no survivors)
    {
        $this->spawnResult[SPAWNINFO_SHORT] = new \StdClass;

        // first get zone/floor with the most spawns
        if ($res = DB::Aowow()->selectRow('SELECT `areaId`, `floor` FROM %n WHERE `typeId` = %i AND `areaX` > 0 AND `areaY` > 0 GROUP BY `areaId`, `floor` ORDER BY COUNT(1) DESC LIMIT 1', $this->spawnsTable, $this->id))
        {
            // get relevant spawn points
            $points = DB::Aowow()->selectAssoc('SELECT `areaX`, `areaY` FROM %n WHERE `typeId` = %i AND `areaId` = %i AND `floor` = %i AND `areaX` > 0 AND `areaY` > 0', $this->spawnsTable, $this->id, $res['areaId'], $res['floor']);
            $spawns = [];
            foreach ($points as $p)
                $spawns[] = [$p['areaX'], $p['areaY']];

            $this->spawnResult[SPAWNINFO_SHORT]->zone   = $res['areaId'];
            $this->spawnResult[SPAWNINFO_SHORT]->coords = [$res['floor'] => $spawns];
        }
    }

    // for display on map (object/npc detail page)
    private function createFullSpawns(bool $skipWPs = false, bool $skipAdmin = false, bool $hasLabel = false, bool $hasLink = false) : void
    {
        $data     = [];
        $wpSum    = [];
        $wpIdx    = 0;
        $worldPos = [];
        $spawns   = DB::Aowow()->selectAssoc(
           'SELECT CASE WHEN z.`type` = %i THEN 1
                        WHEN z.`type` = %i THEN 2
                        WHEN z.`type` = %i THEN 2
                        ELSE 0
                   END AS "mapType", s.*
            FROM   %n s
            JOIN   ::zones z ON s.areaId = z.id
            WHERE s.`typeId` IN %in AND s.`areaX` > 0 AND s.`areaY` > 0',
            MAP_TYPE_DUNGEON_HC, MAP_TYPE_MMODE_RAID, MAP_TYPE_MMODE_RAID_HC,
            $this->spawnsTable, $this->getFoundIDs()
        ) ?: [];

        // vehicle accessories and transport passengers are moved with their parent
        if (!$skipAdmin && User::isInGroup(U_GROUP_MODERATOR))
            if ($guids = array_column(array_filter($spawns, fn($x) => !($x['flags'] & (0xFFF00 | SPAWNTYPE_VEHICLE_ACCESSORY))), 'guid'))
                $worldPos = WorldPosition::getForGUID(self::$type, ...$guids);

        // aowow - custom: what a single spawn row changes over its template
        $spawnExtra = $skipAdmin ? [] : self::getSpawnExtras(array_column(array_filter($spawns, fn($x) => $x['guid'] > 0), 'guid'));

        foreach ($spawns as $s)
        {
            $typeId     = $s['typeId'];
            $flags      = $s['flags'];
            $areaId     = $s['areaId'];
            $floor      = $s['floor'];
            $posX       = $s['areaX'];
            $posY       = $s['areaY'];
            $mapType    = $s['mapType'];
            $guid       = $s['guid']       ?? $typeId;
            $respawn    = $s['respawn']    ?? 0;
            $spawnMask  = $s['spawnMask']  ?? 0;
            $phaseMask  = $s['phaseMask']  ?? 0;
            $pathId     = $s['pathId']     ?? 0;
            $scriptName = $s['ScriptName'] ?? null;
            $stringId   = $s['StringId']   ?? null;
            $continents = [];                               // aowow - custom

            // check, if we can attach waypoints to creature
            // we will get a nice clusterfuck of dots if we do this for more GUIDs, than we have colors though
            if (!$skipWPs && count($spawns) < 6 && $pathId)
            {
                $wPoints = match (self::$type)
                {
                    Type::NPC    => DB::Aowow()->selectAssoc('SELECT * FROM ::creature_waypoints WHERE `kind` = %i AND `pathId` = %i AND `floor` = %i ORDER BY `point` ASC', WaypointPathList::KIND_MOVEMENT, $pathId, $floor),   // aowow - custom: also script_waypoint paths (kind), so pick the movement one (upstream: pathId only)
                    Type::OBJECT => DB::Aowow()->selectAssoc('SELECT * FROM ::objects_waypoints  WHERE `pathId` = %i AND `floor` = %i', $pathId, $floor),
                    default      => []

                };

                // aowow - custom start: a path crossing zones also goes on the map of each continent it touches, the whole route in one view (as on ?taxipath)
                $cPoints  = [];
                $crossing = count(array_unique(array_column($wPoints, 'areaId'))) > 1;
                // aowow - custom end

                foreach ($wPoints as $i => $p)
                {
                    $label = [Lang::npc('waypoint').Lang::main('colon').$p['point']];

                    if ($p['wait'])
                        $label[] = Lang::npc('wait').Lang::main('colon').DateTime::formatTimeElapsedFloat($p['wait']);
                    if (!empty($p['arrivalEventId']))       // gameobjects
                        $label[] = 'on arrival: emit event #'.$p['arrivalEventId'];
                    if (!empty($p['departureEventId']))     // gameobjects
                        $label[] = 'on departure: emit event #'.$p['departureEventId'];
                    if (!empty($p['action']))               // creatures
                        $label[] = lang::main('parensFmt', ['emit event #'.$p['action'], $p['actionChance']]);

                    $opts = array(                          // \0 doesn't get printed and tricks Util::toJSON() into handling this as a string .. i feel slightly dirty now
                        'label' => "\0$<br /><span class=\"q0\">".implode('<br />', $label).'</span>',
                        'type'  => $wpIdx
                    );

                    // aowow - custom: a waypoint pin, unlike every other pin here, has nowhere else to link to - give it the full route
                    if (self::$type == Type::NPC)
                        $opts['url'] = '?waypointpath='.WaypointPathList::encodeId(WaypointPathList::KIND_MOVEMENT, $pathId);

                    // aowow - custom start: the continent map draws this path's points outside every zone itself
                    if ($crossing)
                    {
                        $cPoints[] = [$p['mapId'], $p['mapX'], $p['mapY'], $opts];
                        if ($p['areaId'] < 0)
                            continue;
                    }
                    // aowow - custom end

                    // connective line
                    if ($i > 0 && $wPoints[$i - 1]['areaId'] == $p['areaId'])
                        $opts['lines'] = [[$wPoints[$i - 1]['areaX'], $wPoints[$i - 1]['areaY']]];

                    $data[$p['areaId']][$p['floor']]['coords'][] = [$p['areaX'], $p['areaY'], $opts];
                    if (empty($wpSum[$p['areaId']][$p['floor']]))
                        $wpSum[$p['areaId']][$p['floor']] = 1;
                    else
                        $wpSum[$p['areaId']][$p['floor']]++;
                }

                // aowow - custom start
                if ($cPoints && ($continents = WorldPosition::toContinentRoute($cPoints)))
                    foreach ($continents as $cId => [0 => ['coords' => $cCoords]])
                    {
                        $data[$cId][0]['coords'] = array_merge($data[$cId][0]['coords'] ?? [], $cCoords);
                        $wpSum[$cId][0] = ($wpSum[$cId][0] ?? 0) + count($cCoords);
                    }
                // aowow - custom end

                if ($wPoints)
                    $wpIdx++;
            }

            $opts   = $menu = $tt = $info = [];
            $footer = '';

            if ($respawn > 0)
                $info[1] = '<span class="q0">'.Lang::npc('respawnIn', [Lang::formatTime($respawn * 1000, 'game', 'timeAbbrev', true)]).'</span>';
            else if ($respawn < 0)
            {
                $info[0] = '<span class="q0">'.Lang::npc('scriptedSpawn').'</span>';
                $info[1] = '<span class="q0">'.Lang::npc('despawnAfter', [Lang::formatTime(-$respawn * 1000, 'game', 'timeAbbrev', true)]).'</span>';
            }

            if (!$skipAdmin && User::canSee(U_GROUP_STAFF))
            {
                $vSpawn = match ($flags & 0xFF)
                {
                    SPAWNTYPE_VEHICLE_ACCESSORY => '* Vehicle Accessory',
                    SPAWNTYPE_SUMMON_GROUPS     => '* Creature Summon Group',
                    SPAWNTYPE_SMART_SCRIPTS     => '* Smart AI summon',
                    SPAWNTYPE_TELEPORTER_DEST   => '* Teleporter Destination',
                    SPAWNTYPE_MO_TRANSPORT      => '* Transport',
                    default                     => $info[0] ?? null
                };

                if ($vSpawn)
                {
                    $info[0] = $vSpawn;
                    $opts['type'] = 4;                      // make pip purple
                }
                else if (self::$type == Type::NPC || self::$type == Type::OBJECT)
                    $info[0] = 'GUID'.Lang::main('colon').$guid;

                if ($_ = (($flags >> 8) & 0xFFF))
                    $info[] = Lang::game('transports', $_) ?? 'UNK transport #'.$_;

                if (!empty($phaseMask) && ($phaseMask & 0xFFFF) != 0xFFFF)
                    $info[] = Lang::game('phases').Lang::main('colon').Util::asHex($phaseMask);

                if ($spawnMask == 0xF)
                    $info[] = Lang::game('mode').Lang::game('modes', 0, -1);
                else if ($spawnMask)
                    if ($_ = array_intersect_key(Lang::game('modes', $mapType), Util::mask2bits($spawnMask)))
                        $info[] = Lang::game('mode').implode(', ', $_);

                if ($scriptName)
                    $info[] = 'ScriptName'.Lang::main('colon').$scriptName;
                if ($stringId)
                    $info[] = 'StringId'.Lang::main('colon').$stringId;

                // aowow - custom start
                if ($_ = $spawnExtra[$guid] ?? null)
                    foreach ($this->spawnExtraLines($_, $typeId) as $line)
                        $info[] = $line;
                // aowow - custom end

                if (self::$type == Type::AREATRIGGER && !($flags & SPAWNTYPE_TELEPORTER_DEST))
                {
                    [$deg, $dir] = Util::O2Deg($this->getField('orientation'));
                    $info[] = 'Orientation'.Lang::main('colon').$deg.'° ('.$dir.')';
                }

                if (isset($worldPos[$guid]))
                    $menu = Util::buildPosFixMenu($worldPos[$guid]['mapId'], $worldPos[$guid]['mapX'], $worldPos[$guid]['mapY'], self::$type, $guid, $areaId, $floor);

                if ($menu)
                    $footer = '<br /><span class="q2">Click to move pin</span>';
            }

            /* recognized opts
             * url:     string - makes pin clickable
             * tooltip: array  - title => [info: <arr>lines, footer: <string>line]
             * label:   string - single line tooltip (skipped if 'tooltip' is set)
             * menu:    array  - menu definiton (conflicts with url)
             * type:    int    - colors the pip [default, green, red, blue, purple]
             * lines:   array  - [[destX, destY]] - draws line from point to dest
             */

            if ($info)
                $tt['info'] = $info;

            if ($footer)
                $tt['footer'] = $footer;

            if ($tt && $this->getEntry($typeId))
                $opts['tooltip'] = [$this->getField('name', true) => $tt];
            else if ($hasLabel && $this->getEntry($typeId))
                $opts['label'] = sprintf('<span class="q">%1$s</span> ($)', $this->getField('name', true));

            if ($hasLink)
                $opts['url'] = '?' . Type::getFileString(self::$type) . '=' . $typeId;

            if ($menu)
                $opts['menu'] = $menu;

            $data[$areaId] [$floor] ['coords'] [] = [$posX, $posY, $opts];

            // aowow - custom: the spawn of a path drawn on a continent's map goes there too, unless it already lies there outside every zone
            if ($continents && ($cp = WorldPosition::toContinentPos($s['mapId'], $s['mapX'], $s['mapY'])) && isset($continents[$cp[0]]) && $cp[0] != $areaId)
                $data[$cp[0]][0]['coords'][] = [$cp[1], $cp[2], $opts];
        }

        foreach ($data as $a => &$areas)
            foreach ($areas as $f => &$floor)
                $floor['count'] = count($floor['coords']) - ($wpSum[$a][$f] ?? 0);

        uasort($data, [$this, 'sortBySpawnCount']);
        $this->spawnResult[SPAWNINFO_FULL] = $data;
    }

    // aowow - custom start
    /** per spawn values of `creature` / `gameobject` (+ gameobject_addon, gameobject_overrides) that differ from the template */
    private static function getSpawnExtras(array $guids) : array
    {
        if (!$guids)
            return [];

        if (self::$type == Type::NPC)
            return DB::World()->selectAssoc(
               'SELECT    c.`guid` AS ARRAY_KEY, c.`modelid`, c.`equipment_id`, cet.`ID` IS NOT NULL AS "hasEquip", c.`wander_distance`, c.`MovementType`, c.`npcflag`, c.`unit_flags`, c.`dynamicflags`
                FROM      creature c
                LEFT JOIN creature_equip_template cet ON cet.`CreatureID` = c.`id` AND cet.`ID` = 1
                WHERE     c.`guid` IN %in AND (c.`modelid` <> 0 OR c.`equipment_id` <> 1 OR (c.`MovementType` = 1 AND c.`wander_distance` > 0) OR c.`npcflag` <> 0 OR c.`unit_flags` <> 0 OR c.`dynamicflags` <> 0)',
                $guids
            ) ?: [];

        if (self::$type == Type::OBJECT)
            return DB::World()->selectAssoc(
               'SELECT    g.`guid` AS ARRAY_KEY, g.`state`, ga.`invisibilityType`, ga.`invisibilityValue`, go.`faction`, go.`flags`,
                          gt.`type` AS "goType", g.`animprogress`,
                          IFNULL(ga.`parent_rotation0`, 0) AS "pr0", IFNULL(ga.`parent_rotation1`, 0) AS "pr1", IFNULL(ga.`parent_rotation2`, 0) AS "pr2", IFNULL(ga.`parent_rotation3`, 1) AS "pr3"
                FROM      gameobject g
                JOIN      gameobject_template  gt ON gt.`entry`   = g.`id`
                LEFT JOIN gameobject_addon     ga ON ga.`guid`    = g.`guid`
                LEFT JOIN gameobject_overrides go ON go.`spawnId` = g.`guid`
                WHERE     g.`guid` IN %in AND (g.`state` = 0 OR ga.`invisibilityValue` > 0 OR go.`spawnId` IS NOT NULL OR gt.`type` IN %in OR
                          ga.`parent_rotation0` <> 0 OR ga.`parent_rotation1` <> 0 OR ga.`parent_rotation2` <> 0 OR ga.`parent_rotation3` <> 1)',
                $guids, [OBJECT_TRANSPORT, OBJECT_FISHINGHOLE]
            ) ?: [];

        return [];
    }

    private function spawnExtraLines(array $x, int $typeId) : array
    {
        $lines = [];

        if (self::$type == Type::NPC)
        {
            // MovementType 1 = RANDOM_MOTION_TYPE; the distance is ignored for any other
            if ($x['MovementType'] == 1 && $x['wander_distance'] > 0)
                $lines[] = Lang::npc('spawnWander', [Lang::nf($x['wander_distance'], 1)]);
            if ($x['modelid'])
                $lines[] = Lang::npc('spawnModel', [$x['modelid']]);
            // Creature::Create(): 0 = no equipment, -1 = random set, else creature_equip_template.ID; 1 is the default
            // and 0 only takes something away if the creature has a default set to begin with
            if ($x['equipment_id'] == 0 && $x['hasEquip'])
                $lines[] = Lang::npc('spawnEquipNone');
            else if ($x['equipment_id'] < 0)
                $lines[] = Lang::npc('spawnEquipRnd');
            else if ($x['equipment_id'] != 1)
                $lines[] = Lang::npc('spawnEquip', [$x['equipment_id']]);
            // ObjectMgr::ChooseCreatureFlags(): non-zero replaces the template value
            if ($x['npcflag'])
                $lines[] = Lang::npc('spawnNpcFlags', [Util::asHex($x['npcflag'])]);
            if ($x['unit_flags'])
                $lines[] = Lang::npc('spawnUnitFlags', [Util::asHex($x['unit_flags'])]);
            if ($x['dynamicflags'])
                $lines[] = Lang::npc('spawnDynFlags', [Util::asHex($x['dynamicflags'])]);
        }
        else if (self::$type == Type::OBJECT)
        {
            // GO_STATE_ACTIVE - only reads as "open" for doors and buttons
            if ($x['state'] == 0 && $this->getEntry($typeId) && in_array($this->getField('type'), [OBJECT_DOOR, OBJECT_BUTTON]))
                $lines[] = Lang::npc('spawnOpen');
            if ($x['invisibilityValue'])
                $lines[] = Lang::npc('spawnInvis', [Lang::exist('npc', 'invisTypes', (string)$x['invisibilityType']) ?? $x['invisibilityType'], $x['invisibilityValue']]);
            if ($x['faction'])
                $lines[] = Lang::npc('spawnFaction', [$x['faction']]);
            if ($x['flags'])
                $lines[] = Lang::npc('spawnGoFlags', [Util::asHex($x['flags'])]);
            // GameObject::Create(): only an elevator and a fishing hole take the spawn's animprogress
            if (in_array($x['goType'], [OBJECT_TRANSPORT, OBJECT_FISHINGHOLE]))
                $lines[] = Lang::npc('spawnAnimProgress', [$x['animprogress']]);
            // gameobject_addon: the rotation of the transport or object it is attached to
            if ($x['pr0'] != 0 || $x['pr1'] != 0 || $x['pr2'] != 0 || $x['pr3'] != 1)
                $lines[] = Lang::npc('spawnParentRot', [Lang::nf($x['pr0'], 3), Lang::nf($x['pr1'], 3), Lang::nf($x['pr2'], 3), Lang::nf($x['pr3'], 3)]);
        }

        return $lines;
    }
    // aowow - custom end

    private function sortBySpawnCount(array $a, array $b) : int
    {
        $aCount = array_sum(array_column($a, 'count'));     // sum over all floors
        $bCount = array_sum(array_column($b, 'count'));

        return $bCount <=> $aCount;                         // sort descending
    }

    private function createZoneSpawns() : void              // [zoneId1, zoneId2, ..]             for locations-column in listview
    {
        // aowow - custom: no areaX/areaY filter here (upstream: areaX > 0 AND areaY > 0) - unlike the other spawnHelper queries, this one only
        // needs a known areaId, not a drawable point (see spawns.ss.php's transformPoint() fallback,
        // which writes areaX = areaY = 0 when a map has no WorldMapArea/DungeonMap floor data to project
        // into, e.g. most instances). Keeping those rows means instance-only npcs/objects still get a
        // zone/instance name in the listview Location column and meta description, even with no map to draw.
        $res = DB::Aowow()->selectCol(
           'SELECT `typeId` AS ARRAY_KEY, GROUP_CONCAT(`areaId` ORDER BY `n` DESC)
            FROM (SELECT `typeId`, `areaId`, COUNT(1) AS "n" FROM %n WHERE `typeId` IN %in GROUP BY `typeId`, `areaId`) x
            GROUP BY `typeId`',
            $this->spawnsTable, $this->getfoundIDs()
        ) ?: [];

        foreach ($res as &$r)
            $r = explode(',', $r);

        $this->spawnResult[SPAWNINFO_ZONES] = $res;
    }

    private function createQuestSpawns() :void              // [zoneId => [floor => [[x1, y1], [x2, y2], ..]]]      mapper on quest detail page
    {
        if (self::$type == Type::SOUND)
            return;

        $res    = DB::Aowow()->selectAssoc('SELECT `areaId`, `floor`, `typeId`, `areaX`, `areaY` FROM %n WHERE `typeId` IN %in AND `areaX` > 0 AND `areaY` > 0', $this->spawnsTable, $this->getFoundIDs());
        $spawns = [];
        foreach ($res as $data)
        {
            // zone => floor => spawnData
            // todo (low): why is there a single set of coordinates; which one should be picked, instead of the first? gets used in ShowOnMap.buildTooltip i think
            if (!isset($spawns[$data['areaId']][$data['floor']][$data['typeId']]))
            {
                $spawns[$data['areaId']][$data['floor']][$data['typeId']] = array(
                    'type'          => self::$type,
                    'id'            => $data['typeId'],
                    'point'         => null,                // tbd later (start, end, requirement, sourcestart, sourceend, sourcerequirement)
                    'name'          => Util::localizedString($this->templates[$data['typeId']], 'name'),
                    'coord'         => [$data['areaX'], $data['areaY']],
                    'coords'        => [[$data['areaX'], $data['areaY']]],
                    'objective'     => null,                // tbd later (1-4 set a color; id of creature this entry gives credit for)
                    'reactalliance' => $this->templates[$data['typeId']]['A'] ?: 0,
                    'reacthorde'    => $this->templates[$data['typeId']]['H'] ?: 0
                );
            }
            else
                $spawns[$data['areaId']][$data['floor']][$data['typeId']]['coords'][] = [$data['areaX'], $data['areaY']];
        }

        $this->spawnResult[SPAWNINFO_QUEST] = $spawns;
    }

    public function getSpawns(int $mode, bool ...$info) : array|\StdClass
    {
        if (!$this->getfoundIDs() || !$this->setSpawnsTable())
            return [];

        switch ($mode)
        {
            case SPAWNINFO_SHORT:
                if ($this->spawnResult[SPAWNINFO_SHORT] === null)
                    $this->createShortSpawns();

                return $this->spawnResult[SPAWNINFO_SHORT];
            case SPAWNINFO_FULL:
                if (empty($this->spawnResult[SPAWNINFO_FULL]))
                    $this->createFullSpawns(...$info);

                return $this->spawnResult[SPAWNINFO_FULL];
            case SPAWNINFO_ZONES:                           // aowow - custom: === null - a list none of which is spawned left this empty and re-ran the query for every row (upstream: empty())
                if ($this->spawnResult[SPAWNINFO_ZONES] === null)
                    $this->createZoneSpawns();

                return $this->spawnResult[SPAWNINFO_ZONES][$this->id] ?? [];
            case SPAWNINFO_QUEST:                           // aowow - custom: === null, as SPAWNINFO_ZONES (upstream: empty())
                if ($this->spawnResult[SPAWNINFO_QUEST] === null)
                    $this->createQuestSpawns();

                return $this->spawnResult[SPAWNINFO_QUEST] ?? [];   // aowow - custom: ?? [] - a sound has none, and the method returns an array
        }

        return [];
    }
}

trait profilerHelper
{
    public static string $brickFile = 'profile';            // profile is multipurpose

    private static int $subjectGUID = 0;

    public function selectRealms(?array $fi) : bool
    {
        $this->dbNames = [];

        foreach(Profiler::getRealms() as $idx => $r)
        {
            if (!empty($fi['sv']) && Profiler::urlize($r['name']) != Profiler::urlize($fi['sv']) && intVal($fi['sv']) != $idx)
                continue;

            if (!empty($fi['rg']) && Profiler::urlize($r['region']) != Profiler::urlize($fi['rg']))
                continue;

            $this->dbNames[$idx] = 'Characters';
        }

        return !!$this->dbNames;
    }
}

trait sourceHelper
{
    protected  array $sources    = [];
    protected ?array $sourceMore = null;

    public function getRawSource(int $src) : array
    {
        return $this->sources[$this->id][$src] ?? [];
    }

    public function getSources(?array &$s = [], ?array &$sm = []) : bool
    {
        $s = $sm = [];
        if (empty($this->sources[$this->id]))
            return false;

        if ($this->sourceMore === null)
        {
            $buff = [];
            $this->sourceMore = [];

            foreach ($this->iterate() as $_curTpl)
                if ($_curTpl['moreType'] && $_curTpl['moreTypeId'])
                    $buff[$_curTpl['moreType']][] = $_curTpl['moreTypeId'];

            foreach ($buff as $type => $ids)
                $this->sourceMore[$type] = Type::newList($type, [['id', $ids]]);
        }

        $s = array_keys($this->sources[$this->id]);
        if ($this->curTpl['moreType'] && $this->curTpl['moreTypeId'] && ($srcData = $this->sourceMore[$this->curTpl['moreType']]->getSourceData($this->curTpl['moreTypeId'])))
            $sm = $srcData[$this->curTpl['moreTypeId']];
        else if (!empty($this->sources[$this->id][SRC_PVP]))
            $sm['p'] = $this->sources[$this->id][SRC_PVP][0];

        if ($z = $this->curTpl['moreZoneId'])
            $sm['z'] = $z;

        if ($this->curTpl['moreMask'] & SRC_FLAG_BOSSDROP)
            $sm['bd'] = 1;

        if (isset($this->sources[$this->id][SRC_DROP][0]))
        {
            /*
                mode        srcFlag     log2    dd Flag
                10N/D-NH    0b0001      0       0b001
                25N/D-HC    0b0010      1       0b010
                10H         0b0100      2       0b011
                25H         0b1000      3       0b100
            */
            if ($this->curTpl['moreMask'] & SRC_FLAG_COMMON)
                $sm['dd'] = 99;
            else if ($this->curTpl['moreMask'] & SRC_FLAG_DUNGEON_DROP)
                $sm['dd'] = $this->sources[$this->id][SRC_DROP][0] * -1;
            else if ($this->curTpl['moreMask'] & SRC_FLAG_RAID_DROP)
            {
                $dd = log($this->sources[$this->id][SRC_DROP][0], 2);
                if ($dd == intVal($dd))                             // only one bit set
                    $sm['dd'] = $dd + 1;
            }
        }

        if ($sm)
            $sm = [$sm];

        return true;
    }
}

?>
