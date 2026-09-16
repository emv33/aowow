<?php

namespace Aowow;

if (!defined('AOWOW_REVISION'))
    die('illegal access');


class ZoneBaseResponse extends TemplateResponse implements ICache
{
    use TrDetailPage, TrCache;

    protected  int    $cacheType  = CACHE_TYPE_DETAIL_PAGE;

    protected  string $template   = 'detail-page-generic';
    protected  string $pageName   = 'zone';
    protected ?int    $activeTab  = parent::TAB_DATABASE;
    protected  array  $breadcrumb = [0, 6];

    protected  array  $dataLoader = ['zones'];
    protected  array  $scripts    = [[SC_JS_FILE, 'js/ShowOnMap.js']];

    public  int    $type      = Type::ZONE;
    public  int    $typeId    = 0;
    public  array  $zoneMusic = [];
    public ?string $expansion = null;

    private ZoneList $subject;

    public function __construct(string $id)
    {
        parent::__construct($id);

        $this->typeId     = intVal($id);
        $this->contribute = Type::getClassAttrib($this->type, 'contribute') ?? CONTRIBUTE_NONE;
    }

    protected function generate() : void
    {
        $this->applyXRef();                                 // aowow - custom

        $this->subject = new ZoneList(array(['id', $this->typeId]));
        if ($this->subject->error)
            $this->generateNotFound(Lang::game('zone'), Lang::zone('notFound'));

        $this->h1 = $this->subject->getField('name', true);

        $this->gPageInfo += array(
            'type'   => $this->type,
            'typeId' => $this->typeId,
            'name'   => $this->h1
        );

        $_parentArea = $this->subject->getField('parentArea');
        $_type       = $this->subject->getField('type');


        /*************/
        /* Menu Path */
        /*************/

        $this->breadcrumb[] = $this->subject->getField('category');

        if (in_array($this->subject->getField('category'), [MAP_TYPE_DUNGEON, MAP_TYPE_RAID]))
            $this->breadcrumb[] = $this->subject->getField('expansion');


        /**************/
        /* Page Title */
        /**************/

        array_unshift($this->title, $this->h1, Util::ucFirst(Lang::game('zone')));


        /***********/
        /* Infobox */
        /***********/

        $quickFactsRows = DB::Aowow()->selectCol('SELECT `orderIdx` AS  ARRAY_KEY, `row` FROM ::quickfacts WHERE `type` = %i AND `typeId` = %i ORDER BY `orderIdx` ASC', $this->type, $this->typeId);
        $quickFactsRows = preg_replace_callback('/\|L:(\w+)((:\w+)+)\|/i', function ($m)
        {
            [, $grp, $args] = $m;
            $args = array_filter(explode(':', $args), fn($x) => $x != '');

            return Lang::$grp(...$args);
        }, $quickFactsRows);

        foreach ($quickFactsRows as $er)
            $this->extendGlobalData(Markup::parseTags($er));

        $infobox = Lang::getInfoBoxForFlags($this->subject->getField('cuFlags'));

        if ($topRows = array_filter($quickFactsRows, fn($x) => $x < 0, ARRAY_FILTER_USE_KEY))
            $infobox = array_merge($infobox, $topRows);

        // City
        if ($this->subject->getField('flags') & AREA_FLAG_SLAVE_CAPITAL && !$_parentArea)
            $infobox[] = Lang::zone('city');

        // Auto repop
        if ($this->subject->getField('flags') & AREA_FLAG_NEED_FLY && !$_parentArea)
            $infobox[] = Lang::zone('autoRez');

        // Level
        if ($_ = $this->subject->getField('levelMin'))
        {
            if ($_ < $this->subject->getField('levelMax'))
                $_ .= ' - '.$this->subject->getField('levelMax');

            $infobox[] = Lang::game('level').Lang::main('colon').$_;
        }

        // required Level
        if ($_ = $this->subject->getField('levelReq'))
        {
            if ($__ = $this->subject->getField('levelReqLFG'))
                $buff = Lang::zone('reqLevels', [$_, $__]);
            else
                $buff = Lang::main('_reqLevel').Lang::main('colon').$_;

            $infobox[] = $buff;
        }

        // Territory
        $faction = $this->subject->getField('faction');
        $wrap    = match ($faction)
        {
            TEAM_ALLIANCE => '[span class=icon-alliance]%s[/span]',
            TEAM_HORDE    => '[span class=icon-horde]%s[/span]',
            4, 5          => '[span class=icon-ffa]%s[/span]',
            default       => '%s'
        };

        $infobox[] = Lang::zone('territory').sprintf($wrap, Lang::zone('territories', $faction));

        // Instance Type
        $infobox[] = Lang::zone('instanceType').'[span class=icon-instance'.$this->subject->getField('type').']'.Lang::zone('instanceTypes', $this->subject->getField('type')).'[/span]';

        // Heroic mode
        if ($_ = $this->subject->getField('levelHeroic'))
            $infobox[] = '[icon preset=heroic]'.Lang::zone('hcAvailable', [$_]).'[/icon]';

        // number of players
        if ($_ = $this->subject->getField('maxPlayer'))
        {
            if (in_array($this->subject->getField('category'), [6, 9]))
                $infobox[] = Lang::zone('numPlayersVs', [$_]);
            else
                $infobox[] = Lang::zone('numPlayers', [$_ == -2 ? '10/25' : $_]);
        }

        // Instances
        if ($_ = DB::Aowow()->selectCol('SELECT `typeId` FROM ::spawns WHERE `type`= %i AND `areaId` = %i ', Type::ZONE, $this->typeId))
        {
            $this->extendGlobalIds(Type::ZONE, ...$_);
            $infobox[] = Lang::maps('Instances').Lang::main('colon').Lang::concat($_, Lang::CONCAT_NONE, fn($x) => "\n[zone=".$x."]");
        }

        // start area
        if ($_ = DB::Aowow()->selectCol('SELECT `id` FROM ::races WHERE `startAreaId` = %i', $this->typeId))
        {
            $this->extendGlobalIds(Type::CHR_RACE, ...$_);
            $infobox[] = Lang::concat($_, Lang::CONCAT_NONE, fn($x) => '[race='.$x.']').' '.Lang::race('startZone');
        }

        parent::generate(); // calls applyGlobals .. probably too early here, but addMoveLocationMenu requires PageTemplate to be initialized

        // location (if instance)
        if ($pa = DB::Aowow()->selectRow('SELECT `areaId`, `posX`, `posY`, `floor` FROM ::spawns WHERE `type`= %i AND `typeId` = %i ', Type::ZONE, $this->typeId))
        {
            $this->addMoveLocationMenu($pa['areaId'], $pa['floor']);

            $infobox[] = Lang::zone('location').self::makeMapLink($pa);
        }

        // Attunement Quest/Achievements & Keys
        if ($attmnt = $this->subject->getField('attunes'))
        {
            foreach ($attmnt as $type => $ids)
            {
                $this->extendGlobalIds($type, ...array_map('abs', $ids));

                // one line per kind (normal / heroic), its ids as a list
                $tag  = $type == Type::ITEM ? 'item' : Type::getFileString($type);
                $name = $type == Type::ITEM ? 'key' : 'attunement';

                foreach ([false, true] as $heroic)
                {
                    if (!$_ = array_values(array_filter($ids, fn($id) => ($id < 0) === $heroic)))
                        continue;

                    $infobox[] = Lang::zone($name, (int)$heroic).Lang::concat(array_map(fn($id) => '['.$tag.'='.abs($id).']', $_), Lang::CONCAT_NONE);
                }
            }
        }

        // aowow - custom start: graveyards and weather
        // `game_graveyard` + `graveyard_zone` say where you resurrect, `game_weather` what falls on
        // you while you are alive; neither was read anywhere
        foreach (self::getGraveyards($this->typeId) as $gy)
            $infobox[] = Lang::zone('graveyard').Lang::main('colon').$gy;

        if ($w = self::getWeather($this->typeId))
            $infobox[] = Lang::zone('weather').Lang::main('colon').$w;
        // aowow - custom end

        // aowow - custom start: battleground bracket and dungeon finder bracket
        // zones.ss.php reads BattlemasterList.dbc for `maxPlayers` and LFGDungeons.dbc for the
        // queue level only; the brackets in both were never carried over
        $mapId = $this->subject->getField('mapId');

        if ($bg = self::getBattlemasterList($mapId))
        {
            if ($bg['minLevel'] || $bg['maxLevel'])
                $infobox[] = Lang::zone('bgBracket').Lang::main('colon').$bg['minLevel'].' - '.$bg['maxLevel'];

            // `battleground_template` was read nowhere, though it is what the server actually enforces
            if ($bgt = self::getBattlegroundTemplate($bg['id']))
            {
                $tMin = (int)($bgt['minplayersperteam'] ?? 0);
                $tMax = (int)($bgt['maxplayersperteam'] ?? 0);
                if ($tMax)
                    $infobox[] = Lang::zone('bgTeamSize').Lang::main('colon').($tMin && $tMin != $tMax ? $tMin.' - '.$tMax : $tMax);

                $sMin = (int)($bgt['minlvl'] ?? 0);
                $sMax = (int)($bgt['maxlvl'] ?? 0);
                if (($sMin || $sMax) && ($sMin != $bg['minLevel'] || $sMax != $bg['maxLevel']))
                    $infobox[] = Lang::zone('bgBracketServer').Lang::main('colon').$sMin.' - '.$sMax;
            }

            // `game_event_battleground_holiday` - the Call to Arms rotation, read nowhere
            if ($holidays = self::getBattlegroundHoliday($bg['id']))
            {
                $this->extendGlobalIds(Type::WORLDEVENT, ...$holidays);
                $infobox[] = Lang::zone('holiday').Lang::main('colon').Lang::concat(array_map(fn($x) => '[event='.$x.']', $holidays), Lang::CONCAT_NONE);
            }
        }

        if ($lfg = self::getLFGDungeon($mapId))
        {
            if ($lfg['levelMin'] || $lfg['levelMax'])
                $infobox[] = Lang::zone('lfgBracket').Lang::main('colon').$lfg['levelMin'].' - '.$lfg['levelMax'];

            if ($_ = Lang::zone('lfgTypes', $lfg['type']))
                $infobox[] = Lang::zone('lfgType').Lang::main('colon').$_;

            // `lfg_dungeon_rewards` - the reward quest a completed run hands out, which was read nowhere
            foreach (self::getLFGRewards($lfg['id']) as $rw)
            {
                $quests = [];
                foreach (['firstQuestId' => 'lfgRewardFirst', 'otherQuestId' => 'lfgRewardRepeat'] as $col => $label)
                {
                    if (!$rw[$col])
                        continue;

                    $this->extendGlobalIds(Type::QUEST, $rw[$col]);
                    $quests[] = Lang::zone($label).Lang::main('colon').'[quest='.$rw[$col].']';
                }

                if ($quests)
                    $infobox[] = Lang::zone('lfgReward', [$rw['maxLevel']]).Lang::main('colon').implode(', ', $quests);
            }
        }
        // aowow - custom end

        // aowow - custom start: `instance_template` was read nowhere
        // it is the server's own row for an instance map - whether mounts work inside, which map it
        // is entered from, and the name of the C++ script that runs it
        foreach ($this->getInstanceTemplate($mapId, $pa ?? null) as $line)
            $infobox[] = $line;
        // aowow - custom end

        // id
        $infobox[] = Lang::zone('id') . $this->typeId;

        // original name
        if (Lang::getLocale() != Locale::EN)
            $infobox[] = Util::ucFirst(Lang::lang(Locale::EN->value) . Lang::main('colon')) . '[copy button=false]'.$this->subject->getField('name_loc0').'[/copy][/li]';

        if ($botRows = array_filter($quickFactsRows, fn($x) => $x > 0, ARRAY_FILTER_USE_KEY))
            $infobox = array_merge($infobox, $botRows);

        if ($infobox)
            $this->infobox = new InfoboxMarkup($infobox, ['allow' => Markup::CLASS_STAFF, 'dbpage' => true], 'infobox-contents0');


        /****************/
        /* Main Content */
        /****************/

        $addToSOM = function (string $what, string $group, array $entry) use (&$som) : void
        {
            // entry always contains: type, id, name, level, coords[]
            if (!isset($som[$what][$group]))                // not found yet
                $som[$what][$group][] = $entry;
            else                                            // found .. something..
            {
                // check for identical floors
                foreach ($som[$what][$group] as &$byFloor)
                {
                    if ($byFloor['level'] != $entry['level'])
                        continue;

                    // found existing floor, ammending coords
                    $byFloor['coords'][] = $entry['coords'][0];
                    return;
                }

                // floor not used yet, create it
                $som[$what][$group][] = $entry;
            }
        };

        if ($_parentArea)
        {
            $this->extraText = new Markup(Lang::zone('zonePartOf', [$_parentArea]), ['dbpage' => true, 'allow' => Markup::CLASS_ADMIN], 'text-generic');
            $this->extendGlobalIds(Type::ZONE, $_parentArea);
        }

        // we cannot fetch spawns via lists. lists are grouped by entry
        $oSpawns = DB::Aowow()->selectAssoc('SELECT * FROM ::spawns WHERE `areaId` = %i AND `type` = %i AND `posX` > 0 AND `posY` > 0', $this->typeId, Type::OBJECT);
        $cSpawns = DB::Aowow()->selectAssoc('SELECT * FROM ::spawns WHERE `areaId` = %i AND `type` = %i AND `posX` > 0 AND `posY` > 0', $this->typeId, Type::NPC);
        $aSpawns = User::isInGroup(U_GROUP_STAFF) ? DB::Aowow()->selectAssoc('SELECT * FROM ::spawns WHERE `areaId` = %i AND `type` = %i AND `posX` > 0 AND `posY` > 0', $this->typeId, Type::AREATRIGGER) : [];

        $conditions = [['s.areaId', $this->typeId]];
        if (!User::isInGroup(U_GROUP_STAFF))
            $conditions[] = [['cuFlags', CUSTOM_EXCLUDE_FOR_LISTVIEW, '&'], 0];

        $objectSpawns   = new GameObjectList($conditions, ['calcTotal' => true]);
        $creatureSpawns = new CreatureList($conditions, ['calcTotal' => true]);
        $atSpawns       = new AreaTriggerList($conditions);

        $questsLV = $rewardsLV = [];

        $relQuestZOS = [$this->typeId];
        foreach (Game::$questSubCats as $parent => $children)
        {
            if (in_array($this->typeId, $children))
                $relQuestZOS[] = $parent;
            else if ($this->typeId == $parent)
                $relQuestZOS = array_merge($relQuestZOS, $children);
        }

        // see if we can actually display a map
        $mapFilePath = 'static/images/wow/maps/%s/%d%s.png';
        $options     = array(
            [Lang::getLocale()->json(), ''],                // default case
            [Lang::getLocale()->json(), '-1'],              // try multifloor
            ['enus', ''],                                   // try english fallback
            ['enus', '-1']                                  // try english fallback, multifloor
        );
        $hasMap = false;
        foreach ($options as [$lang, $floor])
        {
            if (!file_exists(sprintf($mapFilePath, $lang, $this->typeId, $floor)))
                continue;

            $hasMap = true;
            break;
        }

        if ($hasMap)
        {
            $som = [];
            foreach ($oSpawns as $spawn)
            {
                $tpl = $objectSpawns->getEntry($spawn['typeId']);
                if (!$tpl)
                    continue;

                $n = Util::localizedString($tpl, 'name');

                $what = match ((int)$tpl['typeCat'])
                {
                    -3      => 'herb',
                    -4      => 'vein',
                     9      => 'book',
                    25      => 'pool',
                     0      => $tpl['type'] == 19 ? 'mail' : '',
                    -6      => $tpl['spellFocusId'] == 1 ? 'anvil' : ($tpl['spellFocusId'] == 3 ? 'forge' : ''),
                    default => ''
                };

                if ($what)
                {
                    $blob = array(
                        'coords' => [[$spawn['posX'], $spawn['posY']]],
                        'level'  => $spawn['floor'],
                        'name'   => $n,
                        'type'   => Type::OBJECT,
                        'id'     => $tpl['id']
                    );

                    if ($what == 'mail')
                    {
                        $blob['side'] = (($tpl['A'] < 0 ? 0 : SIDE_ALLIANCE) | ($tpl['H'] < 0 ? 0 : SIDE_HORDE));
                        $addToSOM($what, $tpl['id'], $blob);
                    }
                    else
                        $addToSOM($what, $n, $blob);
                }

                if ($tpl['startsQuests'])
                {
                        $started = new QuestList(array(['qse.method', 1, '&'], ['qse.type', Type::OBJECT], ['qse.typeId', $tpl['id']]));
                        if ($started->error)
                            continue;

                        // store data for misc tabs
                        foreach ($started->getListviewData() as $id => $data)
                        {
                            if ($started->getField('questSortId') > 0 && !in_array($started->getField('questSortId'), $relQuestZOS))
                                continue;

                            if (!empty($started->rewards[$id][Type::ITEM]))
                                $rewardsLV = array_merge($rewardsLV, array_keys($started->rewards[$id][Type::ITEM]));

                            if (!empty($started->choices[$id][Type::ITEM]))
                                $rewardsLV = array_merge($rewardsLV, array_keys($started->choices[$id][Type::ITEM]));

                            $questsLV[$id] = $data;
                        }

                        $this->extendGlobalData($started->getJSGlobals());

                        if (($tpl['A'] != -1) && ($_ = $started->getSOMData(SIDE_ALLIANCE)))
                            $addToSOM('alliancequests', $n, array(
                                'coords' => [[$spawn['posX'], $spawn['posY']]],
                                'level'  => $spawn['floor'],
                                'name'   => $n,
                                'type'   => Type::OBJECT,
                                'id'     => $tpl['id'],
                                'side'   => (($tpl['A'] < 0 ? 0 : SIDE_ALLIANCE) | ($tpl['H'] < 0 ? 0 : SIDE_HORDE)),
                                'quests' => array_values($_)
                            ));

                        if (($tpl['H'] != -1) && ($_ = $started->getSOMData(SIDE_HORDE)))
                            $addToSOM('hordequests', $n, array(
                                'coords' => [[$spawn['posX'], $spawn['posY']]],
                                'level'  => $spawn['floor'],
                                'name'   => $n,
                                'type'   => Type::OBJECT,
                                'id'     => $tpl['id'],
                                'side'   => (($tpl['A'] < 0 ? 0 : SIDE_ALLIANCE) | ($tpl['H'] < 0 ? 0 : SIDE_HORDE)),
                                'quests' => array_values($_)
                            ));
                }
            }

            $flightNodes = [];
            foreach ($cSpawns as $spawn)
            {
                $tpl = $creatureSpawns->getEntry($spawn['typeId']);
                if (!$tpl)
                    continue;

                $n  = Util::localizedString($tpl, 'name');
                $sn = Util::localizedString($tpl, 'subname');

                $somNPC = array(
                    'coords'        => [[$spawn['posX'], $spawn['posY']]],
                    'level'         => $spawn['floor'],
                    'name'          => $n,
                    'type'          => Type::NPC,
                    'id'            => $tpl['id'],
                    'reacthorde'    => $tpl['H'] ?: 1,      // no neutral (0) setting
                    'reactalliance' => $tpl['A'] ?: 1,
                    'description'   => $sn
                );

                $flagsMap = array(
                    NPC_FLAG_REPAIRER        => 'repair',
                    NPC_FLAG_AUCTIONEER      => 'auctioneer',
                    NPC_FLAG_BANKER          => 'banker',
                    NPC_FLAG_BATTLEMASTER    => 'battlemaster',
                    NPC_FLAG_INNKEEPER       => 'innkeeper',
                    NPC_FLAG_TRAINER         => 'trainer',
                    NPC_FLAG_VENDOR          => 'vendor',
                    NPC_FLAG_FLIGHT_MASTER   => 'flightmaster',
                    NPC_FLAG_STABLE_MASTER   => 'stablemaster',
                    NPC_FLAG_GUILD_MASTER    => 'guildmaster',
                    NPC_FLAG_SPIRIT_HEALER |
                    NPC_FLAG_SPIRIT_GUIDE    => 'spirithealer'
                );

                foreach ($flagsMap as $flag => $what)
                    if ($tpl['npcflag'] & $flag)
                        $addToSOM($what, $n, $somNPC);

                if ($creatureSpawns->isBoss())
                    $addToSOM('boss', $n, $somNPC);
                else if ($tpl['rank'] == NPC_RANK_RARE_ELITE || $tpl['rank'] == NPC_RANK_RARE)
                    $addToSOM('rare', $n, $somNPC);

                if ($tpl['npcflag'] & NPC_FLAG_FLIGHT_MASTER)
                    $flightNodes[$tpl['id']] = [$spawn['posX'], $spawn['posY']];

                if ($tpl['startsQuests'])
                {
                        $started = new QuestList(array(['qse.method', 1, '&'], ['qse.type', Type::NPC], ['qse.typeId', $tpl['id']]));
                        if ($started->error)
                            continue;

                        // store data for misc tabs
                        foreach ($started->getListviewData() as $id => $data)
                        {
                            if ($started->getField('questSortId') > 0 && !in_array($started->getField('questSortId'), $relQuestZOS))
                                continue;

                            if (!empty($started->rewards[$id][Type::ITEM]))
                                $rewardsLV = array_merge($rewardsLV, array_keys($started->rewards[$id][Type::ITEM]));

                            if (!empty($started->choices[$id][Type::ITEM]))
                                $rewardsLV = array_merge($rewardsLV, array_keys($started->choices[$id][Type::ITEM]));

                            $questsLV[$id] = $data;
                        }

                        $this->extendGlobalData($started->getJSGlobals());

                        if (($tpl['A'] != -1) && ($_ = $started->getSOMData(SIDE_ALLIANCE)))
                            $addToSOM('alliancequests', $n, array(
                                'coords'        => [[$spawn['posX'], $spawn['posY']]],
                                'level'         => $spawn['floor'],
                                'name'          => $n,
                                'type'          => Type::NPC,
                                'id'            => $tpl['id'],
                                'reacthorde'    => $tpl['H'],
                                'reactalliance' => $tpl['A'],
                                'side'          => (($tpl['A'] < 0 ? 0 : SIDE_ALLIANCE) | ($tpl['H'] < 0 ? 0 : SIDE_HORDE)),
                                'quests'        => array_values($_)
                            ));

                        if (($tpl['H'] != -1) && ($_ = $started->getSOMData(SIDE_HORDE)))
                            $addToSOM('hordequests', $n, array(
                                'coords'        => [[$spawn['posX'], $spawn['posY']]],
                                'level'         => $spawn['floor'],
                                'name'          => $n,
                                'type'          => Type::NPC,
                                'id'            => $tpl['id'],
                                'reacthorde'    => $tpl['H'],
                                'reactalliance' => $tpl['A'],
                                'side'          => (($tpl['A'] < 0 ? 0 : SIDE_ALLIANCE) | ($tpl['H'] < 0 ? 0 : SIDE_HORDE)),
                                'quests'        => array_values($_)
                            ));
                }
            }

            foreach ($aSpawns as $spawn)
            {
                if ($spawn['guid'] < 0)                     // skip teleporter endpoints
                    continue;

                $tpl = $atSpawns->getEntry($spawn['typeId']);
                if (!$tpl)
                    continue;

                $n = Util::localizedString($tpl, 'name');
                $addToSOM('areatrigger', $n, array(
                    'coords'        => [[$spawn['posX'], $spawn['posY']]],
                    'level'         => $spawn['floor'],
                    'name'          => $n,
                    'type'          => Type::AREATRIGGER,
                    'id'            => $spawn['typeId'],
                    'description'   => Lang::game('type').Lang::areatrigger('types', $tpl['type'])
                ));
            }

            // remove unwanted indizes
            foreach ($som as $what => &$dataz)
            {
                if (empty($som[$what]))
                    continue;

                foreach ($dataz as &$data)
                    $data = array_values($data);

                if (!in_array($what, ['vein', 'herb', 'rare', 'pool']))
                {
                    $foo = [];
                    foreach ($dataz as $d)
                        foreach ($d as $_)
                            $foo[] = $_;

                    $dataz = $foo;
                }
            }

            unset($data);

            // append paths between nodes
            if ($flightNodes)
            {
                // neutral nodes come last as the line is colored by the node it's attached to
                usort($som['flightmaster'], function($a, $b) {
                    $n1 = (int)$a['reactalliance'] == $a['reacthorde'];
                    $n2 = (int)$b['reactalliance'] == $b['reacthorde'];

                    return $n1 <=> $n2;
                });

                $paths = DB::Aowow()->selectAssoc('SELECT n1.`typeId` AS "0", n2.`typeId` AS "1" FROM ::taxipath p JOIN ::taxinodes n1 ON n1.`id` = p.`startNodeId` JOIN ::taxinodes n2 ON n2.`id` = p.`endNodeId` WHERE n1.`typeId` IN %in AND n2.`typeId` IN %in', array_keys($flightNodes), array_keys($flightNodes));

                foreach ($paths as $k => $path)
                {
                    foreach ($som['flightmaster'] as &$fm)
                    {
                        if ($fm['id'] != $path[0] && $fm['id'] != $path[1])
                            continue;

                        if ($fm['id'] == $path[0])
                            $fm['paths'][] = $flightNodes[$path[1]];

                        if ($fm['id'] == $path[1])
                            $fm['paths'][] = $flightNodes[$path[0]];

                        unset($paths[$k]);
                        break;
                    }
                }
            }

            // preselect bosses for raids/dungeons
            if (in_array($_type, [MAP_TYPE_DUNGEON, MAP_TYPE_RAID, MAP_TYPE_BATTLEGROUND, MAP_TYPE_DUNGEON_HC, MAP_TYPE_MMODE_RAID, MAP_TYPE_MMODE_RAID_HC]))
                $som['instance'] = true;

            $this->map = array(
                array(                                      // Mapper
                    'parent'   => 'mapper-generic',
                    'zone'     => $this->typeId,
                    'zoneLink' => false
                ),
                null,                                       // mapperData
                $som,                                       // ShowOnMap
                null                                        // foundIn
            );
        }

        $this->expansion  = Util::$expansionString[$this->subject->getField('expansion')];
        $this->redButtons = array(
            BUTTON_WOWHEAD => true,
            BUTTON_LINKS   => ['type' => $this->type, 'typeId' => $this->typeId]
        );


        /**************/
        /* Extra Tabs */
        /**************/

        $this->lvTabs = new Tabs(['parent' => "\$\$WH.ge('tabs-generic')"], 'tabsRelated', true);

        // aowow - custom start: the NPCs that queue for this battleground
        // `battlemaster_entry` is the only link between a creature and a bgTypeId and was read nowhere
        if ($bg && ($bmIds = self::getBattlemastersFor($bg['id'])))
        {
            $bms = new CreatureList(array(['id', $bmIds]));
            if (!$bms->error)
            {
                $this->extendGlobalData($bms->getJSGlobals());

                $this->addDataLoader('zones');
                $this->lvTabs->addListviewTab(new Listview(array(
                    'data' => $bms->getListviewData(),
                    'name' => Lang::zone('battlemasters'),
                    'id'   => 'battlemasters'
                ), CreatureList::$brickFile));
            }
        }
        // aowow - custom end

        // tab: drops
        if (in_array($this->subject->getField('category'), [MAP_TYPE_DUNGEON, MAP_TYPE_RAID]))
        {
            // Issue 1 - if the bosses drop items that are also sold by vendors moreZoneId will be 0 as vendor location and boss location are likely in conflict with each other
            // Issue 2 - if the boss/chest isn't spawned the loot will not show up
            $items   = new ItemList(array(['src.moreZoneId', $this->typeId], ['src.src2', 0, '>'], ['quality', ITEM_QUALITY_UNCOMMON, '>=']), ['calcTotal' => true]);
            $data    = $items->getListviewData();
            $subTabs = false;
            foreach ($items->iterate() as $id => $__)
            {
                $src = $items->getRawSource(SRC_DROP);
                $map = ($items->getField('moreMask') ?: 0) & (SRC_FLAG_DUNGEON_DROP | SRC_FLAG_RAID_DROP);
                if (!$src || !$map)
                    continue;

                $subTabs = true;

                if ($map & SRC_FLAG_RAID_DROP)
                    $mode = ($src[0] << 3);
                else
                    $mode = ($src[0] & 0x1 ? 0x2 : 0) | ($src[0] & 0x2 ? 0x1 : 0);

                $data[$id] += ['modes' => ['mode' => $mode]];
            }

            $tabData = array(
                'data'            => $data,
                'id'              => 'drops',
                'name'            => '$LANG.tab_drops',
                'extraCols'       => $subTabs ? ['$Listview.extraCols.mode'] : null,
                'computeDataFunc' => '$Listview.funcBox.initLootTable',
                'onAfterCreate'   => $subTabs ? '$Listview.funcBox.addModeIndicator' : null
            );

            if (!is_null(ItemListFilter::getCriteriaIndex(16, $this->typeId)))
                $tabData['note'] = sprintf(Util::$filterResultString, '?items&filter=cr=16;crs='.$this->typeId.';crv=0');

            $this->extendGlobalData($items->getJSGlobals(GLOBALINFO_SELF));

            $this->lvTabs->addListviewTab(new Listview($tabData, ItemList::$brickFile));
        }

        // tab: npcs
        if ($cSpawns && !$creatureSpawns->error)
        {
            $tabData = ['data' => $creatureSpawns->getListviewData()];

            if (!is_null(CreatureListFilter::getCriteriaIndex(6, $this->typeId)))
                $tabData['note'] = sprintf(Util::$filterResultString, '?npcs&filter=cr=6;crs='.$this->typeId.';crv=0');

            if ($creatureSpawns->getMatches() > Listview::DEFAULT_SIZE)
                $tabData['_truncated'] = 1;

            $this->extendGlobalData($creatureSpawns->getJSGlobals(GLOBALINFO_SELF));

            $this->lvTabs->addListviewTab(new Listview($tabData, CreatureList::$brickFile));
        }

        // tab: objects
        if ($oSpawns && !$objectSpawns->error)
        {
            $tabData = ['data' => $objectSpawns->getListviewData()];

            if (!is_null(GameObjectListFilter::getCriteriaIndex(1, $this->typeId)))
                $tabData['note'] = sprintf(Util::$filterResultString, '?objects&filter=cr=1;crs='.$this->typeId.';crv=0');

            if ($objectSpawns->getMatches() > Listview::DEFAULT_SIZE)
                $tabData['_truncated'] = 1;

            $this->extendGlobalData($objectSpawns->getJSGlobals(GLOBALINFO_SELF));

            $this->lvTabs->addListviewTab(new Listview($tabData, GameObjectList::$brickFile));
        }

        $quests = new QuestList(array(['questSortId', $this->typeId]));
        if (!$quests->error)
        {
            $this->extendGlobalData($quests->getJSGlobals());
            foreach ($quests->getListviewData() as $id => $data)
            {
                if (!empty($quests->rewards[$id][Type::ITEM]))
                    $rewardsLV = array_merge($rewardsLV, array_keys($quests->rewards[$id][Type::ITEM]));

                if (!empty($quests->choices[$id][Type::ITEM]))
                    $rewardsLV = array_merge($rewardsLV, array_keys($quests->choices[$id][Type::ITEM]));

                $questsLV[$id] = $data;
            }
        }

        // tab: quests [including data collected by SOM-routine]
        if ($questsLV)
        {
            $tabData = ['data' => $questsLV];

            foreach (Game::QUEST_CLASSES as $parent => $children)
            {
                if (!in_array($this->typeId, $children))
                    continue;

                if (!is_null(ItemListFilter::getCriteriaIndex(126, $this->typeId)))
                    $tabData['note'] = '$$WH.sprintf(LANG.lvnote_zonequests, '.$parent.', '.$this->typeId.',"'.$this->subject->getField('name', true).'", '.$this->typeId.')';
                else
                    $tabData['note'] = '$$WH.sprintf(LANG.lvnote_questsind, '.$parent.', '.$this->typeId.',"'.$this->subject->getField('name', true).'")';
                break;
            }

            $this->lvTabs->addListviewTab(new Listview($tabData, QuestList::$brickFile));
        }

        // tab: starts-quest
        // select every quest starter, that is a drop
        $questStartItem = DB::Aowow()->selectAssoc(
           'SELECT qse.`typeId` AS ARRAY_KEY, `moreType`, `moreTypeId`, `moreZoneId`
            FROM   ::quests_startend qse JOIN ::source src ON src.`type` = qse.`type` AND src.`typeId` = qse.`typeId`
            WHERE  src.`src2` IS NOT NULL AND qse.`type` = %i AND (`moreZoneId` = %i OR (`moreType` = %i AND `moreTypeId` IN %in) OR (`moreType` = %i AND `moreTypeId` IN %in))',
            Type::ITEM,   $this->typeId,
            Type::NPC,    array_unique(array_column($cSpawns, 'typeId')) ?: [0],
            Type::OBJECT, array_unique(array_column($oSpawns, 'typeId')) ?: [0]
        );

        if ($questStartItem)
        {
            $qsiList = new ItemList(array(['id', array_keys($questStartItem)]));
            if (!$qsiList->error)
            {
                $this->lvTabs->addListviewTab(new Listview(array(
                    'data' => $qsiList->getListviewData(),
                    'name' => '$LANG.tab_startsquest',
                    'id'   => 'starts-quest'
                ), ItemList::$brickFile));

                $this->extendGlobalData($qsiList->getJSGlobals(GLOBALINFO_SELF));
            }
        }

        // tab: quest-rewards [ids collected by SOM-routine]
        if ($rewardsLV)
        {
            $rewards = new ItemList(array(['id', array_unique($rewardsLV)]));
            if (!$rewards->error)
            {
                $note = null;
                if (!is_null(ItemListFilter::getCriteriaIndex(126, $this->typeId)))
                    $note = sprintf(Util::$filterResultString, '?items&filter=cr=126;crs='.$this->typeId.';crv=0');

                $this->lvTabs->addListviewTab(new Listview(array(
                    'data' => $rewards->getListviewData(),
                    'name' => '$LANG.tab_questrewards',
                    'id'   => 'quest-rewards',
                    'note' => $note
                ), ItemList::$brickFile));

                $this->extendGlobalData($rewards->getJSGlobals(GLOBALINFO_SELF));
            }
        }

        // tab: achievements

        // tab: criteria-of
        $crtOf = new AchievementList(AchievementList::getLocationConditions($this->typeId, $this->subject->getField('category') != MAP_TYPE_ZONE ? $this->subject->getField('mapId') : 0));
        if (!$crtOf->error)
        {
            $tabData = array(
                'data' => $crtOf->getListviewData(),
                'name' => '$LANG.tab_criteriaof',
                'id'   => 'criteria-of'
            );

            if (!is_null(AchievementListFilter::getCriteriaIndex(4, $this->typeId)))
                $tabData['note'] = sprintf(Util::$filterResultString, '?achievements&filter=cr=4;crs='.$this->typeId.';crv=0');

            $this->extendGlobalData($crtOf->getJSGlobals());

            $this->lvTabs->addListviewTab(new Listview($tabData, AchievementList::$brickFile));
        }

        // tab: fishing
        $fish = new LootByContainer(Loot::FISHING, $this->typeId);
        if ($fish->formatListview())
        {
            $this->extendGlobalData($fish->jsGlobals);
            $xCols = array_merge(['$Listview.extraCols.percent'], $fish->extraCols);

            $note = null;
            if ($skill = DB::World()->selectCell('SELECT `skill` FROM skill_fishing_base_level WHERE `entry` = %i', $this->typeId))
                $note = sprintf(Util::$lvTabNoteString, Lang::zone('fishingSkill'), Lang::formatSkillBreakpoints(Game::getBreakpointsForSkill(SKILL_FISHING, $skill), Lang::FMT_HTML));
            else if ($_parentArea && ($skill = DB::World()->selectCell('SELECT `skill` FROM skill_fishing_base_level WHERE `entry` = %i', $_parentArea)))
                $note = sprintf(Util::$lvTabNoteString, Lang::zone('fishingSkill'), Lang::formatSkillBreakpoints(Game::getBreakpointsForSkill(SKILL_FISHING, $skill), Lang::FMT_HTML));

            $this->lvTabs->addListviewTab(new Listview(array(
                'data'            => $fish->getResult(),
                'name'            => '$LANG.tab_fishing',
                'id'              => 'fishing',
                'extraCols'       => array_unique($xCols),
                'hiddenCols'      => ['side'],
                'note'            => $note,
                'computeDataFunc' => '$Listview.funcBox.initLootTable'
            ), ItemList::$brickFile));
        }

        // tab: spells
        if ($saData = DB::World()->selectAssoc('SELECT * FROM spell_area WHERE `area` = %i', $this->typeId))
        {
            $spells = new SpellList(array(['id', array_column($saData, 'spell')]));
            if (!$spells->error)
            {
                $lvSpells = $spells->getListviewData();
                $this->extendGlobalData($spells->getJSGlobals());

                $cnd = new Conditions();
                foreach ($saData as $a)
                {
                    if (empty($lvSpells[$a['spell']]))
                        continue;

                    if ($a['aura_spell'])
                        $cnd->addExternalCondition(Conditions::SRC_NONE, $a['spell'], [$a['aura_spell'] >  0 ? Conditions::AURA : -Conditions::AURA, abs($a['aura_spell'])]);

                    if ($a['quest_start'])                  // status for quests needs work
                        $cnd->addExternalCondition(Conditions::SRC_NONE, $a['spell'], [Conditions::QUESTSTATE, $a['quest_start'], $a['quest_start_status']]);

                    if ($a['quest_end'] && $a['quest_end'] != $a['quest_start'])
                        $cnd->addExternalCondition(Conditions::SRC_NONE, $a['spell'], [Conditions::QUESTSTATE, $a['quest_end'], $a['quest_end_status']]);

                    if ($a['racemask'])
                        $cnd->addExternalCondition(Conditions::SRC_NONE, $a['spell'], [Conditions::CHR_RACE, $a['racemask']]);

                    if ($a['gender'] != 2)                  // 2: both
                        $cnd->addExternalCondition(Conditions::SRC_NONE, $a['spell'], [Conditions::GENDER, $a['gender']]);
                }

                if ($cnd->toListviewColumn($lvSpells, $extraCols))
                    $this->extendGlobalData($cnd->getJSGlobals());

                $this->lvTabs->addListviewTab(new Listview(array(
                    'data'       => $lvSpells,
                    'hiddenCols' => ['skill'],
                    'extraCols'  => $extraCols ?: null
                ), SpellList::$brickFile));
            }
        }

        // tab: subzones
        $subZones = new ZoneList(array(['parentArea', $this->typeId]));
        if (!$subZones->error)
        {
            $this->lvTabs->addListviewTab(new Listview(array(
                'data'       => $subZones->getListviewData(),
                'name'       => '$LANG.tab_zones',
                'id'         => 'subzones',
                'hiddenCols' => ['territory', 'instancetype']
            ), ZoneList::$brickFile));

            $this->extendGlobalData($subZones->getJSGlobals(GLOBALINFO_SELF));
        }

        // tab: sound (including subzones; excluding parents)
        $areaIds = [];
        if (!$subZones->error)
            $areaIds = $subZones->getFoundIDs();

        $areaIds[] = $this->typeId;

        $soundIds  = [];
        $zoneMusic = DB::Aowow()->selectAssoc(
           'SELECT   x.`soundId` AS ARRAY_KEY, x.`soundId`, x.`worldStateId`, x.`worldStateValue`, x.`type`
            FROM    (SELECT `ambienceDay`   AS "soundId", `worldStateId`, `worldStateValue`, 1 AS "type" FROM ::zones_sounds WHERE `id` IN %in AND `ambienceDay`   > 0 UNION
                     SELECT `ambienceNight` AS "soundId", `worldStateId`, `worldStateValue`, 1 AS "type" FROM ::zones_sounds WHERE `id` IN %in AND `ambienceNight` > 0 UNION
                     SELECT `musicDay`      AS "soundId", `worldStateId`, `worldStateValue`, 2 AS "type" FROM ::zones_sounds WHERE `id` IN %in AND `musicDay`      > 0 UNION
                     SELECT `musicNight`    AS "soundId", `worldStateId`, `worldStateValue`, 2 AS "type" FROM ::zones_sounds WHERE `id` IN %in AND `musicNight`    > 0 UNION
                     SELECT `intro`         AS "soundId", `worldStateId`, `worldStateValue`, 3 AS "type" FROM ::zones_sounds WHERE `id` IN %in AND `intro`         > 0) x
            GROUP BY x.soundId, x.worldStateId, x.worldStateValue',
            $areaIds, $areaIds, $areaIds, $areaIds, $areaIds
        );

        if ($sSpawns = DB::Aowow()->selectCol('SELECT `typeId` FROM ::spawns WHERE `areaId` = %i AND `type` = %i', $this->typeId, Type::SOUND))
            $soundIds = array_merge($soundIds, $sSpawns);

        if ($zoneMusic)
            $soundIds = array_merge($soundIds, array_column($zoneMusic, 'soundId'));

        if ($soundIds)
        {
            $music = new SoundList(array(['id', array_unique($soundIds)]));
            if (!$music->error)
            {
                // tab
                $data    = $music->getListviewData();
                $tabData = [];

                if (array_filter(array_column($zoneMusic, 'worldStateId')))
                {
                    $tabData['extraCols'] = ['$Listview.extraCols.condition'];

                    foreach ($soundIds as $sId)
                        if (!empty($zoneMusic[$sId]['worldStateId']))
                            Conditions::extendListviewRow($data[$sId], Conditions::SRC_NONE, $this->typeId, [Conditions::WORLD_STATE, $zoneMusic[$sId]['worldStateId'], $zoneMusic[$sId]['worldStateValue']]);
                }

                $tabData['data'] = $data;

                $this->lvTabs->addListviewTab(new Listview($tabData, SoundList::$brickFile));

                $this->extendGlobalData($music->getJSGlobals(GLOBALINFO_SELF));

                $typeFilter = function(array $music, int $type) use ($data) : array
                {
                    $result = [];
                    foreach (array_filter($music, fn ($x) => $x['type'] == $type) as $sId => $_)
                        $result = array_merge($result, $data[$sId]['files'] ?? []);

                    return $result;
                };

                // audio controls (order how it appears on page)
                // [title, data, divID, options]
                if ($_ = $typeFilter($zoneMusic, 2))
                    $this->zoneMusic[] = [Lang::sound('music'), $_, 'zonemusic', (object)['loop' => true]];

                if ($_ = $typeFilter($zoneMusic, 3))
                    $this->zoneMusic[] = [Lang::sound('intro'), $_, 'zonemusicintro', (object)[]];

                if ($_ = $typeFilter($zoneMusic, 1))
                    $this->zoneMusic[] = [Lang::sound('ambience'), $_, 'soundambience', (object)['loop' => true]];
            }
        }

        // tab: condition-for
        $cnd = new Conditions();
        $cnd->getByCondition(Type::ZONE, $this->typeId)->prepare();
        if ($tab = $cnd->toListviewTab('condition-for', '$LANG.tab_condition_for'))
        {
            $this->extendGlobalData($cnd->getJSGlobals());
            $this->lvTabs->addDataTab(...$tab);
        }

        // aowow - custom start: the points of interest this zone's gossip options mark on the map
        // points_of_interest has no zone column; it is reached through the gossip menus of the
        // NPCs that spawn here
        if ($poiData = self::getPOIsForZone($this->typeId, $mapId))
        {
            $this->lvTabs->addListviewTab(new Listview(array(
                'data' => $poiData,
                'name' => Lang::zone('poi'),
                'id'   => 'poi'
            ), 'poi', 'poi'));
        }
        // aowow - custom end
    }

