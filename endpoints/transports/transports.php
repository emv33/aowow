<?php

namespace Aowow;

if (!defined('AOWOW_REVISION'))
    die('illegal access');


/*
 * Boats, zeppelins, the Deeprun Tram and every elevator.
 *
 * Transports are gameobjects and keep their own ?object=<id> page, so this is a view over them
 * rather than a new type. What was missing is the route: a GAMEOBJECT_TYPE_MO_TRANSPORT names a
 * TaxiPath in `data0`, and that path resolves - through the taxi tables the setup already writes -
 * to the two nodes it runs between. Nothing linked the two, so a boat's destination appeared nowhere.
 *
 * The world `transports` table lists the instances that are actually spawned; a template without a
 * row there exists but never runs.
 */
class TransportsBaseResponse extends TemplateResponse
{
    use TrListPage;

    protected  int    $requiredUserGroup = U_GROUP_STAFF;

    protected  string $template          = 'transports';
    protected  string $pageName          = 'transports';
    protected ?int    $activeTab         = parent::TAB_DATABASE;
    protected  array  $breadcrumb        = [0, 108];

    protected function generate() : void
    {
        $this->h1 = Util::ucFirst(Lang::game('transports'));


        /**************/
        /* Page Title */
        /**************/

        array_unshift($this->title, $this->h1);


        /****************/
        /* Main Content */
        /****************/

        $this->redButtons[BUTTON_WOWHEAD] = false;

        $this->lvTabs = new Tabs(['parent' => "\$\$WH.ge('tabs-generic')"]);
        $this->lvTabs->addListviewTab(new Listview(['data' => $this->buildListviewData()], 'transport', 'transport'));

        parent::generate();
    }

    private function buildListviewData() : array
    {
        $jsg  = [];
        $data = [];

        foreach (GameObjectList::getTransports() as $entry => $t)
        {
            $row = array(
                'id'      => $entry,
                'name'    => $t['name'] ?: Lang::transport('unnamed', [$entry]),
                'type'    => $t['type'],
                'pathid'  => $t['pathId'],
                'spawned' => $t['spawned'] ? 1 : 0
            );

            if ($t['route'])
            {
                $row['from'] = $t['route']['from'];
                $row['to']   = $t['route']['to'];

                foreach (['fromArea', 'toArea'] as $k)
                    if ($_ = $t['route'][$k])
                        $jsg[Type::ZONE][$_] = $_;

                $row['fromarea'] = $t['route']['fromArea'];
                $row['toarea']   = $t['route']['toArea'];
            }

            $data[] = $row;
        }

        $this->extendGlobalData($jsg);

        return $data;
    }
}

?>
