-- aowow - custom: the cursor names (IconName) of creatures and gameobjects, numbered for the npc and object filter dropdowns
DROP TABLE IF EXISTS `aowow_cursors`;
CREATE TABLE `aowow_cursors` (
  `id` smallint(5) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(64) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

UPDATE `aowow_dbversion` SET `sql` = CONCAT(IFNULL(`sql`, ''), ' cursors');
