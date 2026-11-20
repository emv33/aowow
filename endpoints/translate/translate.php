<?php

namespace Aowow;

if (!defined('AOWOW_REVISION'))
    die('illegal access');


/*
 * Tools page: a preview of the in-game language scramble. Type a line, pick the language it is
 * spoken in and how well the listener knows it, and see the text the way that listener's client
 * renders it. The scramble runs client-side in static/js/translate.js; the server only ships the
 * LanguageWords.dbc word list (g_languageWords) and the state lives in the URL hash, so a
 * translation is shareable.
 */
class TranslateBaseResponse extends TemplateResponse
{
    protected  string $template   = 'translate';
    protected  string $pageName   = 'translate';
    protected ?int    $activeTab  = parent::TAB_TOOLS;
    protected  array  $breadcrumb = [1, 9];

    protected  array  $scripts    = array(
        [SC_JS_FILE, 'js/translate.js']
    );

    public bool  $available   = false;
    public int   $defaultLang = LANG_COMMON;
    public array $words       = [];

    protected function generate() : void
    {
        $this->h1 = Lang::main('translate');

        array_unshift($this->title, $this->h1);

        $this->available = LanguageTranslate::isAvailable();
        if ($this->available)
            $this->words = LanguageTranslate::getWords();

        parent::generate();
    }

    protected function generateMetadata(bool $useArticle = true) : void
    {
        $this->metaTags[] = ['property' => 'og:title', 'content' => $this->h1];
        $this->metaTags[] = ['property' => 'og:type',  'content' => 'website'];

        array_unshift($this->metaTags, ['name' => 'keywords', 'content' => [...Lang::meta('tags', 'generic')]]);

        $this->buildBasicMetadata();
    }
}

?>
