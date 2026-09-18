Listview.templates.text = {
    sort: [1],
    searchable: 1,

    columns: [
        {
            id: 'src',
            name: LANG.fitext.source,
            type: 'text',
            width: '15%',
            align: 'left',
            compute: function(t, td) {
                $WH.ae(td, $WH.ct(LANG.text_sources[t.src] || ('#' + t.src)));
            },
            getVisibleText: function(t) {
                return LANG.text_sources[t.src] || ('#' + t.src);
            },
            sortFunc: function(a, b, col) {
                return $WH.strcmp(this.getVisibleText(a), this.getVisibleText(b));
            }
        },
        {
            id: 'owner',
            name: LANG.fitext.owner,
            type: 'text',
            width: '22%',
            align: 'left',
            // a creature or item resolves to a name through its g_* lookup; a gossip menu has none
            // and a line nothing references has no page either, so both arrive pre-labelled.
            // spelled out twice rather than shared: `this` is the Listview inside compute() and the
            // column inside sortFunc(), so a helper hung off either one is reachable from only one
            compute: function(t, td) {
                var lookup = t.ownerlookup ? window[t.ownerlookup] : null,
                    entry  = lookup && t.ownerid ? lookup[t.ownerid] : null,
                    text   = (entry ? entry['name_' + Locale.getName()] : null) || t.ownername || ('#' + (t.ownerid || t.entry));

                if (!t.ownerid) {
                    $WH.ae(td, $WH.ct(text));
                    return;
                }

                var a = $WH.ce('a');
                a.className = 'q1';
                a.href = '?' + t.ownerurl + '=' + t.ownerid;
                $WH.ae(a, $WH.ct(text));
                $WH.ae(td, a);
            },
            getVisibleText: function(t) {
                var lookup = t.ownerlookup ? window[t.ownerlookup] : null,
                    entry  = lookup && t.ownerid ? lookup[t.ownerid] : null;

                return (entry ? entry['name_' + Locale.getName()] : null) || t.ownername || ('#' + (t.ownerid || t.entry));
            },
            sortFunc: function(a, b, col) {
                return $WH.strcmp(this.getVisibleText(a), this.getVisibleText(b));
            }
        },
        {
            id: 'text',
            name: LANG.fitext.text,
            type: 'text',
            align: 'left',
            compute: function(t, td) {
                // a book page runs for pages; the row is an excerpt, the owner link holds the whole thing
                var txt = t.text.length > 400 ? t.text.substring(0, 400) + '…' : t.text;
                $WH.ae(td, $WH.ct(txt));
            },
            getVisibleText: function(t) {
                return t.text;
            },
            sortFunc: function(a, b, col) {
                return $WH.strcmp(a.text, b.text);
            }
        }
    ],
    getItemLink: function(t) {
        return '?text=' + encodeURIComponent(t.id);
    }
}
