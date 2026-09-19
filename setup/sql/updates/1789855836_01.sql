CREATE TABLE `aowow_taxipath_points` (
  `pathId` smallint(5) unsigned NOT NULL,
  `point`  smallint(5) unsigned NOT NULL,
  `areaId` smallint(5) unsigned NOT NULL,
  `posX`   float unsigned NOT NULL,
  `posY`   float unsigned NOT NULL,
  PRIMARY KEY (`pathId`,`point`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
UPDATE `aowow_dbversion` SET `sql` = CONCAT(IFNULL(`sql`, ''), ' taxi');
