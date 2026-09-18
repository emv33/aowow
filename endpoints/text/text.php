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
            // every source's composite id already bakes in exactly one owner (or none) - browse()
            // splits a text shared by several npcs/menus into one row per owner rather than
            // listing several on one row - so there is never more than a single reference here
            $tag = match ($row['ownerType'])
            {
                Type::NPC    => 'npc',
                Type::OBJECT => 'object',
                Type::ITEM   => 'item',
                Type::GOSSIP => 'gossip',
                default      => null
            };

            $infobox[] = Lang::gameText('owner').Lang::main('colon').($tag ? '['.$tag.'='.$row['ownerId'].']' : ($row['ownerName'] ?: ('#'.$row['ownerId'])));

            if ($tag)
                $this->extendGlobalIds($row['ownerType'], $row['ownerId']);
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
