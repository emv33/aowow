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

        $infobox = [Lang::gameText('source').Lang::main('colon').$srcLabel];

        if ($row['ownerType'] && $row['ownerId'] > 0)
        {
            // only the types Markup's own tag set can render as a link; a gossip menu (the only
            // other owner GameText ever names) prints its label plainly instead
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
        else
            $infobox[] = Lang::gameText('noOwner');

        $this->infobox = new InfoboxMarkup($infobox, ['allow' => Markup::CLASS_STAFF, 'dbpage' => true], 'infobox-contents0');

        // the full line; the listing itself only ever showed a 400-character excerpt of this
        $this->extraText = new Markup($row['text'], ['allow' => Markup::CLASS_STAFF], 'text-contents0');

        parent::generate();
    }
}

?>
