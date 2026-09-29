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
        },
        {
            id: 'z',
            name: LANG.fiteleport.z,
            type: 'text',
            width: '10%',
            align: 'right',
            value: 'posz'
        },
        {
            id: 'orientation',
            name: LANG.fiteleport.orientation,
            type: 'text',
            width: '15%',
            compute: function(t, td) {
                $WH.ae(td, $WH.ct(t.o + '° (' + t.odir + ')'));
            },
            getVisibleText: function(t) {
                return t.o + '° (' + t.odir + ')';
            },
            sortFunc: function(a, b, col) {
                return a.o - b.o;
            }
        }
    ],
    getItemLink: function(t) {
        return '?teleport=' + t.id;
    }
}
