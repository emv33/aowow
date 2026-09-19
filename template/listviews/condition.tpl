Listview.templates.condition = {
    sort: [1],
    searchable: 1,

    columns: [
        {
            id: 'srctype',
            name: LANG.ficondition.srctype,
            type: 'text',
            width: '18%',
            align: 'left',
            compute: function(cnd, td) {
                $WH.ae(td, $WH.ct(LANG.condition_sourcetypes[cnd.srctype] || ('#' + cnd.srctype)));
            },
            getVisibleText: function(cnd) {
                return LANG.condition_sourcetypes[cnd.srctype] || ('#' + cnd.srctype);
            },
            sortFunc: function(a, b, col) {
                return $WH.strcmp(this.getVisibleText(a), this.getVisibleText(b));
            }
        },
        {
            id: 'gates',
            name: LANG.ficondition.gates,
            type: 'text',
            align: 'left',
            compute: function(cnd, td) {
                var nameCol = 'name_' + Locale.getName(),
                    first   = true;

                // most sources resolve group/entry through a g_* lookup; a gossip menu has none of its
                // own (see GameText's owner column, which hands over the same pre-labelled name), so a
                // row may carry a ready-made name instead of a lookup to find one in
                var append = function(lookupName, urlPart, id, inlineName) {
                    if (!inlineName && (!lookupName || !id))
                        return;

                    var lookup = lookupName ? window[lookupName] : null,
                        entry  = lookup && id ? lookup[id] : null;

                    if (!first)
                        $WH.ae(td, $WH.ct(' \u2013 '));

                    var name = (entry && entry[nameCol]) || inlineName;

                    if (name) {
                        var a = $WH.ce('a');
                        a.className = 'q1';
                        a.href = '?' + urlPart + '=' + id;
                        $WH.ae(a, $WH.ct(name));
                        $WH.ae(td, a);
                    }
                    else
                        $WH.ae(td, $WH.ct('#' + id));

                    first = false;
                };

                append(cnd.grouplookup, cnd.groupurl, cnd.group, cnd.groupname);
                append(cnd.entrylookup, cnd.entryurl, cnd.entry, cnd.entryname);

                if (first) {
                    // nothing linkable - fall back to the raw source key
                    $WH.ae(td, $WH.ct(cnd.group + ' / ' + cnd.entry + (cnd.srcid ? ' / ' + cnd.srcid : '')));
                }
            },
            getVisibleText: function(cnd) {
                return cnd.group + ' ' + cnd.entry + ' ' + cnd.srcid;
            },
            sortFunc: function(a, b, col) {
                return (a.group - b.group) || (a.entry - b.entry);
            }
        },
        {
            id: 'nconditions',
            name: LANG.ficondition.count,
            type: 'num',
            width: '10%',
            value: 'nconditions'
        },
        {
            id: 'cndtypes',
            name: LANG.ficondition.types,
            type: 'text',
            width: '32%',
            compute: function(cnd, td) {
                if (!cnd.cndtypes || !cnd.cndtypes.length)
                    return -1;

                var names = cnd.cndtypes.map(function(t) {
                    return LANG.condition_types[t] || ('#' + t);
                });

                $WH.ae(td, $WH.ct(names.join(LANG.comma)));
            },
            getVisibleText: function(cnd) {
                return (cnd.cndtypes || []).map(function(t) { return LANG.condition_types[t] || t; }).join(' ');
            },
            sortFunc: function(a, b, col) {
                return (a.cndtypes ? a.cndtypes.length : 0) - (b.cndtypes ? b.cndtypes.length : 0);
            }
        }
    ],
    getItemLink: function(cnd) {
        return '?condition=' + encodeURIComponent(cnd.cid);
    }
}
