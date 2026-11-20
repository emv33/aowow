/*
 * client-side language scramble - the ?translate tool.
 *
 * Mirrors the client's ChatTranslate::Translate and SStrHash (see
 * includes/game/languagetranslate.class.php for the reference notes). The word list is shipped
 * by the page as g_languageWords, and the full state (text, language, listener skill) lives in
 * the URL hash so a translation is shareable.
 */

var tr_hashTable = [
    0x486E26EE, 0xDCAA16B3, 0xE1918EEF, 0x202DAFDB,
    0x341C7DC7, 0x1C365303, 0x40EF2D37, 0x65FD5E49,
    0xD6057177, 0x904ECE93, 0x1C38024F, 0x98FD323B,
    0xE3061AE7, 0xA39B0FA1, 0x9797F25F, 0xE4444563
];

function tr_upperBackslash(c)
{
    if (c === 0x2F)                 // '/'
        return 0x5C;                // '\'
    if (c >= 0x61 && c <= 0x7A)     // a-z
        return c - 0x20;

    return c;
}

// Storm string hash (noCaseconv = false -> upper/backslash before hashing), uint32 arithmetic
function tr_sstrHash(str, noCaseconv, seed)
{
    if (!seed)
        seed = 0x7FED7FED;

    var shift = 0xEEEEEEEE;
    var bytes = new TextEncoder().encode(str);      // UTF-8 bytes, like the client's char*
    for (var i = 0; i < bytes.length; ++i)
    {
        var c = bytes[i];
        if (!noCaseconv)
            c = tr_upperBackslash(c);

        seed  = ((tr_hashTable[c >> 4] - tr_hashTable[c & 0xF]) ^ (shift + seed)) >>> 0;
        shift = (c + seed + 33 * shift + 3) >>> 0;
    }

    return seed || 1;
}

// CopyWordCase: an upper-case source character forces the replacement character to upper case
function tr_copyCase(src, dst)
{
    var s = Array.from(src), d = Array.from(dst), out = '';
    var n = Math.min(s.length, d.length);

    for (var i = 0; i < n; ++i)
        out += s[i] !== s[i].toLowerCase() ? d[i].toUpperCase() : d[i].toLowerCase();

    for (var i = n; i < d.length; ++i)
        out += d[i];

    return out;
}

function tr_translate(text, lang, skill)
{
    var words = (typeof g_languageWords !== 'undefined') ? g_languageWords[lang] : null;

    // only letters survive - symbols and numbers are stripped, the whitespace between words stays
    text = String(text).replace(/[^\p{L}\s]/gu, '');

    if (!words)
        return text;

    return text.replace(/\p{L}+/gu, function (word)
    {
        var hash = tr_sstrHash(word, false, 0);
        if (hash % 300 < skill)
            return word;                                // understood - keep as-is

        var len = Math.min(Array.from(word).length, 18);
        var group = null;
        for (var l = len; l >= 1; --l)
        {
            if (words[l] && words[l].length)
            {
                group = words[l];
                break;
            }
        }

        if (!group)
            return word;                                // no word of a fitting length - not modified

        return tr_copyCase(word, group[hash % group.length]);
    });
}

function tr_render()
{
    var text  = document.getElementById('tr-text').value;
    var lang  = parseInt(document.getElementById('tr-lang').value, 10) || 0;
    var skill = parseInt(document.getElementById('tr-skill').value, 10) || 0;

    if (skill < 0)
        skill = 0;
    else if (skill > 300)
        skill = 300;

    document.getElementById('tr-result').textContent = tr_translate(text, lang, skill);
}

function tr_writeHash()
{
    var text  = document.getElementById('tr-text').value;
    var lang  = document.getElementById('tr-lang').value;
    var skill = document.getElementById('tr-skill').value;

    var hash = '#t=' + encodeURIComponent(text) + '&l=' + lang + '&s=' + skill;

    if (location.hash !== hash)
        location.replace(hash);
}

function tr_readHash()
{
    if (!location.hash || location.hash.length < 2)
        return false;

    var params;
    try
    {
        params = new URLSearchParams(location.hash.substring(1));
    }
    catch (e)
    {
        return false;
    }

    var text  = params.get('t') || '';
    var lang  = parseInt(params.get('l'), 10);
    var skill = parseInt(params.get('s'), 10);

    if (isNaN(lang))
        lang = 7;
    if (isNaN(skill))
        skill = 0;

    var textEl  = document.getElementById('tr-text');
    var langEl  = document.getElementById('tr-lang');
    var skillEl = document.getElementById('tr-skill');

    if (textEl.value !== text)
        textEl.value = text;
    if (langEl.value !== String(lang))
        langEl.value = lang;
    if (skillEl.value !== String(skill))
        skillEl.value = skill;

    tr_render();

    return true;
}

function tr_onInput()
{
    tr_render();
    tr_writeHash();
}

function tr_init()
{
    if (!tr_readHash())
        tr_render();

    document.getElementById('tr-text').addEventListener('input', tr_onInput);
    document.getElementById('tr-lang').addEventListener('change', tr_onInput);
    document.getElementById('tr-skill').addEventListener('input', tr_onInput);

    window.addEventListener('hashchange', tr_readHash);
}
