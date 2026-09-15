<?php

namespace Aowow;

if (!defined('AOWOW_REVISION'))
    die('illegal access');


/*
 * Browser over the world DB trainer tables.
 *
 * `npc_trainer` (or `creature_default_trainer` + `trainer_spell` on newer cores) is the only
 * place that says what a trainer teaches, at which rank, for which skill and for how much.
 * Nothing enumerated it: a spell page knew it could be trained, but not by whom, and a trainer
 * NPC had no list of its lessons. One row per trainer-spell pair, filtered by `spell` or `npc`.
 */
class TrainersBaseResponse extends TemplateResponse
{
    use TrListPage;

    protected  string $template   = 'trainers';
    protected  string $pageName   = 'trainers';
    protected  int    $requiredUserGroup = U_GROUP_STAFF;

    protected ?int    $activeTab  = parent::TAB_DATABASE;
    protected  array  $breadcrumb = [0, 112];

    protected  array  $expectedGET = array(
        'spell' => ['filter' => FILTER_VALIDATE_INT, 'flags' => FILTER_REQUIRE_SCALAR],
        'npc'   => ['filter' => FILTER_VALIDATE_INT, 'flags' => FILTER_REQUIRE_SCALAR]
    );

    public function __construct(string $rawParam)
    {
        parent::__construct($rawParam);
    }

    protected function generate() : void
    {
        $this->h1 = Util::ucFirst(Lang::trainer('title'));


        /**************/
        /* Page Title */
        /**************/

        array_unshift($this->title, $this->h1);


        /****************/
        /* Main Content */
        /****************/

        $this->redButtons[BUTTON_WOWHEAD] = false;

        $spellFilter = (int)($this->_get['spell'] ?? 0);
        $npcFilter   = (int)($this->_get['npc']   ?? 0);

        $this->lvTabs = new Tabs(['parent' => "\$\$WH.ge('tabs-generic')"]);
        $this->lvTabs->addListviewTab(new Listview(['data' => $this->buildListviewData($spellFilter, $npcFilter)], 'trainer', 'trainer'));

        parent::generate();
    }

    private function buildListviewData(int $spellFilter, int $npcFilter) : array
    {
        $data = [];

        // newer cores: creature_default_trainer (CreatureId, TrainerId) + trainer_spell (TrainerId, ...)
        if (DB::World()->selectCell('SHOW TABLES LIKE %s', 'creature_default_trainer') && DB::World()->selectCell('SHOW TABLES LIKE %s', 'trainer_spell'))
        {
            // the link column is spelled CreatureId on the cores that ship this pair, entry on
            // some forks; both are tried, as the npc teaches tab already does
            foreach (['CreatureId', 'entry'] as $npcCol)
            {
                $query =
                   'SELECT cdt.`'.$npcCol.'` AS "npc", ts.`SpellId` AS "spell", ts.`MoneyCost` AS "cost",
                           ts.`ReqSkillLine` AS "reqSkill", ts.`ReqSkillRank` AS "reqSkillRank", ts.`ReqLevel` AS "reqLevel"
                    FROM   creature_default_trainer cdt JOIN trainer_spell ts ON ts.`TrainerId` = cdt.`TrainerId`';

                $where = [];
                if ($spellFilter)
                    $where[] = 'ts.`SpellId` = '.$spellFilter;
                if ($npcFilter)
                    $where[] = 'cdt.`'.$npcCol.'` = '.$npcFilter;
                if ($where)
                    $query .= ' WHERE '.implode(' AND ', $where);

                $query .= ' ORDER BY cdt.`'.$npcCol.'` ASC, ts.`SpellId` ASC';

                $rows = DB::World()->selectAssoc($query);
                if ($rows !== null)
                    break;
            }

            foreach ($rows ?: [] as $r)
                $data[] = $this->normalizeRow($r);
        }
        // 3.3.5: npc_trainer (entry, spell, spellcost, reqskill, reqskillvalue, reqlevel)
        else if (DB::World()->selectCell('SHOW TABLES LIKE %s', 'npc_trainer'))
        {
            $query =
               'SELECT `entry` AS "npc", `spell`, `spellcost` AS "cost",
                       `reqskill` AS "reqSkill", `reqskillvalue` AS "reqSkillRank", `reqlevel` AS "reqLevel"
                FROM   npc_trainer';

            $where = [];
            if ($spellFilter)
                $where[] = '`spell` = '.$spellFilter;
            if ($npcFilter)
                $where[] = '`entry` = '.$npcFilter;
            if ($where)
                $query .= ' WHERE '.implode(' AND ', $where);

            $query .= ' ORDER BY `entry` ASC, `spell` ASC';

            foreach (DB::World()->selectAssoc($query) ?: [] as $r)
                $data[] = $this->normalizeRow($r);
        }

        $jsg = [];
        foreach ($data as $row)
        {
            if ($row['npc'])
                $jsg[Type::NPC][$row['npc']]     = $row['npc'];
            if ($row['spell'])
                $jsg[Type::SPELL][$row['spell']] = $row['spell'];
            if ($row['reqSkill'])
                $jsg[Type::SKILL][$row['reqSkill']] = $row['reqSkill'];
        }

        $this->extendGlobalData($jsg);

        return $data;
    }

    private function normalizeRow(array $r) : array
    {
        return array(
            'id'           => (int)$r['npc'],
            'npc'          => (int)$r['npc'],
            'spell'        => (int)$r['spell'],
            'cost'         => (int)$r['cost'],
            'reqSkill'     => (int)$r['reqSkill'],
            'reqSkillRank' => (int)$r['reqSkillRank'],
            'reqLevel'     => (int)$r['reqLevel']
        );
    }

    protected function generateMetadata(bool $useArticle = true) : void
    {
        $this->metaTags[] = ['property' => 'og:title', 'content' => $this->h1];
        $this->metaTags[] = ['property' => 'og:type',  'content' => 'website'];

        array_unshift($this->metaTags, ['name' => 'keywords', 'content' => [$this->h1, ...Lang::meta('tags', 'generic')]]);

        $this->buildBasicMetadata(Lang::meta('description', 'genList', [$this->h1]));

        $this->buildLdJson();
    }
}

?>
