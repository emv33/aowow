<?php

namespace Aowow;

if (!defined('AOWOW_REVISION'))
    die('illegal access');


/*
 * A single Languages.dbc id - the page the language labels on gossip text, ?text= and readable
 * items point at. See languages.php for the table's story; there is no Type:: entry, so the row
 * is a fixed client table and the related tabs are built here rather than looked up.
 */
class LanguageBaseResponse extends TemplateResponse implements ICache
{
    use TrDetailPage, TrCache;

    protected  int    $cacheType  = CACHE_TYPE_DETAIL_PAGE;

    protected  string $template   = 'detail-page-generic';
    protected  string $pageName   = 'language';
    protected ?int    $activeTab  = parent::TAB_DATABASE;
    protected  array  $breadcrumb = [0, 110];

    public int $type   = 0;                                 // no Type:: entry
    public int $typeId = 0;

    private int $spellId = 0;
    private int $skillId = 0;

    public function __construct(string $id)
    {
        parent::__construct($id);

        $this->typeId = intVal($id);
    }

    // no Type:: entry, so the key is typed on the page itself (same shape as ?text)
    public function getCacheKeyComponents() : array
    {
        return array(-9, $this->typeId, User::$groups, '');
    }

    protected function generate() : void
    {
        $name = Lang::exist('game', 'languages', $this->typeId);
        if (!$name)
            $this->generateNotFound(Lang::main('language'), Lang::main('pageNotFound', [Util::lower(Lang::main('language'))]));

        $this->spellId = Language::getSpellId($this->typeId);
        $this->skillId = Language::getSkillId($this->typeId);

        $this->h1 = $name;


        /**************/
        /* Page Title */
        /**************/

        array_unshift($this->title, $this->h1, Util::ucFirst(Lang::skill('cat', 10)));


        /****************/
        /* Main Content */
        /****************/

        $this->redButtons = array(BUTTON_LINKS => false, BUTTON_WOWHEAD => false);


        /***********/
        /* Infobox */
        /***********/

        $infobox = array();

        // the skill line whose category 10 makes it a language
        if ($this->skillId)
        {
            $infobox[] = Util::ucFirst(Lang::game('skill')).Lang::main('colon').'[skill='.$this->skillId.']';
            $this->extendGlobalIds(Type::SKILL, $this->skillId);
        }

        // the proficiency spell that grants it
        if ($this->spellId)
        {
            $infobox[] = Util::ucFirst(Lang::game('spell')).Lang::main('colon').'[spell='.$this->spellId.']';
            $this->extendGlobalIds(Type::SPELL, $this->spellId);
        }

        $infobox[] = 'ID'.Lang::main('colon').$this->typeId;

        $this->infobox = new InfoboxMarkup($infobox, ['allow' => Markup::CLASS_STAFF, 'dbpage' => true], 'infobox-contents0');


        /**************/
        /* Extra Tabs */
        /**************/

        $this->lvTabs = new Tabs(['parent' => "\$\$WH.ge('tabs-generic')"], 'tabsRelated', true);

        // the scramble words this language replaces text with (LanguageWords.dbc), as an inline summary table
        $wordList = LanguageTranslate::getWords()[$this->typeId] ?? [];
        if ($wordList)
        {
            ksort($wordList);                               // order by number of letters
            $letterCount = Lang::main('letterCount');       // ["%d letter", "%d letters"]
            $rows = '';
            foreach ($wordList as $len => $words)
            {
                sort($words);
                $rows .= '[tr][td][b]'.sprintf($letterCount[$len == 1 ? 0 : 1], $len).'[/b][/td][td]'.implode(', ', $words).'[/td][/tr]';
            }

            $this->extraText = new Markup('[h3 class=clear]'.Lang::main('words').'[/h3][table class=grid]'.$rows.'[/table]', ['dbpage' => true, 'allow' => Markup::CLASS_ADMIN], 'text-generic');
        }

        // tab: readable items written in this language
        $items = new ItemList(array(Listview::DEFAULT_SIZE, ['languageId', $this->typeId]));
        if (!$items->error)
        {
            $this->extendGlobalData($items->getJSGlobals());
            $this->lvTabs->addListviewTab(new Listview(array(
                'data' => $items->getListviewData(),
                'id'   => 'items',
                'name' => '$LANG.tab_items'
            ), ItemList::$brickFile));
        }

        // tab: the lines the game speaks in this language (world db text, staff gated like ?texts)
        if (User::canSee(U_GROUP_STAFF))
        {
            [$tData, $tJsg] = GameText::getListviewData(GameText::browse(['lang' => $this->typeId]));
            if ($tData)
            {
                $this->extendGlobalData($tJsg);
                $this->lvTabs->addListviewTab(new Listview(array(
                    'data'       => $tData,
                    'id'         => 'texts',
                    'name'       => Util::ucFirst(Lang::gameText('title')),
                    'hiddenCols' => ['language']            // every row is this language
                ), 'text', 'text'));
            }
        }

        // tab: every spell that references this language, the three cases effectMVLanguage() on the
        // spell page resolves - the Learn Language effect the proficiency spell uses, plus the two
        // language auras (Force Language, Comprehend Language)
        $langAuras = [SPELL_AURA_MOD_LANGUAGE, SPELL_AURA_COMPREHEND_LANGUAGE];
        $spellIds = $this->spellId ? [$this->spellId] : [];
        $spellIds = array_merge($spellIds, DB::Aowow()->selectCol(
           'SELECT `id` FROM ::spell
            WHERE (`effect1AuraId` IN %in AND `effect1MiscValue` = %i)
               OR (`effect2AuraId` IN %in AND `effect2MiscValue` = %i)
               OR (`effect3AuraId` IN %in AND `effect3MiscValue` = %i)
               OR (`effect1Id` = %i AND `effect1MiscValue` = %i)
               OR (`effect2Id` = %i AND `effect2MiscValue` = %i)
               OR (`effect3Id` = %i AND `effect3MiscValue` = %i)',
            $langAuras, $this->typeId,
            $langAuras, $this->typeId,
            $langAuras, $this->typeId,
            SPELL_EFFECT_LANGUAGE, $this->typeId,
            SPELL_EFFECT_LANGUAGE, $this->typeId,
            SPELL_EFFECT_LANGUAGE, $this->typeId
        ) ?: []);
        $spellIds = array_values(array_unique(array_map('intVal', $spellIds)));

        if ($spellIds)
        {
            $spells = new SpellList(array(Listview::DEFAULT_SIZE, ['id', $spellIds]));
            if (!$spells->error)
            {
                $this->extendGlobalData($spells->getJSGlobals());
                $this->lvTabs->addListviewTab(new Listview(array(
                    'data' => $spells->getListviewData(),
                    'id'   => 'spells',
                    'name' => '$LANG.tab_spells'
                ), SpellList::$brickFile));
            }
        }

        parent::generate();
    }

    protected function generateMetadata(bool $useArticle = true) : void
    {
        $this->metaTags[] = ['property' => 'og:title', 'content' => $this->h1];
        $this->metaTags[] = ['property' => 'og:type',  'content' => 'article'];

        array_unshift($this->metaTags, ['name' => 'keywords', 'content' => [$this->h1, ...Lang::meta('tags', 'generic')]]);

        $this->buildBasicMetadata(Lang::meta('description', 'genPage', [$this->h1, Util::ucFirst(Lang::skill('cat', 10))]));

        $this->buildLdJson();
    }
}

?>
