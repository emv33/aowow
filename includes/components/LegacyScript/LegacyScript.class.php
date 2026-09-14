<?php

namespace Aowow;

if (!defined('AOWOW_REVISION'))
    die('illegal access');


/*
 * TrinityCore - the pre-SmartAI script engine
 *
 * `event_scripts`, `spell_scripts`, `waypoint_scripts` and (on cores that still ship them)
 * `quest_start_scripts` / `quest_end_scripts` all share one row layout: an id, a delay and a
 * command with up to three numeric arguments plus a position. Nothing about them is imported
 * into the aowow DB, so - like SmartAI, Conditions and Gossip - this reads DB::World() live.
 *
 * Entities driven by these tables used to render as though they did nothing at all, because
 * only `smart_scripts` was ever looked at.
 */
class LegacyScript
{
    public const int SRC_EVENT       = 1;
    public const int SRC_SPELL       = 2;
    public const int SRC_WAYPOINT    = 3;
    public const int SRC_QUEST_START = 4;
    public const int SRC_QUEST_END   = 5;
    public const int SRC_ESCORT_PATH = 6;                   // `script_waypoint` - a different row shape; see buildPathTable()

    // ScriptCommands, as of TrinityCore 3.3.5; gaps are commands that never existed in this branch
    public const int CMD_TALK                 =  0;
    public const int CMD_EMOTE                =  1;
    public const int CMD_FIELD_SET            =  2;
    public const int CMD_MOVE_TO              =  3;
    public const int CMD_FLAG_SET             =  4;
    public const int CMD_FLAG_REMOVE          =  5;
    public const int CMD_TELEPORT_TO          =  6;
    public const int CMD_QUEST_EXPLORED       =  7;
    public const int CMD_KILL_CREDIT          =  8;
    public const int CMD_RESPAWN_GAMEOBJECT   =  9;
    public const int CMD_TEMP_SUMMON_CREATURE = 10;
    public const int CMD_OPEN_DOOR            = 11;
    public const int CMD_CLOSE_DOOR           = 12;
    public const int CMD_ACTIVATE_OBJECT      = 13;
    public const int CMD_REMOVE_AURA          = 14;
    public const int CMD_CAST_SPELL           = 15;
    public const int CMD_PLAY_SOUND           = 16;
    public const int CMD_CREATE_ITEM          = 17;
    public const int CMD_DESPAWN_SELF         = 18;
    public const int CMD_LOAD_PATH            = 20;
    public const int CMD_CALLSCRIPT_TO_UNIT   = 21;
    public const int CMD_KILL                 = 22;
    public const int CMD_ORIENTATION          = 30;
    public const int CMD_EQUIP                = 31;
    public const int CMD_MODEL                = 32;
    public const int CMD_CLOSE_GOSSIP         = 33;
    public const int CMD_PLAYMOVIE            = 34;
    public const int CMD_MOVEMENT             = 35;
    public const int CMD_PLAY_ANIMKIT         = 36;

    private const string BASE_CSS = <<<CSS
        #legacy-script-generic .grid { clear:left; display: grid; }
        #legacy-script-generic .grid thead,
        #legacy-script-generic .grid tbody,
        #legacy-script-generic .grid tr { display: contents; }
        #legacy-script-generic .ls-num { text-align: right; }
    CSS;

    private const array TABLES = array(
        self::SRC_EVENT       => 'event_scripts',
        self::SRC_SPELL       => 'spell_scripts',
        self::SRC_WAYPOINT    => 'waypoint_scripts',
        self::SRC_QUEST_START => 'quest_start_scripts',
        self::SRC_QUEST_END   => 'quest_end_scripts',
        self::SRC_ESCORT_PATH => 'script_waypoint'
    );

    public readonly string $uid;
    public readonly string $title;

    private array  $rows      = [];
    private array  $jsGlobals = [];
    private string $gridCss   = '';
    private string $tbl       = '';
    private array  $strings   = [];                         // db_script_string, keyed by entry; only fetched if a TALK row needs it

    public function __construct(public readonly int $srcType, public readonly int $srcId, array $miscData = [])
    {
        $this->uid   = $miscData['uid']   ?? 'legacy-script-'.$this->srcType.'-'.$this->srcId;
        $this->title = $miscData['title'] ?? '';

        $this->rows  = $this->fetchRows();
    }


