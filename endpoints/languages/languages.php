<?php

namespace Aowow;

if (!defined('AOWOW_REVISION'))
    die('illegal access');


/*
 * Languages.dbc, seen through the two things the client ties to it: the proficiency spell that
 * grants the language and the SkillLine.dbc row (category 10) that spell awards.
 *
 * creature_text.Language, broadcast_text.LanguageID, npc_text.langN and the language of a
 * readable item all store only the id; this listing is where those ids resolve.
 */
class LanguagesBaseResponse extends TemplateResponse implements ICache
{
    use TrListPage, TrCache;

    protected  int    $cacheType  = CACHE_TYPE_LIST_PAGE;

    protected  string $template   = 'list-page-generic';
    protected  string $pageName   = 'languages';
    protected ?int    $activeTab  = parent::TAB_DATABASE;
    protected  array  $breadcrumb = [0, 110];

    public int $type = 0;                                   // no Type:: entry: the rows are a fixed client table

    // same shape as TrListPage's key, plus the page itself: there is no type or category to tell pages apart
    public function getCacheKeyComponents() : array
    {
        return array(0, -1, User::$groups, $this->pageName);
    }

    protected function generate() : void
    {
        $this->h1 = Util::ucFirst(Lang::skill('cat', 10));  // the skill category the languages make up

        array_unshift($this->title, $this->h1);

        $this->redButtons[BUTTON_WOWHEAD] = false;

        $data    = [];
        $skills  = [];
        $spells  = [];
        foreach (Lang::game('languages') as $id => $name)
        {
            $skill = Language::getSkillId($id);
            $spell = Language::getSpellId($id);

            $data[] = array('id' => $id, 'name' => $name, 'skill' => $skill, 'spell' => $spell);

            if ($skill)
                $skills[$skill] = $skill;

            if ($spell)
                $spells[$spell] = $spell;
        }

        // the listview resolves the skill and spell names through their g_* lookups
        $this->extendGlobalIds(Type::SKILL, ...array_values($skills));
        $this->extendGlobalIds(Type::SPELL, ...array_values($spells));

        $this->lvTabs = new Tabs(['parent' => "\$\$WH.ge('tabs-generic')"]);
        $this->lvTabs->addListviewTab(new Listview(array(
            'data' => $data,
            'id'   => 'languages',
            'name' => $this->h1
        ), 'language', 'language'));

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
