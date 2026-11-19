Listview.templates.accessrequirement = {
    searchable: 1,                                          // rows come per map and difficulty; the id is mapId:difficulty

    markupCell: function(td, str) {
        var d = $WH.ce('div');
        d.innerHTML = Markup.toHtml(str || '', { allow: Markup.CLASS_STAFF });
        $WH.ae(td, d);
    },

    plainText: function(str) {
        return (str || '').replace(/\[[^\]]*\]/g, '');
    },

    markupCol: function(id, name, width) {
        return {
            id: id,
            name: name,
            type: 'text',
            align: 'left',
            width: width,
            compute: function(t, td) {
                if (!t[id])
                    return -1;

                Listview.templates.accessrequirement.markupCell(td, t[id]);
            },
            getVisibleText: function(t) {
                return Listview.templates.accessrequirement.plainText(t[id]);
            },
            sortFunc: function(a, b, col) {
                return $WH.strcmp(this.getVisibleText(a), this.getVisibleText(b));
            }
        };
    },

    textCol: function(id, name, width) {
        return {
            id: id,
            name: name,
            type: 'text',
            align: 'left',
            width: width,
            compute: function(t, td) {
                td.style.fontSize = '11px';
                $WH.ae(td, $WH.ct(t[id] || ''));
            },
            getVisibleText: function(t) {
                return t[id] || '';
            }
        };
    }
};

Listview.templates.accessrequirement.columns = [
    Listview.templates.accessrequirement.textCol('map', LANG.fiaccessreq.map, '14%'),
    {
        id: 'mode',
        name: LANG.fiaccessreq.mode,
        type: 'text',
        width: '10%',
        value: 'mode',
        sortFunc: function(a, b, col) {
            return a.difficulty - b.difficulty;
        }
    },
    {
        id: 'level',
        name: LANG.fiaccessreq.level,
        type: 'text',
        width: '10%',
        value: 'level',
        sortFunc: function(a, b, col) {
            return a.levelMin - b.levelMin;
        }
    },
    Listview.templates.accessrequirement.markupCol('items',       LANG.fiaccessreq.items),
    Listview.templates.accessrequirement.markupCol('quest',       LANG.fiaccessreq.quest),
    Listview.templates.accessrequirement.markupCol('achievement', LANG.fiaccessreq.achievement),
    {
        id: 'itemLevel',
        name: LANG.fiaccessreq.itemLevel,
        tooltip: LANG.fiaccessreq.itemLeveltip,
        type: 'num',
        width: '8%',
        value: 'itemLevel'
    },
    Listview.templates.accessrequirement.textCol('failText', LANG.fiaccessreq.failText),
    Listview.templates.accessrequirement.textCol('comment',  LANG.fiaccessreq.comment)
];
