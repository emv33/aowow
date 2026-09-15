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

    $this->brick('pageTemplate', ['fiMenuItem' => [109]]);
?>

            <div id="fi" style="display: block;">
                <form action="?texts" method="get" name="fi">
                    <input type="hidden" name="texts" value="" />
                    <div class="text">

<?php
    $this->brick('headIcons');

    $this->brick('redButtons');
?>

                        <h1><?=$this->h1; ?></h1>
                    </div>
                    <table>
                        <tr>
                            <td><?=$this->ucFirst(Lang::gameText('term')).Lang::main('colon'); ?></td>
                            <td><input type="text" name="q" size="40" value="<?=$this->escHTML($f['q']); ?>" /></td>
                            <td><?=$this->ucFirst(Lang::gameText('source')).Lang::main('colon'); ?></td>
                            <td>
                                <select name="src">
                                    <option value="0"><?=Lang::gameText('anySource'); ?></option>
<?php foreach ($this->srcList as $id => $name): ?>
                                    <option value="<?=$id; ?>"<?=($f['src'] == $id ? ' selected="selected"' : ''); ?>><?=$this->escHTML($name); ?></option>
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

<?php if ($this->notice): ?>
            <div class="pad"></div>
            <div class="text"><?=$this->escHTML($this->notice); ?></div>
<?php endif; ?>

            <div class="pad clear"></div>

<?php $this->brick('lvTabs'); ?>

            <div class="clear"></div>
        </div><!-- main-contents -->
    </div><!-- main -->

<?php $this->brick('footer'); ?>