    private function addMoveLocationMenu(int $_parentArea, int $parentFloor) : void
    {
        // hide for non-staff
        if (!User::isInGroup(U_GROUP_EMPLOYEE))
            return;

        $worldPos = WorldPosition::getForGUID(Type::ZONE, -$this->typeId);
        if (!$worldPos)
            return;

        $menu = Util::buildPosFixMenu($worldPos[-$this->typeId]['mapId'], $worldPos[-$this->typeId]['posX'], $worldPos[-$this->typeId]['posY'], Type::ZONE, -$this->typeId, $_parentArea, $parentFloor);
        if (!$menu)
            return;

        $menu = [1002, 'Edit DB Entry', null, $menu];

        $this->addScript([SC_JS_STRING, '$(document).ready(function () { mn_staff.push('.Util::toJSON(array_values($menu)).'); });']);
    }

    protected function generateMetadata(bool $useArticle = true) : void
    {
        $this->metaTags[] = ['property' => 'og:title', 'content' => $this->h1];
        $this->metaTags[] = ['property' => 'og:type',  'content' => 'article'];

        if ($f = ($this->zoneMusic['music'][0] ?? null))
        {
            $this->metaTags[] = ['property' => 'og:audio',      'content' => $f['url']];
            $this->metaTags[] = ['property' => 'og:audio:type', 'content' => $f['type']];
        }

        $cat = $this->subject->getField('category');

        $keywords = [$this->h1, Util::ucFirst(Lang::game('zone')), Lang::zone('cat', $cat), Lang::zone('territories', $this->subject->getField('faction'))];

        $n = Lang::zone('territories', $this->subject->getField('faction')).' '.Lang::game('zone');
        if ($t = $this->subject->getField('type'))
        {
            $n = Lang::main('parensFmt', [$n, Lang::zone('instanceTypes', $t)]);
            $keywords[] = Lang::zone('instanceTypes', $t);
        }

        $desc  = Lang::meta('description', 'genPage', [$this->h1, $n]);
        $desc .= ' '.Lang::meta('inCategory', [Lang::zone('cat', $cat)]);

        if ($cat == 2 || $cat == 3)
            $keywords[] = Lang::game('expansions', $this->subject->getField('expansion'));

        array_unshift($this->metaTags, ['name' => 'keywords', 'content' => [...$keywords, ...Lang::meta('tags', 'generic')]]);

        $this->buildBasicMetadata($desc);

        $this->buildLdJson();
    }

