ALTER TABLE `aowow_zones`
    MODIFY COLUMN `id` smallint(6) NOT NULL COMMENT 'Zone Id; < 0: continent (what lies on it outside every zone)';
ALTER TABLE `aowow_spawns`
    MODIFY COLUMN `areaId` smallint(6) NOT NULL DEFAULT 0;
ALTER TABLE `aowow_creature_waypoints`
    MODIFY COLUMN `areaId` smallint(6) NOT NULL;
ALTER TABLE `aowow_taxinodes`
    MODIFY COLUMN `areaId` smallint(6) NOT NULL;
ALTER TABLE `aowow_taxipath_points`
    MODIFY COLUMN `areaId` smallint(6) NOT NULL;
ALTER TABLE `aowow_source`
    MODIFY COLUMN `moreZoneId` mediumint(9) DEFAULT NULL;
UPDATE `aowow_dbversion` SET `sql` = CONCAT(IFNULL(`sql`, ''), ' zones spawns taxi source');
