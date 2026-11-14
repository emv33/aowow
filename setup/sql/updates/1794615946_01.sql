-- aowow - custom: Vehicle.dbc seat ids, so the npc page can resolve vehicle_seat_addon
UPDATE `aowow_dbversion` SET `sql` = CONCAT(IFNULL(`sql`, ''), ' vehicleseats');
