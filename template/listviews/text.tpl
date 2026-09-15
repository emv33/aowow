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
            width: '20%',
            align: 'left',
            compute: function(t, td) {
                if (!t.ownerid) {
                    $WH.ae(td, $WH.ct('#' + t.entry));
                    return;
                }

                var lookup = t.ownerlookup ? window[t.ownerlookup] : null,
                    entry  = lookup ? lookup[t.ownerid] : null,
                    name   = entry ? entry['name_' + Locale.getName()] : null,
                    a      = $WH.ce('a');

                a.className = 'q1';
                a.href = '?' + t.ownerurl + '=' + t.ownerid;
                $WH.ae(a, $WH.ct(name || ('#' + t.ownerid)));
                $WH.ae(td, a);
            },
            getVisibleText: function(t) {
                var lookup = t.ownerlookup ? window[t.ownerlookup] : null,
                    entry  = lookup && t.ownerid ? lookup[t.ownerid] : null;

                return (entry ? entry['name_' + Locale.getName()] : null) || ('#' + (t.ownerid || t.entry));
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
        // clicking a row navigates here, so it always has to be a real page - a broadcast text
        // nothing speaks and a page no item holds have no owner to go to
        return t.ownerid ? ('?' + t.ownerurl + '=' + t.ownerid) : ('?texts&src=' + t.src);
    }
}
