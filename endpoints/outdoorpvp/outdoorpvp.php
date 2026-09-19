<?php

namespace Aowow;

if (!defined('AOWOW_REVISION'))
    die('illegal access');


/*
 * A single row of `outdoorpvp_template` - see outdoorpvps.php for the table's story.
 *
 * The listing rendered every row unclickable outright. The table itself carries nothing beyond
 * the id, script name and comment, so that is all this shows - but it is now a real, linkable page
 * rather than a dead end.
 */
class OutdoorpvpBaseResponse extends TemplateResponse implements ICache
{
    use TrDetailPage, TrCache;

    protected  int    $cacheType         = CACHE_TYPE_DETAIL_PAGE;
    protected  int    $requiredUserGroup = U_GROUP_STAFF;

    protected  string $template          = 'detail-page-generic';
    protected  string $pageName          = 'outdoorpvp';
    protected ?int    $activeTab         = parent::TAB_DATABASE;
    protected  array  $breadcrumb        = [0, 115];

    public int $type   = -3;    // no Type:: entry - no DBTypeList backs this ad hoc table read; shared sentinel with OutdoorpvpsBaseResponse
    public int $typeId = 0;

    public function __construct(string $id)
    {
        parent::__construct($id);

        $this->typeId = intVal($id);
    }

    protected function generate() : void
    {
        if (!$this->typeId || !DB::World()->selectCell('SHOW TABLES LIKE %s', 'outdoorpvp_template'))
            $this->generateNotFound(Lang::outdoorpvp('title'), Lang::outdoorpvp('notFound'));

        $row = DB::World()->selectRow('SELECT * FROM outdoorpvp_template WHERE `TypeId` = %i', $this->typeId);
        if (!$row)
            $this->generateNotFound(Lang::outdoorpvp('title'), Lang::outdoorpvp('notFound'));

        // the columns were renamed across TC revisions, same trick outdoorpvps.php uses
        $lc         = array_change_key_case($row, CASE_LOWER);
        $scriptName = (string)($lc['scriptname'] ?? '');
        $comment    = (string)($lc['comment'] ?? '');


        /**************/
        /* Page Title */
        /**************/

        $this->h1 = $comment !== '' ? $comment : ($scriptName !== '' ? $scriptName : ('Outdoor PvP #'.$this->typeId));

        array_unshift($this->title, $this->h1, Util::ucFirst(Lang::outdoorpvp('title')));


        /****************/
        /* Main Content */
        /****************/

        $this->redButtons = [BUTTON_LINKS => false, BUTTON_WOWHEAD => false];

        $infobox = [Lang::outdoorpvp('id').Lang::main('colon').$this->typeId];

        if ($scriptName !== '')
            $infobox[] = Lang::outdoorpvp('scriptName').Lang::main('colon').$scriptName;

        if ($comment !== '')
            $infobox[] = Lang::outdoorpvp('comment').Lang::main('colon').$comment;

        $this->infobox = new InfoboxMarkup($infobox, ['allow' => Markup::CLASS_STAFF, 'dbpage' => true], 'infobox-contents0');

        parent::generate();
    }
}

?>
