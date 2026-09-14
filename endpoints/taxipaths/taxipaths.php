<?php

namespace Aowow;

if (!defined('AOWOW_REVISION'))
    die('illegal access');


class TaxipathsBaseResponse extends TemplateResponse
{
    use TrListPage;

    protected  int    $requiredUserGroup = U_GROUP_STAFF;

    protected  string $template          = 'taxipaths';
    protected  string $pageName          = 'taxipaths';
    protected ?int    $activeTab         = parent::TAB_DATABASE;
    protected  array  $breadcrumb        = [0, 110];

    protected function generate() : void
    {
        $this->h1 = Util::ucFirst(Lang::game('taxipaths'));


        /**************/
        /* Page Title */
        /**************/

        array_unshift($this->title, $this->h1);


        /****************/
        /* Main Content */
        /****************/

        $this->redButtons[BUTTON_WOWHEAD] = false;

        $jsg = [];
        $this->lvTabs = new Tabs(['parent' => "\$\$WH.ge('tabs-generic')"]);
        $this->lvTabs->addListviewTab(new Listview(['data' => TaxiPath::getListviewData([], $jsg)], 'taxipath', 'taxipath'));

        $this->extendGlobalData($jsg);

        parent::generate();
    }

}

?>
