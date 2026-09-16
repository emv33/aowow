ALTER TABLE `aowow_creature_waypoints`
    ADD COLUMN `kind` tinyint(3) unsigned NOT NULL DEFAULT 0 COMMENT '0: waypoint_data path, 1: script_waypoint escort path' AFTER `creatureOrPath`,
    DROP PRIMARY KEY,
    ADD PRIMARY KEY (`kind`,`creatureOrPath`,`point`,`areaId`,`floor`);
UPDATE `aowow_dbversion` SET `sql` = CONCAT(IFNULL(`sql`, ''), ' spawns');
