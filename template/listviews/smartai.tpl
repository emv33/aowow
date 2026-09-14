Listview.templates.smartai = {
    sort: [1],
    searchable: 1,

    columns: [
        {
            id: 'srctype',
            name: LANG.fismartai.srctype,
            type: 'text',
            width: '18%',
            align: 'left',
            compute: function(sai, td) {
                $WH.ae(td, $WH.ct(LANG.smartai_sourcetypes[sai.srctype] || ('#' + sai.srctype)));
            },
            getVisibleText: function(sai) {
                return LANG.smartai_sourcetypes[sai.srctype] || ('#' + sai.srctype);
            },
            sortFunc: function(a, b, col) {
                return $WH.strcmp(this.getVisibleText(a), this.getVisibleText(b));
            }
        },
        {
            id: 'entry',
            name: LANG.fismartai.entity,
            type: 'text',
            align: 'left',
            compute: function(sai, td) {
                var nameCol = 'name_' + Locale.getName(),
                    lookup  = sai.entrylookup ? window[sai.entrylookup] : null,
                    entry   = lookup ? lookup[sai.entry] : null;

                if (entry && entry[nameCol]) {
                    var a = $WH.ce('a');
                    a.className = 'q1';
                    a.href = '?' + sai.entryurl + '=' + sai.entry;
                    $WH.ae(a, $WH.ct(entry[nameCol]));
                    $WH.ae(td, a);
                }
                else if (sai.entry < 0)
                    $WH.ae(td, $WH.ct($WH.sprintf(LANG.smartai_guid, -sai.entry)));
                else
                    $WH.ae(td, $WH.ct('#' + sai.entry));
            },
            getVisibleText: function(sai) {
                var nameCol = 'name_' + Locale.getName(),
                    lookup  = sai.entrylookup ? window[sai.entrylookup] : null,
                    entry   = lookup ? lookup[sai.entry] : null;

                return (entry && entry[nameCol]) ? entry[nameCol] : String(sai.entry);
            },
            sortFunc: function(a, b, col) {
                return $WH.strcmp(this.getVisibleText(a), this.getVisibleText(b));
            }
        },
        {
            id: 'nrows',
            name: LANG.fismartai.count,
            type: 'num',
            width: '8%',
            value: 'nrows'
        },
        {
            id: 'eventtypes',
            name: LANG.fismartai.events,
            type: 'text',
            width: '27%',
            compute: function(sai, td) {
                if (!sai.eventtypes || !sai.eventtypes.length)
                    return -1;

                $WH.ae(td, $WH.ct(sai.eventtypes.join(LANG.comma)));
            },
            getVisibleText: function(sai) {
                return (sai.eventtypes || []).join(' ');
            },
            sortFunc: function(a, b, col) {
                return (a.eventtypes ? a.eventtypes.length : 0) - (b.eventtypes ? b.eventtypes.length : 0);
            }
        },
        {
            id: 'actiontypes',
            name: LANG.fismartai.actions,
            type: 'text',
            width: '27%',
            compute: function(sai, td) {
                if (!sai.actiontypes || !sai.actiontypes.length)
                    return -1;

                $WH.ae(td, $WH.ct(sai.actiontypes.join(LANG.comma)));
            },
            getVisibleText: function(sai) {
                return (sai.actiontypes || []).join(' ');
            },
            sortFunc: function(a, b, col) {
                return (a.actiontypes ? a.actiontypes.length : 0) - (b.actiontypes ? b.actiontypes.length : 0);
            }
        }
    ],
    getItemLink: function(sai) {
        return sai.entryurl && sai.entry > 0 ? ('?' + sai.entryurl + '=' + sai.entry) : 'javascript:;';
    }
}
