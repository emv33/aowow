<?php

namespace Aowow;

if (!defined('AOWOW_REVISION'))
    die('illegal access');


/*
 * World DB lint.
 *
 * A set of cheap consistency checks over the world DB - rows that point at something which is not
 * there, and entities that cannot work as configured. Nothing here is derivable from any single
 * detail page, which is the point: these are the questions you can only ask of the whole DB.
 *
 * Every check stays inside DB::World() on purpose. Whether an item or a creature exists is a world
 * DB question, and answering it against the imported aowow tables instead would report anything
 * excluded from the import as broken.
 */
class DataintegrityBaseResponse extends TemplateResponse
{
    use TrListPage;

    protected  int    $requiredUserGroup = U_GROUP_STAFF;

    protected  string $template          = 'data-integrity';
    protected  string $pageName          = 'data-integrity';
    protected ?int    $activeTab         = parent::TAB_STAFF;
    protected  array  $breadcrumb        = [4];             // staff tab

    public const int SAMPLE_LIMIT = 25;

    public array $checks = [];                              // for the template

    protected function generate() : void
    {
        $this->h1 = Util::ucFirst(Lang::dataIntegrity('title'));


        /**************/
        /* Page Title */
        /**************/

        array_unshift($this->title, $this->h1);


        /****************/
        /* Main Content */
        /****************/

        $this->redButtons[BUTTON_WOWHEAD] = false;

        foreach ($this->runChecks() as $check)
            $this->checks[] = $check;

        parent::generate();
    }

    /**
     * every check as [label, hint, count, samples]
     * a sample is [urlPart, id, caption]; urlPart empty means there is no page to link to
     */
    private function runChecks() : \Generator
    {
        foreach (self::CHECKS as $key => [$tables, $sql, $urlPart])
        {
            foreach ($tables as $t)
            {
                if (!self::hasTable($t))
                {
                    yield ['key' => $key, 'skipped' => $t, 'count' => 0, 'samples' => []];
                    continue 2;
                }
            }

            $rows = DB::World()->selectAssoc($sql.' LIMIT %i', self::SAMPLE_LIMIT);
            if ($rows === null)                             // query failed; a column this core spells differently
            {
                yield ['key' => $key, 'skipped' => $tables[0], 'count' => 0, 'samples' => []];
                continue;
            }

            // the samples are capped, so the headline number needs its own count of the whole result
            $total = (int)DB::World()->selectCell('SELECT COUNT(1) FROM ('.$sql.') x');

            $samples = [];
            foreach ($rows as $r)
                $samples[] = [$urlPart, (int)$r['id'], (string)($r['caption'] ?? $r['id'])];

            yield ['key' => $key, 'skipped' => '', 'count' => $total, 'more' => $total > count($samples), 'samples' => $samples];
        }
    }

    private static function hasTable(string $tbl) : bool
    {
        static $known = [];

        return $known[$tbl] ??= (bool)DB::World()->selectCell('SHOW TABLES LIKE %s', $tbl);
    }

