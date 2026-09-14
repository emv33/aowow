Listview.templates.transport = {
    sort: [1],
    searchable: 1,

    columns: [
        {
            id: 'name',
            name: LANG.name,
            type: 'text',
            align: 'left',
            value: 'name',
            compute: function(tr_, td) {
                var a = $WH.ce('a');
                a.className = 'q1';
                a.href = '?object=' + tr_.id;

                $WH.ae(a, $WH.ct(tr_.name));
                $WH.ae(td, a);

                if (!tr_.spawned) {
                    var s = $WH.ce('span');
                    s.className = 'q10';
                    $WH.ae(s, $WH.ct(' – ' + LANG.transport_notspawned));
                    $WH.ae(td, s);
                }
            },
            getVisibleText: function(tr_) {
                return tr_.name;
            }
        },
        {
            id: 'type',
            name: LANG.fitransport.type,
            type: 'text',
            width: '15%',
            compute: function(tr_, td) {
                $WH.ae(td, $WH.ct(LANG.transport_types[tr_.type] || ('#' + tr_.type)));
            },
            getVisibleText: function(tr_) {
                return LANG.transport_types[tr_.type] || ('#' + tr_.type);
            },
            sortFunc: function(a, b, col) {
                return $WH.strcmp(this.getVisibleText(a), this.getVisibleText(b));
            }
        },
        {
            id: 'route',
            name: LANG.fitransport.route,
            type: 'text',
            width: '40%',
            compute: function(tr_, td) {
                if (!tr_.from && !tr_.to)
                    return -1;

                var append = function(name, areaId) {
                    if (areaId && g_zones[areaId]) {
                        var a = $WH.ce('a');
                        a.className = 'q1';
                        a.href = '?zone=' + areaId;
                        $WH.ae(a, $WH.ct(name || g_zones[areaId]));
                        $WH.ae(td, a);
                    }
                    else
                        $WH.ae(td, $WH.ct(name || '?'));
                };

                append(tr_.from, tr_.fromarea);
                $WH.ae(td, $WH.ct(' → '));
                append(tr_.to, tr_.toarea);
            },
            getVisibleText: function(tr_) {
                return (tr_.from || '') + ' ' + (tr_.to || '');
            },
            sortFunc: function(a, b, col) {
                return $WH.strcmp(this.getVisibleText(a), this.getVisibleText(b));
            }
        },
        {
            id: 'pathid',
            name: LANG.fitransport.path,
            type: 'num',
            width: '10%',
            value: 'pathid'
        }
    ],
    getItemLink: function(tr_) {
        return '?object=' + tr_.id;
    }
}
