Listview.templates.achievementcriteria = {
    sort: [0],
    searchable: 1,

    columns: [
        {
            id: 'id',
            name: 'ID',
            width: '8%',
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
            name: LANG.achievements,
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
            type: 'num',
            width: '8%',
            value: 'type'
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
            id: 'value1',
            name: 'Asset',
            type: 'num',
            width: '10%',
            value: 'value1'
        },
        {
            id: 'value2',
            name: 'Quantity',
            type: 'num',
            width: '10%',
            value: 'value2'
        },
        {
            id: 'flags',
            name: 'Flags',
            type: 'num',
            width: '10%',
            value: 'flags'
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
