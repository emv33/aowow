<?php

namespace Aowow;

if (!defined('AOWOW_REVISION'))
    die('illegal access');


/*
 * Languages.dbc, seen through the two things the client ties to it: the proficiency spell that
 * grants the language and the SkillLine.dbc row (category 10) that spell awards.
 *
 * creature_text.Language, broadcast_text.LanguageID, npc_text.langN and the language of a
 * readable item all store only the id; there is no page for it anywhere else.
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

        $rows = '';
        foreach (Lang::game('languages') as $id => $name)
        {
            $skill = Language::getSkillId($id);
            $spell = Language::getSpellId($id);

            $rows .= '[tr][td]'.$name.'[/td]'
                   . '[td]'.($skill ? '[skill='.$skill.']' : '[i][small class=q0]-[/small][/i]').'[/td]'
                   . '[td]'.($spell ? '[spell='.$spell.']' : '[i][small class=q0]-[/small][/i]').'[/td][/tr]';
        }

        $table = '[table class=grid]'
               . '[tr][td header]'.Lang::main('language').'[/td]'
               . '[td header]'.Util::ucFirst(Lang::game('skill')).'[/td]'
               . '[td header]'.Util::ucFirst(Lang::game('spell')).'[/td][/tr]'
               . $rows
               . '[/table]';

        $this->extraText = new Markup($table, ['allow' => Markup::CLASS_ADMIN], 'text-generic');
        $this->extendGlobalData($this->extraText->getJSGlobals());

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
