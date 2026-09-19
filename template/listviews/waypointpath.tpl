Listview.templates.waypointpath = {
    sort: [4],                                              // 1-based index into columns[] below - 'zone'
    searchable: 1,
    filtrable: 1,

    columns: [
        {
            // a path's 'npc' is always exactly one entry (see WaypointPathList::getListviewData() -
            // it's a scalar, never a list), so this is always "NPC", never "NPCs"
            id: 'npc',
            name: LANG.waypointpath_npc,
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

                // empty for every path that applies to every spawn of the npc; set only for one
                // pinned to a specific spawn (creature_addon, or a SmartAI action keyed to a
                // negative entryorguid)
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
                return e ? (e[nameCol] || '') : '';
            },
            sortFunc: function(a, b, col) {
                return $WH.strcmp(this.getVisibleText(a), this.getVisibleText(b));
            }
        },
        {
            // hidden by default (see waypointpaths.php) since ?waypointpaths already folds this
            // into 'npc' above - an npc's own Waypoints tab hides 'npc' instead (own name is
            // redundant there) and shows this one instead, when it has more than one distinct guid
            id: 'guid',
            name: 'GUID',
            type: 'num',
            width: '10%',
            compute: function(wp, td) {
                if (!wp.guid)
                    return -1;

                $WH.ae(td, $WH.ct(wp.guid));
            },
            value: 'guid'
        },
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
            id: 'source',
            name: LANG.fiwaypointpath.viasmartai,
            type: 'text',
            width: '12%',
            compute: function(wp, td) {
                $WH.ae(td, $WH.ct(wp.viaSmartAI ? LANG.waypointpath_sourcesmartai : LANG.waypointpath_sourcedefault));
            },
            getVisibleText: function(wp) {
                return wp.viaSmartAI ? LANG.waypointpath_sourcesmartai : LANG.waypointpath_sourcedefault;
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