    // key => [required tables, query yielding `id` and `caption`, url part for the link]
    private const array CHECKS = array(
        'vendorItem' => [['npc_vendor', 'item_template'],
           'SELECT   nv.`entry` AS "id", CONCAT("item #", nv.`item`) AS "caption"
            FROM     npc_vendor nv
            LEFT JOIN item_template it ON it.`entry` = nv.`item`
            WHERE    nv.`item` > 0 AND it.`entry` IS NULL
            GROUP BY nv.`entry`, nv.`item`', 'npc'],

        'lootItem' => [['creature_loot_template', 'item_template'],
           'SELECT   clt.`Entry` AS "id", CONCAT("item #", clt.`Item`) AS "caption"
            FROM     creature_loot_template clt
            LEFT JOIN item_template it ON it.`entry` = clt.`Item`
            WHERE    clt.`Reference` = 0 AND clt.`Item` > 0 AND it.`entry` IS NULL
            GROUP BY clt.`Entry`, clt.`Item`', ''],

        'creatureSpawn' => [['creature', 'creature_template'],
           'SELECT   c.`id` AS "id", CONCAT("guid ", MIN(c.`guid`)) AS "caption"
            FROM     creature c
            LEFT JOIN creature_template ct ON ct.`entry` = c.`id`
            WHERE    ct.`entry` IS NULL
            GROUP BY c.`id`', ''],

        'objectSpawn' => [['gameobject', 'gameobject_template'],
           'SELECT   g.`id` AS "id", CONCAT("guid ", MIN(g.`guid`)) AS "caption"
            FROM     gameobject g
            LEFT JOIN gameobject_template gt ON gt.`entry` = g.`id`
            WHERE    gt.`entry` IS NULL
            GROUP BY g.`id`', ''],

        'npcGossip' => [['creature_template', 'gossip_menu'],
           'SELECT   ct.`entry` AS "id", CONCAT("menu #", ct.`gossip_menu_id`) AS "caption"
            FROM     creature_template ct
            LEFT JOIN gossip_menu gm        ON gm.`MenuID`  = ct.`gossip_menu_id`
            LEFT JOIN gossip_menu_option go ON go.`MenuID`  = ct.`gossip_menu_id`
            WHERE    ct.`gossip_menu_id` > 0 AND gm.`MenuID` IS NULL AND go.`MenuID` IS NULL
            GROUP BY ct.`entry`', 'npc'],

        'gossipChain' => [['gossip_menu_option'],
           'SELECT   go.`MenuID` AS "id", CONCAT("opens menu #", go.`ActionMenuID`) AS "caption"
            FROM     gossip_menu_option go
            LEFT JOIN gossip_menu gm         ON gm.`MenuID` = go.`ActionMenuID`
            LEFT JOIN gossip_menu_option go2 ON go2.`MenuID` = go.`ActionMenuID`
            WHERE    go.`ActionMenuID` > 0 AND gm.`MenuID` IS NULL AND go2.`MenuID` IS NULL
            GROUP BY go.`MenuID`, go.`ActionMenuID`', 'gossip'],

        'questEnder' => [['quest_template', 'creature_questender', 'gameobject_questender'],
           'SELECT   qt.`ID` AS "id", qt.`LogTitle` AS "caption"
            FROM     quest_template qt
            LEFT JOIN creature_questender cqe   ON cqe.`quest` = qt.`ID`
            LEFT JOIN gameobject_questender gqe ON gqe.`quest` = qt.`ID`
            WHERE    cqe.`quest` IS NULL AND gqe.`quest` IS NULL
            GROUP BY qt.`ID`', 'quest'],

        'questRelationOrphan' => [['creature_questender', 'quest_template'],
           'SELECT   cqe.`id` AS "id", CONCAT("quest #", cqe.`quest`) AS "caption"
            FROM     creature_questender cqe
            LEFT JOIN quest_template qt ON qt.`ID` = cqe.`quest`
            WHERE    qt.`ID` IS NULL
            GROUP BY cqe.`id`, cqe.`quest`', 'npc'],

        'smartActionList' => [['smart_scripts'],
           'SELECT   s.`entryorguid` AS "id", CONCAT("action list #", s.`action_param1`) AS "caption"
            FROM     smart_scripts s
            LEFT JOIN smart_scripts al ON al.`entryorguid` = s.`action_param1` AND al.`source_type` = 9
            WHERE    s.`action_type` = 80 AND s.`action_param1` > 0 AND al.`entryorguid` IS NULL
            GROUP BY s.`entryorguid`, s.`action_param1`', ''],

        'encounterCredit' => [['instance_encounters', 'creature_template'],
           'SELECT   ie.`entry` AS "id", CONCAT("creature #", ie.`creditEntry`) AS "caption"
            FROM     instance_encounters ie
            LEFT JOIN creature_template ct ON ct.`entry` = ie.`creditEntry`
            WHERE    ie.`creditType` = 0 AND ie.`creditEntry` > 0 AND ct.`entry` IS NULL
            GROUP BY ie.`entry`', 'encounter'],

        'trainerOrphan' => [['trainer_spell', 'trainer'],
           'SELECT   ts.`TrainerId` AS "id", CONCAT("spell #", ts.`SpellId`) AS "caption"
            FROM     trainer_spell ts
            LEFT JOIN trainer t ON t.`Id` = ts.`TrainerId`
            WHERE    t.`Id` IS NULL
            GROUP BY ts.`TrainerId`', ''],

        'conditionGossip' => [['conditions', 'gossip_menu_option'],
           'SELECT   c.`SourceGroup` AS "id", CONCAT("option #", c.`SourceEntry`) AS "caption"
            FROM     conditions c
            LEFT JOIN gossip_menu_option go ON go.`MenuID` = c.`SourceGroup` AND go.`OptionID` = c.`SourceEntry`
            WHERE    c.`SourceTypeOrReferenceId` = 15 AND go.`MenuID` IS NULL
            GROUP BY c.`SourceGroup`, c.`SourceEntry`', 'gossip']
    );
}

?>