    // aowow - custom start: BattlemasterList.dbc / LFGDungeons.dbc lookups
    // both tables are written by the `zones` setup step and stay in the aowow DB unless setup ran
    // with --delete, so every one of these guards the table before touching it

    private static function hasTable(string $tbl, bool $aowow = true) : bool
    {
        static $known = [];

        return $known[($aowow ? 'a:' : 'w:').$tbl] ??= (bool)($aowow ? DB::Aowow() : DB::World())->selectCell('SHOW TABLES LIKE %s', $tbl);
    }

    private static function getBattlemasterList(int $mapId) : ?array
    {
        if ($mapId <= 0 || !self::hasTable('dbc_battlemasterlist'))
            return null;

        $r = DB::Aowow()->selectRow('SELECT `id`, `minLevel`, `maxLevel`, `maxPlayers` FROM dbc_battlemasterlist WHERE `mapId` = %i AND `moreMapId` < 0 LIMIT 1', $mapId);

        return $r ? array_map('intVal', $r) : null;
    }

    private static function getLFGDungeon(int $mapId) : ?array
    {
        if ($mapId <= 0 || !self::hasTable('dbc_lfgdungeons'))
            return null;

        // a map can hold several difficulties; the normal one carries the bracket players actually queue at
        $r = DB::Aowow()->selectRow('SELECT `id`, `levelMin`, `levelMax`, `targetLevel`, `type`, `expansion` FROM dbc_lfgdungeons WHERE `mapId` = %i ORDER BY `difficulty` ASC LIMIT 1', $mapId);

        return $r ? array_map('intVal', $r) : null;
    }

