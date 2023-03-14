<?php

define("WEB_MONTHS", array(
1 => "Январь",
2 => "Февраль",
3 => "Март",
4 => "Апрель",
5 => "Май",
6 => "Июнь",
7 => "Июль",
8 => "Август",
9 => "Сентябрь",
10 => "Октябрь",
11 => "Ноябрь",
12 => "Декабрь"
));

/* common variables */
define("WEB_days","дней");
define("WEB_sec","секунд");
define("WEB_page_speed","Страница сгенерирована за ");
define("WEB_rows_at_page","Записей на страницу");
define("WEB_nagios","Nagios");
define("WEB_nagios_host_up","Хост активен");
define("WEB_nagios_host_down","Хост не доступен");
define("WEB_nagios_host_unknown","Состояние неизвестно");

/* error messages */
define("WEB_auth_unknown","IP-адрес клиента не установлен");
define("WEB_msg_exists","уже существует!");
define("WEB_msg_ip_error","Формат адреса не верен!");

/* common message */
define("WEB_msg_IP","IP-адрес");
define("WEB_msg_ERROR","Ошибка!");
define("WEB_msg_enabled","Включен");
define("WEB_msg_disabled","Выключен");
define("WEB_msg_login","Логин");
define("WEB_msg_fullname","ФИО");
define("WEB_msg_comment","Комментарий");
define("WEB_msg_now","Сейчас");
define("WEB_msg_forbidden","Запрещено");
define("WEB_msg_traffic_blocked","Блок по трафику");
define("WEB_msg_internet","Интернет");
define("WEB_msg_delete","Удалить");
define("WEB_msg_additional","Дополнительно");
define("WEB_msg_unsupported","Не поддерживается");
define("WEB_msg_delete_filter","Удалить фильтр");
define("WEB_msg_add_filter", "Добавить фильтр");
define("WEB_msg_apply_selected","Применить для выделения");

/* SNMP */
define("WEB_snmp_version","SNMP version");
define("WEB_snmp_v3_user_ro","Snmpv3 RO user");
define("WEB_snmp_v3_user_rw","Snmpv3 RW user");
define("WEB_snmp_v3_ro_password","Snmpv3 RO password");
define("WEB_snmp_v3_rw_password","Snmpv3 RW password");
define("WEB_snmp_community_ro","Snmp RO Community");
define("WEB_snmp_community_rw","Snmp RW Community");
define("WEB_snmp_interface_name","Interface name");
define("WEB_snmp_interface_index","Interface index");

/* color schema description */
define("WEB_color_description","Цветовая маркировка");
define("WEB_color_user_disabled","Пользователь выключен");
define("WEB_color_user_blocked","Блокировка по трафику");
define("WEB_color_device_description","Состояние устройства");

/* device and port state */
define("WEB_device_online","Online");
define("WEB_device_down","Down");
define("WEB_port_status","Состояние портов");
define("WEB_port_oper_down","Oper down");
define("WEB_port_oper_up","Oper up");
define("WEB_port_admin_shutdown","Admin off");
define("WEB_port_speed","Port speed");
define("WEB_port_speed_10","10M");
define("WEB_port_speed_100","100M");
define("WEB_port_speed_1G","1G");
define("WEB_port_speed_10G","10G");

/* select items */
define("WEB_select_item_yes","Да");
define("WEB_select_item_no","Нет");
define("WEB_select_item_lease","Аренда адреса");
define("WEB_select_item_enabled","Включенные");
define("WEB_select_item_wan","Внешний");
define("WEB_select_item_lan","Внутренний");
define("WEB_select_item_all_ips","Всe ip");
define("WEB_select_item_every","Все");
define("WEB_select_item_all","Всё");
define("WEB_select_item_events","Все события");
define("WEB_select_item_disabled","Выключенные");
define("WEB_select_item_forbidden","Запретить");
define("WEB_select_item_more","Много");
define("WEB_select_item_lease_refresh","Обновление аренды");
define("WEB_select_item_lease_free","Освобождение адреса");
define("WEB_select_item_allow","Разрешить");

