Listview.templates.text = (function() {
// a broadcast text row lists everything that points at it in t.refs; every other row is its own
// single owner. A creature or item resolves to a name through its g_* lookup; a gossip menu has
// none and a line nothing references has no page either, so those arrive pre-labelled
function owners(t) {
    var list = t.refs && t.refs.length ? t.refs : [t],
        out  = [];

    for (var i = 0; i < list.length; i++) {
        var r      = list[i],
            lookup = r.ownerlookup ? window[r.ownerlookup] : null,
            entry  = lookup && r.ownerid ? lookup[r.ownerid] : null,
            text   = (entry ? entry['name_' + Locale.getName()] : null) || r.ownername || (r.ownerid ? '#' + r.ownerid : (t.refs && t.refs.length ? null : '#' + t.entry));

        if (text !== null)
            out.push({ text: text, url: r.ownerid ? '?' + r.ownerurl + '=' + r.ownerid : null });
    }

    return out;
}

function sources(t) {
    var seen = {}, out = [];
    var list = t.refs && t.refs.length ? t.refs : [t];

    for (var i = 0; i < list.length; i++) {
        var name = LANG.text_sources[list[i].src] || ('#' + list[i].src);
        if (!seen[name]) {
            seen[name] = true;
            out.push(name);
        }
    }

    return out.join(', ');
}

return {
    sort: [2],
    searchable: 1,

    columns: [
        {
            id: 'src',
            name: LANG.fitext.source,
            type: 'text',
            width: '15%',
            align: 'left',
            compute: function(t, td) {
                $WH.ae(td, $WH.ct(sources(t)));
            },
            getVisibleText: function(t) {
                return sources(t);
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
            compute: function(t, td) {
                var list = owners(t);
                for (var i = 0; i < list.length; i++) {
                    if (i)
                        $WH.ae(td, $WH.ct(', '));

                    if (!list[i].url) {
                        $WH.ae(td, $WH.ct(list[i].text));
                        continue;
                    }

                    var a = $WH.ce('a');
                    a.href = list[i].url;
                    $WH.ae(a, $WH.ct(list[i].text));
                    $WH.ae(td, a);
                }
            },
            getVisibleText: function(t) {
                return owners(t).map(function(o) { return o.text; }).join(', ');
            },
            // numeric-aware: the pre-labelled owners end in an id, and "#10" must not precede "#9"
            sortFunc: function(a, b, col) {
                return this.getVisibleText(a).localeCompare(this.getVisibleText(b), undefined, { numeric: true });
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
        return '?text=' + encodeURIComponent(t.kind + ':' + t.id);
    }
};
})();
