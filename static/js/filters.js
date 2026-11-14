var fi_type = null;
var fi_weights = null;
var fi_weightsFactor;
var fi_nExtraCols;
var fi_gemScores;

var fi_filters = {

    items: [
        { id: 1,   name: 'sepgeneral' },
        { id: 2,   name: 'bindonpickup',        type: 'yn' },
        { id: 3,   name: 'bindonequip',         type: 'yn' },
        { id: 4,   name: 'bindonuse',           type: 'yn' },
        { id: 133, name: 'bindtoaccount',       type: 'yn' },
        { id: 146, name: 'heroicitem',          type: 'yn' },
        { id: 107, name: 'effecttext',          type: 'str' },
        { id: 81,  name: 'fitsgemslot',         type: 'gem' },
        { id: 132, name: 'glyphtype',           type: 'glyphtype' },
        { id: 80,  name: 'hassockets',          type: 'gem' },
        { id: 151, name: 'id',                  type: 'num', before: 'name', noweights: 1 },
        { id: 100, name: 'nsockets',            type: 'num', noweights: 1 },
        { id: 124, name: 'randomenchants',      type: 'str' },
        { id: 125, name: 'reqarenartng',        type: 'num', noweights: 1 },
        { id: 111, name: 'reqskillrank',        type: 'num', noweights: 1 },
        { id: 99,  name: 'requiresprof',        type: 'profession' },
        { id: 66,  name: 'requiresprofspec',    type: 'profession' },
        { id: 17,  name: 'requiresrepwith',     type: 'faction-any+none' },
        { id: 169, name: 'requiresevent',       type: 'event-any+none' },
        { id: 160, name: 'relatedevent',        type: 'event-any+none' },
        { id: 168, name: 'teachesspell',        type: 'yn' },
        { id: 15,  name: 'unique',              type: 'yn' },
        { id: 83,  name: 'uniqueequipped',      type: 'yn' },
        { id: 152, name: 'classspecific',       type: 'classs' },
        { id: 153, name: 'racespecific',        type: 'race' },

        { id: 19,  name: 'sepbasestats' },
        { id: 21,  name: 'agi',                 type: 'num' },
        { id: 23,  name: 'int',                 type: 'num' },
        { id: 22,  name: 'sta',                 type: 'num' },
        { id: 24,  name: 'spi',                 type: 'num' },
        { id: 20,  name: 'str',                 type: 'num' },
        { id: 115, name: 'health',              type: 'num' },
        { id: 116, name: 'mana',                type: 'num' },
        { id: 60,  name: 'healthrgn',           type: 'num' },
        { id: 61,  name: 'manargn',             type: 'num' },

        { id: 120, name: 'sepdefensivestats' },
        { id: 41,  name: 'armor',               type: 'num' },
        { id: 44,  name: 'blockrtng',           type: 'num' },
        { id: 43,  name: 'block',               type: 'num' },
        { id: 42,  name: 'defrtng',             type: 'num' },
        { id: 45,  name: 'dodgertng',           type: 'num' },
        { id: 46,  name: 'parryrtng',           type: 'num' },
        { id: 79,  name: 'resirtng',            type: 'num' },

        { id: 31,  name: 'sepoffensivestats' },
        { id: 77,  name: 'atkpwr',              type: 'num' },
        { id: 97,  name: 'feratkpwr',           type: 'num', indent: 1 },
        { id: 114, name: 'armorpenrtng',        type: 'num' },
        { id: 96,  name: 'critstrkrtng',        type: 'num' },
        { id: 117, name: 'exprtng',             type: 'num' },
        { id: 103, name: 'hastertng',           type: 'num' },
        { id: 119, name: 'hitrtng',             type: 'num' },
        { id: 94,  name: 'splpen',              type: 'num' },
        { id: 123, name: 'splpwr',              type: 'num' },
        { id: 52,  name: 'arcsplpwr',           type: 'num', indent: 1 },
        { id: 53,  name: 'firsplpwr',           type: 'num', indent: 1 },
        { id: 54,  name: 'frosplpwr',           type: 'num', indent: 1 },
        { id: 55,  name: 'holsplpwr',           type: 'num', indent: 1 },
        { id: 56,  name: 'natsplpwr',           type: 'num', indent: 1 },
        { id: 57,  name: 'shasplpwr',           type: 'num', indent: 1 },

        { id: 122, name: 'sepweaponstats' },
        { id: 32,  name: 'dps',                 type: 'num' },
        { id: 35,  name: 'damagetype',          type: 'resistance' },
        { id: 33,  name: 'dmgmin1',             type: 'num' },
        { id: 34,  name: 'dmgmax1',             type: 'num' },
        { id: 36,  name: 'speed',               type: 'num' },
        { id: 134, name: 'mledps',              type: 'num' },
        { id: 135, name: 'mledmgmin',           type: 'num' },
        { id: 136, name: 'mledmgmax',           type: 'num' },
        { id: 137, name: 'mlespeed',            type: 'num' },
        { id: 138, name: 'rgddps',              type: 'num' },
        { id: 139, name: 'rgddmgmin',           type: 'num' },
        { id: 140, name: 'rgddmgmax',           type: 'num' },
        { id: 141, name: 'rgdspeed',            type: 'num' },

        { id: 121, name: 'sepresistances' },
        { id: 25,  name: 'arcres',              type: 'num' },
        { id: 26,  name: 'firres',              type: 'num' },
        { id: 28,  name: 'frores',              type: 'num' },
        { id: 30,  name: 'holres',              type: 'num' },
        { id: 27,  name: 'natres',              type: 'num' },
        { id: 29,  name: 'shares',              type: 'num' },

        { id: 67,  name: 'sepsource' },
        { id: 86,  name: 'craftedprof',             type: 'profession' },
        { id: 16,  name: 'dropsin',                 type: 'zone' },
        { id: 105, name: 'dropsinnormal',           type: 'heroicdungeon-any' },
        { id: 106, name: 'dropsinheroic',           type: 'heroicdungeon-any' },
        { id: 147, name: 'dropsinnormal10',         type: 'multimoderaid-any' },
        { id: 148, name: 'dropsinnormal25',         type: 'multimoderaid-any' },
        { id: 149, name: 'dropsinheroic10',         type: 'heroicraid-any' },
        { id: 150, name: 'dropsinheroic25',         type: 'heroicraid-any' },
        { id: 68,  name: 'otdisenchanting',         type: 'yn' },
        { id: 69,  name: 'otfishing',               type: 'yn' },
        { id: 70,  name: 'otherbgathering',         type: 'yn' },
        { id: 71,  name: 'otitemopening',           type: 'yn' },
        { id: 72,  name: 'otlooting',               type: 'yn' },
        { id: 143, name: 'otmilling',               type: 'yn' },
        { id: 73,  name: 'otmining',                type: 'yn' },
        { id: 74,  name: 'otobjectopening',         type: 'yn' },
        { id: 75,  name: 'otpickpocketing',         type: 'yn' },
        { id: 88,  name: 'otprospecting',           type: 'yn' },
        { id: 93,  name: 'otpvp',                   type: 'pvp' },
        { id: 171, name: 'otredemption',            type: 'yn' },
        { id: 76,  name: 'otskinning',              type: 'yn' },
        { id: 158, name: 'purchasablewithcurrency', type: 'currency-any' },
        { id: 118, name: 'purchasablewithitem',     type: 'itemcurrency-any' },
        { id: 144, name: 'purchasablewithhonor',    type: 'yn' },
        { id: 145, name: 'purchasablewitharena',    type: 'yn' },
        { id: 18,  name: 'rewardedbyfactionquest',  type: 'side' },
        { id: 126, name: 'rewardedbyquestin',       type: 'zone-any' },
        { id: 172, name: 'rewardedbyachievement',   type: 'yn' },
        { id: 92,  name: 'soldbyvendor',            type: 'yn' },
        { id: 129, name: 'soldbynpc',               type: 'str-small' },
        { id: 128, name: 'sepsource',               type: 'itemsource' },

        { id: 47,  name: 'sepindividualstats' },
        { id: 37,  name: 'mleatkpwr',           type: 'num' },
        { id: 84,  name: 'mlecritstrkrtng',     type: 'num' },
        { id: 78,  name: 'mlehastertng',        type: 'num' },
        { id: 95,  name: 'mlehitrtng',          type: 'num' },
        { id: 38,  name: 'rgdatkpwr',           type: 'num' },
        { id: 40,  name: 'rgdcritstrkrtng',     type: 'num' },
        { id: 101, name: 'rgdhastertng',        type: 'num' },
        { id: 39,  name: 'rgdhitrtng',          type: 'num' },
        { id: 49,  name: 'splcritstrkrtng',     type: 'num' },
        { id: 102, name: 'splhastertng',        type: 'num' },
        { id: 48,  name: 'splhitrtng',          type: 'num' },
        { id: 51,  name: 'spldmg',              type: 'num' },
        { id: 50,  name: 'splheal',             type: 'num' },

        { id: 58,  name: 'sepmisc' },
        { id: 109, name: 'armorbonus',              type: 'num' },
        { id: 161, name: 'availabletoplayers',      type: 'yn' },
        { id: 90,  name: 'avgbuyout',               type: 'num', noweights: 1 },
        { id: 65,  name: 'avgmoney',                type: 'num', noweights: 1 },
        { id: 9,   name: 'conjureditem',            type: 'yn' },
        { id: 62,  name: 'cooldown',                type: 'num', noweights: 1 },
        { id: 162, name: 'deprecated',              type: 'yn' },
        { id: 8,   name: 'disenchantable',          type: 'yn' },
        { id: 163, name: 'disenchantsinto',         type: 'disenchanting' },
        { id: 59,  name: 'dura',                    type: 'num', noweights: 1 },
        { id: 104, name: 'flavortext',              type: 'str' },
        { id: 7,   name: 'hasflavortext',           type: 'yn' },
        { id: 142, name: 'icon',                    type: 'str' },
        { id: 10,  name: 'locked',                  type: 'yn' },
        { id: 159, name: 'millable',                type: 'yn' },
     // { id: 127, name: 'notavailable',            type: 'yn' },       redundant with 161
        { id: 85,  name: 'objectivequest',          type: 'side' },
        { id: 11,  name: 'openable',                type: 'yn' },
        { id: 12,  name: 'partofset',               type: 'yn' },
        { id: 98,  name: 'partyloot',               type: 'yn' },
        { id: 89,  name: 'prospectable',            type: 'yn' },
        { id: 5,   name: 'questitem',               type: 'yn' },
        { id: 13,  name: 'randomlyenchanted',       type: 'yn' },
        { id: 14,  name: 'readable',                type: 'yn' },
        { id: 87,  name: 'reagentforability',       type: 'profession' },
        { id: 63,  name: 'buyprice',                type: 'num', noweights: 1 },
        { id: 154, name: 'refundable',              type: 'yn' },
        { id: 165, name: 'repaircost',              type: 'num', noweights: 1 },
        { id: 64,  name: 'sellprice',               type: 'num', noweights: 1 },
        { id: 157, name: 'smartloot',               type: 'yn' },
        { id: 6,   name: 'startsquest',             type: 'side' },
        { id: 91,  name: 'tool',                    type: 'totemcategory' },
        { id: 155, name: 'usableinarenas',          type: 'yn' },
        { id: 156, name: 'usablewhenshapeshifted',  type: 'yn' },
        { id: 200, name: 'itemvisual',              type: 'itemvisuals-any+none' },  // aowow - custom
        { id: 203, name: 'nocreator',               type: 'yn' },               // aowow - custom start
        { id: 204, name: 'noreagentcost',           type: 'yn' },
        { id: 205, name: 'notusableinarenas',       type: 'yn' },
        { id: 206, name: 'realtimeduration',        type: 'yn' },
        { id: 207, name: 'ignorequeststatus',       type: 'yn' },
        { id: 208, name: 'followlootrules',         type: 'yn' },
        { id: 209, name: 'greedonly',               type: 'yn' },
        { id: 210, name: 'goldandcost',             type: 'yn' },
        { id: 216, name: 'zonebound',               type: 'yn' },
        { id: 211, name: 'foodtype',                type: 'num' },
        { id: 212, name: 'bagfamily',               type: 'num' },
        { id: 218, name: 'ammotype',                type: 'num' },
        { id: 213, name: 'duration',                type: 'num' },
        { id: 214, name: 'reqhonorrank',            type: 'num' },
        { id: 215, name: 'reqcityrank',             type: 'num' },
        { id: 202, name: 'itemlimitcategory',       type: 'num' },              // aowow - custom end
    //  { id: 201, name: 'spellvisual',             type: 'str-small' }, // aowow - custom

        { id: 112, name: 'sepcommunity' },
        { id: 130, name: 'hascomments',         type: 'yn' },
        { id: 113, name: 'hasscreenshots',      type: 'yn' },
        { id: 167, name: 'hasvideos',           type: 'yn' },

        { id: 1,   name: 'sepstaffonly',                       staffonly: true },
        { id: 176, name: 'flags',               type: 'flags', staffonly: true },
        { id: 177, name: 'flags2',              type: 'flags', staffonly: true },
        { id: 217, name: 'scriptname',          type: 'str',   staffonly: true }    // aowow - custom
    ],

    itemsets: [
        { id: 9999,name: 'sepgeneral' },
        { id: 12,  name: 'availabletoplayers',  type: 'yn' },
        { id: 3,   name: 'pieces',              type: 'num' },
        { id: 4,   name: 'bonustext',           type: 'str' },
        { id: 5,   name: 'heroic',              type: 'yn' },
        { id: 6,   name: 'relatedevent',        type: 'event-any+none' },
        { id: 2,   name: 'id',                  type: 'num', before: 'name' },

        { id: 9999,name: 'sepcommunity' },
        { id:  8,  name: 'hascomments',         type: 'yn' },
        { id:  9,  name: 'hasscreenshots',      type: 'yn' },
        { id: 10,  name: 'hasvideos',           type: 'yn' }
    ],

    npcs: [
        { id: 4,   name: 'sepgeneral' },
        { id: 5,   name: 'canrepair',           type: 'yn' },
        { id: 3,   name: 'faction',             type: 'faction' },
        { id: 6,   name: 'foundin',             type: 'zone' },
        { id: 1,   name: 'health',              type: 'num' },
        { id: 2,   name: 'mana',                type: 'num' },
        { id: 32,  name: 'instanceboss',        type: 'yn' },
        { id: 143, name: 'npcmode',             type: 'npcmode-any+none' },     // aowow - custom
        { id: 144, name: 'hasdifficultyversions', type: 'yn' },                 // aowow - custom
        { id: 38,  name: 'relatedevent',        type: 'event-any+none' },
        { id: 7,   name: 'startsquest',         type: 'side' },
        { id: 8,   name: 'endsquest',           type: 'side' },
        { id: 34,  name: 'usemodel',            type: 'str-small' },
        { id: 35,  name: 'useskin',             type: 'str' },
        { id: 106, name: 'visibilityrange',     type: 'visibilityrange-any' },
        { id: 107, name: 'npcclass',            type: 'npcclass' },
        { id: 109, name: 'mechanicimmune',      type: 'mechanic-any+none' },
        { id: 110, name: 'schoolimmune',        type: 'resistance-any+none' },
        { id: 113, name: 'meleeschool',         type: 'resistance' },
        { id: 137, name: 'equipsitem',          type: 'str-small' },
        { id: 111, name: 'isvehicle',           type: 'yn' },
        { id: 112, name: 'racialleader',        type: 'yn' },
        { id: 135, name: 'regenhealth',         type: 'yn' },
        { id: 136, name: 'phased',              type: 'yn' },
        { id: 138, name: 'formationfollower',   type: 'yn' },
        { id: 139, name: 'formationleader',     type: 'yn' },
        { id: 140, name: 'ridesmount',          type: 'yn' },
        { id: 141, name: 'usedasmount',         type: 'yn' },
        { id: 142, name: 'hasquestitems',       type: 'yn' },
        { id: 156, name: 'npcappearsdead',      type: 'yn' },                       // aowow - custom start
        { id: 157, name: 'npctracked',          type: 'yn' },
        { id: 164, name: 'npcuntrackable',      type: 'yn' },
        { id: 160, name: 'npcweaponsdrawn',     type: 'yn' },
        { id: 163, name: 'npcanimtier',         type: 'yn' },
        { id: 161, name: 'npcffapvp',           type: 'yn' },
        { id: 162, name: 'npcsanctuary',        type: 'yn' },
        { id: 159, name: 'npcinteractionpause', type: 'yn' },
        { id: 165, name: 'npcpetbar',           type: 'yn' },
        { id: 158, name: 'npccursor',           type: 'cursor-any+none' },                // aowow - custom end
        { id: 37,  name: 'id',                  type: 'num', before: 'name' },

        { id: 9998, name: 'sepnpcstats' },
        { id: 120, name: 'armor',               type: 'num' },
        { id: 114, name: 'holres',              type: 'num' },
        { id: 115, name: 'firres',              type: 'num' },
        { id: 116, name: 'natres',              type: 'num' },
        { id: 117, name: 'frores',              type: 'num' },
        { id: 118, name: 'shares',              type: 'num' },
        { id: 119, name: 'arcres',              type: 'num' },
        { id: 121, name: 'meleemin',            type: 'num' },
        { id: 122, name: 'meleemax',            type: 'num' },
        { id: 126, name: 'walkspeed',           type: 'num' },
        { id: 127, name: 'runspeed',            type: 'num' },
        { id: 128, name: 'scale',               type: 'num' },
        { id: 129, name: 'hoverheight',         type: 'num' },
        { id: 130, name: 'healthmod',           type: 'num' },
        { id: 131, name: 'manamod',             type: 'num' },
        { id: 132, name: 'armormod',            type: 'num' },
        { id: 133, name: 'damagemod',           type: 'num' },
        { id: 134, name: 'xpmod',               type: 'num' },
        { id: 166, name: 'meleevariance',       type: 'num' },                      // aowow - custom
        { id: 167, name: 'rangedvariance',      type: 'num' },                      // aowow - custom

        { id: 9997, name: 'sepmovement' },                                     // aowow - custom start
        { id: 146, name: 'npcnoground',         type: 'yn' },
        { id: 147, name: 'npchovers',           type: 'yn' },
        { id: 148, name: 'npcnoswim',           type: 'yn' },
        { id: 149, name: 'npcdisablegravity',   type: 'yn' },
        { id: 150, name: 'npcflies',            type: 'yn' },
        { id: 151, name: 'npcrooted',           type: 'yn' },
        { id: 152, name: 'npcchasemaywalk',     type: 'yn' },
        { id: 153, name: 'npcchasewalks',       type: 'yn' },
        { id: 154, name: 'npcidlemayrun',       type: 'yn' },
        { id: 155, name: 'npcidleruns',         type: 'yn' },                 // aowow - custom end

        { id: 14,  name: 'seploot' },
        { id: 12,  name: 'averagemoneydropped', type: 'num' },
        { id: 43,  name: 'decreasesrepwith',    type: 'faction' },
        { id: 42,  name: 'increasesrepwith',    type: 'faction' },
        { id: 15,  name: 'gatherable',          type: 'yn' },
        { id: 44,  name: 'salvageable',         type: 'yn' },
        { id: 9,   name: 'lootable',            type: 'yn' },
        { id: 16,  name: 'minable',             type: 'yn' },
        { id: 11,  name: 'pickpocketable',      type: 'yn' },
        { id: 10,  name: 'skinnable',           type: 'yn' },

        { id: 17,  name: 'sepgossipoptions' },
        { id: 18,  name: 'auctioneer',          type: 'yn' },
        { id: 19,  name: 'banker',              type: 'yn' },
        { id: 20,  name: 'battlemaster',        type: 'yn' },
        { id: 21,  name: 'flightmaster',        type: 'yn' },
        { id: 13,  name: 'gossip',              type: 'yn' },
        { id: 51,  name: 'guildbank',           type: 'yn' },
        { id: 22,  name: 'guildmaster',         type: 'yn' },
        { id: 23,  name: 'innkeeper',           type: 'yn' },
        { id: 53,  name: 'mailbox',             type: 'yn' },
        { id: 50,  name: 'petitioner',          type: 'yn' },
        { id: 36,  name: 'professiontrainer',   type: 'yn' },
        { id: 26,  name: 'questgiver',          type: 'yn' },
        { id: 52,  name: 'spellclick',          type: 'yn' },
        { id: 39,  name: 'spirithealer',        type: 'yn' },
        { id: 45,  name: 'spiritguide',         type: 'yn' },
        { id: 27,  name: 'stablemaster',        type: 'yn' },
        { id: 24,  name: 'talentunlearner',     type: 'yn' },
        { id: 25,  name: 'tabardvendor',        type: 'yn' },
        { id: 28,  name: 'trainer',             type: 'yn' },
        { id: 29,  name: 'vendor',              type: 'yn' },
        { id: 46,  name: 'ammovendor',          type: 'yn' },
        { id: 47,  name: 'foodvendor',          type: 'yn' },
        { id: 48,  name: 'poisonvendor',        type: 'yn' },
        { id: 49,  name: 'reagentvendor',       type: 'yn' },

        { id: 105, name: 'sepnpcunitflags' },
        { id: 77,  name: 'npcunattackable', type: 'yn' },
        { id: 78,  name: 'npcimmunenpc',    type: 'yn' },
        { id: 79,  name: 'npcuntargetable', type: 'yn' },
        { id: 80,  name: 'npccannotswim',   type: 'yn' },
        { id: 81,  name: 'npchidebody',     type: 'yn' },
        { id: 82,  name: 'npcnofadein',     type: 'yn' },
        { id: 83,  name: 'npccannotturn',   type: 'yn' },

        { id: 9996, name: 'sepnpcextraflags' },
        { id: 54,  name: 'npcinstancebind', type: 'yn' },
        { id: 55,  name: 'npccivilian',     type: 'yn' },
        { id: 56,  name: 'npcnoparry',      type: 'yn' },
        { id: 57,  name: 'npcnoparryhaste', type: 'yn' },
        { id: 58,  name: 'npcnoblock',      type: 'yn' },
        { id: 59,  name: 'npcnocrushing',   type: 'yn' },
        { id: 60,  name: 'npcnoxp',         type: 'yn' },
        { id: 61,  name: 'npctrigger',      type: 'yn' },
        { id: 62,  name: 'npcnotaunt',      type: 'yn' },
        { id: 63,  name: 'npcspiritvisible', type: 'yn' },
        { id: 64,  name: 'npcoffhand',      type: 'yn' },
        { id: 65,  name: 'npcnoselltovendor', type: 'yn' },
        { id: 66,  name: 'npcignorecombat', type: 'yn' },
        { id: 67,  name: 'npcworldevent',   type: 'yn' },
        { id: 68,  name: 'npcguard',        type: 'yn' },
        { id: 69,  name: 'npcignorefeign',  type: 'yn' },
        { id: 70,  name: 'npcnocrit',       type: 'yn' },
        { id: 71,  name: 'npcnoskillgains', type: 'yn' },
        { id: 72,  name: 'npctauntdr',      type: 'yn' },
        { id: 73,  name: 'npcalldiminish',  type: 'yn' },
        { id: 74,  name: 'npcnodamagereq',  type: 'yn' },
        { id: 75,  name: 'npcignorepath',   type: 'yn' },
        { id: 76,  name: 'npcknockbackimmune', type: 'yn' },

        { id: 9995, name: 'sepnpctypeflags' },
        { id: 108, name: 'npcbossmob',      type: 'yn' },
        { id: 84,  name: 'npcvisibleghosts', type: 'yn' },
        { id: 85,  name: 'npcspellattackable', type: 'yn' },
        { id: 86,  name: 'npcinteractdead', type: 'yn' },
        { id: 87,  name: 'npcmountedcombat', type: 'yn' },
        { id: 88,  name: 'npccanassist',    type: 'yn' },
        { id: 89,  name: 'npcnopetbar',     type: 'yn' },
        { id: 90,  name: 'npcinteractcombat', type: 'yn' },
        { id: 91,  name: 'npccreatoronly',  type: 'yn' },
        { id: 92,  name: 'npcraidunit',     type: 'yn' },
        { id: 93,  name: 'npcforcegossip',  type: 'yn' },
        { id: 94,  name: 'npcnowoundanim',  type: 'yn' },
        { id: 95,  name: 'npcnofactiontip', type: 'yn' },
        { id: 96,  name: 'npcmoreaudible',  type: 'yn' },
        { id: 97,  name: 'npcnodeathmsg',   type: 'yn' },
        { id: 98,  name: 'npcmodelcollision', type: 'yn' },
        { id: 99,  name: 'npcmissilecollide', type: 'yn' },
        { id: 100, name: 'npcnonameplate',  type: 'yn' },
        { id: 101, name: 'npclinkall',      type: 'yn' },
        { id: 102, name: 'npcnoeventsounds', type: 'yn' },
        { id: 103, name: 'npcnoshadow',     type: 'yn' },
        { id: 104, name: 'npcnosheathe',    type: 'yn' },

        { id: 30,  name: 'sepcommunity' },
        { id: 33,  name: 'hascomments',         type: 'yn' },
        { id: 31,  name: 'hasscreenshots',      type: 'yn' },
        { id: 40,  name: 'hasvideos',           type: 'yn' },

        { id: 999, name: 'sepstaffonly',                    staffonly: true },
        { id: 41,  name: 'haslocation',         type: 'yn', staffonly: true },
        { id: 123, name: 'scriptname',          type: 'str', staffonly: true },
        { id: 124, name: 'stringid',            type: 'str', staffonly: true },
        { id: 145, name: 'isdifficultyversion', type: 'yn', staffonly: true }
    ],

    objects: [
        { id: 8,   name: 'sepgeneral' },
        { id: 1,   name: 'foundin',             type: 'zone' },
        { id: 15,  name: 'id',                  type: 'num', before: 'name' },
        { id: 16,  name: 'relatedevent',        type: 'event-any+none' },
        { id: 7,   name: 'requiredskilllevel',  type: 'num' },
        { id: 2,   name: 'startsquest',         type: 'side' },
        { id: 3,   name: 'endsquest',           type: 'side' },
        { id: 50,  name: 'spellfocus',          type: 'num' },
        { id: 51,  name: 'faction',             type: 'faction' },              // aowow - custom start
        { id: 67,  name: 'npcmode',             type: 'npcmode-any+none' },
        { id: 68,  name: 'hasdifficultyversions', type: 'yn' },
        { id: 52,  name: 'usemodel',            type: 'num' },
        { id: 66,  name: 'scale',               type: 'num' },
        { id: 53,  name: 'visibilityrange',     type: 'visibilityrange-any' },
        { id: 58,  name: 'objreadable',         type: 'yn' },
        { id: 59,  name: 'objlinkedtrap',       type: 'yn' },
        { id: 60,  name: 'objreqquest',         type: 'yn' },
        { id: 62,  name: 'objquestitems',       type: 'yn' },
        { id: 63,  name: 'objgossip',           type: 'yn' },
        { id: 64,  name: 'objsmartai',          type: 'yn' },
        { id: 61,  name: 'phased',              type: 'yn' },
        { id: 65,  name: 'objpooled',           type: 'yn' },
        { id: 69,  name: 'objserveronly',       type: 'yn' },
        { id: 70,  name: 'objusablemounted',    type: 'yn' },
        { id: 83,  name: 'objartkits',          type: 'yn' },
        { id: 81,  name: 'objcastbar',          type: 'str-small' },
        { id: 82,  name: 'objcursor',           type: 'cursor-any+none' },            // aowow - custom end

        { id: 9,   name: 'seploot' },
        { id: 5,   name: 'averagemoneycontained',   type: 'num' },
        { id: 4,   name: 'openable',                type: 'yn' },
        { id: 57,  name: 'lootable',                type: 'yn' },               // aowow - custom

        { id: 9998, name: 'sepobjflags' },                                      // aowow - custom start
        { id: 71,  name: 'goflaginuse',         type: 'yn' },
        { id: 72,  name: 'goflaglocked',        type: 'yn' },
        { id: 73,  name: 'goflaginteractcond',  type: 'yn' },
        { id: 74,  name: 'goflagtransport',     type: 'yn' },
        { id: 75,  name: 'goflagnotselectable', type: 'yn' },
        { id: 76,  name: 'goflagnodespawn',     type: 'yn' },
        { id: 77,  name: 'goflagaiobstacle',    type: 'yn' },
        { id: 78,  name: 'goflagfreezeanim',    type: 'yn' },
        { id: 79,  name: 'goflagdamaged',       type: 'yn' },
        { id: 80,  name: 'goflagdestroyed',     type: 'yn' },                   // aowow - custom end

        { id: 10,  name: 'sepcommunity' },
        { id: 13,  name: 'hascomments',         type: 'yn' },
        { id: 11,  name: 'hasscreenshots',      type: 'yn' },
        { id: 18,  name: 'hasvideos',           type: 'yn' },

        { id: 999, name: 'sepstaffonly',                    staffonly: true },  // aowow - custom start
        { id: 54,  name: 'haslocation',         type: 'yn', staffonly: true },
        { id: 55,  name: 'scriptname',          type: 'str', staffonly: true },
        { id: 56,  name: 'stringid',            type: 'str', staffonly: true } // aowow - custom end
    ],

    quests: [
        { id: 12,  name: 'sepgeneral' },
        { id: 34,  name: 'availabletoplayers',      type: 'yn' },
        { id: 37,  name: 'classspecific',           type: 'classs' },
        { id: 38,  name: 'racespecific',            type: 'race' },
        { id: 27,  name: 'daily',                   type: 'yn' },
        { id: 28,  name: 'weekly',                  type: 'yn' },
        { id: 29,  name: 'repeatable',              type: 'yn' },
        { id: 30,  name: 'id',                      type: 'num', before: 'name' },
        { id: 44,  name: 'countsforloremaster_stc', type: 'yn' },
        { id: 47,  name: 'setspvpflag',             type: 'yn' },
        { id: 9,   name: 'objectiveearnrepwith',    type: 'faction-any+none' },
        { id: 33,  name: 'relatedevent',            type: 'event-any+none' },
        { id: 5,   name: 'sharable',                type: 'yn' },
        { id: 19,  name: 'startsfrom',              type: 'queststart' },
        { id: 21,  name: 'endsat',                  type: 'questend' },
        { id: 11,  name: 'suggestedplayers',        type: 'num' },
        { id: 6,   name: 'timer',                   type: 'num' },
        { id: 55,  name: 'autoaccept',              type: 'yn' },               // aowow - custom start
        { id: 48,  name: 'autocomplete',            type: 'yn' },
        { id: 56,  name: 'monthly',                 type: 'yn' },
        { id: 49,  name: 'raidallowed',             type: 'yn' },
        { id: 52,  name: 'dungeonfinder',           type: 'yn' },
        { id: 50,  name: 'trackingquest',           type: 'yn' },
        { id: 51,  name: 'hiddenrewards',           type: 'yn' },
        { id: 53,  name: 'extcomplete',             type: 'yn' },
        { id: 68,  name: 'exclusivegroup',          type: 'yn' },
        { id: 69,  name: 'breadcrumb',              type: 'yn' },
        { id: 71,  name: 'pooled',                  type: 'yn' },
        { id: 73,  name: 'grantsareaaura',          type: 'yn' },
        { id: 74,  name: 'advancesevent',           type: 'yn' },
        { id: 75,  name: 'hasgiveremotes',          type: 'yn' },
        { id: 62,  name: 'providesitem',            type: 'yn' },
        { id: 63,  name: 'castsonaccept',           type: 'yn' },

        { id: 9997, name: 'seprequirements' },
        { id: 57,  name: 'maxlevel',                type: 'num' },
        { id: 58,  name: 'requiresprof',            type: 'profession' },
        { id: 59,  name: 'requiresrepwith',         type: 'faction-any+none' },
        { id: 60,  name: 'requiredmoney',           type: 'num' },
        { id: 61,  name: 'requiredpvpkills',        type: 'num' },

        { id: 9996, name: 'sepobjectives' },
        { id: 76,  name: 'objectivenpc',            type: 'num' },
        { id: 77,  name: 'objectiveobject',         type: 'num' },
        { id: 78,  name: 'objectiveitem',           type: 'num' },
        { id: 79,  name: 'objectiveexplore',        type: 'yn' },
        { id: 54,  name: 'spellcastobjectives',     type: 'yn' },               // aowow - custom end

        { id: 999, name: 'sepstaffonly',                        staffonly: true },
        { id: 42,  name: 'flags',               type: 'flags',  staffonly: true },
        { id: 80,  name: 'specialflags',        type: 'flags',  staffonly: true },  // aowow - custom start
        { id: 70,  name: 'hasobjectivemarkers', type: 'yn',     staffonly: true },
        { id: 72,  name: 'haslegacyscript',     type: 'yn',     staffonly: true },  // aowow - custom end

        { id: 13,  name: 'sepgainsrewards' },
        { id: 2,   name: 'experiencegained',    type: 'num' },
        { id: 43,  name: 'currencyrewarded',    type: 'currency' },
        { id: 45,  name: 'titlerewarded',       type: 'yn' },
        { id: 23,  name: 'itemchoices',         type: 'num' },
        { id: 22,  name: 'itemrewards',         type: 'num' },
        { id: 3,   name: 'moneyrewarded',       type: 'num' },
        { id: 65,  name: 'honorrewarded',       type: 'num' },                      // aowow - custom start
        { id: 66,  name: 'arenarewarded',       type: 'num' },
        { id: 67,  name: 'talentsrewarded',     type: 'num' },
        { id: 64,  name: 'rewardsmail',         type: 'yn' },                       // aowow - custom end
        { id: 4,   name: 'spellrewarded',       type: 'yn' },
        { id: 1,   name: 'increasesrepwith',    type: 'faction' },
        { id: 10,  name: 'decreasesrepwith',    type: 'faction' },

        { id: 14,  name: 'sepseries' },
        { id: 7,   name: 'firstquestseries',    type: 'yn' },
        { id: 15,  name: 'lastquestseries',     type: 'yn' },
        { id: 16,  name: 'partseries',          type: 'yn' },

        { id: 17,  name: 'sepcommunity' },
        { id: 25,  name: 'hascomments',         type: 'yn' },
        { id: 18,  name: 'hasscreenshots',      type: 'yn' },
        { id: 36,  name: 'hasvideos',           type: 'yn' },

        { id: 9999,name: 'sepmisc' },
        { id: 24,  name: 'lacksstartend',       type: 'yn'}
    ],

    spells: [
        { id: 9999, name: 'sepgeneral'                                                     },
        { id: 2,    name: 'prcntbasemanarequired',  type: 'num'                            },
        { id: 116,  name: 'onGlobalCooldown',       type: 'yn'                             },
        { id: 28,   name: 'casttime',               type: 'num'                            },
        { id: 27,   name: 'channeled',              type: 'yn'                             },
        { id: 200,  name: 'cooldown',               type: 'num'                            }, // aowow - custom
        { id: 26,   name: 'gcdcategory_stc',        type: 'str'                            },
        { id: 40,   name: 'damagetype',             type: 'damagetype'                     },
        { id: 201,  name: 'duration_stc',           type: 'num'                            }, // aowow - custom
        { id: 202,  name: 'targetcreaturetype',     type: 'num'                            }, // aowow - custom start
        { id: 203,  name: 'reqfacing',              type: 'yn'                             },
        { id: 204,  name: 'casteraurastate',        type: 'num'                            },
        { id: 205,  name: 'targetaurastate',        type: 'num'                            },
        { id: 206,  name: 'casteraurastatenot',     type: 'num'                            },
        { id: 207,  name: 'targetaurastatenot',     type: 'num'                            },
        { id: 208,  name: 'castinterruptedbymove',  type: 'yn'                             },
        { id: 209,  name: 'castpushback',           type: 'yn'                             },
        { id: 210,  name: 'castinterruptible',      type: 'yn'                             },
        { id: 211,  name: 'aurabrokenbydamage',     type: 'yn'                             },
        { id: 212,  name: 'aurabrokenbymove',       type: 'yn'                             },
        { id: 216,  name: 'missilespeed',           type: 'num'                            },
        { id: 217,  name: 'runicpowergain',         type: 'num'                            },
        { id: 218,  name: 'hasprocentry',           type: 'yn'                             },
        { id: 219,  name: 'linkedspells',           type: 'yn'                             },
        { id: 220,  name: 'spellgroup',             type: 'yn'                             },
        { id: 221,  name: 'bonusdata',              type: 'yn'                             },
        { id: 222,  name: 'targetposition',         type: 'yn'                             },
        { id: 223,  name: 'petauras',               type: 'yn'                             },
        { id: 224,  name: 'threatmod',              type: 'yn'                             },
        { id: 225,  name: 'requiresspell',          type: 'yn'                             },
        { id: 226,  name: 'areaspell',              type: 'yn'                             },
        { id: 227,  name: 'difficultyversions',     type: 'yn'                             },
        { id: 228,  name: 'extraitems',             type: 'yn'                             },
        { id: 229,  name: 'perfectitems',           type: 'yn'                             }, // aowow - custom end
        { id: 109,  name: 'effecttype',             type: 'effecttype'                     },
        { id: 29,   name: 'appliesaura',            type: 'effectauranames-misc'           },
        { id: 15,   name: 'icon',                   type: 'str'                            },
        { id: 10,   name: 'firstrank',              type: 'yn'                             },
        { id: 20,   name: 'hasreagents',            type: 'yn'                             },
        { id: 14,   name: 'id',                     type: 'num',            before: 'name' },
        { id: 12,   name: 'lastrank',               type: 'yn'                             },
     // { id: 121,  name: 'inMyFavorites_stc',      type: 'yn'                             },
        { id: 117,  name: 'maximumRange_stc',       type: 'num'                            },
        { id: 118,  name: 'minimumRange_stc',       type: 'num'                            },
        { id: 120,  name: 'modifiesSpell_filter',   type: 'str'                            },
        { id: 22,   name: 'proficiencytype',        type: 'proficiencytype'                },
        { id: 13,   name: 'rankno',                 type: 'num'                            },
        { id: 3,    name: 'requiresnearbyobject',   type: 'yn'                             },
        { id: 5,    name: 'requiresprofspec',       type: 'yn'                             },
        { id: 114,  name: 'requiresfaction',        type: 'side'                           },
        { id: 1,    name: 'manaenergyragecost',     type: 'num'                            },
        { id: 45,   name: 'resourcetype',           type: 'resourcetype'                   },
        { id: 129,  name: 'givesResourceType_stc',  type: 'resourcetype'                   },
        { id: 25,   name: 'rewardsskillups',        type: 'yn'                             },
     // { id: 110,  name: 'scalingap',              type: 'yn'                             },
        { id: 19,   name: 'scaling',                type: 'yn'                             },
     // { id: 111,  name: 'scalingsp',              type: 'yn'                             },
        { id: 9,    name: 'source',                 type: 'spellsource'                    },
        { id: 4,    name: 'trainingcost',           type: 'num'                            },
        { id: 43,   name: 'usableinbgs',            type: 'yn'                             },
        { id: 44,   name: 'usableinarenas',         type: 'yn'                             },
        { id: 31,   name: 'usablewhenshapeshifted', type: 'yn'                             },
        { id: 6,    name: 'disabled',               type: 'yn'                             },

        { id: 9999, name: 'sepattributes' },
        { id: 69,   name: 'harmful',                    type: 'yn' },
        { id: 57,   name: 'uncancellableaura',          type: 'yn' },
        { id: 51,   name: 'hiddenaura',                 type: 'yn' },
        { id: 95,   name: 'bandagespell',               type: 'yn' },
        { id: 61,   name: 'usabledead',                 type: 'yn' },
        { id: 62,   name: 'usablemounted',              type: 'yn' },
        { id: 64,   name: 'usablesitting',              type: 'yn' },
        { id: 53,   name: 'daytimeonly',                type: 'yn' },
        { id: 54,   name: 'nighttimeonly',              type: 'yn' },
        { id: 55,   name: 'indoorsonly',                type: 'yn' },
        { id: 56,   name: 'outdoorsonly',               type: 'yn' },
        { id: 79,   name: 'targetonlyplayer',           type: 'yn' },
        { id: 60,   name: 'cannotavoid',                type: 'yn' },
        { id: 67,   name: 'cannotreflect',              type: 'yn' },
        { id: 91,   name: 'notinraid',                  type: 'yn' },
        { id: 33,   name: 'combatcastable',             type: 'yn' },
        { id: 34,   name: 'chancetocrit',               type: 'yn' },
        { id: 35,   name: 'chancetomiss',               type: 'yn' },
        { id: 66,   name: 'channeled',                  type: 'yn' },
        { id: 85,   name: 'auratickswhileloggedout',    type: 'yn' },
        { id: 84,   name: 'nolog',                      type: 'yn' },
        { id: 68,   name: 'usablestealthed',            type: 'yn' },
        { id: 81,   name: 'doesntengagetarget',         type: 'yn' },
        { id: 77,   name: 'doesntreqshapeshift',        type: 'yn' },
        { id: 46,   name: 'disregardimmunity',          type: 'yn' },
        { id: 47,   name: 'disregardschoolimmunity',    type: 'yn' },
        { id: 78,   name: 'foodbuff',                   type: 'yn' },
        { id: 71,   name: 'nothreat',                   type: 'yn' },
        { id: 52,   name: 'onnextswingnpcs',            type: 'yn' },
        { id: 49,   name: 'onnextswingplayers',         type: 'yn' },
        { id: 90,   name: 'onlyarena',                  type: 'yn' },
        { id: 92,   name: 'paladinaura',                type: 'yn' },
        { id: 50,   name: 'passivespell',               type: 'yn' },
        { id: 36,   name: 'persiststhroughdeath',       type: 'yn' },
        { id: 72,   name: 'pickpocket',                 type: 'yn' },
        { id: 73,   name: 'dispelauraonimmunity',       type: 'yn' },
        { id: 48,   name: 'reqrangedweapon',            type: 'yn' },
        { id: 82,   name: 'reqwand',                    type: 'yn' },
        { id: 83,   name: 'reqoffhand',                 type: 'yn' },
        { id: 74,   name: 'reqfishingpole',             type: 'yn' },
        { id: 41,   name: 'requiresmetamorphosis',      type: 'yn' },
        { id: 80,   name: 'reqmainhand',                type: 'yn' },
        { id: 38,   name: 'requiresstealth',            type: 'yn' },
        { id: 75,   name: 'requntappedtarget',          type: 'yn' },
        { id: 58,   name: 'damagedependsonlevel',       type: 'yn' },
        { id: 39,   name: 'spellstealable',             type: 'yn' },
        { id: 63,   name: 'delayedrecoverystarttime',   type: 'yn' },
        { id: 86,   name: 'onlyaffectsingletarget',     type: 'yn' },
        { id: 87,   name: 'startstickingatapplication', type: 'yn' },
        { id: 59,   name: 'stopsautoattack',            type: 'yn' },
        { id: 76,   name: 'targetownitem',              type: 'yn' },
        { id: 70,   name: 'targetnotincombat',          type: 'yn' },
        { id: 93,   name: 'totemspell',                 type: 'yn' },
        { id: 42,   name: 'usablewhenstunned',          type: 'yn' },
        { id: 88,   name: 'usableconfused',             type: 'yn' },
        { id: 89,   name: 'usablefeared',               type: 'yn' },
        { id: 65,   name: 'usesallpower',               type: 'yn' },

        { id: 9999, name: 'sepattributes0' },                          // aowow - custom
        { id: 1000, name: 'attr0_0',   type: 'yn' }, // aowow - custom
        { id: 1003, name: 'attr0_3',   type: 'yn' }, // aowow - custom
        { id: 1004, name: 'attr0_4',   type: 'yn' }, // aowow - custom
        { id: 1005, name: 'attr0_5',   type: 'yn' }, // aowow - custom
        { id: 1007, name: 'attr0_7',   type: 'yn' }, // aowow - custom
        { id: 1009, name: 'attr0_9',   type: 'yn' }, // aowow - custom
        { id: 1011, name: 'attr0_11',  type: 'yn' }, // aowow - custom
        { id: 1018, name: 'attr0_18',  type: 'yn' }, // aowow - custom
        { id: 1022, name: 'attr0_22',  type: 'yn' }, // aowow - custom
        { id: 1030, name: 'attr0_30',  type: 'yn' }, // aowow - custom

        { id: 9999, name: 'sepattributes1' },                          // aowow - custom
        { id: 1032, name: 'attr1_0',   type: 'yn' }, // aowow - custom
        { id: 1035, name: 'attr1_3',   type: 'yn' }, // aowow - custom
        { id: 1036, name: 'attr1_4',   type: 'yn' }, // aowow - custom
        { id: 1041, name: 'attr1_9',   type: 'yn' }, // aowow - custom
        { id: 1043, name: 'attr1_11',  type: 'yn' }, // aowow - custom
        { id: 1045, name: 'attr1_13',  type: 'yn' }, // aowow - custom
        { id: 1046, name: 'attr1_14',  type: 'yn' }, // aowow - custom
        { id: 1049, name: 'attr1_17',  type: 'yn' }, // aowow - custom
        { id: 1050, name: 'attr1_18',  type: 'yn' }, // aowow - custom
        { id: 1051, name: 'attr1_19',  type: 'yn' }, // aowow - custom
        { id: 1052, name: 'attr1_20',  type: 'yn' }, // aowow - custom
        { id: 1053, name: 'attr1_21',  type: 'yn' }, // aowow - custom
        { id: 1054, name: 'attr1_22',  type: 'yn' }, // aowow - custom
        { id: 1055, name: 'attr1_23',  type: 'yn' }, // aowow - custom
        { id: 1057, name: 'attr1_25',  type: 'yn' }, // aowow - custom
        { id: 1058, name: 'attr1_26',  type: 'yn' }, // aowow - custom
        { id: 1059, name: 'attr1_27',  type: 'yn' }, // aowow - custom
        { id: 1061, name: 'attr1_29',  type: 'yn' }, // aowow - custom
        { id: 1062, name: 'attr1_30',  type: 'yn' }, // aowow - custom
        { id: 1063, name: 'attr1_31',  type: 'yn' }, // aowow - custom

        { id: 9999, name: 'sepattributes2' },                          // aowow - custom
        { id: 1064, name: 'attr2_0',   type: 'yn' }, // aowow - custom
        { id: 1065, name: 'attr2_1',   type: 'yn' }, // aowow - custom
        { id: 1066, name: 'attr2_2',   type: 'yn' }, // aowow - custom
        { id: 1067, name: 'attr2_3',   type: 'yn' }, // aowow - custom
        { id: 1068, name: 'attr2_4',   type: 'yn' }, // aowow - custom
        { id: 1069, name: 'attr2_5',   type: 'yn' }, // aowow - custom
        { id: 1071, name: 'attr2_7',   type: 'yn' }, // aowow - custom
        { id: 1073, name: 'attr2_9',   type: 'yn' }, // aowow - custom
        { id: 1074, name: 'attr2_10',  type: 'yn' }, // aowow - custom
        { id: 1075, name: 'attr2_11',  type: 'yn' }, // aowow - custom
        { id: 1076, name: 'attr2_12',  type: 'yn' }, // aowow - custom
        { id: 1078, name: 'attr2_14',  type: 'yn' }, // aowow - custom
        { id: 1079, name: 'attr2_15',  type: 'yn' }, // aowow - custom
        { id: 1080, name: 'attr2_16',  type: 'yn' }, // aowow - custom
        { id: 1081, name: 'attr2_17',  type: 'yn' }, // aowow - custom
        { id: 1082, name: 'attr2_18',  type: 'yn' }, // aowow - custom
        { id: 1084, name: 'attr2_20',  type: 'yn' }, // aowow - custom
        { id: 1085, name: 'attr2_21',  type: 'yn' }, // aowow - custom
        { id: 1086, name: 'attr2_22',  type: 'yn' }, // aowow - custom
        { id: 1087, name: 'attr2_23',  type: 'yn' }, // aowow - custom
        { id: 1088, name: 'attr2_24',  type: 'yn' }, // aowow - custom
        { id: 1089, name: 'attr2_25',  type: 'yn' }, // aowow - custom
        { id: 1090, name: 'attr2_26',  type: 'yn' }, // aowow - custom
        { id: 1091, name: 'attr2_27',  type: 'yn' }, // aowow - custom
        { id: 1092, name: 'attr2_28',  type: 'yn' }, // aowow - custom
        { id: 1094, name: 'attr2_30',  type: 'yn' }, // aowow - custom

        { id: 9999, name: 'sepattributes3' },                          // aowow - custom
        { id: 1096, name: 'attr3_0',   type: 'yn' }, // aowow - custom
        { id: 1097, name: 'attr3_1',   type: 'yn' }, // aowow - custom
        { id: 1098, name: 'attr3_2',   type: 'yn' }, // aowow - custom
        { id: 1099, name: 'attr3_3',   type: 'yn' }, // aowow - custom
        { id: 1100, name: 'attr3_4',   type: 'yn' }, // aowow - custom
        { id: 1101, name: 'attr3_5',   type: 'yn' }, // aowow - custom
        { id: 1102, name: 'attr3_6',   type: 'yn' }, // aowow - custom
        { id: 1103, name: 'attr3_7',   type: 'yn' }, // aowow - custom
        { id: 1105, name: 'attr3_9',   type: 'yn' }, // aowow - custom
        { id: 1108, name: 'attr3_12',  type: 'yn' }, // aowow - custom
        { id: 1109, name: 'attr3_13',  type: 'yn' }, // aowow - custom
        { id: 1110, name: 'attr3_14',  type: 'yn' }, // aowow - custom
        { id: 1111, name: 'attr3_15',  type: 'yn' }, // aowow - custom
        { id: 1112, name: 'attr3_16',  type: 'yn' }, // aowow - custom
        { id: 1115, name: 'attr3_19',  type: 'yn' }, // aowow - custom
        { id: 1117, name: 'attr3_21',  type: 'yn' }, // aowow - custom
        { id: 1119, name: 'attr3_23',  type: 'yn' }, // aowow - custom
        { id: 1121, name: 'attr3_25',  type: 'yn' }, // aowow - custom
        { id: 1122, name: 'attr3_26',  type: 'yn' }, // aowow - custom
        { id: 1123, name: 'attr3_27',  type: 'yn' }, // aowow - custom
        { id: 1124, name: 'attr3_28',  type: 'yn' }, // aowow - custom
        { id: 1125, name: 'attr3_29',  type: 'yn' }, // aowow - custom
        { id: 1126, name: 'attr3_30',  type: 'yn' }, // aowow - custom
        { id: 1127, name: 'attr3_31',  type: 'yn' }, // aowow - custom

        { id: 9999, name: 'sepattributes4' },                          // aowow - custom
        { id: 1128, name: 'attr4_0',   type: 'yn' }, // aowow - custom
        { id: 1129, name: 'attr4_1',   type: 'yn' }, // aowow - custom
        { id: 1131, name: 'attr4_3',   type: 'yn' }, // aowow - custom
        { id: 1132, name: 'attr4_4',   type: 'yn' }, // aowow - custom
        { id: 1133, name: 'attr4_5',   type: 'yn' }, // aowow - custom
        { id: 1135, name: 'attr4_7',   type: 'yn' }, // aowow - custom
        { id: 1136, name: 'attr4_8',   type: 'yn' }, // aowow - custom
        { id: 1137, name: 'attr4_9',   type: 'yn' }, // aowow - custom
        { id: 1138, name: 'attr4_10',  type: 'yn' }, // aowow - custom
        { id: 1139, name: 'attr4_11',  type: 'yn' }, // aowow - custom
        { id: 1140, name: 'attr4_12',  type: 'yn' }, // aowow - custom
        { id: 1141, name: 'attr4_13',  type: 'yn' }, // aowow - custom
        { id: 1142, name: 'attr4_14',  type: 'yn' }, // aowow - custom
        { id: 1143, name: 'attr4_15',  type: 'yn' }, // aowow - custom
        { id: 1146, name: 'attr4_18',  type: 'yn' }, // aowow - custom
        { id: 1147, name: 'attr4_19',  type: 'yn' }, // aowow - custom
        { id: 1148, name: 'attr4_20',  type: 'yn' }, // aowow - custom
        { id: 1149, name: 'attr4_21',  type: 'yn' }, // aowow - custom
        { id: 1150, name: 'attr4_22',  type: 'yn' }, // aowow - custom
        { id: 1151, name: 'attr4_23',  type: 'yn' }, // aowow - custom
        { id: 1152, name: 'attr4_24',  type: 'yn' }, // aowow - custom
        { id: 1153, name: 'attr4_25',  type: 'yn' }, // aowow - custom
        { id: 1154, name: 'attr4_26',  type: 'yn' }, // aowow - custom
        { id: 1155, name: 'attr4_27',  type: 'yn' }, // aowow - custom
        { id: 1156, name: 'attr4_28',  type: 'yn' }, // aowow - custom
        { id: 1157, name: 'attr4_29',  type: 'yn' }, // aowow - custom
        { id: 1159, name: 'attr4_31',  type: 'yn' }, // aowow - custom

        { id: 9999, name: 'sepattributes5' },                          // aowow - custom
        { id: 1160, name: 'attr5_0',   type: 'yn' }, // aowow - custom
        { id: 1161, name: 'attr5_1',   type: 'yn' }, // aowow - custom
        { id: 1162, name: 'attr5_2',   type: 'yn' }, // aowow - custom
        { id: 1164, name: 'attr5_4',   type: 'yn' }, // aowow - custom
        { id: 1166, name: 'attr5_6',   type: 'yn' }, // aowow - custom
        { id: 1167, name: 'attr5_7',   type: 'yn' }, // aowow - custom
        { id: 1168, name: 'attr5_8',   type: 'yn' }, // aowow - custom
        { id: 1170, name: 'attr5_10',  type: 'yn' }, // aowow - custom
        { id: 1171, name: 'attr5_11',  type: 'yn' }, // aowow - custom
        { id: 1172, name: 'attr5_12',  type: 'yn' }, // aowow - custom
        { id: 1173, name: 'attr5_13',  type: 'yn' }, // aowow - custom
        { id: 1174, name: 'attr5_14',  type: 'yn' }, // aowow - custom
        { id: 1175, name: 'attr5_15',  type: 'yn' }, // aowow - custom
        { id: 1176, name: 'attr5_16',  type: 'yn' }, // aowow - custom
        { id: 1179, name: 'attr5_19',  type: 'yn' }, // aowow - custom
        { id: 1180, name: 'attr5_20',  type: 'yn' }, // aowow - custom
        { id: 1181, name: 'attr5_21',  type: 'yn' }, // aowow - custom
        { id: 1182, name: 'attr5_22',  type: 'yn' }, // aowow - custom
        { id: 1183, name: 'attr5_23',  type: 'yn' }, // aowow - custom
        { id: 1184, name: 'attr5_24',  type: 'yn' }, // aowow - custom
        { id: 1185, name: 'attr5_25',  type: 'yn' }, // aowow - custom
        { id: 1186, name: 'attr5_26',  type: 'yn' }, // aowow - custom
        { id: 1187, name: 'attr5_27',  type: 'yn' }, // aowow - custom
        { id: 1188, name: 'attr5_28',  type: 'yn' }, // aowow - custom
        { id: 1189, name: 'attr5_29',  type: 'yn' }, // aowow - custom
        { id: 1190, name: 'attr5_30',  type: 'yn' }, // aowow - custom
        { id: 1191, name: 'attr5_31',  type: 'yn' }, // aowow - custom

        { id: 9999, name: 'sepattributes6' },                          // aowow - custom
        { id: 1192, name: 'attr6_0',   type: 'yn' }, // aowow - custom
        { id: 1194, name: 'attr6_2',   type: 'yn' }, // aowow - custom
        { id: 1195, name: 'attr6_3',   type: 'yn' }, // aowow - custom
        { id: 1196, name: 'attr6_4',   type: 'yn' }, // aowow - custom
        { id: 1197, name: 'attr6_5',   type: 'yn' }, // aowow - custom
        { id: 1198, name: 'attr6_6',   type: 'yn' }, // aowow - custom
        { id: 1199, name: 'attr6_7',   type: 'yn' }, // aowow - custom
        { id: 1200, name: 'attr6_8',   type: 'yn' }, // aowow - custom
        { id: 1201, name: 'attr6_9',   type: 'yn' }, // aowow - custom
        { id: 1202, name: 'attr6_10',  type: 'yn' }, // aowow - custom
        { id: 1204, name: 'attr6_12',  type: 'yn' }, // aowow - custom
        { id: 1205, name: 'attr6_13',  type: 'yn' }, // aowow - custom
        { id: 1206, name: 'attr6_14',  type: 'yn' }, // aowow - custom
        { id: 1207, name: 'attr6_15',  type: 'yn' }, // aowow - custom
        { id: 1208, name: 'attr6_16',  type: 'yn' }, // aowow - custom
        { id: 1209, name: 'attr6_17',  type: 'yn' }, // aowow - custom
        { id: 1210, name: 'attr6_18',  type: 'yn' }, // aowow - custom
        { id: 1211, name: 'attr6_19',  type: 'yn' }, // aowow - custom
        { id: 1212, name: 'attr6_20',  type: 'yn' }, // aowow - custom
        { id: 1214, name: 'attr6_22',  type: 'yn' }, // aowow - custom
        { id: 1215, name: 'attr6_23',  type: 'yn' }, // aowow - custom
        { id: 1216, name: 'attr6_24',  type: 'yn' }, // aowow - custom
        { id: 1217, name: 'attr6_25',  type: 'yn' }, // aowow - custom
        { id: 1218, name: 'attr6_26',  type: 'yn' }, // aowow - custom
        { id: 1219, name: 'attr6_27',  type: 'yn' }, // aowow - custom
        { id: 1220, name: 'attr6_28',  type: 'yn' }, // aowow - custom
        { id: 1221, name: 'attr6_29',  type: 'yn' }, // aowow - custom
        { id: 1222, name: 'attr6_30',  type: 'yn' }, // aowow - custom
        { id: 1223, name: 'attr6_31',  type: 'yn' }, // aowow - custom

        { id: 9999, name: 'sepattributes7' },                          // aowow - custom
        { id: 1225, name: 'attr7_1',   type: 'yn' }, // aowow - custom
        { id: 1227, name: 'attr7_3',   type: 'yn' }, // aowow - custom
        { id: 1228, name: 'attr7_4',   type: 'yn' }, // aowow - custom
        { id: 1230, name: 'attr7_6',   type: 'yn' }, // aowow - custom
        { id: 1231, name: 'attr7_7',   type: 'yn' }, // aowow - custom
        { id: 1232, name: 'attr7_8',   type: 'yn' }, // aowow - custom
        { id: 1233, name: 'attr7_9',   type: 'yn' }, // aowow - custom
        { id: 1234, name: 'attr7_10',  type: 'yn' }, // aowow - custom
        { id: 1235, name: 'attr7_11',  type: 'yn' }, // aowow - custom
        { id: 1236, name: 'attr7_12',  type: 'yn' }, // aowow - custom
        { id: 1237, name: 'attr7_13',  type: 'yn' }, // aowow - custom
        { id: 1238, name: 'attr7_14',  type: 'yn' }, // aowow - custom
        { id: 1239, name: 'attr7_15',  type: 'yn' }, // aowow - custom
        { id: 1240, name: 'attr7_16',  type: 'yn' }, // aowow - custom
        { id: 1241, name: 'attr7_17',  type: 'yn' }, // aowow - custom
        { id: 1242, name: 'attr7_18',  type: 'yn' }, // aowow - custom
        { id: 1243, name: 'attr7_19',  type: 'yn' }, // aowow - custom
        { id: 1244, name: 'attr7_20',  type: 'yn' }, // aowow - custom
        { id: 1245, name: 'attr7_21',  type: 'yn' }, // aowow - custom
        { id: 1246, name: 'attr7_22',  type: 'yn' }, // aowow - custom
        { id: 1247, name: 'attr7_23',  type: 'yn' }, // aowow - custom
        { id: 1248, name: 'attr7_24',  type: 'yn' }, // aowow - custom
        { id: 1249, name: 'attr7_25',  type: 'yn' }, // aowow - custom
        { id: 1250, name: 'attr7_26',  type: 'yn' }, // aowow - custom
        { id: 1251, name: 'attr7_27',  type: 'yn' }, // aowow - custom
        { id: 1252, name: 'attr7_28',  type: 'yn' }, // aowow - custom
        { id: 1253, name: 'attr7_29',  type: 'yn' }, // aowow - custom
        { id: 1254, name: 'attr7_30',  type: 'yn' }, // aowow - custom
        { id: 1255, name: 'attr7_31',  type: 'yn' }, // aowow - custom

        { id: 9999, name: 'sepcustomattr' },                            // aowow - custom
        { id: 130,  name: 'customattrenchantproc',                  type: 'yn' }, // aowow - custom
        { id: 131,  name: 'customattrconeback',                     type: 'yn' }, // aowow - custom
        { id: 132,  name: 'customattrconeline',                     type: 'yn' }, // aowow - custom
        { id: 133,  name: 'customattrsharedamage',                  type: 'yn' }, // aowow - custom
        { id: 134,  name: 'customattrnoinitialthreat',              type: 'yn' }, // aowow - custom
        { id: 135,  name: 'customattrauracc',                       type: 'yn' }, // aowow - custom
        { id: 136,  name: 'customattrdontbreakstealth',             type: 'yn' }, // aowow - custom
        { id: 137,  name: 'customattrcancrit',                      type: 'yn' }, // aowow - custom
        { id: 138,  name: 'customattrdirectdamage',                 type: 'yn' }, // aowow - custom
        { id: 139,  name: 'customattrcharge',                       type: 'yn' }, // aowow - custom
        { id: 140,  name: 'customattrpickpocket',                   type: 'yn' }, // aowow - custom
        { id: 141,  name: 'customattrrollingperiodic',              type: 'yn' }, // aowow - custom
        { id: 142,  name: 'customattrnegativeeff0',                 type: 'yn' }, // aowow - custom
        { id: 143,  name: 'customattrnegativeeff1',                 type: 'yn' }, // aowow - custom
        { id: 144,  name: 'customattrnegativeeff2',                 type: 'yn' }, // aowow - custom
        { id: 145,  name: 'customattrignorearmor',                  type: 'yn' }, // aowow - custom
        { id: 146,  name: 'customattrreqtargetfacingcaster',        type: 'yn' }, // aowow - custom
        { id: 147,  name: 'customattrreqcasterbehindtarget',        type: 'yn' }, // aowow - custom
        { id: 148,  name: 'customattrallowinflighttarget',          type: 'yn' }, // aowow - custom
        { id: 149,  name: 'customattrneedsammodata',                type: 'yn' }, // aowow - custom
        { id: 150,  name: 'customattrbinaryspell',                  type: 'yn' }, // aowow - custom
        { id: 151,  name: 'customattrschoolmasknormalwithmagic',    type: 'yn' }, // aowow - custom
        { id: 152,  name: 'customattrdeprecatedliquidaura',         type: 'yn' }, // aowow - custom
        { id: 153,  name: 'customattristalent',                     type: 'yn' }, // aowow - custom
        { id: 154,  name: 'customattrauracannotbesaved',            type: 'yn' }, // aowow - custom
        { id: 155,  name: 'customattrcantargetanyprivateobject',    type: 'yn' }, // aowow - custom

        { id: 9999, name: 'sepcommunity'               },
        { id: 11,   name: 'hascomments',    type: 'yn' },
        { id: 8,    name: 'hasscreenshots', type: 'yn' },
        { id: 17,   name: 'hasvideos',      type: 'yn' },

        { id: 9999, name: 'sepstaffonly',                        staffonly: true }, // aowow - type renamed as 335a only has 8(+1) flag fields
        { id: 96,   name: 'flags1',       type: 'spell_flags1',  staffonly: true },
        { id: 97,   name: 'flags2',       type: 'spell_flags2',  staffonly: true },
        { id: 98,   name: 'flags3',       type: 'spell_flags3',  staffonly: true },
        { id: 99,   name: 'flags4',       type: 'spell_flags4',  staffonly: true },
        { id: 100,  name: 'flags5',       type: 'spell_flags5',  staffonly: true },
        { id: 101,  name: 'flags6',       type: 'spell_flags6',  staffonly: true },
        { id: 102,  name: 'flags7',       type: 'spell_flags7',  staffonly: true },
        { id: 103,  name: 'flags8',       type: 'spell_flags8',  staffonly: true },
        { id: 104,  name: 'flags9',       type: 'spell_flags9',  staffonly: true },
        { id: 105,  name: 'flags10',      type: 'spell_flags10', staffonly: true },
        { id: 106,  name: 'flags11',      type: 'flags',         staffonly: true },
        { id: 107,  name: 'flags12',      type: 'flags',         staffonly: true },
        { id: 108,  name: 'flags13',      type: 'flags',         staffonly: true },
        { id: 213,  name: 'interruptflags',        type: 'flags',  staffonly: true }, // aowow - custom start
        { id: 214,  name: 'aurainterruptflags',    type: 'flags',  staffonly: true },
        { id: 215,  name: 'channelinterruptflags', type: 'flags',  staffonly: true },
        { id: 230,  name: 'scriptname',            type: 'str',    staffonly: true },
        { id: 231,  name: 'haslegacyscript',       type: 'yn',     staffonly: true }  // aowow - custom end
    ],

    achievements: [
        { id: 1,   name: 'sepgeneral' },
        { id: 2,   name: 'givesreward',         type: 'yn' },
        { id: 3,   name: 'rewardtext',          type: 'str' },
        { id: 4,   name: 'location',            type: 'zone' },
        { id: 10,  name: 'icon',                type: 'str' },
        { id: 9,   name: 'id',                  type: 'num', before: 'name' },
        { id: 11,  name: 'relatedevent',        type: 'event-any+none' },
        { id: 12,  name: 'disabled',            type: 'yn' },

        { id: 8,   name: 'sepseries' },
        { id: 5,   name: 'firstseries',         type: 'yn' },
        { id: 6,   name: 'lastseries',          type: 'yn' },
        { id: 7,   name: 'partseries',          type: 'yn' },

        { id: 9999,name: 'sepcommunity' },
        { id: 14,  name: 'hascomments',         type: 'yn' },
        { id: 15,  name: 'hasscreenshots',      type: 'yn' },
        { id: 16,  name: 'hasvideos',           type: 'yn' },

        { id: 9999,name: 'sepstaffonly',                        staffonly: true },
        { id: 18,  name: 'flags',               type: 'flags',  staffonly: true }
    ],

    profiles: [
        { id: 1,   name: 'sepgeneral' },
        { id: 2,   name: 'gearscore',               type: 'num' },
        { id: 3,   name: 'achievementpoints',       type: 'num' },
        { id: 21,  name: 'wearingitem',             type: 'str-small' },
        { id: 22,  name: 'wearingpermenchant',      type: 'str-small' },
        { id: 23,  name: 'completedachievement',    type: 'str-small' },
        { id: 80,  name: 'ownsmount',               type: 'str-small' },
        { id: 81,  name: 'ownspet',                 type: 'str-small' },

        { id: 24,  name: 'sepprofession' },
        { id: 25,  name: 'alchemy',             type: 'num' },
        { id: 26,  name: 'blacksmithing',       type: 'num' },
        { id: 27,  name: 'enchanting',          type: 'num' },
        { id: 28,  name: 'engineering',         type: 'num' },
        { id: 29,  name: 'herbalism',           type: 'num' },
        { id: 30,  name: 'inscription',         type: 'num' },
        { id: 31,  name: 'jewelcrafting',       type: 'num' },
        { id: 32,  name: 'leatherworking',      type: 'num' },
        { id: 33,  name: 'mining',              type: 'num' },
        { id: 34,  name: 'skinning',            type: 'num' },
        { id: 35,  name: 'tailoring',           type: 'num' },

        { id: 4,   name: 'septalent' },
        { id: 5,   name: 'talenttree1',         type: 'num' },
        { id: 6,   name: 'talenttree2',         type: 'num' },
        { id: 7,   name: 'talenttree3',         type: 'num' },

        { id: 8,   name: 'sepguild' },
        { id: 36,  name: 'hasguild',            type: 'yn' },
        { id: 9,   name: 'guildname',           type: 'str' },
        { id: 10,  name: 'guildrank',           type: 'num' },

        { id: 11,  name: 'separenateam' },
        { id: 12,  name: 'teamname2v2',         type: 'str' },
        { id: 13,  name: 'teamrtng2v2',         type: 'num' },
        { id: 14,  name: 'teamcontrib2v2',      type: 'num' },
        { id: 15,  name: 'teamname3v3',         type: 'str' },
        { id: 16,  name: 'teamrtng3v3',         type: 'num' },
        { id: 17,  name: 'teamcontrib3v3',      type: 'num' },
        { id: 18,  name: 'teamname5v5',         type: 'str' },
        { id: 19,  name: 'teamrtng5v5',         type: 'num' },
        { id: 20,  name: 'teamcontrib5v5',      type: 'num' }
    ],

    icons: [
        { id: 9999, name: 'sepuses' },
        { id: 13,   name: 'used',                   type: 'num' },
        { id: 1,    name: 'items',                  type: 'num' },
        { id: 2,    name: 'spells',                 type: 'num' },
        { id: 3,    name: 'achievements',           type: 'num' },
     // { id: 4,    name: 'battlepets',             type: 'num' },
     // { id: 5,    name: 'battlepetabilities',     type: 'num' },
        { id: 6,    name: 'currencies',             type: 'num' },
     // { id: 7,    name: 'garrisonabilities',      type: 'num' },
     // { id: 8,    name: 'garrisonbuildings',      type: 'num' },
        { id: 9,    name: 'hunterpets',             type: 'num' },
     // { id: 10,   name: 'garrisonmissionthreats', type: 'num' },
        { id: 11,   name: 'classes',                type: 'num' }
    ],

    // aowow custom
    enchantments: [
        { id: 1,   name: 'sepgeneral' },
        { id: 2,   name: 'id',                  type: 'num', before: 'name' },
        { id: 3,   name: 'requiresprof',        type: 'profession' },
        { id: 4,   name: 'reqskillrank',        type: 'num' },
        { id: 5,   name: 'hascondition',        type: 'yn' },
        { id: 200, name: 'itemvisual',          type: 'itemvisuals-any+none' },  // aowow - custom

        { id: 19,  name: 'sepbasestats' },
        { id: 21,  name: 'agi',                 type: 'num' },
        { id: 23,  name: 'int',                 type: 'num' },
        { id: 22,  name: 'sta',                 type: 'num' },
        { id: 24,  name: 'spi',                 type: 'num' },
        { id: 20,  name: 'str',                 type: 'num' },
        { id: 115, name: 'health',              type: 'num' },
        { id: 116, name: 'mana',                type: 'num' },
        { id: 60,  name: 'healthrgn',           type: 'num' },
        { id: 61,  name: 'manargn',             type: 'num' },

        { id: 120, name: 'sepdefensivestats' },
        { id: 41,  name: 'armor',               type: 'num' },
        { id: 44,  name: 'blockrtng',           type: 'num' },
        { id: 43,  name: 'block',               type: 'num' },
        { id: 42,  name: 'defrtng',             type: 'num' },
        { id: 45,  name: 'dodgertng',           type: 'num' },
        { id: 46,  name: 'parryrtng',           type: 'num' },
        { id: 79,  name: 'resirtng',            type: 'num' },

        { id: 31,  name: 'sepoffensivestats' },
        { id: 32,  name: 'dps',                 type: 'num' },
        { id: 34,  name: 'dmg',                 type: 'num' },
        { id: 77,  name: 'atkpwr',              type: 'num' },
        { id: 97,  name: 'feratkpwr',           type: 'num', indent: 1 },
        { id: 114, name: 'armorpenrtng',        type: 'num' },
        { id: 96,  name: 'critstrkrtng',        type: 'num' },
        { id: 117, name: 'exprtng',             type: 'num' },
        { id: 103, name: 'hastertng',           type: 'num' },
        { id: 119, name: 'hitrtng',             type: 'num' },
        { id: 94,  name: 'splpen',              type: 'num' },
        { id: 123, name: 'splpwr',              type: 'num' },
        { id: 52,  name: 'arcsplpwr',           type: 'num', indent: 1 },
        { id: 53,  name: 'firsplpwr',           type: 'num', indent: 1 },
        { id: 54,  name: 'frosplpwr',           type: 'num', indent: 1 },
        { id: 55,  name: 'holsplpwr',           type: 'num', indent: 1 },
        { id: 56,  name: 'natsplpwr',           type: 'num', indent: 1 },
        { id: 57,  name: 'shasplpwr',           type: 'num', indent: 1 },

        { id: 121, name: 'sepresistances' },
        { id: 25,  name: 'arcres',              type: 'num' },
        { id: 26,  name: 'firres',              type: 'num' },
        { id: 28,  name: 'frores',              type: 'num' },
        { id: 30,  name: 'holres',              type: 'num' },
        { id: 27,  name: 'natres',              type: 'num' },
        { id: 29,  name: 'shares',              type: 'num' },

        { id: 47,  name: 'sepindividualstats' },
        { id: 37,  name: 'mleatkpwr',           type: 'num' },
        { id: 84,  name: 'mlecritstrkrtng',     type: 'num' },
        { id: 78,  name: 'mlehastertng',        type: 'num' },
        { id: 95,  name: 'mlehitrtng',          type: 'num' },
        { id: 38,  name: 'rgdatkpwr',           type: 'num' },
        { id: 40,  name: 'rgdcritstrkrtng',     type: 'num' },
        { id: 101, name: 'rgdhastertng',        type: 'num' },
        { id: 39,  name: 'rgdhitrtng',          type: 'num' },
        { id: 49,  name: 'splcritstrkrtng',     type: 'num' },
        { id: 102, name: 'splhastertng',        type: 'num' },
        { id: 48,  name: 'splhitrtng',          type: 'num' },
        { id: 51,  name: 'spldmg',              type: 'num' },
        { id: 50,  name: 'splheal',             type: 'num' },

        { id: 9999,name: 'sepcommunity' },
        { id: 10,  name: 'hascomments',         type: 'yn' },
        { id: 11,  name: 'hasscreenshots',      type: 'yn' },
        { id: 12,  name: 'hasvideos',           type: 'yn' }
    ],

    areatrigger: [
        { id: 1, name: 'sepgeneral'              },
        { id: 2, name: 'id',        type: 'num'  },
        { id: 3, name: 'foundin',   type: 'zone' }
    ],

    waypointpath: [
        { id: 1, name: 'sepgeneral'              },
        { id: 2, name: 'id',        type: 'num'  },
        { id: 3, name: 'points',    type: 'num'  },
        { id: 4, name: 'foundin',   type: 'zone' },
        { id: 5, name: 'viasmartai',type: 'yn'   }
    ],

    achievementcriteria: [
        { id: 1, name: 'sepgeneral'                         },
        { id: 2, name: 'id',              type: 'num'       },
        { id: 3, name: 'achievementid',   type: 'num'       },
        { id: 4, name: 'achievementname', type: 'str'       },
        { id: 5, name: 'assetid',         type: 'num'       },
        { id: 9999, name: 'sepflags'                        },
        { id: 6, name: 'progressbar',     type: 'yn'        },
        { id: 7, name: 'hidden',          type: 'yn'        },
        { id: 8, name: 'moneycounter',    type: 'yn'        }
    ],

    gossip: [
        { id: 1, name: 'sepgeneral'             },
        { id: 2, name: 'id',       type: 'num'  },
        { id: 3, name: 'textid',   type: 'num'  }
    ],

    smartai: [
        { id: 1, name: 'sepgeneral'                          },
        { id: 2, name: 'entry',      type: 'num'             },
        { id: 3, name: 'refid',      type: 'num'             },
        { id: 4, name: 'eventtype',  type: 'smartevent'      },
        { id: 5, name: 'actiontype', type: 'smartaction'     }
    ],

    condition: [
        { id: 1, name: 'sepgeneral'                       },
        { id: 2, name: 'sourcegroup',      type: 'num'    },
        { id: 3, name: 'sourceentry',      type: 'num'    },
        { id: 4, name: 'sourcegroupentry', type: 'num'    },
        { id: 5, name: 'conditiontype',     type: 'conditiontype' },
        { id: 6, name: 'conditionvalue',    type: 'num'    }
    ],

    encounter: [
        { id: 1, name: 'sepgeneral'             },
        { id: 2, name: 'id',       type: 'num'  },
        { id: 3, name: 'credit',   type: 'num'  }
    ]

    // end aowow custom
};

