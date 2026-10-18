<?php
    namespace Aowow\Template;

    use \Aowow\Lang;

    /** @var PageTemplate $this */

    $this->brick('header');
    $f = $this->filter->values;                             // shorthand
?>

    <div class="main" id="main">
        <div class="main-precontents" id="main-precontents"></div>
        <div class="main-contents" id="main-contents">

<?php
    $this->brick('announcement');

    $this->brick('pageTemplate', ['fiQuery' => $this->fiMenuExtension, 'fiMenuItem' => [109]]);
?>

            <div id="fi" style="display: <?=($this->filter->query ? 'block' : 'none'); ?>;">
                <form action="?filter=texts" method="post" name="fi" onsubmit="return fi_submit(this)" onreset="return fi_reset(this)">
                    <div class="text">

<?php
    $this->brick('headIcons');

    $this->brick('redButtons');
?>

                        <h1><?=$this->h1; ?></h1>
                    </div>
                    <div class="rightpanel">
                        <div style="float: left"><?=Lang::gameText('source'); ?></div>
                        <small><a href="javascript:;" onclick="document.forms['fi'].elements['ty[]'].selectedIndex = -1; return false" onmousedown="return false"><?=Lang::main('clear'); ?></a></small>
                        <div class="clear"></div>
                        <select name="ty[]" size="5" multiple="multiple" class="rightselect" style="width: 14em">
<?=$this->makeOptionsList(Lang::gameText('sources'), $f['ty'] ?? [], 28); ?>
                        </select>
                    </div>
                    <table>
                        <tr>
                            <td><?=$this->ucFirst(Lang::gameText('term')).Lang::main('colon'); ?></td>
                            <td colspan="2">
                                <table><tr>
                                    <td>&nbsp;<input type="text" name="na" size="30" <?=($f['na'] ? 'value="'.$this->escHTML($f['na']).'" ' : ''); ?>/></td>
                                </tr></table>
                            </td>
                        </tr>
                    </table>

                    <div class="clear"></div>

                    <div class="padded">
                        <input type="submit" value="<?=Lang::main('applyFilter'); ?>" />
                        <input type="reset" value="<?=Lang::main('resetForm'); ?>" />
                    </div>

                </form>
            </div>
            <div class="pad clear"></div>

<?=$this->renderFilter(12); ?>

<?php if ($this->notice): ?>
            <div class="pad"></div>
            <div class="text"><?=$this->escHTML($this->notice); ?></div>
<?php endif; ?>

<?php $this->brick('lvTabs'); ?>

            <div class="clear"></div>
        </div><!-- main-contents -->
    </div><!-- main -->

<?php $this->brick('footer'); ?>
