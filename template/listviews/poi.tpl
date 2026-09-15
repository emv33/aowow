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
            id: 'pos',
            name: LANG.fipoi.position,
            type: 'text',
            width: '22%',
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
                $WH.ae(td, $WH.ct(t.icon || '-'));
            },
            getVisibleText: function(t) {
                return t.icon;
            }
        }
    ],
    getItemLink: function(t) {
        return '?poi';
    }
}
