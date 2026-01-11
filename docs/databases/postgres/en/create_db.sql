-- Enable required extensions
CREATE EXTENSION IF NOT EXISTS pg_trgm;
CREATE EXTENSION IF NOT EXISTS btree_gin;

-- Access Control List
CREATE TABLE acl (
id SERIAL PRIMARY KEY,
name VARCHAR(30) NOT NULL,
description_english VARCHAR(250) NOT NULL,
description_russian VARCHAR(250) NOT NULL
);
COMMENT ON TABLE acl IS 'Access Control List - roles and permissions';
COMMENT ON COLUMN acl.description_english IS 'Description in English';
COMMENT ON COLUMN acl.description_russian IS 'Description in Russian';

-- Active Directory computer cache
CREATE TABLE ad_comp_cache (
id SERIAL PRIMARY KEY,
name VARCHAR(63) NOT NULL UNIQUE,
last_found TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);
COMMENT ON TABLE ad_comp_cache IS 'Active Directory computer cache';
COMMENT ON COLUMN ad_comp_cache.name IS 'Computer name in AD';
COMMENT ON COLUMN ad_comp_cache.last_found IS 'Last time this computer was detected';

-- Authentication rules
CREATE TABLE auth_rules (
id SERIAL PRIMARY KEY,
user_id INTEGER,
ou_id INTEGER,
rule_type SMALLINT NOT NULL,
rule VARCHAR(40) UNIQUE,
description VARCHAR(250)
);
COMMENT ON TABLE auth_rules IS 'User authentication and authorization rules';
COMMENT ON COLUMN auth_rules.rule_type IS 'Rule type: 0=allow, 1=deny, etc.';
COMMENT ON COLUMN auth_rules.rule IS 'Rule identifier (unique)';

-- Buildings
CREATE TABLE building (
id SERIAL PRIMARY KEY,
name VARCHAR(50) NOT NULL,
description VARCHAR(250)
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
description_russian TEXT,
description_english TEXT,
draft SMALLINT NOT NULL DEFAULT 0,
uniq SMALLINT NOT NULL DEFAULT 1,
option_type VARCHAR(100) NOT NULL,
default_value VARCHAR(250),
min_value INTEGER NOT NULL DEFAULT 0,
max_value INTEGER NOT NULL DEFAULT 0
);
COMMENT ON TABLE config_options IS 'Available configuration options';
COMMENT ON COLUMN config_options.option_name IS 'Option name/key';
COMMENT ON COLUMN config_options.draft IS 'Option is in draft state';
COMMENT ON COLUMN config_options.uniq IS 'Option is unique (single value)';

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
COMMENT ON COLUMN connections.auth_id IS 'User authentication ID';
COMMENT ON COLUMN connections.last_found IS 'Last connection activity time';

-- System users
CREATE TABLE customers (
id SERIAL PRIMARY KEY,
login VARCHAR(20),
description VARCHAR(100),
password VARCHAR(255),
api_key VARCHAR(255),
rights SMALLINT NOT NULL DEFAULT 3
);
COMMENT ON TABLE customers IS 'System users/administrators';
COMMENT ON COLUMN customers.login IS 'User login';
COMMENT ON COLUMN customers.rights IS 'Access level: 0=view, 1=operator, 2=admin, 3=superadmin';

