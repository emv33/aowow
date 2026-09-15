<?php
    namespace Aowow\Template;

    use \Aowow\Lang;

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

<?php
    $this->brick('redButtons');

    if ($this->h1):
        echo '                <h1>'.$this->h1.'</h1>';
    endif;

    $this->brick('mapper');

    $this->brick('markup', ['markup' => $this->article]);

    $this->brick('markup', ['markup' => $this->extraText]);

    echo $this->extraHTML ?? '';
?>

                <form class="search-form" method="get" action="?achievement-criteria">
                    <input type="hidden" name="achievement-criteria" value="">
                    <label>ID <input type="text" name="id" value="<?= htmlspecialchars((string)($this->formValues['id'] ?? '')) ?>" size="8"></label>
                    <label>Achievement <input type="text" name="ac" value="<?= htmlspecialchars((string)($this->formValues['ac'] ?? '')) ?>" size="8"></label>
                    <label>Type <input type="text" name="ty" value="<?= htmlspecialchars((string)($this->formValues['ty'] ?? '')) ?>" size="6"></label>
                    <label>Flags <input type="text" name="fl" value="<?= htmlspecialchars((string)($this->formValues['fl'] ?? '')) ?>" size="6"></label>
                    <label>Name <input type="text" name="na" value="<?= htmlspecialchars((string)($this->formValues['na'] ?? '')) ?>"></label>
                    <button type="submit"><?= Lang::main('search') ?></button>
                </form>

<?php
    if ($this->tabsTitle):
        echo '                <h2 class="clear">'.$this->tabsTitle.'</h2>';
    endif;
?>

            </div>

<?php
    if ($this->lvTabs):
        $this->brick('lvTabs');
?>

        <div class="clear"></div>

<?php
    endif;
?>

        </div><!-- main-contents -->
    </div><!-- main -->

<?php $this->brick('footer'); ?>