    /**********/
    /* Lookup */
    /**********/

    /** a core may not ship every one of these tables; a missing one makes selectAssoc() return null rather than throw */
    private static function tableExists(string $tbl) : bool
    {
        static $known = [];

        return $known[$tbl] ??= (bool)DB::World()->selectCell('SHOW TABLES LIKE %s', $tbl);
    }

    public static function exists(int $srcType, int $srcId) : bool
    {
        if ($srcId <= 0 || !($tbl = self::TABLES[$srcType] ?? null) || !self::tableExists($tbl))
            return false;

        $key = $srcType == self::SRC_ESCORT_PATH ? 'entry' : 'id';

        return (bool)DB::World()->selectCell('SELECT 1 FROM %n WHERE %n = %i LIMIT 1', $tbl, $key, $srcId);
    }

    /**
     * every event id a spell sends via SPELL_EFFECT_SEND_EVENT
     * event_scripts is keyed by that id, and nothing else links the two
     */
    public static function getEventIdsForSpell(int $spellId) : array
    {
        $ids = DB::Aowow()->selectCol(
           'SELECT `effect1MiscValue` FROM ::spell WHERE `id` = %i AND `effect1Id` = %i
            UNION SELECT `effect2MiscValue` FROM ::spell WHERE `id` = %i AND `effect2Id` = %i
            UNION SELECT `effect3MiscValue` FROM ::spell WHERE `id` = %i AND `effect3Id` = %i',
            $spellId, SPELL_EFFECT_SEND_EVENT, $spellId, SPELL_EFFECT_SEND_EVENT, $spellId, SPELL_EFFECT_SEND_EVENT
        ) ?: [];

        return array_values(array_filter(array_map('intVal', $ids)));
    }

    /** eventId fields of a gameobject_template row; the column is type dependent, as with gossip */
    public static function getEventIdsForObject(int $objectId) : array
    {
        $col = 'IF(`type` = '.GO_TYPE_GOOBER.', `data1`, IF(`type` = '.GO_TYPE_CHEST.', `data6`, IF(`type` = '.GO_TYPE_CAMERA.', `data2`, 0)))';

        $ids = DB::World()->selectCol('SELECT '.$col.' FROM gameobject_template WHERE `entry` = %i', $objectId) ?: [];

        return array_values(array_filter(array_map('intVal', $ids)));
    }

    /**
     * `waypoint_scripts` is never keyed by a creature; it is reached through the `action`
     * column of the path the creature walks, set per guid or per entry in *_addon
     */
    public static function getWaypointScriptIdsForNPC(int $npcId) : array
    {
        if (!self::tableExists('waypoint_data'))
            return [];

        $ids = DB::World()->selectCol(
           'SELECT DISTINCT wd.`action`
            FROM   waypoint_data wd
            WHERE  wd.`action` > 0 AND wd.`id` IN (
                       SELECT ca.`path_id` FROM creature_addon ca JOIN creature c ON c.`guid` = ca.`guid` WHERE c.`id` = %i AND ca.`path_id` > 0
                       UNION
                       SELECT cta.`path_id` FROM creature_template_addon cta WHERE cta.`entry` = %i AND cta.`path_id` > 0
                   )',
            $npcId, $npcId
        ) ?: [];

        return array_values(array_filter(array_map('intVal', $ids)));
    }

    private function fetchRows() : array
    {
        if ($this->srcId <= 0 || !($tbl = self::TABLES[$this->srcType] ?? null) || !self::tableExists($tbl))
            return [];

        if ($this->srcType == self::SRC_ESCORT_PATH)
            return DB::World()->selectAssoc('SELECT * FROM script_waypoint WHERE `entry` = %i ORDER BY `pointid` ASC', $this->srcId) ?: [];

        $rows = DB::World()->selectAssoc('SELECT * FROM %n WHERE `id` = %i ORDER BY `delay` ASC', $tbl, $this->srcId) ?: [];

        $out = [];
        foreach ($rows as $r)
            $out[] = array(
                'delay'     => (int)  ($r['delay']     ?? 0),
                'command'   => (int)  ($r['command']   ?? 0),
                'datalong'  => (int)  ($r['datalong']  ?? 0),
                'datalong2' => (int)  ($r['datalong2'] ?? 0),
                'dataint'   => (int)  ($r['dataint']   ?? 0),
                'effIndex'  => isset($r['effIndex']) ? (int)$r['effIndex'] : null,
                'x'         => (float)($r['x']         ?? 0),
                'y'         => (float)($r['y']         ?? 0),
                'z'         => (float)($r['z']         ?? 0),
                'o'         => (float)($r['o']         ?? 0)
            );

        return $out;
    }

