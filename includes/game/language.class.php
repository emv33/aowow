<?php

namespace Aowow;

if (!defined('AOWOW_REVISION'))
    die('illegal access');


/*
 * TrinityCore - ObjectMgr.cpp: lang_description[]
 *
 * A language is three things the client keeps tied together: the Languages.dbc id that a
 * creature_text or npc_text line is spoken in, the proficiency spell that grants the language
 * and the SkillLine.dbc row that spell awards. The world db only ever stores the id, the site
 * does not extract Languages.dbc, and the core keeps the association in a fixed table rather
 * than in any data file - so it is mirrored here as the core spells it.
 */
class Language
{
    // Languages.dbc id => [spell id, SkillLine.dbc id]
    private const array DESC = array(
         1 => [  669, 109],     // Orcish
         2 => [  671, 113],     // Darnassian
         3 => [  670, 115],     // Taurahe
         6 => [  672, 111],     // Dwarvish
         7 => [  668,  98],     // Common
         8 => [  815, 139],     // Demonic
         9 => [  816, 140],     // Titan
        10 => [  813, 137],     // Thalassian
        11 => [  814, 138],     // Draconic
        12 => [  817, 141],     // Kalimag
        13 => [ 7340, 313],     // Gnomish
        14 => [ 7341, 315],     // Troll
        33 => [17737, 673],     // Gutterspeak
        35 => [29932, 759],     // Draenei
        36 => [    0,   0],     // Zombie
        37 => [    0,   0],     // Gnomish Binary
        38 => [    0,   0]      // Goblin Binary
    );

    public static function getSpellId(int $id) : int
    {
        return self::DESC[$id][0] ?? 0;
    }

    public static function getSkillId(int $id) : int
    {
        return self::DESC[$id][1] ?? 0;
    }
}

?>
