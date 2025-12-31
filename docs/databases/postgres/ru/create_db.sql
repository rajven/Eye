-- Включаем необходимые расширения
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
COMMENT ON TABLE acl IS 'Список контроля доступа - роли и разрешения';
COMMENT ON COLUMN acl."description.english" IS 'Описание на английском языке';
COMMENT ON COLUMN acl."description.russian" IS 'Описание на русском языке';

-- Кэш компьютеров из Active Directory
CREATE TABLE ad_comp_cache (
id SERIAL PRIMARY KEY,
name VARCHAR(63) NOT NULL UNIQUE,
last_found TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);
COMMENT ON TABLE ad_comp_cache IS 'Кэш компьютеров из Active Directory';
COMMENT ON COLUMN ad_comp_cache.name IS 'Имя компьютера в AD';
COMMENT ON COLUMN ad_comp_cache.last_found IS 'Время последнего обнаружения этого компьютера';

-- Правила аутентификации
CREATE TABLE auth_rules (
id SERIAL PRIMARY KEY,
user_id INTEGER,
ou_id INTEGER,
type SMALLINT NOT NULL,
rule VARCHAR(40) UNIQUE,
comment VARCHAR(250)
);
COMMENT ON TABLE auth_rules IS 'Правила аутентификации и авторизации пользователей';
COMMENT ON COLUMN auth_rules.type IS 'Тип правила: 0=разрешить, 1=запретить, и т.д.';
COMMENT ON COLUMN auth_rules.rule IS 'Идентификатор правила (уникальный)';

-- Здания
CREATE TABLE building (
id SERIAL PRIMARY KEY,
name VARCHAR(50) NOT NULL,
comment VARCHAR(250)
);
COMMENT ON TABLE building IS 'Физические здания/локации';
COMMENT ON COLUMN building.name IS 'Название здания';

-- Системная конфигурация
CREATE TABLE config (
id SERIAL PRIMARY KEY,
option_id INTEGER,
value VARCHAR(250)
);
COMMENT ON TABLE config IS 'Значения системной конфигурации';

-- Опции конфигурации
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
COMMENT ON TABLE config_options IS 'Доступные опции конфигурации';
COMMENT ON COLUMN config_options.option_name IS 'Имя/ключ опции';
COMMENT ON COLUMN config_options.draft IS 'Опция в черновом состоянии';
COMMENT ON COLUMN config_options.uniq IS 'Опция уникальна (единственное значение)';

-- Сетевые соединения
CREATE TABLE connections (
id BIGSERIAL PRIMARY KEY,
device_id BIGINT NOT NULL,
port_id BIGINT NOT NULL,
auth_id BIGINT NOT NULL,
last_found TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);
COMMENT ON TABLE connections IS 'Текущие сетевые соединения (MAC-IP-устройство-порт)';
COMMENT ON COLUMN connections.device_id IS 'ID сетевого устройства';
COMMENT ON COLUMN connections.port_id IS 'ID порта устройства';
COMMENT ON COLUMN connections.auth_id IS 'ID авторизации пользователя';
COMMENT ON COLUMN connections.last_found IS 'Время последней активности соединения';

-- Пользователи системы
CREATE TABLE Customers (
id SERIAL PRIMARY KEY,
Login VARCHAR(20),
comment VARCHAR(100),
password VARCHAR(255),
api_key VARCHAR(255),
rights SMALLINT NOT NULL DEFAULT 3
);
COMMENT ON TABLE Customers IS 'Пользователи/администраторы системы';
COMMENT ON COLUMN Customers.Login IS 'Логин пользователя';
COMMENT ON COLUMN Customers.rights IS 'Уровень прав доступа: 0=просмотр, 1=оператор, 2=админ, 3=суперадмин';

-- Сетевые устройства
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
COMMENT ON TABLE devices IS 'Сетевые устройства (коммутаторы, маршрутизаторы и т.д.)';
COMMENT ON COLUMN devices.device_type IS 'ID типа устройства';
COMMENT ON COLUMN devices.ip IS 'IP-адрес управления устройством';
COMMENT ON COLUMN devices.snmp_version IS 'Версия SNMP: 0=отключено, 1=v1, 2=v2c, 3=v3';
COMMENT ON COLUMN devices.discovery IS 'Включить автоматическое обнаружение';
COMMENT ON COLUMN devices.active IS 'Устройство активно и мониторится';

