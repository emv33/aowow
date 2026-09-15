<?php

namespace Aowow;

if (!defined('AOWOW_REVISION'))
    die('illegal access');


/*
 * Browser over `points_of_interest`.
 *
 * The Gossip component reads the rows a gossip option points at; this enumerates the whole table,
 * so every named point on a map - inns, mailboxes, flight masters - is browsable on its own.
 */
class PoiBaseResponse extends TemplateResponse
{
    use TrListPage;

    protected  string $template   = 'poi';
    protected  string $pageName   = 'poi';
    protected  int    $requiredUserGroup = U_GROUP_STAFF;

    protected ?int    $activeTab  = parent::TAB_DATABASE;
    protected  array  $breadcrumb = [0, 116];

    protected function generate() : void
    {
        $this->h1 = Util::ucFirst(Lang::poi('title'));


        /**************/
        /* Page Title */
        /**************/

        array_unshift($this->title, $this->h1);


        /****************/
        /* Main Content */
        /****************/

        $this->redButtons[BUTTON_WOWHEAD] = false;

        $this->lvTabs = new Tabs(['parent' => "\$\$WH.ge('tabs-generic')"]);
        $this->lvTabs->addListviewTab(new Listview(['data' => $this->buildListviewData()], 'poi', 'poi'));

        parent::generate();
    }

    private function buildListviewData() : array
    {
        if (!DB::World()->selectCell('SHOW TABLES LIKE %s', 'points_of_interest'))
            return [];

        // 3.3.5 spells the columns differently from newer cores; both are tried, as in Gossip
        $rows = DB::World()->selectAssoc('SELECT `ID`, `PositionX` AS "x", `PositionY` AS "y", `Icon` AS "icon", `Name` AS "name" FROM points_of_interest ORDER BY `ID` ASC');
        if (!$rows)
            $rows = DB::World()->selectAssoc('SELECT `entry` AS "ID", `x`, `y`, `icon`, `icon_name` AS "name" FROM points_of_interest ORDER BY `entry` ASC');

        $data = [];
        foreach ($rows ?: [] as $r)
        {
            $name = trim((string)$r['name']);
            $data[] = array(
                'id'    => (int)$r['ID'],
                'name'  => $name !== '' && $name[0] == '$' ? ' '.$name : $name,
                'x'     => (float)$r['x'],
                'y'     => (float)$r['y'],
                'icon'  => (int)$r['icon']
            );
        }

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
