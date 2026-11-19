Listview.templates.summongroupmember = {
    searchable: 1,                                          // rows come in creature_summon_groups order; it has no key, so the id is the row's place in it

    // a click on a member shows its summon point on the map, the way the Spawns tab does
    itemClick: function(row, e) {
        if (g_isLeftClick(e))
            MapViewer.show({ link: row.map });
    },

    isItemClickable: function(row) {
        return !!row.map;
    },

    npcName: function(t) {
        var entry = g_npcs ? g_npcs[t.npc] : null,
            name  = entry ? entry['name_' + Locale.getName()] : null;

        return name || ('#' + t.npc);
    },

    columns: [
        {
            id: 'npc',
            name: LANG.fisummongroup.npc,
            type: 'text',
            align: 'left',
            compute: function(t, td) {
                var a = $WH.ce('a');
                a.style.fontFamily = 'Verdana, sans-serif';
                a.href = '?npc=' + t.npc;
                $WH.ae(a, $WH.ct(Listview.templates.summongroupmember.npcName(t)));
                $WH.ae(td, a);
            },
            getVisibleText: function(t) {
                return Listview.templates.summongroupmember.npcName(t);
            },
            sortFunc: function(a, b, col) {
                return $WH.strcmp(this.getVisibleText(a), this.getVisibleText(b));
            }
        },
        {
            id: 'location',
            name: LANG.fispawn.location,
            type: 'text',
            width: '20%',
            align: 'left',
            compute: function(t, td) {
                var d = $WH.ce('div');
                d.innerHTML = Markup.toHtml(t.location || '', { allow: Markup.CLASS_STAFF });
                $WH.ae(td, d);
            },
            getVisibleText: function(t) {
                return (t.zone && g_zones[t.zone]) || (t.location || '').replace(/\[[^\]]*\]/g, '');
            },
            sortFunc: function(a, b, col) {
                return $WH.strcmp(this.getVisibleText(a), this.getVisibleText(b));
            }
        },
        {
            id: 'position',
            name: LANG.fispawn.position,
            type: 'text',
            width: '18%',
            compute: function(t, td) {
                td.style.fontSize = '11px';
                td.style.whiteSpace = 'nowrap';
                $WH.ae(td, $WH.ct(t.position));
            },
            getVisibleText: function(t) {
                return t.position;
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
            width: '10%',
            compute: function(t, td) {
                if (t.summonTime)
                    $WH.ae(td, $WH.ct(g_formatTimeElapsed(t.summonTime / 1000)));
            },
            getVisibleText: function(t) {
                return t.summonTime ? g_formatTimeElapsed(t.summonTime / 1000) : '';
            },
            sortFunc: function(a, b, col) {
                return a.summonTime - b.summonTime;
            }
        },
        {
            id: 'comment',
            name: LANG.fisummongroup.comment,
            type: 'text',
            align: 'left',
            compute: function(t, td) {
                td.style.fontSize = '11px';
                $WH.ae(td, $WH.ct(t.comment || ''));
            },
            getVisibleText: function(t) {
                return t.comment || '';
            }
        }
    ]
}