-- Экземпляры фильтров устройств
CREATE TABLE device_filter_instances (
id SERIAL PRIMARY KEY,
instance_id INTEGER,
device_id INTEGER
);
COMMENT ON TABLE device_filter_instances IS 'Экземпляры фильтров, назначенные устройствам';

-- L3 интерфейсы устройств
CREATE TABLE device_l3_interfaces (
id SERIAL PRIMARY KEY,
device_id INTEGER,
snmpin INTEGER,
interface_type SMALLINT NOT NULL DEFAULT 0,
name VARCHAR(100)
);
COMMENT ON TABLE device_l3_interfaces IS 'Интерфейсы 3 уровня на устройствах';
COMMENT ON COLUMN device_l3_interfaces.interface_type IS 'Тип интерфейса: 0=неизвестно, 1=LAN, 2=WAN, 3=DMZ';

-- Модели устройств
CREATE TABLE device_models (
id SERIAL PRIMARY KEY,
model_name VARCHAR(200),
vendor_id INTEGER DEFAULT 1,
poe_in SMALLINT NOT NULL DEFAULT 0,
poe_out SMALLINT NOT NULL DEFAULT 0,
nagios_template VARCHAR(200)
);
COMMENT ON TABLE device_models IS 'Модели устройств и их характеристики';
COMMENT ON COLUMN device_models.poe_in IS 'Поддерживает питание по Ethernet на входе';
COMMENT ON COLUMN device_models.poe_out IS 'Обеспечивает питание по Ethernet';

-- Порты устройств
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
COMMENT ON TABLE device_ports IS 'Порты/интерфейсы сетевых устройств';
COMMENT ON COLUMN device_ports.port IS 'Номер физического порта';
COMMENT ON COLUMN device_ports.uplink IS 'Это аплинк-порт';
COMMENT ON COLUMN device_ports.vlan IS 'VLAN по умолчанию/нативный VLAN';

-- Типы устройств
CREATE TABLE device_types (
id SERIAL PRIMARY KEY,
"name.russian" VARCHAR(50),
"name.english" VARCHAR(50)
);
COMMENT ON TABLE device_types IS 'Классификация типов устройств';
COMMENT ON COLUMN device_types."name.russian" IS 'Название типа устройства на русском';
COMMENT ON COLUMN device_types."name.english" IS 'Название типа устройства на английском';

-- Логи DHCP
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
COMMENT ON TABLE dhcp_log IS 'Логи транзакций DHCP сервера';
COMMENT ON COLUMN dhcp_log.action IS 'Действие DHCP: DISCOVER, REQUEST, ACK, NAK, RELEASE';
COMMENT ON COLUMN dhcp_log."circuit-id" IS 'DHCP опция 82 circuit ID';

-- Очередь DHCP
CREATE TABLE dhcp_queue (
id BIGSERIAL PRIMARY KEY,
mac MACADDR NOT NULL,
ip INET NOT NULL,
action VARCHAR(10) NOT NULL,
timestamp TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
dhcp_hostname VARCHAR(250)
);
COMMENT ON TABLE dhcp_queue IS 'Очередь отложенных операций DHCP';

-- DNS кэш
CREATE TABLE dns_cache (
id BIGSERIAL PRIMARY KEY,
dns VARCHAR(250),
ip BIGINT,
timestamp TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);
COMMENT ON TABLE dns_cache IS 'Кэш DNS разрешений';

-- Очередь DNS
CREATE TABLE dns_queue (
id SERIAL PRIMARY KEY,
auth_id INTEGER,
name_type VARCHAR(10) NOT NULL DEFAULT 'A',
name VARCHAR(200),
type VARCHAR(10) NOT NULL DEFAULT 'add',
value VARCHAR(100)
);
COMMENT ON TABLE dns_queue IS 'Очередь отложенных операций DNS';
COMMENT ON COLUMN dns_queue.name_type IS 'Тип DNS записи: A, AAAA, PTR, CNAME';
COMMENT ON COLUMN dns_queue.type IS 'Тип операции: add, delete, update';

-- Экземпляры фильтров
CREATE TABLE filter_instances (
id SERIAL PRIMARY KEY,
name VARCHAR(50) UNIQUE,
comment VARCHAR(200)
);
COMMENT ON TABLE filter_instances IS 'Экземпляры политик фильтрации';

-- Список правил фильтрации
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
COMMENT ON TABLE Filter_list IS 'Правила firewall/фильтрации';
COMMENT ON COLUMN Filter_list.proto IS 'Протокол: tcp, udp, icmp и т.д.';
COMMENT ON COLUMN Filter_list.dst IS 'IP/CIDR назначения';
COMMENT ON COLUMN Filter_list.type IS 'Тип правила: 0=разрешить, 1=запретить';

