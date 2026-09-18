<?php

namespace Aowow;

if (!defined('AOWOW_REVISION'))
    die('illegal access');


/*
 * Browser over `spellfocusobject.dbc`.
 *
 * A gameobject of type SPELLFOCUS (anvils, forges, altars, Runeforges, ...) names the focus it
 * provides only by its numeric id (`spellFocusId`, generated from `gameobject_template.data0` -
 * see objects.ss.php), and spells that require standing near one point at that same id through
 * SpellCastingRequirements' `RequiresSpellFocus`. Neither side ever resolved the id to the name
 * the DBC already carries.
 */
class SpellfocusesBaseResponse extends TemplateResponse
{
    use TrListPage;

    protected  int    $requiredUserGroup = U_GROUP_STAFF;

    protected  string $template          = 'spellfocuses';
    protected  string $pageName          = 'spellfocuses';
    protected ?int    $activeTab         = parent::TAB_DATABASE;
    protected  array  $breadcrumb        = [0, 119];

    protected function generate() : void
    {
        $this->h1 = Util::ucFirst(Lang::spellfocus('title'));


        /**************/
        /* Page Title */
        /**************/

        array_unshift($this->title, $this->h1);


        /****************/
        /* Main Content */
        /****************/

        $this->redButtons[BUTTON_WOWHEAD] = false;

        $this->lvTabs = new Tabs(['parent' => "\$\$WH.ge('tabs-generic')"]);
        $this->lvTabs->addListviewTab(new Listview(['data' => $this->buildListviewData()], 'spellfocus', 'spellfocus'));

        parent::generate();
    }

    private function buildListviewData() : array
    {
        if (!DB::Aowow()->selectCell('SHOW TABLES LIKE %s', 'aowow_spellfocusobject'))
            return [];

        $rows = DB::Aowow()->selectAssoc('SELECT `id`, `name_loc0`, `name_loc'.Lang::getLocale()->value.'` FROM ::spellfocusobject ORDER BY `id` ASC') ?: [];

        // read straight off the site's own objects table rather than trusting a fixed list of
        // ids - GameObjectListFilter's cr=50 used to gate this off a hand-typed enum that never
        // matched what a given core's gameobject_template actually has
        $usedIds = array_flip(DB::Aowow()->selectCol('SELECT DISTINCT `spellFocusId` FROM ::objects WHERE `spellFocusId` != 0') ?: []);

        $data = [];
        foreach ($rows as $r)
        {
            $name = Util::localizedString($r, 'name');
            $id   = (int)$r['id'];

            $row = array(
                'id'   => $id,
                'name' => $name !== '' && $name[0] == '$' ? ' '.$name : $name
            );

            if (isset($usedIds[$id]))
                $row['objlink'] = '?objects&filter=cr=50;crs=3;crv='.$id;

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