    /** SCRIPT_COMMAND_TALK points `dataint` at db_script_string, which not every core still has */
    private function fetchStrings(array $entries) : array
    {
        if (!$entries || !self::tableExists('db_script_string'))
            return [];

        $loc  = Lang::getLocale();
        $rows = DB::World()->selectAssoc('SELECT * FROM db_script_string WHERE `entry` IN %in', $entries) ?: [];

        $out = [];
        foreach ($rows as $r)
        {
            $text = (string)($r['content_default'] ?? '');
            if ($loc != Locale::EN && !empty($r['content_loc'.$loc->value]))
                $text = (string)$r['content_loc'.$loc->value];

            $out[(int)$r['entry']] = $text;
        }

        return $out;
    }


    /*************/
    /* Rendering */
    /*************/

    public function prepare() : bool
    {
        if (!$this->rows)
            return false;

        if ($this->tbl)
            return true;

        if ($this->srcType == self::SRC_ESCORT_PATH)
        {
            $this->tbl = $this->buildPathTable();
            return true;
        }

        $talkIds = [];
        foreach ($this->rows as $r)
            if ($r['command'] == self::CMD_TALK && $r['dataint'] > 0)
                $talkIds[] = $r['dataint'];

        $this->strings = $this->fetchStrings($talkIds);
        $this->tbl     = $this->buildTable();

        return true;
    }

    private function buildTable() : string
    {
        $hasPos = false;
        $rows   = [];

        foreach ($this->rows as $r)
        {
            if ($r['x'] || $r['y'] || $r['z'])
                $hasPos = true;

            $rows[] = array(
                $r['delay'] ? Lang::formatTime($r['delay'] * 1000) : Lang::legacyScript('instantly'),
                $this->describe($r),
                ($r['x'] || $r['y'] || $r['z']) ? '[small class=q0]'.sprintf('%.1f, %.1f, %.1f', $r['x'], $r['y'], $r['z']).'[/small]' : ''
            );
        }

        $th = array(
            [Lang::legacyScript('delay'),   '120px'],
            [Lang::legacyScript('action'),  'auto' ],
            [Lang::legacyScript('atPos'),   '160px']
        );

        return $this->renderGrid($th, $rows, $hasPos ? [] : [2]);
    }

    /** `script_waypoint` holds plain coordinates for an escort, not commands */
    private function buildPathTable() : string
    {
        $hasText = false;
        $rows    = [];

        foreach ($this->rows as $r)
        {
            $text = (string)($r['point_comment'] ?? '');
            if ($text)
                $hasText = true;

            $rows[] = array(
                '#[b]'.(int)($r['pointid'] ?? 0).'[/b]',
                '[small class=q0]'.sprintf('%.1f, %.1f, %.1f', (float)($r['location_x'] ?? 0), (float)($r['location_y'] ?? 0), (float)($r['location_z'] ?? 0)).'[/small]',
                ($_ = (int)($r['waittime'] ?? 0)) ? Lang::formatTime($_) : '',
                $text
            );
        }

        $th = array(
            [Lang::legacyScript('point'),   '90px' ],
            [Lang::legacyScript('atPos'),   '180px'],
            [Lang::legacyScript('waits'),   '120px'],
            [Lang::legacyScript('comment'), 'auto' ]
        );

        return $this->renderGrid($th, $rows, $hasText ? [] : [3]);
    }

