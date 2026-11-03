-- aowow - custom: AreaTable.dbc exploration level, read by the zone page's exploration xp
-- re-extract areatable, it gained the explorationLevel column
DROP TABLE IF EXISTS `dbc_areatable`;

ALTER TABLE `aowow_zones`
    ADD COLUMN `explorationLevel` tinyint(3) unsigned NOT NULL DEFAULT 0 AFTER `name_loc8`
;

UPDATE `aowow_dbversion` SET `sql` = CONCAT(IFNULL(`sql`, ''), ' zones');
