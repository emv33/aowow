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
    $this->brick('headIcons');

    $this->brick('redButtons');
?>

                <h1><?=$this->h1; ?></h1>

                <p><?=Lang::dataIntegrity('intro'); ?></p>

<?php foreach ($this->checks as $check): ?>
                <h3><?=$this->escHTML(Lang::dataIntegrity('checks', $check['key'], 'name')); ?>
<?php if ($check['skipped']): ?>
                    <small class="q0"> &ndash; <?=$this->escHTML(Lang::dataIntegrity('skipped', [$check['skipped']])); ?></small>
<?php elseif (!$check['count']): ?>
                    <small class="q2"> &ndash; <?=Lang::dataIntegrity('clean'); ?></small>
<?php else: ?>
                    <small class="q10"> &ndash; <?=$this->escHTML(Lang::dataIntegrity('found', [$check['count']])); ?></small>
<?php endif; ?>
                </h3>
                <div class="pad2"></div>
                <small class="q0"><?=$this->escHTML(Lang::dataIntegrity('checks', $check['key'], 'hint')); ?></small>

<?php if ($check['samples']): ?>
                <ul>
<?php foreach ($check['samples'] as [$urlPart, $id, $caption]): ?>
                    <li><?php if ($urlPart): ?><a href="?<?=$urlPart; ?>=<?=$id; ?>"><?=$id; ?></a><?php else: ?><?=$id; ?><?php endif; ?> <span class="q0">&ndash; <?=$this->escHTML($caption); ?></span></li>
<?php endforeach; ?>
<?php if ($check['more']): ?>
                    <li class="q0"><?=$this->escHTML(Lang::dataIntegrity('andMore', [$check['count'] - count($check['samples'])])); ?></li>
<?php endif; ?>
                </ul>
<?php endif; ?>
                <div class="pad2"></div>
<?php endforeach; ?>

            </div>

            <div class="clear"></div>
        </div><!-- main-contents -->
    </div><!-- main -->

<?php $this->brick('footer'); ?>
