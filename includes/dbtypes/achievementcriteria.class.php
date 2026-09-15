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
            $data[$this->id] = array(
                'id'              => $this->id,
                'achievement'     => $this->getField('refAchievementId'),
                'achievementname' => $this->getField('achievementName', true),
                'type'            => $this->getField('type'),
                'name'            => $this->getField('name', true),
                'value1'          => $this->getField('value1'),
                'value2'          => $this->getField('value2'),
                'flags'           => $this->getField('completionFlags')
            );
        }

        return $data;
    }

    public function getJSGlobals(int $addMask = GLOBALINFO_ANY) : array
    {
        return [];
    }

    public function renderTooltip() : ?string { return null; }
}

?>
