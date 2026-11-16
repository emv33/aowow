<?php

namespace Aowow;

if (!defined('AOWOW_REVISION'))
    die('illegal access');


/*
 * Browser over `warden_checks` - see WardenCheck for the table's story.
 *
 * The anti-cheat probes were read nowhere on the site. The whole table is a few hundred rows, so it
 * is filtered by the listview's own search box rather than through a form, as ?teleports does.
 */
class WardenchecksBaseResponse extends TemplateResponse implements ICache
{
    use TrListPage, TrCache;

    protected  int    $type       = -18;   // no Type:: entry - no DBTypeList backs this ad hoc table read; shared sentinel with WardencheckBaseResponse
    protected  int    $cacheType  = CACHE_TYPE_LIST_PAGE;
    protected  string $template   = 'wardenchecks';
    protected  string $pageName   = 'wardenchecks';
    protected  int    $requiredViewGroup = U_GROUP_STAFF;

    protected ?int    $activeTab  = parent::TAB_DATABASE;
    protected  array  $breadcrumb = [0, 123];

    protected function generate() : void
    {
        $this->h1 = Util::ucFirst(Lang::warden('title'));


        /**************/
        /* Page Title */
        /**************/

        array_unshift($this->title, $this->h1);


        /****************/
        /* Main Content */
        /****************/

        $this->redButtons[BUTTON_WOWHEAD] = false;

        $this->lvTabs = new Tabs(['parent' => "\$\$WH.ge('tabs-generic')"]);
        $this->lvTabs->addListviewTab(new Listview(['data' => $this->buildListviewData(), 'id' => 'wardencheck', 'name' => Util::ucFirst(Lang::warden('title'))], 'wardencheck', 'wardencheck'));

        parent::generate();
    }

    private function buildListviewData() : array
    {
        $overrides = WardenCheck::actionOverrides();

        $data = [];
        foreach (WardenCheck::getRows() as $id => $r)
        {
            $row = array(
                'id'       => $id,
                'type'     => $r['type'],
                'typeName' => WardenCheck::typeName($r['type']),
                'category' => WardenCheck::category($r['type']) ?? -1,
                'target'   => WardenCheck::target($r),
                'comment'  => $r['comment']
            );

            if (WardenCheck::skipReason($r) !== null)
                $row['skipped'] = 1;

            // one realm's override reads as the action, several as "realm: action" each
            if ($o = $overrides[$id] ?? [])
                $row['action'] = count($o) == 1
                               ? (Lang::warden('actions', reset($o)) ?? '#'.reset($o))
                               : implode(', ', array_map(fn($realm, $a) => $realm.Lang::main('colon').(Lang::warden('actions', $a) ?? '#'.$a), array_keys($o), $o));

            $data[] = $row;
        }

        return $data;
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
