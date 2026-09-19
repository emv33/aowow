Listview.templates.waypointpath = {
    sort: [1],
    searchable: 1,
    filtrable: 1,

    columns: [
        {
            id: 'id',
            name: 'ID',
            type: 'num',
            width: '7%',
            value: 'id',
            compute: function(data, td) {
                if (data.id) {
                    let pre = $WH.ce('pre', { style: { display: 'inline', margin: '0' }}, $WH.ct(data.id));
                    $WH.clickToCopy(pre);
                    $WH.ae(td, pre);
                }
            }
        },
        {
            id: 'zone',
            name: LANG.fiwaypointpath.foundin,
            type: 'text',
            width: '25%',
            compute: function(wp, td) {
                if (!wp.areaId || !g_zones[wp.areaId])
                    return -1;

                var a = $WH.ce('a');
                a.className = 'q1';
                a.href = '?zone=' + wp.areaId;
                $WH.ae(a, $WH.ct(g_zones[wp.areaId]));
                $WH.ae(td, a);
            },
            getVisibleText: function(wp) {
                return wp.areaId && g_zones[wp.areaId] ? g_zones[wp.areaId] : '';
            },
            sortFunc: function(a, b, col) {
                return $WH.strcmp(this.getVisibleText(a), this.getVisibleText(b));
            }
        },
        {
            id: 'numpoints',
            name: LANG.waypointpath_points,
            type: 'num',
            width: '10%',
            value: 'numpoints'
        },
        {
            id: 'npc',
            name: LANG.tab_npcs,
            type: 'text',
            width: '25%',
            compute: function(wp, td) {
                var nameCol = 'name_' + Locale.getName(),
                    e       = wp.npc && g_npcs[wp.npc];

                if (!e || !e[nameCol])
                    return -1;

                var a = $WH.ce('a');
                a.className = 'q1';
                a.href = '?npc=' + wp.npc;
                $WH.ae(a, $WH.ct(e[nameCol]));
                $WH.ae(td, a);

                if (wp.guid) {
                    var sm = $WH.ce('small');
                    sm.className = 'q0';
                    $WH.ae(sm, $WH.ct(' (GUID: ' + wp.guid + ')'));
                    $WH.ae(td, sm);
                }
            },
            getVisibleText: function(wp) {
                var nameCol = 'name_' + Locale.getName(),
                    e       = wp.npc && g_npcs[wp.npc];
                return (e ? (e[nameCol] || '') : '') + (wp.guid ? ' ' + wp.guid : '');
            },
            sortFunc: function(a, b, col) {
                return $WH.strcmp(this.getVisibleText(a), this.getVisibleText(b));
            }
        }
    ],
    getItemLink: function(wp) {
        return '?waypointpath=' + wp.id;
    },
    onBeforeCreate : function() {
        // hide duplicate id col
        if (this.debug || g_user?.debug) {
            let colId = this.columns.findIndex(x => x.id == 'id');
            this.visibility = this.visibility.filter(x => x != colId);
        }
    }
}
