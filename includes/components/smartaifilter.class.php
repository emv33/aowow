<?php

namespace Aowow;

if (!defined('AOWOW_REVISION'))
    die('illegal access');


/*
 * Filter of the ?smartai browser.
 *
 * `smart_scripts` has no DBTypeList, so the usual getConditions() round trip is not used: the validated
 * values are handed to SmartAI::browse() through getBrowseOpts() and become SQL there. The class is what
 * gives the page the same filter form (source type list, criteria, match all / any) as the rest.
 */
class SmartAIFilter extends Filter
{
    public const int CR_ENTRY  = 2;
    public const int CR_REF    = 3;
    public const int CR_EVENT  = 4;
    public const int CR_ACTION = 5;

    protected string $type = 'smartai';

    protected static array $enums = [];                     // [CR_EVENT / CR_ACTION => type ids], see __construct

    protected static array $genericFilter = array(
        self::CR_ENTRY  => [parent::CR_NUMERIC, 'entryorguid', NUM_CAST_INT       ],
        self::CR_REF    => [parent::CR_NUMERIC, 'ref',         NUM_CAST_INT       ],
        self::CR_EVENT  => [parent::CR_ENUM,    'event_type',  false,         true],
        self::CR_ACTION => [parent::CR_ENUM,    'action_type', false,         true]
    );

    // fieldId => [checkType, checkValue[, fieldIsArray]]
    protected static array $inputFields = array(
        'cr'  => [parent::V_LIST,  [2, 3, 4, 5],        true ], // criteria ids
        'crs' => [parent::V_RANGE, [0, 999],            true ], // criteria operators / event or action type
        'crv' => [parent::V_REGEX, parent::PATTERN_CRV, true ], // criteria values - numeric; a negative entry is a spawn guid
        'ma'  => [parent::V_EQUAL, 1,                   false], // match any / all filter
        'ty'  => [parent::V_RANGE, [0, 9],              true ]  // source types
    );

    public function __construct(string|array $data, array $opts = [])
    {
        static::$enums[self::CR_EVENT]  = array_keys(Lang::smartAI('events'));
        static::$enums[self::CR_ACTION] = array_keys(Lang::smartAI('actions'));

        parent::__construct($data, $opts);
    }

    /** the validated values, in the terms SmartAI::browse() works with */
    public function getBrowseOpts() : array
    {
        $criteria = [];
        foreach ($this->values['cr'] ?? [] as $i => $cr)
        {
            $crs = (int)$this->values['crs'][$i];
            $crv = (int)$this->values['crv'][$i];

            if ($cr == self::CR_EVENT || $cr == self::CR_ACTION)   // the "operator" holds the picked type
                $criteria[] = ['kind' => $cr, 'op' => '=', 'value' => $crs];
            else if ($cr == self::CR_REF)                   // an id, other comparisons make no sense
                $criteria[] = ['kind' => $cr, 'op' => '=', 'value' => $crv];
            else if ($this->int2Op($crs))
                $criteria[] = ['kind' => $cr, 'op' => $crs, 'value' => $crv];
        }

        return array(
            'srcType'  => array_map('intVal', $this->values['ty'] ?? []),
            'criteria' => $criteria,
            'matchAny' => (bool)$this->values['ma']
        );
    }

    protected function createSQLForValues() : array
    {
        return [];                                          // see getBrowseOpts()
    }
}

?>
