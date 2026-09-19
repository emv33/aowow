<?php

namespace Aowow;

if (!defined('AOWOW_REVISION'))
    die('illegal access');


/*
 * Browser over the `player_factionchange_*` tables.
 *
 * When a character pays for a faction change, these tables decide what the equivalent of each
 * spell, item, quest, reputation and title is on the other side. Nothing enumerated them; the
 * faction page only ever resolved its own pendant.
 */
class FactionchangeBaseResponse extends TemplateResponse implements ICache
{
    use TrListPage, TrCache;

    protected  int    $type       = -12;   // no single Type:: - a browser over several unrelated player_factionchange_* tables
    protected  int    $cacheType  = CACHE_TYPE_LIST_PAGE;
    protected  string $template   = 'factionchange';
    protected  string $pageName   = 'factionchange';
    protected  int    $requiredUserGroup = U_GROUP_STAFF;

    protected ?int    $activeTab  = parent::TAB_DATABASE;
    protected  array  $breadcrumb = [0, 117];

    protected function generate() : void
    {
        $this->h1 = Util::ucFirst(Lang::factionchange('title'));


        /**************/
        /* Page Title */
        /**************/

        array_unshift($this->title, $this->h1);


        /****************/
        /* Main Content */
        /****************/

        $this->redButtons[BUTTON_WOWHEAD] = false;

        $this->lvTabs = new Tabs(['parent' => "\$\$WH.ge('tabs-generic')"]);
        $this->lvTabs->addListviewTab(new Listview(['data' => $this->buildListviewData()], 'factionchange', 'factionchange'));

        parent::generate();
    }

    private function buildListviewData() : array
    {
        $data = [];
        $jsg  = [];

        // [table, type, alliance col, horde col]
        $tables = [
            ['player_factionchange_spells',       Type::SPELL,   'alliance_id', 'horde_id'],
            ['player_factionchange_items',        Type::ITEM,    'alliance_id', 'horde_id'],
            ['player_factionchange_quests',       Type::QUEST,   'alliance_id', 'horde_id'],
            ['player_factionchange_reputations',  Type::FACTION, 'alliance_id', 'horde_id'],
            ['player_factionchange_titles',       Type::TITLE,   'alliance_id', 'horde_id']
        ];

        foreach ($tables as [$table, $type, $aCol, $hCol])
        {
            if (!DB::World()->selectCell('SHOW TABLES LIKE %s', $table))
                continue;

            $rows = DB::World()->selectAssoc('SELECT `'.$aCol.'` AS "a", `'.$hCol.'` AS "h" FROM '.$table.' ORDER BY `'.$aCol.'` ASC') ?: [];
            foreach ($rows as $r)
            {
                $a = (int)$r['a'];
                $h = (int)$r['h'];

                $data[] = array('type' => $type, 'alliance' => $a, 'horde' => $h, 'id' => $a ?: $h);

                if ($a)
                    $jsg[$type][$a] = $a;
                if ($h)
                    $jsg[$type][$h] = $h;
            }
        }

        $this->extendGlobalData($jsg);

        return $data;
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
