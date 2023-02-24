<?php

$m = array(
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
);

/* header title */
$title_reports = "Отчёт";
$title_groups = "Группы";
$title_users = "Пользователи";
$title_users_ips = "Все IP";
$title_filters = "Фильтры";
$title_shapers = "Шейперы";
$title_devices = "Инфраструктура";

/* traffic headers */
$title_ip = "Адрес";
$title_date = "Дата";
$title_input = "Входящий";
$title_output = "Исходящий";
$title_pktin = "IN, pkt/s";
$title_pktout = "OUT, pkt/s";
$title_maxpktin = "Max IN, pkt/s";
$title_maxpktout = "Max OUT, pkt/s";
$title_sum = "Суммарно";
$title_itog = "Итого";

/* table cell names */
$cell_login = "Логин";
$cell_fio = "ФИО";
$cell_ou = "Группа";
$cell_enabled = "Включен";
$cell_blocked = "Блокировка";
$cell_perday = "В день";
$cell_permonth = "В месяц";
$cell_report = "Отчёт";
$cell_name = "Название";
$cell_ip = "IP";
$cell_mac = "MAC";
$cell_clientid = "Client-id";
$cell_host_firmware = "Firmware";
$cell_comment = "Комментарий";
$cell_wikiname = "Wiki Name";
$cell_filter = "Фильтр";
$cell_proxy = "Proxy";
$cell_dhcp = "Dhcp";
$cell_nat = "Nat";
$cell_transparent = "Transparent";
$cell_shaper = "Шейпер";
$cell_connection = "Подключен";
$cell_dns_name = "Имя в dns";
$cell_host_model = "Модель устройства";
$cell_nagios = "Мониторинг";
$cell_nagios_handler = "Реакция на событие";
$cell_link = "Линк";
$cell_traf = "Запись трафика";
$cell_acl = "dhcp acl";
$cell_rule = "Правил";

/* lists name */
$list_ou = "Список групп";
$list_subnet = "Список подсетей";
$list_customers = "Список администраторов";
$list_filters = "Список фильтров";
$list_users = "Список полльзователей";

/* button names */
$btn_remove = "Удалить";
$btn_add = "Добавить";
$btn_save = "Сохранить";
$btn_move = "Переместить";
$btn_apply = "Применить конфигурацию";
$btn_device = "+Устройство";
$btn_mac_add = "+MAC";
$btn_mac_del = "-MAC";
$btn_ip_add = "+IP";
$btn_ip_del = "-IP";

/* error messages */
$msg_exists = "уже существует!";
$msg_ip_error = "Формат адреса не верен!";


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

/* common */
define("WEB_msg_IP","IP-адрес");
define("WEB_msg_ERROR","Ошибка!");
define("WEB_auth_unknown","IP-адрес клиента не установлен");
define("WEB_msg_enabled","Включен");
define("WEB_msg_disabled","Выключен");
define("WEB_msg_login","Логин");
define("WEB_msg_fullname","ФИО");
define("WEB_msg_comment","Комментарий");
define("WEB_msg_now","Сейчас");
define("WEB_msg_forbidden","Запрещено");
define("WEB_msg_traffic_blocked","Блок по трафику");
define("WEB_msg_internet","Интернет");
define("WEB_msg_run","Выполнить");
define("WEB_msg_delete","Удалить");
define("WEB_msg_apply","Применить");
define("WEB_msg_add","Добавить");

/* header title */
define("WEB_title_reports","Отчёт");
define("WEB_title_groups","Группы");
define("WEB_title_users","Пользователи");
define("WEB_title_users_ips","Все адреса");
define("WEB_title_filters","Фильтры");
define("WEB_title_shapers","Шейперы");
define("WEB_title_devices","Инфраструктура");

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
define("WEB_cell_comment","Комментарий");
define("WEB_cell_wikiname","Wiki Name");
define("WEB_cell_filter","Фильтр");
define("WEB_cell_proxy","Proxy");
define("WEB_cell_dhcp","Dhcp");
define("WEB_cell_nat","НАТ");
define("WEB_cell_transparent","Transparent");
define("WEB_cell_shaper","Шейпер");
define("WEB_cell_connection","Подключен");
define("WEB_cell_dns_name","Имя в dns");
define("WEB_cell_host_model","Модель устройства");
define("WEB_cell_nagios","Мониторинг");
define("WEB_cell_nagios_handler","Реакция на событие");
define("WEB_cell_link","Линк");
define("WEB_cell_traf","Запись трафика");
define("WEB_cell_acl","dhcp acl");
define("WEB_cell_le","Правил");
define("WEB_ceil_login_quote_month","Квота на логин, месяц");
define("WEB_ceil_ip_quote_month","Квота на адрес, месяц");
define("WEB_ceil_login_quote_day","Квота на логин, день");
define("WEB_ceil_ip_quote_day","Квота на адрес, день");

/* lists name */
define("WEB_list_ou","Список групп");
define("WEB_list_subnet","Список подсетей");
define("WEB_list_customers","Список администраторов");
define("WEB_list_filters","Список фильтров");
define("WEB_list_users","Список полльзователей");

/* button names */
define("WEB_btn_remove","Удалить");
define("WEB_btn_add","Добавить");
define("WEB_btn_save","Сохранить");
define("WEB_btn_move","Переместить");
define("WEB_btn_apply","Применить конфигурацию");
define("WEB_btn_device","+Устройство");
define("WEB_btn_mac_add","+MAC");
define("WEB_btn_mac_del","-MAC");
define("WEB_btn_ip_add","+IP");
define("WEB_btn_ip_del","-IP");

/* error messages */
define("WEB_msg_exists","уже существует!");
define("WEB_msg_ip_error","Формат адреса не верен!");

/* log messages */

/* control options */
define("WEB_config_remove_option","Удалён параметр");
define("WEB_config_set_option","Изменён параметр");
define("WEB_config_add_option","Добавлен параметр");
define("WEB_config_parameters","Настройки");
define("WEB_config_option","Параметр");
define("WEB_config_value","Значение");

/* public user */
define("WEB_msg_auth_unknown","в списках не значится");
define("WEB_msg_user_unknown","принадлежит несуществующему юзеру. Вероятно запись удалена.");
define("WEB_msg_traffic_for_ip","Трафик на адрес");
define("WEB_msg_traffic_for_login","Трафик клиента");
define("WEB_public_day_traffic","за день, (Вх/Исх)");
define("WEB_public_month_traffic","за месяц, (Вх/Исх)");

?>
