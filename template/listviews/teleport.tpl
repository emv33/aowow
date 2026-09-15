Listview.templates.teleport = {
    sort: [1],
    searchable: 1,

    columns: [
        {
            id: 'name',
            name: LANG.fiteleport.name,
            type: 'text',
            align: 'left',
            value: 'name'
        },
        {
            id: 'zone',
            name: LANG.fiteleport.zone,
            type: 'text',
            width: '30%',
            align: 'left',
            compute: function(t, td) {
                if (!t.zone) {
                    $WH.ae(td, $WH.ct($WH.sprintf(LANG.teleport_map, t.map)));
                    return;
                }

                var zone = g_zones ? g_zones[t.zone] : null,
                    a    = $WH.ce('a');

                a.className = 'q1';
                a.href = '?zone=' + t.zone;
                $WH.ae(a, $WH.ct(zone || ('#' + t.zone)));
                $WH.ae(td, a);
            },
            getVisibleText: function(t) {
                return (t.zone && g_zones ? g_zones[t.zone] : null) || ('#' + (t.zone || t.map));
            },
            sortFunc: function(a, b, col) {
                return $WH.strcmp(this.getVisibleText(a), this.getVisibleText(b));
            }
        },
        {
            id: 'pos',
            name: LANG.fiteleport.position,
            type: 'text',
            width: '20%',
            compute: function(t, td) {
                $WH.ae(td, $WH.ct(t.posx || t.posy ? (t.posx + ', ' + t.posy) : '-'));
            },
            getVisibleText: function(t) {
                return t.posx + ', ' + t.posy;
            },
            sortFunc: function(a, b, col) {
                return (a.posx - b.posx) || (a.posy - b.posy);
            }
        }
    ],
    getItemLink: function(t) {
        return t.zone ? ('?zone=' + t.zone) : '?teleports';
    }
}