    /**
     * `battleground_template` - what the server enforces, which the client's dbc does not know
     * the bracket is in both, and it is the world DB one that decides who may queue
     */
    private static function getBattlegroundTemplate(int $bgTypeId) : array
    {
        if ($bgTypeId <= 0 || !self::hasTable('battleground_template', false))
            return [];

        // the columns were renamed across TC revisions, so the row is read whole and its keys matched lowercased
        $r = DB::World()->selectRow('SELECT * FROM battleground_template WHERE `ID` = %i', $bgTypeId);

        return $r ? array_change_key_case($r, CASE_LOWER) : [];
    }

    /** `lfg_dungeon_rewards` - the quest the Dungeon Finder hands out for a run, by level cap */
    private static function getLFGRewards(int $dungeonId) : array
    {
        if ($dungeonId <= 0 || !self::hasTable('lfg_dungeon_rewards', false))
            return [];

        $rows = DB::World()->selectAssoc(
           'SELECT `maxLevel`, `firstQuestId`, `otherQuestId` FROM lfg_dungeon_rewards WHERE `dungeonId` = %i ORDER BY `maxLevel` ASC',
            $dungeonId
        ) ?: [];

        return array_map(fn($r) => array_map('intVal', $r), $rows);
    }

    /** `instance_template` - what the server knows about an instance map that the dbc does not */
    private function getInstanceTemplate(int $mapId, ?array $portal = null) : array
    {
        if ($mapId <= 0 || !self::hasTable('instance_template', false))
            return [];

        if (!($r = DB::World()->selectRow('SELECT * FROM instance_template WHERE `map` = %i', $mapId)))
            return [];

        $r   = array_change_key_case($r, CASE_LOWER);
        $out = [];

        // only the exception is worth a line - an instance blocks mounts unless it says otherwise
        if (!empty($r['allowmount']))
            $out[] = Lang::zone('mountsAllowed');

        // the map an instance is entered from, which is how the core groups its resets
        if ($parent = (int)($r['parent'] ?? 0))
        {
            if ($zoneId = (int)DB::Aowow()->selectCell('SELECT `id` FROM ::zones WHERE `mapId` = %i AND `parentArea` = 0 AND (`cuFlags` & %i) = 0 LIMIT 1', $parent, CUSTOM_EXCLUDE_FOR_LISTVIEW))
            {
                $this->extendGlobalIds(Type::ZONE, $zoneId);

                // the zone's own spawn row marks the entrance on the map it is entered from
                if ($portal)
                    $out[] = Lang::zone('enteredFrom').Lang::main('colon').self::makeMapLink($portal);
                else
                    $out[] = Lang::zone('enteredFrom').Lang::main('colon').'[zone='.$zoneId.']';
            }
        }

        if (($script = trim((string)($r['script'] ?? ''))) && User::isInGroup(U_GROUP_STAFF))
            $out[] = Lang::zone('instanceScript').Lang::main('colon').$script;

        return $out;
    }

