<?php

namespace Aowow;

if (!defined('AOWOW_REVISION'))
    die('invalid access');


// assumptions
// every character in this sitemap will be bog-standard ANSI
// so it consumes 1 Byte in UTF-8
// every item is thus <140 byte
// so we hit 50k entries and have ~3.5MB storage capacity left

class Sitemap
{
    public const string ERR_TITLE  = 'Invalid sitemap';
    public const string ERR_PAGE   = 'This sitemap does not exist.';
    public const string ERR_OFFSET = 'The maximum page for this sitemap is %d.';

    private const int MAX_ENTRIES  = 50000;
    private const int LASTMOD_BASE = 1435701600;            // 01.07.2015 - 00:00:00

    public static int $maxPage = 0;

    private static string $page       = '';
    private static int    $offset     = 1;
    private static array  $validPages = array(
        'npc'         => [Type::NPC,         '::creature',        'IF(x.`cuFlags` & 0x40000000, 0.1, 0.4)'],
        'object'      => [Type::OBJECT,      '::objects',         'IF(x.`cuFlags` & 0x40000000, 0.1, 0.4)'],
        'item'        => [Type::ITEM,        '::items',           'IF(x.`cuFlags` & 0x40000000, 0.1, IF(src.`typeId` IS NULL, 0.5, 0.7))'],
        'itemset'     => [Type::ITEMSET,     '::itemset',         'IF(x.`cuFlags` & 0x40000000, 0.1, 0.7)'],
        'quest'       => [Type::QUEST,       '::quests',          'IF(x.`cuFlags` & 0x40000000, 0.1, IF(src.`typeId` IS NULL, 0.3, 0.5))'],
        'spell'       => [Type::SPELL,       '::spell',           'IF(x.`cuFlags` & 0x40000000, 0.1, IF(src.`typeId` IS NULL, 0.5, 0.8))'],
        'zone'        => [Type::ZONE,        '::zones',           'IF(x.`cuFlags` & 0x40000000, 0.1, 0.4)'],
        'faction'     => [Type::FACTION,     '::factions',        'IF(x.`cuFlags` & 0x40000000, 0.1, 0.4)'],
        'pet'         => [Type::PET,         '::pet',             'IF(x.`cuFlags` & 0x40000000, 0.1, 0.4)'],
        'achievement' => [Type::ACHIEVEMENT, '::achievement',     'IF(x.`cuFlags` & 0x40000000, 0.1, IF(x.`category` = 81, 0.6, IF(x.`category` IN (1, 122, 133, 141, 134, 14807, 131, 130, 128, 132, 21, 124, 135, 126, 154, 125, 140, 145, 147, 136, 127, 152, 153, 191, 123, 14822, 14821, 14823, 137, 178, 173, 14963, 15021, 15062), 0.3, 0.4)))'],
        'title'       => [Type::TITLE,       '::titles',          'IF(x.`cuFlags` & 0x40000000, 0.1, IF(src.`typeId` IS NULL, 0.3, 0.4))'],
        'event'       => [Type::WORLDEVENT,  '::events',          'IF(x.`cuFlags` & 0x40000000, 0.1, IF(x.`holidayId` = 0, 0.2, 0.4))'],
        'class'       => [Type::CHR_CLASS,   '::classes',         'IF(x.`cuFlags` & 0x40000000, 0.1, 0.7)'],
        'race'        => [Type::CHR_RACE,    '::races',           'IF(x.`cuFlags` & 0x40000000, 0.1, 0.7)'],
        'skill'       => [Type::SKILL,       '::skillline',       'IF(x.`cuFlags` & 0x40000000, 0.1, IF(x.`typeCat` IN(11, 9), 0.5, IF(x.`typeCat` IN (8, 6), 0.4, 0.3)))'],
        'currency'    => [Type::CURRENCY,    '::currencies',      'IF(x.`cuFlags` & 0x40000000, 0.1, IF(x.`category` = 3, 0.2, IF(x.`description_loc0`, 0.4, 0.3)))'],
        'sound'       => [Type::SOUND,       '::sounds',          'IF(x.`cuFlags` & 0x40000000, 0.1, 0.3)'],
        'icon'        => [Type::ICON,        '::icons',           'IF(x.`cuFlags` & 0x40000000, 0.1, 0.3)'],
        'emote'       => [Type::EMOTE,       '::emotes',          'IF(x.`cuFlags` & 0x40000000, 0.1, 0.3)'],
        'enchantment' => [Type::ENCHANTMENT, '::itemenchantment', 'IF(x.`cuFlags` & 0x40000000, 0.1, IF(x.`type1` IN (1, 7) OR x.`type2` IN (1, 7) OR x.`type3` IN (1, 7), 0.4, 0.3))'],
        'areatrigger' => [Type::AREATRIGGER, '::areatrigger',     'IF(x.`cuFlags` & 0x40000000, 0.1, 0.3)'],
        'mail'        => [Type::MAIL,        '::mails',           'IF(x.`cuFlags` & 0x40000000, 0.1, 0.3)'],
     // 'guide'       => [Type::GUIDE,       '::guides',          ''] super low prio .. need a way to filter for publicly visible guides
        // aowow - custom: formerly staff-gated, made public; see TaxipathBaseResponse - `id` is a
        // real column on aowow_taxipath (unlike gossip/encounter/teleport/etc, which read straight
        // off a world DB table this scheme can't reach), it just has no Type:: constant of its own
        // (-6 is TaxipathBaseResponse's own sentinel, reused here) and no cuFlags column to weigh
        // rows by, so the src/comments/screenshots/videos joins below simply never match anything
        // for it (harmless - the page carries no comment widget either, see BUTTON_LINKS => false)
        // and the priority is a flat number instead of an IF()
        'taxipath'    => [-6, '::taxipath', '0.3']
    );

