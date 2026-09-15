Listview.templates.factionchange = {
    sort: [0],
    searchable: 1,

    columns: [
        {
            id: 'type',
            name: LANG.fifactionchange.type,
            type: 'text',
            width: '14%',
            compute: function(t, td) {
                $WH.ae(td, $WH.ct(LANG.fifactionchange.types[t.type] || ('#' + t.type)));
            },
            getVisibleText: function(t) {
                return LANG.fifactionchange.types[t.type] || ('#' + t.type);
            },
            sortFunc: function(a, b, col) {
                return $WH.strcmp(this.getVisibleText(a), this.getVisibleText(b));
            }
        },
        {
            id: 'alliance',
            name: LANG.fifactionchange.alliance,
            type: 'text',
            width: '40%',
            align: 'left',
            compute: function(t, td) {
                if (!t.alliance) {
                    $WH.ae(td, $WH.ct('-'));
                    return;
                }

                var file = {3: 'item', 5: 'quest', 6: 'spell', 8: 'faction', 11: 'title'}[t.type],
                    nameCol = 'name_' + Locale.getName(),
                    lookup = {3: g_items, 5: g_quests, 6: g_spells, 8: g_factions, 11: g_titles}[t.type],
                    entry  = lookup[t.alliance],
                    a      = $WH.ce('a');

                a.className = 'q1';
                a.href = '?' + file + '=' + t.alliance;
                $WH.ae(a, $WH.ct((entry && entry[nameCol]) ? entry[nameCol] : ('#' + t.alliance)));

                if (entry && entry.icon)
                    $WH.ae(td, Icon.create(entry.icon, 0, null, a.href, null, null, null, null, true));

                $WH.ae(td, a);
            },
            getVisibleText: function(t) {
                var nameCol = 'name_' + Locale.getName(),
                    lookup = {3: g_items, 5: g_quests, 6: g_spells, 8: g_factions, 11: g_titles}[t.type],
                    entry  = t.alliance ? lookup[t.alliance] : null;

                return (entry && entry[nameCol]) ? entry[nameCol] : (t.alliance ? ('#' + t.alliance) : '');
            },
            sortFunc: function(a, b, col) {
                return $WH.strcmp(this.getVisibleText(a), this.getVisibleText(b));
            }
        },
        {
            id: 'horde',
            name: LANG.fifactionchange.horde,
            type: 'text',
            width: '40%',
            align: 'left',
            compute: function(t, td) {
                if (!t.horde) {
                    $WH.ae(td, $WH.ct('-'));
                    return;
                }

                var file = {3: 'item', 5: 'quest', 6: 'spell', 8: 'faction', 11: 'title'}[t.type],
                    nameCol = 'name_' + Locale.getName(),
                    lookup = {3: g_items, 5: g_quests, 6: g_spells, 8: g_factions, 11: g_titles}[t.type],
                    entry  = lookup[t.horde],
                    a      = $WH.ce('a');

                a.className = 'q1';
                a.href = '?' + file + '=' + t.horde;
                $WH.ae(a, $WH.ct((entry && entry[nameCol]) ? entry[nameCol] : ('#' + t.horde)));

                if (entry && entry.icon)
                    $WH.ae(td, Icon.create(entry.icon, 0, null, a.href, null, null, null, null, true));

                $WH.ae(td, a);
            },
            getVisibleText: function(t) {
                var nameCol = 'name_' + Locale.getName(),
                    lookup = {3: g_items, 5: g_quests, 6: g_spells, 8: g_factions, 11: g_titles}[t.type],
                    entry  = t.horde ? lookup[t.horde] : null;

                return (entry && entry[nameCol]) ? entry[nameCol] : (t.horde ? ('#' + t.horde) : '');
            },
            sortFunc: function(a, b, col) {
                return $WH.strcmp(this.getVisibleText(a), this.getVisibleText(b));
            }
        }
    ],
    clickable: false
}
