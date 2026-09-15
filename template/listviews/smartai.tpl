// sai.linkid/url/lookup/name describe the row's own entity (creature/object/areatrigger); sai.owners
// (only ever set for a Timed action list row, which has no page of its own) lists every entity -
// same shape minus linkid - whose script calls that list
// not a column method: col.compute is invoked bound to the Listview instance (see listview.js
// createRow()), not the column, so a helper shared with getVisibleText has to live out here instead
function smartaiResolveName(url, id, lookup, name) {
    var nameCol = 'name_' + Locale.getName(),
        entry   = lookup ? (window[lookup] || {})[id] : null;

    return (entry && entry[nameCol]) ? entry[nameCol] : (name || null);
}

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
                var name = smartaiResolveName(sai.url, sai.linkid, sai.lookup, sai.name);

                if (name && sai.url) {
                    var a = $WH.ce('a');
                    a.className = 'q1';
                    a.href = '?' + sai.url + '=' + sai.linkid;
                    $WH.ae(a, $WH.ct(name));
                    $WH.ae(td, a);

                    if (sai.entry < 0)
                        $WH.ae(td, $WH.ct(' ' + $WH.sprintf(LANG.smartai_guid, -sai.entry)));
                }
                else if (sai.entry < 0)
                    $WH.ae(td, $WH.ct($WH.sprintf(LANG.smartai_guid, -sai.entry)));
                else
                    $WH.ae(td, $WH.ct('#' + sai.entry));

                if (sai.owners && sai.owners.length) {
                    $WH.ae(td, $WH.ct(' ('));
                    sai.owners.forEach(function(o, i) {
                        if (i > 0)
                            $WH.ae(td, $WH.ct(', '));

                        var a = $WH.ce('a');
                        a.className = 'q1';
                        a.href = '?' + o.url + '=' + o.id;
                        $WH.ae(a, $WH.ct(smartaiResolveName(o.url, o.id, o.lookup, o.name) || ('#' + o.id)));
                        $WH.ae(td, a);
                    });
                    $WH.ae(td, $WH.ct(')'));
                }
            },
            getVisibleText: function(sai) {
                var name = smartaiResolveName(sai.url, sai.linkid, sai.lookup, sai.name),
                    text = name ? (name + (sai.entry < 0 ? ' ' + $WH.sprintf(LANG.smartai_guid, -sai.entry) : ''))
                                : (sai.entry < 0 ? $WH.sprintf(LANG.smartai_guid, -sai.entry) : String(sai.entry));

                if (sai.owners && sai.owners.length)
                    text += ' (' + sai.owners.map(function(o) {
                        return smartaiResolveName(o.url, o.id, o.lookup, o.name) || ('#' + o.id);
                    }).join(', ') + ')';

                return text;
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
        return sai.url && sai.linkid ? ('?' + sai.url + '=' + sai.linkid) : 'javascript:;';
    }
}