    // aowow - custom: formerly staff-gated listing/detail pages, given real per-row sitemap coverage.
    // each reads straight off a world DB (or, for waypointpath, an aggregated aowow DB) table rather
    // than one of the flagged, cuFlags-bearing aowow tables $validPages assumes, so they can't reuse
    // getIndex()/getPage() unmodified - see getCustomPageCount()/getCustomPageRows() below
    private const array CUSTOM_PAGES = ['gossip', 'encounter', 'teleport', 'graveyard', 'outdoorpvp', 'poi', 'waypointpath'];

    public static function generate(string $page, int $offset) : ?string
    {
        self::$page   = $page;
        self::$offset = $offset;

        if (!self::$page)
            return self::getIndex();
        else if (self::$page == 'special')
            return self::getSpecial();
        else if (isset(self::$validPages[self::$page][1]))
            return self::getPage();
        else if (in_array(self::$page, self::CUSTOM_PAGES, true))
            return self::getCustomPage();

        // whoops!
        return null;
    }

    private static function getIndex() : ?string
    {
        $root = new SimpleXML('<sitemapindex />');
        $root->addAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');

        $root->addChild('sitemap')->addChild('loc', Cfg::get('HOST_URL').'/?sitemap=special');

        foreach (self::$validPages as $page => [, $table, ])
        {
            $n = DB::Aowow()->selectCell('SELECT CEIL(COUNT(*) / %i) FROM %n', self::MAX_ENTRIES, $table);
            for ($i = 1; $i <= $n; $i++)
                $root->addChild('sitemap')->addChild('loc', Cfg::get('HOST_URL').'/?sitemap='.$page.'&amp;page='.$i);
        }

        // aowow - custom
        foreach (self::CUSTOM_PAGES as $page)
        {
            $n = (int)ceil(self::getCustomPageCount($page) / self::MAX_ENTRIES);
            for ($i = 1; $i <= $n; $i++)
                $root->addChild('sitemap')->addChild('loc', Cfg::get('HOST_URL').'/?sitemap='.$page.'&amp;page='.$i);
        }

        return $root->asXML() ?: null;
    }

