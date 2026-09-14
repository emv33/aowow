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

    $this->brick('pageTemplate', ['fiMenuItem' => [106]]);
?>

            <div id="fi" style="display: block;">
                <form action="?smartai" method="get" name="fi">
                    <input type="hidden" name="smartai" value="" />
                    <div class="text">

<?php
    $this->brick('headIcons');

    $this->brick('redButtons');
?>

                        <h1><?=$this->h1; ?></h1>
                    </div>
                    <table>
                        <tr>
                            <td><?=$this->ucFirst(Lang::smartaiBrowser('srcType')).Lang::main('colon'); ?></td>
                            <td>
                                <select name="src">
                                    <option value=""><?=Lang::smartaiBrowser('anySource'); ?></option>
<?php foreach ($this->srcTypeList as $id => $name): ?>
                                    <option value="<?=$id; ?>"<?=($f['src'] !== null && $f['src'] == $id ? ' selected="selected"' : ''); ?>><?=$this->escHTML($name); ?></option>
<?php endforeach; ?>
                                </select>
                            </td>
                            <td><?=$this->ucFirst(Lang::smartaiBrowser('entry')).Lang::main('colon'); ?></td>
                            <td><input type="text" name="ent" size="10" value="<?=($f['ent'] ?: ''); ?>" /></td>
                        </tr>
                        <tr>
                            <td><?=$this->ucFirst(Lang::smartaiBrowser('eventType')).Lang::main('colon'); ?></td>
                            <td>
                                <select name="evt">
                                    <option value=""><?=Lang::smartaiBrowser('anyEvent'); ?></option>
<?php foreach ($this->evtTypeList as $id => $name): ?>
                                    <option value="<?=$id; ?>"<?=($f['evt'] !== null && $f['evt'] == $id ? ' selected="selected"' : ''); ?>><?=$id.' &ndash; '.$this->escHTML($name); ?></option>
<?php endforeach; ?>
                                </select>
                            </td>
                            <td><?=$this->ucFirst(Lang::smartaiBrowser('refId')).Lang::main('colon'); ?></td>
                            <td><input type="text" name="ref" size="10" value="<?=($f['ref'] ?: ''); ?>" /></td>
                        </tr>
                        <tr>
                            <td><?=$this->ucFirst(Lang::smartaiBrowser('actionType')).Lang::main('colon'); ?></td>
                            <td colspan="3">
                                <select name="act">
                                    <option value=""><?=Lang::smartaiBrowser('anyAction'); ?></option>
<?php foreach ($this->actTypeList as $id => $name): ?>
                                    <option value="<?=$id; ?>"<?=($f['act'] !== null && $f['act'] == $id ? ' selected="selected"' : ''); ?>><?=$id.' &ndash; '.$this->escHTML($name); ?></option>
<?php endforeach; ?>
                                </select>
                            </td>
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
