<?php

namespace Aowow;

if (!defined('AOWOW_REVISION'))
    die('illegal access');


/*
 * Browser over `outdoorpvp_template`.
 *
 * The table drives the world PvP objectives (Halaa, Silithus, Hellfire towers) and was read
 * nowhere: which template id exists, which script runs it and what the core's own comment says.
 */
class OutdoorpvpBaseResponse extends TemplateResponse
{
    use TrListPage;

    protected  string $template   = 'outdoorpvp';
    protected  string $pageName   = 'outdoorpvp';
    protected  int    $requiredUserGroup = U_GROUP_STAFF;

    protected ?int    $activeTab  = parent::TAB_DATABASE;
    protected  array  $breadcrumb = [0, 115];

    protected function generate() : void
    {
        $this->h1 = Util::ucFirst(Lang::outdoorpvp('title'));


        /**************/
        /* Page Title */
        /**************/

        array_unshift($this->title, $this->h1);


        /****************/
        /* Main Content */
        /****************/

        $this->redButtons[BUTTON_WOWHEAD] = false;

        $this->lvTabs = new Tabs(['parent' => "\$\$WH.ge('tabs-generic')"]);
        $this->lvTabs->addListviewTab(new Listview(['data' => $this->buildListviewData()], 'outdoorpvp', 'outdoorpvp'));

        parent::generate();
    }

    private function buildListviewData() : array
    {
        if (!DB::World()->selectCell('SHOW TABLES LIKE %s', 'outdoorpvp_template'))
            return [];

        $rows = DB::World()->selectAssoc('SELECT * FROM outdoorpvp_template ORDER BY `TypeId` ASC') ?: [];

        $data = [];
        foreach ($rows as $r)
        {
            // the columns were renamed across TC revisions, so the row is read whole and its
            // keys are matched lowercased - same trick the battleground_template block uses
            $lc = array_change_key_case($r, CASE_LOWER);

            $data[] = array(
                'typeId'     => (int)($lc['typeid'] ?? 0),
                'scriptName' => (string)($lc['scriptname'] ?? ''),
                'comment'    => (string)($lc['comment'] ?? '')
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
