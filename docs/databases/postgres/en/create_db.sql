-- Enable required extensions
CREATE EXTENSION IF NOT EXISTS pg_trgm;
CREATE EXTENSION IF NOT EXISTS btree_gin;
CREATE EXTENSION IF NOT EXISTS ip4r;

-- Access Control List
CREATE TABLE acl (
id SERIAL PRIMARY KEY,
name VARCHAR(30) NOT NULL,
"description.english" VARCHAR(250) NOT NULL,
"description.russian" VARCHAR(250) NOT NULL
);
COMMENT ON TABLE acl IS 'Access Control List - roles and permissions';
COMMENT ON COLUMN acl."description.english" IS 'Description in English';
COMMENT ON COLUMN acl."description.russian" IS 'Description in Russian';

-- Active Directory computer cache
CREATE TABLE ad_comp_cache (
id SERIAL PRIMARY KEY,
name VARCHAR(63) NOT NULL UNIQUE,
last_found TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);
COMMENT ON TABLE ad_comp_cache IS 'Cache of computers from Active Directory';
COMMENT ON COLUMN ad_comp_cache.name IS 'Computer name in AD';
COMMENT ON COLUMN ad_comp_cache.last_found IS 'Last time this computer was found';

-- Authentication rules
CREATE TABLE auth_rules (
id SERIAL PRIMARY KEY,
user_id INTEGER,
ou_id INTEGER,
type SMALLINT NOT NULL,
rule VARCHAR(40) UNIQUE,
comment VARCHAR(250)
);
COMMENT ON TABLE auth_rules IS 'User authentication and authorization rules';
COMMENT ON COLUMN auth_rules.type IS 'Rule type: 0=allow, 1=deny, etc.';
COMMENT ON COLUMN auth_rules.rule IS 'Rule identifier (unique)';

-- Buildings
CREATE TABLE building (
id SERIAL PRIMARY KEY,
name VARCHAR(50) NOT NULL,
comment VARCHAR(250)
);
COMMENT ON TABLE building IS 'Physical buildings/locations';
COMMENT ON COLUMN building.name IS 'Building name';

-- System configuration
CREATE TABLE config (
id SERIAL PRIMARY KEY,
option_id INTEGER,
value VARCHAR(250)
);
COMMENT ON TABLE config IS 'System configuration values';

-- Configuration options
CREATE TABLE config_options (
id SERIAL PRIMARY KEY,
option_name VARCHAR(50) NOT NULL,
"description.russian" TEXT,
"description.english" TEXT,
draft SMALLINT NOT NULL DEFAULT 0,
uniq SMALLINT NOT NULL DEFAULT 1,
type VARCHAR(100) NOT NULL,
default_value VARCHAR(250),
min_value INTEGER NOT NULL DEFAULT 0,
max_value INTEGER NOT NULL DEFAULT 0
);
COMMENT ON TABLE config_options IS 'Available configuration options';
COMMENT ON COLUMN config_options.option_name IS 'Option name/key';
COMMENT ON COLUMN config_options.draft IS 'Is option in draft state';
COMMENT ON COLUMN config_options.uniq IS 'Is option unique (single value)';

-- Network connections
CREATE TABLE connections (
id BIGSERIAL PRIMARY KEY,
device_id BIGINT NOT NULL,
port_id BIGINT NOT NULL,
auth_id BIGINT NOT NULL,
last_found TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);
COMMENT ON TABLE connections IS 'Current network connections (MAC-IP-device-port)';
COMMENT ON COLUMN connections.device_id IS 'Network device ID';
COMMENT ON COLUMN connections.port_id IS 'Device port ID';
COMMENT ON COLUMN connections.auth_id IS 'User authorization ID';
COMMENT ON COLUMN connections.last_found IS 'Last time this connection was active';

