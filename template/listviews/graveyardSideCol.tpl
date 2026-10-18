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