-- Шлюзы подсетей
CREATE TABLE gateway_subnets (
id SERIAL PRIMARY KEY,
device_id INTEGER,
subnet_id INTEGER
);
COMMENT ON TABLE gateway_subnets IS 'Какие устройства являются шлюзами для каких подсетей';

-- Назначения фильтров группам
CREATE TABLE Group_filters (
id SERIAL PRIMARY KEY,
group_id INTEGER NOT NULL DEFAULT 0,
filter_id INTEGER NOT NULL DEFAULT 0,
"order" INTEGER NOT NULL DEFAULT 0,
action SMALLINT NOT NULL DEFAULT 0
);
COMMENT ON TABLE Group_filters IS 'Правила фильтрации, назначенные группам';
COMMENT ON COLUMN Group_filters."order" IS 'Порядок обработки правил';
COMMENT ON COLUMN Group_filters.action IS 'Действие: 1=разрешить, 0=запретить';

-- Группы фильтров
CREATE TABLE Group_list (
id SERIAL PRIMARY KEY,
instance_id INTEGER NOT NULL DEFAULT 1,
group_name VARCHAR(50),
comment VARCHAR(250)
);
COMMENT ON TABLE Group_list IS 'Группы политик фильтрации';

-- История MAC-адресов
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
COMMENT ON TABLE mac_history IS 'История перемещений MAC-адресов';
COMMENT ON COLUMN mac_history.mac IS 'MAC-адрес (12 шестнадцатеричных символов)';
COMMENT ON COLUMN mac_history.ip IS 'Последний использованный IP-адрес';

-- Производители MAC-адресов
CREATE TABLE mac_vendors (
id SERIAL PRIMARY KEY,
oui VARCHAR(20),
companyName VARCHAR(255),
companyAddress VARCHAR(255)
);
COMMENT ON TABLE mac_vendors IS 'База данных производителей по MAC-адресам';
COMMENT ON COLUMN mac_vendors.oui IS 'Organizationally Unique Identifier (первые 6 символов MAC)';

-- Организационные единицы
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
COMMENT ON TABLE OU IS 'Организационные единицы (отделы/группы)';
COMMENT ON COLUMN OU.ou_name IS 'Имя/идентификатор OU';
COMMENT ON COLUMN OU.life_duration IS 'Время жизни по умолчанию в часах для динамических OU';

-- Очереди шейпинга трафика
CREATE TABLE Queue_list (
id SERIAL PRIMARY KEY,
queue_name VARCHAR(20) NOT NULL,
Download INTEGER NOT NULL DEFAULT 0,
Upload INTEGER NOT NULL DEFAULT 0
);
COMMENT ON TABLE Queue_list IS 'Профили полосы пропускания для шейпинга трафика';
COMMENT ON COLUMN Queue_list.Download IS 'Ограничение скорости скачивания в Кбит/с';
COMMENT ON COLUMN Queue_list.Upload IS 'Ограничение скорости отдачи в Кбит/с';

-- Удаленные syslog сообщения
CREATE TABLE remote_syslog (
id BIGSERIAL PRIMARY KEY,
date TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
device_id BIGINT NOT NULL,
ip VARCHAR(15) NOT NULL,
message TEXT NOT NULL
);
COMMENT ON TABLE remote_syslog IS 'Syslog сообщения от сетевых устройств';

-- PHP сессии
CREATE TABLE sessions (
id VARCHAR(128) PRIMARY KEY,
data TEXT NOT NULL,
last_accessed INTEGER NOT NULL
);
COMMENT ON TABLE sessions IS 'Хранилище PHP сессий';

-- Сетевые подсети
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
COMMENT ON TABLE subnets IS 'Конфигурация сетевых подсетей';
COMMENT ON COLUMN subnets.subnet IS 'Сеть в нотации CIDR';
COMMENT ON COLUMN subnets.vlan_tag IS 'ID VLAN для этой подсети';
COMMENT ON COLUMN subnets.office IS 'Это офисная подсеть';
COMMENT ON COLUMN subnets.hotspot IS 'Это публичная/гостевая подсеть';
COMMENT ON COLUMN subnets.notify IS 'Битовая маска для уведомлений: 1=email, 2=sms, 4=telegram';