function fi_toggle() {
    var c = $WH.ge('fi');
    var b = g_toggleDisplay(c);
    var d = $WH.ge('fi_toggle');

    if (b) {
        // Set focus on first textbox
        d.firstChild.nodeValue = LANG.fihide;
        c = (c.parentNode.tagName == 'FORM' ? c.parentNode : $WH.gE(c, 'form')[0]);
        c = c.elements.na ? c.elements.na : c.elements.ti;
        // aowow - custom: not every filter form has a name/title field to focus (conditions, smartai, ...)
        if (c) {
            c.focus();
            c.select();
        }
    }
    else {
        d.firstChild.nodeValue = LANG.fishow;
    }
    d.className = 'disclosure-' + (b ? 'on' : 'off');

    return false;
}

function fi_submit(_this) {
    var sum = 0;

    var _ = _this.elements;
    for (var i = 0; i < _.length; ++i) {
        switch (_[i].nodeName) {
            case 'INPUT':
                switch (_[i].type) {
                    case 'text':
                        if ($WH.trim(_[i].value).length > 0) {
                            ++sum;
                        }
                        break;
                    case 'checkbox':
                        if ((_[i].value == 'ja' || _[i].name == 'gb') && _[i].checked) {
                            ++sum;
                        }
                    case 'radio':
                        if(_[i].name != 'ma' && _[i].checked && _[i].value)
                            ++sum;

                    break;
                }
                break;

            case 'SELECT':
                if (_[i].name != 'cr[]' && _[i].name != 'gm' && _[i].selectedIndex != -1 && _[i].options[_[i].selectedIndex].value) {
                    ++sum;
                }

                break;
        }
    }

    var g = $WH.g_getGets();
    if (sum == 0 && !g.filter) {
        alert(LANG.message_fillsomecriteria);
        return false;
    }

    return true;
}

