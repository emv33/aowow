Listview.templates.spawnpool = {
    sort: [-4, 1],
    searchable: 1,

    columns: [
        {
            id: 'id',
            name: 'ID',
            width: '8%',
            value: 'id'
        },
        {
            id: 'name',
            name: LANG.spawnpool.name,
            type: 'text',
            align: 'left',
            compute: function(t, td) {
                $WH.ae(td, $WH.ct(this.getVisibleText(t)));
            },
            getVisibleText: function(t) {
                return t.name || $WH.sprintf(LANG.spawnpool.unnamed, t.id);
            },
            sortFunc: function(a, b, col) {
                return $WH.strcmp(this.getVisibleText(a), this.getVisibleText(b));
            }
        },
        {
            id: 'location',
            name: LANG.location,
            type: 'text',
            compute: function(t, td) {
                return Listview.funcBox.location(t, td);
            },
            getVisibleText: function(t) {
                return Listview.funcBox.arrayText(t.location, g_zones);
            },
            sortFunc: function(a, b, col) {
                return Listview.funcBox.assocArrCmp(a.location, b.location, g_zones);
            }
        },
        {
            id: 'active',
            name: LANG.spawnpool.active,
            tooltip: LANG.spawnpool.activetip,
            width: '14%',
            compute: function(t, td) {
                $WH.ae(td, $WH.ct(this.getVisibleText(t)));
            },
            getVisibleText: function(t) {
                return (t.active || '?') + ' / ' + t.total;
            },
            sortFunc: function(a, b, col) {
                return $WH.strcmp(a.active, b.active) || $WH.strcmp(a.total, b.total);
            }
        },
        {
            id: 'mine',
            name: LANG.spawnpool.mine,
            tooltip: LANG.spawnpool.minetip,
            width: '12%',
            value: 'mine'
        },
        {
            id: 'respawn',
            name: LANG.spawnpool.respawn,
            tooltip: LANG.spawnpool.respawntip,
            width: '14%',
            compute: function(t, td) {
                $WH.ae(td, $WH.ct(this.getVisibleText(t)));
            },
            getVisibleText: function(t) {
                if (t.respawn[0] == t.respawn[1])
                    return g_formatTimeElapsed(t.respawn[0]);

                return g_formatTimeElapsed(t.respawn[0]) + ' - ' + g_formatTimeElapsed(t.respawn[1]);
            },
            sortFunc: function(a, b, col) {
                return $WH.strcmp(a.respawn[0], b.respawn[0]) || $WH.strcmp(a.respawn[1], b.respawn[1]);
            }
        }
    ],
    onBeforeCreate: function() {
        // hide the template's own id col when the debug id col is shown
        if (this.debug || g_user?.debug) {
            let colId = this.columns.findIndex(x => x.id == 'id');
            this.visibility = this.visibility.filter(x => x != colId);
        }
    }
}
