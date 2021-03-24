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

/* table cell names */
$cell_login = "Логин";
$cell_fio = "ФИО";
$cell_ou = "Подразделение";
$cell_enabled = "Включен";
$cell_blocked = "Блокировка";
$cell_perday = "В день";
$cell_permonth = "В месяц";
$cell_report = "Отчёт";
$cell_name = "Название";
$cell_ip = "IP";
$cell_mac = "MAC";
$cell_clientid = "Client-id";
$cell_comment = "Комментарий";
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
$cell_rule = "Правило";

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

/* error messages */
$msg_exists = "уже существует!";
$msg_ip_error = "Формат адреса не верен!";

/* /inc/header.php */
define("menu1", "Трафик");
define("menu2", "Группы");
define("menu3", "Пользователи");
define("menu4", "Фильтры");

/* /reports/userinfo.php */
define("userinfo1", "Трафик пользователя");
define("userinfo2", "Для адреса:");
define("userinfo3", "Логин");
define("userinfo4", "За месяц");
define("userinfo5", "За год");
define("userinfo6", "За день");
define("userinfo7", "Входящий");
define("userinfo8", "Исходящий");
define("userinfo9", "Общий трафик");
define("userinfo10", "За период");
define("userinfo11", " год ");
define("userinfo12", " месяц ");
define("userinfo13", " день ");

/* /sessions/index.php */
define("sessions1", "Активные подключения");
define("sessions2", "Логин");
define("sessions3", "IP сессии");
define("sessions4", "Подключено с IP");
define("sessions5", "Интерфейс");
define("sessions6", "Баланс");
define("sessions7", "Дата");
define("sessions8", "Статистика использования трафика");
define("sessions9", "Логин");
define("sessions10", "За месяц");
define("sessions11", "За сегодня");
define("sessions12", "За последний час");
define("sessions13", "Входящий");
define("sessions14", "Исходящий");
define("sessions15", "Общий трафик");
define("sessions16", "Отключить выбранных");

/* /users/index.php */
define("users1", "Пользователи");
define("users2", "Логин");
define("users3", "IP");
define("users4", "Тариф");
define("users5", "Баланс");
define("users6", "Не блокировать");
define("users7", "Новый пользователь");
define("users8", "Удалить пользователей");
define("users9", "Пополнить баланс:");
define("users10", "Сменить тариф:");
define("users11", "Пополнить");
define("users12", "Сменить");

/* /users/adduser.php */
define("adduser1", "Добавить пользователя");
define("adduser2", "Логин");
define("adduser3", "Пароль");
define("adduser4", "Подтверждение:");
define("adduser5", "IP адрес");
define("adduser6", "Баланс");
define("adduser7", "Тариф");
define("adduser8", "Не блокировать при отрицательном балансе");
define("adduser9", "Добавить");

/* /users/edituser.php */
define("edituser1", "Редактирование пользователя");
define("edituser2", "Логин");
define("edituser3", "IP адрес");
define("edituser4", "Баланс");
define("edituser5", "Тариф");
define("edituser6", "Не блокировать при отрицательном балансе");
define("edituser7", "Сохранить");
define("edituser8", "Изменить пароль");
define("edituser9", "Новый пароль");
define("edituser10", "Подтверждение");
define("edituser11", "Сохранить");

/* /tariffs/index.php */
define("tariffs1", "Тарифы");
define("tariffs2", "Название");
define("tariffs3", "Цена за 1мб");
define("tariffs4", "Скорость");
define("tariffs5", "Входящий");
define("tariffs6", "Исходящий");
define("tariffs7", "Новый тариф");
define("tariffs8", "Удалить тарифы");
define("tariffs9", "Шейпер");
define("tariffs10", "Стат.");
define("tariffs11", "Макс.");
define("tariffs12", "Мин.");
define("tariffs13", "Нет");
define("tariffs14", "Статич.");
define("tariffs15", "Динамич.");

