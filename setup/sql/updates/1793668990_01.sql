DELETE FROM `aowow_config` WHERE `key` = 'public_access';
INSERT INTO `aowow_config` (`key`, `value`, `default`, `cat`, `flags`, `comment`) VALUES
    ('public_access', 0, 0, 1, 132, 'show every visitor what is otherwise reserved for staff (unlisted entries, debug info, script names, world DB browsers); does not grant any action');