-- Network devices
CREATE TABLE devices (
id SERIAL PRIMARY KEY,
device_type INTEGER NOT NULL DEFAULT 1,
device_model_id INTEGER DEFAULT 89,
firmware VARCHAR(100),
vendor_id INTEGER NOT NULL DEFAULT 1,
device_name VARCHAR(50),
building_id INTEGER NOT NULL DEFAULT 1,
ip INET DEFAULT NULL,
ip_int BIGINT,
login VARCHAR(50),
password VARCHAR(255),
protocol SMALLINT NOT NULL DEFAULT 0,
control_port INTEGER NOT NULL DEFAULT 23,
port_count INTEGER NOT NULL DEFAULT 0,
SN VARCHAR(80),
description VARCHAR(255),
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
COMMENT ON COLUMN devices.active IS 'Device is active and monitored';

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
COMMENT ON TABLE device_models IS 'Device models and their characteristics';
COMMENT ON COLUMN device_models.poe_in IS 'Supports Power over Ethernet input';
COMMENT ON COLUMN device_models.poe_out IS 'Provides Power over Ethernet output';

-- Device ports
CREATE TABLE device_ports (
id BIGSERIAL PRIMARY KEY,
device_id INTEGER,
snmp_index INTEGER,
port INTEGER,
ifName VARCHAR(40),
port_name VARCHAR(40),
description VARCHAR(50),
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
COMMENT ON TABLE device_ports IS 'Ports/interfaces of network devices';
COMMENT ON COLUMN device_ports.port IS 'Physical port number';
COMMENT ON COLUMN device_ports.uplink IS 'This is an uplink port';
COMMENT ON COLUMN device_ports.vlan IS 'Default/native VLAN';

-- Device types
CREATE TABLE device_types (
id SERIAL PRIMARY KEY,
name_russian VARCHAR(50),
name_english VARCHAR(50)
);
COMMENT ON TABLE device_types IS 'Device type classification';
COMMENT ON COLUMN device_types.name_russian IS 'Device type name in Russian';
COMMENT ON COLUMN device_types.name_english IS 'Device type name in English';

-- DHCP logs
CREATE TABLE dhcp_log (
id BIGSERIAL PRIMARY KEY,
mac MACADDR NOT NULL,
ip_int BIGINT NOT NULL,
ip INET DEFAULT NULL,
action VARCHAR(10) NOT NULL,
ts TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
auth_id BIGINT NOT NULL,
dhcp_hostname VARCHAR(250),
circuit_id VARCHAR(255),
remote_id VARCHAR(255),
client_id VARCHAR(250)
);
COMMENT ON TABLE dhcp_log IS 'DHCP server transaction logs';
COMMENT ON COLUMN dhcp_log.action IS 'DHCP action: DISCOVER, REQUEST, ACK, NAK, RELEASE';
COMMENT ON COLUMN dhcp_log.circuit_id IS 'DHCP option 82 circuit ID';

-- DHCP queue
CREATE TABLE dhcp_queue (
id BIGSERIAL PRIMARY KEY,
mac MACADDR NOT NULL,
ip INET DEFAULT NULL,
action VARCHAR(10) NOT NULL,
ts TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
dhcp_hostname VARCHAR(250)
);
COMMENT ON TABLE dhcp_queue IS 'Queue of deferred DHCP operations';

-- DNS cache
CREATE TABLE dns_cache (
id BIGSERIAL PRIMARY KEY,
dns VARCHAR(250),
ip BIGINT,
ts TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);
COMMENT ON TABLE dns_cache IS 'DNS resolution cache';

-- DNS queue
CREATE TABLE dns_queue (
id SERIAL PRIMARY KEY,
auth_id INTEGER,
name_type VARCHAR(10) NOT NULL DEFAULT 'A',
name VARCHAR(200),
operation_type VARCHAR(10) NOT NULL DEFAULT 'add',
value VARCHAR(100)
);
COMMENT ON TABLE dns_queue IS 'Queue of deferred DNS operations';
COMMENT ON COLUMN dns_queue.name_type IS 'DNS record type: A, AAAA, PTR, CNAME';
COMMENT ON COLUMN dns_queue.operation_type IS 'Operation type: add, delete, update';

-- Filter instances
CREATE TABLE filter_instances (
id SERIAL PRIMARY KEY,
name VARCHAR(50) UNIQUE,
description VARCHAR(200)
);
COMMENT ON TABLE filter_instances IS 'Filtering policy instances';

-- Firewall rule list
CREATE TABLE filter_list (
id SERIAL PRIMARY KEY,
name VARCHAR(50),
description VARCHAR(250),
proto VARCHAR(10),
dst TEXT,
dstport VARCHAR(20),
srcport VARCHAR(20),
filter_type SMALLINT NOT NULL DEFAULT 0
);
COMMENT ON TABLE filter_list IS 'Firewall/filtering rules';
COMMENT ON COLUMN filter_list.proto IS 'Protocol: tcp, udp, icmp, etc.';
COMMENT ON COLUMN filter_list.dst IS 'Destination IP/CIDR';
COMMENT ON COLUMN filter_list.filter_type IS 'Rule type: 0=allow, 1=deny';

-- Subnet gateways
CREATE TABLE gateway_subnets (
id SERIAL PRIMARY KEY,
device_id INTEGER,
subnet_id INTEGER
);
COMMENT ON TABLE gateway_subnets IS 'Which devices act as gateways for which subnets';

-- Group filter assignments
CREATE TABLE group_filters (
id SERIAL PRIMARY KEY,
group_id INTEGER NOT NULL DEFAULT 0,
filter_id INTEGER NOT NULL DEFAULT 0,
rule_order INTEGER NOT NULL DEFAULT 0,
action SMALLINT NOT NULL DEFAULT 0
);
COMMENT ON TABLE group_filters IS 'Filtering rules assigned to groups';
COMMENT ON COLUMN group_filters.rule_order IS 'Rule processing order';
COMMENT ON COLUMN group_filters.action IS 'Action: 1=allow, 0=deny';

-- Filter groups
CREATE TABLE group_list (
id SERIAL PRIMARY KEY,
instance_id INTEGER NOT NULL DEFAULT 1,
group_name VARCHAR(50),
description VARCHAR(250)
);
COMMENT ON TABLE group_list IS 'Filtering policy groups';

-- MAC address history
CREATE TABLE mac_history (
id BIGSERIAL PRIMARY KEY,
mac VARCHAR(12),
ts TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
device_id BIGINT,
port_id BIGINT,
ip INET DEFAULT NULL,
auth_id BIGINT,
dhcp_hostname VARCHAR(250)
);
COMMENT ON TABLE mac_history IS 'MAC address movement history';
COMMENT ON COLUMN mac_history.mac IS 'MAC address (12 hexadecimal characters)';
COMMENT ON COLUMN mac_history.ip IS 'Last used IP address';

-- MAC address vendors
CREATE TABLE mac_vendors (
id SERIAL PRIMARY KEY,
oui VARCHAR(20),
companyName VARCHAR(255),
companyAddress VARCHAR(255)
);
COMMENT ON TABLE mac_vendors IS 'MAC address vendor database';
COMMENT ON COLUMN mac_vendors.oui IS 'Organizationally Unique Identifier (first 6 MAC characters)';

-- Organizational Units
CREATE TABLE ou (
id SERIAL PRIMARY KEY,
ou_name VARCHAR(40),
description VARCHAR(250),
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
COMMENT ON TABLE ou IS 'Organizational Units (departments/groups)';
COMMENT ON COLUMN ou.ou_name IS 'ou name/identifier';
COMMENT ON COLUMN ou.life_duration IS 'Default lifetime in hours for dynamic OUs';

-- Traffic shaping queues
CREATE TABLE queue_list (
id SERIAL PRIMARY KEY,
queue_name VARCHAR(20) NOT NULL,
download INTEGER NOT NULL DEFAULT 0,
upload INTEGER NOT NULL DEFAULT 0
);
COMMENT ON TABLE queue_list IS 'Bandwidth profiles for traffic shaping';
COMMENT ON COLUMN queue_list.download IS 'Download speed limit in Kbit/s';
COMMENT ON COLUMN queue_list.upload IS 'Upload speed limit in Kbit/s';

-- Remote syslog messages
CREATE TABLE remote_syslog (
id BIGSERIAL PRIMARY KEY,
ts TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
device_id BIGINT NOT NULL,
ip INET DEFAULT NULL,
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
description VARCHAR(250)
);
COMMENT ON TABLE subnets IS 'Network subnet configuration';
COMMENT ON COLUMN subnets.subnet IS 'Network in CIDR notation';
COMMENT ON COLUMN subnets.vlan_tag IS 'VLAN ID for this subnet';
COMMENT ON COLUMN subnets.office IS 'This is an office subnet';
COMMENT ON COLUMN subnets.hotspot IS 'This is a public/guest subnet';
COMMENT ON COLUMN subnets.notify IS 'Notification bitmask: 1=email, 2=sms, 4=telegram';

CREATE TABLE traffic_detail (
id BIGSERIAL PRIMARY KEY,
auth_id   bigint,
router_id integer NOT NULL DEFAULT 0,
ts TIMESTAMP,
proto     smallint,
src_ip    bigint NOT NULL DEFAULT 0,
dst_ip    bigint NOT NULL DEFAULT 0,
src_port  integer NOT NULL DEFAULT 0,
dst_port  integer NOT NULL DEFAULT 0,
bytes     bigint NOT NULL DEFAULT 0,
pkt       bigint NOT NULL DEFAULT 0,
);
COMMENT ON TABLE traffic_detail IS 'Detailed traffic flow records (NetFlow)';
COMMENT ON COLUMN traffic_detail.proto IS 'IP protocol number';
COMMENT ON COLUMN traffic_detail.src_ip IS 'Source IP as integer';
COMMENT ON COLUMN traffic_detail.dst_ip IS 'Destination IP as integer';
COMMENT ON COLUMN traffic_detail.bytes IS 'Bytes transferred in this flow';

-- Unknown MAC addresses
CREATE TABLE unknown_mac (
id BIGSERIAL PRIMARY KEY,
mac VARCHAR(12),
port_id BIGINT,
device_id INTEGER,
ts TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);
COMMENT ON TABLE unknown_mac IS 'Recently detected unknown MAC addresses';

-- User authorization records
CREATE TABLE user_auth (
id SERIAL PRIMARY KEY,
user_id BIGINT NOT NULL DEFAULT 0,
ou_id INTEGER,
ip INET DEFAULT NULL,
ip_int BIGINT NOT NULL DEFAULT 0,
save_traf SMALLINT NOT NULL DEFAULT 0,
enabled SMALLINT NOT NULL DEFAULT 0,
dhcp SMALLINT NOT NULL DEFAULT 1,
filter_group_id SMALLINT NOT NULL DEFAULT 0,
dynamic SMALLINT NOT NULL DEFAULT 0,
end_life TIMESTAMP,
deleted SMALLINT NOT NULL DEFAULT 0,
description VARCHAR(250),
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
mac_found TIMESTAMP,
blocked SMALLINT NOT NULL DEFAULT 0,
day_quota INTEGER NOT NULL DEFAULT 0,
month_quota INTEGER NOT NULL DEFAULT 0,
device_model_id INTEGER DEFAULT 87,
firmware VARCHAR(100),
ts TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
client_id VARCHAR(250),
nagios SMALLINT NOT NULL DEFAULT 0,
nagios_status VARCHAR(10) NOT NULL DEFAULT '',
nagios_handler VARCHAR(50) NOT NULL DEFAULT '',
link_check SMALLINT NOT NULL DEFAULT 0,
changed SMALLINT NOT NULL DEFAULT 0,
dhcp_changed SMALLINT NOT NULL DEFAULT 0,
changed_time TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
created_by VARCHAR(10)
);
COMMENT ON TABLE user_auth IS 'User/device network authorization records';
COMMENT ON COLUMN user_auth.enabled IS 'This authorization is active';
COMMENT ON COLUMN user_auth.dynamic IS 'This is a dynamically created record';
COMMENT ON COLUMN user_auth.day_quota IS 'Daily traffic quota in bytes';
COMMENT ON COLUMN user_auth.nagios IS 'Enable Nagios monitoring for this host';

-- User authorization aliases
CREATE TABLE user_auth_alias (
id SERIAL PRIMARY KEY,
auth_id INTEGER NOT NULL,
alias VARCHAR(100),
description VARCHAR(100),
ts TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);
COMMENT ON TABLE user_auth_alias IS 'Aliases/DNS names for authorization records';

-- User list
CREATE TABLE user_list (
id BIGSERIAL PRIMARY KEY,
ts TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
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
COMMENT ON TABLE user_list IS 'User accounts in the system';
COMMENT ON COLUMN user_list.fio IS 'Full name';
COMMENT ON COLUMN user_list.permanent IS 'Permanent (non-dynamic) user';

-- User web sessions
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
CREATE TABLE user_stats (
id BIGSERIAL PRIMARY KEY,
router_id BIGINT DEFAULT 0,
auth_id BIGINT NOT NULL DEFAULT 0,
ts TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
byte_in BIGINT NOT NULL DEFAULT 0,
byte_out BIGINT NOT NULL DEFAULT 0,
pkt_in INTEGER NOT NULL DEFAULT 0,
pkt_out INTEGER NOT NULL DEFAULT 0,
step SMALLINT NOT NULL DEFAULT 3600
);
COMMENT ON TABLE user_stats IS 'Aggregated user traffic statistics';

-- Detailed user statistics
CREATE TABLE user_stats_full (
id BIGSERIAL PRIMARY KEY,
router_id BIGINT DEFAULT 0,
auth_id BIGINT NOT NULL DEFAULT 0,
ts TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
byte_in BIGINT NOT NULL DEFAULT 0,
byte_out BIGINT NOT NULL DEFAULT 0,
pkt_in INTEGER NOT NULL DEFAULT 0,
pkt_out INTEGER NOT NULL DEFAULT 0,
step SMALLINT NOT NULL DEFAULT 600
);
COMMENT ON TABLE user_stats_full IS 'Detailed user traffic statistics';
COMMENT ON COLUMN user_stats_full.step IS 'Statistics collection interval in seconds';

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
version VARCHAR(10) NOT NULL DEFAULT '3.0.0'
);
COMMENT ON TABLE version IS 'System version information';

-- WAN interface statistics
CREATE TABLE wan_stats (
id BIGSERIAL PRIMARY KEY,
ts TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
router_id INTEGER,
interface_id INTEGER,
bytes_in BIGINT NOT NULL DEFAULT 0,
bytes_out BIGINT NOT NULL DEFAULT 0,
forward_in BIGINT NOT NULL DEFAULT 0,
forward_out BIGINT NOT NULL DEFAULT 0
);
COMMENT ON TABLE wan_stats IS 'WAN interface traffic statistics';
COMMENT ON COLUMN wan_stats.bytes_in IS 'Bytes received on WAN interface';
COMMENT ON COLUMN wan_stats.bytes_out IS 'Bytes sent from WAN interface';

-- System activity log
CREATE TABLE worklog (
id BIGSERIAL PRIMARY KEY,
ts TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
auth_id BIGINT NOT NULL DEFAULT 0,
customer VARCHAR(50) NOT NULL DEFAULT 'system',
ip INET NOT NULL DEFAULT '127.0.0.1',
message TEXT NOT NULL,
level SMALLINT NOT NULL DEFAULT 1
);
COMMENT ON TABLE worklog IS 'System activity and audit log';
COMMENT ON COLUMN worklog.level IS 'Log level: 1=info, 2=warning, 3=error, 4=debug';

-- Indexes (same as in the original schema)
CREATE INDEX idx_devices_ip ON devices(ip);
CREATE INDEX idx_devices_device_type ON devices(device_type);
CREATE INDEX idx_devices_active ON devices(active) WHERE active = 1;

CREATE INDEX idx_device_ports_device_id ON device_ports(device_id);
CREATE INDEX idx_device_ports_port ON device_ports(port);
CREATE INDEX idx_device_ports_target_port_id ON device_ports(target_port_id);

CREATE INDEX idx_dhcp_log_ts ON dhcp_log(ts, action);
CREATE INDEX idx_dhcp_queue_ts ON dhcp_queue(ts, action);

CREATE INDEX idx_dns_cache_dns ON dns_cache(dns, ip);
CREATE INDEX idx_dns_cache_ts ON dns_cache(ts);

CREATE INDEX idx_mac_history_mac ON mac_history(mac, ts);
CREATE INDEX idx_mac_history_ip ON mac_history(ip, ts);
CREATE INDEX idx_mac_history_ts ON mac_history(ts);

CREATE INDEX idx_ou_ou_name_gin ON OU USING GIN(ou_name gin_trgm_ops);

CREATE INDEX idx_subnets_ip_int_start ON subnets(ip_int_start, ip_int_stop);
CREATE INDEX idx_subnets_dhcp ON subnets(dhcp, office, hotspot, static);

CREATE INDEX idx_traffic_detail_src ON traffic_detail(auth_id, ts, router_id, src_ip);
CREATE INDEX idx_traffic_detail_dst ON traffic_detail(auth_id, ts, router_id, dst_ip);

CREATE INDEX idx_unknown_mac_ts ON unknown_mac(ts, device_id, port_id, mac);

CREATE INDEX idx_user_auth_main ON user_auth(id, user_id, ip_int, mac, ip, deleted);
CREATE INDEX idx_user_auth_deleted ON user_auth(deleted) WHERE deleted = 0;
CREATE INDEX idx_user_auth_ou_id ON user_auth(ou_id);

CREATE INDEX idx_user_list_main ON user_list(id, ou_id, enabled, blocked, deleted);

CREATE INDEX idx_user_sessions_session_id ON user_sessions(session_id);
CREATE INDEX idx_user_sessions_user_id ON user_sessions(user_id);
CREATE INDEX idx_user_sessions_is_active ON user_sessions(is_active) WHERE is_active = 1;

CREATE INDEX idx_user_stats_ts ON user_stats(ts, auth_id, router_id);
CREATE INDEX idx_user_stats_full_ts ON user_stats_full(ts, auth_id, router_id);

CREATE INDEX idx_wan_stats_time ON wan_stats(ts, router_id, interface_id);

CREATE INDEX idx_worklog_customer ON worklog(customer, level, ts);
CREATE INDEX idx_worklog_ts ON worklog(level, ts);
CREATE INDEX idx_worklog_auth_id ON worklog(auth_id, level, ts);