    /**
     * a [lightbox=map] link to the map a spawn row sits on, pinned at its position
     * the pin code is the tile position - six digits, zero-padded per axis - the
     * client side decodes back into the same (posX, posY) the mapper displays
     */
    private static function makeMapLink(array $spawn) : string
    {
        $pins = str_pad((int)round($spawn['posX'] * 10), 3, '0', STR_PAD_LEFT) . str_pad((int)round($spawn['posY'] * 10), 3, '0', STR_PAD_LEFT);

        return '[lightbox=map zone='.$spawn['areaId'].' '.($spawn['floor'] > 1 ? 'floor='.($spawn['floor'] - 1) : '').' pins='.$pins.']'.ZoneList::getName($spawn['areaId']).'[/lightbox]';
    }

    private static function getBattlemastersFor(int $bgTypeId) : array
    {
        if ($bgTypeId <= 0 || !DB::World()->selectCell('SHOW TABLES LIKE %s', 'battlemaster_entry'))
            return [];

        $ids = DB::World()->selectCol('SELECT `entry` FROM battlemaster_entry WHERE `bg_template` = %i', $bgTypeId) ?: [];

        return array_values(array_filter(array_map('intVal', $ids)));
    }

    /** `game_event_battleground_holiday` - which Call to Arms events rotate this battleground in */
    private static function getBattlegroundHoliday(int $bgTypeId) : array
    {
        if ($bgTypeId <= 0 || !self::hasTable('game_event_battleground_holiday', false))
            return [];

        $rows = DB::World()->selectAssoc('SELECT * FROM game_event_battleground_holiday') ?: [];

        $out = [];
        foreach ($rows as $r)
        {
            $lc   = array_change_key_case($r, CASE_LOWER);
            $flag = (int)($lc['bgflag'] ?? $lc['bg_flag'] ?? $lc['battleground'] ?? $lc['bg'] ?? 0);
            if ($flag == $bgTypeId && ($ev = (int)($lc['evententry'] ?? $lc['event'] ?? 0)))
                $out[$ev] = $ev;
        }

        return array_values($out);
    }

