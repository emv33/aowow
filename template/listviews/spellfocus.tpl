Listview.templates.spellfocus = {
    sort: [0],
    searchable: 1,

    columns: [
        {
            id: 'id',
            name: 'ID',
            width: '8%',
            value: 'id'
        },
        {
            id: 'name',
            name: LANG.fispellfocus.name,
            type: 'text',
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
            id: 'objects',
            name: LANG.fispellfocus.objects,
            type: 'text',
            width: '20%',
            align: 'left',
            compute: function(t, td) {
                if (!t.objlink) {
                    $WH.ae(td, $WH.ct('-'));
                    return;
                }

                var a = $WH.ce('a');
                a.className = 'q1';
                a.href = t.objlink;
                $WH.ae(a, $WH.ct(LANG.fispellfocus.viewObjects));
                $WH.ae(td, a);
            },
            getVisibleText: function(t) {
                return t.objlink ? LANG.fispellfocus.viewObjects : '';
            }
        }
    ],
    getItemLink: function(t) {
        return t.objlink || 'javascript:;';
    },
    onBeforeCreate: function() {
        // hide the template's own id col when the debug id col is shown
        if (this.debug || g_user?.debug) {
            let colId = this.columns.findIndex(x => x.id == 'id');
            this.visibility = this.visibility.filter(x => x != colId);
        }
    }
}