function fi_initWeightedListview() {
    this._scoreMode = (this._upgradeIds ? 2 : 0); // Normalized or percent for upgrades

    if (this.sort[0] == -this.columns.length) {
        this.applySort();
    }

    if (this._upgradeIds && this._minScore) {
        this._maxScore = this._minScore; // Makes percentages relative to upgraded item
    }
}

function fi_filterUpgradeListview(item, i) {
    var nResults = 25;

    if(!this._upgradeIds) {
        return i < nResults;
    }

    if(this._upgradeRow == null) {
        this._upgradeRow = nResults - 1;
    }

    if($WH.in_array(this._upgradeIds, item.id) != -1) {
        if(i < nResults) {
            this._upgradeRow++;
        }

        return true;
    }

    return i < this._upgradeRow;
}

function fi_addUpgradeIndicator() {
    if(this._upgradeIds) {
        var upgradeUrl = fi_filterParamToJson($WH.g_parseQueryString(location.href.replace(/^.*?filter=/, 'filter=')).filter).upg;
        for(var i = 0; i < this.data.length; ++i) {
            var
                item = this.data[i],
                newUpgrade = upgradeUrl.replace(item.id, '').replace(/(^:*|:*$)/g, '').replace('::', ':');

            if($WH.in_array(this._upgradeIds, item.id) != -1) {
                this.createIndicator($WH.sprintf(LANG.lvnote_upgradesfor, item.id, (7 - parseInt(item.name.charAt(0))), item.name.substr(1)), location.href.replace(';upg=' + upgradeUrl, (newUpgrade ? ';upg=' + newUpgrade : '')));
            }
        }
    }
}

