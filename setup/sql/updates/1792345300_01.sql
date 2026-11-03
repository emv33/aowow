ALTER TABLE `aowow_taxinodes`
  ADD COLUMN `mountA` mediumint(8) unsigned NOT NULL DEFAULT 0 AFTER `name_loc8`,
  ADD COLUMN `mountH` mediumint(8) unsigned NOT NULL DEFAULT 0 AFTER `mountA`
;

UPDATE `aowow_dbversion` SET `sql` = CONCAT(IFNULL(`sql`, ''), ' taxi');
