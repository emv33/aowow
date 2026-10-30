-- aowow - custom: Spell.dbc procTypeMask, read by the spell page's proc conditions
-- re-extract spell, it gained the procTypeMask column
DROP TABLE IF EXISTS `dbc_spell`;

ALTER TABLE `aowow_spell`
    ADD COLUMN `procFlags` int(10) unsigned NOT NULL DEFAULT 0 AFTER `areaGroupId`
;

UPDATE `aowow_dbversion` SET `sql` = CONCAT(IFNULL(`sql`, ''), ' spell');
