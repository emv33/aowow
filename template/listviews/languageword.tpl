Listview.templates.languageword = {
    sort: ['len', 'word'],
    searchable: 1,

    columns: [
        {
            id: 'word',
            name: LANG.word,
            type: 'text',
            width: '60%',
            align: 'left',
            compute: function(l, td) {
                $WH.ae(td, $WH.ct(l.word));
            },
            getVisibleText: function(l) {
                return l.word;
            },
            sortFunc: function(a, b, col) {
                return $WH.strcmp(a.word, b.word);
            }
        },
        {
            id: 'len',
            name: LANG.letters,
            type: 'number',
            width: '40%',
            align: 'left',
            compute: function(l, td) {
                $WH.ae(td, $WH.ct(l.len));
            },
            getVisibleText: function(l) {
                return String(l.len);
            },
            sortFunc: function(a, b, col) {
                return a.len - b.len;
            }
        }
    ]
};
