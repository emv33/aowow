<?php

namespace Aowow;

if (!defined('AOWOW_REVISION'))
    die('illegal access');

if (!CLI)
    die('not in cli mode');


// aowow - custom: max. skill values per step, read by SPELL_EFFECT_SKILL_STEP (Spell::EffectLearnSkill)
CLISetup::registerSetup("sql", new class extends SetupScript
{
    use TrDBCcopy;

    protected string $command        = 'skilltiers';
    protected array  $dbcSourceFiles = ['skilltiers'];
});

?>
