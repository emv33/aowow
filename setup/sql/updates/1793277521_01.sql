-- aowow - custom: max. skill per learn skill step (SkillRaceClassInfo.SkillTierID -> SkillTiers.dbc)
-- re-extract skillraceclassinfo, it gained the skillTierId column
DROP TABLE IF EXISTS `dbc_skillraceclassinfo`;

ALTER TABLE `aowow_skillline`
    ADD COLUMN `skillTierId` smallint(5) unsigned NOT NULL DEFAULT 0 AFTER `specializations`
;

UPDATE `aowow_dbversion` SET `sql` = CONCAT(IFNULL(`sql`, ''), ' skilltiers skillline');
