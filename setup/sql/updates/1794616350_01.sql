-- aowow - custom: Spell.dbc target creature type, facing, aura states, interrupt flags and missile speed, read by the spell page
-- re-extract spell, it gained these columns
DROP TABLE IF EXISTS `dbc_spell`;

ALTER TABLE `aowow_spell`
    ADD COLUMN `targetCreatureType` int(10) unsigned NOT NULL DEFAULT 0 AFTER `procFlags`,
    ADD COLUMN `facingCasterFlags` int(10) unsigned NOT NULL DEFAULT 0 AFTER `targetCreatureType`,
    ADD COLUMN `casterAuraState` int(10) unsigned NOT NULL DEFAULT 0 AFTER `facingCasterFlags`,
    ADD COLUMN `targetAuraState` int(10) unsigned NOT NULL DEFAULT 0 AFTER `casterAuraState`,
    ADD COLUMN `casterAuraStateNot` int(10) unsigned NOT NULL DEFAULT 0 AFTER `targetAuraState`,
    ADD COLUMN `targetAuraStateNot` int(10) unsigned NOT NULL DEFAULT 0 AFTER `casterAuraStateNot`,
    ADD COLUMN `interruptFlags` int(10) unsigned NOT NULL DEFAULT 0 AFTER `targetAuraStateNot`,
    ADD COLUMN `auraInterruptFlags` int(10) unsigned NOT NULL DEFAULT 0 AFTER `interruptFlags`,
    ADD COLUMN `channelInterruptFlags` int(10) unsigned NOT NULL DEFAULT 0 AFTER `auraInterruptFlags`,
    ADD COLUMN `speed` float NOT NULL DEFAULT 0 AFTER `channelInterruptFlags`
;

UPDATE `aowow_dbversion` SET `sql` = CONCAT(IFNULL(`sql`, ''), ' spell');
