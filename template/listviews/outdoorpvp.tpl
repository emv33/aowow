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
                var a = $WH.ce('a');
                a.style.fontFamily = 'Verdana, sans-serif';
                a.href = this.getItemLink(t);

                $WH.ae(a, $WH.ct(t.scriptName || ('#' + t.typeId)));
                $WH.ae(td, a);
            },
            getVisibleText: function(t) {
                return t.scriptName || ('#' + t.typeId);
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
                if (!t.comment)
                    return -1;

                $WH.ae(td, $WH.ct(t.comment));
            },
            getVisibleText: function(t) {
                return t.comment;
            },
            sortFunc: function(a, b, col) {
                return $WH.strcmp(this.getVisibleText(a), this.getVisibleText(b));
            }
        }
    ],
    getItemLink: function(t) {
        return '?outdoorpvp=' + t.typeId;
    }
}
