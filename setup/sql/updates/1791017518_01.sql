ALTER TABLE aowow_spell
    ADD COLUMN `areaGroupId` smallint(5) unsigned NOT NULL DEFAULT 0 AFTER `trainingCost`
;

UPDATE `aowow_dbversion` SET `sql` = CONCAT(IFNULL(`sql`, ''), ' areagroup spell');
