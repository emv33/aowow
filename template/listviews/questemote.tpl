Listview.templates.questemote = {
    sort: [0],
    searchable: 1,                                          // the id is the emote's place in the dialogue, so sorting by it restores the order

    columns: [
        {
            id: 'id',
            name: '#',
            type: 'num',
            width: '5%',
            value: 'id'
        },
        {
            id: 'stage',
            name: LANG.fiquestemote.stage,
            type: 'text',
            align: 'left',
            width: '30%',
            value: 'stage',
            sortFunc: function(a, b, col) {
                return a.id - b.id;
            }
        },
        {
            id: 'emote',
            name: LANG.fiquestemote.emote,
            type: 'text',
            align: 'left',
            compute: function(t, td) {
                var d = $WH.ce('div');
                d.innerHTML = Markup.toHtml(t.emote, { allow: Markup.CLASS_STAFF });
                $WH.ae(td, d);
            },
            getVisibleText: function(t) {
                return t.emote.replace(/\[emote=(-?\d+)\]/g, function(m, id) {
                    var e = window.g_emotes && g_emotes[id];
                    return e ? e['name_' + Locale.getName()] : '#' + id;
                });
            }
        },
        {
            id: 'delay',
            name: LANG.fiquestemote.delay,
            type: 'text',
            width: '15%',
            value: 'delayText',
            sortFunc: function(a, b, col) {
                return a.delay - b.delay;
            }
        }
    ]
}
