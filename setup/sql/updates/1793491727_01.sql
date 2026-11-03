-- aowow - custom: TaxiPathNode.dbc arrival / departure events, fired by transports and read by legacy script reverse lookups
-- re-extract taxipathnode, it gained the arrivalEventId and departureEventId columns
DROP TABLE IF EXISTS `dbc_taxipathnode`;

UPDATE `aowow_dbversion` SET `sql` = CONCAT(IFNULL(`sql`, ''), ' taxi');
