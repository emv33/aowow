Listview.templates.achievementcriteria = {
    sort: [0],
    searchable: 1,

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
            id: 'achievement',
            name: LANG.tab_achievements,
            type: 'text',
            align: 'left',
            compute: function(crt, td) {
                if (!crt.achievement)
                    return -1;

                var a = $WH.ce('a');
                a.className = 'q';
                a.href = this.getItemLink(crt);

                $WH.ae(a, $WH.ct(crt.achievementname || ('#' + crt.achievement)));
                $WH.ae(td, a);
            },
            getVisibleText: function(crt) {
                return crt.achievementname || ('#' + crt.achievement);
            },
            sortFunc: function(a, b, col) {
                return $WH.strcmp(this.getVisibleText(a), this.getVisibleText(b));
            }
        },
        {
            id: 'type',
            name: LANG.type,
            type: 'text',
            width: '16%',
            compute: function(crt, td) {
                var a = $WH.ce('a');
                a.className = 'q1';
                a.href = '?achievement-criteria&ty=' + crt.type;

                $WH.ae(a, $WH.ct(crt.typename));
                $WH.ae(td, a);
            },
            getVisibleText: function(crt) {
                return crt.typename;
            },
            sortFunc: function(a, b, col) {
                return $WH.strcmp(this.getVisibleText(a), this.getVisibleText(b));
            }
        },
        {
            id: 'name',
            name: LANG.name,
            type: 'text',
            align: 'left',
            value: 'name',
            compute: function(crt, td) {
                $WH.ae(td, $WH.ct(crt.name || ('#' + crt.id)));
            },
            getVisibleText: function(crt) {
                return crt.name || ('#' + crt.id);
            }
        },
        {
            id: 'asset',
            name: 'Asset',
            type: 'text',
            width: '14%',
            compute: function(crt, td) {
                if (!crt.asset)
                    return -1;

                var name = null;
                if (crt.assettype && window[crt.assettype]) {
                    var entry = window[crt.assettype][crt.asset];
                    if (entry)
                        name = entry['name_' + Locale.getName()] || entry.name;
                }

                if (crt.asseturl && name) {
                    var a = $WH.ce('a');
                    a.className = 'q1';
                    a.href = crt.asseturl;

                    $WH.ae(a, $WH.ct(name));
                    $WH.ae(td, a);
                }
                else
                    $WH.ae(td, $WH.ct('#' + crt.asset));
            },
            getVisibleText: function(crt) {
                if (!crt.asset)
                    return '';

                if (crt.assettype && window[crt.assettype]) {
                    var entry = window[crt.assettype][crt.asset];
                    if (entry)
                        return entry['name_' + Locale.getName()] || entry.name;
                }

                return '#' + crt.asset;
            },
            sortFunc: function(a, b, col) {
                return $WH.strcmp(this.getVisibleText(a), this.getVisibleText(b));
            }
        },
        {
            id: 'value2',
            name: 'Quantity',
            type: 'num',
            width: '9%',
            value: 'value2'
        },
        {
            id: 'flags',
            name: 'Flags',
            type: 'text',
            width: '16%',
            compute: function(crt, td) {
                if (crt.flagnames)
                    $WH.ae(td, $WH.ct(crt.flagnames));
                else
                    return -1;
            },
            getVisibleText: function(crt) {
                return crt.flagnames;
            },
            sortFunc: function(a, b, col) {
                return $WH.strcmp(this.getVisibleText(a), this.getVisibleText(b));
            }
        }
    ],
    getItemLink: function(crt) {
        return '?achievement=' + crt.achievement;
    },
    onBeforeCreate : function() {
        // hide duplicate id col
        if (this.debug || g_user?.debug) {
            let colId = this.columns.findIndex(x => x.id == 'id');
            this.visibility = this.visibility.filter(x => x != colId);
        }
    }
}
