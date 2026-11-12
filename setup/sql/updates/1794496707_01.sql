DROP TABLE IF EXISTS `aowow_creature_difficulty`;
CREATE TABLE `aowow_creature_difficulty` (
  `id` mediumint(8) unsigned NOT NULL,
  `parentId` mediumint(8) unsigned NOT NULL COMMENT 'entry holding the difficulty_entry_* columns',
  `slot` tinyint(3) unsigned NOT NULL COMMENT '0: the parent itself; 1-3: its difficulty_entry_<slot>',
  `kind` tinyint(3) unsigned NOT NULL COMMENT '0: unknown; 1: dungeon; 2: raid',
  `modeMask` tinyint(3) unsigned NOT NULL COMMENT 'bit n: used at map difficulty n',
  PRIMARY KEY (`id`),
  KEY `idx_parent` (`parentId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

UPDATE `aowow_dbversion` SET `sql` = CONCAT(IFNULL(`sql`, ''), ' creaturedifficulty');
UPDATE `aowow_dbversion` SET `build` = CONCAT(IFNULL(`build`, ''), ' globaljs');
