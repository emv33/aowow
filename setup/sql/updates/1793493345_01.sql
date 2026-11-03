-- aowow - custom: GameObjectDisplayInfo.dbc model file, the object page's "Same model as" tab matches on it
ALTER TABLE `aowow_objects`
    ADD COLUMN `modelId` mediumint(8) unsigned NOT NULL DEFAULT 0 AFTER `StringId`,
    ADD KEY `idx_modelid` (`modelId`)
;

UPDATE `aowow_dbversion` SET `sql` = CONCAT(IFNULL(`sql`, ''), ' objects');
