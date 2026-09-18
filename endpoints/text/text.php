<?php

namespace Aowow;

if (!defined('AOWOW_REVISION'))
    die('illegal access');


/*
 * A single row of GameText::browse() - see texts.php for the table's story.
 *
 * The listing sent a click to the owning entity's own page where one existed, and back to the
 * listing itself, filtered by source, where it did not - a line nothing speaks has no other page
 * to fall back to. This gives every line, owned or not, its own permanent address; the owner (when
 * there is one) is a link on it, rather than the whole destination.
 */
class TextBaseResponse extends TemplateResponse
{
    use TrDetailPage;

    protected  int    $requiredUserGroup = U_GROUP_STAFF;

    protected  string $template          = 'detail-page-generic';
    protected  string $pageName          = 'text';
    protected ?int    $activeTab         = parent::TAB_DATABASE;
    protected  array  $breadcrumb        = [0, 109];

    public ?Book $book = null;

    private string $rowId = '';

    public function __construct(string $id)
    {
        parent::__construct($id);

        $this->rowId = $id;
    }

    protected function generate() : void
    {
        $row = $this->rowId !== '' ? GameText::getOne($this->rowId) : null;
        if (!$row)
            $this->generateNotFound(Lang::gameText('title'), Lang::gameText('notFound'));

        $srcLabel = Lang::gameText('sources', $row['src']) ?: ('#'.$row['src']);


        /**************/
        /* Page Title */
        /**************/

        $this->h1 = Util::ucFirst($srcLabel).' #'.$row['entry'];

        array_unshift($this->title, $this->h1, Util::ucFirst(Lang::gameText('title')));


        /****************/
        /* Main Content */
        /****************/

        $this->redButtons = [BUTTON_LINKS => false, BUTTON_WOWHEAD => false];

        $infobox = [Util::ucFirst(Lang::gameText('source')).Lang::main('colon').$srcLabel];

        if ($row['ownerType'] && $row['ownerId'] > 0)
        {
            // only the types Markup's own tag set can render as a link; a gossip menu (the only
            // other owner GameText ever names) has no tag of its own - it gets a real link below
            // instead, as a one-row related tab, the same way every other cross-reference here does
            $tag = match ($row['ownerType'])
            {
                Type::NPC    => 'npc',
                Type::OBJECT => 'object',
                Type::ITEM   => 'item',
                default      => null
            };

            $infobox[] = Lang::gameText('owner').Lang::main('colon').($tag ? '['.$tag.'='.$row['ownerId'].']' : ($row['ownerName'] ?: ('#'.$row['ownerId'])));

            $this->extendGlobalIds($row['ownerType'], $row['ownerId']);
        }

        $this->infobox = new InfoboxMarkup($infobox, ['allow' => Markup::CLASS_STAFF, 'dbpage' => true], 'infobox-contents0');


        /**************/
        /* Extra Tabs */
        /**************/

        if ($row['ownerType'] == Type::GOSSIP)
        {
            $menu = new GossipList(array(['id', $row['ownerId']]));
            if (!$menu->error)
            {
                $this->extendGlobalData($menu->getJSGlobals());

                $this->lvTabs = new Tabs(['parent' => "\$\$WH.ge('tabs-generic')"], 'tabsRelated', true);
                $this->lvTabs->addListviewTab(new Listview(['data' => $menu->getListviewData(), 'name' => Lang::gameText('owner')], GossipList::$brickFile));
            }
        }

        // a page_text row is BBCode's own [tag]-based Markup only in name - the format it actually
        // carries is the html subset UIText::format(..., Lang::FMT_HTML) understands, the same
        // pipeline Game::getBook() feeds the item/object page's book widget with; every other
        // source renders as the plain, already-resolved excerpt browse() built
        if ($row['src'] == GameText::SRC_PAGE_TEXT)
            $this->book = new Book([$row['raw']], 'book-generic');
        else
            $this->extraText = new Markup($row['text'], ['allow' => Markup::CLASS_STAFF], 'text-contents0');

        parent::generate();
    }
}

?>