    /**
     * `points_of_interest` has no zone column, so the ones a zone page can show are reached
     * through the gossip menus of the NPCs that spawn there
     */
    private static function getPOIsForZone(int $areaId, int $mapId) : array
    {
        foreach (['points_of_interest', 'gossip_menu', 'gossip_menu_option', 'creature_template', 'creature'] as $tbl)
            if (!self::hasTable($tbl, false))
                return [];

        $guids = DB::Aowow()->selectCol('SELECT `guid` FROM ::spawns WHERE `type` = %i AND `areaId` = %i AND `posX` > 0 AND `posY` > 0', Type::NPC, $areaId);
        if (!$guids)
            return [];

        // 3.3.5 spells the columns differently from newer cores; both are tried, as in Gossip
        $rows = DB::World()->selectAssoc(
           'SELECT poi.`ID` AS "id", MIN(poi.`PositionX`) AS "x", MIN(poi.`PositionY`) AS "y", MIN(poi.`Icon`) AS "icon", MIN(poi.`Name`) AS "name"
            FROM   creature c
            JOIN   creature_template ct ON ct.`entry` = c.`id`
            JOIN   gossip_menu gm ON gm.`MenuID` = ct.`gossip_menu_id`
            JOIN   gossip_menu_option gmo ON gmo.`MenuID` = gm.`MenuID` AND gmo.`ActionPoiID` > 0
            JOIN   points_of_interest poi ON poi.`ID` = gmo.`ActionPoiID`
            WHERE  c.`guid` IN %in
            GROUP BY poi.`ID`', $guids
        );

        if ($rows === null)
        {
            $rows = DB::World()->selectAssoc(
               'SELECT poi.`entry` AS "id", MIN(poi.`x`) AS "x", MIN(poi.`y`) AS "y", MIN(poi.`icon`) AS "icon", MIN(poi.`icon_name`) AS "name"
                FROM   creature c
                JOIN   creature_template ct ON ct.`entry` = c.`id`
                JOIN   gossip_menu gm ON gm.`MenuID` = ct.`gossip_menu_id`
                JOIN   gossip_menu_option gmo ON gmo.`MenuID` = gm.`MenuID` AND gmo.`ActionPoiID` > 0
                JOIN   points_of_interest poi ON poi.`entry` = gmo.`ActionPoiID`
                WHERE  c.`guid` IN %in
                GROUP BY poi.`entry`', $guids
            ) ?: [];
        }

        $data = [];
        foreach ($rows as $r)
        {
            $name = trim((string)$r['name']);
            $x    = (float)$r['x'];
            $y    = (float)$r['y'];

            $poi = array(
                'id'      => (int)$r['id'],
                'name'    => $name !== '' && $name[0] == '$' ? ' '.$name : $name,
                'zone'    => $areaId,
                'x'       => $x,
                'y'       => $y,
                'icon'    => (int)$r['icon'],
                'maplink' => '?maps='.$areaId               // pin-less fallback; refined below when a pin resolves
            );

            if ($mapId > 0 && ($pt = WorldPosition::toZonePos($mapId, $x, $y, $areaId)))
                $poi['maplink'] = '?maps='.$areaId.':'.self::pinStr($pt[0]['posX']).self::pinStr($pt[0]['posY']);

            $data[] = $poi;
        }

        return $data;
    }

