ALTER TABLE `aowow_factions`
    ADD COLUMN `likedByFactionIds` varchar(255) NOT NULL DEFAULT '' COMMENT 'space separated' AFTER `enemyFactionIds`,
    ADD COLUMN `hatedByFactionIds` varchar(255) NOT NULL DEFAULT '' COMMENT 'space separated' AFTER `likedByFactionIds`;
UPDATE `aowow_dbversion` SET `sql` = CONCAT(IFNULL(`sql`, ''), ' factions');