-- System customers/users
CREATE TABLE Customers (
id SERIAL PRIMARY KEY,
Login VARCHAR(20),
comment VARCHAR(100),
password VARCHAR(255),
api_key VARCHAR(255),
rights SMALLINT NOT NULL DEFAULT 3
);
COMMENT ON TABLE Customers IS 'System users/administrators';
COMMENT ON COLUMN Customers.Login IS 'User login name';
COMMENT ON COLUMN Customers.rights IS 'Access rights level: 0=view, 1=operator, 2=admin, 3=superadmin';

-- Network devices
CREATE TABLE devices (
id SERIAL PRIMARY KEY,
device_type INTEGER NOT NULL DEFAULT 1,
device_model_id INTEGER DEFAULT 89,
firmware VARCHAR(100),
vendor_id INTEGER NOT NULL DEFAULT 1,
device_name VARCHAR(50),
building_id INTEGER NOT NULL DEFAULT 1,
ip INET,
ip_int BIGINT,
login VARCHAR(50),
password VARCHAR(255),
protocol SMALLINT NOT NULL DEFAULT 0,
control_port INTEGER NOT NULL DEFAULT 23,
port_count INTEGER NOT NULL DEFAULT 0,
SN VARCHAR(80),
comment VARCHAR(255),
snmp_version SMALLINT NOT NULL DEFAULT 0,
snmp3_auth_proto VARCHAR(10) NOT NULL DEFAULT 'sha512',
snmp3_priv_proto VARCHAR(10) NOT NULL DEFAULT 'aes128',
snmp3_user_rw VARCHAR(20),
snmp3_user_rw_password VARCHAR(20),
snmp3_user_ro VARCHAR(20),
snmp3_user_ro_password VARCHAR(20),
community VARCHAR(50) NOT NULL DEFAULT 'public',
rw_community VARCHAR(50) NOT NULL DEFAULT 'private',
fdb_snmp_index SMALLINT NOT NULL DEFAULT 0,
discovery SMALLINT NOT NULL DEFAULT 1,
netflow_save SMALLINT NOT NULL DEFAULT 0,
user_acl SMALLINT NOT NULL DEFAULT 0,
dhcp SMALLINT NOT NULL DEFAULT 0,
nagios SMALLINT NOT NULL DEFAULT 0,
active SMALLINT NOT NULL DEFAULT 1,
nagios_status VARCHAR(10) NOT NULL DEFAULT 'UP',
queue_enabled SMALLINT NOT NULL DEFAULT 0,
connected_user_only SMALLINT NOT NULL DEFAULT 1,
user_id INTEGER,
deleted SMALLINT NOT NULL DEFAULT 0,
discovery_locked SMALLINT NOT NULL DEFAULT 0,
locked_timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
COMMENT ON TABLE devices IS 'Network devices (switches, routers, etc.)';
COMMENT ON COLUMN devices.device_type IS 'Device type ID';
COMMENT ON COLUMN devices.ip IS 'Device management IP address';
COMMENT ON COLUMN devices.snmp_version IS 'SNMP version: 0=disabled, 1=v1, 2=v2c, 3=v3';
COMMENT ON COLUMN devices.discovery IS 'Enable automatic discovery';
COMMENT ON COLUMN devices.active IS 'Is device active and monitored';

-- Device filter instances
CREATE TABLE device_filter_instances (
id SERIAL PRIMARY KEY,
instance_id INTEGER,
device_id INTEGER
);
COMMENT ON TABLE device_filter_instances IS 'Filter instances assigned to devices';

-- Device L3 interfaces
CREATE TABLE device_l3_interfaces (
id SERIAL PRIMARY KEY,
device_id INTEGER,
snmpin INTEGER,
interface_type SMALLINT NOT NULL DEFAULT 0,
name VARCHAR(100)
);
COMMENT ON TABLE device_l3_interfaces IS 'Layer 3 interfaces on devices';
COMMENT ON COLUMN device_l3_interfaces.interface_type IS 'Interface type: 0=unknown, 1=LAN, 2=WAN, 3=DMZ';

-- Device models
CREATE TABLE device_models (
id SERIAL PRIMARY KEY,
model_name VARCHAR(200),
vendor_id INTEGER DEFAULT 1,
poe_in SMALLINT NOT NULL DEFAULT 0,
poe_out SMALLINT NOT NULL DEFAULT 0,
nagios_template VARCHAR(200)
);
COMMENT ON TABLE device_models IS 'Device models and specifications';
COMMENT ON COLUMN device_models.poe_in IS 'Supports Power over Ethernet input';
COMMENT ON COLUMN device_models.poe_out IS 'Provides Power over Ethernet';

-- Device ports
CREATE TABLE device_ports (
id BIGSERIAL PRIMARY KEY,
device_id INTEGER,
snmp_index INTEGER,
port INTEGER,
ifName VARCHAR(40),
port_name VARCHAR(40),
comment VARCHAR(50),
target_port_id INTEGER NOT NULL DEFAULT 0,
auth_id BIGINT,
last_mac_count INTEGER DEFAULT 0,
uplink SMALLINT NOT NULL DEFAULT 0,
nagios SMALLINT NOT NULL DEFAULT 0,
skip SMALLINT NOT NULL DEFAULT 0,
vlan INTEGER NOT NULL DEFAULT 1,
tagged_vlan VARCHAR(250),
untagged_vlan VARCHAR(250),
forbidden_vlan VARCHAR(250)
);
COMMENT ON TABLE device_ports IS 'Network device ports/interfaces';
COMMENT ON COLUMN device_ports.port IS 'Physical port number';
COMMENT ON COLUMN device_ports.uplink IS 'Is this an uplink port';
COMMENT ON COLUMN device_ports.vlan IS 'Default/native VLAN ID';

-- Device types
CREATE TABLE device_types (
id SERIAL PRIMARY KEY,
"name.russian" VARCHAR(50),
"name.english" VARCHAR(50)
);
COMMENT ON TABLE device_types IS 'Device type classification';
COMMENT ON COLUMN device_types."name.russian" IS 'Device type name in Russian';
COMMENT ON COLUMN device_types."name.english" IS 'Device type name in English';

-- DHCP logs
CREATE TABLE dhcp_log (
id BIGSERIAL PRIMARY KEY,
mac MACADDR NOT NULL,
ip_int BIGINT NOT NULL,
ip INET NOT NULL,
action VARCHAR(10) NOT NULL,
timestamp TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
auth_id BIGINT NOT NULL,
dhcp_hostname VARCHAR(250),
"circuit-id" VARCHAR(255),
"remote-id" VARCHAR(255),
"client-id" VARCHAR(250)
);
COMMENT ON TABLE dhcp_log IS 'DHCP server transaction logs';
COMMENT ON COLUMN dhcp_log.action IS 'DHCP action: DISCOVER, REQUEST, ACK, NAK, RELEASE';
COMMENT ON COLUMN dhcp_log."circuit-id" IS 'DHCP option 82 circuit ID';

-- DHCP queue
CREATE TABLE dhcp_queue (
id BIGSERIAL PRIMARY KEY,
mac MACADDR NOT NULL,
ip INET NOT NULL,
action VARCHAR(10) NOT NULL,
timestamp TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
dhcp_hostname VARCHAR(250)
);
COMMENT ON TABLE dhcp_queue IS 'Pending DHCP operations queue';

-- DNS cache
CREATE TABLE dns_cache (
id BIGSERIAL PRIMARY KEY,
dns VARCHAR(250),
ip BIGINT,
timestamp TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);
COMMENT ON TABLE dns_cache IS 'DNS resolution cache';

-- DNS queue
CREATE TABLE dns_queue (
id SERIAL PRIMARY KEY,
auth_id INTEGER,
name_type VARCHAR(10) NOT NULL DEFAULT 'A',
name VARCHAR(200),
type VARCHAR(10) NOT NULL DEFAULT 'add',
value VARCHAR(100)
);
COMMENT ON TABLE dns_queue IS 'Pending DNS operations queue';
COMMENT ON COLUMN dns_queue.name_type IS 'DNS record type: A, AAAA, PTR, CNAME';
COMMENT ON COLUMN dns_queue.type IS 'Operation type: add, delete, update';

-- Filter instances
CREATE TABLE filter_instances (
id SERIAL PRIMARY KEY,
name VARCHAR(50) UNIQUE,
comment VARCHAR(200)
);
COMMENT ON TABLE filter_instances IS 'Filter policy instances';

-- Filter rules list
CREATE TABLE Filter_list (
id SERIAL PRIMARY KEY,
name VARCHAR(50),
comment VARCHAR(250),
proto VARCHAR(10),
dst TEXT,
dstport VARCHAR(20),
srcport VARCHAR(20),
type SMALLINT NOT NULL DEFAULT 0
);
COMMENT ON TABLE Filter_list IS 'Firewall/filter rules';
COMMENT ON COLUMN Filter_list.proto IS 'Protocol: tcp, udp, icmp, etc.';
COMMENT ON COLUMN Filter_list.dst IS 'Destination IP/CIDR';
COMMENT ON COLUMN Filter_list.type IS 'Rule type: 0=allow, 1=deny';

-- Gateway subnets
CREATE TABLE gateway_subnets (
id SERIAL PRIMARY KEY,
device_id INTEGER,
subnet_id INTEGER
);
COMMENT ON TABLE gateway_subnets IS 'Which devices serve as gateways for which subnets';

-- Filter assignments to groups
CREATE TABLE Group_filters (
id SERIAL PRIMARY KEY,
group_id INTEGER NOT NULL DEFAULT 0,
filter_id INTEGER NOT NULL DEFAULT 0,
"order" INTEGER NOT NULL DEFAULT 0,
action SMALLINT NOT NULL DEFAULT 0
);
COMMENT ON TABLE Group_filters IS 'Filter rules assigned to groups';
COMMENT ON COLUMN Group_filters."order" IS 'Rule processing order';
COMMENT ON COLUMN Group_filters.action IS 'Action: 1=allow, 0=deny';

-- Filter groups
CREATE TABLE Group_list (
id SERIAL PRIMARY KEY,
instance_id INTEGER NOT NULL DEFAULT 1,
group_name VARCHAR(50),
comment VARCHAR(250)
);
COMMENT ON TABLE Group_list IS 'Filter policy groups';

-- MAC address history
CREATE TABLE mac_history (
id BIGSERIAL PRIMARY KEY,
mac VARCHAR(12),
timestamp TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
device_id BIGINT,
port_id BIGINT,
ip VARCHAR(16) NOT NULL DEFAULT '',
auth_id BIGINT,
dhcp_hostname VARCHAR(250)
);
COMMENT ON TABLE mac_history IS 'Historical MAC address movements';
COMMENT ON COLUMN mac_history.mac IS 'MAC address (12 hex chars)';
COMMENT ON COLUMN mac_history.ip IS 'IP address last used';

-- MAC address vendors
CREATE TABLE mac_vendors (
id SERIAL PRIMARY KEY,
oui VARCHAR(20),
companyName VARCHAR(255),
companyAddress VARCHAR(255)
);
COMMENT ON TABLE mac_vendors IS 'MAC address vendor database';
COMMENT ON COLUMN mac_vendors.oui IS 'Organizationally Unique Identifier (first 6 chars of MAC)';

-- Organizational Units
CREATE TABLE OU (
id SERIAL PRIMARY KEY,
ou_name VARCHAR(40),
comment VARCHAR(250),
default_users SMALLINT NOT NULL DEFAULT 0,
default_hotspot SMALLINT NOT NULL DEFAULT 0,
nagios_dir VARCHAR(255),
nagios_host_use VARCHAR(50),
nagios_ping SMALLINT NOT NULL DEFAULT 1,
nagios_default_service VARCHAR(100),
enabled SMALLINT NOT NULL DEFAULT 0,
filter_group_id INTEGER NOT NULL DEFAULT 0,
queue_id INTEGER NOT NULL DEFAULT 0,
dynamic SMALLINT NOT NULL DEFAULT 0,
life_duration DECIMAL(10,2) NOT NULL DEFAULT 24.00,
parent_id INTEGER
);
COMMENT ON TABLE OU IS 'Organizational Units (departments/groups)';
COMMENT ON COLUMN OU.ou_name IS 'OU name/identifier';
COMMENT ON COLUMN OU.life_duration IS 'Default lease duration in hours for dynamic OUs';

-- Traffic shaping queues
CREATE TABLE Queue_list (
id SERIAL PRIMARY KEY,
queue_name VARCHAR(20) NOT NULL,
Download INTEGER NOT NULL DEFAULT 0,
Upload INTEGER NOT NULL DEFAULT 0
);
COMMENT ON TABLE Queue_list IS 'Traffic shaping bandwidth profiles';
COMMENT ON COLUMN Queue_list.Download IS 'Download speed limit in Kbps';
COMMENT ON COLUMN Queue_list.Upload IS 'Upload speed limit in Kbps';

-- Remote syslog messages
CREATE TABLE remote_syslog (
id BIGSERIAL PRIMARY KEY,
date TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
device_id BIGINT NOT NULL,
ip VARCHAR(15) NOT NULL,
message TEXT NOT NULL
);
COMMENT ON TABLE remote_syslog IS 'Syslog messages from network devices';

-- PHP sessions
CREATE TABLE sessions (
id VARCHAR(128) PRIMARY KEY,
data TEXT NOT NULL,
last_accessed INTEGER NOT NULL
);
COMMENT ON TABLE sessions IS 'PHP session storage';

-- Network subnets
CREATE TABLE subnets (
id SERIAL PRIMARY KEY,
subnet VARCHAR(18),
vlan_tag INTEGER NOT NULL DEFAULT 1,
ip_int_start BIGINT NOT NULL,
ip_int_stop BIGINT NOT NULL,
dhcp_start BIGINT NOT NULL DEFAULT 0,
dhcp_stop BIGINT NOT NULL DEFAULT 0,
dhcp_lease_time INTEGER NOT NULL DEFAULT 480,
gateway BIGINT NOT NULL DEFAULT 0,
office SMALLINT NOT NULL DEFAULT 1,
hotspot SMALLINT NOT NULL DEFAULT 0,
vpn SMALLINT NOT NULL DEFAULT 0,
free SMALLINT NOT NULL DEFAULT 0,
dhcp SMALLINT NOT NULL DEFAULT 1,
static SMALLINT NOT NULL DEFAULT 0,
dhcp_update_hostname SMALLINT NOT NULL DEFAULT 0,
discovery SMALLINT NOT NULL DEFAULT 1,
notify SMALLINT NOT NULL DEFAULT 7,
comment VARCHAR(250)
);
COMMENT ON TABLE subnets IS 'Network subnets configuration';
COMMENT ON COLUMN subnets.subnet IS 'Network in CIDR notation';
COMMENT ON COLUMN subnets.vlan_tag IS 'VLAN ID for this subnet';
COMMENT ON COLUMN subnets.office IS 'Is this an office subnet';
COMMENT ON COLUMN subnets.hotspot IS 'Is this a hotspot/public subnet';
COMMENT ON COLUMN subnets.notify IS 'Bitmask for notifications: 1=email, 2=sms, 4=telegram';

-- Detailed traffic logs
CREATE TABLE Traffic_detail (
id BIGSERIAL PRIMARY KEY,
auth_id BIGINT,
router_id INTEGER NOT NULL DEFAULT 0,
timestamp TIMESTAMP,
proto SMALLINT,
src_ip INTEGER NOT NULL,
dst_ip INTEGER NOT NULL,
src_port INTEGER NOT NULL,
dst_port INTEGER NOT NULL,
bytes BIGINT NOT NULL,
pkt INTEGER NOT NULL DEFAULT 0
);
COMMENT ON TABLE Traffic_detail IS 'Detailed traffic flow records (NetFlow)';
COMMENT ON COLUMN Traffic_detail.proto IS 'IP protocol number';
COMMENT ON COLUMN Traffic_detail.src_ip IS 'Source IP as integer';
COMMENT ON COLUMN Traffic_detail.bytes IS 'Bytes transferred in this flow';

-- Unknown MAC addresses
CREATE TABLE Unknown_mac (
id BIGSERIAL PRIMARY KEY,
mac VARCHAR(12),
port_id BIGINT,
device_id INTEGER,
timestamp TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);
COMMENT ON TABLE Unknown_mac IS 'Recently discovered unknown MAC addresses';

-- User authorization records
CREATE TABLE User_auth (
id SERIAL PRIMARY KEY,
user_id BIGINT NOT NULL DEFAULT 0,
ou_id INTEGER,
ip VARCHAR(18) NOT NULL DEFAULT '',
ip_int BIGINT NOT NULL DEFAULT 0,
save_traf SMALLINT NOT NULL DEFAULT 0,
enabled SMALLINT NOT NULL DEFAULT 0,
dhcp SMALLINT NOT NULL DEFAULT 1,
filter_group_id SMALLINT NOT NULL DEFAULT 0,
dynamic SMALLINT NOT NULL DEFAULT 0,
eof TIMESTAMP,
deleted SMALLINT NOT NULL DEFAULT 0,
comments VARCHAR(250),
dns_name VARCHAR(253),
dns_ptr_only SMALLINT NOT NULL DEFAULT 0,
WikiName VARCHAR(250),
dhcp_acl TEXT,
queue_id INTEGER NOT NULL DEFAULT 0,
mac VARCHAR(20) NOT NULL DEFAULT '',
dhcp_action VARCHAR(10) NOT NULL DEFAULT '',
dhcp_option_set VARCHAR(50),
dhcp_time TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
dhcp_hostname VARCHAR(60),
last_found TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
arp_found TIMESTAMP,
blocked SMALLINT NOT NULL DEFAULT 0,
day_quota INTEGER NOT NULL DEFAULT 0,
month_quota INTEGER NOT NULL DEFAULT 0,
device_model_id INTEGER DEFAULT 87,
firmware VARCHAR(100),
timestamp TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
"client-id" VARCHAR(250),
nagios SMALLINT NOT NULL DEFAULT 0,
nagios_status VARCHAR(10) NOT NULL DEFAULT '',
nagios_handler VARCHAR(50) NOT NULL DEFAULT '',
link_check SMALLINT NOT NULL DEFAULT 0,
changed SMALLINT NOT NULL DEFAULT 0,
dhcp_changed SMALLINT NOT NULL DEFAULT 0,
changed_time TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
created_by VARCHAR(10)
);
COMMENT ON TABLE User_auth IS 'Network user/device authorization records';
COMMENT ON COLUMN User_auth.enabled IS 'Is this authorization active';
COMMENT ON COLUMN User_auth.dynamic IS 'Is this a dynamically created record';
COMMENT ON COLUMN User_auth.day_quota IS 'Daily traffic quota in bytes';
COMMENT ON COLUMN User_auth.nagios IS 'Enable Nagios monitoring for this host';

-- User authorization aliases
CREATE TABLE User_auth_alias (
id SERIAL PRIMARY KEY,
auth_id INTEGER NOT NULL,
alias VARCHAR(100),
description VARCHAR(100),
timestamp TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);
COMMENT ON TABLE User_auth_alias IS 'Aliases/DNS names for authorization records';

-- User list
CREATE TABLE User_list (
id BIGSERIAL PRIMARY KEY,
timestamp TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
login VARCHAR(255),
fio VARCHAR(255),
enabled SMALLINT NOT NULL DEFAULT 1,
blocked SMALLINT NOT NULL DEFAULT 0,
deleted SMALLINT NOT NULL DEFAULT 0,
ou_id INTEGER NOT NULL DEFAULT 0,
device_id INTEGER,
filter_group_id INTEGER NOT NULL DEFAULT 0,
queue_id INTEGER NOT NULL DEFAULT 0,
day_quota INTEGER NOT NULL DEFAULT 0,
month_quota INTEGER NOT NULL DEFAULT 0,
permanent SMALLINT NOT NULL DEFAULT 0
);
COMMENT ON TABLE User_list IS 'User accounts in the system';
COMMENT ON COLUMN User_list.fio IS 'Full name (ФИО)';
COMMENT ON COLUMN User_list.permanent IS 'Is this a permanent user (not dynamic)';

-- User sessions (web interface)
CREATE TABLE user_sessions (
id SERIAL PRIMARY KEY,
session_id VARCHAR(128) NOT NULL,
user_id INTEGER NOT NULL,
ip_address VARCHAR(45) NOT NULL,
user_agent TEXT NOT NULL,
created_at INTEGER NOT NULL,
last_activity INTEGER NOT NULL,
is_active SMALLINT DEFAULT 1
);
COMMENT ON TABLE user_sessions IS 'Web interface user sessions';

-- User traffic statistics
CREATE TABLE User_stats (
id BIGSERIAL PRIMARY KEY,
router_id BIGINT DEFAULT 0,
auth_id BIGINT NOT NULL DEFAULT 0,
timestamp TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
byte_in BIGINT NOT NULL DEFAULT 0,
byte_out BIGINT NOT NULL DEFAULT 0
);
COMMENT ON TABLE User_stats IS 'User traffic statistics (aggregated)';

-- Detailed user statistics
CREATE TABLE User_stats_full (
id BIGSERIAL PRIMARY KEY,
router_id BIGINT DEFAULT 0,
auth_id BIGINT NOT NULL DEFAULT 0,
timestamp TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
byte_in BIGINT NOT NULL DEFAULT 0,
byte_out BIGINT NOT NULL DEFAULT 0,
pkt_in INTEGER,
pkt_out INTEGER,
step SMALLINT NOT NULL DEFAULT 600
);
COMMENT ON TABLE User_stats_full IS 'Detailed user traffic statistics';
COMMENT ON COLUMN User_stats_full.step IS 'Statistics collection interval in seconds';

-- Temporary variables
CREATE TABLE variables (
id SERIAL PRIMARY KEY,
name VARCHAR(30) NOT NULL UNIQUE,
value VARCHAR(255),
clear_time TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
created TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);
COMMENT ON TABLE variables IS 'Temporary system variables and locks';

-- Device vendors
CREATE TABLE vendors (
id SERIAL PRIMARY KEY,
name VARCHAR(40) NOT NULL
);
COMMENT ON TABLE vendors IS 'Network equipment vendors';

-- System version
CREATE TABLE version (
id INTEGER PRIMARY KEY DEFAULT 1,
version VARCHAR(10) NOT NULL DEFAULT '2.4.14'
);
COMMENT ON TABLE version IS 'System version information';

-- WAN interface statistics
CREATE TABLE Wan_stats (
id SERIAL PRIMARY KEY,
time TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
router_id INTEGER,
interface_id INTEGER,
"in" BIGINT NOT NULL DEFAULT 0,
"out" BIGINT NOT NULL DEFAULT 0,
forward_in BIGINT NOT NULL DEFAULT 0,
forward_out BIGINT NOT NULL DEFAULT 0
);
COMMENT ON TABLE Wan_stats IS 'WAN interface traffic statistics';
COMMENT ON COLUMN Wan_stats."in" IS 'Bytes received on WAN interface';
COMMENT ON COLUMN Wan_stats."out" IS 'Bytes transmitted on WAN interface';

-- System activity log
CREATE TABLE worklog (
id BIGSERIAL PRIMARY KEY,
timestamp TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
auth_id BIGINT NOT NULL DEFAULT 0,
customer VARCHAR(50) NOT NULL DEFAULT 'system',
ip VARCHAR(18) NOT NULL DEFAULT '127.0.0.1',
message TEXT NOT NULL,
level SMALLINT NOT NULL DEFAULT 1
);
COMMENT ON TABLE worklog IS 'System activity and audit log';
COMMENT ON COLUMN worklog.level IS 'Log level: 1=info, 2=warning, 3=error, 4=debug';

-- Create indexes (same as original structure)
CREATE INDEX idx_devices_ip ON devices(ip);
CREATE INDEX idx_devices_device_type ON devices(device_type);
CREATE INDEX idx_devices_active ON devices(active) WHERE active = 1;

CREATE INDEX idx_device_ports_device_id ON device_ports(device_id);
CREATE INDEX idx_device_ports_port ON device_ports(port);
CREATE INDEX idx_device_ports_target_port_id ON device_ports(target_port_id);

CREATE INDEX idx_dhcp_log_timestamp ON dhcp_log(timestamp, action);
CREATE INDEX idx_dhcp_queue_timestamp ON dhcp_queue(timestamp, action);

CREATE INDEX idx_dns_cache_dns ON dns_cache(dns, ip);
CREATE INDEX idx_dns_cache_timestamp ON dns_cache(timestamp);

CREATE INDEX idx_mac_history_mac ON mac_history(mac, timestamp);
CREATE INDEX idx_mac_history_ip ON mac_history(ip, timestamp);
CREATE INDEX idx_mac_history_timestamp ON mac_history(timestamp);

CREATE INDEX idx_ou_ou_name_gin ON OU USING GIN(ou_name gin_trgm_ops);

CREATE INDEX idx_subnets_ip_int_start ON subnets(ip_int_start, ip_int_stop);
CREATE INDEX idx_subnets_dhcp ON subnets(dhcp, office, hotspot, static);

CREATE INDEX idx_traffic_detail_src ON Traffic_detail(auth_id, timestamp, router_id, src_ip);
CREATE INDEX idx_traffic_detail_dst ON Traffic_detail(auth_id, timestamp, router_id, dst_ip);

CREATE INDEX idx_unknown_mac_timestamp ON Unknown_mac(timestamp, device_id, port_id, mac);

CREATE INDEX idx_user_auth_main ON User_auth(id, user_id, ip_int, mac, ip, deleted);
CREATE INDEX idx_user_auth_deleted ON User_auth(deleted) WHERE deleted = 0;
CREATE INDEX idx_user_auth_ou_id ON User_auth(ou_id);

CREATE INDEX idx_user_list_main ON User_list(id, ou_id, enabled, blocked, deleted);

CREATE INDEX idx_user_sessions_session_id ON user_sessions(session_id);
CREATE INDEX idx_user_sessions_user_id ON user_sessions(user_id);
CREATE INDEX idx_user_sessions_is_active ON user_sessions(is_active) WHERE is_active = 1;

CREATE INDEX idx_user_stats_timestamp ON User_stats(timestamp, auth_id, router_id);
CREATE INDEX idx_user_stats_full_timestamp ON User_stats_full(timestamp, auth_id, router_id);

CREATE INDEX idx_wan_stats_time ON Wan_stats(time, router_id, interface_id);

CREATE INDEX idx_worklog_customer ON worklog(customer, level, timestamp);
CREATE INDEX idx_worklog_timestamp ON worklog(level, timestamp);
CREATE INDEX idx_worklog_auth_id ON worklog(auth_id, level, timestamp);