/* /tariffs/addtariff.php */
define("addtariff1", "Добавить тариф");
define("addtariff2", "Название");
define("addtariff3", "Входяший");
define("addtariff4", "Исходящий");
define("addtariff5", "Не ограничивать");
define("addtariff6", "Скорость");
define("addtariff7", "Добавить тариф");
define("addtariff8", "Статическая");
define("addtariff9", "Динамическая");
define("addtariff10", "руб/Мб");
define("addtariff11", "Кбит/сек");
define("addtariff12", "Минимальная");
define("addtariff13", "Максимальная");

define("addtariff14", "Абонентская плата");
define("addtariff15", "Сумма");
define("addtariff16", "Пояснение");
define("addtariff17", "Предоплаченный трафик");
define("addtariff18", "Входящий");
define("addtariff19", "Исходящий");
define("addtariff20", "руб");
define("addtariff21", "Мбайт");
define("addtariff22", "Ограничение скорости");

/* /tariffs/edittariff.php */
define("edittariff1", "Редактировать тариф");
define("edittariff2", "Название");
define("edittariff3", "Входяший");
define("edittariff4", "Исходящий");
define("edittariff5", "Не ограничивать");
define("edittariff6", "Скорость");
define("edittariff7", "Сохранить");
define("edittariff8", "Статическая");
define("edittariff9", "Динамическая");
define("edittariff10", "руб/Мб");
define("edittariff11", "Кбит/сек");
define("edittariff12", "Минимальная");
define("edittariff13", "Максимальная");

define("edittariff14", "Абонентская плата");
define("edittariff15", "Сумма");
define("edittariff16", "Пояснение");
define("edittariff17", "Предоплаченный трафик");
define("edittariff18", "Входящий");
define("edittariff19", "Исходящий");
define("edittariff20", "руб");
define("edittariff21", "Мбайт");
define("edittariff22", "Ограничение скорости");

/* /tariffs/prices.php */
define("prices1", "Ценовые правила для тарифа");
define("prices2", "Хост/Сеть");
define("prices3", "Входящий");
define("prices4", "Исходящий");
define("prices5", "Описание");
define("prices6", "Новое правло");
define("prices7", "Удалить отмеченные");

/* /tariffs/addprice.php */
define("addprice1", "Добавление правила");
define("addprice2", "Хост");
define("addprice3", "Сеть");
define("addprice4", "Входящий");
define("addprice5", "Исходящий");
define("addprice6", "Описание");
define("addprice7", "руб/Мб");
define("addprice8", "Добавить");
define("addprice9", "Включен предоплаченный трафик");

/* /tariffs/editprice.php */
define("editprice1", "Редактирование правила");
define("editprice2", "Хост");
define("editprice3", "Сеть");
define("editprice4", "Входящий");
define("editprice5", "Исходящий");
define("editprice6", "Описание");
define("editprice7", "руб/Мб");
define("editprice8", "Сохранить");
define("editprice9", "Включен предоплаченный трафик");

/* /stats/ */
define("stats1", "Часовая");
define("stats2", "Дневная");
define("stats3", "Месячная");
define("stats4", "Время");
define("stats5", "Дата");
define("stats6", "Месяц");
define("stats7", "Пользователь");
define("stats8", "Входящий");
define("stats9", "Исходящий");
define("stats10", "Всего");
define("stats11", "Детальная статистика за");
define("stats12", "Показать");
define("stats13", "Период");
define("stats14", "Статистика");
define("stats15", "В период");
/* /msgs/index.php */
define("msgs1", "Сообщения пользователям");
define("msgs2", "Текст");
define("msgs3", "Дата");
define("msgs4", "Новое сообщение");
define("msgs5", "Удалить сообщения");

/* /msgs/addmsg.php */
define("addmsg1", "Добавить сообщение");
define("addmsg2", "Текст сообщения:");
define("addmsg3", "Добавить");

/* /msgs/editmsg.php */
define("editmsg1", "Редактировать сообщение");
define("editmsg2", "Текст сообщения:");
define("editmsg3", "Сохранить");

?>