function fi_reset(_this) {
    fi_resetCriterion($WH.ge('fi_criteria'));
    fi_resetCriterion($WH.ge('fi_weight'));

    // aowow - custom start (originally a click on reset would reload the page with empty filters)
    var bc = PageTemplate.get('breadcrumb');
    if (typeof bc[1] !== 'undefined')
        Menu.modifyUrl(Menu.findItem(mn_database, [bc[1]]), {}, {});
    // aowow - custom end

    var _ = $WH.ge('sdkgnsdkn436');
    if (_) {
        _.parentNode.style.display = 'none';
        while (_.firstChild) {
            $WH.de(_.firstChild);
        }

        $WH.ae(_, $WH.ce('option'));
    }

    _ = _this.elements;
    for (var i = 0; i < _.length; ++i) {
        switch (_[i].nodeName) {
            case 'INPUT':
                if (_[i].type == 'text') {
                    _[i].value = '';
                }
                else if (_[i].type == 'checkbox') {
                    _[i].checked = false;
                }
                else if (_[i].type == 'radio' && _[i].value.length == 0) {
                    _[i].checked = true;
                }
            break;

            case 'SELECT':
            _[i].selectedIndex = _[i].multiple ? -1 : 0;
            if (_[i].i) {
                _[i].i = _[i].selectedIndex;
            }
            break;
        }
    }

    return false;
}

