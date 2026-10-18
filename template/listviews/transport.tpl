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
                a.style.fontFamily = 'Verdana, sans-serif';
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
            align: 'left',
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
            align: 'left',
            width: '40%',
            compute: function(tr_, td) {
                if (!tr_.pathid)
                    return -1;

                // the path page exists exactly where the route resolves; a path without one is only a number
                if (!tr_.from && !tr_.to) {
                    $WH.ae(td, $WH.ct($WH.sprintf(LANG.transport_path, tr_.pathid)));
                    return;
                }

                var a = $WH.ce('a');
                a.href = '?taxipath=' + tr_.pathid;
                $WH.ae(a, $WH.ct((tr_.from || '?') + ' \u2192 ' + (tr_.to || '?')));
                $WH.ae(td, a);
            },
            getVisibleText: function(tr_) {
                if (!tr_.pathid)
                    return '';

                return (tr_.from || tr_.to) ? ((tr_.from || '') + ' ' + (tr_.to || '')) : $WH.sprintf(LANG.transport_path, tr_.pathid);
            },
            sortFunc: function(a, b, col) {
                return $WH.strcmp(this.getVisibleText(a), this.getVisibleText(b));
            }
        }
    ],
    getItemLink: function(tr_) {
        return '?object=' + tr_.id;
    }
}
