Listview.templates.spawn = {
    searchable: 1,                                          // rows come in guid order; the guid is the row id, which the id column shows

    // a click on a spawn shows it on the map, the way a [lightbox=map] link does
    itemClick: function(row, e) {
        if (g_isLeftClick(e))
            MapViewer.show({ link: row.map });
    },

    // no map position, or a zone without a map image: nothing to show, so not clickable
    isItemClickable: function(row) {
        return !!row.map;
    },

    // location and details arrive as markup, built server side from the world DB row of the spawn
    markupCell: function(td, str) {
        var d = $WH.ce('div');
        d.innerHTML = Markup.toHtml(str, { allow: Markup.CLASS_STAFF });
        $('a[href^="#sai-"]', d).on('click', Listview.templates.spawn.showSmartAI);
        $WH.ae(td, d);
    },

    // a link to the SmartAI box of a spawn's own script: open the box if it is collapsed, then bring it into view
    showSmartAI: function(e) {
        var id     = this.getAttribute('href').substr(1),
            box    = $WH.ge(id),
            toggle = $('a[onclick*="\'' + id + '\'"]')[0];

        if (!box)
            return true;

        if (box.style.display == 'none' && toggle)
            g_disclose(box, toggle);

        (toggle || box).scrollIntoView({ block: 'start' });
        return false;
    },

    plainText: function(str) {
        return (str || '').replace(/\[[^\]]*\]/g, '');
    },

    columns: [
        {
            id: 'location',
            name: LANG.fispawn.location,
            type: 'text',
            width: '18%',
            align: 'left',
            compute: function(t, td) {
                Listview.templates.spawn.markupCell(td, t.location);
            },
            getVisibleText: function(t) {
                // g_zones (locale_*.js) names every zone by id, as the other location columns read it
                return (t.zone && g_zones[t.zone]) || Listview.templates.spawn.plainText(t.location);
            },
            sortFunc: function(a, b, col) {
                return $WH.strcmp(this.getVisibleText(a), this.getVisibleText(b));
            }
        },
        {
            id: 'position',
            name: LANG.fispawn.position,
            type: 'text',
            width: '16%',
            compute: function(t, td) {
                td.style.fontSize = '11px';
                td.style.whiteSpace = 'nowrap';
                $WH.ae(td, $WH.ct(t.position));
            },
            getVisibleText: function(t) {
                return t.position;
            }
        },
        {
            id: 'mode',
            name: LANG.fispawn.mode,
            type: 'text',
            width: '10%',
            value: 'mode'
        },
        {
            id: 'event',
            name: LANG.fispawn.event,
            type: 'text',
            width: '14%',
            align: 'left',
            compute: function(t, td) {
                if (!t.event)
                    return -1;

                Listview.templates.spawn.markupCell(td, t.event);
            },
            getVisibleText: function(t) {
                // the markup names its events by id; their names come from g_holidays
                return (t.event || '').replace(/\[event=(\d+)\]/g, function(m, id) {
                    var e = window.g_holidays && g_holidays[id];
                    return e ? e['name_' + Locale.getName()] : '#' + id;
                }).replace(/\[br\]/g, ', ').replace(/\[[^\]]*\]/g, '');
            },
            sortFunc: function(a, b, col) {
                return $WH.strcmp(this.getVisibleText(a), this.getVisibleText(b));
            }
        },
        {
            id: 'phase',
            name: LANG.fispawn.phase,
            type: 'num',
            width: '7%',
            value: 'phase'
        },
        {
            id: 'respawn',
            name: LANG.fispawn.respawn,
            type: 'text',
            width: '9%',
            compute: function(t, td) {
                $WH.ae(td, $WH.ct(t.respawnText));
            },
            getVisibleText: function(t) {
                return t.respawnText;
            },
            sortFunc: function(a, b, col) {
                return a.respawn - b.respawn;
            }
        },
        {
            id: 'movement',
            name: LANG.fispawn.movement,
            type: 'text',
            width: '11%',
            value: 'movement'
        },
        {
            id: 'state',
            name: LANG.fispawn.state,
            type: 'text',
            width: '11%',
            value: 'state'
        },
        {
            id: 'stringId',
            name: LANG.fispawn.stringId,
            type: 'text',
            width: '12%',
            value: 'stringId'
        },
        {
            id: 'details',
            name: LANG.fispawn.details,
            type: 'text',
            align: 'left',
            compute: function(t, td) {
                if (!t.details)
                    return -1;

                td.style.fontSize = '11px';
                Listview.templates.spawn.markupCell(td, t.details);
            },
            getVisibleText: function(t) {
                return Listview.templates.spawn.plainText(t.details);
            },
            sortFunc: function(a, b, col) {
                return $WH.strcmp(this.getVisibleText(a), this.getVisibleText(b));
            }
        }
    ]
}
