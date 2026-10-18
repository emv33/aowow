Listview.templates.teleport = {
    sort: [1],
    searchable: 1,

    columns: [
        {
            id: 'name',
            name: LANG.fiteleport.name,
            type: 'text',
            align: 'left',
            value: 'name',
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
                // a point in no zone is named by its map instead
                if (!t.location) {
                    $WH.ae(td, $WH.ct(t.mapname || $WH.sprintf(LANG.teleport_map, t.map)));
                    return;
                }

                return Listview.funcBox.location(t, td);
            },
            getVisibleText: function(t) {
                return t.location ? Listview.funcBox.arrayText(t.location, g_zones) : (t.mapname || ('#' + t.map));
            },
            sortFunc: function(a, b, col) {
                return $WH.strcmp(this.getVisibleText(a), this.getVisibleText(b));
            }
        },
        {
            id: 'pos',
            name: LANG.fiteleport.position,
            type: 'text',
            align: 'left',
            width: '20%',
            compute: function(t, td) {
                if (!t.posx && !t.posy)
                    return -1;

                $WH.ae(td, $WH.ct(t.posx + ', ' + t.posy));
            },
            getVisibleText: function(t) {
                return t.posx + ', ' + t.posy;
            },
            sortFunc: function(a, b, col) {
                return (a.posx + a.posy) - (b.posx + b.posy);
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
            align: 'left',
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
