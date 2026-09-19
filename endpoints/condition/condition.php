<?php

namespace Aowow;

if (!defined('AOWOW_REVISION'))
    die('illegal access');


/*
 * Detail page for one distinct condition source - the row identity ?conditions browses
 * (SourceType/SourceGroup/SourceEntry/SourceId). Reached only from a row's own link there: unlike
 * SmartAI, whose owning entity almost always has a page of its own to link to instead, most
 * condition sources (a loot template, a gossip menu, a quest availability check, ...) have none,
 * so ?conditions could previously only ever re-filter its own listing down to a source type - never
 * pin one exact row, which several rows can share (e.g. SRC_SMART_EVENT rows differing only by
 * SourceId). This gives every row its own permanent address.
 *
 * No Type:: entry - `conditions` has no DBTypeList and this id is a composite string, not a real
 * typeId - so this stays off the g_* lookup / [tag=id] markup system entities get; a resolvable
 * source still links to its own entity page, just spelled out via [url=...] or the matching db tag.
 */
class ConditionBaseResponse extends TemplateResponse implements ICache
{
    use TrDetailPage, TrCache;

    protected  int    $cacheType         = CACHE_TYPE_DETAIL_PAGE;
    protected  int    $requiredUserGroup = U_GROUP_STAFF;

    protected  string $template          = 'detail-page-generic';
    protected  string $pageName          = 'condition';
    protected ?int    $activeTab         = parent::TAB_DATABASE;
    protected  array  $breadcrumb        = [0, 105];

    public int $srcType = 0;
    public int $group   = 0;
    public int $entry   = 0;
    public int $srcId   = 0;

    private string $key = '';

    public function __construct(string $id)
    {
        parent::__construct($id);

        $this->key = $id;

        $parts = array_pad(array_slice(array_map('intVal', explode(':', $id)), 0, 4), 4, 0);
        [$this->srcType, $this->group, $this->entry, $this->srcId] = $parts;
    }

    // no Type:: - see class doc comment; both DBType and typeId fold into misc instead
    public function getCacheKeyComponents() : array
    {
        return array(-9, 0, User::$groups, md5($this->key));
    }

    protected function generate() : void
    {
        // existence is checked against the raw table, not against whether prepare() produced any
        // markup below - a source like SRC_CREATURE_LOOT_TEMPLATE resolves through a lookup
        // (lootIdToNpc() et al.) that can legitimately come up empty (loot template not currently
        // tied to any imported creature) while the row itself is real; that must not read as 404
        $exists = (bool)DB::World()->selectCell(
           'SELECT 1 FROM conditions WHERE `SourceTypeOrReferenceId` = %i AND `SourceGroup` = %i AND `SourceEntry` = %i AND `SourceId` = %i LIMIT 1',
            $this->srcType, $this->group, $this->entry, $this->srcId
        );

        if (!$exists)
            $this->generateNotFound(Util::ucFirst(Lang::game('conditions')), Lang::condition('notFound'));

        // arrays, not plain ints: getBySource() treats an int 0 as "no filter", which would pull
        // in every SourceGroup/Entry/Id rather than just the (possibly legitimately 0) one this
        // row's own key names - see Gossip::buildTextTable()'s identical guard
        $cnd = new Conditions();
        $cnd->getBySource([$this->srcType], [$this->group], [$this->entry], [$this->srcId])->prepare();

        $cndTag = $cnd->toMarkupTag();

        $this->extendGlobalData($cnd->getJSGlobals());

        $srcTypeLabel = Lang::conditionBrowser('srcTypes')[$this->srcType] ?? ('#'.$this->srcType);
        [$label, $link] = $this->resolveSource();

        $this->h1 = $label !== null
            ? Lang::condition('title', [Util::ucFirst($srcTypeLabel), $label])
            : Lang::condition('titleRaw', [Util::ucFirst($srcTypeLabel), $this->entry ?: ($this->group ?: $this->srcId)]);


        /**************/
        /* Page Title */
        /**************/

        array_unshift($this->title, $this->h1, Util::ucFirst(Lang::game('conditions')));


        /****************/
        /* Main Content */
        /****************/

        $this->redButtons = [BUTTON_LINKS => false, BUTTON_WOWHEAD => false];

        $infobox = [Lang::conditionBrowser('srcType').Lang::main('colon').$srcTypeLabel];

        if ($link)
            $infobox[] = Lang::condition('source').Lang::main('colon').$link;

        $infobox[] = Lang::condition('srcGroup').Lang::main('colon').$this->group;
        $infobox[] = Lang::condition('srcEntry').Lang::main('colon').$this->entry;

        if ($this->srcId)
            $infobox[] = Lang::condition('srcId').Lang::main('colon').$this->srcId;

        $this->infobox = new InfoboxMarkup($infobox, ['allow' => Markup::CLASS_STAFF, 'dbpage' => true], 'infobox-contents0');

        if ($cndTag)
            $this->extraText = new Markup('[h3]'.Lang::condition('conditions').'[/h3]'.$cndTag, ['allow' => Markup::CLASS_STAFF], 'text-generic');

        parent::generate();
    }

