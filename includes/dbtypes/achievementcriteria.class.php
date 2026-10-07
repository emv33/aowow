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

    // aowow - custom: ids with no ACHIEVEMENT_CRITERIA_TYPE_* define are literals, named from the criteria rows that carry them
    public const array TYPE_NAMES = array(
        ACHIEVEMENT_CRITERIA_TYPE_KILL_CREATURE        => 'Kill creature',
        ACHIEVEMENT_CRITERIA_TYPE_WIN_BG               => 'Win battleground',
        ACHIEVEMENT_CRITERIA_TYPE_REACH_LEVEL          => 'Reach level',
        ACHIEVEMENT_CRITERIA_TYPE_REACH_SKILL_LEVEL    => 'Reach skill level',
        ACHIEVEMENT_CRITERIA_TYPE_COMPLETE_ACHIEVEMENT => 'Complete achievement',
        9                                              => 'Complete quest count',
        10                                             => 'Complete daily quest on consecutive days',
        ACHIEVEMENT_CRITERIA_TYPE_COMPLETE_QUESTS_IN_ZONE => 'Complete quests in zone',
        13                                             => 'Damage done',
        14                                             => 'Complete daily quest count',
        ACHIEVEMENT_CRITERIA_TYPE_COMPLETE_BATTLEGROUND => 'Complete battleground',
        ACHIEVEMENT_CRITERIA_TYPE_DEATH_AT_MAP         => 'Die on map',
        17                                             => 'Die',
        18                                             => 'Die in instance (size)',
        19                                             => 'Complete instance (size)',
        ACHIEVEMENT_CRITERIA_TYPE_KILLED_BY_CREATURE   => 'Killed by creature',
        23                                             => 'Killed by player',
        24                                             => 'Fall without dying',
        26                                             => 'Die from environment',
        ACHIEVEMENT_CRITERIA_TYPE_COMPLETE_QUEST       => 'Complete quest',
        ACHIEVEMENT_CRITERIA_TYPE_BE_SPELL_TARGET      => 'Be spell target (28)',
        ACHIEVEMENT_CRITERIA_TYPE_CAST_SPELL           => 'Cast spell',
        30                                             => 'Battleground objective capture',
        ACHIEVEMENT_CRITERIA_TYPE_HONORABLE_KILL_AT_AREA => 'Honorable kill at area',
        ACHIEVEMENT_CRITERIA_TYPE_WIN_ARENA            => 'Win arena',
        ACHIEVEMENT_CRITERIA_TYPE_PLAY_ARENA           => 'Play arena',
        ACHIEVEMENT_CRITERIA_TYPE_LEARN_SPELL          => 'Learn spell',
        35                                             => 'Honorable kill',
        ACHIEVEMENT_CRITERIA_TYPE_OWN_ITEM             => 'Own item',
        37                                             => 'Win rated arena',
        38                                             => 'Highest team rating',
        39                                             => 'Reach team rating',
        ACHIEVEMENT_CRITERIA_TYPE_LEARN_SKILL_LEVEL    => 'Learn skill level',
        ACHIEVEMENT_CRITERIA_TYPE_USE_ITEM             => 'Use item',
        ACHIEVEMENT_CRITERIA_TYPE_LOOT_ITEM            => 'Loot item',
        ACHIEVEMENT_CRITERIA_TYPE_EXPLORE_AREA         => 'Explore area',
        44                                             => 'Own PvP rank',
        45                                             => 'Buy bank slot',
        ACHIEVEMENT_CRITERIA_TYPE_GAIN_REPUTATION      => 'Gain reputation',
        47                                             => 'Gain exalted reputation',
        48                                             => 'Visit barber shop',
        49                                             => 'Equip epic item',
        50                                             => 'Roll need on loot',
        51                                             => 'Roll greed on loot',
        ACHIEVEMENT_CRITERIA_TYPE_HK_CLASS             => 'Honorable kill class',
        ACHIEVEMENT_CRITERIA_TYPE_HK_RACE              => 'Honorable kill race',
        ACHIEVEMENT_CRITERIA_TYPE_DO_EMOTE             => 'Do emote',
        55                                             => 'Healing done',
        56                                             => 'Get killing blows',
        ACHIEVEMENT_CRITERIA_TYPE_EQUIP_ITEM           => 'Equip item',
        59                                             => 'Money from vendors',
        60                                             => 'Gold spent on talents',
        61                                             => 'Talent resets',
        62                                             => 'Money from quest reward',
        63                                             => 'Gold spent on travelling',
        65                                             => 'Gold spent at barber shop',
        66                                             => 'Gold spent on mail',
        67                                             => 'Loot money',
        ACHIEVEMENT_CRITERIA_TYPE_USE_GAMEOBJECT       => 'Use game object',
        ACHIEVEMENT_CRITERIA_TYPE_BE_SPELL_TARGET2     => 'Be spell target (69)',
        70                                             => 'Special PvP kill',
        ACHIEVEMENT_CRITERIA_TYPE_FISH_IN_GAMEOBJECT   => 'Fish in game object',
        73                                             => 'Defeat boss',
        ACHIEVEMENT_CRITERIA_TYPE_ON_LOGIN             => 'On login',
        ACHIEVEMENT_CRITERIA_TYPE_LEARN_SKILLLINE_SPELLS => 'Learn skill line spells',
        76                                             => 'Win duel',
        77                                             => 'Lose duel',
        ACHIEVEMENT_CRITERIA_TYPE_KILL_CREATURE_TYPE   => 'Kill creature type',
        80                                             => 'Gold earned from auctions',
        82                                             => 'Create auction',
        83                                             => 'Highest auction bid',
        84                                             => 'Won auctions',
        85                                             => 'Highest auction sold',
        86                                             => 'Highest gold owned',
        87                                             => 'Gain revered reputation',
        88                                             => 'Gain honored reputation',
        89                                             => 'Known factions',
        90                                             => 'Loot epic item',
        91                                             => 'Receive epic item',
        93                                             => 'Roll need',
        94                                             => 'Roll greed',
        101                                            => 'Largest hit dealt',
        102                                            => 'Largest hit received',
        103                                            => 'Total damage received',
        104                                            => 'Largest heal cast',
        105                                            => 'Total healing received',
        106                                            => 'Largest heal received',
        107                                            => 'Quests abandoned',
        108                                            => 'Flight paths taken',
        109                                            => 'Loot type',
        ACHIEVEMENT_CRITERIA_TYPE_CAST_SPELL2          => 'Cast spell (target)',
        ACHIEVEMENT_CRITERIA_TYPE_LEARN_SKILL_LINE     => 'Learn skill line (count)',
        113                                            => 'Earn honorable kills',
        114                                            => 'Accepted summonings',
        117                                            => 'Disenchant roll',
        119                                            => 'Use LFD to group with players'
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