    private static function getSpecial() : ?string
    {
        if (self::$offset != 1)
        {
            self::$maxPage = 1;
            return null;
        }

        $root = new SimpleXML('<urlset />');
        $root->addAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');

        // home
        $url = $root->addChild('url');
        $url->addChild('loc', Cfg::get('HOST_URL'));
        $url->addChild('priority', 1);
        $url->addChild('changefreq', 'monthly');

        // talent calc
        $url = $root->addChild('url');
        $url->addChild('loc', Cfg::get('HOST_URL').'/?talent');
        $url->addChild('priority', 1);
        $url->addChild('changefreq', 'yearly');

        // pet calc
        $url = $root->addChild('url');
        $url->addChild('loc', Cfg::get('HOST_URL').'/?petcalc');
        $url->addChild('priority', 0.8);
        $url->addChild('changefreq', 'yearly');

        // item compare
        $url = $root->addChild('url');
        $url->addChild('loc', Cfg::get('HOST_URL').'/?compare');
        $url->addChild('priority', 0.9);
        $url->addChild('changefreq', 'yearly');

        // profiler
        if (Cfg::get('PROFILER_ENABLE'))
        {
            $url = $root->addChild('url');
            $url->addChild('loc', Cfg::get('HOST_URL').'/?profiler');
            $url->addChild('priority', 1);
            $url->addChild('changefreq', 'yearly');
        }

        // maps
        $url = $root->addChild('url');
        $url->addChild('loc', Cfg::get('HOST_URL').'/?maps');
        $url->addChild('priority', 0.7);
        $url->addChild('changefreq', 'yearly');

        // achievement criteria browser - aowow - custom
        // a single search/filter tool, not a per-row detail page (a criterion's own detail lives
        // on its achievement's page), so it belongs here rather than in $validPages
        $url = $root->addChild('url');
        $url->addChild('loc', Cfg::get('HOST_URL').'/?achievement-criteria');
        $url->addChild('priority', 0.3);
        $url->addChild('changefreq', 'monthly');

        // aowow - custom start
        // the fork's own listing pages, formerly staff-gated. gossip/encounter/teleport/graveyard/
        // outdoorpvp/poi/taxipath/waypointpath now get real per-row detail-page coverage instead
        // (see $validPages' 'taxipath' entry and CUSTOM_PAGES/getCustomPage() above) and so were
        // dropped from this flat list, same as none of the pre-existing 20 $validPages types have
        // their own listing page ($validPages ?<foo>s form) linked here either - only detail pages
        // get a <url>.
        //
        // the rest stay as one flat URL each: conditions/smartai/texts are keyed by a composite
        // string (source type+group+entry+id, or a ct:/bt:/nt: text id) with no scalar per-row id
        // to build a ?<page>=<id> URL from (see ConditionBaseResponse's/TextBaseResponse's own
        // class doc comments); weather/trainers/transports/factionchange have no detail page of
        // their own at all to link a row to - only the listing. data-integrity is left out on
        // purpose: it's a world DB lint tool for staff, not reader content.
        foreach ([
            'conditions', 'smartai', 'weather', 'trainers', 'transports', 'factionchange', 'texts',
        ] as $page)
        {
            $url = $root->addChild('url');
            $url->addChild('loc', Cfg::get('HOST_URL').'/?'.$page);
            $url->addChild('priority', 0.3);
            $url->addChild('changefreq', 'monthly');
        }
        // aowow - custom end

        return $root->asXML();
    }

    private static function getPage() : ?string
    {
        [$type, $table, $prioString] = self::$validPages[self::$page];

        $n = DB::Aowow()->selectCell('SELECT CEIL(COUNT(*) / %i) FROM %n', self::MAX_ENTRIES, $table);
        if (self::$offset <= 0 || self::$offset > $n)
        {
            self::$maxPage = $n;
            return null;
        }

        $root = new SimpleXML('<urlset />');
        $root->addAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');

        $rows = DB::Aowow()->selectAssoc(
           'SELECT x.`id` AS ARRAY_KEY, ('.$prioString.') AS "priority", GREATEST(IFNULL(MAX(ss.`date`), 0), IFNULL(MAX(vi.`date`), 0), IFNULL(MAX(co.`date`), 0)) AS "lastmod" FROM %n x
            LEFT JOIN ::source      src ON src.`type` = %i AND src.`typeId` = x.`id`
            LEFT JOIN ::comments    co  ON  co.`type` = %i AND  co.`typeId` = x.`id` AND (co.`flags` & %i) = 0
            LEFT JOIN ::screenshots ss  ON  ss.`type` = %i AND  ss.`typeId` = x.`id` AND (co.`flags` & %i) = 0 AND (co.`flags` & %i) > 0
            LEFT JOIN ::videos      vi  ON  vi.`type` = %i AND  vi.`typeId` = x.`id` AND (co.`flags` & %i) = 0 AND (co.`flags` & %i) > 0
            GROUP BY x.`id` LIMIT %i, %i',
            $table,
            $type,
            $type, CC_FLAG_DELETED,
            $type, CC_FLAG_DELETED, CC_FLAG_APPROVED,
            $type, CC_FLAG_DELETED, CC_FLAG_APPROVED,
            self::MAX_ENTRIES * (self::$offset - 1), self::MAX_ENTRIES
        );

        foreach ($rows as $id => $pair)
        {
            $url = $root->addChild('url');
            $url->addChild('loc', Cfg::get('HOST_URL').'/?'.self::$page.'='.$id);
            $url->addChild('priority', $pair['priority']);
            $url->addChild('lastmod', date('c', $pair['lastmod'] ?: self::LASTMOD_BASE));
        }

        return $root->asXML();
    }

