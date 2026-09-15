<?php

namespace Aowow;

if (!defined('AOWOW_REVISION'))
    die('illegal access');


/*
 * The entity's own template row, as the world DB stores it.
 *
 * Everything else on a detail page is an interpretation of these columns - flags resolved to names,
 * ids resolved to links, numbers folded into sentences. That is what the page is for, but it means
 * checking one column against the DB meant leaving the site for a MySQL client.
 *
 * Staff only, collapsed, and read from DB::World() live.
 */
class RawRow
{
    private const string BASE_CSS = <<<CSS
        #raw-row-generic .grid { clear:left; display: grid; grid-template-columns: 260px auto; }
        #raw-row-generic .grid thead,
        #raw-row-generic .grid tbody,
        #raw-row-generic .grid tr { display: contents; }
        #raw-row-generic .grid td { word-break: break-word; }
    CSS;

    /** the template table behind each page, and the key columns TC has spelled it with */
    private const array TABLES = array(
        Type::NPC    => ['creature_template',   ['entry']        ],
        Type::OBJECT => ['gameobject_template', ['entry']        ],
        Type::ITEM   => ['item_template',       ['entry']        ],
        Type::QUEST  => ['quest_template',      ['ID', 'entry']  ]
    );

    public static function buildFor(int $type, int $typeId) : ?Markup
    {
        if ($typeId <= 0 || !User::isInGroup(U_GROUP_STAFF) || !isset(self::TABLES[$type]))
            return null;

        [$table, $keys] = self::TABLES[$type];

        if (!DB::World()->selectCell('SHOW TABLES LIKE %s', $table))
            return null;

        $row = null;
        foreach ($keys as $k)
            if ($row = DB::World()->selectRow('SELECT * FROM %n WHERE %n = %i', $table, $k, $typeId))
                break;

        if (!$row)
            return null;

        $tbl = '';
        foreach ($row as $col => $val)
        {
            // no escaping: the value is substituted into markup, and Markup::cleanText() json_encodes the whole body
            $val = (string)$val;
            $tbl .= '[tr][td][small class=q0]'.$col.'[/small][/td][td]'.($val === '' ? '[small class=q0]-[/small]' : $val).'[/td][/tr]';
        }

        return new Markup(
            '[style]'.strtr(self::BASE_CSS, "\n", ' ').'[/style]' .
            '[pad][h3][toggler=hidden id=raw-row]'.Lang::rawRow('title', [$table]).'[/toggler][/h3]' .
            '[div=hidden id=raw-row clear=left][table class=grid]'.$tbl.'[/table][/div]',
            ['allow' => Markup::CLASS_ADMIN], 'raw-row-generic'
        );
    }
}

?>
