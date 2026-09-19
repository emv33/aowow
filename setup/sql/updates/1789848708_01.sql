ALTER TABLE `aowow_creature_waypoints`
    ADD COLUMN `viaSmartAI` tinyint(3) unsigned NOT NULL DEFAULT 0 COMMENT '0: the creatures own default path (via *_addon); 1: assigned by a SmartAI ACTION_WP_START' AFTER `wait`;
UPDATE `aowow_dbversion` SET `sql` = CONCAT(IFNULL(`sql`, ''), ' spawns');
