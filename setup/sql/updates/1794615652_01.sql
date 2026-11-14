-- aowow - custom: CreatureSpellData.dbc, the pet action bar of a creature with PetSpellDataId, read by the npc page
UPDATE `aowow_dbversion` SET `sql` = CONCAT(IFNULL(`sql`, ''), ' creaturespelldata');
