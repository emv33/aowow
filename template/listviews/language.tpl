Listview.templates.language = {
    sort: [0],
    searchable: 1,

    columns: [
        {
            id: 'name',
            name: LANG.name,
            type: 'text',
            width: '50%',
            align: 'left',
            compute: function(l, td) {
                var a = $WH.ce('a');
                a.style.fontFamily = 'Verdana, sans-serif';
                a.href = this.getItemLink(l);
                $WH.ae(a, $WH.ct(l.name));
                $WH.ae(td, a);
            },
            getVisibleText: function(l) {
                return l.name;
            },
            sortFunc: function(a, b, col) {
                return $WH.strcmp(a.name, b.name);
            }
        },
        {
            id: 'id',
            name: 'ID',
            type: 'num',
            width: '10%',
            value: 'id'
        },
        {
            id: 'skill',
            name: LANG.tab_skills,
            type: 'text',
            width: '20%',
            align: 'left',
            compute: function(l, td) {
                if (!l.skill)
                    return -1;

                var nameCol = 'name_' + Locale.getName(),
                    entry   = g_skills[l.skill],
                    a       = $WH.ce('a');

                a.className = 'q';
                a.href = '?skill=' + l.skill;
                $WH.ae(a, $WH.ct((entry && entry[nameCol]) ? entry[nameCol] : ('#' + l.skill)));
                $WH.ae(td, a);
            },
            getVisibleText: function(l) {
                var nameCol = 'name_' + Locale.getName(),
                    entry   = g_skills[l.skill];

                return (entry && entry[nameCol]) ? entry[nameCol] : (l.skill ? ('#' + l.skill) : '');
            },
            sortFunc: function(a, b, col) {
                return $WH.strcmp(this.getVisibleText(a), this.getVisibleText(b));
            }
        },
        {
            id: 'spell',
            name: LANG.tab_spells,
            type: 'text',
            width: '20%',
            align: 'left',
            compute: function(l, td) {
                if (!l.spell)
                    return -1;

                var nameCol = 'name_' + Locale.getName(),
                    entry   = g_spells[l.spell],
                    a       = $WH.ce('a');

                a.className = 'q';
                a.href = '?spell=' + l.spell;
                $WH.ae(a, $WH.ct((entry && entry[nameCol]) ? entry[nameCol] : ('#' + l.spell)));
                $WH.ae(td, a);
            },
            getVisibleText: function(l) {
                var nameCol = 'name_' + Locale.getName(),
                    entry   = g_spells[l.spell];

                return (entry && entry[nameCol]) ? entry[nameCol] : (l.spell ? ('#' + l.spell) : '');
            },
            sortFunc: function(a, b, col) {
                return $WH.strcmp(this.getVisibleText(a), this.getVisibleText(b));
            }
        }
    ],

    getItemLink: function(l) {
        return '?language=' + l.id;
    }
}
