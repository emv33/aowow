<?php

namespace Aowow;

if (!defined('AOWOW_REVISION'))
    die('illegal access');


class AchievementCriteriaList extends DBTypeList
{
    public static int    $type      = 0;                    // no Type of its own; criteria are addressed via ?achievement-criteria
    public static string $brickFile = 'achievementcriteria';
    public static string $dataTable = '::achievementcriteria';

    protected string $queryBase = 'SELECT ac.*, ac.`id` AS ARRAY_KEY FROM ::achievementcriteria ac';
    protected array  $queryOpts = array(
                        'ac' => [['a'], 'o' => 'ac.`id` ASC'],
                        'a'  => ['j' => ['::achievement a ON a.`id` = ac.`refAchievementId`', true], 's' => ', a.`name_loc0` AS "achievementName_loc0", a.`name_loc2` AS "achievementName_loc2", a.`name_loc3` AS "achievementName_loc3", a.`name_loc4` AS "achievementName_loc4", a.`name_loc6` AS "achievementName_loc6", a.`name_loc8` AS "achievementName_loc8"']
                    );

    public const array TYPE_NAMES = array(
        ACHIEVEMENT_CRITERIA_TYPE_KILL_CREATURE        => 'Kill creature',
        ACHIEVEMENT_CRITERIA_TYPE_WIN_BG               => 'Win battleground',
        ACHIEVEMENT_CRITERIA_TYPE_REACH_LEVEL          => 'Reach level',
        ACHIEVEMENT_CRITERIA_TYPE_REACH_SKILL_LEVEL    => 'Reach skill level',
        ACHIEVEMENT_CRITERIA_TYPE_COMPLETE_ACHIEVEMENT => 'Complete achievement',
        ACHIEVEMENT_CRITERIA_TYPE_COMPLETE_QUESTS_IN_ZONE => 'Complete quests in zone',
        ACHIEVEMENT_CRITERIA_TYPE_COMPLETE_BATTLEGROUND => 'Complete battleground',
        ACHIEVEMENT_CRITERIA_TYPE_DEATH_AT_MAP         => 'Die on map',
        ACHIEVEMENT_CRITERIA_TYPE_KILLED_BY_CREATURE   => 'Killed by creature',
        ACHIEVEMENT_CRITERIA_TYPE_COMPLETE_QUEST       => 'Complete quest',
        ACHIEVEMENT_CRITERIA_TYPE_BE_SPELL_TARGET      => 'Be spell target',
        ACHIEVEMENT_CRITERIA_TYPE_CAST_SPELL           => 'Cast spell',
        ACHIEVEMENT_CRITERIA_TYPE_HONORABLE_KILL_AT_AREA => 'Honorable kill at area',
        ACHIEVEMENT_CRITERIA_TYPE_WIN_ARENA            => 'Win arena',
        ACHIEVEMENT_CRITERIA_TYPE_PLAY_ARENA           => 'Play arena',
        ACHIEVEMENT_CRITERIA_TYPE_LEARN_SPELL          => 'Learn spell',
        ACHIEVEMENT_CRITERIA_TYPE_OWN_ITEM             => 'Own item',
        ACHIEVEMENT_CRITERIA_TYPE_LEARN_SKILL_LEVEL    => 'Learn skill level',
        ACHIEVEMENT_CRITERIA_TYPE_USE_ITEM             => 'Use item',
        ACHIEVEMENT_CRITERIA_TYPE_LOOT_ITEM            => 'Loot item',
        ACHIEVEMENT_CRITERIA_TYPE_EXPLORE_AREA         => 'Explore area',
        ACHIEVEMENT_CRITERIA_TYPE_GAIN_REPUTATION      => 'Gain reputation',
        ACHIEVEMENT_CRITERIA_TYPE_HK_CLASS             => 'Honorable kill class',
        ACHIEVEMENT_CRITERIA_TYPE_HK_RACE              => 'Honorable kill race',
        ACHIEVEMENT_CRITERIA_TYPE_DO_EMOTE             => 'Do emote',
        ACHIEVEMENT_CRITERIA_TYPE_EQUIP_ITEM           => 'Equip item',
        ACHIEVEMENT_CRITERIA_TYPE_USE_GAMEOBJECT       => 'Use game object',
        ACHIEVEMENT_CRITERIA_TYPE_BE_SPELL_TARGET2     => 'Be spell target',
        ACHIEVEMENT_CRITERIA_TYPE_FISH_IN_GAMEOBJECT   => 'Fish in game object',
        ACHIEVEMENT_CRITERIA_TYPE_ON_LOGIN             => 'On login',
        ACHIEVEMENT_CRITERIA_TYPE_LEARN_SKILLLINE_SPELLS => 'Learn skill line spells',
        ACHIEVEMENT_CRITERIA_TYPE_KILL_CREATURE_TYPE   => 'Kill creature type',
        87                                              => 'Gain revered reputation',
        88                                              => 'Gain honored reputation',
        89                                              => 'Known factions'
    );

    private const array FLAG_NAMES = array(
        ACHIEVEMENT_CRITERIA_FLAG_SHOW_PROGRESS_BAR => 'Show progress bar',
        ACHIEVEMENT_CRITERIA_FLAG_HIDDEN            => 'Hidden',
        ACHIEVEMENT_CRITERIA_FLAG_MONEY_COUNTER     => 'Money counter'
    );