    /** @return array{0: ?string, 1: ?string} [plain label, markup to link it - both null if this source can't be named] */
    private function resolveSource() : array
    {
        // SourceEntry is smart_scripts.entryorguid, SourceId its source_type (0 creature, 1 gameobject,
        // 2 areatrigger); anything else (action list, gossip, quest, spell, ...) has no entity page -
        // same resolution ConditionList.js's own _createTab() does for the same field
        if ($this->srcType == Conditions::SRC_SMART_EVENT)
        {
            $ownerType = match ($this->srcId)
            {
                0       => Type::NPC,
                1       => Type::OBJECT,
                2       => Type::AREATRIGGER,
                default => 0
            };

            if (!$ownerType)
                return [null, null];

            $ownerId = $this->entry;
            if ($ownerId < 0 && $ownerType != Type::AREATRIGGER)
                $ownerId = (int)DB::Aowow()->selectCell('SELECT `typeId` FROM ::spawns WHERE `type` = %i AND `guid` = %i', $ownerType, -$ownerId);

            if ($ownerId <= 0 || !($name = self::nameFor($ownerType, $ownerId)))
                return [null, null];

            return [$name, $this->linkFor($ownerType, $ownerId, $name)];
        }

        // a gossip menu has no name of its own and no g_* lookup - same fallback GameText::row() uses
        if (($this->srcType == Conditions::SRC_GOSSIP_MENU || $this->srcType == Conditions::SRC_GOSSIP_MENU_OPTION) && $this->group > 0)
        {
            $name = (string)Lang::gossip('menu', [$this->group]);
            return [$name, '[url=?gossip='.$this->group.']'.$name.'[/url]'];
        }

        [$grpType, $entryType, ] = Conditions::getSourceTypes()[$this->srcType] ?? [null, null, null];

        if (is_int($entryType) && $this->entry > 0 && ($name = self::nameFor($entryType, $this->entry)))
            return [$name, $this->linkFor($entryType, $this->entry, $name)];

        if (is_int($grpType) && $this->group > 0 && ($name = self::nameFor($grpType, $this->group)))
            return [$name, $this->linkFor($grpType, $this->group, $name)];

        return [null, null];
    }

    /** areatrigger has no [tag=id] markup support (no g_* lookup - see Type::getJSGlobalString()); every other type here does */
    private function linkFor(int $type, int $id, string $name) : string
    {
        if ($type == Type::AREATRIGGER)
            return '[url=?areatrigger='.$id.']'.$name.'[/url]';

        $this->extendGlobalIds($type, $id);
        return '['.Type::getFileString($type).'='.$id.']';
    }

    private static function nameFor(int $type, int $id) : ?string
    {
        $cls = match ($type)
        {
            Type::NPC         => CreatureList::class,
            Type::ITEM        => ItemList::class,
            Type::ZONE        => ZoneList::class,
            Type::OBJECT      => GameObjectList::class,
            Type::QUEST       => QuestList::class,
            Type::SPELL       => SpellList::class,
            Type::AREATRIGGER => AreaTriggerList::class,
            default           => null
        };

        if (!$cls)
            return null;

        return (string)($cls::getName($id) ?: '') ?: null;
    }
}

?>
