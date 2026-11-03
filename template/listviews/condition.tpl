Listview.templates.condition = {
    sort: [2],
    searchable: 1,

    // a link to an entity the way its own listings show it: item / spell / ... icon in front,
    // quests with the marker the game shows for them (yellow, or blue if repeatable)
    appendLink: function(td, lookupName, id, entry, href, name) {
        var a = $WH.ce('a');
        if (lookupName == 'g_items' && entry && entry.quality != null)
            a.className = 'q' + entry.quality;
        a.href = href;
        $WH.ae(a, $WH.ct(name));

        if (lookupName == 'g_quests') {
            a.className += ' icontiny tinyspecial';
            a.style.backgroundImage = 'url(' + Icon.url(entry && (entry.daily || entry.weekly) ? 'quest_start_daily' : 'quest_start', null, true) + ')';
        }
        else {
            // classes and races have no icon in their data, the client knows them by file name
            var icon = entry && entry.icon;
            if (lookupName == 'g_classes')
                icon = 'class_' + g_file_classes[id];
            else if (lookupName == 'g_races')
                icon = 'race_' + g_file_races[id] + '_' + g_file_genders[0];

            if (icon) {
                $WH.ae(td, Icon.create(icon, 0, null, href, null, null, null, null, true));
                $WH.ae(td, $WH.ct(' '));
            }
        }

        $WH.ae(td, a);
    },

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

                    if (name)
                        Listview.templates.condition.appendLink(td, lookupName, id, entry, '?' + urlPart + '=' + id, name);
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

                var targets = g_condition_sources[cnd.srctype] ? g_condition_sources[cnd.srctype][0] : [];

                for (var i = 0; i < cnd.targets.length; i++) {
                    var t = cnd.targets[i];

                    if (i)
                        $WH.ae(td, $WH.ct(LANG.comma));

                    if (t.t) {                                  // plain text (player level, target health)
                        $WH.ae(td, $WH.ct(t.t));
                        continue;
                    }

                    if (t.c) {                                  // a condition, worded like the detail page does
                        var span = $WH.ce('span');
                        Markup.printHtml(ConditionList.parseEntry(t.c, targets, t.g), span);
                        $WH.ae(td, span);
                        continue;
                    }

                    var lookup = window[t.l],
                        entry  = lookup && lookup[t.i];

                    Listview.templates.condition.appendLink(td, t.l, t.i, entry, '?' + t.u + '=' + t.i, (entry && entry[nameCol]) || ('#' + t.i));

                    // the rank a skill is needed at, or the standings of a faction
                    var note = t.l == 'g_factions' ? ConditionList.standings(t.n) : t.n;
                    if (note)
                        $WH.ae(td, $WH.ct(' (' + note + ')'));
                }

                if (cnd.moretargets)
                    $WH.ae(td, $WH.ct(' ' + cnd.moretargets));
            },
            getVisibleText: function(cnd) {
                var nameCol = 'name_' + Locale.getName(),
                    parts   = [];

                (cnd.targets || []).forEach(function(t) {
                    var lookup = t.l ? window[t.l] : null,
                        entry  = lookup && lookup[t.i];

                    if (t.t)
                        parts.push(t.t);
                    else if (t.c)
                        parts.push(Markup.removeTags(ConditionList.parseEntry(t.c, [], t.g)));
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
            align: 'left',
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
