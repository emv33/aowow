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
                var a = $WH.ce('a');
                a.style.fontFamily = 'Verdana, sans-serif';
                a.href = this.getItemLink(t);

                $WH.ae(a, $WH.ct(t.name || ('#' + t.id)));
                $WH.ae(td, a);
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
                    return -1;
                }

                var a = $WH.ce('a');
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
            align: 'left',
            width: '16%',
            compute: function(t, td) {
                if (!t.x && !t.y)
                    return -1;

                $WH.ae(td, $WH.ct(t.x + ', ' + t.y));
            },
            getVisibleText: function(t) {
                return t.x + ', ' + t.y;
            },
            sortFunc: function(a, b, col) {
                return (a.x + a.y) - (b.x + b.y);
            }
        }
    ],
    getItemLink: function(t) {
        return '?poi=' + t.id;
    },
    onBeforeCreate: function() {
        // hide the template's own id col when the debug id col is shown
        if (this.debug || g_user?.debug) {
            let colId = this.columns.findIndex(x => x.id == 'id');
            this.visibility = this.visibility.filter(x => x != colId);
        }
    }
}
