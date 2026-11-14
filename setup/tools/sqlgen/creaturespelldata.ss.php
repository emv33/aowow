<?php

namespace Aowow;

if (!defined('AOWOW_REVISION'))
    die('illegal access');

if (!CLI)
    die('not in cli mode');


// aowow - custom: the pet action bar of a creature with creature_template.PetSpellDataId (SpellMgr::LoadPetDefaultSpells())
CLISetup::registerSetup("sql", new class extends SetupScript
{
    use TrDBCcopy;

    protected string $command        = 'creaturespelldata';
    protected array  $dbcSourceFiles = ['creaturespelldata'];
});

?>