    private const array ASSET_TYPES = array(
        ACHIEVEMENT_CRITERIA_TYPE_KILL_CREATURE        => Type::NPC,
        ACHIEVEMENT_CRITERIA_TYPE_KILLED_BY_CREATURE   => Type::NPC,
        ACHIEVEMENT_CRITERIA_TYPE_REACH_SKILL_LEVEL    => Type::SKILL,
        ACHIEVEMENT_CRITERIA_TYPE_COMPLETE_ACHIEVEMENT => Type::ACHIEVEMENT,
        ACHIEVEMENT_CRITERIA_TYPE_COMPLETE_QUESTS_IN_ZONE => Type::ZONE,
        ACHIEVEMENT_CRITERIA_TYPE_COMPLETE_QUEST       => Type::QUEST,
        ACHIEVEMENT_CRITERIA_TYPE_BE_SPELL_TARGET      => Type::SPELL,
        ACHIEVEMENT_CRITERIA_TYPE_CAST_SPELL           => Type::SPELL,
        ACHIEVEMENT_CRITERIA_TYPE_HONORABLE_KILL_AT_AREA => Type::ZONE,
        ACHIEVEMENT_CRITERIA_TYPE_LEARN_SPELL          => Type::SPELL,
        ACHIEVEMENT_CRITERIA_TYPE_OWN_ITEM             => Type::ITEM,
        ACHIEVEMENT_CRITERIA_TYPE_LEARN_SKILL_LEVEL    => Type::SKILL,
        ACHIEVEMENT_CRITERIA_TYPE_USE_ITEM             => Type::ITEM,
        ACHIEVEMENT_CRITERIA_TYPE_LOOT_ITEM            => Type::ITEM,
        ACHIEVEMENT_CRITERIA_TYPE_GAIN_REPUTATION      => Type::FACTION,
        ACHIEVEMENT_CRITERIA_TYPE_HK_CLASS             => Type::CHR_CLASS,
        ACHIEVEMENT_CRITERIA_TYPE_HK_RACE              => Type::CHR_RACE,
        ACHIEVEMENT_CRITERIA_TYPE_DO_EMOTE             => Type::EMOTE,
        ACHIEVEMENT_CRITERIA_TYPE_EQUIP_ITEM           => Type::ITEM,
        ACHIEVEMENT_CRITERIA_TYPE_USE_GAMEOBJECT       => Type::OBJECT,
        ACHIEVEMENT_CRITERIA_TYPE_BE_SPELL_TARGET2     => Type::SPELL,
        ACHIEVEMENT_CRITERIA_TYPE_FISH_IN_GAMEOBJECT   => Type::OBJECT,
        ACHIEVEMENT_CRITERIA_TYPE_LEARN_SKILLLINE_SPELLS => Type::SKILL
    );

    public function __construct(array $conditions = [], array $miscData = [])
    {
        parent::__construct($conditions, $miscData);

        if ($this->error)
            return;

        // post processing
        foreach ($this->iterate() as $_id => &$_curTpl)
        {
            $_curTpl['name']            = Util::localizedString($_curTpl, 'name');
            $_curTpl['achievementName'] = Util::localizedString($_curTpl, 'achievementName');
        }
    }

    public function getListviewData() : array
    {
        $data = [];

        foreach ($this->iterate() as $__)
        {
            $type    = (int)$this->getField('type');
            $value1  = (int)$this->getField('value1');
            $assetType = self::ASSET_TYPES[$type] ?? 0;

            $data[$this->id] = array(
                'id'              => $this->id,
                'achievement'     => $this->getField('refAchievementId'),
                'achievementname' => $this->getField('achievementName', true),
                'type'            => $type,
                'typename'        => self::TYPE_NAMES[$type] ?? 'Criteria type #'.$type,
                'name'            => $this->getField('name', true),
                'value1'          => $value1,
                'value2'          => $this->getField('value2'),
                'flags'           => $this->getField('completionFlags'),
                'flagnames'       => self::formatFlags((int)$this->getField('completionFlags')),
                'asset'           => $value1,
                'asseturl'        => $assetType && $value1 ? '?'.Type::getFileString($assetType).'='.$value1 : '',
                'assettype'       => $assetType && $value1 ? Type::getJSGlobalString($assetType) : ''
            );
        }

        return $data;
    }

    public function getJSGlobals(int $addMask = GLOBALINFO_ANY) : array
    {
        $data = [];

        foreach ($this->iterate() as $__)
        {
            $type = (int)$this->getField('type');
            if ($assetType = self::ASSET_TYPES[$type] ?? 0)
                if ($value1 = (int)$this->getField('value1'))
                    $data[$assetType][$value1] = $value1;
        }

        return $data;
    }

    private static function formatFlags(int $flags) : string
    {
        $names = [];
        $rest  = $flags;

        foreach (self::FLAG_NAMES as $bit => $name)
        {
            if ($flags & $bit)
            {
                $names[] = $name;
                $rest &= ~$bit;
            }
        }

        if ($rest)
            $names[] = '0x'.dechex($rest);

        return $names ? implode(', ', $names) : ($flags ? '0x'.dechex($flags) : '');
    }

    public function renderTooltip() : ?string { return null; }
}

?>
