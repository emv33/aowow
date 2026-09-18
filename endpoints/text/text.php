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
            // Markup's own tag set covers npc/object/item; a gossip menu has no tag of its own, and
            // a raw <a href> in its place doesn't work either - Markup.js escapes the whole string
            // first and only unescapes its own recognized [tag] syntax, so hand-written HTML comes
            // out as literal, dead text
            $tag = match ($row['ownerType'])
            {
                Type::NPC    => 'npc',
                Type::OBJECT => 'object',
                Type::ITEM   => 'item',
                default      => null
            };

            if ($tag)
            {
                $infobox[] = Lang::gameText('owner').Lang::main('colon').'['.$tag.'='.$row['ownerId'].']';
                $this->extendGlobalIds($row['ownerType'], $row['ownerId']);
            }
            else
            {
                $infobox[] = Lang::gameText('owner').Lang::main('colon').($row['ownerName'] ?: ('#'.$row['ownerId']));

                // a gossip menu is the one owner type left without a link - GossipList enumerates
                // off gossip_menu, and per its own doc comment a menu built only from
                // gossip_menu_option rows has no such row even though its detail page resolves
                // fine regardless; a hand-built one-row tab still links there through the same
                // getItemLink() every other listview already uses, no bbcode or raw html involved
                //
                // unlike npc/object/quest/zone, 'gossip' isn't one of the templates baked into the
                // static Listview.templates bundle - it only exists as template/listviews/gossip.tpl,
                // which needs the 3rd (addIn) constructor arg to get inlined onto the page at all;
                // without it Listview.templates.gossip is undefined and the tab silently never
                // registers, which is what every existing use of that .tpl file already does
                if ($row['ownerType'] == Type::GOSSIP)
                {
                    $this->lvTabs = new Tabs(['parent' => "\$\$WH.ge('tabs-generic')"], 'tabsRelated', true);
                    $this->lvTabs->addListviewTab(new Listview(array(
                        'data' => [['id' => $row['ownerId'], 'name' => $row['ownerName'], 'noptions' => 0]],
                        'name' => Lang::gameText('owner')
                    ), GossipList::$brickFile, GossipList::$brickFile));
                }
            }
        }

        $this->infobox = new InfoboxMarkup($infobox, ['allow' => Markup::CLASS_STAFF, 'dbpage' => true], 'infobox-contents0');

        // a page_text row is BBCode's own [tag]-based Markup only in name - the format it actually
        // carries is the html subset Game::getBook() already knows how to read (same call the
        // item/object page makes for the identical row); every other source renders as the plain,
        // already-resolved excerpt browse() built
        if ($row['src'] == GameText::SRC_PAGE_TEXT)
        {
            if ($this->book = Game::getBook($row['entry']))
                $this->addScript(
                    [SC_JS_FILE,  'js/Book.js'],
                    [SC_CSS_FILE, 'css/Book.css']
                );
        }
        else
        {
            // browse()'s excerpt() collapses line breaks to spaces for the listing preview - the
            // full body instead formats the untouched original the same way Gossip's own page
            // renders this exact text: UIText::format(..., Lang::FMT_MARKUP), wrapped the same
            $body = '[span class=gossip-text]'.UIText::format($row['raw'], Lang::FMT_MARKUP).'[/span]';
            $this->extraText = new Markup($body, ['allow' => Markup::CLASS_STAFF], 'text-contents0');
        }

        parent::generate();
    }
}

?>
