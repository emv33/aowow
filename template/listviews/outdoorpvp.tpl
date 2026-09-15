Listview.templates.outdoorpvp = {
    sort: [0],
    searchable: 1,

    columns: [
        {
            id: 'typeId',
            name: LANG.fioutdoorpvp.typeId,
            type: 'num',
            width: '12%',
            value: 'typeId'
        },
        {
            id: 'scriptName',
            name: LANG.fioutdoorpvp.scriptName,
            type: 'text',
            width: '30%',
            align: 'left',
            compute: function(t, td) {
                $WH.ae(td, $WH.ct(t.scriptName || '-'));
            },
            getVisibleText: function(t) {
                return t.scriptName;
            },
            sortFunc: function(a, b, col) {
                return $WH.strcmp(this.getVisibleText(a), this.getVisibleText(b));
            }
        },
        {
            id: 'comment',
            name: LANG.fioutdoorpvp.comment,
            type: 'text',
            align: 'left',
            compute: function(t, td) {
                $WH.ae(td, $WH.ct(t.comment || '-'));
            },
            getVisibleText: function(t) {
                return t.comment;
            },
            sortFunc: function(a, b, col) {
                return $WH.strcmp(this.getVisibleText(a), this.getVisibleText(b));
            }
        }
    ],
    clickable: false
}
