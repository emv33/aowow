<?php

namespace Aowow;

if (!defined('AOWOW_REVISION'))
    die('illegal access');


/*
 * A single named location out of `game_tele` - see teleports.php for the table's story.
 *
 * The listing pinned every row on the general maps page; this gives each one its own address,
 * with the same single-point mapper pin an areatrigger or a waypoint gets.
 */
class TeleportBaseResponse extends TemplateResponse
{
    use TrDetailPage;

    protected  int    $requiredUserGroup = U_GROUP_STAFF;

    protected  string $template          = 'detail-page-generic';
    protected  string $pageName          = 'teleport';
    protected ?int    $activeTab         = parent::TAB_DATABASE;
    protected  array  $breadcrumb        = [0, 111];

    public int $typeId = 0;

    public function __construct(string $id)
    {
        parent::__construct($id);

        $this->typeId = intVal($id);
    }

    protected function generate() : void
    {
        if (!$this->typeId || !DB::World()->selectCell('SHOW TABLES LIKE %s', 'game_tele'))
            $this->generateNotFound(Lang::teleport('title'), Lang::teleport('notFound'));

        $row = DB::World()->selectRow(
           'SELECT `id`, `name`, `map`, `position_x`, `position_y` FROM game_tele WHERE `id` = %i',
            $this->typeId
        );

        if (!$row)
            $this->generateNotFound(Lang::teleport('title'), Lang::teleport('notFound'));

        $name = trim((string)$row['name']);


        /**************/
        /* Page Title */
        /**************/

        // Util::toJSON() emits any string starting with a $ as raw JavaScript, same guard the listing uses
        $this->h1 = $name !== '' ? ($name[0] == '$' ? ' '.$name : $name) : ('Teleport #'.$this->typeId);

        array_unshift($this->title, $this->h1, Util::ucFirst(Lang::teleport('title')));


        /****************/
        /* Main Content */
        /****************/

        $this->redButtons = [BUTTON_LINKS => false, BUTTON_WOWHEAD => false];

        $mapId = (int)$row['map'];
        $infobox = [Lang::teleport('id').Lang::main('colon').$this->typeId];

        // the same axis-swapping conversion the spawn importer and the listing use
        $pt = WorldPosition::toZonePos($mapId, (float)$row['position_x'], (float)$row['position_y']);
        if ($pt)
        {
            $p      = $pt[0];
            $areaId = (int)$p['areaId'];

            $this->extendGlobalIds(Type::ZONE, $areaId);
            $infobox[] = Lang::teleport('zone').Lang::main('colon').'[zone='.$areaId.']';
            $infobox[] = Lang::teleport('position').Lang::main('colon').sprintf('%.1f, %.1f', $p['posX'], $p['posY']);

            $this->addDataLoader('zones');
            $this->map = array(
                ['parent' => 'mapper-generic'],
                [$areaId => [(int)$p['floor'] => ['coords' => [[$p['posX'], $p['posY'], []]], 'count' => 1]]],
                null,
                null
            );
        }
        else
            $infobox[] = Lang::teleport('map', [$mapId]);

        $this->infobox = new InfoboxMarkup($infobox, ['allow' => Markup::CLASS_STAFF, 'dbpage' => true], 'infobox-contents0');

        parent::generate();
    }
}

?>
