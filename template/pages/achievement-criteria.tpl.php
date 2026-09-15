<?php
    namespace Aowow\Template;

    use \Aowow\Lang;

    /** @var PageTemplate $this */

    $this->brick('header');
    $f = $this->formValues;                                 // shorthand
?>

    <div class="main" id="main">
        <div class="main-precontents" id="main-precontents"></div>
        <div class="main-contents" id="main-contents">

<?php
    $this->brick('announcement');

    $this->brick('pageTemplate', ['fiMenuItem' => [118]]);
?>

            <div id="fi" style="display: <?=($this->pageTemplate['filter'] ? 'block' : 'none'); ?>;">
                <form action="?achievement-criteria" method="get" name="fi">
                    <input type="hidden" name="achievement-criteria" value="" />
                    <div class="text">

<?php
    $this->brick('headIcons');

    $this->brick('redButtons');
?>

                        <h1><?=$this->h1; ?></h1>
                    </div>
                    <table>
                        <tr>
                            <td><?=$this->ucFirst(Lang::achievementCriteriaBrowser('id')).Lang::main('colon'); ?></td>
                            <td><input type="text" name="id" size="10" value="<?=($f['id'] ?: ''); ?>" /></td>
                            <td><?=$this->ucFirst(Lang::achievementCriteriaBrowser('achievement')).Lang::main('colon'); ?></td>
                            <td><input type="text" name="ac" size="20" value="<?=$this->escHTML($f['ac']); ?>" /></td>
                        </tr>
                        <tr>
                            <td><?=$this->ucFirst(Lang::achievementCriteriaBrowser('type')).Lang::main('colon'); ?></td>
                            <td><input type="text" name="ty" size="10" value="<?=($f['ty'] ?: ''); ?>" /></td>
                            <td><?=$this->ucFirst(Lang::achievementCriteriaBrowser('flags')).Lang::main('colon'); ?></td>
                            <td><input type="text" name="fl" size="10" value="<?=($f['fl'] ?: ''); ?>" /></td>
                        </tr>
                        <tr>
                            <td><?=$this->ucFirst(Lang::main('name')).Lang::main('colon'); ?></td>
                            <td colspan="3"><input type="text" name="na" size="30" value="<?=$this->escHTML($f['na']); ?>" /></td>
                        </tr>
                    </table>

                    <div class="padded">
                        <input type="submit" value="<?=Lang::main('applyFilter'); ?>" />
                    </div>

                </form>
            </div>
            <div class="pad clear"></div>

<?php $this->brick('lvTabs'); ?>

            <div class="clear"></div>
        </div><!-- main-contents -->
    </div><!-- main -->

<?php $this->brick('footer'); ?>
