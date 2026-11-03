-- aowow - custom: AreaTrigger.dbc z, radius and box dimensions, read by the areatrigger page
-- re-extract areatrigger, it gained the posZ, radius and box columns
DROP TABLE IF EXISTS `dbc_areatrigger`;

ALTER TABLE `aowow_areatrigger`
    ADD COLUMN `posZ`      float NOT NULL DEFAULT 0 AFTER `orientation`,
    ADD COLUMN `radius`    float NOT NULL DEFAULT 0 AFTER `posZ`,
    ADD COLUMN `boxLength` float NOT NULL DEFAULT 0 AFTER `radius`,
    ADD COLUMN `boxWidth`  float NOT NULL DEFAULT 0 AFTER `boxLength`,
    ADD COLUMN `boxHeight` float NOT NULL DEFAULT 0 AFTER `boxWidth`
;

UPDATE `aowow_dbversion` SET `sql` = CONCAT(IFNULL(`sql`, ''), ' areatrigger');
