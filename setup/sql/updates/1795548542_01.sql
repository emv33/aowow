-- aowow - custom: carry the fork's spawn data over to the per-type spawn tables of 1795306151_01
-- continent pseudo zones (< 0) need a signed areaId
ALTER TABLE `aowow_creature_spawns`
    MODIFY COLUMN `areaId` smallint(6) NOT NULL COMMENT '< 0: continent (what lies on it outside every zone)';
ALTER TABLE `aowow_objects_spawns`
    MODIFY COLUMN `areaId` smallint(6) NOT NULL COMMENT '< 0: continent (what lies on it outside every zone)';
ALTER TABLE `aowow_areatrigger_spawns`
    MODIFY COLUMN `areaId` smallint(6) NOT NULL COMMENT '< 0: continent (what lies on it outside every zone)';
ALTER TABLE `aowow_sounds_spawns`
    MODIFY COLUMN `areaId` smallint(6) NOT NULL COMMENT '< 0: continent (what lies on it outside every zone)';
ALTER TABLE `aowow_zones_spawns`
    MODIFY COLUMN `areaId` smallint(6) NOT NULL COMMENT '< 0: continent (what lies on it outside every zone)';
ALTER TABLE `aowow_objects_waypoints`
    MODIFY COLUMN `areaId` smallint(6) NOT NULL COMMENT '< 0: continent (what lies on it outside every zone)';

-- script_waypoint escort paths share the table with waypoint_data paths
ALTER TABLE `aowow_creature_waypoints`
    MODIFY COLUMN `pathId` int(10) unsigned NOT NULL COMMENT 'kind 0: waypoint_data.id; kind 1: script_waypoint.entry',
    ADD COLUMN `kind` tinyint(3) unsigned NOT NULL DEFAULT 0 COMMENT '0: waypoint_data path, 1: script_waypoint escort path' AFTER `pathId`,
    MODIFY COLUMN `areaId` smallint(6) NOT NULL COMMENT '< 0: continent (what lies on it outside every zone)',
    ADD COLUMN `viaSmartAI` tinyint(3) unsigned NOT NULL DEFAULT 0 COMMENT '0: the creatures own default path (via *_addon); 1: assigned by a SmartAI ACTION_WP_START' AFTER `actionChance`,
    DROP PRIMARY KEY,
    ADD PRIMARY KEY (`kind`, `pathId`, `point`);

-- the areatrigger page shows the world position
ALTER TABLE `aowow_areatrigger`
    ADD COLUMN `posX` float NOT NULL DEFAULT 0 AFTER `orientation`,
    ADD COLUMN `posY` float NOT NULL DEFAULT 0 AFTER `posX`;

UPDATE `aowow_dbversion` SET `sql` = CONCAT(IFNULL(`sql`, ''), ' spawns areatrigger');