    /** turn one row into readable markup, linking every id the command points at */
    private function describe(array $r) : string
    {
        $cmd = $r['command'];
        $dl  = $r['datalong'];
        $dl2 = $r['datalong2'];
        $di  = $r['dataint'];

        $out = match ($cmd)
        {
            self::CMD_TALK                 => Lang::legacyScript('cmd', 'talk', [$this->talkText($di)]),
            self::CMD_EMOTE                => Lang::legacyScript('cmd', 'emote', [$this->link(Type::EMOTE, $dl)]),
            self::CMD_FIELD_SET            => Lang::legacyScript('cmd', 'fieldSet', [$dl, $dl2]),
            self::CMD_MOVE_TO              => Lang::legacyScript('cmd', 'moveTo', [Lang::formatTime($dl2)]),
            self::CMD_FLAG_SET             => Lang::legacyScript('cmd', 'flagSet', [$dl, '0x'.strtoupper(dechex($dl2))]),
            self::CMD_FLAG_REMOVE          => Lang::legacyScript('cmd', 'flagRemove', [$dl, '0x'.strtoupper(dechex($dl2))]),
            self::CMD_TELEPORT_TO          => Lang::legacyScript('cmd', 'teleportTo', [$dl]),                        // datalong is a mapId, not an areaId - nothing to link to
            self::CMD_QUEST_EXPLORED       => Lang::legacyScript('cmd', 'questExplored', [$this->link(Type::QUEST, $dl), $dl2]),
            self::CMD_KILL_CREDIT          => Lang::legacyScript('cmd', 'killCredit', [$this->link(Type::NPC, $dl)]),
            self::CMD_RESPAWN_GAMEOBJECT   => Lang::legacyScript('cmd', 'respawnObject', [$this->link(Type::OBJECT, $dl), Lang::formatTime($dl2 * 1000)]),
            self::CMD_TEMP_SUMMON_CREATURE => Lang::legacyScript('cmd', 'summonCreature', [$this->link(Type::NPC, $dl), Lang::formatTime($dl2)]),
            self::CMD_OPEN_DOOR            => Lang::legacyScript('cmd', 'openDoor', [$this->link(Type::OBJECT, $dl), Lang::formatTime($dl2 * 1000)]),
            self::CMD_CLOSE_DOOR           => Lang::legacyScript('cmd', 'closeDoor', [$this->link(Type::OBJECT, $dl), Lang::formatTime($dl2 * 1000)]),
            self::CMD_ACTIVATE_OBJECT      => Lang::legacyScript('cmd', 'activateObject'),
            self::CMD_REMOVE_AURA          => Lang::legacyScript('cmd', 'removeAura', [$this->link(Type::SPELL, $dl)]),
            self::CMD_CAST_SPELL           => Lang::legacyScript('cmd', 'castSpell', [$this->link(Type::SPELL, $dl)]),
            self::CMD_PLAY_SOUND           => Lang::legacyScript('cmd', 'playSound', [$this->link(Type::SOUND, $dl)]),
            self::CMD_CREATE_ITEM          => Lang::legacyScript('cmd', 'createItem', [$this->link(Type::ITEM, $dl), max(1, $dl2)]),
            self::CMD_DESPAWN_SELF         => Lang::legacyScript('cmd', 'despawnSelf', [Lang::formatTime($dl)]),
            self::CMD_LOAD_PATH            => Lang::legacyScript('cmd', 'loadPath', [$dl]),
            self::CMD_CALLSCRIPT_TO_UNIT   => Lang::legacyScript('cmd', 'callScript', [$dl, $dl2]),
            self::CMD_KILL                 => Lang::legacyScript('cmd', 'kill'),
            self::CMD_ORIENTATION          => Lang::legacyScript('cmd', 'orientation'),
            self::CMD_EQUIP                => Lang::legacyScript('cmd', 'equip', [$dl]),
            self::CMD_MODEL                => Lang::legacyScript('cmd', 'model', [$dl]),
            self::CMD_CLOSE_GOSSIP         => Lang::legacyScript('cmd', 'closeGossip'),
            self::CMD_PLAYMOVIE            => Lang::legacyScript('cmd', 'playMovie', [$dl]),
            self::CMD_MOVEMENT             => Lang::legacyScript('cmd', 'movement', [$dl, $dl2]),
            self::CMD_PLAY_ANIMKIT         => Lang::legacyScript('cmd', 'playAnimKit', [$dl]),
            default                        => Lang::legacyScript('cmd', 'unknown', [$cmd, $dl, $dl2, $di])
        };

        // spell_scripts rows are bound to one effect of the spell; the other tables have no such column
        if ($r['effIndex'] !== null)
            $out .= ' [small class=q0]'.Lang::legacyScript('effIndex', [$r['effIndex'] + 1]).'[/small]';

        return $out;
    }

