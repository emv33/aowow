<?php

namespace Aowow;

if (!defined('AOWOW_REVISION'))
    die('illegal access');

if (!CLI)
    die('not in cli mode');


// aowow - custom: the VehicleSeat.dbc ids of each Vehicle.dbc entry, so the npc page can resolve `vehicle_seat_addon`
CLISetup::registerSetup("sql", new class extends SetupScript
{
    use TrDBCcopy;

    protected string $command        = 'vehicleseats';
    protected array  $dbcSourceFiles = ['vehicle'];
});

?>
