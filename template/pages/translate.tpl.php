<?php
    namespace Aowow\Template;

    use \Aowow\Lang;
    use \Aowow\Util;

    /** @var PageTemplate $this */

    $this->brick('header');
?>

    <div class="main" id="main">
        <div class="main-precontents" id="main-precontents"></div>
        <div class="main-contents" id="main-contents">

<?php
    $this->brick('announcement');

    $this->brick('pageTemplate');
?>

            <div class="text">
                <h1><?=$this->h1; ?></h1>

<?php if (!$this->available): ?>
                <p class="q0"><?=Lang::main('translateUnavailable'); ?></p>
<?php endif; ?>

                <table>
                    <tr>
                        <td class="padded"><?=Lang::main('translateText').Lang::main('colon'); ?></td>
                        <td class="padded"><textarea id="tr-text" rows="4" cols="60"></textarea></td>
                    </tr>
                    <tr>
                        <td class="padded"><?=Lang::main('translateLang').Lang::main('colon'); ?></td>
                        <td class="padded">
                            <select id="tr-lang">
<?=$this->makeOptionsList(Lang::game('languages'), $this->defaultLang, 28); ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <td class="padded"><?=Lang::main('translateSkill').Lang::main('colon'); ?></td>
                        <td class="padded"><input type="text" id="tr-skill" maxlength="3" size="4" value="0" /> <small class="q0"><?=Lang::main('translateSkillHint'); ?></small></td>
                    </tr>
                </table>

                <h2><?=Lang::main('translateResult'); ?></h2>
                <div id="tr-result" style="white-space: pre-wrap"></div>

                <script type="text/javascript">//<![CDATA[
                    var g_languageWords = <?=Util::toJSON($this->words, JSON_HEX_TAG | JSON_UNESCAPED_UNICODE); ?>;
                    tr_init();
                //]]></script>
            </div>

            <div class="clear"></div>
        </div><!-- main-contents -->
    </div><!-- main -->

<?php $this->brick('footer'); ?>
