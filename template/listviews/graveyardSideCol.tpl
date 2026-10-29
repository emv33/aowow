Listview.extraCols.graveyardSide = {
    id: 'side',
    name: LANG.side,
    after: 'name',
    type: 'text',
    width: '14%',
    align: 'left',
    // what a graveyard link to a zone is restricted to: 1 Alliance, 2 Horde, both for a link each; none is open to everyone
    compute: function(zone, td) {
        var shown = false;

        [[1, 'icon-alliance', LANG.figraveyard.alliance], [2, 'icon-horde', LANG.figraveyard.horde]].forEach(function(s) {
            if (!(zone.side & s[0]))
                return;

            if (shown)
                $WH.ae(td, $WH.ct(LANG.comma));

            $WH.ae(td, $WH.ce('span', { className: s[1] }, $WH.ct(s[2])));
            shown = true;
        });

        if (!shown)
            $WH.ae(td, $WH.ct(LANG.figraveyard.neutral));
    },
    getVisibleText: function(zone) {
        return zone.side == 1 ? LANG.figraveyard.alliance : (zone.side == 2 ? LANG.figraveyard.horde : (zone.side == 3 ? LANG.figraveyard.alliance + ' ' + LANG.figraveyard.horde : LANG.figraveyard.neutral));
    },
    sortFunc: function(a, b, col) {
        return (a.side || 0) - (b.side || 0);
    }
};

// aowow - custom: under the zone's name, the graveyard the core picks instead of an unused link
// zone.unused: [[side bits, graveyard picked instead (0: depends on where the ghost died), its name], ...]
Listview.funcBox.beforeGraveyardZones = function() {
    var i = $WH.in_array(this.columns, 'name', function(x) { return x.id; });
    if (i == -1)
        return;

    var compute = this.columns[i].compute;
    this.columns[i].compute = function(zone, td) {
        var result = compute.apply(this, arguments);

        (zone.unused || []).forEach(function(u) {
            var div = $WH.ce('div', { className: 'q0 small' });

            if (u[0] != (zone.side || 3))
                $WH.ae(div, $WH.ce('span', { className: u[0] == 1 ? 'icon-alliance' : 'icon-horde' }, $WH.ct((u[0] == 1 ? LANG.figraveyard.alliance : LANG.figraveyard.horde) + LANG.colon)));

            if (!u[1])
                $WH.ae(div, $WH.ct(LANG.figraveyard.unusedNear));
            else
            {
                var parts = LANG.figraveyard.unused.split('$1');
                $WH.ae(div, $WH.ct(parts[0]));
                $WH.ae(div, $WH.ce('a', { href: '?graveyard=' + u[1] }, $WH.ct(u[2])));
                $WH.ae(div, $WH.ct(parts[1] || ''));
            }

            $WH.ae(td, div);
        });

        return result;
    };
};
