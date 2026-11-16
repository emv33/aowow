<?php

namespace Aowow;

if (!defined('AOWOW_REVISION'))
    die('illegal access');


/*
 * A single row of `warden_checks` - see WardenCheck for the table's story.
 *
 * Shows the columns the core reads for the row's type, the way it reads them (a memory check reads
 * as many bytes as its expected result holds, not `length`), and says so when a set column is one
 * the core ignores for this type or when the core would not load the row at all.
 */
class WardencheckBaseResponse extends TemplateResponse implements ICache
{
    use TrDetailPage, TrCache;

    private const string GRID_CSS = <<<CSS
        #warden-check .grid { clear:left; display: grid; grid-template-columns: 160px auto; }
        #warden-check .grid thead,
        #warden-check .grid tbody,
        #warden-check .grid tr { display: contents; }
        #warden-check .grid td { word-break: break-all; }
    CSS;

    protected  int    $cacheType         = CACHE_TYPE_DETAIL_PAGE;
    protected  int    $requiredViewGroup = U_GROUP_STAFF;

    protected  string $template          = 'detail-page-generic';
    protected  string $pageName          = 'wardencheck';
    protected ?int    $activeTab         = parent::TAB_DATABASE;
    protected  array  $breadcrumb        = [0, 123];

    public int $type   = -18;   // no Type:: entry - no DBTypeList backs this ad hoc table read; shared sentinel with WardenchecksBaseResponse
    public int $typeId = 0;

    public function __construct(string $id)
    {
        parent::__construct($id);

        $this->typeId = ctype_digit($id) ? (int)$id : -1;   // check 0 exists: the core indexes its checks by id from 0
    }

    protected function generate() : void
    {
        if ($this->typeId < 0 || !($r = WardenCheck::getRows($this->typeId)[$this->typeId] ?? null))
            $this->generateNotFound(Lang::warden('title'), Lang::warden('notFound'));

        $type     = $r['type'];
        $category = WardenCheck::category($type);


        /**************/
        /* Page Title */
        /**************/

        // the core names a row without a comment "Undocumented Check" in its logs
        $this->h1 = Util::htmlEscape($r['comment'] !== '' ? $r['comment'] : Lang::warden('undocumented'));

        array_unshift($this->title, $this->h1, Lang::warden('titleOf', [$this->typeId]), Util::ucFirst(Lang::warden('title')));


        /****************/
        /* Main Content */
        /****************/

        $this->redButtons = [BUTTON_LINKS => false, BUTTON_WOWHEAD => false];

        $infobox   = [];
        $infobox[] = Lang::warden('id').Lang::main('colon').$this->typeId;
        $infobox[] = Lang::warden('type').Lang::main('colon').WardenCheck::typeName($type).' [small class=q0]('.$type.')[/small]';
        $infobox[] = Lang::warden('category').Lang::main('colon').($category !== null ? Lang::warden('categories', $category) : Lang::warden('unsupported'));

        // IsWardenCategoryInWorldOnly()
        if ($category === WardenCheck::CAT_LUA)
            $infobox[] = Lang::warden('inWorldOnly');

        if ($skip = WardenCheck::skipReason($r))
            $infobox[] = '[span class=q10]'.Lang::warden('skipped').'[/span]'.Lang::main('colon').$skip;

        // `warden_action` per realm, else the worldserver.conf default nothing in the DBs records
        $actions = [];
        foreach (WardenCheck::actionOverrides()[$this->typeId] ?? [] as $realm => $a)
            $actions[] = $realm.Lang::main('colon').(Lang::warden('actions', $a) ?? '#'.$a);
        $infobox[] = Lang::warden('action').Lang::main('colon').($actions ? implode(', ', $actions) : Lang::warden('actionDefault'));

        $this->infobox = new InfoboxMarkup($infobox, ['allow' => Markup::CLASS_STAFF, 'dbpage' => true], 'infobox-contents0');

        // the columns the core reads for this type; world db text, so a '[' of its own is escaped for Markup
        $esc  = fn(string $x) : string => str_replace('[', '\\[', $x);
        $hex  = fn(string $x) : string => '[span style="font-family:monospace"]'.trim(chunk_split($x, 2, ' ')).'[/span]';
        $addr = fn(?int $x) : string => '[span style="font-family:monospace"]0x'.strtoupper(str_pad(dechex((int)$x), 8, '0', STR_PAD_LEFT)).'[/span]';

        $rows = [];
        foreach (WardenCheck::usedColumns($type) as $col)
        {
            $rows[] = match ($col)
            {
                'str'     => match ($type)
                {
                    WardenCheck::LUA_EVAL_CHECK => [Lang::warden('luaCode'), '[code]'.$esc($r['str']).'[/code]'],
                    WardenCheck::MPQ_CHECK      => [Lang::warden('file'),    $esc($r['str'])],
                    WardenCheck::DRIVER_CHECK   => [Lang::warden('driver'),  $esc($r['str'])],
                    default                     => [Lang::warden('module'),  $r['str'] !== '' ? $esc($r['str']) : Lang::warden('mainExe')]
                },
                'address' => [Lang::warden('address'), $addr($r['address'])],
                'length'  => [Lang::warden('length'),  (int)$r['length']],
                'data'    => [Lang::warden('data'),    $r['data'] !== '' ? $hex($r['data']) : '[small class=q0]-[/small]'],
                'result'  => [Lang::warden('result'),  $r['result'] !== '' ? $hex($r['result']) : '[small class=q0]-[/small]']
            };

            // MEM_CHECK sends the size of the expected result as the number of bytes to read (WardenWin::RequestChecks)
            if ($col == 'result' && $type == WardenCheck::MEM_CHECK)
                $rows[] = [Lang::warden('readLength'), strlen($r['result']) / 2];
        }

        // set, but not read for this type
        $unused = [];
        foreach (['str', 'address', 'length', 'data', 'result'] as $col)
            if (!in_array($col, WardenCheck::usedColumns($type)) && $r[$col] !== null && $r[$col] !== '' && $r[$col] !== 0 && trim((string)$r[$col], '0') !== '')
                $unused[] = '`'.$col.'`';

        if ($unused)
            $rows[] = [Lang::warden('unused'), implode(', ', $unused).' [small class=q0]('.Lang::warden('unusedNote').')[/small]'];

        if ($rows)
        {
            $tbl = '';
            foreach ($rows as [$label, $value])
                $tbl .= '[tr][td][b]'.$label.'[/b][/td][td]'.$value.'[/td][/tr]';

            $this->extraText = new Markup(
                '[style]'.strtr(self::GRID_CSS, "\n", ' ').'[/style][div id=warden-check][table class=grid]'.$tbl.'[/table][/div]',
                ['allow' => Markup::CLASS_ADMIN], 'text-contents0'
            );
        }

        parent::generate();
    }
}

?>
