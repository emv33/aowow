<?php

namespace Aowow;

if (!defined('AOWOW_REVISION'))
    die('illegal access');


/*
 * The named locations in `game_tele`.
 *
 * The table exists so a GM can type ".tele shadowfang" - which makes it the one place in the world
 * DB where a point in the world carries a human name. Nothing read it, so a gazetteer of the game's
 * landmarks, already written down, was unreachable.
 *
 * Every row is resolved to the zone it falls in, so a name answers "where is this" rather than
 * printing three floats.
 */
class TeleportsBaseResponse extends TemplateResponse
{
    use TrListPage;

    protected  int    $requiredUserGroup = U_GROUP_STAFF;

    protected  string $template          = 'teleports';
    protected  string $pageName          = 'teleports';
    protected ?int    $activeTab         = parent::TAB_DATABASE;
    protected  array  $breadcrumb        = [0, 111];

    protected function generate() : void
    {
        $this->h1 = Util::ucFirst(Lang::teleport('title'));


        /**************/
        /* Page Title */
        /**************/

        array_unshift($this->title, $this->h1);


        /****************/
        /* Main Content */
        /****************/

        $this->redButtons[BUTTON_WOWHEAD] = false;

        $this->lvTabs = new Tabs(['parent' => "\$\$WH.ge('tabs-generic')"]);
        $this->lvTabs->addListviewTab(new Listview(['data' => $this->buildListviewData()], 'teleport', 'teleport'));

        parent::generate();
    }

    private function buildListviewData() : array
    {
        if (!DB::World()->selectCell('SHOW TABLES LIKE %s', 'game_tele'))
            return [];

        $rows = DB::World()->selectAssoc('SELECT `id`, `name`, `map`, `position_x`, `position_y`, `position_z` FROM game_tele ORDER BY `name` ASC') ?: [];

        $jsg  = [];
        $data = [];
        foreach ($rows as $r)
        {
            $row = array(
                'id'   => (int)$r['id'],
                'name' => (string)$r['name'],
                'map'  => (int)$r['map'],
                'posx' => 0,
                'posy' => 0
            );

            // the same conversion the spawn importer uses; it swaps the axes, so it is not done by hand
            if ($pt = WorldPosition::toZonePos((int)$r['map'], (float)$r['position_x'], (float)$r['position_y']))
            {
                $row['zone'] = (int)$pt[0]['areaId'];
                $row['posx'] = $pt[0]['posX'];
                $row['posy'] = $pt[0]['posY'];

                $jsg[Type::ZONE][$row['zone']] = $row['zone'];
            }

            $data[] = $row;
        }

        $this->extendGlobalData($jsg);

        return $data;
    }
}

?>
