<?php

namespace Aowow;

if (!defined('AOWOW_REVISION'))
    die('illegal access');

if (!CLI)
    die('not in cli mode');


// aowow - custom: the cursor names (IconName) creature_template and gameobject_template use, numbered for the filter dropdowns
CLISetup::registerSetup("sql", new class extends SetupScript
{
    protected array $info = array(
        'cursors' => [[], CLISetup::ARGV_PARAM, 'Collects the cursor names of creatures and gameobjects for the filter dropdowns.']
    );

    protected array $worldDependency = ['creature_template', 'gameobject_template'];

    public function generate() : bool
    {
        $names = array_merge(
            DB::World()->selectCol('SELECT DISTINCT `IconName` FROM creature_template   WHERE `IconName` <> ""') ?: [],
            DB::World()->selectCol('SELECT DISTINCT `IconName` FROM gameobject_template WHERE `IconName` <> ""') ?: []
        );

        $names = array_values(array_unique(array_map('trim', $names)));
        sort($names, SORT_NATURAL | SORT_FLAG_CASE);

        // existing rows keep their id, so saved filter links stay valid after a resync; names gone from the world db stay too, matching nothing
        foreach ($names as $name)
            if ($name !== '')
                DB::Aowow()->qry('INSERT IGNORE INTO ::cursors (`name`) VALUES (%s)', $name);

        return true;
    }
});

?>
