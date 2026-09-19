<?php

namespace Aowow;

if (!defined('AOWOW_REVISION'))
    die('illegal access');


/*
 * Browser over the world DB graveyard tables.
 *
 * Cores that still ship a position table split the data over `game_graveyard` (where the
 * graveyard sits) and `graveyard_zone` / `game_graveyard_zone` (which zones resurrect there).
 * Cores without the position table keep everything in the link table: `Comment` carries the
 * name and the map is derived from the ghost zone.
 */
class GraveyardsBaseResponse extends TemplateResponse implements ICache
{
    use TrListPage, TrCache;

    protected  int    $type       = -2;    // no Type:: entry - no DBTypeList backs this ad hoc table read; shared sentinel with GraveyardBaseResponse
    protected  int    $cacheType  = CACHE_TYPE_LIST_PAGE;
    protected  string $template   = 'graveyards';
    protected  string $pageName   = 'graveyards';
    protected  int    $requiredUserGroup = U_GROUP_STAFF;

    protected ?int    $activeTab  = parent::TAB_DATABASE;
    protected  array  $breadcrumb = [0, 113];

    protected function generate() : void
    {
        $this->h1 = Util::ucFirst(Lang::graveyard('title'));


        /**************/
        /* Page Title */
        /**************/

        array_unshift($this->title, $this->h1);


        /****************/
        /* Main Content */
        /****************/

        $this->redButtons[BUTTON_WOWHEAD] = false;

        $this->lvTabs = new Tabs(['parent' => "\$\$WH.ge('tabs-generic')"]);
        $this->lvTabs->addListviewTab(new Listview(['data' => $this->buildListviewData()], 'graveyard', 'graveyard'));

        parent::generate();
    }

    private function buildListviewData() : array
    {
        $zoneTbl = self::hasTable('graveyard_zone')
            ? 'graveyard_zone'
            : (self::hasTable('game_graveyard_zone') ? 'game_graveyard_zone' : null);

        if (!$zoneTbl)
            return [];

        // [graveyardId => [zones => [zoneId => true], ally => bool, horde => bool, comment => string]]
        $links = [];
        foreach (DB::World()->selectAssoc('SELECT * FROM '.$zoneTbl) ?: [] as $r)
        {
            $lc = array_change_key_case($r, CASE_LOWER);
            $id = (int)($lc['id'] ?? 0);
            if (!$id)
                continue;

            $links[$id]['zones'][(int)($lc['ghostzone'] ?? $lc['ghost_zone'] ?? 0)] = true;

            // the link table stores the faction template id: 469 Alliance, 67 Horde, 0 any
            $faction = (int)($lc['faction'] ?? 0);
            if ($faction == 469)
                $links[$id]['ally'] = true;
            else if ($faction == 67)
                $links[$id]['horde'] = true;

            $comment = trim((string)($lc['comment'] ?? ''));
            if ($comment !== '')
                $links[$id]['comment'] = $comment;
        }

        // optional position table on cores that still ship it; its name and map win where present
        $positions = [];
        if (self::hasTable('game_graveyard'))
        {
            foreach (DB::World()->selectAssoc('SELECT * FROM game_graveyard') ?: [] as $r)
            {
                $lc = array_change_key_case($r, CASE_LOWER);
                $id = (int)($lc['id'] ?? 0);

                $positions[$id] = array(
                    'name' => trim((string)($lc['comment'] ?? $lc['name'] ?? '')),
                    'map'  => (int)($lc['map'] ?? $lc['mapid'] ?? $lc['map_id'] ?? 0)
                );
            }
        }

        $ids = array_keys($links);
        sort($ids);

        // the link table has no map column, so the map is taken from the first ghost zone
        $zoneIds = [];
        foreach ($links as $link)
            foreach (array_keys($link['zones']) as $z)
                $zoneIds[$z] = $z;

        $zoneIds   = array_values($zoneIds);
        $mapByZone = [];

        if ($zoneIds)
            foreach (DB::Aowow()->selectAssoc('SELECT `id` AS ARRAY_KEY, `mapId` FROM ::zones WHERE `id` IN %in', $zoneIds) ?: [] as $zId => $zRow)
                $mapByZone[(int)$zId] = (int)$zRow['mapId'];

        $rows = [];
        $mapIds = [];
        foreach ($ids as $id)
        {
            $link  = $links[$id];
            $zones = array_keys($link['zones']);
            sort($zones);

            $pos  = $positions[$id] ?? null;
            $name = $pos && $pos['name'] !== '' ? $pos['name'] : (string)($link['comment'] ?? '');
            $map  = $pos && $pos['map'] ? $pos['map'] : (isset($zones[0]) ? ($mapByZone[$zones[0]] ?? 0) : 0);

            $faction = 0;
            if (!empty($link['ally']))
                $faction |= 1;
            if (!empty($link['horde']))
                $faction |= 2;

            $mapIds[$map] = $map;

            $rows[] = array(
                'id'      => $id,
                'name'    => $name !== '' && $name[0] == '$' ? ' '.$name : $name,
                'map'     => $map,
                'mapLink' => isset($zones[0]) ? '?maps='.$zones[0] : '',
                'zones'   => $zones,
                'faction' => $faction
            );
        }

        // Map.dbc is written into the aowow DB by the zones setup step and names every map, so
        // the raw id only survives where the table is gone
        $mapNameByMap = [];
        if ($mapIds && DB::Aowow()->selectCell('SHOW TABLES LIKE %s', 'dbc_map'))
            foreach (DB::Aowow()->selectAssoc('SELECT `id` AS ARRAY_KEY, `name_loc'.Lang::getLocale()->value.'` AS "name" FROM dbc_map WHERE `id` IN %in', array_values($mapIds)) ?: [] as $mId => $mRow)
                $mapNameByMap[(int)$mId] = (string)$mRow['name'];

        $data = [];
        foreach ($rows as $row)
        {
            $mapName = $mapNameByMap[$row['map']] ?? '';

            if ($mapName === '')
            {
                $mapName = match ($row['map'])
                {
                    0   => Lang::maps('EasternKingdoms'),
                    1   => Lang::maps('Kalimdor'),
                    530 => Lang::maps('Outland'),
                    571 => Lang::maps('Northrend'),
                    default => ''
                };
            }

            $row['mapName'] = $mapName;
            $data[] = $row;
        }

        if ($zoneIds)
            $this->extendGlobalIds(Type::ZONE, ...$zoneIds);

        return $data;
    }

    private static function hasTable(string $tbl) : bool
    {
        static $known = [];

        return $known[$tbl] ??= (bool)DB::World()->selectCell('SHOW TABLES LIKE %s', $tbl);
    }

    protected function generateMetadata(bool $useArticle = true) : void
    {
        $this->metaTags[] = ['property' => 'og:title', 'content' => $this->h1];
        $this->metaTags[] = ['property' => 'og:type',  'content' => 'website'];

        array_unshift($this->metaTags, ['name' => 'keywords', 'content' => [$this->h1, ...Lang::meta('tags', 'generic')]]);

        $this->buildBasicMetadata(Lang::meta('description', 'genList', [$this->h1]));

        $this->buildLdJson();
    }
}

?>