-- Подробные логи трафика
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
COMMENT ON TABLE Traffic_detail IS 'Подробные записи потоков трафика (NetFlow)';
COMMENT ON COLUMN Traffic_detail.proto IS 'Номер IP протокола';
COMMENT ON COLUMN Traffic_detail.src_ip IS 'Исходный IP в виде целого числа';
COMMENT ON COLUMN Traffic_detail.bytes IS 'Байтов переданно в этом потоке';

-- Неизвестные MAC-адреса
CREATE TABLE Unknown_mac (
id BIGSERIAL PRIMARY KEY,
mac VARCHAR(12),
port_id BIGINT,
device_id INTEGER,
timestamp TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);
COMMENT ON TABLE Unknown_mac IS 'Недавно обнаруженные неизвестные MAC-адреса';

-- Записи авторизации пользователей
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
COMMENT ON TABLE User_auth IS 'Записи авторизации пользователей/устройств в сети';
COMMENT ON COLUMN User_auth.enabled IS 'Эта авторизация активна';
COMMENT ON COLUMN User_auth.dynamic IS 'Это динамически созданная запись';
COMMENT ON COLUMN User_auth.day_quota IS 'Дневная квота трафика в байтах';
COMMENT ON COLUMN User_auth.nagios IS 'Включить мониторинг Nagios для этого хоста';

-- Алиасы авторизации пользователей
CREATE TABLE User_auth_alias (
id SERIAL PRIMARY KEY,
auth_id INTEGER NOT NULL,
alias VARCHAR(100),
description VARCHAR(100),
timestamp TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);
COMMENT ON TABLE User_auth_alias IS 'Алиасы/DNS имена для записей авторизации';

-- Список пользователей
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
COMMENT ON TABLE User_list IS 'Учетные записи пользователей в системе';
COMMENT ON COLUMN User_list.fio IS 'Фамилия Имя Отчество';
COMMENT ON COLUMN User_list.permanent IS 'Это постоянный пользователь (не динамический)';

-- Сессии пользователей (веб-интерфейс)
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
COMMENT ON TABLE user_sessions IS 'Сессии пользователей веб-интерфейса';

-- Статистика трафика пользователей
CREATE TABLE User_stats (
id BIGSERIAL PRIMARY KEY,
router_id BIGINT DEFAULT 0,
auth_id BIGINT NOT NULL DEFAULT 0,
timestamp TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
byte_in BIGINT NOT NULL DEFAULT 0,
byte_out BIGINT NOT NULL DEFAULT 0
);
COMMENT ON TABLE User_stats IS 'Статистика трафика пользователей (агрегированная)';

-- Подробная статистика пользователей
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
COMMENT ON TABLE User_stats_full IS 'Подробная статистика трафика пользователей';
COMMENT ON COLUMN User_stats_full.step IS 'Интервал сбора статистики в секундах';

-- Временные переменные
CREATE TABLE variables (
id SERIAL PRIMARY KEY,
name VARCHAR(30) NOT NULL UNIQUE,
value VARCHAR(255),
clear_time TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
created TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);
COMMENT ON TABLE variables IS 'Временные системные переменные и блокировки';

-- Производители устройств
CREATE TABLE vendors (
id SERIAL PRIMARY KEY,
name VARCHAR(40) NOT NULL
);
COMMENT ON TABLE vendors IS 'Производители сетевого оборудования';

-- Версия системы
CREATE TABLE version (
id INTEGER PRIMARY KEY DEFAULT 1,
version VARCHAR(10) NOT NULL DEFAULT '2.4.14'
);
COMMENT ON TABLE version IS 'Информация о версии системы';

-- Статистика WAN интерфейсов
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
COMMENT ON TABLE Wan_stats IS 'Статистика трафика WAN интерфейсов';
COMMENT ON COLUMN Wan_stats."in" IS 'Байтов получено на WAN интерфейсе';
COMMENT ON COLUMN Wan_stats."out" IS 'Байтов отправлено с WAN интерфейса';

-- Журнал активности системы
CREATE TABLE worklog (
id BIGSERIAL PRIMARY KEY,
timestamp TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
auth_id BIGINT NOT NULL DEFAULT 0,
customer VARCHAR(50) NOT NULL DEFAULT 'system',
ip VARCHAR(18) NOT NULL DEFAULT '127.0.0.1',
message TEXT NOT NULL,
level SMALLINT NOT NULL DEFAULT 1
);
COMMENT ON TABLE worklog IS 'Журнал активности и аудита системы';
COMMENT ON COLUMN worklog.level IS 'Уровень логирования: 1=инфо, 2=предупреждение, 3=ошибка, 4=отладка';

-- Индексы (такие же как в оригинальной структуре)
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

