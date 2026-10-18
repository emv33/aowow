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
