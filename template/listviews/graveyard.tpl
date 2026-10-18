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
            id: 'map',
            name: LANG.figraveyard.map,
            type: 'text',
            width: '14%',
            align: 'left',
            compute: function(t, td) {
                var label = t.mapName || ('#' + t.map);
                if (t.mapLink) {
                    var a = $WH.ce('a');
                    a.className = 'q1';
                    a.href = t.mapLink;
                    $WH.ae(a, $WH.ct(label));
                    $WH.ae(td, a);
                }
                else
                    $WH.ae(td, $WH.ct(label));
            },
            getVisibleText: function(t) {
                return t.mapName || ('#' + t.map);
            },
            sortFunc: function(a, b, col) {
                return $WH.strcmp(this.getVisibleText(a), this.getVisibleText(b));
            }
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
                // the side's emblem in front of its name, as the other listings show it; both sides get both
                var sides = [[1, 'icon-alliance', LANG.figraveyard.alliance], [2, 'icon-horde', LANG.figraveyard.horde]],
                    first = true;

                sides.forEach(function(s) {
                    if (!(t.faction & s[0]))
                        return;

                    if (!first)
                        $WH.ae(td, $WH.ct(LANG.comma));

                    $WH.ae(td, $WH.ce('span', { className: s[1] }, $WH.ct(s[2])));
                    first = false;
                });

                if (first)
                    $WH.ae(td, $WH.ct(LANG.figraveyard.neutral));
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
        return '?graveyard=' + t.id;
    },
    onBeforeCreate: function() {
        // hide the template's own id col when the debug id col is shown
        if (this.debug || g_user?.debug) {
            let colId = this.columns.findIndex(x => x.id == 'id');
            this.visibility = this.visibility.filter(x => x != colId);
        }
    }
}
