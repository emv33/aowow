Listview.templates.gossip = {
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
            compute: function(gossip, td, tr) {
                var a = $WH.ce('a');
                a.style.fontFamily = 'Verdana, sans-serif';
                a.href = this.getItemLink(gossip);

                $WH.ae(a, $WH.ct(gossip.name));
                $WH.ae(td, a);
            },
            sortFunc: function(a, b, col) {
                return a.id - b.id;
            },
            getVisibleText: function(gossip) {
                return gossip.name;
            }
        },
        {
            id: 'textids',
            name: LANG.figossip.textid,
            type: 'text',
            width: '15%',
            compute: function(gossip, td) {
                if (!gossip.textids || !gossip.textids.length)
                    return -1;

                $WH.ae(td, $WH.ct(gossip.textids.join(LANG.comma)));
            },
            getVisibleText: function(gossip) {
                return (gossip.textids || []).join(' ');
            },
            sortFunc: function(a, b, col) {
                return (a.textids ? a.textids.length : 0) - (b.textids ? b.textids.length : 0);
            }
        },
        {
            id: 'noptions',
            name: LANG.gossip_options,
            type: 'num',
            width: '10%',
            value: 'noptions',
            compute: function(gossip, td) {
                $WH.ae(td, $WH.ct(gossip.noptions));

                (gossip.opticons || []).forEach(function(cls) {
                    $WH.ae(td, $WH.ce('div', {
                        className: cls,
                        title: cls.replace('gossip-', ''),
                        style: { display: 'inline-block', width: '16px', height: '16px', marginLeft: '2px', verticalAlign: 'middle' }
                    }));
                });
            },
            sortFunc: function(a, b, col) {
                return a.noptions - b.noptions;
            }
        },
        {
            id: 'openedby',
            name: LANG.gossip_openedby,
            type: 'text',
            compute: function(gossip, td) {
                var nameCol = 'name_' + Locale.getName();
                var first   = true;

                var append = function(ids, lookup, urlPart) {
                    if (!ids)
                        return;

                    for (var i = 0; i < ids.length; ++i) {
                        var entry = lookup[ids[i]];
                        if (!entry || !entry[nameCol])
                            continue;

                        if (!first)
                            $WH.ae(td, $WH.ct(LANG.comma));

                        var a = $WH.ce('a');
                        a.className = 'q1';
                        a.href = '?' + urlPart + '=' + ids[i];
                        $WH.ae(a, $WH.ct(entry[nameCol]));
                        $WH.ae(td, a);

                        first = false;
                    }
                };

                append(gossip.npcs,    g_npcs,    'npc');
                append(gossip.objects, g_objects, 'object');

                if (first)
                    return -1;
            },
            getVisibleText: function(gossip) {
                var nameCol = 'name_' + Locale.getName(),
                    buff    = [];

                (gossip.npcs    || []).forEach(function(id) { if (g_npcs[id])    buff.push(g_npcs[id][nameCol]);    });
                (gossip.objects || []).forEach(function(id) { if (g_objects[id]) buff.push(g_objects[id][nameCol]); });

                return buff.join(' ');
            },
            sortFunc: function(a, b, col) {
                return $WH.strcmp(this.getVisibleText(a), this.getVisibleText(b));
            }
        }
    ],
    getItemLink: function(gossip) {
        return '?gossip=' + gossip.id;
    },
    onBeforeCreate : function() {
        // hide duplicate id col
        if (this.debug || g_user?.debug) {
            let colId = this.columns.findIndex(x => x.id == 'id');
            this.visibility = this.visibility.filter(x => x != colId);
        }
    }
}
