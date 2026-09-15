Listview.templates.graveyard = {
    sort: [0],
    searchable: 1,

    columns: [
        {
            id: 'id',
            name: 'ID',
            width: '7%',
            value: 'id'
        },
        {
            id: 'name',
            name: LANG.figraveyard.name,
            type: 'text',
            width: '28%',
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
            id: 'map',
            name: LANG.figraveyard.map,
            type: 'num',
            width: '10%',
            value: 'map'
        },
        {
            id: 'zones',
            name: LANG.figraveyard.zones,
            type: 'text',
            align: 'left',
            compute: function(t, td) {
                if (!t.zones || !t.zones.length) {
                    $WH.ae(td, $WH.ct('-'));
                    return;
                }

                for (var i = 0; i < t.zones.length; i++) {
                    if (i)
                        $WH.ae(td, $WH.ct(', '));

                    var z     = t.zones[i],
                        entry = g_gatheredzones ? g_gatheredzones[z] : null,
                        name  = null,
                        expansion = 0;

                    if (entry) {
                        var nameCol = 'name_' + Locale.getName();
                        name = entry[nameCol] || entry.name || ('#' + z);
                        expansion = entry.expansion || 0;
                    }
                    else
                        name = (g_zones && g_zones[z]) ? g_zones[z] : ('#' + z);

                    var a = $WH.ce('a');
                    a.className = 'q1';
                    a.href = '?zone=' + z;
                    $WH.ae(a, $WH.ct(name));

                    if (expansion) {
                        var sp = $WH.ce('span');
                        sp.className = g_GetExpansionClassName(expansion);
                        $WH.ae(sp, a);
                        $WH.ae(td, sp);
                    }
                    else
                        $WH.ae(td, a);
                }
            },
            getVisibleText: function(t) {
                var out = [];
                for (var i = 0; i < (t.zones || []).length; i++) {
                    var z     = t.zones[i],
                        entry = g_gatheredzones ? g_gatheredzones[z] : null;

                    if (entry) {
                        var nameCol = 'name_' + Locale.getName();
                        out.push(entry[nameCol] || entry.name || ('#' + z));
                    }
                    else
                        out.push((g_zones && g_zones[z]) ? g_zones[z] : ('#' + z));
                }

                return out.join(', ');
            },
            sortFunc: function(a, b, col) {
                return $WH.strcmp(this.getVisibleText(a), this.getVisibleText(b));
            }
        },
        {
            id: 'faction',
            name: LANG.figraveyard.faction,
            type: 'text',
            width: '12%',
            compute: function(t, td) {
                var label = t.faction == 1 ? LANG.figraveyard.alliance : (t.faction == 2 ? LANG.figraveyard.horde : (t.faction == 3 ? LANG.figraveyard.both : LANG.figraveyard.neutral));
                $WH.ae(td, $WH.ct(label));
            },
            getVisibleText: function(t) {
                return t.faction == 1 ? LANG.figraveyard.alliance : (t.faction == 2 ? LANG.figraveyard.horde : (t.faction == 3 ? LANG.figraveyard.both : LANG.figraveyard.neutral));
            },
            sortFunc: function(a, b, col) {
                return $WH.strcmp(this.getVisibleText(a), this.getVisibleText(b));
            }
        }
    ],
    getItemLink: function(t) {
        return t.zones && t.zones.length ? ('?zone=' + t.zones[0]) : '?graveyards';
    },
    onBeforeCreate: function() {
        // hide the template's own id col when the debug id col is shown
        if (this.debug || g_user?.debug) {
            let colId = this.columns.findIndex(x => x.id == 'id');
            this.visibility = this.visibility.filter(x => x != colId);
        }
    }
}