/* submenu */
define("WEB_submenu_dhcp_log","Журнал dhcp");
define("WEB_submenu_work_log","Журнал работы");
define("WEB_submenu_mac_history","Приключения маков");
define("WEB_submenu_ip_history","История ip-адресов");
define("WEB_submenu_mac_unknown","Неизвестные");
define("WEB_submenu_traffic","Трафик");
define("WEB_submenu_syslog","syslog");
define("WEB_submenu_control","Управление");
define("WEB_submenu_network","Сети");
define("WEB_submenu_network_stats","Сети (Статистика)");
define("WEB_submenu_options","Параметры");
define("WEB_submenu_customers","Пользователи");
define("WEB_submenu_filter_list","Список фильтров");
define("WEB_submenu_filter_group","Группы фильтров");
define("WEB_submenu_traffic_ip_report","Отчёт по трафику (ip)");
define("WEB_submenu_traffic_login_report","Отчёт по трафику (login)");
define("WEB_submenu_traffic_top10","TOP 10 по трафику");
define("WEB_submenu_detail_log","Подробный лог");
define("WEB_submenu_net_devices","Сетевые устройства");
define("WEB_submenu_passive_net_devices","Пассивные устройства");
define("WEB_submenu_buildings","Расположение");
define("WEB_submenu_hierarchy","Структура");
define("WEB_submenu_device_models","Модели устройств");
define("WEB_submenu_vendors","Vendors");
define("WEB_submenu_ports_vlan","Порты по вланам");
define("WEB_submenu_ports","Порты");
define("WEB_submenu_state","Состояние");
define("WEB_submenu_connections","Соединения");
define("WEB_submenu_ip_list","Список адресов");
define("WEB_submenu_nagios","Информация для nagios");
define("WEB_submenu_doubles","Дубли");
define("WEB_submenu_deleted","Удалённые адреса");
define("WEB_submenu_auto_rules","Правила автоназначения");

/* header title */
define("WEB_site_title","Панель администратора");
define("WEB_title_reports","Отчёт");
define("WEB_title_groups","Группы");
define("WEB_title_users","Пользователи");
define("WEB_title_users_ips","Все адреса");
define("WEB_title_filters","Фильтры");
define("WEB_title_shapers","Шейперы");
define("WEB_title_devices","Инфраструктура");
define("WEB_title_logs","Логи");
define("WEB_title_control","Настройки");
define("WEB_title_exit","Выход");

/* traffic headers */
define("WEB_title_ip","Адрес");
define("WEB_title_date","Дата");
define("WEB_title_input","Входящий");
define("WEB_title_output","Исходящий");
define("WEB_title_pktin","IN, pkt/s");
define("WEB_title_pktout","OUT, pkt/s");
define("WEB_title_maxpktin","Max IN, pkt/s");
define("WEB_title_maxpktout","Max OUT, pkt/s");
define("WEB_title_sum","Суммарно");
define("WEB_title_itog","Итого");

/* table cell names */
define("WEB_cell_login","Логин");
define("WEB_cell_fio","ФИО");
define("WEB_cell_ou","Группа");
define("WEB_cell_enabled","Включен");
define("WEB_cell_blocked","Блокировка");
define("WEB_cell_perday","В день");
define("WEB_cell_permonth","В месяц");
define("WEB_cell_report","Отчёт");
define("WEB_cell_name","Название");
define("WEB_cell_ip","IP");
define("WEB_cell_mac","MAC");
define("WEB_cell_clientid","Client-id");
define("WEB_cell_host_firmware","Прошивка");
define("WEB_cell_sn","SN");
define("WEB_cell_comment","Комментарий");
define("WEB_cell_wikiname","Wiki Name");
define("WEB_cell_filter","Фильтр");
define("WEB_cell_proxy","Proxy");
define("WEB_cell_dhcp","Dhcp");
define("WEB_cell_nat","НАТ");
define("WEB_cell_transparent","Transparent");
define("WEB_cell_shaper","Шейпер");
define("WEB_cell_connection","Подключен");
define("WEB_cell_last_found","Последняя активность");
define("WEB_cell_dns_name","Имя в dns");
define("WEB_cell_host_model","Модель устройства");
define("WEB_cell_nagios","Мониторинг");
define("WEB_cell_nagios_handler","Реакция на событие");
define("WEB_cell_link","Линк");
define("WEB_cell_traf","Запись трафика");
define("WEB_cell_acl","dhcp acl");
define("WEB_cell_le","Правил");
define("WEB_cell_login_quote_month","Квота на логин, месяц");
define("WEB_cell_ip_quote_month","Квота на адрес, месяц");
define("WEB_cell_login_quote_day","Квота на логин, день");
define("WEB_cell_ip_quote_day","Квота на адрес, день");
define("WEB_cell_type","Тип");
define("WEB_cell_skip","Пропустить");
define("WEB_cell_vlan","Vlan");
define("WEB_cell_mac_count","Mac count");
define("WEB_cell_forename","Имя");
define("WEB_cell_flags","Флаги");
define("WEB_cell_created","Создан");
define("WEB_cell_deleted","Удалён");