function fi_resetCriterion(_this) {
    if (_this != null) {
        var d;
        while (_this.childNodes.length > 1) {
            d = _this.childNodes[1];

            while (d.childNodes.length > 1) {
                d.removeChild(d.childNodes[1]);
            }

            _this.removeChild(d);
        }

        d = _this.childNodes[0];

        while (d.childNodes.length > 1) {
            d.removeChild(d.childNodes[1]);
        }

        d.firstChild.i = null;
        d.firstChild.selectedIndex = 0;

        if (_this.nextSibling.firstChild) {
            _this.nextSibling.firstChild.style.display = _this.style.display;
        }
    }
}

function fi_addCriterion(_this, cr) {
    var _ = $WH.ge(_this.id.replace('add', ''));

    if (_.childNodes.length >= 19 || (_this.id.indexOf('criteria') > 0 && _.childNodes.length >= 4)) {
        _this.style.display = 'none';
    }

    var a = _.childNodes[0].lastChild;
    if (a.nodeName != 'A') {
        fi_appendRemoveLink(_.childNodes[0]);
    }
    else {
        a.firstChild.nodeValue = LANG.firemove;
        a.onmouseup = fi_removeCriterion;
    }

    var
        d = $WH.ce('div'),
        c = _.childNodes[0].childNodes[0].cloneNode(true);

    c.onchange = c.onkeyup = fi_criterionChange.bind(0, c);
    c.i = null;

    if (cr != null) {
        var opts = c.getElementsByTagName('option');
        for (var i = 0; i < opts.length; ++i) {
            if (opts[i].value == cr) {
                opts[i].selected = true;
                break;
            }
        }
    }
    else {
        c.firstChild.selected = true;
    }

    d.appendChild(c);
    fi_appendRemoveLink(d);
    _.appendChild(d);

    return c;
}

