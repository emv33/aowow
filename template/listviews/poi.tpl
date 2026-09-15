Listview.templates.poi = {
    sort: [0],
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
            name: LANG.fipoi.name,
            type: 'text',
            align: 'left',
            compute: function(t, td) {
                $WH.ae(td, $WH.ct(t.name || ('#' + t.id)));
            },
            getVisibleText: function(t) {
                return t.name || ('#' + t.id);
            },
            sortFunc: function(a, b, col) {
                return $WH.strcmp(this.getVisibleText(a), this.getVisibleText(b));
            }
        },
        {
            id: 'zone',
            name: LANG.fipoi.zone,
            type: 'text',
            width: '20%',
            align: 'left',
            compute: function(t, td) {
                if (!t.zone) {
                    $WH.ae(td, $WH.ct('-'));
                    return;
                }

                var a = $WH.ce('a');
                a.className = 'q1';
                a.href = t.maplink || ('?maps=' + t.zone);
                $WH.ae(a, $WH.ct((g_zones && g_zones[t.zone]) ? g_zones[t.zone] : ('#' + t.zone)));
                $WH.ae(td, a);
            },
            getVisibleText: function(t) {
                return (g_zones && g_zones[t.zone]) ? g_zones[t.zone] : (t.zone ? ('#' + t.zone) : '');
            },
            sortFunc: function(a, b, col) {
                return $WH.strcmp(this.getVisibleText(a), this.getVisibleText(b));
            }
        },
        {
            id: 'pos',
            name: LANG.fipoi.position,
            type: 'text',
            width: '16%',
            compute: function(t, td) {
                $WH.ae(td, $WH.ct((t.x || t.y) ? (t.x + ', ' + t.y) : '-'));
            },
            getVisibleText: function(t) {
                return t.x + ', ' + t.y;
            },
            sortFunc: function(a, b, col) {
                return (a.x - b.x) || (a.y - b.y);
            }
        },
        {
            id: 'icon',
            name: LANG.fipoi.icon,
            type: 'num',
            width: '12%',
            value: 'icon',
            compute: function(t, td) {
                if (!t.icon) {
                    $WH.ae(td, $WH.ct('-'));
                    return;
                }

                // the client resolves this id to a texture through hardcoded UI logic, not
                // through any DBC/DB table this site has access to - a generic pin glyph marks
                // it as an icon reference rather than claiming to render the actual graphic
                var sp = $WH.ce('span');
                sp.className = 'mapper-pin mapper-pin-1';
                $WH.ae(sp, $WH.ct(String(t.icon)));
                $WH.ae(td, sp);
            },
            getVisibleText: function(t) {
                return t.icon;
            }
        }
    ],
    getItemLink: function(t) {
        return t.maplink || 'javascript:;';
    },
    onBeforeCreate: function() {
        // hide the template's own id col when the debug id col is shown
        if (this.debug || g_user?.debug) {
            let colId = this.columns.findIndex(x => x.id == 'id');
            this.visibility = this.visibility.filter(x => x != colId);
        }
    }
}