    /*
     * aowow - custom start
     *
     * per-row sitemap coverage for CUSTOM_PAGES: pages whose detail view takes a real scalar id,
     * but whose backing table can't go through $validPages/getPage() as-is - either it lives in the
     * world DB rather than the aowow DB getPage() always queries (gossip, encounter, teleport,
     * graveyard, outdoorpvp, poi), or the id is a derived aggregate rather than a literal column
     * (waypointpath - see WaypointPathList's own class doc comment). None of these tables carry a
     * cuFlags-style hide flag (there is nothing to check - a row that exists at all is public), so
     * every row gets a flat priority instead of $validPages' IF(cuFlags & ..., low, high) formula;
     * this still satisfies "never exclude a flagged row outright" since there is no flag to exclude
     * on in the first place. Only gossip and encounter carry a real Type:: constant and expose the
     * comment/screenshot/video widget (BUTTON_LINKS is false on every other one of these detail
     * pages), so only those two bother resolving a real lastmod; the rest fall back to LASTMOD_BASE.
     */

    private static function getCustomPage() : ?string
    {
        $n = (int)ceil(self::getCustomPageCount(self::$page) / self::MAX_ENTRIES);
        if (self::$offset <= 0 || self::$offset > $n)
        {
            self::$maxPage = $n;
            return null;
        }

        $root = new SimpleXML('<urlset />');
        $root->addAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');

        $limit  = self::MAX_ENTRIES;
        $offset = self::MAX_ENTRIES * (self::$offset - 1);

        foreach (self::getCustomPageRows(self::$page, $limit, $offset) as $id => [$priority, $lastmod])
        {
            $url = $root->addChild('url');
            $url->addChild('loc', Cfg::get('HOST_URL').'/?'.self::$page.'='.$id);
            $url->addChild('priority', $priority);
            $url->addChild('lastmod', date('c', $lastmod ?: self::LASTMOD_BASE));
        }

        return $root->asXML();
    }

    private static function getCustomPageCount(string $page) : int
    {
        return match ($page)
        {
            'gossip'       => (int)DB::World()->selectCell('SELECT COUNT(*) FROM (SELECT `MenuID` FROM gossip_menu UNION SELECT `MenuID` FROM gossip_menu_option) t'),
            'encounter'    => (int)DB::World()->selectCell('SELECT COUNT(*) FROM instance_encounters'),
            'teleport'     => self::worldTableExists('game_tele')           ? (int)DB::World()->selectCell('SELECT COUNT(*) FROM game_tele') : 0,
            'graveyard'    => ($t = self::graveyardZoneTable())             ? (int)DB::World()->selectCell('SELECT COUNT(DISTINCT `id`) FROM '.$t) : 0,
            'outdoorpvp'   => self::worldTableExists('outdoorpvp_template') ? (int)DB::World()->selectCell('SELECT COUNT(*) FROM outdoorpvp_template') : 0,
            'poi'          => self::poiIdColumn()                          ? (int)DB::World()->selectCell('SELECT COUNT(*) FROM points_of_interest') : 0,
            'waypointpath' => count(self::waypointPathIds()),
            default        => 0
        };
    }

    /** @return array id => [priority, lastmod] */
    private static function getCustomPageRows(string $page, int $limit, int $offset) : array
    {
        $out = [];

        switch ($page)
        {
            case 'gossip':
                // a menu may exist as options only (built by a script) - don't enumerate off `gossip_menu` alone, same as Gossip::exists()
                $ids     = array_map('intVal', DB::World()->selectCol('SELECT `MenuID` FROM (SELECT `MenuID` FROM gossip_menu UNION SELECT `MenuID` FROM gossip_menu_option) t ORDER BY `MenuID` ASC LIMIT %i, %i', $offset, $limit) ?: []);
                $lastmod = self::lastModFor(Type::GOSSIP, $ids);
                foreach ($ids as $id)
                    $out[$id] = [0.3, $lastmod[$id] ?? 0];
                break;

            case 'encounter':
                $ids     = array_map('intVal', DB::World()->selectCol('SELECT `entry` FROM instance_encounters ORDER BY `entry` ASC LIMIT %i, %i', $offset, $limit) ?: []);
                $lastmod = self::lastModFor(Type::ENCOUNTER, $ids);
                foreach ($ids as $id)
                    $out[$id] = [0.3, $lastmod[$id] ?? 0];
                break;

            case 'teleport':
                if (self::worldTableExists('game_tele'))
                    foreach (DB::World()->selectCol('SELECT `id` FROM game_tele ORDER BY `id` ASC LIMIT %i, %i', $offset, $limit) ?: [] as $id)
                        $out[(int)$id] = [0.3, 0];
                break;

            case 'graveyard':
                if ($t = self::graveyardZoneTable())
                    foreach (DB::World()->selectCol('SELECT DISTINCT `id` FROM '.$t.' ORDER BY `id` ASC LIMIT %i, %i', $offset, $limit) ?: [] as $id)
                        $out[(int)$id] = [0.3, 0];
                break;

            case 'outdoorpvp':
                if (self::worldTableExists('outdoorpvp_template'))
                    foreach (DB::World()->selectCol('SELECT `TypeId` FROM outdoorpvp_template ORDER BY `TypeId` ASC LIMIT %i, %i', $offset, $limit) ?: [] as $id)
                        $out[(int)$id] = [0.3, 0];
                break;

            case 'poi':
                if ($col = self::poiIdColumn())
                    foreach (DB::World()->selectCol('SELECT `'.$col.'` FROM points_of_interest ORDER BY `'.$col.'` ASC LIMIT %i, %i', $offset, $limit) ?: [] as $id)
                        $out[(int)$id] = [0.3, 0];
                break;

            case 'waypointpath':
                foreach (array_slice(self::waypointPathIds(), $offset, $limit) as $id)
                    $out[$id] = [0.3, 0];
                break;
        }

        return $out;
    }

