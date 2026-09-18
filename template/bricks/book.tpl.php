<?php
    namespace Aowow\Template;

    use \Aowow\Lang;

    /** @var PageTemplate $this */

if ($this->book): ?>

<?php if (empty($noClear)): ?>
                <div class="clear"></div>
<?php endif; ?>
                <h3><?=Lang::item('content'); ?></h3>

                <div id="book-generic"></div>
                <script>//<![CDATA[
                    <?=$this->book; ?>
                //]]></script>

<?php endif; ?>