    private function talkText(int $entry) : string
    {
        if ($entry <= 0)
            return Lang::legacyScript('noText');

        if (!isset($this->strings[$entry]))                  // core still resolves it, we simply have no table to read it from
            return Lang::legacyScript('stringId', [$entry]);

        return '[span class=q2]"'.UIText::format($this->strings[$entry], Lang::FMT_MARKUP).'"[/span]';
    }

    /** markup link plus the jsGlobal that makes it render with a name instead of an id */
    private function link(int $type, int $id) : string
    {
        if ($id <= 0)
            return '[b]'.$id.'[/b]';

        if ($file = Type::getFileString($type))
        {
            $this->jsGlobals[$type][$id] = $id;
            return '['.$file.'='.$id.']';
        }

        return '[b]'.$id.'[/b]';
    }

    private function renderGrid(array $th, array $rows, array $dropCols) : string
    {
        foreach ($dropCols as $i)
        {
            unset($th[$i]);
            foreach ($rows as &$r)
                unset($r[$i]);

            unset($r);
        }

        $tblId = Util::createHash(12);
        $this->gridCss .= "\n#tbl-".$tblId." { grid-template-columns: ".implode(' ', array_column($th, 1))."; }";

        $tbl = '[tr]' . array_reduce(array_column($th, 0), fn($out, $n) => $out .= '[td header]'.$n.'[/td]', '') . '[/tr]';
        foreach ($rows as $r)
            $tbl .= '[tr][td]'.implode('[/td][td]', $r).'[/td][/tr]';

        return '[table id=tbl-'.$tblId.' class=grid]'.$tbl.'[/table]';
    }

    public function getMarkupBody(bool $collapsed = false) : ?string
    {
        if (!$this->tbl)
            return null;

        $state = $collapsed ? '=hidden' : '';
        $head  = Lang::legacyScript('srcTypes', $this->srcType, [$this->srcId]).$this->title;

        return '[pad][h3][toggler'.$state.' id='.$this->uid.']'.$head.'[/toggler][/h3][div'.$state.' id='.$this->uid.' clear=left]'.$this->tbl.'[/div]';
    }

    public function getGridCss() : string
    {
        return $this->gridCss;
    }

    public function getJSGlobals() : array
    {
        return $this->jsGlobals;
    }

    /**
     * one Markup for a set of legacy scripts
     *
     * @param  array   $sources    [[srcType, srcId], ..]
     * @param  string  $uidPrefix  keeps the [toggler]/[div] ids unique across entities on one page
     * @param  array  &$jsGlobals  receives the globals referenced by the rendered commands
     * @return ?Markup             null when none of the sources hold any rows
     */
    public static function buildMarkupFor(array $sources, string $uidPrefix, ?array &$jsGlobals = []) : ?Markup
    {
        $found = [];
        foreach ($sources as [$srcType, $srcId])
        {
            $ls = new LegacyScript($srcType, $srcId, ['uid' => $uidPrefix.'-'.$srcType.'-'.$srcId]);
            if ($ls->prepare())
                $found[] = $ls;
        }

        if (!$found)
            return null;

        $collapsed = count($found) > 1;
        $body      = '';
        $css       = '';

        foreach ($found as $ls)
        {
            Util::mergeJsGlobals($jsGlobals, $ls->getJSGlobals());

            $body .= $ls->getMarkupBody($collapsed);
            $css  .= $ls->getGridCss();
        }

        $body = '[style]'.strtr(self::BASE_CSS.$css, "\n", ' ').'[/style]'.$body;

        return new Markup($body, ['allow' => Markup::CLASS_ADMIN], 'legacy-script-generic');
    }
}

?>
