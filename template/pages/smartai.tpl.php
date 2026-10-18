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

    $this->brick('pageTemplate', ['fiQuery' => $this->fiMenuExtension, 'fiMenuItem' => [106]]);
?>

            <div id="fi" style="display: <?=($this->filter->query ? 'block' : 'none'); ?>;">
                <form action="?filter=smartai" method="post" name="fi" onsubmit="return fi_submit(this)" onreset="return fi_reset(this)">
                    <div class="text">

<?php
    $this->brick('headIcons');

    $this->brick('redButtons');
?>

                        <h1><?=$this->h1; ?></h1>
                    </div>
                    <div class="rightpanel">
                        <div style="float: left"><?=Lang::smartaiBrowser('srcType'); ?></div>
                        <small><a href="javascript:;" onclick="document.forms['fi'].elements['ty[]'].selectedIndex = -1; return false" onmousedown="return false"><?=Lang::main('clear'); ?></a></small>
                        <div class="clear"></div>
                        <select name="ty[]" size="4" multiple="multiple" class="rightselect" style="width: 14em">
<?=$this->makeOptionsList($this->srcTypeList, $f['ty'] ?? [], 28); ?>
                        </select>
                    </div>

                    <div id="fi_criteria" class="padded criteria"><div></div></div><div><a href="javascript:;" id="fi_addcriteria" onclick="fi_addCriterion(this); return false"><?=Lang::main('addFilter'); ?></a></div>

                    <div class="padded2 clear">
                        <div style="float: right"><?=Lang::main('refineSearch'); ?></div>
                        <?=Lang::main('match'); ?><input type="radio" name="ma" value="" id="ma-0" <?=(!$f['ma'] ? 'checked="checked" ' : ''); ?>/><label for="ma-0"><?=Lang::main('allFilter'); ?></label><input type="radio" name="ma" value="1" id="ma-1" <?=($f['ma'] ? 'checked="checked" ' : ''); ?> /><label for="ma-1"><?=Lang::main('oneFilter'); ?></label>
                    </div>

                    <div class="clear"></div>

                    <div class="padded">
                        <input type="submit" value="<?=Lang::main('applyFilter'); ?>" />
                        <input type="reset" value="<?=Lang::main('resetForm'); ?>" />
                    </div>

                </form>
            </div>
            <div class="pad clear"></div>

<script type="text/javascript">//<![CDATA[
    // the event and action types are named server side only; offered as the dropdowns of their criteria
    LANG.fidropdowns.smartevent  = <?=\Aowow\Util::toJSON(array_map(fn($id, $n) => [$id, $id.' – '.$n], array_keys($this->evtTypeList), $this->evtTypeList)); ?>;
    LANG.fidropdowns.smartaction = <?=\Aowow\Util::toJSON(array_map(fn($id, $n) => [$id, $id.' – '.$n], array_keys($this->actTypeList), $this->actTypeList)); ?>;
//]]></script>
<?=$this->renderFilter(12); ?>

<?php $this->brick('lvTabs'); ?>

            <div class="clear"></div>
        </div><!-- main-contents -->
    </div><!-- main -->

<?php $this->brick('footer'); ?>
