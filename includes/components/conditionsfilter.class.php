<?php

namespace Aowow;

if (!defined('AOWOW_REVISION'))
    die('illegal access');


/*
 * Filter of the ?conditions browser.
 *
 * `conditions` has no DBTypeList, so the usual getConditions() round trip is not used: the validated
 * values are handed to Conditions::browse() through getBrowseOpts() and become SQL there. The class is
 * what gives the page the same filter form (source type list, criteria, match all / any) as the rest.
 */
class ConditionsFilter extends Filter
{
    public const int CR_SOURCE_GROUP       = 2;
    public const int CR_SOURCE_ENTRY       = 3;
    public const int CR_SOURCE_GROUP_ENTRY = 4;             // either of the two
    public const int CR_CONDITION_TYPE     = 5;
    public const int CR_CONDITION_VALUE    = 6;

    protected string $type = 'condition';

    protected static array $enums = [];                     // [CR_CONDITION_TYPE => condition type ids], see __construct

    protected static array $genericFilter = array(
        self::CR_SOURCE_GROUP       => [parent::CR_NUMERIC, 'SourceGroup',   NUM_CAST_INT       ],
        self::CR_SOURCE_ENTRY       => [parent::CR_NUMERIC, 'SourceEntry',   NUM_CAST_INT       ],
        self::CR_SOURCE_GROUP_ENTRY => [parent::CR_NUMERIC, 'SourceGroup',   NUM_CAST_INT       ],
        self::CR_CONDITION_TYPE     => [parent::CR_ENUM,    'ConditionType', false,         true],
        self::CR_CONDITION_VALUE    => [parent::CR_NUMERIC, 'ConditionValue1', NUM_CAST_INT     ]
    );

    // fieldId => [checkType, checkValue[, fieldIsArray]]
    protected static array $inputFields = array(
        'cr'  => [parent::V_LIST,  [2, 3, 4, 5, 6],      true ], // criteria ids
        'crs' => [parent::V_RANGE, [1, 60],              true ], // criteria operators / condition type
        'crv' => [parent::V_REGEX, parent::PATTERN_INT,  true ], // criteria values - all criteria are numeric here
        'ma'  => [parent::V_EQUAL, 1,                    false], // match any / all filter
        'ty'  => [parent::V_RANGE, [1, 30],              true ]  // source types
    );

    public function __construct(string|array $data, array $opts = [])
    {
        static::$enums[self::CR_CONDITION_TYPE] = array_keys(Lang::conditionBrowser('cndTypes'));

        parent::__construct($data, $opts);
    }

    /** the validated values, in the terms Conditions::browse() works with */
    public function getBrowseOpts() : array
    {
        $criteria = [];
        foreach ($this->values['cr'] ?? [] as $i => $cr)
        {
            $crs = (int)$this->values['crs'][$i];
            $crv = (int)$this->values['crv'][$i];

            if ($cr == self::CR_CONDITION_TYPE)             // the "operator" holds the picked type
                $criteria[] = ['kind' => $cr, 'op' => '=', 'value' => $crs];
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
