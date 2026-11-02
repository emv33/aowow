-- aowow - custom: Holidays.dbc durations and dates, read by the events step for holiday stage times
-- re-extract holidays, it gained the duration and date columns
DROP TABLE IF EXISTS `dbc_holidays`;

UPDATE `aowow_dbversion` SET `sql` = CONCAT(IFNULL(`sql`, ''), ' holidays events');
