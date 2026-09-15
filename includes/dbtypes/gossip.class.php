<?php

namespace Aowow;

if (!defined('AOWOW_REVISION'))
    die('illegal access');


/*
 * Gossip menus live in the world DB (`gossip_menu`, `gossip_menu_option`, `npc_text`) and are
 * never imported into the aowow DB, so this list queries DB::World() directly - same as
 * SmartAI, Conditions and the loot classes do.
 *
 * Note: the listing is based on `gossip_menu`. Menus that exist only as `gossip_menu_option`
 * rows (i.e. built by a script without any attached npc_text) are not enumerable here, but
 * their detail page works regardless - see GossipBaseResponse.
 */
class GossipList extends DBTypeList
{
    public static int    $type       = Type::GOSSIP;
    public static string $brickFile  = 'gossip';
    public static string $dataTable  = '';                  // world DB type; nothing aowow-side to flag with cuFlags
    public static int    $contribute = CONTRIBUTE_NONE;

    protected array  $dbNames  = ['World'];

    private ?array $sources = null;                         // menuId => [type => ids]; filled on demand

    // note: `id` is a select alias for the listview only - conditions must target `gm.MenuID`, as MySQL
    // does not resolve select aliases in WHERE
    protected string $queryBase = 'SELECT gm.`MenuID` AS ARRAY_KEY, gm.`MenuID` AS "id" FROM gossip_menu gm';
    protected array  $queryOpts = array(
                        'gm'  => [['gmo'],                                  // always count the attached options
                                  'g' => 'gm.`MenuID`',
                                  'o' => 'gm.`MenuID` ASC',
                                  's' => ', GROUP_CONCAT(DISTINCT gm.`TextID`) AS "textIds"'],
                        'gmo' => ['j' => ['gossip_menu_option gmo ON gmo.`MenuID` = gm.`MenuID`', true],
                                  's' => ', COUNT(DISTINCT gmo.`OptionID`) AS "nOptions", GROUP_CONCAT(DISTINCT gmo.`OptionIcon`) AS "optionIcons"']
                    );

    public function __construct(array $conditions = [], array $miscData = [])
    {
        parent::__construct($conditions, $miscData);

        foreach ($this->iterate() as $id => &$_curTpl)
        {
            $_curTpl['textIds']  = $_curTpl['textIds'] ? array_map('intVal', explode(',', $_curTpl['textIds'])) : [];
            $_curTpl['nOptions'] = intVal($_curTpl['nOptions'] ?? 0);
            $_curTpl['name']     = Lang::gossip('menu', [$id]);

            // the option css classes an option's icon resolves to (skipping the plain-chat/no-icon ones); same lookup GossipBaseResponse uses
            $_curTpl['optIcons'] = array_values(array_unique(array_filter(array_map(
                fn($i) => trim(Gossip::iconToCSS((int)$i)),
                $_curTpl['optionIcons'] ? explode(',', $_curTpl['optionIcons']) : []
            ))));
        }
    }

    public static function getName(int $id) : ?LocString
    {
        $n = ['name_loc0'                             => Lang::gossip('menu', [$id]),
              'name_loc'.Lang::getLocale()->value     => Lang::gossip('menu', [$id])];

        return new LocString($n);
    }

