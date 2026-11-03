Listview.templates.weather = {
    sort: [0],
    searchable: 1,

    columns: [
        {
            id: 'location',
            name: LANG.location,
            type: 'text',
            compute: function(t, td) {
                return Listview.funcBox.location(t, td);
            },
            getVisibleText: function(t) {
                return Listview.funcBox.arrayText(t.location, g_zones);
            },
            sortFunc: function(a, b, col) {
                return Listview.funcBox.assocArrCmp(a.location, b.location, g_zones);
            }
        },
        {
            id: 'rain',
            name: LANG.fiweather.rain,
            type: 'num',
            width: '14%',
            value: 'rain',
            compute: function(t, td) {
                if (!t.rain)
                    return -1;

                $WH.ae(td, $WH.ct(t.rain + '%'));
            },
            getVisibleText: function(t) {
                return t.rain;
            }
        },
        {
            id: 'snow',
            name: LANG.fiweather.snow,
            type: 'num',
            width: '14%',
            value: 'snow',
            compute: function(t, td) {
                if (!t.snow)
                    return -1;

                $WH.ae(td, $WH.ct(t.snow + '%'));
            },
            getVisibleText: function(t) {
                return t.snow;
            }
        },
        {
            id: 'storm',
            name: LANG.fiweather.storm,
            type: 'num',
            width: '14%',
            value: 'storm',
            compute: function(t, td) {
                if (!t.storm)
                    return -1;

                $WH.ae(td, $WH.ct(t.storm + '%'));
            },
            getVisibleText: function(t) {
                return t.storm;
            }
        }
    ],
    getItemLink: function(t) {
        return '?zone=' + t.zone;
    }
}
