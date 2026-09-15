Listview.templates.trainer = {
    sort: [0],
    searchable: 1,

    columns: [
        {
            id: 'npc',
            name: LANG.fitrainer.trainer,
            type: 'text',
            width: '30%',
            align: 'left',
            compute: function(t, td) {
                var nameCol = 'name_' + Locale.getName(),
                    entry   = g_npcs[t.npc],
                    a       = $WH.ce('a');

                a.className = 'q1';
                a.href = '?npc=' + t.npc;
                $WH.ae(a, $WH.ct((entry && entry[nameCol]) ? entry[nameCol] : ('#' + t.npc)));
                $WH.ae(td, a);
            },
            getVisibleText: function(t) {
                var nameCol = 'name_' + Locale.getName(),
                    entry   = g_npcs[t.npc];

                return (entry && entry[nameCol]) ? entry[nameCol] : ('#' + t.npc);
            },
            sortFunc: function(a, b, col) {
                return $WH.strcmp(this.getVisibleText(a), this.getVisibleText(b));
            }
        },
        {
            id: 'spell',
            name: LANG.fitrainer.spell,
            type: 'text',
            width: '30%',
            align: 'left',
            compute: function(t, td) {
                var icon = g_spells.createIcon(t.spell, 0);
                icon.style.cssFloat = icon.style.styleFloat = 'left';
                $WH.ae(td, icon);

                var nameCol = 'name_' + Locale.getName(),
                    entry   = g_spells[t.spell],
                    a       = $WH.ce('a');

                a.className = 'q';
                a.href = '?spell=' + t.spell;
                $WH.ae(a, $WH.ct((entry && entry[nameCol]) ? entry[nameCol] : ('#' + t.spell)));
                $WH.ae(td, a);
            },
            getVisibleText: function(t) {
                var nameCol = 'name_' + Locale.getName(),
                    entry   = g_spells[t.spell];

                return (entry && entry[nameCol]) ? entry[nameCol] : ('#' + t.spell);
            },
            sortFunc: function(a, b, col) {
                return $WH.strcmp(this.getVisibleText(a), this.getVisibleText(b));
            }
        },
        {
            id: 'cost',
            name: LANG.fitrainer.cost,
            type: 'num',
            width: '12%',
            value: 'cost',
            compute: function(t, td) {
                if (t.cost)
                    Listview.funcBox.appendMoney(td, t.cost);
                else
                    $WH.ae(td, $WH.ct('-'));
            },
            getVisibleText: function(t) {
                return t.cost;
            }
        },
        {
            id: 'reqSkill',
            name: LANG.fitrainer.reqSkill,
            type: 'text',
            width: '14%',
            align: 'left',
            compute: function(t, td) {
                if (!t.reqSkill) {
                    $WH.ae(td, $WH.ct('-'));
                    return;
                }

                var icon = g_skills.createIcon(t.reqSkill, 0);
                icon.style.cssFloat = icon.style.styleFloat = 'left';
                $WH.ae(td, icon);

                var nameCol = 'name_' + Locale.getName(),
                    entry   = g_skills[t.reqSkill],
                    a       = $WH.ce('a');

                a.className = 'q';
                a.href = '?skill=' + t.reqSkill;
                $WH.ae(a, $WH.ct((entry && entry[nameCol]) ? entry[nameCol] : ('#' + t.reqSkill)));
                $WH.ae(td, a);
            },
            getVisibleText: function(t) {
                var nameCol = 'name_' + Locale.getName(),
                    entry   = g_skills[t.reqSkill];

                return (entry && entry[nameCol]) ? entry[nameCol] : (t.reqSkill ? ('#' + t.reqSkill) : '');
            },
            sortFunc: function(a, b, col) {
                return $WH.strcmp(this.getVisibleText(a), this.getVisibleText(b));
            }
        },
        {
            id: 'reqSkillRank',
            name: LANG.fitrainer.skillLevel,
            type: 'num',
            width: '7%',
            value: 'reqSkillRank',
            compute: function(t, td) {
                $WH.ae(td, $WH.ct(t.reqSkillRank || '-'));
            },
            getVisibleText: function(t) {
                return t.reqSkillRank;
            }
        },
        {
            id: 'reqLevel',
            name: LANG.fitrainer.level,
            type: 'num',
            width: '7%',
            value: 'reqLevel',
            compute: function(t, td) {
                $WH.ae(td, $WH.ct(t.reqLevel || '-'));
            },
            getVisibleText: function(t) {
                return t.reqLevel;
            }
        }
    ],
    getItemLink: function(t) {
        return '?npc=' + t.npc;
    }
}