    /**
     * every entity that can open the given menus, keyed by menuId
     *
     * @param  array $menuIds  menus to look up
     * @return array           [menuId => [Type::NPC => [ids], Type::OBJECT => [ids]]]
     */
    public static function getSourcesFor(array $menuIds) : array
    {
        $result = [];
        if (!$menuIds = array_filter(array_map('intVal', $menuIds)))
            return $result;

        // creature_template.gossip_menu_id
        foreach (DB::World()->selectAssoc('SELECT `gossip_menu_id` AS "menuId", `entry` FROM creature_template WHERE `gossip_menu_id` IN %in', $menuIds) ?: [] as $r)
            $result[(int)$r['menuId']][Type::NPC][(int)$r['entry']] = (int)$r['entry'];

        // gameobject_template - the gossip field is type dependent
        $goGossip = 'IF(`type` = '.GO_TYPE_QUESTGIVER.', `data3`, IF(`type` = '.GO_TYPE_GOOBER.', `data18`, 0))';
        foreach (DB::World()->selectAssoc('SELECT '.$goGossip.' AS "menuId", `entry` FROM gameobject_template WHERE '.$goGossip.' IN %in', $menuIds) ?: [] as $r)
            $result[(int)$r['menuId']][Type::OBJECT][(int)$r['entry']] = (int)$r['entry'];

        // SMART_ACTION_SEND_GOSSIP_MENU - menus set by script are not in either template
        foreach (DB::World()->selectAssoc(
           'SELECT `action_param1` AS "menuId", `source_type` AS "srcType", `entryorguid` AS "entry"
            FROM   smart_scripts
            WHERE  `action_type` = %i AND `action_param1` IN %in',
            SmartAction::ACTION_SEND_GOSSIP_MENU, $menuIds) ?: [] as $r)
        {
            $type = match ((int)$r['srcType'])
            {
                SmartAI::SRC_TYPE_CREATURE   => Type::NPC,
                SmartAI::SRC_TYPE_OBJECT     => Type::OBJECT,
                default                      => 0
            };

            if (!$type || $r['entry'] <= 0)                 // negative entries are guids; resolving them is not worth a query per row here
                continue;

            $result[(int)$r['menuId']][$type][(int)$r['entry']] = (int)$r['entry'];
        }

        return $result;
    }

    private function sources() : array
    {
        return $this->sources ??= self::getSourcesFor(array_keys($this->templates));
    }

    public function getListviewData() : array
    {
        $data    = [];
        $sources = $this->sources();

        foreach ($this->iterate() as $id => $__)
        {
            $data[$id] = array(
                'id'       => $id,
                'name'     => $this->curTpl['name'],
                'textids'  => $this->curTpl['textIds'],
                'noptions' => $this->curTpl['nOptions']
            );

            if ($this->curTpl['optIcons'])
                $data[$id]['opticons'] = $this->curTpl['optIcons'];

            if ($_ = ($sources[$id][Type::NPC] ?? []))
                $data[$id]['npcs'] = array_values($_);

            if ($_ = ($sources[$id][Type::OBJECT] ?? []))
                $data[$id]['objects'] = array_values($_);
        }

        return $data;
    }

    public function getJSGlobals(int $addMask = GLOBALINFO_ANY) : array
    {
        $data = [];

        foreach ($this->sources() as $bySource)
            foreach ($bySource as $type => $ids)
                foreach ($ids as $id)
                    $data[$type][$id] = $id;

        return $data;
    }

    // the base implementation looks for an `::aowow_*` table in queryBase and would fall over on a world DB type
    public function getRandomId() : int
    {
        return (int)DB::World()->selectCell('SELECT `MenuID` FROM gossip_menu ORDER BY RAND() ASC LIMIT 1');
    }

    public function renderTooltip() : ?string { return null; }
}

class GossipListFilter extends Filter
{
    protected string $type = 'gossip';

    protected static array $genericFilter = array(
        2 => [parent::CR_NUMERIC, 'gm.MenuID',  NUM_CAST_INT], // id
        3 => [parent::CR_NUMERIC, 'gm.TextID',  NUM_CAST_INT]  // textid
    );

    // fieldId => [checkType, checkValue[, fieldIsArray]]
    protected static array $inputFields = array(
        'cr'  => [parent::V_LIST,  [2, 3],                 true ], // criteria ids
        'crs' => [parent::V_RANGE, [1, 4987],              true ], // criteria operators
        'crv' => [parent::V_REGEX, parent::PATTERN_INT,    true ], // criteria values - all criteria are numeric here
        'na'  => [parent::V_NAME,  false,                  false], // name - matched against the option texts
        'ma'  => [parent::V_EQUAL, 1,                      false], // match any / all filter
        'ty'  => [parent::V_RANGE, [0, GOSSIP_OPTION_MAX], true ]  // option types
    );

    protected function createSQLForValues() : array
    {
        $parts = [];
        $_v    = &$this->values;

        // name [str] - menus have no name of their own; match the text of their options instead
        if ($_v['na'])
            if ($_ = $this->buildLikeLookup([['na', 'gmo.OptionText']]))
                $parts[] = $_;

        // option type [list]
        if ($_v['ty'])
            $parts[] = ['gmo.OptionType', $_v['ty']];

        return $parts;
    }
}

?>
