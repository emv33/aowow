<?php

namespace Aowow;

if (!defined('AOWOW_REVISION'))
    die('illegal access');


/*
 * Filter of the ?texts browser.
 *
 * The game text lives in several world DB tables with no DBTypeList of their own, so the validated values
 * are handed to GameText::browse() through getBrowseOpts(). There is nothing to pick criteria from, the
 * form is the text term and the kinds of text to search.
 */
class TextsFilter extends Filter
{
    protected string $type = 'texts';

    protected static array $genericFilter = [];

    // fieldId => [checkType, checkValue[, fieldIsArray]]
    protected static array $inputFields = array(
        'na' => [parent::V_REGEX, parent::PATTERN_NAME,           false], // text contains - one phrase, not tokens, as GameText::browse() takes it
        'ty' => [parent::V_RANGE, [1, 5], true ]  // kinds of text
    );

    /** the validated values, in the terms GameText::browse() works with */
    public function getBrowseOpts() : array
    {
        return array(
            'query' => (string)($this->values['na'] ?? ''),
            'src'   => array_map('intVal', $this->values['ty'] ?? [])
        );
    }

    protected function createSQLForValues() : array
    {
        return [];                                          // see getBrowseOpts()
    }
}

?>
