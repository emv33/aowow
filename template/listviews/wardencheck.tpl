Listview.templates.wardencheck = {
    searchable: 1,                                          // rows come in id order; the id column shows the check id

    // compute() runs with the listview as `this`, not the column, so the texts it shares with getVisibleText() live here
    categoryText: function(t) {
        return t.category >= 0 ? LANG.fiwarden.categories[t.category] : LANG.fiwarden.unsupported;
    },

    actionText: function(t) {
        return t.action || LANG.fiwarden.actionDefault;
    },

    columns: [
        {
            id: 'type',
            name: LANG.fiwarden.type,
            type: 'text',
            width: '14%',
            align: 'left',
            compute: function(t, td) {
                var a = $WH.ce('a');
                a.style.fontFamily = 'Verdana, sans-serif';
                a.href = this.getItemLink(t);
                $WH.ae(a, $WH.ct(t.typeName));
                $WH.ae(td, a);

                // a row WardenCheckMgr does not load is still listed, greyed out
                if (t.skipped)
                    td.className = 'q0';
            },
            getVisibleText: function(t) {
                return t.typeName;
            },
            sortFunc: function(a, b, col) {
                return $WH.strcmp(a.typeName, b.typeName);
            }
        },
        {
            id: 'category',
            name: LANG.fiwarden.category,
            type: 'text',
            width: '12%',
            compute: function(t, td) {
                $WH.ae(td, $WH.ct(Listview.templates.wardencheck.categoryText(t)));
            },
            getVisibleText: function(t) {
                return Listview.templates.wardencheck.categoryText(t);
            },
            sortFunc: function(a, b, col) {
                return $WH.strcmp(this.getVisibleText(a), this.getVisibleText(b));
            }
        },
        {
            id: 'target',
            name: LANG.fiwarden.target,
            type: 'text',
            width: '30%',
            align: 'left',
            compute: function(t, td) {
                if (!t.target)
                    return -1;

                // Lua snippets run long; the full code is on the check's own page
                var txt = t.target.length > 80 ? t.target.substr(0, 77) + '...' : t.target;
                td.style.fontFamily = 'monospace';
                $WH.ae(td, $WH.ct(txt));
            },
            getVisibleText: function(t) {
                return t.target;
            },
            sortFunc: function(a, b, col) {
                return $WH.strcmp(a.target, b.target);
            }
        },
        {
            id: 'comment',
            name: LANG.fiwarden.comment,
            type: 'text',
            align: 'left',
            compute: function(t, td) {
                if (!t.comment)
                    return -1;

                $WH.ae(td, $WH.ct(t.comment));
            },
            getVisibleText: function(t) {
                return t.comment;
            },
            sortFunc: function(a, b, col) {
                return $WH.strcmp(a.comment, b.comment);
            }
        },
        {
            id: 'action',
            name: LANG.fiwarden.action,
            type: 'text',
            width: '12%',
            compute: function(t, td) {
                $WH.ae(td, $WH.ct(Listview.templates.wardencheck.actionText(t)));
            },
            getVisibleText: function(t) {
                return Listview.templates.wardencheck.actionText(t);
            },
            sortFunc: function(a, b, col) {
                return $WH.strcmp(this.getVisibleText(a), this.getVisibleText(b));
            }
        }
    ],
    getItemLink: function(t) {
        return '?wardencheck=' + t.id;
    }
}
