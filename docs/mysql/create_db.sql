SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

CREATE DATABASE IF NOT EXISTS `stat` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `stat`;

CREATE TABLE `acl` (
  `id` int(11) NOT NULL,
  `name` varchar(30) NOT NULL,
  `description.english` varchar(250) NOT NULL,
  `description.russian` varchar(250) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `ad_comp_cache` (
  `id` int(11) NOT NULL,
  `name` varchar(63) NOT NULL,
  `last_found` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `auth_rules` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `ou_id` int(11) DEFAULT NULL,
  `type` int(11) NOT NULL,
  `rule` varchar(40) DEFAULT NULL,
  `comment` varchar(250) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `building` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `comment` varchar(250) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `config` (
  `id` int(11) NOT NULL,
  `option_id` int(11) DEFAULT NULL,
  `value` varchar(250) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `config_options` (
  `id` int(11) NOT NULL,
  `option_name` varchar(50) NOT NULL,
  `description.russian` text DEFAULT NULL,
  `description.english` text DEFAULT NULL,
  `draft` tinyint(1) NOT NULL DEFAULT 0,
  `uniq` tinyint(1) NOT NULL DEFAULT 1,
  `type` varchar(100) NOT NULL,
  `default_value` varchar(250) DEFAULT NULL,
  `min_value` int(11) NOT NULL DEFAULT 0,
  `max_value` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `connections` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `device_id` bigint(20) UNSIGNED NOT NULL,
  `port_id` bigint(20) UNSIGNED NOT NULL,
  `auth_id` bigint(20) UNSIGNED NOT NULL,
  `last_found` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `Customers` (
  `id` int(11) NOT NULL,
  `Login` varchar(20) DEFAULT 'NULL',
  `comment` varchar(100) DEFAULT NULL,
  `password` varchar(255) DEFAULT 'NULL',
  `api_key` varchar(255) DEFAULT NULL,
  `rights` tinyint(1) NOT NULL DEFAULT 3
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `devices` (
  `id` int(11) NOT NULL,
  `device_type` int(11) NOT NULL DEFAULT 1,
  `device_model_id` int(11) DEFAULT 89,
  `firmware` varchar(100) DEFAULT NULL,
  `vendor_id` int(11) NOT NULL DEFAULT 1,
  `device_name` varchar(50) DEFAULT NULL,
  `building_id` int(11) NOT NULL DEFAULT 1,
  `ip` varchar(15) DEFAULT NULL,
  `ip_int` bigint(10) UNSIGNED DEFAULT NULL,
  `login` varchar(50) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `protocol` int(11) NOT NULL DEFAULT 0,
  `control_port` int(11) NOT NULL DEFAULT 23,
  `port_count` int(11) NOT NULL DEFAULT 0,
  `SN` varchar(80) DEFAULT NULL,
  `comment` varchar(255) DEFAULT NULL,
  `snmp_version` tinyint(4) NOT NULL DEFAULT 0,
  `snmp3_auth_proto` varchar(10) NOT NULL DEFAULT 'sha512',
  `snmp3_priv_proto` varchar(10) NOT NULL DEFAULT 'aes128',
  `snmp3_user_rw` varchar(20) DEFAULT NULL,
  `snmp3_user_rw_password` varchar(20) DEFAULT NULL,
  `snmp3_user_ro` varchar(20) DEFAULT NULL,
  `snmp3_user_ro_password` varchar(20) DEFAULT NULL,
  `community` varchar(50) NOT NULL DEFAULT 'public',
  `rw_community` varchar(50) NOT NULL DEFAULT 'private',
  `fdb_snmp_index` tinyint(1) NOT NULL DEFAULT 0,
  `discovery` tinyint(1) NOT NULL DEFAULT 1,
  `netflow_save` tinyint(1) NOT NULL DEFAULT 0,
  `user_acl` tinyint(1) NOT NULL DEFAULT 0,
  `dhcp` tinyint(1) NOT NULL DEFAULT 0,
  `nagios` tinyint(1) NOT NULL DEFAULT 0,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `nagios_status` varchar(10) NOT NULL DEFAULT 'UP',
  `queue_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `connected_user_only` tinyint(1) NOT NULL DEFAULT 1,
  `user_id` int(11) DEFAULT NULL,
  `deleted` tinyint(1) NOT NULL DEFAULT 0,
  `discovery_locked` tinyint(1) NOT NULL DEFAULT 0,
  `locked_timestamp` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `device_filter_instances` (
  `id` int(11) NOT NULL,
  `instance_id` int(11) DEFAULT NULL,
  `device_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `device_l3_interfaces` (
  `id` int(11) NOT NULL,
  `device_id` int(11) DEFAULT NULL,
  `snmpin` int(11) DEFAULT NULL,
  `interface_type` int(11) NOT NULL DEFAULT 0,
  `name` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `device_models` (
  `id` int(11) NOT NULL,
  `model_name` varchar(200) DEFAULT NULL,
  `vendor_id` int(11) DEFAULT 1,
  `poe_in` tinyint(1) NOT NULL DEFAULT 0,
  `poe_out` tinyint(1) NOT NULL DEFAULT 0,
  `nagios_template` varchar(200) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `device_ports` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `device_id` int(11) DEFAULT NULL,
  `snmp_index` int(11) DEFAULT NULL,
  `port` int(11) DEFAULT NULL,
  `ifName` varchar(40) DEFAULT NULL,
  `port_name` varchar(40) DEFAULT NULL,
  `comment` varchar(50) DEFAULT NULL,
  `target_port_id` int(11) NOT NULL DEFAULT 0,
  `auth_id` bigint(20) UNSIGNED DEFAULT NULL,
  `last_mac_count` int(11) DEFAULT 0,
  `uplink` tinyint(1) NOT NULL DEFAULT 0,
  `nagios` tinyint(1) NOT NULL DEFAULT 0,
  `skip` tinyint(1) NOT NULL DEFAULT 0,
  `vlan` int(11) NOT NULL DEFAULT 1,
  `tagged_vlan` varchar(250) DEFAULT NULL,
  `untagged_vlan` varchar(250) DEFAULT NULL,
  `forbidden_vlan` varchar(250) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `device_types` (
  `id` int(11) NOT NULL,
  `name.russian` varchar(50) DEFAULT NULL,
  `name.english` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `dhcp_log` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `mac` varchar(17) NOT NULL,
  `ip_int` bigint(20) UNSIGNED NOT NULL,
  `ip` varchar(15) NOT NULL,
  `action` varchar(10) NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp(),
  `auth_id` bigint(20) UNSIGNED NOT NULL,
  `dhcp_hostname` varchar(250) DEFAULT NULL,
  `circuit-id` varchar(255) DEFAULT NULL,
  `remote-id` varchar(255) DEFAULT NULL,
  `client-id` varchar(250) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci KEY_BLOCK_SIZE=8 ROW_FORMAT=COMPRESSED;

CREATE TABLE `dhcp_queue` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `mac` varchar(17) NOT NULL,
  `ip` varchar(15) NOT NULL,
  `action` varchar(10) NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp(),
  `dhcp_hostname` varchar(250) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci KEY_BLOCK_SIZE=8 ROW_FORMAT=COMPRESSED;

CREATE TABLE `dns_cache` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `dns` varchar(250) DEFAULT NULL,
  `ip` bigint(20) UNSIGNED DEFAULT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `dns_queue` (
  `id` int(11) NOT NULL,
  `auth_id` int(11) DEFAULT NULL,
  `name_type` varchar(10) NOT NULL DEFAULT 'A',
  `name` varchar(200) DEFAULT NULL,
  `type` varchar(10) NOT NULL DEFAULT 'add',
  `value` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `filter_instances` (
  `id` int(11) NOT NULL,
  `name` varchar(50) DEFAULT NULL,
  `comment` varchar(200) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `Filter_list` (
  `id` int(11) NOT NULL,
  `name` varchar(50) DEFAULT NULL,
  `comment` varchar(250) DEFAULT NULL,
  `proto` varchar(10) DEFAULT NULL,
  `dst` text DEFAULT NULL,
  `dstport` varchar(20) DEFAULT NULL,
  `srcport` varchar(20) DEFAULT NULL,
  `type` int(10) UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `gateway_subnets` (
  `id` int(11) NOT NULL,
  `device_id` int(11) DEFAULT NULL,
  `subnet_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `Group_filters` (
  `id` int(11) NOT NULL,
  `group_id` int(11) NOT NULL DEFAULT 0,
  `filter_id` int(11) NOT NULL DEFAULT 0,
  `order` int(11) NOT NULL DEFAULT 0,
  `action` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `Group_list` (
  `id` int(11) NOT NULL,
  `instance_id` int(11) NOT NULL DEFAULT 1,
  `group_name` varchar(50) DEFAULT NULL,
  `comment` varchar(250) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `mac_history` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `mac` varchar(12) DEFAULT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp(),
  `device_id` bigint(20) UNSIGNED DEFAULT NULL,
  `port_id` bigint(20) UNSIGNED DEFAULT NULL,
  `ip` varchar(16) NOT NULL DEFAULT '',
  `auth_id` bigint(20) UNSIGNED DEFAULT NULL,
  `dhcp_hostname` varchar(250) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `mac_vendors` (
  `id` int(11) NOT NULL,
  `oui` varchar(20) DEFAULT NULL,
  `companyName` varchar(255) DEFAULT NULL,
  `companyAddress` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `OU` (
  `id` int(11) NOT NULL,
  `ou_name` varchar(40) DEFAULT NULL,
  `comment` varchar(250) DEFAULT NULL,
  `default_users` tinyint(1) NOT NULL DEFAULT 0,
  `default_hotspot` tinyint(1) NOT NULL DEFAULT 0,
  `nagios_dir` varchar(255) DEFAULT NULL,
  `nagios_host_use` varchar(50) DEFAULT NULL,
  `nagios_ping` tinyint(1) NOT NULL DEFAULT 1,
  `nagios_default_service` varchar(100) DEFAULT NULL,
  `enabled` int(11) NOT NULL DEFAULT 0,
  `filter_group_id` int(11) NOT NULL DEFAULT 0,
  `queue_id` int(11) NOT NULL DEFAULT 0,
  `dynamic` tinyint(1) NOT NULL DEFAULT 0,
  `life_duration` decimal(10,2) NOT NULL DEFAULT 24.00,
  `parent_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `Queue_list` (
  `id` int(11) NOT NULL,
  `queue_name` varchar(20) NOT NULL,
  `Download` int(11) NOT NULL DEFAULT 0,
  `Upload` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `remote_syslog` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `date` timestamp NOT NULL DEFAULT current_timestamp(),
  `device_id` bigint(20) UNSIGNED NOT NULL,
  `ip` varchar(15) NOT NULL,
  `message` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci KEY_BLOCK_SIZE=8 ROW_FORMAT=COMPRESSED;

CREATE TABLE `sessions` (
  `id` varchar(128) NOT NULL,
  `data` text NOT NULL,
  `last_accessed` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `subnets` (
  `id` int(11) NOT NULL,
  `subnet` varchar(18) DEFAULT NULL,
  `vlan_tag` int(11) NOT NULL DEFAULT 1,
  `ip_int_start` bigint(20) NOT NULL,
  `ip_int_stop` bigint(20) NOT NULL,
  `dhcp_start` bigint(20) NOT NULL DEFAULT 0,
  `dhcp_stop` bigint(20) NOT NULL DEFAULT 0,
  `dhcp_lease_time` int(11) NOT NULL DEFAULT 480,
  `gateway` bigint(20) NOT NULL DEFAULT 0,
  `office` tinyint(1) NOT NULL DEFAULT 1,
  `hotspot` tinyint(1) NOT NULL DEFAULT 0,
  `vpn` tinyint(1) NOT NULL DEFAULT 0,
  `free` tinyint(1) NOT NULL DEFAULT 0,
  `dhcp` tinyint(1) NOT NULL DEFAULT 1,
  `static` tinyint(1) NOT NULL DEFAULT 0,
  `dhcp_update_hostname` tinyint(1) NOT NULL DEFAULT 0,
  `discovery` tinyint(1) NOT NULL DEFAULT 1,
  `notify` tinyint(1) NOT NULL DEFAULT 7,
  `comment` varchar(250) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `Traffic_detail` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `auth_id` bigint(20) UNSIGNED DEFAULT NULL,
  `router_id` int(11) NOT NULL DEFAULT 0,
  `timestamp` timestamp NULL DEFAULT NULL,
  `proto` tinyint(3) UNSIGNED DEFAULT NULL,
  `src_ip` int(10) UNSIGNED NOT NULL,
  `dst_ip` int(10) UNSIGNED NOT NULL,
  `src_port` smallint(5) UNSIGNED NOT NULL,
  `dst_port` smallint(5) UNSIGNED NOT NULL,
  `bytes` bigint(20) NOT NULL,
  `pkt` int(10) UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `Unknown_mac` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `mac` varchar(12) DEFAULT NULL,
  `port_id` bigint(20) UNSIGNED DEFAULT NULL,
  `device_id` int(11) DEFAULT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `User_auth` (
  `id` int(11) NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `ou_id` int(11) DEFAULT NULL,
  `ip` varchar(18) NOT NULL DEFAULT '',
  `ip_int` bigint(10) UNSIGNED NOT NULL DEFAULT 0,
  `save_traf` tinyint(1) NOT NULL DEFAULT 0,
  `enabled` tinyint(1) NOT NULL DEFAULT 0,
  `dhcp` tinyint(1) NOT NULL DEFAULT 1,
  `filter_group_id` tinyint(1) NOT NULL DEFAULT 0,
  `dynamic` tinyint(1) NOT NULL DEFAULT 0,
  `eof` datetime DEFAULT NULL,
  `deleted` tinyint(4) NOT NULL DEFAULT 0,
  `comments` varchar(250) DEFAULT NULL,
  `dns_name` varchar(253) DEFAULT NULL,
  `dns_ptr_only` tinyint(1) NOT NULL DEFAULT 0,
  `WikiName` varchar(250) DEFAULT NULL,
  `dhcp_acl` text DEFAULT NULL,
  `queue_id` int(11) NOT NULL DEFAULT 0,
  `mac` varchar(20) NOT NULL DEFAULT '',
  `dhcp_action` varchar(10) NOT NULL DEFAULT '',
  `dhcp_option_set` varchar(50) DEFAULT NULL,
  `dhcp_time` datetime NOT NULL DEFAULT current_timestamp(),
  `dhcp_hostname` varchar(60) DEFAULT NULL,
  `last_found` datetime NOT NULL DEFAULT current_timestamp(),
  `arp_found` datetime DEFAULT NULL,
  `blocked` tinyint(1) NOT NULL DEFAULT 0,
  `day_quota` int(11) NOT NULL DEFAULT 0,
  `month_quota` int(11) NOT NULL DEFAULT 0,
  `device_model_id` int(11) DEFAULT 87,
  `firmware` varchar(100) DEFAULT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp(),
  `client-id` varchar(250) DEFAULT NULL,
  `nagios` tinyint(1) NOT NULL DEFAULT 0,
  `nagios_status` varchar(10) NOT NULL DEFAULT '',
  `nagios_handler` varchar(50) NOT NULL DEFAULT '',
  `link_check` tinyint(1) NOT NULL DEFAULT 0,
  `changed` tinyint(1) NOT NULL DEFAULT 0,
  `dhcp_changed` int(11) NOT NULL DEFAULT 0,
  `changed_time` datetime NOT NULL DEFAULT current_timestamp(),
  `created_by` varchar(10) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `User_auth_alias` (
  `id` int(11) NOT NULL,
  `auth_id` int(11) NOT NULL,
  `alias` varchar(100) DEFAULT NULL,
  `description` varchar(100) DEFAULT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `User_list` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp(),
  `login` varchar(255) DEFAULT NULL,
  `fio` varchar(255) DEFAULT NULL,
  `enabled` tinyint(1) NOT NULL DEFAULT 1,
  `blocked` tinyint(1) NOT NULL DEFAULT 0,
  `deleted` tinyint(1) NOT NULL DEFAULT 0,
  `ou_id` int(11) NOT NULL DEFAULT 0,
  `device_id` int(11) DEFAULT NULL,
  `filter_group_id` int(11) NOT NULL DEFAULT 0,
  `queue_id` int(11) NOT NULL DEFAULT 0,
  `day_quota` int(11) NOT NULL DEFAULT 0,
  `month_quota` int(11) NOT NULL DEFAULT 0,
  `permanent` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `user_sessions` (
  `id` int(11) NOT NULL,
  `session_id` varchar(128) NOT NULL,
  `user_id` int(11) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `user_agent` text NOT NULL,
  `created_at` int(11) NOT NULL,
  `last_activity` int(11) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `User_stats` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `router_id` bigint(20) UNSIGNED DEFAULT 0,
  `auth_id` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `timestamp` datetime NOT NULL DEFAULT current_timestamp(),
  `byte_in` bigint(20) NOT NULL DEFAULT 0,
  `byte_out` bigint(20) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `User_stats_full` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `router_id` bigint(20) UNSIGNED DEFAULT 0,
  `auth_id` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `timestamp` datetime NOT NULL DEFAULT current_timestamp(),
  `byte_in` bigint(20) NOT NULL DEFAULT 0,
  `byte_out` bigint(20) NOT NULL DEFAULT 0,
  `pkt_in` int(11) DEFAULT NULL,
  `pkt_out` int(11) DEFAULT NULL,
  `step` int(11) NOT NULL DEFAULT 600
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `variables` (
  `id` int(11) NOT NULL,
  `name` varchar(30) NOT NULL,
  `value` varchar(255) DEFAULT NULL,
  `clear_time` timestamp NOT NULL DEFAULT current_timestamp(),
  `created` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `vendors` (
  `id` int(11) NOT NULL,
  `name` varchar(40) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `version` (
  `id` int(11) NOT NULL DEFAULT 1,
  `version` varchar(10) NOT NULL DEFAULT '2.4.14'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `Wan_stats` (
  `id` int(11) NOT NULL,
  `time` datetime NOT NULL DEFAULT current_timestamp(),
  `router_id` int(11) DEFAULT NULL,
  `interface_id` int(11) DEFAULT NULL,
  `in` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `out` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `forward_in` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `forward_out` bigint(20) UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `worklog` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp(),
  `auth_id` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `customer` varchar(50) NOT NULL DEFAULT 'system',
  `ip` varchar(18) NOT NULL DEFAULT '127.0.0.1',
  `message` text NOT NULL,
  `level` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci KEY_BLOCK_SIZE=8 ROW_FORMAT=COMPRESSED;


ALTER TABLE `acl`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `ad_comp_cache`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `comp_name` (`name`);

ALTER TABLE `auth_rules`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `rule` (`rule`),
  ADD KEY `user_id` (`user_id`);

ALTER TABLE `building`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `config`
  ADD PRIMARY KEY (`id`),
  ADD KEY `option` (`option_id`);

ALTER TABLE `config_options`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `connections`
  ADD PRIMARY KEY (`id`),
  ADD KEY `auth_id` (`auth_id`),
  ADD KEY `device_id` (`device_id`,`port_id`);

ALTER TABLE `Customers`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `devices`
  ADD PRIMARY KEY (`id`),
  ADD KEY `ip` (`ip`),
  ADD KEY `device_type` (`device_type`);

ALTER TABLE `device_filter_instances`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `device_l3_interfaces`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `device_models`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `device_ports`
  ADD PRIMARY KEY (`id`),
  ADD KEY `device_id` (`device_id`),
  ADD KEY `port` (`port`),
  ADD KEY `target_port_id` (`target_port_id`);

ALTER TABLE `device_types`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `dhcp_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `timestamp` (`timestamp`,`action`);

ALTER TABLE `dhcp_queue`
  ADD PRIMARY KEY (`id`),
  ADD KEY `timestamp` (`timestamp`,`action`);

ALTER TABLE `dns_cache`
  ADD PRIMARY KEY (`id`),
  ADD KEY `dns` (`dns`,`ip`),
  ADD KEY `timestamp` (`timestamp`);

ALTER TABLE `dns_queue`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `filter_instances`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

ALTER TABLE `Filter_list`
  ADD PRIMARY KEY (`id`),
  ADD KEY `Name` (`name`);

ALTER TABLE `gateway_subnets`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `Group_filters`
  ADD PRIMARY KEY (`id`),
  ADD KEY `GroupId` (`group_id`,`filter_id`);

ALTER TABLE `Group_list`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `mac_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `mac` (`mac`,`timestamp`),
  ADD KEY `ip` (`ip`,`timestamp`),
  ADD KEY `timestamp` (`timestamp`) USING BTREE,
  ADD KEY `mac_2` (`mac`),
  ADD KEY `ip_2` (`ip`);

ALTER TABLE `mac_vendors`
  ADD PRIMARY KEY (`id`),
  ADD KEY `oui` (`oui`);

ALTER TABLE `OU`
  ADD PRIMARY KEY (`id`);
ALTER TABLE `OU` ADD FULLTEXT KEY `ou_name` (`ou_name`);
ALTER TABLE `OU` ADD FULLTEXT KEY `ou_name_2` (`ou_name`);

ALTER TABLE `Queue_list`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `id` (`id`);

ALTER TABLE `remote_syslog`
  ADD PRIMARY KEY (`id`),
  ADD KEY `date` (`date`,`device_id`,`ip`);

ALTER TABLE `sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `last_accessed` (`last_accessed`);

ALTER TABLE `subnets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `ip_int_start` (`ip_int_start`,`ip_int_stop`),
  ADD KEY `dhcp` (`dhcp`,`office`,`hotspot`,`static`);

ALTER TABLE `Traffic_detail`
  ADD PRIMARY KEY (`id`),
  ADD KEY `src` (`auth_id`,`timestamp`,`router_id`,`src_ip`) USING BTREE,
  ADD KEY `dst` (`auth_id`,`timestamp`,`router_id`,`dst_ip`) USING BTREE;

ALTER TABLE `Unknown_mac`
  ADD PRIMARY KEY (`id`),
  ADD KEY `timestamp` (`timestamp`,`device_id`,`port_id`,`mac`);

ALTER TABLE `User_auth`
  ADD PRIMARY KEY (`id`),
  ADD KEY `auth_index` (`id`,`user_id`,`ip_int`,`mac`,`ip`,`deleted`) USING BTREE,
  ADD KEY `deleted` (`deleted`),
  ADD KEY `ou_id` (`ou_id`);

ALTER TABLE `User_auth_alias`
  ADD PRIMARY KEY (`id`),
  ADD KEY `auth_id` (`auth_id`);

ALTER TABLE `User_list`
  ADD PRIMARY KEY (`id`),
  ADD KEY `users` (`id`,`ou_id`,`enabled`,`blocked`,`deleted`);

ALTER TABLE `user_sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `session_id` (`session_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `is_active` (`is_active`);

ALTER TABLE `User_stats`
  ADD PRIMARY KEY (`id`),
  ADD KEY `timestamp` (`timestamp`,`auth_id`,`router_id`);

ALTER TABLE `User_stats_full`
  ADD PRIMARY KEY (`id`),
  ADD KEY `timestamp` (`timestamp`,`auth_id`,`router_id`);

ALTER TABLE `variables`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`),
  ADD KEY `clear_time` (`clear_time`,`created`);

ALTER TABLE `vendors`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `version`
  ADD UNIQUE KEY `id` (`id`);

ALTER TABLE `Wan_stats`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `main` (`time`,`router_id`,`interface_id`),
  ADD KEY `times` (`time`);

ALTER TABLE `worklog`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_customer` (`customer`,`level`,`timestamp`),
  ADD KEY `idx_timestamp` (`level`,`timestamp`),
  ADD KEY `idx_auth_id` (`auth_id`,`level`,`timestamp`);


ALTER TABLE `acl`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `ad_comp_cache`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `auth_rules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `building`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `config`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `config_options`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `connections`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `Customers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `devices`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `device_filter_instances`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `device_l3_interfaces`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `device_models`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `device_ports`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `device_types`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `dhcp_log`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `dhcp_queue`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `dns_cache`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `dns_queue`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `filter_instances`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `Filter_list`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `gateway_subnets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `Group_filters`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `Group_list`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `mac_history`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `mac_vendors`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `OU`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `Queue_list`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `remote_syslog`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `subnets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `Traffic_detail`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `Unknown_mac`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `User_auth`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `User_auth_alias`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `User_list`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `user_sessions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `User_stats`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `User_stats_full`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `variables`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `vendors`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `Wan_stats`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `worklog`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