    /** the three-digit pin block the Mapper link format uses per coordinate */
    private static function pinStr(float $coord) : string
    {
        return sprintf('%03d', (int)round($coord * 10));
    }
    // aowow - custom end

    // aowow - custom start: graveyard and weather lookups

    /** @return string[] one line per graveyard serving this zone */
    private static function getGraveyards(int $areaId) : array
    {
        if (!self::hasTable('graveyard_zone', false) || !self::hasTable('game_graveyard', false))
            return [];

        $rows = DB::World()->selectAssoc(
           'SELECT gy.`ID`, gy.`Map`, gy.`Comment`, gz.`Faction`
            FROM   graveyard_zone gz JOIN game_graveyard gy ON gy.`ID` = gz.`ID`
            WHERE  gz.`GhostZone` = %i', $areaId
        ) ?: [];

        $out = [];
        foreach ($rows as $r)
        {
            $name = trim((string)$r['Comment']) ?: Lang::zone('graveyardUnnamed', [(int)$r['ID']]);
            $side = match ((int)$r['Faction'])
            {
                TEAM_ALLIANCE => '[span class=icon-alliance]%s[/span]',
                TEAM_HORDE    => '[span class=icon-horde]%s[/span]',
                default       => '%s'
            };

            $out[] = sprintf($side, $name);
        }

        return $out;
    }

    /** the seasonal chances of `game_weather`, summarised as the wettest season */
    private static function getWeather(int $areaId) : ?string
    {
        if (!self::hasTable('game_weather', false))
            return null;

        if (!($r = DB::World()->selectRow('SELECT * FROM game_weather WHERE `zone` = %i', $areaId)))
            return null;

        $lc    = array_change_key_case($r, CASE_LOWER);
        $parts = [];

        foreach (['rain', 'snow', 'storm'] as $what)
        {
            $max = 0;
            foreach (['spring', 'summer', 'fall', 'winter'] as $season)
                $max = max($max, (int)($lc[$season.'_'.$what.'_chance'] ?? 0));

            if ($max)
                $parts[] = Lang::zone('weatherTypes', $what).' '.$max.'%';
        }

        return $parts ? implode(', ', $parts) : null;
    }
    // aowow - custom end
}

?>