/* lists name */
define("WEB_list_ou","Список групп");
define("WEB_list_subnet","Список подсетей");
define("WEB_list_customers","Список администраторов");
define("WEB_list_filters","Список фильтров");
define("WEB_list_users","Список полльзователей");
define("WEB_list_models","Список моделей устройств");
define("WEB_list_vendors","Список вендоров");

/* button names */
define("WEB_btn_remove","Удалить");
define("WEB_btn_add","Добавить");
define("WEB_btn_save","Сохранить");
define("WEB_btn_move","Переместить");
define("WEB_btn_config_apply","Применить конфигурацию");
define("WEB_btn_device","+Устройство");
define("WEB_btn_mac_add","+MAC");
define("WEB_btn_mac_del","-MAC");
define("WEB_btn_ip_add","+IP");
define("WEB_btn_ip_del","-IP");
define("WEB_btn_run","Выполнить");
define("WEB_btn_refresh","Обновить");
define("WEB_btn_delete","Удалить");
define("WEB_btn_apply","Применить");
define("WEB_btn_show","Показать");
define("WEB_btn_reorder","Применить порядок");

/* control options */
define("WEB_config_remove_option","Удалён параметр");
define("WEB_config_set_option","Изменён параметр");
define("WEB_config_add_option","Добавлен параметр");
define("WEB_config_parameters","Настройки");
define("WEB_config_option","Параметр");
define("WEB_config_value","Значение");

/* control-subnets-usage */
define("WEB_network_usage_title","Статистика использования сетей организации");
define("WEB_network_subnet","Сеть");
define("WEB_network_all_ip","Всего<br>адресов");
define("WEB_network_used_ip","Занято");
define("WEB_network_free_ip","Свободно<br>(всего)");
define("WEB_network_dhcp_size","Размер<br>dhcp пула");
define("WEB_network_dhcp_used","Занято<br>в пуле");
define("WEB_network_dhcp_free","Свободно<br>в пуле");
define("WEB_network_static_free","Свободно<br>(static)");
define("WEB_network_zombi_dhcp","Зомби, dhcp");
define("WEB_network_zombi","Зомби, всего");

/* control-subnets */
define("WEB_network_org_title","Сети организации");
define("WEB_network_gateway","Шлюз");
define("WEB_network_use_dhcp","DHCP");
define("WEB_network_static","Static");
define("WEB_network_dhcp_first","DHCP start");
define("WEB_network_dhcp_last","DHCP end");
define("WEB_network_dhcp_leasetime","Lease time,m");
define("WEB_network_office_subnet","Офисная");
define("WEB_network_hotspot","Хот-спот");
define("WEB_network_vpn","VPN");
define("WEB_network_free","Бесплатная");
define("WEB_network_dyndns","Обновлять dns<br>из dhcp");
define("WEB_network_discovery","Discovery");
define("WEB_network_create","Новая сеть");

/*  control */
define("WEB_control_access","Управление доступом");
define("WEB_control_dhcp","Конфигурация dhcp");
define("WEB_control_dns","Конфигурация dns");
define("WEB_control_nagios","Reconfigure Nagios");
define("WEB_control_nagios_clear_alarm","Nagios - сбросить аварию");
define("WEB_control_scan_network","Сканирование сети");
define("WEB_control_fping_scan_network","Активное сканирование");
define("WEB_control_log_traffic_on","Включить запись трафика у всех");
define("WEB_control_log_traffic_off","Выключить запись трафика у всех");
define("WEB_control_clear_dns_cache","Сбросить кэш DNS");
define("WEB_control_port_off","Управление портами");

/* editcustom */
define("WEB_custom_titles","Администратор");
define("WEB_custom_login","Логин");
define("WEB_custom_password","Пароль");
define("WEB_custom_mode","Только просмотр");
define("WEB_custom_api_key","Ключ API");

/* custom index */
define("WEB_custom_index_title","Администраторы");

/* ipcam */
define("WEB_control_group","Для группы");
define("WEB_control_port_poe_off","Выключить порты");
define("WEB_control_port_poe_on","Включить порты");

