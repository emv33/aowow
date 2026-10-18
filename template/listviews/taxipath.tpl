Listview.templates.taxipath = {
    sort: [1],
    searchable: 1,

    columns: [
        {
            id: 'id',
            name: 'ID',
            type: 'num',
            width: '7%',
            value: 'id',
            compute: function(data, td) {
                if (data.id) {
                    let pre = $WH.ce('pre', { style: { display: 'inline', margin: '0' }}, $WH.ct(data.id));
                    $WH.clickToCopy(pre);
                    $WH.ae(td, pre);
                }
            }
        },
        {
            id: 'route',
            name: LANG.fitaxipath.route,
            type: 'text',
            align: 'left',
            compute: function(tp, td) {
                var a = $WH.ce('a');
                a.style.fontFamily = 'Verdana, sans-serif';
                a.href = this.getItemLink(tp);
                $WH.ae(a, $WH.ct(tp.from + ' → ' + tp.to));
                $WH.ae(td, a);
            },
            getVisibleText: function(tp) {
                return tp.from + ' ' + tp.to;
            }
        },
        {
            id: 'location',
            name: LANG.location,
            type: 'text',
            compute: function(tp, td) {
                return Listview.funcBox.location(tp, td);
            },
            getVisibleText: function(tp) {
                return Listview.funcBox.arrayText(tp.location, g_zones);
            },
            sortFunc: function(a, b, col) {
                return Listview.funcBox.assocArrCmp(a.location, b.location, g_zones);
            }
        },
        {
            id: 'flightmaster',
            name: LANG.fitaxipath.flightmaster,
            type: 'text',
            align: 'left',
            width: '20%',
            compute: function(tp, td) {
                if (!tp.flightmaster || !tp.flightmaster.length)
                    return -1;

                var nameCol = 'name_' + Locale.getName(),
                    first   = true;

                tp.flightmaster.forEach(function(id) {
                    var e = g_npcs[id];
                    if (!e || !e[nameCol])
                        return;

                    if (!first)
                        $WH.ae(td, $WH.ct(LANG.comma));

                    var a = $WH.ce('a');
                    a.href = '?npc=' + id;
                    $WH.ae(a, $WH.ct(e[nameCol]));
                    $WH.ae(td, a);
                    first = false;
                });

                if (first)
                    return -1;
            },
            getVisibleText: function(tp) {
                var nameCol = 'name_' + Locale.getName();
                return (tp.flightmaster || []).map(function(id) {
                    return g_npcs[id] ? g_npcs[id][nameCol] : '';
                }).join(' ');
            },
            sortFunc: function(a, b, col) {
                return $WH.strcmp(this.getVisibleText(a), this.getVisibleText(b));
            }
        },
        {
            id: 'triggers',
            name: LANG.fitaxipath.triggers,
            type: 'text',
            align: 'left',
            width: '25%',
            compute: function(tp, td) {
                var nameCol = 'name_' + Locale.getName();

                // the first few by name, the rest as a number; scripts without a creature or object to name are counted
                if (tp.startedby && tp.startedby.length) {
                    tp.startedby.forEach(function(s, i) {
                        var lookup = window[s[0]],
                            entry  = lookup && lookup[s[2]];

                        if (i)
                            $WH.ae(td, $WH.ct(LANG.comma));

                        var a = $WH.ce('a');
                        a.href = '?' + s[1] + '=' + s[2];
                        $WH.ae(a, $WH.ct((entry && entry[nameCol]) || ('#' + s[2])));

                        if (entry && entry.icon) {
                            $WH.ae(td, Icon.create(entry.icon, 0, null, a.href, null, null, null, null, true));
                            $WH.ae(td, $WH.ct(' '));
                        }

                        $WH.ae(td, a);
                    });

                    if (tp.startedmore)
                        $WH.ae(td, $WH.ct(' ' + tp.startedmore));

                    return;
                }

                if (tp.nscripts) {
                    $WH.ae(td, $WH.ct($WH.sprintf(LANG.taxipath_nscripts, tp.nscripts)));
                    return;
                }

                return -1;
            },
            getVisibleText: function(tp) {
                var nameCol = 'name_' + Locale.getName();

                return (tp.startedby || []).map(function(s) {
                    var lookup = window[s[0]];

                    return lookup && lookup[s[2]] ? lookup[s[2]][nameCol] : '';
                }).join(' ');
            },
            sortFunc: function(a, b, col) {
                return (a.nstarted || 0) - (b.nstarted || 0);
            }
        }
    ],
    getItemLink: function(tp) {
        return '?taxipath=' + tp.id;
    },
    onBeforeCreate : function() {
        // hide duplicate id col
        if (this.debug || g_user?.debug) {
            let colId = this.columns.findIndex(x => x.id == 'id');
            this.visibility = this.visibility.filter(x => x != colId);
        }
    }
}
