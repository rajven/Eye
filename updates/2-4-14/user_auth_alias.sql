DELETE FROM `User_auth_alias` WHERE `auth_id` in (SELECT `id` FROM `User_auth` WHERE `deleted`=1);
DELETE FROM `User_auth_alias` WHERE `auth_id` in (SELECT `id` FROM `User_auth` WHERE `dns_name`='' or `dns_name` IS NULL);
