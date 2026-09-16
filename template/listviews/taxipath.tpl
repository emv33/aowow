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
                a.href = this.getItemLink(tp);
                $WH.ae(a, $WH.ct(tp.from + ' → ' + tp.to));
                $WH.ae(td, a);
            },
            getVisibleText: function(tp) {
                return tp.from + ' ' + tp.to;
            }
        },
        {
            id: 'zones',
            name: LANG.fitaxipath.zones,
            type: 'text',
            width: '25%',
            compute: function(tp, td) {
                var part = function(areaId, name) {
                    if (areaId && g_zones[areaId]) {
                        var a = $WH.ce('a');
                        a.className = 'q1';
                        a.href = '?zone=' + areaId;
                        $WH.ae(a, $WH.ct(g_zones[areaId]));
                        $WH.ae(td, a);
                    }
                    else
                        $WH.ae(td, $WH.ct(name || '?'));
                };

                part(tp.fromarea, tp.from);
                $WH.ae(td, $WH.ct(' → '));
                part(tp.toarea, tp.to);
            },
            getVisibleText: function(tp) {
                return (g_zones[tp.fromarea] || '') + ' ' + (g_zones[tp.toarea] || '');
            },
            sortFunc: function(a, b, col) {
                return $WH.strcmp(this.getVisibleText(a), this.getVisibleText(b));
            }
        },
        {
            id: 'flightmaster',
            name: LANG.fitaxipath.flightmaster,
            type: 'text',
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
                    a.className = 'q1';
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
            width: '18%',
            compute: function(tp, td) {
                var buff = [];
                if (tp.nspells)  buff.push($WH.sprintf(LANG.taxipath_nspells,  tp.nspells));
                if (tp.nscripts) buff.push($WH.sprintf(LANG.taxipath_nscripts, tp.nscripts));
                if (tp.nobjects) buff.push($WH.sprintf(LANG.taxipath_nobjects, tp.nobjects));

                if (!buff.length)
                    return -1;

                $WH.ae(td, $WH.ct(buff.join(LANG.comma)));
            },
            getVisibleText: function(tp) {
                return '';
            },
            sortFunc: function(a, b, col) {
                return (a.nspells + a.nscripts + a.nobjects) - (b.nspells + b.nscripts + b.nobjects);
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
