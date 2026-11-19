Listview.templates.vehicleseat = {
    sort: [1],
    searchable: 1,                                          // the id is the VehicleSeat.dbc id, the seat the index accessories use

    columns: [
        {
            id: 'id',
            name: LANG.fivehicleseat.seatId,
            type: 'num',
            width: '10%',
            value: 'id'
        },
        {
            id: 'seat',
            name: LANG.fivehicleseat.seat,
            type: 'num',
            width: '8%',
            value: 'seat'
        },
        {
            id: 'passenger',
            name: LANG.fivehicleseat.passenger,
            type: 'text',
            align: 'left',
            compute: function(t, td) {
                if (!t.passenger)
                    return -1;

                var d = $WH.ce('div');
                d.innerHTML = Markup.toHtml(t.passenger, { allow: Markup.CLASS_STAFF });
                $WH.ae(td, d);
            },
            getVisibleText: function(t) {
                return (t.passenger || '').replace(/\[npc=(\d+)\]/g, function(m, id) {
                    var n = g_npcs[id];
                    return n ? n['name_' + Locale.getName()] : '#' + id;
                }).replace(/\[[^\]]*\]/g, '');
            }
        },
        {
            id: 'facing',
            name: LANG.fivehicleseat.facing,
            tooltip: LANG.fivehicleseat.facingtip,
            type: 'text',
            width: '10%',
            value: 'facing'
        },
        {
            id: 'exit',
            name: LANG.fivehicleseat.exit,
            type: 'text',
            align: 'left',
            width: '35%',
            value: 'exit'
        }
    ]
}
