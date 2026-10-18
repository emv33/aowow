Listview.templates.condition = {
    sort: [2],
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
                // inline search matches against this, so it has to carry the names the cell displays
                var nameCol = 'name_' + Locale.getName(),
                    parts   = [];

                var resolve = function(lookupName, id, inlineName) {
                    var lookup = lookupName ? window[lookupName] : null,
                        entry  = lookup && id ? lookup[id] : null,
                        name   = (entry && entry[nameCol]) || inlineName;

                    if (name)
                        parts.push(name);
                };

                resolve(cnd.grouplookup, cnd.group, cnd.groupname);
                resolve(cnd.entrylookup, cnd.entry, cnd.entryname);

                return parts.join(' ') + ' ' + cnd.group + ' ' + cnd.entry + ' ' + cnd.srcid;
            },
            sortFunc: function(a, b, col) {
                return (a.group - b.group) || (a.entry - b.entry);
            }
        },
        {
            id: 'nconditions',
            name: LANG.ficondition.count,
            type: 'text',
            width: '28%',
            align: 'left',
            compute: function(cnd, td) {
                var nameCol = 'name_' + Locale.getName();

                // the entities the conditions refer to; a source that names none shows how many it has
                if (!cnd.targets || !cnd.targets.length) {
                    $WH.ae(td, $WH.ct(cnd.ncondtext));
                    return;
                }

                for (var i = 0; i < cnd.targets.length; i++) {
                    if (!cnd.targets[i][0]) {                   // plain text, nothing to link (player level)
                        if (i)
                            $WH.ae(td, $WH.ct(LANG.comma));

                        $WH.ae(td, $WH.ct(cnd.targets[i][3]));
                        continue;
                    }

                    var t      = cnd.targets[i],
                        lookup = window[t[0]],
                        entry  = lookup && lookup[t[2]],
                        a      = $WH.ce('a');

                    a.className = 'q1';
                    a.href = '?' + t[1] + '=' + t[2];
                    $WH.ae(a, $WH.ct((entry && entry[nameCol]) || ('#' + t[2])));

                    if (i)
                        $WH.ae(td, $WH.ct(LANG.comma));

                    if (entry && entry.icon) {
                        var icon = Icon.create(entry.icon, 0, null, a.href, null, null, null, null, true);
                        $WH.ae(td, icon);
                        $WH.ae(td, $WH.ct(' '));
                    }

                    $WH.ae(td, a);

                    if (t[3])                                   // e.g. the rank a skill is needed at
                        $WH.ae(td, $WH.ct(' (' + t[3] + ')'));
                }

                if (cnd.moretargets)
                    $WH.ae(td, $WH.ct(' ' + cnd.moretargets));
            },
            getVisibleText: function(cnd) {
                var nameCol = 'name_' + Locale.getName(),
                    parts   = [];

                (cnd.targets || []).forEach(function(t) {
                    var lookup = t[0] ? window[t[0]] : null,
                        entry  = lookup && lookup[t[2]];

                    if (!t[0])
                        parts.push(t[3]);
                    else if (entry && entry[nameCol])
                        parts.push(entry[nameCol]);
                });

                return parts.join(' ');
            },
            sortFunc: function(a, b, col) {
                return a.nconditions - b.nconditions;
            }
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
        return '?condition=' + encodeURIComponent(cnd.id);
    }
}
