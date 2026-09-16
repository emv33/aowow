ALTER TABLE `aowow_factiontemplate`
    ADD COLUMN `friendFactionId1` smallint(5) unsigned NOT NULL DEFAULT 0 AFTER `H`,
    ADD COLUMN `friendFactionId2` smallint(5) unsigned NOT NULL DEFAULT 0 AFTER `friendFactionId1`,
    ADD COLUMN `friendFactionId3` smallint(5) unsigned NOT NULL DEFAULT 0 AFTER `friendFactionId2`,
    ADD COLUMN `friendFactionId4` smallint(5) unsigned NOT NULL DEFAULT 0 AFTER `friendFactionId3`,
    ADD COLUMN `enemyFactionId1` smallint(5) unsigned NOT NULL DEFAULT 0 AFTER `friendFactionId4`,
    ADD COLUMN `enemyFactionId2` smallint(5) unsigned NOT NULL DEFAULT 0 AFTER `enemyFactionId1`,
    ADD COLUMN `enemyFactionId3` smallint(5) unsigned NOT NULL DEFAULT 0 AFTER `enemyFactionId2`,
    ADD COLUMN `enemyFactionId4` smallint(5) unsigned NOT NULL DEFAULT 0 AFTER `enemyFactionId3`;
ALTER TABLE `aowow_factions`
    ADD COLUMN `friendFactionIds` varchar(255) NOT NULL DEFAULT '' COMMENT 'space separated' AFTER `templateIds`,
    ADD COLUMN `enemyFactionIds` varchar(255) NOT NULL DEFAULT '' COMMENT 'space separated' AFTER `friendFactionIds`;
UPDATE `aowow_dbversion` SET `sql` = CONCAT(IFNULL(`sql`, ''), ' factions');
