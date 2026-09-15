Listview.templates.weather = {
    sort: [0],
    searchable: 1,

    columns: [
        {
            id: 'zone',
            name: LANG.fiweather.zone,
            type: 'text',
            align: 'left',
            compute: function(t, td) {
                var name = (g_zones && g_zones[t.zone]) ? g_zones[t.zone] : ('#' + t.zone),
                    a    = $WH.ce('a');

                a.className = 'q1';
                a.href = '?zone=' + t.zone;
                $WH.ae(a, $WH.ct(name));
                $WH.ae(td, a);
            },
            getVisibleText: function(t) {
                return (g_zones && g_zones[t.zone]) ? g_zones[t.zone] : ('#' + t.zone);
            },
            sortFunc: function(a, b, col) {
                return $WH.strcmp(this.getVisibleText(a), this.getVisibleText(b));
            }
        },
        {
            id: 'rain',
            name: LANG.fiweather.rain,
            type: 'num',
            width: '14%',
            value: 'rain',
            compute: function(t, td) {
                $WH.ae(td, $WH.ct(t.rain ? (t.rain + '%') : '-'));
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
                $WH.ae(td, $WH.ct(t.snow ? (t.snow + '%') : '-'));
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
                $WH.ae(td, $WH.ct(t.storm ? (t.storm + '%') : '-'));
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