function fi_removeCriterion() {
    var
        _,
        d = this.parentNode,
        c = d.parentNode,
        n = (d.firstChild.name == 'wt[]');

    c.removeChild(d);

    if (c.childNodes.length == 1) {
        _ = c.firstChild;
        if (_.firstChild.selectedIndex > 0) {
            var a = _.lastChild;
            a.firstChild.nodeValue = LANG.ficlear;
            a.onmouseup = fi_clearCriterion;
        }
        else {
            _.removeChild(_.lastChild);
            _.removeChild(_.lastChild);
        }
    }

    if (c.nextSibling.firstChild) {
        c.nextSibling.firstChild.style.display = '';
    }

    if (n) {
        _ = $WH.ge('sdkgnsdkn436');
        _.selectedIndex = 0;
        _.i = 0;
        fi_presetMatch();
    }
}

function fi_clearCriterion() {
    var d = this.parentNode;
    d.firstChild.selectedIndex = 0;
    fi_criterionChange(d.firstChild);
}

function fi_appendRemoveLink(d) {
    d.appendChild($WH.ct(String.fromCharCode(160, 160)));
    var a = $WH.ce('a');
    a.href = 'javascript:;';
    a.appendChild($WH.ct(LANG.firemove));
    a.onmouseup = fi_removeCriterion;
    a.onmousedown = a.onclick = $WH.rf;
    d.appendChild(a);
}

function fi_appendClearLink(d) {
    d.appendChild($WH.ct(String.fromCharCode(160, 160)));
    var a = $WH.ce('a');
    a.href = 'javascript:;';
    a.appendChild($WH.ct(LANG.ficlear));
    a.onmouseup = fi_clearCriterion;
    a.onmousedown = a.onclick = $WH.rf;
    d.appendChild(a);
}

function fi_Lookup(value, type) {
    var c;

    if (type == null) {
        type = fi_type;
    }

    if (fi_Lookup.cache == null) {
        fi_Lookup.cache = {};
    }

    if (fi_Lookup.cache[type] == null) {
        c = {};

        for (var i = 0, len = fi_filters[type].length; i < len; ++i) {
            if(!(g_user.roles & U_GROUP_EMPLOYEE) && !g_user.publicAccess && fi_filters[type][i].staffonly)    // aowow - custom: publicAccess
                continue;

            var f = fi_filters[type][i];

            c[f.id]   = f;
            c[f.name] = f;
        }

        fi_Lookup.cache[type] = c;
    }
    else {
        c = fi_Lookup.cache[type];
    }

    if (value && typeof value == 'string') {
        var firstChar = value.charCodeAt(0);
        if (firstChar >= '0'.charCodeAt(0) && firstChar <= '9'.charCodeAt(0)) {
            value = parseInt(value);
        }
    }

    return c[value];
}

function fi_criterionChange(_this, crs, crv) {
    var _;

    if (_this.selectedIndex != _this.i) {
        var
            o = _this.options[_this.selectedIndex],
            d = _this.parentNode;

        if (d.childNodes.length > 1) {
            if (_this.selectedIndex > 0 && _this.i > 0) {
                var a = fi_Lookup(o.value);
                var b = fi_Lookup(_this.options[_this.i].value);

                if (a.type == b.type) {
                    return;
                }
            }

            while (d.childNodes.length > 1) {
                d.removeChild(d.childNodes[1]);
            }
        }

        if (_this.selectedIndex > 0) {
            var p = fi_Lookup(o.value);
            var parts = p.type.split('-');
            var criteriaName = parts[0];
            var criteriaParams = parts[1] || '';

            if (LANG.fidropdowns[criteriaName] != null) {
                if (_this.name == 'cr[]') {
                    var _c = LANG.fidropdowns[criteriaName];
                    _ = $WH.ce('select');
                    _.name = 'crs[]';

                    var group = _;
                    var crsSel = _;

                    if (criteriaParams.indexOf('any') != -1) {
                        var o2 = $WH.ce('option');
                        o2.value = '-2323';
                        o2.appendChild($WH.ct(LANG.fiany));
                        $WH.ae(group, o2);
                        if (crs != null && crs == '-2323') {
                            o2.selected = true;
                        }
                    }

                    for (var i = 0; i < _c.length; ++i) {
                        if (_c[i][0] !== null) {
                            var o2 = $WH.ce('option');
                            o2.value = _c[i][0];
                            o2.appendChild($WH.ct(_c[i][1]));
                            $WH.ae(group, o2);
                            if (crs != null && crs == _c[i][0]) {
                                o2.selected = true;
                            }
                        }
                        else {
                            var group = $WH.ce('optgroup');
                            group.label = _c[i][1];
                            $WH.ae(_, group);
                        }
                    }

                    if (criteriaParams.indexOf('none') != -1) {
                        var o2 = $WH.ce('option');
                        o2.value = '-2324';
                        o2.appendChild($WH.ct(LANG.finone));
                        $WH.ae(group, o2);
                        if (crs != null && crs == '-2324') {
                            o2.selected = true;
                        }
                    }

                    d.appendChild($WH.ct(' '));
                    d.appendChild(_);
                }

                var n = (criteriaName == 'num');

                if (n) {
                    d.appendChild($WH.ct(' '));
                }

                _ = $WH.ce('input');
                _.type = 'text';

                if (crv != null) {
                    _.value = crv.toString();
                }
                else {
                    _.value = '0';
                }

                if (_this.name == 'cr[]') {
                    _.name = 'crv[]';
                }
                else {
                    _.name = 'wtv[]';
                    _.onchange = fi_changeWeight.bind(0, _);
                }

                if (n) {
                    _.maxLength = 8;
                    _.style.textAlign = 'center';
                    _.style.width = '5.0em';
                }
                else {
                    _.type = 'hidden';
                }

                _.setAttribute('autocomplete', 'off');

                d.appendChild(_);

                // aowow - custom: optional misc value for stealth / invisibility auras, blank = any (sent as -1)
                if (criteriaParams.indexOf('misc') != -1 && _this.name == 'cr[]') {
                    var hid = _;
                    var mv = $WH.ce('input');
                    var miscAuras = [16, 17, 18, 19];

                    mv.type = 'text';
                    mv.maxLength = 8;
                    mv.style.textAlign = 'center';
                    mv.style.width = '5.0em';
                    mv.title = 'Misc value (blank = any)';
                    mv.setAttribute('autocomplete', 'off');
                    if (crv != null && parseInt(crv) >= 0 && miscAuras.indexOf(parseInt(crs)) != -1) {
                        mv.value = parseInt(crv).toString();
                    }

                    var syncMisc = function () {
                        var show = miscAuras.indexOf(parseInt(crsSel.value)) != -1;
                        var val  = mv.value.replace(/\s+/g, '');

                        mv.style.display = show ? '' : 'none';
                        hid.value = (show && val !== '' && !isNaN(parseInt(val))) ? parseInt(val).toString() : (show ? '-1' : '0');
                    };

                    crsSel.onchange = mv.onchange = mv.onkeyup = syncMisc;
                    d.appendChild($WH.ct(' '));
                    d.appendChild(mv);
                    syncMisc();
                }

                if (_this.name == 'wt[]') {
                    fi_sortWeight(_);
                }
            }
            else if (criteriaName == 'str') {
                _ = $WH.ce('input');
                _.name = 'crs[]';
                _.type = 'hidden';
                _.value = '0';
                d.appendChild(_);

                _ = $WH.ce('input');
                _.type = 'text';
                if (criteriaParams.indexOf('small') != -1) {
                    _.maxLength = 8;
                    _.style.textAlign = 'center';
                    _.style.width = '5.0em';
                }
                else {
                    _.maxLength = 50;
                    _.style.width = '9em';
                }
                _.name = 'crv[]';

                if (crv != null) {
                    _.value = crv;
                }

                d.appendChild($WH.ct(' '));
                d.appendChild(_);
            }
        }

        if (d.parentNode.childNodes.length == 1) {
            if (_this.selectedIndex > 0) {
                fi_appendClearLink(d);
            }
        }
        else if (d.parentNode.childNodes.length > 1) {
            fi_appendRemoveLink(d);
        }

        _this.i = _this.selectedIndex;
    }
}

