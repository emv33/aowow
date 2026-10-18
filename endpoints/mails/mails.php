<?php

namespace Aowow;

if (!defined('AOWOW_REVISION'))
    die('illegal access');


class MailsBaseResponse extends TemplateResponse implements ICache
{
    use TrListPage, TrCache;

    protected  int    $type       = Type::MAIL;
    protected  int    $cacheType  = CACHE_TYPE_LIST_PAGE;

    protected  string $template   = 'list-page-generic';
    protected  string $pageName   = 'mails';
    protected ?int    $activeTab  = parent::TAB_DATABASE;
    protected  array  $breadcrumb = [0, 103];

    public function __construct(string $rawParam)
    {
        $this->getCategoryFromUrl($rawParam);

        parent::__construct($rawParam);
    }

    protected function generate() : void
    {
        $this->h1 = Util::ucFirst(Lang::game('mails'));


        /**************/
        /* Page Title */
        /**************/

        array_unshift($this->title, $this->h1);


        /****************/
        /* Main Content */
        /****************/

        $mails = new MailList();
        $data  = $mails->error ? [] : $mails->getListviewData();

        $this->extendGlobalData($mails->getJSGlobals());

        $extraCols = [];

        // aowow - custom start: `mail_level_reward` - the mails the server sends for reaching a
        // level, which the detail page could only ever resolve backwards; shown as a column of the
        // one mail listing (upstream: no level column)
        if (DB::World()->selectCell('SHOW TABLES LIKE %s', 'mail_level_reward'))
        {
            $rewardRows = DB::World()->selectAssoc('SELECT `level`, `mailTemplateId` FROM mail_level_reward ORDER BY `level` ASC') ?: [];
            foreach ($rewardRows as $r)
            {
                $id = (int)$r['mailTemplateId'];

                if (!isset($data[$id]))                     // template without a DBC entry
                    $data[$id] = array(
                        'id'          => $id,
                        'subject'     => Lang::mail('untitled', [$id]),
                        'body'        => '',
                        'attachments' => []
                    );

                if (!isset($data[$id]['level']))            // lowest level wins, rows are ordered
                    $data[$id]['level'] = (int)$r['level'];
            }

            if ($rewardRows)
                $extraCols[] = "\$Listview.funcBox.createSimpleCol('level', LANG.level, '8%', 'level')";
        }
        // aowow - custom end

        $this->lvTabs = new Tabs(['parent' => "\$\$WH.ge('tabs-generic')"]);

        $this->lvTabs->addListviewTab(new Listview(['data' => $data, 'extraCols' => $extraCols ?: null], MailList::$brickFile, 'mail'));

        parent::generate();
    }

    protected function generateMetadata(bool $useArticle = true) : void
    {
        $this->metaTags[] = ['property' => 'og:title', 'content' => $this->h1];
        $this->metaTags[] = ['property' => 'og:type',  'content' => 'website'];

        array_unshift($this->metaTags, ['name' => 'keywords', 'content' => [$this->h1, ...Lang::meta('tags', 'generic')]]);

        $this->buildBasicMetadata(Lang::meta('description', 'genList', [$this->h1]));

        $this->buildLdJson();
    }
}

?>
