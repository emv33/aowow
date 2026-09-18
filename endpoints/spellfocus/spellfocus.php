<?php

namespace Aowow;

if (!defined('AOWOW_REVISION'))
    die('illegal access');


/*
 * A single row of `spellfocusobject.dbc` - see spellfocuses.php for the table's story.
 *
 * The listing could only send a click to a pre-filtered objects listing; this gives the focus its
 * own page, with that same filtered set rendered directly as a related tab instead of a redirect.
 */
class SpellfocusBaseResponse extends TemplateResponse
{
    use TrDetailPage;

    protected  int    $requiredUserGroup = U_GROUP_STAFF;

    protected  string $template          = 'detail-page-generic';
    protected  string $pageName          = 'spellfocus';
    protected ?int    $activeTab         = parent::TAB_DATABASE;
    protected  array  $breadcrumb        = [0, 119];

    public int $typeId = 0;

    public function __construct(string $id)
    {
        parent::__construct($id);

        $this->typeId = intVal($id);
    }

    protected function generate() : void
    {
        if (!$this->typeId || !DB::Aowow()->selectCell('SHOW TABLES LIKE %s', 'aowow_spellfocusobject'))
            $this->generateNotFound(Lang::spellfocus('title'), Lang::spellfocus('notFound'));

        $row = DB::Aowow()->selectRow(
           'SELECT `id`, `name_loc0`, `name_loc'.Lang::getLocale()->value.'` FROM ::spellfocusobject WHERE `id` = %i',
            $this->typeId
        );

        if (!$row)
            $this->generateNotFound(Lang::spellfocus('title'), Lang::spellfocus('notFound'));

        $name = Util::localizedString($row, 'name');


        /**************/
        /* Page Title */
        /**************/

        $this->h1 = $name !== '' ? ($name[0] == '$' ? ' '.$name : $name) : ('Spell focus #'.$this->typeId);

        array_unshift($this->title, $this->h1, Util::ucFirst(Lang::spellfocus('title')));


        /****************/
        /* Main Content */
        /****************/

        $this->redButtons = [BUTTON_LINKS => false, BUTTON_WOWHEAD => false];

        $infobox = [Lang::spellfocus('id').Lang::main('colon').$this->typeId];
        $this->infobox = new InfoboxMarkup($infobox, ['allow' => Markup::CLASS_STAFF, 'dbpage' => true], 'infobox-contents0');


        /**************/
        /* Extra Tabs */
        /**************/

        $this->lvTabs = new Tabs(['parent' => "\$\$WH.ge('tabs-generic')"], 'tabsRelated', true);

        $objects = new GameObjectList(array(['spellFocusId', $this->typeId]));
        if (!$objects->error)
        {
            $this->extendGlobalData($objects->getJSGlobals());
            $this->lvTabs->addListviewTab(new Listview(['data' => $objects->getListviewData(), 'name' => Lang::spellfocus('objects')], GameObjectList::$brickFile));
        }

        parent::generate();
    }
}

?>
