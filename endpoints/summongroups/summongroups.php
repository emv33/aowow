<?php

namespace Aowow;

if (!defined('AOWOW_REVISION'))
    die('illegal access');


/*
 * The summon groups in `creature_summon_groups` - the table SmartAction::ACTION_SUMMON_CREATURE_GROUP
 * (SmartAI action 107) hands a whole group of creatures to at once.
 *
 * Previously only readable one summoner at a time, from inside that summoner's own SmartAI script
 * text (or, for the handful of world-boss groups tied to a map rather than a creature/gameobject,
 * not readable anywhere at all) - this gives every group a row, and its own page, of its own.
 */
class SummongroupsBaseResponse extends TemplateResponse implements ICache
{
    use TrListPage, TrCache;

    protected  int    $type              = -16;    // no Type:: entry - no DBTypeList backs this ad hoc table read; shared sentinel with SummongroupBaseResponse
    protected  int    $cacheType         = CACHE_TYPE_LIST_PAGE;
    protected  string $template          = 'summongroups';
    protected  string $pageName          = 'summongroups';
    protected  int    $requiredUserGroup = U_GROUP_NONE;

    protected ?int    $activeTab         = parent::TAB_DATABASE;
    protected  array  $breadcrumb        = [0, 121];

    protected function generate() : void
    {
        $this->h1 = Util::ucFirst(Lang::summongroup('title'));


        /**************/
        /* Page Title */
        /**************/

        array_unshift($this->title, $this->h1);


        /****************/
        /* Main Content */
        /****************/

        $this->redButtons[BUTTON_WOWHEAD] = false;

        $this->lvTabs = new Tabs(['parent' => "\$\$WH.ge('tabs-generic')"]);
        $this->lvTabs->addListviewTab(new Listview(['data' => $this->buildListviewData()], 'summongroup', 'summongroup'));

        parent::generate();
    }

    private function buildListviewData() : array
    {
        if (!DB::World()->selectCell('SHOW TABLES LIKE %s', 'creature_summon_groups'))
            return [];

        $rows = DB::World()->selectAssoc(
           'SELECT `summonerId`, `summonerType`, `groupId`, `entry`, COUNT(*) AS "n", MIN(`summonType`) AS "summonType", MIN(`summonTime`) AS "summonTime"
            FROM creature_summon_groups
            GROUP BY `summonerId`, `summonerType`, `groupId`, `entry`
            ORDER BY `summonerType`, `summonerId`, `groupId`'
        ) ?: [];

        // fold the per-member rows into one row per (summonerId, summonerType, groupId)
        $groups = [];
        foreach ($rows as $r)
        {
            $sType = (int)$r['summonerType'];
            $sId   = (int)$r['summonerId'];
            $gId   = (int)$r['groupId'];
            $key   = $sType.':'.$sId.':'.$gId;

            $groups[$key]['summonerType'] ??= $sType;
            $groups[$key]['summonerId']   ??= $sId;
            $groups[$key]['groupId']      ??= $gId;
            $groups[$key]['summonType']   ??= (int)$r['summonType'];
            $groups[$key]['summonTime']   ??= (int)$r['summonTime'];
            $groups[$key]['members'][]     = [(int)$r['entry'], (int)$r['n']];
        }

        $jsg  = [];
        $data = [];
        foreach ($groups as $id => $g)
        {
            // an unknown summonType has no entity in it to look further into, so it falls back
            // to a plain '#n' rather than the bbcode-carrying UNK message infobox lines use
            $typeLabel = Lang::smartAI('summonTypes', $g['summonType']) ?? ('#'.$g['summonType']);

            $row = array(
                'id'              => $id,
                'group'           => $g['groupId'],
                'summonType'      => $g['summonType'],
                'summonTypeLabel' => $typeLabel,
                'summonTime'      => $g['summonTime'],
                'members'         => $g['members']
            );

            foreach ($g['members'] as [$entry, ])
                $jsg[Type::NPC][$entry] = $entry;

            if ($g['summonerType'] == SUMMONER_TYPE_CREATURE)
            {
                $row['npc'] = $g['summonerId'];
                $jsg[Type::NPC][$g['summonerId']] = $g['summonerId'];
            }
            else if ($g['summonerType'] == SUMMONER_TYPE_GAMEOBJECT)
            {
                $row['object'] = $g['summonerId'];
                $jsg[Type::OBJECT][$g['summonerId']] = $g['summonerId'];
            }
            else                                             // SUMMONER_TYPE_MAP - no entity to link to
                $row['mapName'] = self::mapName($g['summonerId']);

            $data[] = $row;
        }

        $this->extendGlobalData($jsg);

        return $data;
    }

    /** SUMMONER_TYPE_MAP has no owning entity - name the map instead, same fallback graveyards.php uses */
    private static function mapName(int $mapId) : string
    {
        if (DB::Aowow()->selectCell('SHOW TABLES LIKE %s', 'dbc_map'))
        {
            $name = DB::Aowow()->selectCell('SELECT `name_loc'.Lang::getLocale()->value.'` FROM dbc_map WHERE `id` = %i', $mapId);
            if ($name !== null && $name !== '')
                return (string)$name;
        }

        return match ($mapId)
        {
            0   => Lang::maps('EasternKingdoms'),
            1   => Lang::maps('Kalimdor'),
            530 => Lang::maps('Outland'),
            571 => Lang::maps('Northrend'),
            default => '#'.$mapId
        };
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
