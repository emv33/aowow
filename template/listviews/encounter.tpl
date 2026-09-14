Listview.templates.encounter = {
    sort: [1],
    searchable: 1,
    filtrable: 1,

    columns: [
        {
            id: 'id',
            name: 'ID',
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
            id: 'name',
            name: LANG.name,
            type: 'text',
            align: 'left',
            value: 'name',
            compute: function(enc, td, tr) {
                var a = $WH.ce('a');
                a.href = this.getItemLink(enc);

                $WH.ae(a, $WH.ct(enc.name));
                $WH.ae(td, a);

                if (enc.lastboss) {
                    var s = $WH.ce('span');
                    s.className = 'q2';
                    $WH.ae(s, $WH.ct(' – ' + LANG.encounter_lastboss));
                    $WH.ae(td, s);
                }
            },
            getVisibleText: function(enc) {
                return enc.name;
            }
        },
        {
            id: 'instance',
            name: LANG.fiencounter.instance,
            type: 'text',
            width: '25%',
            compute: function(enc, td) {
                if (!enc.area || !g_zones[enc.area])
                    return -1;

                var a = $WH.ce('a');
                a.className = 'q1';
                a.href = '?zone=' + enc.area;
                $WH.ae(a, $WH.ct(g_zones[enc.area]));
                $WH.ae(td, a);
            },
            getVisibleText: function(enc) {
                return (enc.area && g_zones[enc.area]) ? g_zones[enc.area] : '';
            },
            sortFunc: function(a, b, col) {
                return $WH.strcmp(this.getVisibleText(a), this.getVisibleText(b));
            }
        },
        {
            id: 'credit',
            name: LANG.fiencounter.credit,
            type: 'text',
            width: '25%',
            compute: function(enc, td) {
                if (!enc.creditentry)
                    return -1;

                var isSpell = enc.credittype == 1,
                    lookup  = isSpell ? g_spells : g_npcs,
                    nameCol = 'name_' + Locale.getName(),
                    entry   = lookup[enc.creditentry];

                if (entry && entry[nameCol]) {
                    var a = $WH.ce('a');
                    a.className = isSpell ? 'q' : 'q1';
                    a.href = (isSpell ? '?spell=' : '?npc=') + enc.creditentry;
                    $WH.ae(a, $WH.ct(entry[nameCol]));
                    $WH.ae(td, a);
                }
                else
                    $WH.ae(td, $WH.ct('#' + enc.creditentry));
            },
            getVisibleText: function(enc) {
                var nameCol = 'name_' + Locale.getName(),
                    lookup  = enc.credittype == 1 ? g_spells : g_npcs,
                    entry   = enc.creditentry ? lookup[enc.creditentry] : null;

                return (entry && entry[nameCol]) ? entry[nameCol] : '';
            },
            sortFunc: function(a, b, col) {
                return $WH.strcmp(this.getVisibleText(a), this.getVisibleText(b));
            }
        },
        {
            id: 'order',
            name: LANG.fiencounter.order,
            type: 'num',
            width: '8%',
            value: 'order'
        }
    ],
    getItemLink: function(enc) {
        return '?encounter=' + enc.id;
    },
    onBeforeCreate : function() {
        // hide duplicate id col
        if (this.debug || g_user?.debug) {
            let colId = this.columns.findIndex(x => x.id == 'id');
            this.visibility = this.visibility.filter(x => x != colId);
        }
    }
}