function fi_setCriteria(cr, crs, crv) {
    var _ = $WH.ge('fi_criteria');

    var
        i,
        c = _.childNodes[0].childNodes[0],
        s;

    _ = c.getElementsByTagName('option');
    for (i = 0; i < _.length; ++i) {
        if (_[i].value == cr[0]) {
            _[i].selected = true;

            if (s = fi_Lookup(cr[0])) {
                $WH.Track.nonInteractiveEvent({
                    category: "Filters",
                    action: fi_type,                        // vars.page,
                    label: s.name
                });
            }

            break;
        }
    }
    fi_criterionChange(c, crs[0], crv[0]);

    var a = $WH.ge('fi_addcriteria');
    for (i = 1; i < cr.length && i < 5; ++i) {
        fi_criterionChange(fi_addCriterion(a, cr[i]), crs[i], crv[i]);

        if (s = fi_Lookup(cr[i])) {
            $WH.Track.nonInteractiveEvent({
                category: "Filters",
                action: fi_type,                            // vars.page,
                label: s.name
            });
        }
    }
}

function fi_setWeights(weights, nt, ids, stealth) {
    if (ids) {
        var
            wt = weights[0],
            wtv = weights[1],
            weights = {};

        for (var i = 0; i < wt.length; ++i) {
            var a = fi_Lookup(wt[i]);
            if (a && a.type == 'num' && !a.noweights && LANG.traits[a.name]) {
                weights[a.name] = wtv[i];
            }
        }
    }

    var _ = $WH.ge('fi_weight');

    if (fi_weights == null) {
        fi_weights = {};
        $WH.cO(fi_weights, weights);
    }

    var
        a = $WH.ge('fi_addweight'),
        c = _.childNodes[0].childNodes[0];

    var i = 0;
    for (var w in weights) {
        if (!LANG.traits[w]) {
            continue;
        }

        if (i++>0) {
            c = fi_addCriterion(a, w);
        }

        var opts = c.getElementsByTagName('option');

        for (var j = 0; j < opts.length; ++j) {
            if (opts[j].value && w == fi_Lookup(opts[j].value).name) {
                opts[j].selected = true;
                break;
            }
        }

        fi_criterionChange(c, 0, weights[w]);
    }

    fi_weightsFactor = fi_convertWeights(weights, true);

    $WH.ge('fi_weight_toggle').className = 'disclosure-on';
    $WH.ge('fi_weight').parentNode.style.display = '';

    if (!nt) {
        if (!fi_presetMatch(weights, stealth)) {
            fi_presetDetails();
        }
    }
}

function fi_changeWeight(_this) {
    var _ = $WH.ge('sdkgnsdkn436');
    _.selectedIndex = 0;
    _.i = 0;
    fi_sortWeight(_this);
    fi_presetMatch();
}

function fi_sortWeight(_this) {
    var
        i,
        _ = $WH.ge('fi_weight'),
        v = Number(_this.value);

    _this = _this.parentNode;

    n = 0;
    for (i = 0; i < _.childNodes.length; ++i) {
        var m = _.childNodes[i];
        if (m.childNodes.length == 5) {
            n++;
            if (m.childNodes[2].nodeName == "INPUT" && v > Number(m.childNodes[2].value)) {
                _.insertBefore(_this, m);
                return;
            }
        }
    }
    _.insertBefore(_this, _.childNodes[n]);
}

function fi_convertWeights(weights, getf) {
    var
        f = 0,
        m = 0;

    for (var w in weights) {
        if (!LANG.traits[w]) {
            continue;
        }
        f += Math.abs(weights[w]);
        if (Number(weights[w]) > m) {
            m = Number(weights[w]);
        }
    }

    if (getf) {
        return f;
    }

    var result = {};
    for (var w in weights) {
        result[w] = (LANG.traits[w] ? Math.round(1000 * weights[w] / f) / 1000 : weights[w]);
    }

    return result;
}

function fi_convertScore(score, mode, maxScore) {
    if (mode == 1) { // Raw
        return parseInt(score * fi_weightsFactor);
    }
    else if (mode == 2) { // Percent
        return ((score / maxScore) * 100).toFixed(1) + '%';
    }
    else { // Normalized
            return score.toFixed(2);
    }
}

function fi_updateScores() {
    if (++this._scoreMode > 2) {
        this._scoreMode = 0;
    }

    for (var i = 0; i < this.data.length; ++i) {
        if (this.data[i].__tr) {
            var cell = this.data[i].__tr.lastChild;
            cell.firstChild.firstChild.nodeValue = fi_convertScore(this.data[i].score, this._scoreMode, this._maxScore);
        }
    }
}

function fi_presetClass(_this, stealth) {
    if (_this.selectedIndex != _this.i) {
        var
            i,
            _group,
            c = _this.options[_this.selectedIndex],
            d = _this.parentNode,
            langref = LANG.presets;

        fi_resetCriterion(_);

        var _ = $WH.ge('sdkgnsdkn436');
        _.parentNode.style.display = 'none';

        var oldValue = 1000;
        if (_this.i) {
            oldValue = $($('#fi_presets select option')[_this.i]).attr('value');
        }

        if(!stealth && (_this.form.ub.selectedIndex == 0 || _this.form.ub.value == oldValue)) {
            $('select[name=ub] option[value="' + _this.value + '"]').attr('selected', 'selected');
        }

        while (_.firstChild) {
            $WH.de(_.firstChild);
        }
        $WH.ae(_, $WH.ce('option'));

        if (_this.selectedIndex > 0) {
            for (_group in c._presets) {
                var weights = c._presets[_group];

                if(langref[_group] != null) {
                    var group = $WH.ce('optgroup');
                    group.label = langref[_group];
                }
                else
                    group = _;

                for (var p in weights) {
                    var o = $WH.ce('option');
                    o.value = p;
                    o._weights = weights[p];
                    $WH.ae(o, $WH.ct(weights[p].name ? weights[p].name : langref[p]));
                    $WH.ae(group, o);
                }

                if (langref[_group] != null && group && group.childNodes.length > 0) {
                    $WH.ae(_, group);
                }
            }

            if (_.childNodes.length > 1) {
                _.parentNode.style.display = '';
            }
        }

        fi_presetChange(_);
        _this.i = _this.selectedIndex;
    }
}

function fi_presetChange(_this) {
    if (_this.selectedIndex != _this.i) {
        fi_resetCriterion($WH.ge('fi_weight'));

        var o = _this.options[_this.selectedIndex];
        if (_this.selectedIndex > 0) {
            // Default gem color
            if (_this.form.elements.gm.selectedIndex == 0) {
                _this.form.elements.gm.selectedIndex = 2; // Rare
            }

            fi_resetCriterion($WH.ge('fi_weight'));
            fi_setWeights(o._weights, 1, 0);
        }

        _this.i = _this.selectedIndex;

        if (g_user.id > 0) {
            var a = $WH.ge('fi_remscale');
            a.style.display = (o._weights && o._weights.name ? '' : 'none');
        }
    }
}

function fi_presetDetails() {
    var
        _     = $WH.ge('fi_weight'),
        _this = $WH.ge('fi_detail'),
        n     = $WH.ge('fi_addweight');

    var a = g_toggleDisplay(_);

    n.style.display = 'none';
    if (a) {
        _this.firstChild.nodeValue = LANG.fihidedetails;
        if (_.childNodes.length < 19) {
            n.style.display = '';
        }
    }
    else {
        _this.firstChild.nodeValue = LANG.fishowdetails;
    }

    return false;
}

function fi_presetMatch(weights, stealth) {
    if (!weights) {
        weights = {};
        var _ = $WH.ge('fi_weight');

        for (var i = 0; i < _.childNodes.length; ++i) {
            if (_.childNodes[i].childNodes.length == 5) {
                if (_.childNodes[i].childNodes[0].nodeName == "SELECT" && _.childNodes[i].childNodes[2].nodeName == "INPUT") {
                    var node = _.childNodes[i].childNodes[0].options[_.childNodes[i].childNodes[0].selectedIndex];

                    if (node.value) {
                        weights[fi_Lookup(node.value)] = Number(_.childNodes[i].childNodes[2].value);
                    }
                }
            }
        }
    }

    var _ = $WH.ge('fi_presets');
    var s = _.getElementsByTagName('select');
    if (s.length != 2) {
        return false;
    }

    var n = fi_convertWeights(weights);
    for (var l in wt_presets) {
        for (var k in wt_presets[l]) {
            for (var v in wt_presets[l][k]) {
                if (Object.keys(wt_presets[l][k][v]).length == 1) {
                    continue;
                }

                p = fi_convertWeights(wt_presets[l][k][v]);

                var match = true;
                for (var w in p) {
                    if (!LANG.traits[w]) {
                        continue;
                    }
                    if (!n[w] || p[w] != n[w]) {
                        match = false;
                        break;
                    }
                }

                if (match) {
                    for (var i = 0; i < s[0].options.length; ++i) {
                        if (l == s[0].options[i].value) {
                            s[0].options[i].selected = true;
                            fi_presetClass(s[0], stealth);
                            break;
                        }
                    }

                    for (var j = 0; j < s[1].options.length; ++j) {
                        if (v == s[1].options[j].value) {
                            s[1].options[j].selected = true;
                            fi_presetChange(s[1]);
                            break;
                        }
                    }
                    return true;
                }
            }
        }
    }

    return false;
}


function fi_presetSave() {
    if (!g_user.id) {
        return;
    }

    var
        o     = $('#sdkgnsdkn436 option:selected').get(0),
        name  = '',
        id    = ((o._weights ? o._weights.id : 0) | 0),
        scale = { id: id, name: name },
        _     = $WH.ge('fi_weight'),
        n     = 0;

    for (i = 0; i < _.childNodes.length; ++i) {
        var
            w = fi_Lookup($('[name="wt[]"]', _.childNodes[i]).val()),
            v = $('[name="wtv[]"]', _.childNodes[i]).val();

        if (w && v != 0) {
            scale[w.name] = v;
            ++n;
        }
    }

    if (n < 1 || (!(o._weights && o._weights.id) && g_user.weightscales && g_user.weightscales.length >= 5)) {
        return alert(LANG.message_weightscalesaveerror);
    }

    if (name = prompt(LANG.prompt_nameweightscale, $(o).text())) {
        var data = { save: 1,   name: $WH.urlencode(name), scale: '' };

        if (id) {
            data.id = id;
        }

        scale.name = name;
        n = 0;

        for (var w in scale) {
            if (!LANG.traits[w]) {
                continue;
            }
            if (n++ > 0) {
                data.scale += ',';
            }
            data.scale += w + ':' + scale[w];
        }

        $.post('?account=weightscales', data, function(response) {
            if (response > 0) {
                if (g_user.weightscales == null) {
                    g_user.weightscales = [];
                }

                if (scale.id) {
                    g_user.weightscales = $WH.array_filter(g_user.weightscales, function(x) {
                        return x.id != scale.id
                    });
                }

                scale.id = response;
                g_user.weightscales.push(scale);

                var
                    s = $('#fi_presets select').get(0),
                    c = $('option[value=-1]', s).get(0);

                if (!c) {
                    c = $WH.ce('option');
                    c.value = -1;
                    $WH.ae(c, $WH.ct(LANG.ficustom));
                    $WH.aef(s, c);
                    $WH.aef(s, $('option', s).get(1));
                }

                c._presets = { custom: {} };
                for (var i = 0, len = g_user.weightscales.length; i < len; ++i) {
                    c._presets.custom[g_user.weightscales[i].id] = g_user.weightscales[i];
                }

                c.selected = true;
                c.parentNode.onchange();

                var o = $('#sdkgnsdkn436 option[value=' + scale.id + ']').get(0);
                if (o) {
                    o.text = scale.name;
                    o.selected = true;
                    o.parentNode.onchange();
                }

                alert(LANG.message_saveok);
            }
            else {
                alert(LANG.message_weightscalesaveerror);
            }
        });
    }
}

function fi_presetDelete() {
    if(!g_user.id) {
        return;
    }

    var
        s = $('#fi_presets select').get(0),
        c = $('option:selected', s).get(0);

    if(c.value == -1) {
        var o = $('#sdkgnsdkn436 option:selected').get(0);
        if (o.value && confirm(LANG.confirm_deleteweightscale)) {
            $.post('?account=weightscales', { 'delete': 1, id: o.value });

            g_user.weightscales = $WH.array_filter(g_user.weightscales, function(x) {
                return x.id != o.value;
            });

            if (g_user.weightscales.length) {
                c._presets = { custom: {} };
                for (var i = 0, len = g_user.weightscales.length; i < len; ++i) {
                    c._presets.custom[g_user.weightscales[i].id] = g_user.weightscales[i];
                }
            }
            else {
                $WH.de(c);
            }

            $WH.de(o);

            $('#fi_presets select').change();
        }
    }
}