/* public user */
define("WEB_msg_auth_unknown","в списках не значится");
define("WEB_msg_user_unknown","принадлежит несуществующему юзеру. Вероятно запись удалена.");
define("WEB_msg_traffic_for_ip","Трафик на адрес");
define("WEB_msg_traffic_for_login","Трафик клиента");
define("WEB_public_day_traffic","за день, (Вх/Исх)");
define("WEB_public_month_traffic","за месяц, (Вх/Исх)");

/* device models */
define("WEB_model_vendor","Производитель");
define("WEB_nagios_template","Шаблон Нагиос");

/* edit_l3int */
define("WEB_list_l3_interfaces","Список L3 интерфейсов");
define("WEB_l3_interface_add","Добавить интерфейс");

/* editdevice */
define("WEB_location_name","Расположение");
define("WEB_device_access_control","Управление доступом");
define("WEB_device_queues_enabled","Шейперы");
define("WEB_device_connected_only","Только сети маршрутизатора");
define("WEB_device_dhcp_server","DHCP-Server");
define("WEB_device_snmp_hint","Некоторые устройства отдают mac-таблицу по индексу порта в snmp, другие - по номеру");
define("WEB_device_mac_by_oid","Mac by snmp");
define("WEB_device_mac_table","Show mac table");
define("WEB_device_walk_port_list","Port Walk");
define("WEB_device_port_count","Портов");

/* editport */
define("WEB_device_port_number","Порт N");
define("WEB_device_port_name","Порт");
define("WEB_device_port_snmp_index","Номер порта в snmp");
define("WEB_device_port_uplink_device","Устройство");
define("WEB_device_port_uplink","Uplink");
define("WEB_device_port_allien","Не проверять");

/* devices: index-passive */
define("WEB_device_type_show","Тип оборудования");
define("WEB_device_hide_unknown","Скрыть неизвестные");
define("WEB_device_show_location","Расположение оборудования");

/* mac table */
define("WEB_device_mac_table_show","Список маков активных на оборудовании");
define("WEB_device_port_mac_table_show","Список маков активных на порту оборудования");
define("WEB_device_port_mac_table_history","Список маков когда-либо обнаруженных на порту");

/* portsbyvlan */
define("WEB_device_ports_by_vlan","Список портов в влане");

/* switchport-connection */
define("WEB_device_port_connections","Список соединений на портах");

/* switchport */
define("WEB_device_port_list","Список портов");
define("WEB_device_connected_endpoint","Юзер/Устройство");
define("WEB_device_first_port_snmp_value","SNMP-индекс первого порта");
define("WEB_device_recalc_snmp_port","Пересчитать snmp индексы");

/* switchport-status */
define("WEB_device_port_state_list","Состояние портов");
define("WEB_device_snmp_port_oid_name","IfName");
define("WEB_device_port_speed","Speed");
define("WEB_device_port_errors","Errors");
define("WEB_device_poe_control","Управление POE");
define("WEB_device_port_control","Управление портом");
define("WEB_device_port_on","Включить порт");
define("WEB_device_port_off","Выключить порт");
define("WEB_device_poe_on","Включить POE");
define("WEB_device_poe_off","Выключить POE");

/* edit filter */
define("WEB_title_filter","Фильтр");
define("Web_filter_type","Тип фильтра");
define("WEB_traffic_action","Действие");
define("WEB_traffic_dest_address","Адрес назначения");
define("WEB_traffic_source_address","Адрес источника");
define("WEB_traffic_proto","Протокол");
define("WEB_traffic_src_port","Порт источник");
define("WEB_traffic_dst_port","Порт назначения");

/* edit group filters */
define("WEB_title_group","Группа");
define("WEB_groups_filter_list","Список фильтров группы");
define("WEB_group_filter_order","Порядок правил");
define("WEB_group_filter_name","Название фильтра");

/* edit OU */
define("WEB_ou_autoclient_rules","Правила для автоназначенных клиентов");
define("WEB_ou_rules_for_autoassigning","Правила автоназначения адресов в");
define("WEB_ou_rules_order","Порядок применения");
define("WEB_ou_rule","Правило");
define("WEB_ou_new_rule","Новое правило");

/* auto rules */
define("WEB_rules_target","Юзер/группа");

/* all ip list */
define("WEB_ips_show_by_state","По активности");
define("WEB_ips_search_host","Поиск ip or mac");
define("WEB_selection_title","Применить к выделению");
define("WEB_ips_search_full","Поиск по комментарию/ip/mac/dhcp hostname");

?>
