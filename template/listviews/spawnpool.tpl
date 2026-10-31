Listview.templates.spawnpool = {
    sort: [1],
    searchable: 1,

    columns: [
        {
            id: 'id',
            name: 'ID',
            width: '7%',
            value: 'id'
        },
        {
            id: 'name',
            name: LANG.spawnpool.name,
            type: 'text',
            align: 'left',
            value: 'name',
            compute: function(t, td) {
                var a = $WH.ce('a');
                a.style.fontFamily = 'Verdana, sans-serif';
                a.href = this.getItemLink(t);

                $WH.ae(a, $WH.ct(t.name || $WH.sprintf(LANG.spawnpool.unnamed, t.id)));
                $WH.ae(td, a);
            },
            getVisibleText: function(t) {
                return t.name || $WH.sprintf(LANG.spawnpool.unnamed, t.id);
            },
            sortFunc: function(a, b, col) {
                return $WH.strcmp(a.name || '', b.name || '') || $WH.strcmp(a.id, b.id);
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
            width: '12%',
            compute: function(t, td) {
                return (t.active || '?') + ' / ' + t.total;
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
            width: '9%',
            value: 'mine'
        },
        {
            id: 'members',
            name: LANG.spawnpool.members,
            type: 'text',
            width: '18%',
            compute: function(t, td) {
                return Listview.templates.spawnpool.membersText(t);
            },
            getVisibleText: function(t) {
                return Listview.templates.spawnpool.membersText(t);
            },
            sortFunc: function(a, b, col) {
                return $WH.strcmp(a.total, b.total);
            }
        },
        {
            id: 'respawn',
            name: LANG.spawnpool.respawn,
            tooltip: LANG.spawnpool.respawntip,
            width: '14%',
            compute: function(t, td) {
                return Listview.templates.spawnpool.respawnText(t);
            },
            getVisibleText: function(t) {
                return Listview.templates.spawnpool.respawnText(t);
            },
            sortFunc: function(a, b, col) {
                return $WH.strcmp(a.respawn ? a.respawn[0] : -1, b.respawn ? b.respawn[0] : -1);
            }
        }
    ],

    membersText: function(t) {
        var parts = [];
        if (t.creatures)
            parts.push(t.creatures + ' ' + LANG.types[1][3]);
        if (t.objects)
            parts.push(t.objects + ' ' + LANG.types[2][3]);
        if (t.pools)
            parts.push(t.pools + ' ' + LANG.spawnpool.pools);

        return parts.join(LANG.comma);
    },

    respawnText: function(t) {
        if (!t.respawn)
            return '';

        if (t.respawn[0] == t.respawn[1])
            return g_formatTimeElapsed(t.respawn[0]);

        return g_formatTimeElapsed(t.respawn[0]) + ' - ' + g_formatTimeElapsed(t.respawn[1]);
    },

    getItemLink: function(t) {
        return '?spawnpool=' + t.id;
    },

    onBeforeCreate: function() {
        // hide the template's own id col when the debug id col is shown
        if (this.debug || g_user?.debug) {
            let colId = this.columns.findIndex(x => x.id == 'id');
            this.visibility = this.visibility.filter(x => x != colId);
        }
    }
}