function fi_scoreSockets(item) {
    if (fi_gemScores != null) {
        var match = 0,  best = 0,   sock = 0;
        var mGems = [], bGems = [];
        var mUniq = [], bUniq = [];
        var mSock = {}, bSock = {};
        var noMatch = false;

        for (var i = 1; i <= 3; ++i) {
            var
                n = item['socket' + i],
                s = (n == 1 ? 1 : 0);

            if (n) {
                if (fi_gemScores[n]) {
                    for (var j = 0; j < fi_gemScores[n].length; ++j) {
                        var t = fi_gemScores[n][j];
                        if (t.socketLevel <= item.level && (t.uniqEquip == 0 || $WH.in_array(mUniq, t.id) < 0)) {
                            match += t.score;
                            mGems.push(t.id);

                            mSock[i - 1] = 1;

                            if (t.uniqEquip == 1) {
                                mUniq.push(t.id);
                            }

                            break;
                        }
                    }
                }
                else {
                    noMatch = true;
                }

                if (fi_gemScores[s]) {
                    for (var j = 0; j < fi_gemScores[s].length; ++j) {
                        var t = fi_gemScores[s][j];
                        if (t.socketLevel <= item.level && (t.uniqEquip == 0 || $WH.in_array(bUniq, t.id) < 0)) {
                            best += t.score;
                            bGems.push(t.id);

                            bSock[i - 1] = (t.colors & n);

                            if (t.uniqEquip == 1) {
                                bUniq.push(t.id);
                            }

                            break;
                        }
                    }
                }
            }
        }

        if (item.socketbonusstat && fi_weights && fi_weightsFactor != 0) {
            for (var i in fi_weights) {
                if (item.socketbonusstat[i]) {
                    sock += item.socketbonusstat[i] * fi_weights[i] / fi_weightsFactor;
                }
            }
        }

        item.scoreBest = best;
        item.scoreMatch = match + sock;
        item.scoreSocket = sock;

        if (item.scoreMatch >= item.scoreBest && !noMatch) {
            item.matchSockets = mSock;
            item.gemGain = item.scoreMatch;
            item.gems = mGems;
        }
        else {
            item.matchSockets = bSock;
            item.gemGain = item.scoreBest;
            item.gems = bGems;
        }

        item.score += item.gemGain;
    }

    if (item.score > this._maxScore || !this._maxScore) {
        this._maxScore = item.score;
    }

    if (this._upgradeIds && $WH.in_array(this._upgradeIds, item.id) != -1) {
        this._minScore = item.score;
        item.upgraded = 1;
    }
}

function fi_dropdownSync(_this) {
    if (_this.selectedIndex >= 0) {
        _this.className = _this.options[_this.selectedIndex].className;
    }
}

function fi_init(type) {
    fi_type = type;

    // aowow - custom: the condition types are named once, in LANG.condition_types; offer them as the dropdown, too
    if (type == 'condition' && !LANG.fidropdowns.conditiontype) {
        LANG.fidropdowns.conditiontype = [];
        for (var id in LANG.condition_types)
            LANG.fidropdowns.conditiontype.push([id * 1, LANG.condition_types[id]]);
    }

    var
        s = $WH.ge('fi_subcat'),
        m = Menu.findItem(mn_path, PageTemplate.get('breadcrumb'));

    if (s && m[3]) {
        Menu.add(s, m[3]);
    }
    else if (s) {
        $WH.de(s.parentNode);
    }

    fi_initCriterion($WH.ge('fi_criteria'), 'cr[]', type);
    if (type == 'items') {
        var foo = $WH.ge('fi_presets');
        if (foo) {
            fi_initPresets($WH.ge('fi_presets'));
            fi_initCriterion($WH.ge('fi_weight'), 'wt[]', type);
        }
    }

    var ma = $WH.ge('ma-0');
    if (ma.getAttribute('checked')) {
        ma.checked = true;
    }
}

function fi_initCriterion(_this, sname, type) {
    var div = _this.firstChild;

    var s = $WH.ce('select');
    s.name = sname;
    s.onchange = s.onkeyup = fi_criterionChange.bind(0, s);
    $WH.ae(s, $WH.ce('option'));

    var group = null;
    var langref = LANG['fi' + type];

    for (var i = 0, len = fi_filters[type].length; i < len; ++i) {
        if (!(g_user.roles & U_GROUP_EMPLOYEE) && !g_user.publicAccess && fi_filters[type][i].staffonly) {    // aowow - custom: publicAccess
            continue;
        }

        var p = fi_filters[type][i];

        if (!p.type) {
            if (group && group.childNodes.length > 0) {
                $WH.ae(s, group);
            }
            group = $WH.ce('optgroup');
            group.label = (LANG.traits[p.name] ? LANG.traits[p.name] : langref[p.name]);
        }
        else if (sname != 'wt[]' || (p.type == 'num' && !p.noweights)) {
            var o = $WH.ce('option');
            o.value = p.id;

            var txt = LANG.traits[p.name] ? LANG.traits[p.name][0] : langref[p.name];
            if (p.indent) {
                txt = '- ' + txt;
            }

            $WH.ae(o, $WH.ct(txt));
            $WH.ae(group, o);
        }
    }
    if (group && group.childNodes.length > 0) {
        $WH.ae(s, group);
    }

    $WH.ae(div, s);
}

function fi_initPresets(_this) {
    var
        _class,
        s = $WH.ce('select');

    s.onchange = s.onkeyup = fi_presetClass.bind(0, s, 0);

    $WH.ae(s, $WH.ce('option'));

    if (g_user.weightscales != null && g_user.weightscales.length) {
        var o = $WH.ce('option');
        o.value = -1;
        o._presets = { custom: {} };
        $WH.ae(o, $WH.ct(LANG.ficustom));
        $WH.ae(s, o);

        for (var i = 0, len = g_user.weightscales.length; i < len; ++i) {
            o._presets.custom[g_user.weightscales[i].id] = g_user.weightscales[i];
        }
    }

    var temp = [];
    for (var c in wt_presets) {
        temp.push(c);
    }

    temp.sort(function (a, b) {
        return $WH.strcmp(g_chr_classes[a], g_chr_classes[b]);
    });

    for (var i = 0, len = temp.length; i < len; ++i) {
        var
            c = temp[i],
            o = $WH.ce('option');

        o.value = c;
        o._presets = wt_presets[c];
        $WH.ae(o, $WH.ct(g_chr_classes[c]));
        $WH.ae(s, o);
    }

    $WH.ae(_this, s);

    var _ = $WH.ce('span');
    _.style.display = 'none';

    var s = $WH.ce('select');
    s.id = 'sdkgnsdkn436';
    s.onchange = s.onkeyup = fi_presetChange.bind(0, s);
    $WH.ae(s, $WH.ce('option'));

    $WH.ae(_, $WH.ct(' '));
    $WH.ae(_, s);
    $WH.ae(_this, _);
    $WH.ae(_this, $WH.ct(String.fromCharCode(160, 160)));

    var a = $WH.ce('a');
    a.href = 'javascript:;';
    a.id = 'fi_detail';
    a.appendChild($WH.ct(LANG.fishowdetails));
    a.onclick = fi_presetDetails;
    a.onmousedown = $WH.rf;
    $WH.ae(_this, a);

    if(g_user.id > 0) {
        $WH.ae(_this, $WH.ct(String.fromCharCode(160, 160)));

        a = $WH.ce('a');
        a.href = 'javascript:;';
        a.className = 'icon-save';
        a.appendChild($WH.ct(LANG.fisavescale));
        a.onclick = fi_presetSave;
        a.onmousedown = $WH.rf;
        $WH.ae(_this, a);

        $WH.ae(_this, $WH.ct(String.fromCharCode(160, 160)));

        a = $WH.ce('a');
        a.href = 'javascript:;';
        a.id = 'fi_remscale';
        a.className = 'icon-delete';
        a.style.display = 'none';
        a.appendChild($WH.ct(LANG.fideletescale));
        a.onclick = fi_presetDelete;
        a.onmousedown = $WH.rf;
        $WH.ae(_this, a);
    }

    var helpLink = $WH.ge('statweight-help');
    if (helpLink) {
        g_addTooltip(helpLink, LANG.tooltip_statweighting, 'q');
    }
}

function fi_getExtraCols(wt, gm, pu) {
    if (!wt.length) {
        return;
    }

    var
        res = [],
        langref = LANG.fiitems;

    var nColsMax = 10;
    if (fi_weightsFactor) {
        nColsMax--;
    }
    if (gm) {
        nColsMax--;
    }
    if (pu) {
        nColsMax--;
    }

    for (var i = 0; i < wt.length && i < nColsMax; ++i) {
        var a = fi_Lookup(wt[i]);
        if (a && a.name && a.type == 'num' && a.name != 'buyprice') {
            var b = {
                id:      a.name,
                value:   a.name,
                name:    (LANG.traits[a.name] ? LANG.traits[a.name][2] : langref[a.name]),
                tooltip: (LANG.traits[a.name] ? LANG.traits[a.name][0] : langref[a.name]),
                before:  (a.before ? a.before: 'source')
            };

            // Fix display of decimal columns
            if (a.name == 'speed') {
                b.compute = function (item, td) {
                    return (item.speed || 0).toFixed(2);
                }
            }

            if (/dps/i.test(a.name)) {                      // mledps|rngdps|dps
                b.compute = function (item, td) {
                    return (item.dps || 0).toFixed(1);
                }
            }

            // Fix display of money columns
            if (/money|price|cost/i.test(a.name)) {
                b.compute = function (item, td) {
                    td.innerHTML = g_getMoneyHtml(item[a.name] || 0);
                }
            }

            if (a.name == 'id') {
                b.width = '5%';
            }

            res.push(b);
        }
    }

    if (fi_weightsFactor) {
        if (gm) {
            res.push({
                id: 'gems',
                name: LANG.gems,
                getValue: function (item) {
                    return item.gems.length;
                },
                compute: function (item, td) {
                    if (!item.nsockets || !item.gems) {
                        return;
                    }

                    var sockets = [];
                    for (var i = 0; i < item.nsockets; i++) {
                        sockets.push(item['socket' + (i + 1)]);
                    }

                    var
                        bonusText = '',
                        i = 0;

                    for (var s in item.socketbonusstat) {
                        if (LANG.traits[s]) {
                            if (i++ > 0) {
                                bonusText += ', ';
                            }
                            bonusText += '+' + item.socketbonusstat[s] + ' ' + LANG.traits[s][2];
                        }
                    }

                    Listview.funcBox.createSocketedIcons(sockets, td, item.gems, item.matchSockets, bonusText);
                },
                sortFunc: function (a, b, col) {
                    return $WH.strcmp((a.gems ? a.gems.length: 0), (b.gems ? b.gems.length: 0));
                }
            });
        }

        res.push({
            id: 'score',
            name: LANG.score,
            width: '7%',
            value: 'score',
            compute: function (item, td) {
                var a = $WH.ce('a');
                a.href = 'javascript:;';
                a.onclick = fi_updateScores.bind(this);
                a.className = (item.gemGain > 0 ? 'q2': 'q1');

                $WH.ae(a, $WH.ct(fi_convertScore(item.score, this._scoreMode, this._maxScore)));

                $WH.ae(td, a);
            }
        });
    }

    if (pu) {
        res.push(Listview.extraCols.cost);
    }

    fi_nExtraCols = res.length;

    return res;
}

function fi_getReputationCols(factions) {
    var res = [];

    for (var i = 0, len = factions.length; i < len; ++i) {
        var name = factions[i][1];

        if (name.length > 15) {
            var words = factions[i][1].split(' ');
            for (var j = 0, len2 = words.length; j < len2; ++j) {
                if (words[j].length > 3) {
                    name = (words[j].length > 15 ? words[j].substring(0, 12) + '...': words[j]);
                    break;
                }
            }
        }

        var col = {
            id:      'faction-' + factions[i][0],
            name:    name,
            tooltip: factions[i][1],
            type:    'num',
            before:  'category'
        };

        eval("col.getValue = function(quest) { return Listview.funcBox.getQuestReputation(" + factions[i][0] + ", quest) }");
        eval("col.compute = function(quest, td) { return Listview.funcBox.getQuestReputation(" + factions[i][0] + ", quest) }");
        eval("col.sortFunc = function(a, b, col) { var _ = Listview.funcBox.getQuestReputation; return $WH.strcmp(_(" + factions[i][0] + ", a), _(" + factions[i][0] + ", b)) }");

        res.push(col);
    }

    return res;
}

function fi_setFilterParams(newParam, menuUrl) {
    return fi_mergeFilterParams(null, newParam, menuUrl);
}

// This is used when propagating the active filter throughout the menu/breadcrumb.
// If the item already had filter params, they will be combined.
function fi_mergeFilterParams(oldParam, newParam, menuUrl) {
    var oldJson = fi_filterParamToJson(oldParam);
    var newJson = fi_filterParamToJson(newParam);

    if(menuUrl && menuUrl.match('filter=')) { // Don't propegate child menu criteria
        menuJson = fi_filterParamToJson($WH.g_parseQueryString(menuUrl.replace(/^.*?filter=/, 'filter=')).filter);
        newJson  = fi_removeMenuCriteria(newJson, menuJson);
    }

    var combinedJson = $.extend(newJson, oldJson); // Existing filter params have priority over new ones

    return fi_filterJsonToParam(combinedJson);
}

function fi_removeMenuCriteria(json, menu) {
    if(menu.cr && json.cr && json.crs && json.crv) {
        var
            parts   = menu.cr.split(':'),
            filters = {
                cr:  json.cr.split(':'),
                crs: json.crs.split(':'),
                crv: json.crv.split(':')
            };

        $WH.array_walk(parts, function(part) {
            var pos = $WH.in_array(filters.cr, part);
            if (pos != -1) {
                filters.cr.splice(pos, 1);
                filters.crs.splice(pos, 1);
                filters.crv.splice(pos, 1);
            }
        });

        json.cr  = filters.cr.join(':');
        json.crs = filters.crs.join(':');
        json.crv = filters.crv.join(':');
    }
    else if (menu.fa && json.fa) {
        delete json.fa;
    }

    return json;
}

function fi_filterParamToJson(filters) {
    var result = {};

    if(filters) {
        var parts = filters.split(';');

        $.each(parts, function(idx, part) {
            $WH.g_splitQueryParam(part, result);
        });
    }

    return result;
}

function fi_filterJsonToParam(json) {
    var result = '';

    var i = 0;

    $.each(json, function(name, value) {
        if (value !== '') {
            if (i++ > 0) {
                result += ';';
            }
            result += name + '=' + value;
        }
    });

    return result;
}
