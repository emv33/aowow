<?php

namespace Aowow;

if (!defined('AOWOW_REVISION'))
    die('illegal access');


/*
 * The client-side language scramble (ChatTranslate::Translate).
 *
 * WoW does not translate text between languages: a listener who does not know the language a
 * message is spoken in sees every word replaced by a deterministic look-alike of the same
 * length. The lookup table is LanguageWords.dbc (language id, word), grouped per language and
 * per byte length, and the replacement index is SStrHash(word) % group size - the same Storm
 * string hash the client uses elsewhere. The core never implements this (it is display-only in
 * the client); the algorithm is mirrored here from the decompiled client via wowdev.
 *
 * https://wowdev.wiki/DB/LanguageWords  https://wowdev.wiki/SStrHash
 */
class LanguageTranslate
{
    private const string DBC_TABLE = 'dbc_languagewords';

    private const int MAX_WORD_LEN = 18;

    private const array S_HASH_TABLE = [
        0x486E26EE, 0xDCAA16B3, 0xE1918EEF, 0x202DAFDB,
        0x341C7DC7, 0x1C365303, 0x40EF2D37, 0x65FD5E49,
        0xD6057177, 0x904ECE93, 0x1C38024F, 0x98FD323B,
        0xE3061AE7, 0xA39B0FA1, 0x9797F25F, 0xE4444563,
    ];

    private static ?bool $hasDbc  = null;
    private static ?array $words  = null;

    public static function isAvailable() : bool
    {
        return self::$hasDbc ??= (bool)DB::Aowow()->selectCell('SHOW TABLES LIKE %s', self::DBC_TABLE);
    }

    // ChatTranslate::Translate over a plain string (no hyperlink stripping - input is not chat)
    public static function translate(string $text, int $langId, int $skill = 0) : string
    {
        // only letters survive - symbols and numbers are stripped, the whitespace between words stays
        $text = preg_replace('/[^\p{L}\s]/u', '', $text) ?? '';

        $words = self::wordList($langId);
        if (!$words)
            return $text;

        return preg_replace_callback('/\p{L}+/u', function (array $m) use ($words, $skill) : string
        {
            $word = $m[0];

            $hash = self::sstrHash($word, false, 0);
            if ($hash % 300 < $skill)
                return $word;                               // understood - keep as-is

            $len = min(self::mbStrLen($word), self::MAX_WORD_LEN);
            $group = null;
            for ($l = $len; $l >= 1; $l--)
            {
                if (!empty($words[$l]))
                {
                    $group = $words[$l];
                    break;
                }
            }

            if ($group === null)
                return $word;                               // no word of a fitting length - not modified

            return self::copyCase($word, $group[$hash % count($group)]);
        }, $text) ?? $text;
    }

    // Storm string hash (no_caseconv = false -> upper/backslash before hashing), uint32 arithmetic
    public static function sstrHash(string $str, bool $noCaseconv = false, int $seed = 0) : int
    {
        if (!$seed)
            $seed = 0x7FED7FED;

        $shift = 0xEEEEEEEE;
        $len   = strlen($str);

        for ($i = 0; $i < $len; $i++)
        {
            $c = ord($str[$i]);
            if (!$noCaseconv)
                $c = self::upperBackslash($c);

            $seed  = ((self::S_HASH_TABLE[$c >> 4] - self::S_HASH_TABLE[$c & 0xF]) & 0xFFFFFFFF) ^ (($shift + $seed) & 0xFFFFFFFF);
            $shift = ($c + $seed + 33 * $shift + 3) & 0xFFFFFFFF;
        }

        return $seed ?: 1;
    }

    // the whole word list, [language id][word length] => [words], shipped to the page as g_languageWords
    public static function getWords() : array
    {
        self::loadWords();

        return self::$words;
    }

    private static function loadWords() : void
    {
        if (self::$words !== null)
            return;

        self::$words = [];
        if (self::isAvailable())
        {
            foreach (DB::Aowow()->selectAssoc('SELECT `languageId`, `word` FROM '.self::DBC_TABLE.' ORDER BY `id`') ?: [] as $r)
                self::$words[(int)$r['languageId']][strlen($r['word'])][] = $r['word'];
        }
    }

    private static function wordList(int $langId) : array
    {
        self::loadWords();

        return self::$words[$langId] ?? [];
    }

    private static function upperBackslash(int $c) : int
    {
        if ($c === 0x2F)                                    // '/'
            return 0x5C;                                    // '\'
        if ($c >= 0x61 && $c <= 0x7A)                       // a-z
            return $c - 0x20;

        return $c;
    }

    // CopyWordCase: an upper-case source character forces the replacement character to upper case
    private static function copyCase(string $src, string $dst) : string
    {
        $srcChars = preg_split('//u', $src, -1, PREG_SPLIT_NO_EMPTY);
        $dstChars = preg_split('//u', $dst, -1, PREG_SPLIT_NO_EMPTY);

        $out = '';
        $n   = min(count($srcChars), count($dstChars));
        for ($i = 0; $i < $n; $i++)
            $out .= mb_strtolower($srcChars[$i], 'UTF-8') !== $srcChars[$i] ? mb_strtoupper($dstChars[$i], 'UTF-8') : mb_strtolower($dstChars[$i], 'UTF-8');

        for ($i = $n, $c = count($dstChars); $i < $c; $i++)
            $out .= $dstChars[$i];

        return $out;
    }

    private static function mbStrLen(string $str) : int
    {
        return mb_strlen($str, 'UTF-8');
    }
}

?>
