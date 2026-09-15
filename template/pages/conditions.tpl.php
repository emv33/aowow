<?php
    namespace Aowow\Template;

    use \Aowow\Lang;

    /** @var PageTemplate $this */

    $this->brick('header');
    $f = $this->formValues;                                 // shorthand
    $hasQuery = $f['src'] || $f['cnd'] || $f['val'] || $f['ent'];
?>

    <div class="main" id="main">
        <div class="main-precontents" id="main-precontents"></div>
        <div class="main-contents" id="main-contents">

<?php
    $this->brick('announcement');

    $this->brick('pageTemplate', ['fiMenuItem' => [105]]);
?>

            <div id="fi" style="display: <?=($hasQuery ? 'block' : 'none'); ?>;">
                <form action="?conditions" method="get" name="fi">
                    <input type="hidden" name="conditions" value="" />
                    <div class="text">

<?php
    $this->brick('headIcons');

    $this->brick('redButtons');
?>

                        <h1><?=$this->h1; ?></h1>
                    </div>
                    <table>
                        <tr>
                            <td><?=$this->ucFirst(Lang::conditionBrowser('srcType')).Lang::main('colon'); ?></td>
                            <td>
                                <select name="src">
                                    <option value="0"><?=Lang::conditionBrowser('anySource'); ?></option>
<?php foreach ($this->srcTypeList as $id => $name): ?>
                                    <option value="<?=$id; ?>"<?=($f['src'] == $id ? ' selected="selected"' : ''); ?>><?=$this->escHTML($name); ?></option>
<?php endforeach; ?>
                                </select>
                            </td>
                            <td><?=$this->ucFirst(Lang::conditionBrowser('entry')).Lang::main('colon'); ?></td>
                            <td><input type="text" name="ent" size="10" value="<?=($f['ent'] ?: ''); ?>" /></td>
                        </tr>
                        <tr>
                            <td><?=$this->ucFirst(Lang::conditionBrowser('cndType')).Lang::main('colon'); ?></td>
                            <td>
                                <select name="cnd">
                                    <option value="0"><?=Lang::conditionBrowser('anyCondition'); ?></option>
<?php foreach ($this->cndTypeList as $id => $name): ?>
                                    <option value="<?=$id; ?>"<?=($f['cnd'] == $id ? ' selected="selected"' : ''); ?>><?=$this->escHTML($name); ?></option>
<?php endforeach; ?>
                                </select>
                            </td>
                            <td><?=$this->ucFirst(Lang::conditionBrowser('value1')).Lang::main('colon'); ?></td>
                            <td><input type="text" name="val" size="10" value="<?=($f['val'] ?: ''); ?>" /></td>
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