    /** MAX(date) across comments/screenshots/videos for a real Type:: + a set of typeIds, same signal getPage() folds into its own per-row query */
    private static function lastModFor(int $type, array $ids) : array
    {
        if (!$ids)
            return [];

        $out = [];

        foreach (DB::Aowow()->selectAssoc('SELECT `typeId` AS ARRAY_KEY, MAX(`date`) AS "d" FROM ::comments WHERE `type` = %i AND `typeId` IN %in AND (`flags` & %i) = 0 GROUP BY `typeId`', $type, $ids, CC_FLAG_DELETED) ?: [] as $id => $r)
            $out[(int)$id] = max($out[(int)$id] ?? 0, (int)$r['d']);

        foreach (DB::Aowow()->selectAssoc('SELECT `typeId` AS ARRAY_KEY, MAX(`date`) AS "d" FROM ::screenshots WHERE `type` = %i AND `typeId` IN %in AND `status` = %i GROUP BY `typeId`', $type, $ids, CC_FLAG_APPROVED) ?: [] as $id => $r)
            $out[(int)$id] = max($out[(int)$id] ?? 0, (int)$r['d']);

        foreach (DB::Aowow()->selectAssoc('SELECT `typeId` AS ARRAY_KEY, MAX(`date`) AS "d" FROM ::videos WHERE `type` = %i AND `typeId` IN %in AND `status` = %i GROUP BY `typeId`', $type, $ids, CC_FLAG_APPROVED) ?: [] as $id => $r)
            $out[(int)$id] = max($out[(int)$id] ?? 0, (int)$r['d']);

        return $out;
    }

    private static function worldTableExists(string $tbl) : bool
    {
        static $known = [];

        return $known[$tbl] ??= (bool)DB::World()->selectCell('SHOW TABLES LIKE %s', $tbl);
    }

    /** graveyard link data moved from `game_graveyard_zone` to `graveyard_zone` across TC revisions - same fallback GraveyardBaseResponse/GraveyardsBaseResponse use */
    private static function graveyardZoneTable() : ?string
    {
        if (self::worldTableExists('graveyard_zone'))
            return 'graveyard_zone';
        if (self::worldTableExists('game_graveyard_zone'))
            return 'game_graveyard_zone';

        return null;
    }

    /** 3.3.5 spells the primary key `ID`; some cores use `entry` instead - same fallback PoiBaseResponse/PoisBaseResponse try when reading a row */
    private static function poiIdColumn() : ?string
    {
        if (!self::worldTableExists('points_of_interest'))
            return null;

        return DB::World()->selectCell('SHOW COLUMNS FROM points_of_interest LIKE %s', 'ID') ? 'ID' : 'entry';
    }

    /** every (kind, sourceId) pair in `::creature_waypoints`, folded into WaypointPathList's own public id encoding rather than re-deriving KIND_OFFSET by hand */
    private static function waypointPathIds() : array
    {
        static $ids = null;

        if ($ids !== null)
            return $ids;

        $ids = [];
        foreach (DB::Aowow()->selectAssoc('SELECT CONCAT(`kind`, ":", `creatureOrPath`) AS ARRAY_KEY, `kind`, `creatureOrPath` FROM ::creature_waypoints GROUP BY `kind`, `creatureOrPath`') ?: [] as $r)
            $ids[] = WaypointPathList::encodeId((int)$r['kind'], -(int)$r['creatureOrPath']);

        sort($ids);

        return $ids;
    }
    // aowow - custom end
}

?>
