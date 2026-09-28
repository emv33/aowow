Listview.templates.summongroup = {
    sort: [0],
    searchable: 1,

    columns: [
        {
            id: 'summoner',
            name: LANG.fisummongroup.summoner,
            type: 'text',
            width: '25%',
            align: 'left',
            compute: function(t, td) {
                if (t.mapName) {
                    $WH.ae(td, $WH.ct(t.mapName));
                    return;
                }

                var nameCol = 'name_' + Locale.getName(),
                    isNpc   = t.npc != null,
                    id      = isNpc ? t.npc : t.object,
                    lookup  = isNpc ? g_npcs : g_objects,
                    entry   = lookup ? lookup[id] : null,
                    a       = $WH.ce('a');

                a.className = 'q1';
                a.href = (isNpc ? '?npc=' : '?object=') + id;
                $WH.ae(a, $WH.ct((entry && entry[nameCol]) ? entry[nameCol] : ('#' + id)));
                $WH.ae(td, a);
            },
            getVisibleText: function(t) {
                if (t.mapName)
                    return t.mapName;

                var nameCol = 'name_' + Locale.getName(),
                    isNpc   = t.npc != null,
                    id      = isNpc ? t.npc : t.object,
                    lookup  = isNpc ? g_npcs : g_objects,
                    entry   = lookup ? lookup[id] : null;

                return (entry && entry[nameCol]) ? entry[nameCol] : ('#' + id);
            },
            sortFunc: function(a, b, col) {
                return $WH.strcmp(this.getVisibleText(a), this.getVisibleText(b));
            }
        },
        {
            id: 'group',
            name: LANG.fisummongroup.group,
            type: 'num',
            width: '8%',
            value: 'group'
        },
        {
            id: 'members',
            name: LANG.fisummongroup.members,
            type: 'text',
            compute: function(t, td) {
                var nameCol = 'name_' + Locale.getName();

                (t.members || []).forEach(function(m, i) {
                    if (i > 0)
                        $WH.ae(td, $WH.ct(LANG.comma));

                    var entry = g_npcs ? g_npcs[m[0]] : null,
                        a     = $WH.ce('a');

                    a.className = 'q1';
                    a.href = '?npc=' + m[0];
                    $WH.ae(td, $WH.ct(m[1] + 'x '));
                    $WH.ae(a, $WH.ct((entry && entry[nameCol]) ? entry[nameCol] : ('#' + m[0])));
                    $WH.ae(td, a);
                });
            },
            getVisibleText: function(t) {
                var nameCol = 'name_' + Locale.getName();

                return (t.members || []).map(function(m) {
                    var entry = g_npcs ? g_npcs[m[0]] : null;
                    return m[1] + 'x ' + ((entry && entry[nameCol]) ? entry[nameCol] : ('#' + m[0]));
                }).join(', ');
            }
        },
        {
            id: 'summonType',
            name: LANG.fisummongroup.summonType,
            type: 'text',
            width: '18%',
            value: 'summonTypeLabel',
            sortFunc: function(a, b, col) {
                return a.summonType - b.summonType;
            }
        },
        {
            id: 'summonTime',
            name: LANG.fisummongroup.summonTime,
            type: 'text',
            width: '12%',
            compute: function(t, td) {
                if (t.summonTime)
                    $WH.ae(td, $WH.ct(g_formatTimeElapsed(t.summonTime / 1000)));
            },
            sortFunc: function(a, b, col) {
                return a.summonTime - b.summonTime;
            }
        }
    ],
    getItemLink: function(t) {
        return '?summongroup=' + encodeURIComponent(t.id);
    }
}
