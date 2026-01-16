<?php
if (!defined("CONFIG")) {
    die("Not defined");
}

require_once($_SERVER['DOCUMENT_ROOT'] . "/inc/consts.php");
require_once($_SERVER['DOCUMENT_ROOT'] . "/inc/snmp.php");

//ValidIpAddressRegex = "^(([0-9]|[1-9][0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5])\.){3}([0-9]|[1-9][0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5])$";
//ValidHostnameRegex = "^(([a-zA-Z0-9]|[a-zA-Z0-9][a-zA-Z0-9\-]*[a-zA-Z0-9])\.)*([A-Za-z0-9]|[A-Za-z0-9][A-Za-z0-9\-]*[A-Za-z0-9])$";
//$ValidMacAddressRegex="^([0-9A-Fa-f]{2}[:-]){5}[0-9A-Fa-f]{2}|([0-9a-fA-F]{4}[\\.-][0-9a-fA-F]{4}[\\.-][0-9a-fA-F]{4})|[0-9A-Fa-f]{12}$";


$config["init"] = 0;

// #### vendor id
// 1, Unknown
// 2, 'Eltex'
// 3, 'Huawei'
// 4, 'Zyxel'
// 5, 'Raisecom'
// 6, 'SNR'
// 7, 'Dlink'
// 8, 'Allied Telesis'
// 9, 'Mikrotik'
// 10, 'NetGear'
// 11, 'Ubiquiti'
// 15, 'HP'
// 16, 'Cisco'
// 17, 'Maipu'
// 18, 'Asus'

// Функция для безопасного получения параметров
/*
FILTER_DEFAULT              // Без фильтрации (по умолчанию)
FILTER_UNSAFE_RAW           // Без фильтрации (аналогично FILTER_DEFAULT)
FILTER_CALLBACK             // Пользовательская функция обратного вызова

FILTER_VALIDATE_BOOLEAN    // true/false/1/0/"1"/"0"/"yes"/"no"
FILTER_VALIDATE_EMAIL      // Email адрес
FILTER_VALIDATE_FLOAT      // Число с плавающей точкой
FILTER_VALIDATE_INT        // Целое число
FILTER_VALIDATE_IP         // IP адрес (IPv4/IPv6)
FILTER_VALIDATE_REGEXP     // По регулярному выражению
FILTER_VALIDATE_URL        // URL адрес
FILTER_VALIDATE_DOMAIN     // Доменное имя (PHP 7.0+)

FILTER_SANITIZE_EMAIL          // Удаляет все кроме букв, цифр и !#$%&'*+-/=?^_`{|}~@.[]
FILTER_SANITIZE_ENCODED        // URL-encode строка
FILTER_SANITIZE_MAGIC_QUOTES   // Apply addslashes()
FILTER_SANITIZE_NUMBER_FLOAT   // Удаляет все кроме цифр, +- и .,eE
FILTER_SANITIZE_NUMBER_INT     // Удаляет все кроме цифр и +-
FILTER_SANITIZE_SPECIAL_CHARS  // HTML-escape '"<>& и символы с ASCII < 32
FILTER_SANITIZE_FULL_SPECIAL_CHARS // Эквивалентно htmlspecialchars()
FILTER_SANITIZE_STRING         // Устарело - используйте FILTER_SANITIZE_FULL_SPECIAL_CHARS
FILTER_SANITIZE_STRIPPED       // Устарело
FILTER_SANITIZE_URL            // Удаляет все кроме букв, цифр и $-_.+!*'(),{}|\\^~[]`<>#%";/?:@&=

Flags::

// Для FILTER_VALIDATE_BOOLEAN
FILTER_NULL_ON_FAILURE      // Возвращает null вместо false при failure

// Для FILTER_VALIDATE_INT
FILTER_FLAG_ALLOW_OCTAL     // Разрешает восьмеричные числа (0123)
FILTER_FLAG_ALLOW_HEX       // Разрешает шестнадцатеричные числа (0x1A)

// Для FILTER_VALIDATE_FLOAT
FILTER_FLAG_ALLOW_THOUSAND  // Разрешает разделитель тысяч (1,234.56)
FILTER_FLAG_ALLOW_SCIENTIFIC// Разрешает научную нотацию (1.2e3)

// Для FILTER_VALIDATE_IP
FILTER_FLAG_IPV4            // Только IPv4
FILTER_FLAG_IPV6            // Только IPv6
FILTER_FLAG_NO_PRIV_RANGE   // Запрещает частные IP (10.0.0.0/8, 172.16.0.0/12, 192.168.0.0/16)
FILTER_FLAG_NO_RES_RANGE    // Запрещает зарезервированные IP
FILTER_FLAG_GLOBAL_RANGE    // Разрешает только глобальные IP

// Для FILTER_VALIDATE_URL
FILTER_FLAG_PATH_REQUIRED   // Требует наличие path (/page.html)
FILTER_FLAG_QUERY_REQUIRED  // Требует наличие query string (?id=1)

// Для FILTER_SANITIZE_STRING
FILTER_FLAG_NO_ENCODE_QUOTES // Не кодировать кавычки
FILTER_FLAG_STRIP_LOW        // Удаляет символы с ASCII < 32
FILTER_FLAG_STRIP_HIGH       // Удаляет символы с ASCII > 127
FILTER_FLAG_ENCODE_LOW       // Кодирует символы с ASCII < 32
FILTER_FLAG_ENCODE_HIGH      // Кодирует символы с ASCII > 127
FILTER_FLAG_ENCODE_AMP       // Кодирует амперсанд (&)
*/

function capture_print_r($var) {
    ob_start();
    print_r($var);
    return ob_get_clean();
}

/**
 * Получает параметр (скаляр или массив) из POST/GET, с опциональной валидацией каждого элемента
 */
function getParam($name, $page_url = null, $default = null, $filter = FILTER_DEFAULT, $options = []) {
    if (isset($_POST[$name]) && is_array($_POST[$name])) {
        return $_POST[$name];
    }
    if (isset($_GET[$name]) && is_array($_GET[$name])) {
        return $_GET[$name];
    }
    // Если не массив — пробуем как скаляр
    if ((isset($_POST[$name]) && $_POST[$name]==='') || (isset($_GET[$name]) && $_GET[$name]==='')) {
        if ($page_url !== null  && isset($_SESSION[$page_url][$name])) {
            return $_SESSION[$page_url][$name];
        }
        return $default;
    }

    $value = filter_input(INPUT_POST, $name, $filter, $options) ??
             filter_input(INPUT_GET, $name, $filter, $options);
    if ($value === false || $value === null) {
        if ($page_url !== null && isset($_SESSION[$page_url][$name])) {
            return $_SESSION[$page_url][$name];
        }
        return $default;
    }
    return $value;
}

/**
 * Получает параметр только из POST (скаляр или массив)
 */
function getPOST($name, $page_url = null, $default = null, $filter = FILTER_DEFAULT, $options = []) {
    if (isset($_POST[$name]) && is_array($_POST[$name])) {
        return $_POST[$name];
    }
    if (isset($_POST[$name]) && $_POST[$name]==='') {
        if ($page_url !== null  && isset($_SESSION[$page_url][$name])) {
            return $_SESSION[$page_url][$name];
        }
        return $default;
    }
    $value = filter_input(INPUT_POST, $name, $filter, $options);
    if ($value === false || $value === null) {
        if ($page_url !== null  && isset($_SESSION[$page_url][$name])) {
            return $_SESSION[$page_url][$name];
        }
        return $default;
    }
    return $value;
}

function intval_or_zero($v): int {
    return is_numeric($v) ? intval($v) : 0;
}

function normalize_vlan($vlan): int {
    $v = intval_or_zero($vlan);
    return ($v >= 1 && $v <= 4096) ? $v : 1;
}

function validate_dhcp_range(int $start, int $stop, int $net_start, int $net_stop): bool {
    if (!$start || !$stop) return false;
    if ($start >= $stop) return false;
    if ($start <= $net_start) return false;
    if ($stop  >= $net_stop) return false;
    return true;
}

function get_dhcp_gateway($gw_raw, $fallback): int {
    $gw = ip2long(trim((string)$gw_raw));
    return $gw ? $gw : $fallback;
}

function safeUrlEncode($url) {
    // Сначала декодируем на случай двойного кодирования
    $decoded = urldecode($url);
    // Если после декодирования получили другой результат - значит был закодирован
    if ($decoded !== $url) {
        // Уже был закодирован - возвращаем декодированную версию
        return $url; // Или $decoded в зависимости от логики
    }
    // Не был закодирован - кодируем
    return urlencode($url);
}

function getSafeRedirectUrl(string $default = '/'): string {
    $url = filter_input(INPUT_GET, 'redirect_url', FILTER_SANITIZE_URL)
        ?? filter_input(INPUT_POST, 'redirect_url', FILTER_SANITIZE_URL)
        ?? $default;

    $default = safeUrlEncode($default);
    $decodedUrl = urldecode($url);

    // Проверяем:
    // 1. URL начинается с `/` (но не `//` или `http://`)
    // 2. Содержит только разрешённые символы (a-z, 0-9, -, _, /, ?, =, &, ., ~)
    if (!preg_match('/^\/(?!\/)[a-z0-9\-_\/?=&.~]*$/i', $decodedUrl)) {
        return $default;
    }

    // Проверяем:
    // 1. Начинается с /, не содержит //, ~, %00
    // 2. Разрешённые символы: a-z, 0-9, -, _, /, ?, =, &, .
    // 3. Допустимые форматы:
    //    - /path/          (слэш на конце)
    //    - /path           (без слэша)
    //    - /file.html      (только .html)
    //    - /script.php     (только .php)
    //    - Любой вариант с параметрами (?id=1)
    if (!preg_match(
        '/^\/'                      // Начинается с /
        . '(?!\/)'                  // Не //
        . '[a-z0-9\-_\/?=&.]*'      // Разрешённые символы
        . '(?:\/'                   // Варианты окончаний:
          . '|\.(html|php)(?:\?[a-z0-9\-_=&]*)?'  // .html/.php (+ параметры)
          . '|(?:\?[a-z0-9\-_=&]*)?' // Или параметры без расширения
        . ')$/i', 
        $decodedUrl
    )) {
        return $default;
    }

    // Дополнительная защита: явно блокируем /config/, /vendor/ и т.д.
    if (preg_match('/(^|\/)(cfg|inc|log|sessions|tmp)(\/|$)/i', $decodedUrl)) {
        return $default;
    }

    return safeUrlEncode($url);
}

function randomPassword($length = 8)
{
    $alphabet = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890';
    $pass = array(); //remember to declare $pass as an array
    $alphaLength = strlen($alphabet) - 1; //put the length -1 in cache
    for ($i = 0; $i < $length; $i++) {
        $n = rand(0, $alphaLength);
        $pass[] = $alphabet[$n];
    }
    return implode($pass); //turn the array into a string
}

if (!function_exists('mb_ucfirst')) {
    function mb_ucfirst(string $string, ?string $encoding = null): string
    {
        if ($string === '') {
            return '';
        }
        $encoding ??= mb_internal_encoding();
        $firstChar = mb_substr($string, 0, 1, $encoding);
        return mb_strtoupper($firstChar, $encoding) . 
               mb_substr($string, 1, null, $encoding);
    }
}

function print_datetime($datetime) {
if (is_empty_datetime($datetime)) { print "-"; } else { print $datetime; }
}

function get_datetime_display($datetime) {
if (is_empty_datetime($datetime)) { return "-"; } else { return $datetime; }
}

function is_empty_datetime($datetime) {
    if (empty($datetime)) {
        return true;
    }
    
    // Проверяем формат даты и "нулевые" значения
    if ($datetime === '0000-00-00 00:00:00') {
        return true;
    }
    
    // Регулярное выражение для даты в формате YYYY-MM-DD HH:MM:SS
    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2}) (\d{2}):(\d{2}):(\d{2})$/', $datetime, $matches)) {
        return false; // Неверный формат — считаем валидным
    }
    
    $year = (int)$matches[1];
    $month = (int)$matches[2];
    $day = (int)$matches[3];
    $hour = (int)$matches[4];
    $minute = (int)$matches[5];
    $second = (int)$matches[6];
    
    // Проверяем, что дата в пределах "нулевого" диапазона
    // Unix epoch начинается с 1970-01-01 00:00:00 UTC
    // Но из-за часовых поясов могут быть варианты вроде 1970-01-01 03:00:00
    if ($year == 1970 && $month == 1 && $day == 1) {
        // Допускаем небольшой диапазон часов (0-12) как "нулевую" дату
        if ($hour >= 0 && $hour <= 12 && $minute == 0 && $second == 0) {
            return true;
        }
    }
    
    // Также проверяем даты до 1970 года (иногда бывают)
    if ($year < 1970) {
        return true;
    }
    
    return false;
}

function get_user_ip()
{
    $auth_ip = getenv("HTTP_CLIENT_IP");
    if (empty($auth_ip)) {
        $auth_ip = getenv("HTTP_X_FORWARDED_FOR");
        if (empty($auth_ip)) {
            $auth_ip = getenv("REMOTE_ADDR");
            if (empty($auth_ip)) {
                $auth_ip = $_SERVER['REMOTE_ADDR'];
            }
        }
    }
    return $auth_ip;
}

function fbytes($traff)
{
    $units_IEC = array(
        "",
        "Ki",
        "Mi",
        "Gi",
        "Ti"
    );

    $units_metric = array(
        "",
        "k",
        "M",
        "G",
        "T"
    );

    if (!empty($traff) and $traff > 0) {
        $KB = get_const('KB');
        if ($KB) {
            $KB = 1024;
        } else {
            $KB = 1000;
        }
        //IEC
        if ($KB == 1024) {
            $index = min(((int) log($traff, $KB)), count($units_IEC) - 1);
            $result = round($traff / pow($KB, $index), 3) . ' ' . $units_IEC[$index] . 'B';
        } else {
            $index = min(((int) log($traff, $KB)), count($units_metric) - 1);
            $result = round($traff / pow($KB, $index), 3) . ' ' . $units_metric[$index] . 'B';
        }
    } else {
        $result = '0 B';
    }
    return $result;
}

function fpkts($packets)
{
    $units = array(
        "",
        "k",
        "M",
        "G",
        "T"
    );
    if (!empty($packets) and $packets > 0) {
        $index = min(((int) log($packets, 1000)), count($units) - 1);
        $result = round($packets / pow(1000, $index), 3) . ' ' . $units[$index] . 'pkt/s';
    } else {
        $result = '0 pkt/s';
    }
    return $result;
}

function checkValidIp($cidr)
{
    if (!is_string($cidr) || empty(trim($cidr))) {
        return false;
    }

    $cidr = trim($cidr);

    // Проверяем IPv4 CIDR
    if (preg_match('/^(\d{1,3}\.){3}\d{1,3}(\/\d{1,2})?$/', $cidr)) {
        return validateIPv4CIDR($cidr);
    }

    // Проверяем IPv6 CIDR
    if (preg_match('/^([0-9a-fA-F:]+)+(\/\d{1,3})?$/i', $cidr)) {
        return validateIPv6CIDR($cidr);
    }

    return false;
}

function validateIPv4CIDR($cidr)
{
    $parts = explode("/", $cidr);
    $ip = $parts[0];
    $netmask = isset($parts[1]) ? (int)$parts[1] : 32;

    // Проверяем октеты
    $octets = explode(".", $ip);
    if (count($octets) !== 4) {
        return false;
    }

    foreach ($octets as $octet) {
        $octet = (int)$octet;
        if ($octet < 0 || $octet > 255) {
            return false;
        }
    }

    return $netmask >= 0 && $netmask <= 32;
}

// для IPv6
function validateIPv6CIDR($cidr)
{
    // Упрощенная проверка IPv6
    $parts = explode("/", $cidr);
    $ip = $parts[0];
    $netmask = isset($parts[1]) ? (int)$parts[1] : 128;

    return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false 
           && $netmask >= 0 && $netmask <= 128;
}

function normalizeIpAddress($input) {
    // Заменяем все возможные варианты "ю" на стандартные точки
    $normalized = str_replace(
        ['ю', 'Ю', '>'], // Кириллические и латинские варианты
        '.', 
        strtolower(trim($input))
    );
    if (!checkValidIp($normalized)) { return ''; }
    return $normalized;
}

function checkValidMac($mac)
{
    if (!is_string($mac)) return false;

    $mac = trim($mac);

    // Отдельные шаблоны для каждого формата
    $patterns = [
        '/^([0-9A-Fa-f]{2}[:-]){5}[0-9A-Fa-f]{2}$/', // 00:1A:2B:3C:4D:5E
        '/^([0-9a-fA-F]{4}[\\.-]){2}[0-9a-fA-F]{4}$/', // 001A.2B3C.4D5E
        '/^[0-9A-Fa-f]{12}$/' // 001A2B3C4D5E
    ];

    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $mac)) {
            return true;
        }
    }

    return false;
}

function MayBeMac($mac)
{
    if (!is_string($mac)) {
        return;
    }

    // Оставляем только hex-символы
    $hex = preg_replace('/[^0-9a-f]/i', '', trim($mac));

    // Длина: от 2 до 6 октетов (4–12 hex-символов)
    if (!preg_match('/^[0-9a-f]{4,12}$/i', $hex)) {
        return;
    }

    // Чётное количество символов (целые октеты)
    if (strlen($hex) % 2 !== 0) {
        return;
    }

    return mac_dotted2($mac);
}

function mac_dotted2($mac)
{
    if (!is_string($mac)) {
        return;
    }

    // оставляем только hex
    $hex = preg_replace('/[^0-9a-f]/i', '', trim($mac));

    // максимум 6 октетов = 12 hex
    if ($hex === '' || strlen($hex) > 12) {
        return;
    }

    // только целые октеты
    if (strlen($hex) % 2 !== 0) {
        return;
    }

    // разбиваем по 2 символа
    return implode(':', str_split(strtolower($hex), 2));
}


function checkValidHostname($dnsname)
{
    if (empty($dnsname)) {
        return TRUE;
    }

    $host_pattern = "/^(([a-zA-Z0-9]|[a-zA-Z0-9][a-zA-Z0-9\-]*[a-zA-Z0-9])\.)*([A-Za-z0-9]|[A-Za-z0-9][A-Za-z0-9\-]*[A-Za-z0-9])\.?$/";
    if (!preg_match($host_pattern, $dnsname)) {
        $result = FALSE;
    } else {
        $result = TRUE;
    }
    return $result;
}

function replaceSpecialChars($input) {
    // Заменяем ? на _ в любом месте
    $result = str_replace('?', '_', $input);
    // Заменяем * на % только в начале и конце с помощью регулярного выражения
    $result = preg_replace('/\*(?=.*.)/', '%', $result);
    return $result;
}

function searchHostname($db, $id, $hostname)
{
    if (empty($hostname)) {
        return null;
    }

    $domain_zone = get_option($db, 33);
    $full_hostname = rtrim($hostname . '.' . ltrim($domain_zone, '.'), '.');
    
    $result_parts = [];

    // === Поиск A-записей в user_auth ===
    $a_sql = "SELECT id, ip FROM user_auth 
              WHERE deleted = 0 
                AND id != ? 
                AND (dns_name = ? OR dns_name = ?)";
    $a_records = get_records_sql($db, $a_sql, [$id, $hostname, $full_hostname]);
    
    if (!empty($a_records)) {
        $a_list = [];
        foreach ($a_records as $rec) {
            $a_list[] = 'auth_id:' . $rec['id'] . ' ip: ' . $rec['ip'];
        }
        $result_parts[] = 'A-record: ' . implode('; ', $a_list) . ';';
    }

    // === Поиск CNAME-записей в user_auth_alias ===
    $cname_sql = "SELECT auth_id FROM user_auth_alias 
                  WHERE auth_id != ? 
                    AND (alias = ? OR alias = ?)";
    $cname_records = get_records_sql($db, $cname_sql, [$id, $hostname, $full_hostname]);
    
    if (!empty($cname_records)) {
        $cname_list = [];
        foreach ($cname_records as $rec) {
            $cname_list[] = 'auth_id:' . $rec['auth_id'];
        }
        $result_parts[] = 'CNAME-record: ' . implode(';', $cname_list) . ';';
    }

    return !empty($result_parts) ? trim(implode(' ', $result_parts)) : '';
}

function checkUniqHostname($db, $id, $hostname)
{
    if (empty($hostname)) {
        return true;
    }

    $domain_zone = get_option($db, 33);
    $full_hostname = rtrim($hostname . '.' . ltrim($domain_zone, '.'), '.');

    // Проверка A-записей в user_auth
    $count_a = get_count_records($db, 'user_auth', 
        'deleted = 0 AND id != ? AND (dns_name = ? OR dns_name = ?)', 
        [$id, $hostname, $full_hostname]
    );
    
    if ($count_a > 0) {
        return false;
    }

    // Проверка CNAME-записей в user_auth_alias
    $count_cname = get_count_records($db, 'user_auth_alias', 
        'auth_id != ? AND (alias = ? OR alias = ?)', 
        [$id, $hostname, $full_hostname]
    );
    
    if ($count_cname > 0) {
        return false;
    }

    return true;
}

// Транслитерация строк.
function transliterate($string, $gost = false)
{
    if ($gost) {
        $replace = array(
            "А" => "A",
            "а" => "a",
            "Б" => "B",
            "б" => "b",
            "В" => "V",
            "в" => "v",
            "Г" => "G",
            "г" => "g",
            "Д" => "D",
            "д" => "d",
            "Е" => "E",
            "е" => "e",
            "Ё" => "E",
            "ё" => "e",
            "Ж" => "Zh",
            "ж" => "zh",
            "З" => "Z",
            "з" => "z",
            "И" => "I",
            "и" => "i",
            "Й" => "I",
            "й" => "i",
            "К" => "K",
            "к" => "k",
            "Л" => "L",
            "л" => "l",
            "М" => "M",
            "м" => "m",
            "Н" => "N",
            "н" => "n",
            "О" => "O",
            "о" => "o",
            "П" => "P",
            "п" => "p",
            "Р" => "R",
            "р" => "r",
            "С" => "S",
            "с" => "s",
            "Т" => "T",
            "т" => "t",
            "У" => "U",
            "у" => "u",
            "Ф" => "F",
            "ф" => "f",
            "Х" => "Kh",
            "х" => "kh",
            "Ц" => "Tc",
            "ц" => "tc",
            "Ч" => "Ch",
            "ч" => "ch",
            "Ш" => "Sh",
            "ш" => "sh",
            "Щ" => "Shch",
            "щ" => "shch",
            "Ы" => "Y",
            "ы" => "y",
            "Э" => "E",
            "э" => "e",
            "Ю" => "Iu",
            "ю" => "iu",
            "Я" => "Ia",
            "я" => "ia",
            "ъ" => "",
            "ь" => ""
        );
    } else {
        $arStrES = array(
            "ае",
            "уе",
            "ое",
            "ые",
            "ие",
            "эе",
            "яе",
            "юе",
            "ёе",
            "ее",
            "ье",
            "ъе",
            "ый",
            "ий"
        );
        $arStrOS = array(
            "аё",
            "уё",
            "оё",
            "ыё",
            "иё",
            "эё",
            "яё",
            "юё",
            "ёё",
            "её",
            "ьё",
            "ъё",
            "ый",
            "ий"
        );
        $arStrRS = array(
            "а$",
            "у$",
            "о$",
            "ы$",
            "и$",
            "э$",
            "я$",
            "ю$",
            "ё$",
            "е$",
            "ь$",
            "ъ$",
            "@",
            "@"
        );

        $replace = array(
            "А" => "A",
            "а" => "a",
            "Б" => "B",
            "б" => "b",
            "В" => "V",
            "в" => "v",
            "Г" => "G",
            "г" => "g",
            "Д" => "D",
            "д" => "d",
            "Е" => "Ye",
            "е" => "e",
            "Ё" => "Ye",
            "ё" => "e",
            "Ж" => "Zh",
            "ж" => "zh",
            "З" => "Z",
            "з" => "z",
            "И" => "I",
            "и" => "i",
            "Й" => "Y",
            "й" => "y",
            "К" => "K",
            "к" => "k",
            "Л" => "L",
            "л" => "l",
            "М" => "M",
            "м" => "m",
            "Н" => "N",
            "н" => "n",
            "О" => "O",
            "о" => "o",
            "П" => "P",
            "п" => "p",
            "Р" => "R",
            "р" => "r",
            "С" => "S",
            "с" => "s",
            "Т" => "T",
            "т" => "t",
            "У" => "U",
            "у" => "u",
            "Ф" => "F",
            "ф" => "f",
            "Х" => "Kh",
            "х" => "kh",
            "Ц" => "Ts",
            "ц" => "ts",
            "Ч" => "Ch",
            "ч" => "ch",
            "Ш" => "Sh",
            "ш" => "sh",
            "Щ" => "Shch",
            "щ" => "shch",
            "Ъ" => "",
            "ъ" => "",
            "Ы" => "Y",
            "ы" => "y",
            "Ь" => "",
            "ь" => "",
            "Э" => "E",
            "э" => "e",
            "Ю" => "Yu",
            "ю" => "yu",
            "Я" => "Ya",
            "я" => "ya",
            "@" => "y",
            "$" => "ye"
        );

        $string = str_replace($arStrES, $arStrRS, $string);
        $string = str_replace($arStrOS, $arStrRS, $string);
    }

    return strtr($string, $replace);
}

function cidrToRange($cidr)
{
    $range = array();
    $cidr = explode('/', $cidr);
    if (!isset($cidr[1]) or empty($cidr[1])) {
        $cidr[1] = 32;
    }
    if (!empty($cidr[1]) and $cidr[1]>32) {
        $cidr[1] = 32;
    }
    $mask = (int)$cidr[1];
    $start = (ip2long($cidr[0])) & ((-1 << (32 - $mask)));
    $stop = $start + pow(2, (32 - $mask)) - 1;
    $range[0] = long2ip($start);
    $range[1] = long2ip($stop);
    $range[2] = $mask;
    //dhcp
    $dhcp_size = round(($stop - $start) / 2, PHP_ROUND_HALF_UP);
    $dhcp_start = $start + round($dhcp_size / 2, PHP_ROUND_HALF_UP);
    $range[3] = long2ip($dhcp_start);
    $range[4] = long2ip($dhcp_start + $dhcp_size);
    //gateway
    $range[5] = long2ip($start + 1);
    return $range;
}

function crypt_string($simple_string)
{
    // Storin gthe cipher method
    $ciphering = "aes-128-cbc";
    // Using OpenSSl Encryption method
    $iv_length = openssl_cipher_iv_length($ciphering);
    $options = 0;
    // Using openssl_encrypt() function to encrypt the data
    return openssl_encrypt($simple_string, $ciphering, ENCRYPTION_KEY, $options, ENCRYPTION_IV);
}

function decrypt_string($crypted_string)
{
    // Storin gthe cipher method
    $ciphering = "aes-128-cbc";
    // Using OpenSSl Encryption method
    $iv_length = openssl_cipher_iv_length($ciphering);
    $options = 0;
    // Using openssl_decrypt() function to decrypt the data
    return  openssl_decrypt($crypted_string, $ciphering, ENCRYPTION_KEY, $options, ENCRYPTION_IV);
}

function print_instance_select($db, $instance_name, $instance_value)
{   
    echo "<select id=\"" . htmlspecialchars($instance_name) . "\" name=\"" . htmlspecialchars($instance_name) . "\">\n";
    $t_instance = get_records_sql($db, "SELECT id, name, description FROM filter_instances ORDER BY id");
    foreach ($t_instance as $row) {
        print_select_item($row['name'], $row['id'], $instance_value);
    }
    echo "</select>\n";
}

function get_subnet_description($db, $subnet_id)
{   
    if (empty($subnet_id)) {
        return '';
    }
    
    $subnet = get_record_sql($db, 'SELECT * FROM subnets WHERE id = ?', [(int)$subnet_id]);
    if (empty($subnet)) {
        return '';
    }
    
    $result = $subnet['subnet'] . '&nbsp;(' . $subnet['description'] . ')';
    return $result;
}

function get_filter_instance_description($db, $instance_id)
{   
    if (empty($instance_id)) {
        return '';
    }
    
    $instance = get_record_sql($db, 'SELECT * FROM filter_instances WHERE id = ?', [(int)$instance_id]);
    if (empty($instance)) {
        return '';
    }
    
    $result = $instance['name'] . '&nbsp;(' . $instance['description'] . ')';
    return $result;
}


function print_add_gw_subnets($db, $device_id, $gs_name)
{   
    echo "<select id=\"" . htmlspecialchars($gs_name) . "\" name=\"" . htmlspecialchars($gs_name) . "\">\n";
    $t_gs = get_records_sql($db, 
        "SELECT id, subnet, description FROM subnets 
         WHERE subnets.free = 0 
           AND subnets.id NOT IN (SELECT subnet_id FROM gateway_subnets WHERE gateway_subnets.device_id = ?) 
         ORDER BY subnet", 
        [(int)$device_id]
    );
    
    if (!empty($t_gs)) {
        foreach ($t_gs as $row) {
            $display = htmlspecialchars($row['subnet']) . '(' . htmlspecialchars($row['description']) . ')';
            print_select_item($display, $row['id'], 0);
        }
    }
    echo "</select>\n";
}

function print_add_gw_instances($db, $device_id, $gs_name)
{
    echo "<select id=\"" . htmlspecialchars($gs_name) . "\" name=\"" . htmlspecialchars($gs_name) . "\">\n";
    $t_gs = get_records_sql($db,
        "SELECT id, name, description FROM filter_instances 
         WHERE filter_instances.id NOT IN (SELECT instance_id FROM device_filter_instances WHERE device_filter_instances.device_id = ?) 
         ORDER BY name",
        [(int)$device_id]
    );
    
    if (!empty($t_gs)) {
        foreach ($t_gs as $row) {
            $display = htmlspecialchars($row['name']) . '(' . htmlspecialchars($row['description']) . ')';
            print_select_item($display, $row['id'], 0);
        }
    }
    echo "</select>\n";
}

function print_add_dev_interface($db, $device_id, $int_list, $int_name)
{
    echo "&nbsp;<select id=\"" . htmlspecialchars($int_name) . "\" name=\"" . htmlspecialchars($int_name) . "\">\n";
    
    $t_int = get_records_sql($db, 
        "SELECT * FROM device_l3_interfaces WHERE device_id = ?", 
        [(int)$device_id]
    );
    
    $int_exists = [];
    if (!empty($t_int)) {
        foreach ($t_int as $interface) {
            $int_exists[$interface['snmpin']] = $interface;
        }
    }
    
    foreach ($int_list as $interface) {
        if (!empty($int_exists[$interface['index']])) {
            continue;
        }
        
        $display_value = WEB_select_item_lan;
        if ($interface['interface_type'] == 1) {
            $display_value = WEB_select_item_wan;
        }
        
        // Отображаемое значение (видит пользователь) — экранируем
        $display_str = htmlspecialchars($interface['name']) . '&nbsp;|' . 
                      htmlspecialchars($interface['ip']) . '|' . 
                      htmlspecialchars($display_value);
        
        // Значение (отправляется на сервер) — НЕ экранируем
        $value = $interface['name'] . ';' . 
                $interface['index'] . ';' . 
                $interface['interface_type'];
        
        print_select_item($display_str, $value, 0);
    }
    echo "</select>\n";
}

function print_ou_select_recursive($db, $ou_name, $ou_value, $parent_id = null, $level = 0, $hide_zero_id = false)
{
    // Ограничение глубины рекурсии: не более 3 уровней (0, 1, 2 → max level=2, следующий вызов будет level=3 и остановится)
    if ($level > 2) {
        return;
    }

    $params = [];

    if ($parent_id === null) {
        $sql = "SELECT id, parent_id, ou_name FROM ou WHERE (parent_id IS NULL OR parent_id = 0)";
    } else {
        $sql = "SELECT id, parent_id, ou_name FROM ou WHERE parent_id = ?";
        $params[] = (int)$parent_id;
    }

    if ($hide_zero_id) {
        $sql .= " AND id != 0";
    }

    $sql .= " ORDER BY id";

    $items = get_records_sql($db, $sql, $params);

    if (empty($items)) {
        return;
    }

    foreach ($items as $row) {
        $indent = str_repeat("&nbsp;&nbsp;&nbsp;", $level);
        $prefix = ($level > 0) ? $indent . "-&nbsp;" : "";
        $display_name = $prefix . htmlspecialchars($row['ou_name']);
        print_select_item($display_name, $row['id'], $ou_value);

        // Рекурсивный вызов — только если уровень < 3
        if ($level < 2) { // потому что следующий уровень будет $level + 1 = 3 → запрещён
            print_ou_select_recursive($db, $ou_name, $ou_value, $row['id'], $level + 1, $hide_zero_id);
        }
    }
}
    
function print_ou_select($db, $ou_name, $ou_value)
{
    echo "<select id=\"" . htmlspecialchars($ou_name) . "\" name=\"" . htmlspecialchars($ou_name) . "\">\n";
    print_ou_select_recursive($db, $ou_name, $ou_value, null, 0, false);
    echo "</select>\n";
}

function print_ou_set($db, $ou_name, $ou_value)
{
    echo "<select id=\"" . htmlspecialchars($ou_name) . "\" name=\"" . htmlspecialchars($ou_name) . "\">\n";
    print_ou_select_recursive($db, $ou_name, $ou_value, null, 0, true);
    echo "</select>\n";
}

function print_subnet_select($db, $subnet_name, $subnet_value)
{
    echo "<select id=\"" . htmlspecialchars($subnet_name) . "\" name=\"" . htmlspecialchars($subnet_name) . "\">\n";
    $t_subnet = get_records_sql($db, "SELECT id, subnet FROM subnets ORDER BY ip_int_start");
    print_select_item(WEB_select_item_all_ips, 0, $subnet_value);
    if (!empty($t_subnet)) {
        foreach ($t_subnet as $row) {
            print_select_item(htmlspecialchars($row['subnet']), $row['id'], $subnet_value);
        }
    }
    echo "</select>\n";
}

function print_acl_select($db, $acl_name, $acl_value)
{
    echo "<select id=\"" . htmlspecialchars($acl_name) . "\" name=\"" . htmlspecialchars($acl_name) . "\">\n";
    $t_acl = get_records_sql($db, "SELECT id, name FROM acl ORDER BY id");
    if (!empty($t_acl)) {
        foreach ($t_acl as $row) {
            print_select_item(htmlspecialchars($row['name']), $row['id'], $acl_value);
        }
    }
    echo "</select>\n";
}

function print_device_ip_select($db, $ip_name, $ip, $user_id)
{
    echo "<select id=\"" . htmlspecialchars($ip_name) . "\" name=\"" . htmlspecialchars($ip_name) . "\">\n";
    $auth_list = get_records_sql($db, "SELECT ip FROM user_auth WHERE user_id = ? AND deleted = 0 ORDER BY ip_int", [(int)$user_id]);
    if (!empty($auth_list)) {
        foreach ($auth_list as $row) {
            // IP-адреса не требуют htmlspecialchars (они безопасны по формату)
            print_select_item($row['ip'], $row['ip'], $ip);
        }
    }
    echo "</select>\n";
}

function print_subnet_select_office($db, $subnet_name, $subnet_value)
{
    echo "<select id=\"" . htmlspecialchars($subnet_name) . "\" name=\"" . htmlspecialchars($subnet_name) . "\">\n";
    $t_subnet = get_records_sql($db, "SELECT id, subnet FROM subnets WHERE office = 1 ORDER BY ip_int_start");
    print_select_item(WEB_select_item_all_ips, 0, $subnet_value);
    if (!empty($t_subnet)) {
        foreach ($t_subnet as $row) {
            print_select_item(htmlspecialchars($row['subnet']), $row['id'], $subnet_value);
        }
    }
    echo "</select>\n";
}

function print_subnet_select_office_splitted($db, $subnet_name, $subnet_value)
{
    echo "<select id=\"" . htmlspecialchars($subnet_name) . "\" name=\"" . htmlspecialchars($subnet_name) . "\">\n";
    $t_subnet = get_records_sql($db, "SELECT id, subnet, ip_int_start, ip_int_stop FROM subnets WHERE office = 1 ORDER BY ip_int_start");
    print_select_item(WEB_select_item_all_ips, 0, $subnet_value);
    
    foreach ($t_subnet as $row) {
        // Основная подсеть
        print_select_item(htmlspecialchars($row['subnet']), $row['subnet'], $subnet_value);
        
        // Разбивка на /24
        $cidr = cidrToRange($row['subnet']);
        $f_start_ip = $row['ip_int_start'];
        $f_stop_ip = $row['ip_int_stop'];
        
        if (!empty($cidr[2][1]) && $cidr[2][1] < 24) {
            while ($f_start_ip <= $f_stop_ip) {
                $ip_24 = long2ip($f_start_ip) . "/24";
                $display = "&nbsp;&nbsp;-&nbsp;" . htmlspecialchars($ip_24);
                print_select_item($display, $ip_24, $subnet_value);
                $f_start_ip += 256;
            }
        }
    }
    echo "</select>\n";
}

function print_loglevel_select($item_name, $value)
{
    echo "<select id=\"" . htmlspecialchars($item_name) . "\" name=\"" . htmlspecialchars($item_name) . "\">\n";
    print_select_item('Error', L_ERROR, $value);
    print_select_item('Warning', L_WARNING, $value);
    print_select_item('Info', L_INFO, $value);
    print_select_item('Verbose', L_VERBOSE, $value);
    print_select_item('Debug', L_DEBUG, $value);
    echo "</select>\n";
}

function print_timeshift_select($value)
{
    echo "<select id=\"date_shift\" name=\"date_shift\" onchange=\"updateDates()\">\n";
    print_select_item('-', '-', $value);
    print_select_item(WEB_date_shift_hour, 'h', $value);
    print_select_item(WEB_date_shift_8hour, '8h', $value);
    print_select_item(WEB_date_shift_day, 'd', $value);
    print_select_item(WEB_date_shift_month, 'm', $value);
    echo "</select>\n";
}

function print_date_fields($date1,$date2,$date_shift)
{
print WEB_log_start_date.'&nbsp'; print '<input type="datetime-local" name="date_start" id="date_start" value="'.$date1.'" onchange="SetDateShiftCustom()"/>';
print WEB_log_stop_date.'&nbsp'; print '<input type="datetime-local" name="date_stop" id="date_stop" value="'.$date2.'" onchange="SetDateShiftCustom()"/>';
print WEB_date_shift.'&nbsp'; print_timeshift_select($date_shift);
}

function reencodeurl($url)
{
    $url_arr = explode('?', $url);
    $fpage = $url_arr[0];
    if (isset($url_arr[1])) {
        $params = $url_arr[1];
        $params_arr = explode('&', $params);
        $new_params = '';
        foreach ($params_arr as $row) {
            $param = explode('=', $row);
            $key = $param[0];
            $value = urlencode(urldecode($param[1]));
            $new_params .= "&" . $key . "=" . $value;
        }
        $new_params = preg_replace('/^&/', '', $new_params);
    } else {
        $new_params = '=';
    }
    if ($new_params === '=') {
        $new_url = $fpage;
    } else {
        $new_url = $fpage . "?" . $new_params;
    }
    return $new_url;
}

function print_submenu_url($display_name, $page, $current_page, $last)
{
    $url_arr = explode('?', $page);
    $fpage = $url_arr[0];
    $new_url = reencodeurl($page);
    if ($fpage === $current_page) {
        print "<b>$display_name</b>";
    } else {
        print "<a href='" . $new_url . "'> $display_name </a>";
    }
    if (!isset($last) or $last == 0) {
        print " | ";
    }
}

function print_submenu_nw($display_name, $page, $current_page, $last)
{
    $url_arr = explode('?', $page);
    $fpage = $url_arr[0];
    $new_url = reencodeurl($page);
    if ($fpage === $current_page) {
        print "<b>$display_name</b>";
    } else {
        print '<a href="" onclick="window.open(\'' . $new_url . "', '_tab').focus(); return false;\">" . $display_name . "</a>";
    }
    if (!isset($last) or $last == 0) {
        print " | ";
    }
}

function print_url($display_name, $page)
{
    print "<a href='" . reencodeurl($page) . "'> $display_name </a>";
}

function print_log_submenu($current_page)
{
    print "<div id='submenu'>\n";
    print_submenu_url(WEB_submenu_dhcp_log, '/admin/logs/dhcp.php', $current_page, 0);
    print_submenu_url(WEB_submenu_work_log, '/admin/logs/index.php', $current_page, 0);
    print_submenu_url(WEB_submenu_mac_history, '/admin/logs/mac.php', $current_page, 0);
    print_submenu_url(WEB_submenu_ip_history, '/admin/logs/ip.php', $current_page, 0);
    print_submenu_url(WEB_submenu_mac_unknown, '/admin/logs/unknown.php', $current_page, 0);
    print_submenu_url(WEB_submenu_traffic, '/admin/logs/detaillog.php', $current_page, 0);
    print_submenu_url(WEB_submenu_syslog, '/admin/logs/syslog.php', $current_page, 1);
    print "</div>\n";
}

function print_control_submenu($current_page)
{
    print "<div id='submenu'>\n";
    print_submenu_url(WEB_submenu_control, '/admin/customers/control.php', $current_page, 0);
    print_submenu_url(WEB_submenu_network, '/admin/customers/index-subnets.php', $current_page, 0);
    print_submenu_url(WEB_submenu_network_stats, '/admin/customers/control-subnets-usage.php', $current_page, 0);
    print_submenu_url(WEB_submenu_options, '/admin/customers/control-options.php', $current_page, 0);
    print_submenu_url(WEB_submenu_customers, '/admin/customers/index.php', $current_page, 0);
    print_submenu_url(WEB_submenu_buildings, '/admin/customers/building.php', $current_page, 0);
    print_submenu_url(WEB_submenu_device_models, '/admin/customers/devmodels.php', $current_page, 0);
    print_submenu_url(WEB_submenu_vendors, '/admin/customers/devvendors.php', $current_page, 1);
    print "</div>\n";
}

function print_filters_submenu($current_page)
{
    print "<div id='submenu'>\n";
    print_submenu_url(WEB_submenu_filter_list, '/admin/filters/index.php', $current_page, 0);
    print_submenu_url(WEB_submenu_filter_group, '/admin/filters/groups.php', $current_page, 0);
    print_submenu_url(WEB_submenu_filter_instances, '/admin/filters/instances.php', $current_page, 1);
    print "</div>\n";
}

function print_reports_submenu($current_page)
{
    print "<div id='submenu'>\n";
    print_submenu_url(WEB_submenu_traffic_ip_report, '/admin/reports/index-full.php', $current_page, 0);
    print_submenu_url(WEB_submenu_traffic_login_report, '/admin/reports/index.php', $current_page, 0);
    print_submenu_url(WEB_submenu_traffic_wan_report, '/admin/reports/wan.php', $current_page, 1);
    print "</div>\n";
}

function print_trafdetail_submenu($current_page, $params, $description)
{
    print "<div id='subsubmenu'>\n";
    print "$description\n";
    print_submenu_url(WEB_submenu_traffic_top10, '/admin/reports/userdaydetail.php' . "?$params", $current_page, 0);
    print_submenu_url(WEB_submenu_detail_log, '/admin/reports/userdaydetaillog.php' . "?$params", $current_page, 1);
    print "</div>\n";
}

function print_device_submenu($current_page)
{
    print "<div id='submenu'>\n";
    print_submenu_url(WEB_submenu_net_devices, '/admin/devices/index.php', $current_page, 0);
    print_submenu_url(WEB_submenu_passive_net_devices, '/admin/devices/index-passive.php', $current_page, 0);
    print_submenu_url(WEB_submenu_hierarchy, '/admin/devices/index-tree.php', $current_page, 0);
    print_submenu_url(WEB_submenu_ports_vlan, '/admin/devices/portsbyvlan.php', $current_page, 1);
    print "</div>\n";
}

function open_window_url($url)
{
    return "window.open('" . $url . "', '_blank');";
}

function print_editdevice_submenu($current_page, $id, $dev_type, $dev_name = NULL)
{
    print "<div id='subsubmenu'>\n";
    $dev_id = '';
    if (isset($id)) {
        $dev_id = '?id=' . $id;
    }
    if (!empty($dev_name)) {
        print "<b>" . $dev_name . "::</b>";
    }
    print_submenu_url(WEB_submenu_options, '/admin/devices/editdevice.php' . $dev_id, $current_page, 0);
    if ($dev_type <= 2) {
        print_submenu_url(WEB_submenu_ports, '/admin/devices/switchport.php' . $dev_id, $current_page, 0);
        print_submenu_url(WEB_submenu_state, '/admin/devices/switchstatus.php' . $dev_id, $current_page, 0);
        print_submenu_url(WEB_submenu_connections, '/admin/devices/switchport-conn.php' . $dev_id, $current_page, 1);
    }
    print "</div>\n";
}

function print_ip_submenu($current_page)
{
    print "<div id='submenu'>\n";
    print_submenu_url(WEB_submenu_ip_list, '/admin/iplist/index.php', $current_page, 0);
    print_submenu_url(WEB_submenu_nagios, '/admin/iplist/nagios.php', $current_page, 0);
    print_submenu_url(WEB_submenu_doubles, '/admin/iplist/doubles.php', $current_page, 0);
    print_submenu_url(WEB_submenu_deleted, '/admin/iplist/deleted.php', $current_page, 0);
    print_submenu_url(WEB_submenu_auto_rules, '/admin/iplist/auto_rules.php', $current_page, 1);
    print "</div>\n";
}

function get_nagios_name($auth)
{
    if (!empty($auth['dns_name'])) {
        return $auth['dns_name'];
    }
    if (!empty($auth['dhcp_hostname'])) {
        return $auth['dhcp_hostname'];
    }
    if (!empty($auth['description'])) {
        $result = transliterate($auth['description']);
        $result = preg_replace('/\(/', '-', $result);
        $result = preg_replace('/\)/', '-', $result);
        $result = preg_replace('/--/', '-', $result);
        return $result;
    }
    if (empty($auth['login'])) {
        $auth['login'] = 'host';
    }
    return $auth['login'] . "_" . $auth['id'];
}

function get_ou($db, $ou_value)
{
    if (!isset($ou_value)) {
        return null;
    }
    
    $ou_name = get_record_sql($db, "SELECT ou_name FROM ou WHERE id = ?", [(int)$ou_value]);
    if (empty($ou_name)) {
        return null;
    }
    
    return $ou_name['ou_name'];
}

function get_device_model($db, $model_value)
{
    if (!isset($model_value)) {
        return null;
    }
    
    $model_name = get_record_sql($db, "SELECT model_name FROM device_models WHERE id = ?", [(int)$model_value]);
    if (empty($model_name)) {
        return null;
    }
    
    return $model_name['model_name'];
}

function get_device_model_name($db, $model_value)
{   
    if (!isset($model_value)) {
        return '';
    }
    
    $model_name = get_record_sql($db, 
        "SELECT M.id, M.model_name, V.name 
         FROM device_models M, vendors V 
         WHERE M.vendor_id = V.id AND M.id = ?", 
        [(int)$model_value]
    );
    
    if (empty($model_name)) {
        return '';
    }
    
    return $model_name['name'] . ' ' . $model_name['model_name'];
}

function get_device_model_vendor($db, $model_value)
{   
    if (!isset($model_value)) {
        return '';
    }
    
    $model_name = get_record_sql($db, "SELECT vendor_id FROM device_models WHERE id = ?", [(int)$model_value]);
    if (empty($model_name)) {
        return '';
    }
    
    return $model_name['vendor_id'];
}

function get_building($db, $building_value)
{
    if (!isset($building_value)) {
        return null;
    }
    
    $building_name = get_record_sql($db, "SELECT name FROM building WHERE id = ?", [(int)$building_value]);
    if (empty($building_name)) {
        return null;
    }
    
    return $building_name['name'];
}

function print_device_model_select($db, $device_model_name, $device_model_value)
{
    echo "<select id=\"" . htmlspecialchars($device_model_name) . "\" name=\"" . htmlspecialchars($device_model_name) . "\" class=\"js-select-single\">\n";
    $t_device_model = get_records_sql($db, 
        "SELECT M.id, M.model_name, V.name 
         FROM device_models M, vendors V 
         WHERE M.vendor_id = V.id 
         ORDER BY V.name, M.model_name"
    );
    
    foreach ($t_device_model as $row) {
        $display = htmlspecialchars($row['name']) . " " . htmlspecialchars($row['model_name']);
        print_select_item($display, $row['id'], $device_model_value);
    }
    echo "</select>\n";
}

function print_filter_group_select($db, $group_name, $group_value)
{
    echo "<select id=\"" . htmlspecialchars($group_name) . "\" name=\"" . htmlspecialchars($group_name) . "\">\n";
    $t_group = get_records_sql($db, "SELECT id, group_name FROM group_list ORDER BY group_name");
    
    foreach ($t_group as $row) {
        print_select_item(htmlspecialchars($row['group_name']), $row['id'], $group_value);
    }
    echo "</select>\n";
}

function print_building_select($db, $building_name, $building_value)
{
    echo "<select id=\"" . htmlspecialchars($building_name) . "\" name=\"" . htmlspecialchars($building_name) . "\">\n";
    print_select_item(WEB_select_item_all, 0, $building_value);
    $t_building = get_records_sql($db, "SELECT id, name FROM building ORDER BY name");
    
    foreach ($t_building as $row) {
        print_select_item(htmlspecialchars($row['name']), $row['id'], $building_value);
    }
    echo "</select>\n";
}

function print_devmodels_select($db, $devmodel_name, $devmodel_value, $dev_filter = 'device_type<=2')
{
    // Валидация фильтра для предотвращения SQL-инъекций
    $allowed_filters = [
        'device_type<=2',
        'device_type=2',
        'device_type=1',
        'device_type=0'
    ];
    
    if (!in_array($dev_filter, $allowed_filters)) {
        $dev_filter = 'device_type<=2';
    }
    
    echo "<select id=\"" . htmlspecialchars($devmodel_name) . "\" name=\"" . htmlspecialchars($devmodel_name) . "\">\n";
    print_select_item(WEB_select_item_all, -1, $devmodel_value);
    
    $t_devmodel = get_records_sql($db,
        "SELECT M.id, V.name, M.model_name 
         FROM device_models M, vendors V 
         WHERE M.vendor_id = V.id 
           AND M.id IN (SELECT device_model_id FROM devices WHERE $dev_filter) 
         ORDER BY V.name, M.model_name"
    );
    
    if (!empty($t_devmodel)) {
        foreach ($t_devmodel as $row) {
            $display = htmlspecialchars($row['name']) . " " . htmlspecialchars($row['model_name']);
            print_select_item($display, $row['id'], $devmodel_value);
        }
    }
    echo "</select>\n";
}

function print_devtypes_select($db, $devtype_name, $devtype_value, $mode)
{
    // Валидация режима для предотвращения SQL-инъекций
    $allowed_modes = [
        '',
        'device_class = 1',
        'device_class = 2',
        'device_class <= 2',
        'id IN (0,1,2)',
        'id > 0'
        // Добавьте другие допустимые значения по необходимости
    ];
    
    if (!in_array($mode, $allowed_modes)) {
        $mode = '';
    }
    
    echo "<select id=\"" . htmlspecialchars($devtype_name) . "\" name=\"" . htmlspecialchars($devtype_name) . "\">\n";
    print_select_item(WEB_select_item_all, -1, $devtype_value);
    
    $filter = '';
    if (!empty($mode)) {
        $filter = "WHERE $mode";
    }
    
    $lang_column = 'name_' . HTML_LANG;
    $t_devtype = get_records_sql($db, "SELECT id, $lang_column FROM device_types $filter ORDER BY $lang_column");
    
    if (!empty($t_devtype)) {
        foreach ($t_devtype as $row) {
            print_select_item(htmlspecialchars($row[$lang_column]), $row['id'], $devtype_value);
        }
    }
    echo "</select>\n";
}

function print_devtype_select($db, $devtype_name, $devtype_value)
{
    echo "<select id=\"" . htmlspecialchars($devtype_name) . "\" name=\"" . htmlspecialchars($devtype_name) . "\">\n";
    
    $lang_column = 'name_' . HTML_LANG;
    $t_devtype = get_records_sql($db, "SELECT id, $lang_column FROM device_types ORDER BY $lang_column");
    
    foreach ($t_devtype as $row) {
        print_select_item(htmlspecialchars($row[$lang_column]), $row['id'], $devtype_value);
    }
    echo "</select>\n";
}

function get_group($db, $group_value)
{
    if (!isset($group_value)) {
        return '';
    }
    
    $group = get_record_sql($db, "SELECT group_name FROM group_list WHERE id = ?", [(int)$group_value]);
    if (!empty($group) && isset($group['group_name'])) {
        return $group['group_name'];
    }
    return '';
}

function get_devtype_name($db, $device_type_id)
{
    if (!isset($device_type_id)) {
        return '';
    }
    
    $lang_column = 'name_' . HTML_LANG;
    $type = get_record_sql($db, "SELECT $lang_column FROM device_types WHERE id = ?", [(int)$device_type_id]);
    if (!empty($type) && isset($type[$lang_column])) {
        return $type[$lang_column];
    }
    return '';
}

function get_l3_interfaces($db, $device_id)
{
    $wan = '';
    $lan = '';
    
    $t_l3int = get_records_sql($db, 
        "SELECT name, interface_type FROM device_l3_interfaces WHERE device_id = ? ORDER BY name", 
        [(int)$device_id]
    );
    
    if (empty($t_l3int)) { 
        return ''; 
    }
    
    foreach ($t_l3int as $row) {
        // Экранируем имя интерфейса для защиты от XSS при выводе
        $name = htmlspecialchars($row['name']);
        
        if ($row['interface_type'] == 0) {
            $lan .= " " . $name;
        }
        if ($row['interface_type'] == 1) {
            $wan .= " " . $name;
        }
    }
    
    $wan = trim($wan);
    $lan = trim($lan);
    $result = '';
    
    if (!empty($wan)) {
        $result .= ' WAN: ' . $wan . '<br>';
    }
    if (!empty($lan)) {
        $result .= ' LAN: ' . $lan;
    }
    
    return trim($result);
}

function get_wan_interfaces($db, $device_id)
{
    $device_id = (int)$device_id;
    
    // Получаем WAN-интерфейсы
    $t_l3int = get_records_sql($db, 
        "SELECT id, name, snmpin FROM device_l3_interfaces 
         WHERE device_id = ? AND interface_type = 1 
         ORDER BY name", 
        [$device_id]
    );
    
    // Обрабатываем каждый интерфейс
    foreach ($t_l3int as &$row) {
        $row['description'] = '';
        
        if (empty($row['snmpin'])) {
            continue;
        }
        
        // Получаем описание порта
        $conn = get_record_sql($db, 
            "SELECT description FROM device_ports 
             WHERE device_id = ? AND snmp_index = ?", 
            [$device_id, $row['snmpin']]
        );
        
        // Проверяем, есть ли комментарий в результатах запроса
        if (!empty($conn) && !empty($conn['description'])) {
            $row['description'] = htmlspecialchars($conn['description']);
        }
    }
    unset($row);
    
    return $t_l3int;
}

function get_gw_subnets($db, $device_id)
{
    $gw_subnets = get_records_sql($db, 
        'SELECT gateway_subnets.*, subnets.subnet, subnets.description 
         FROM gateway_subnets 
         LEFT JOIN subnets ON gateway_subnets.subnet_id = subnets.id 
         WHERE gateway_subnets.device_id = ? 
         ORDER BY subnets.subnet ASC', 
        [(int)$device_id]
    );
    
    if (empty($gw_subnets)) { 
        return ''; 
    }
    
    $result = '';
    foreach ($gw_subnets as $row) {
        if (!empty($row['subnet'])) {
            $result .= ' ' . htmlspecialchars($row['subnet']) . '<br>';
        }
    }
    
    return trim($result);
}

function print_queue_select($db, $queue_name, $queue_value)
{   
    echo "<select id=\"" . htmlspecialchars($queue_name) . "\" name=\"" . htmlspecialchars($queue_name) . "\">\n";
    $t_queue = get_records_sql($db, "SELECT id, queue_name FROM queue_list ORDER BY queue_name");
    
    foreach ($t_queue as $row) {
        print_select_item(htmlspecialchars($row['queue_name']), $row['id'], $queue_value);
    }
    echo "</select>\n";
}

function get_queue($db, $queue_value)
{   
    if (!isset($queue_value)) {
        return '';
    }
    
    $queue = get_record_sql($db, "SELECT queue_name FROM queue_list WHERE id = ?", [(int)$queue_value]);
    if (!empty($queue) && isset($queue['queue_name'])) {
        return $queue['queue_name'];
    }
    return '';
}

function print_qa_l3int_select($qa_name, $qa_value = 0)
{
    print "<select id=\"$qa_name\" name=\"$qa_name\">\n";
    print_select_item(WEB_select_item_lan, 0, $qa_value);
    print_select_item(WEB_select_item_wan, 1, $qa_value);
    print "</select>\n";
}

function print_qa_rule_select($qa_name, $qa_value = 1)
{
    print "<select id=\"$qa_name\" name=\"$qa_name\">\n";
    print_select_item('Subnet', 1, $qa_value);
    print_select_item('Mac', 2, $qa_value);
    print_select_item('Hostname', 3, $qa_value);
    print "</select>\n";
}

function print_snmp_auth_proto_select($qa_name, $qa_value = 'sha512')
{
    print "<select id=\"$qa_name\" name=\"$qa_name\">\n";
    print_select_item('sha512', 'sha512', $qa_value);
    print_select_item('sha256', 'sha256', $qa_value);
    print_select_item('sha', 'sha', $qa_value);
    print_select_item('md5', 'md5', $qa_value);
    print "</select>\n";
}

function print_snmp_priv_proto_select($qa_name, $qa_value = 'aes128')
{
    print "<select id=\"$qa_name\" name=\"$qa_name\">\n";
    print_select_item('aes128', 'aes128', $qa_value);
    print_select_item('aes', 'aes', $qa_value);
    print_select_item('des', 'des', $qa_value);
    print "</select>\n";
}

function get_int($qa_value = 0)
{
    if (empty($qa_value)) {
        $qa_value = 0;
    } else {
        $qa_value = (int)$qa_value * 1;
    }
    return $qa_value;
}

function print_qa_select($qa_name, $qa_value = 0)
{
    print "<select name=\"$qa_name\" id=\"$qa_name\">\n";
    if (empty($qa_value)) {
        $qa_value = 0;
    } else {
        $qa_value = $qa_value * 1;
    }
    print_select_item(WEB_select_item_yes, 1, $qa_value);
    print_select_item(WEB_select_item_no, 0, $qa_value);
    print "</select>\n";
}

function print_list_select($qa_name, $qa_value, $list)
{
    print "<select id=\"$qa_name\" name=\"$qa_name\">\n";
    if (empty($qa_value)) {
        $qa_value = '';
    }
    for ($i = 0; $i < count($list); ++$i) {
        print_select_item($list[$i], $list[$i], $qa_value);
    }
    print "</select>\n";
}

function print_qa_select_ext($qa_name, $qa_value = 0, $readonly = 1)
{
    $state = '';
    if ($readonly) {
        $state = 'disabled=true';
    }
    print "<select id=\"$qa_name\" name=\"$qa_name\">\n";
    print_select_item_ext(WEB_select_item_yes, 1, $qa_value, $readonly);
    print_select_item_ext(WEB_select_item_no, 0, $qa_value, $readonly);
    print "</select>\n";
}

function print_td_yes_no($qa_value = 0, $text = FALSE)
{
    $cl = 'down';
    if ($qa_value == 1) { $cl = 'up'; }
//    $cl = 'data';
    if ($text) { 
        print "<td class=\"$cl\">";
        if ($qa_value == 1) { print WEB_select_item_yes; } else { print WEB_select_item_no; }
        print "</td>\n";
        } else { print_td_qa($qa_value,FALSE,$cl); }
}

function print_yes_no($qa_value = 0, $yes_style = 'data', $no_style='data')
{
    if ($qa_value) { $cl = $yes_style; } else { $cl = $no_style; }
    print "<td class=\"$cl\">";
    if ($qa_value == 1) {
        print WEB_select_item_yes;
    } else {
        print WEB_select_item_no;
    }
    print "</td>\n";
}

function print_control_proto_select($qa_name, $qa_value = -1)
{
    print "<select name=\"$qa_name\">\n";
    print_select_item('Disabled', -1, $qa_value);
    print_select_item('Ssh', 0, $qa_value);
    print_select_item('Telnet', 1, $qa_value);
    //   print_select_item('Mikrotik rest api', 2, $qa_value);
    print "</select>\n";
}

function print_snmp_select($qa_name, $qa_value = 0)
{
    print "<select id=\"$qa_name\" name=\"$qa_name\">\n";
    print_select_item('Disabled', 0, $qa_value);
    print_select_item('v1', 1, $qa_value);
    print_select_item('v2', 2, $qa_value);
    print_select_item('v3', 3, $qa_value);
    print "</select>\n";
}

function print_dhcp_select($qa_name, $qa_value = 0)
{
    print "<select id=\"$qa_name\" name=\"$qa_name\">\n";
    if (!isset($qa_value) or strlen($qa_value) == 0) {
        $qa_value = 'all';
    }
    print_select_item(WEB_select_item_events, 'all', $qa_value);
    print_select_item(WEB_select_item_lease, 'add', $qa_value);
    print_select_item(WEB_select_item_lease_refresh, 'old', $qa_value);
    print_select_item(WEB_select_item_lease_free, 'del', $qa_value);
    print "</select>\n";
}

function print_nagios_handler_select($db, $qa_name)
{   
    $nagios_handler = get_records_sql($db, 
        "SELECT DISTINCT nagios_handler FROM user_auth 
         WHERE nagios_handler IS NOT NULL AND nagios_handler != '' AND deleted = 0"
    );
    
    if (!empty($nagios_handler)) {
        echo "<select name=\"" . htmlspecialchars($qa_name) . "\">\n";
        print_select_simple(WEB_select_item_no, '');
        
        foreach ($nagios_handler as $handler) {
            $value = htmlspecialchars($handler['nagios_handler']);
            print_select_simple($value, $value);
        }
        echo "</select>\n";
    } else {
        echo "<input type=\"text\" name=\"" . htmlspecialchars($qa_name) . "\" value=\"\" size=\"10\" />\n";
    }
}

function print_dhcp_acl($db, $qa_name)
{
    $dhcp_acl = get_records_sql($db,
        "SELECT DISTINCT dhcp_acl FROM user_auth 
         WHERE dhcp_acl IS NOT NULL AND dhcp_acl != '' AND deleted = 0"
    );
    
    if (!empty($dhcp_acl)) {
        echo "<select name=\"" . htmlspecialchars($qa_name) . "\">\n";
        print_select_simple(WEB_select_item_no, '');
        
        foreach ($dhcp_acl as $acl) {
            $value = htmlspecialchars($acl['dhcp_acl']);
            print_select_simple($value, $value);
        }
        echo "</select>\n";
    } else {
        echo "<input type=\"text\" name=\"" . htmlspecialchars($qa_name) . "\" value=\"\" size=\"10\" />\n";
    }
}
    
function print_dhcp_option_set($db, $qa_name)
{   
    $dhcp_option_sets = get_records_sql($db,
        "SELECT DISTINCT dhcp_option_set FROM user_auth 
         WHERE dhcp_option_set IS NOT NULL AND dhcp_option_set != '' AND deleted = 0"
    );
    
    if (!empty($dhcp_option_sets)) {
        echo "<select name=\"" . htmlspecialchars($qa_name) . "\">\n";
        print_select_simple(WEB_select_item_no, '');
        
        foreach ($dhcp_option_sets as $dhcp_option_set) {
            $value = htmlspecialchars($dhcp_option_set['dhcp_option_set']);
            print_select_simple($value, $value);
        }
        echo "</select>\n";
    } else {
        echo "<input type=\"text\" name=\"" . htmlspecialchars($qa_name) . "\" value=\"\" size=\"10\" />\n";
    }
}

function print_dhcp_acl_list($db, $qa_name, $value = '')
{
    $dhcp_acl = get_records_sql($db,
        "SELECT DISTINCT dhcp_acl FROM user_auth 
         WHERE dhcp_acl IS NOT NULL AND dhcp_acl != '' AND deleted = 0"
    );
    
    if (!empty($dhcp_acl)) {
        echo "<input list=\"dhcp_acl\" id=\"" . htmlspecialchars($qa_name) . "\" name=\"" . htmlspecialchars($qa_name) . "\" value=\"" . htmlspecialchars($value) . "\" />";
        echo "<datalist id=\"dhcp_acl\">";
        echo "<option value=\"\">";
        
        foreach ($dhcp_acl as $acl) {
            echo "<option value=\"" . htmlspecialchars($acl['dhcp_acl']) . "\">";
        }
        echo "</datalist>";
    } else {
        echo "<input type=\"text\" name=\"" . htmlspecialchars($qa_name) . "\" value=\"\" size=\"10\" />";
    }
}

function print_dhcp_option_set_list($db, $qa_name, $value = '')
{
    $dhcp_option_sets = get_records_sql($db,
        "SELECT DISTINCT dhcp_option_set FROM user_auth 
         WHERE dhcp_option_set IS NOT NULL AND dhcp_option_set != '' AND deleted = 0"
    );
    
    if (!empty($dhcp_option_sets)) {
        echo "<input list=\"dhcp_option_set\" id=\"" . htmlspecialchars($qa_name) . "\" name=\"" . htmlspecialchars($qa_name) . "\" value=\"" . htmlspecialchars($value) . "\" />";
        echo "<datalist id=\"dhcp_option_set\">";
        echo "<option value=\"\">";
        
        foreach ($dhcp_option_sets as $dhcp_option_set) {
            echo "<option value=\"" . htmlspecialchars($dhcp_option_set['dhcp_option_set']) . "\">";
        }
        echo "</datalist>";
    } else {
        echo "<input type=\"text\" name=\"" . htmlspecialchars($qa_name) . "\" value=\"\" size=\"10\" />";
    }
}

function print_enabled_select($qa_name, $qa_value)
{
    // Убедимся, что значение корректно
    if (!isset($qa_value) || $qa_value === '') {
        $qa_value = 0;
    } else {
        $qa_value = (int)$qa_value;
    }
    
    echo "<select id=\"" . htmlspecialchars($qa_name) . "\" name=\"" . htmlspecialchars($qa_name) . "\">\n";
    print_select_item('-', 0, $qa_value);
    print_select_item(WEB_select_item_disabled, 1, $qa_value);
    print_select_item(WEB_select_item_enabled, 2, $qa_value);
    echo "</select>\n";
}

function print_rule_target_select($qa_name, $qa_value)
{
    // Убедимся, что значение корректно
    if (!isset($qa_value) || $qa_value === '') {
        $qa_value = 0;
    } else {
        $qa_value = (int)$qa_value;
    }
    
    echo "<select id=\"" . htmlspecialchars($qa_name) . "\" name=\"" . htmlspecialchars($qa_name) . "\">\n";
    print_select_item('-', 0, $qa_value);
    print_select_item(WEB_rules_target_user, 1, $qa_value);
    print_select_item(WEB_rules_target_group, 2, $qa_value);
    echo "</select>\n";
}

function print_rule_type_select($qa_name, $qa_value)
{
    print "<select id=\"$qa_name\" name=\"$qa_name\">\n";
    if (!isset($qa_value) or strlen($qa_value) == 0) {
        $qa_value = 0;
    }
    print_select_item('-', 0, $qa_value);
    print_select_item(WEB_rules_type_subnet, 1, $qa_value);
    print_select_item(WEB_rules_type_mac, 2, $qa_value);
    print_select_item(WEB_rules_type_hostname, 3, $qa_value);
    print "</select>\n";
}

function print_yn_select($qa_name, $qa_value)
{
    print "<select id=\"$qa_name\" name=\"$qa_name\">\n";
    if (!isset($qa_value) or strlen($qa_value) == 0) {
        $qa_value = 0;
    }
    print_select_item('-', 0, $qa_value);
    print_select_item(WEB_select_item_yes, 1, $qa_value);
    print_select_item(WEB_select_item_no, 2, $qa_value);
    print "</select>\n";
}

function print_ip_type_select($qa_name, $qa_value)
{
    print "<select id=\"$qa_name\" name=\"$qa_name\">\n";
    if (!isset($qa_value) or strlen($qa_value) == 0) {
        $qa_value = 0;
    }
    print_select_item(WEB_select_item_every, 0, $qa_value);
    print_select_item(WEB_select_item_static, 1, $qa_value);
    print_select_item(WEB_select_item_dhcp, 2, $qa_value);
    print_select_item(WEB_select_item_suspicious, 3, $qa_value);
    print "</select>\n";
}

function print_vendor_select($db, $qa_name, $qa_value)
{   
    echo "<select id=\"" . htmlspecialchars($qa_name) . "\" name=\"" . htmlspecialchars($qa_name) . "\" style=\"width: 100%\">\n";
    $vendors = get_records_sql($db, "SELECT id, name FROM vendors ORDER BY name");
    print_select_item(WEB_select_item_all, 0, $qa_value);
    
    foreach ($vendors as $row) {
        print_select_item(htmlspecialchars($row['name']), $row['id'], $qa_value);
    }
    echo "</select>\n";
}

function print_vendor_set($db, $qa_name, $qa_value)
{
    echo "<select id=\"" . htmlspecialchars($qa_name) . "\" name=\"" . htmlspecialchars($qa_name) . "\" style=\"width: 100%\">\n";
    $vendors = get_records_sql($db, "SELECT id, name FROM vendors ORDER BY name");
    
    foreach ($vendors as $row) {
        print_select_item(htmlspecialchars($row['name']), $row['id'], $qa_value);
    }
    echo "</select>\n";
}
    
function get_vendor_name($db, $v_id)
{   
    if (!isset($v_id)) {
        return '';
    }
    
    $vendor = get_record_sql($db, "SELECT name FROM vendors WHERE id = ?", [(int)$v_id]);
    if (!empty($vendor) && isset($vendor['name'])) {
        return $vendor['name'];
    }
    return '';
}

function get_qa($qa_value, $text = FALSE)
{
    if ($text) {
        if ($qa_value == 1) { return "Да"; }
        return "Нет";
        } else {
        if ($qa_value == 1) { return '<span style="font-size: 24px; font-weight: bold;">✓</span>'; }
        return '<span style="font-size: 24px; font-weight: bold;">✗</span>';
        }
}

function get_yes($qa_value, $text = FALSE)
{
    if ($text) {
        if ($qa_value == 1) { return "Да"; }
        return "";
        } else {
        if ($qa_value == 1) { return '<span style="font-size: 16px; font-weight: bold;">✓</span>'; }
        return "";
        }
}

function print_td_qa ($qa_value, $text = FALSE, $parent_class = '')
{
$cl = "data_green";
if (!$qa_value) { $cl = "data_red"; }
print "<td class=\"$parent_class $cl\" >" . get_qa($qa_value,$text) . "</td>";
}

function print_td_yes ($qa_value, $text = FALSE, $parent_class = '')
{
$cl = "data_green";
if (!$qa_value) { $cl = "data_red"; }
print "<td class=\"$parent_class $cl\" >" . get_yes($qa_value,$text) . "</td>";
}

function print_action_select($action_name, $action_value)
{
    print "<select id=\"$action_name\" name=\"$action_name\">\n";
    print_select_item(WEB_select_item_allow, 1, $action_value);
    print_select_item(WEB_select_item_forbidden, 0, $action_value);
    print "</select>\n";
}

function get_action($action_value)
{
    if ($action_value == 1) {
        return "Разрешить";
    }
    return "Запретить";
}

function print_filter_select($db, $filter_name, $group_id)
{   
    echo "<select id=\"" . htmlspecialchars($filter_name) . "\" name=\"" . htmlspecialchars($filter_name) . "\" class=\"js-select-single\">\n";
    
    if (isset($group_id)) {
        $t_filters = get_records_sql($db,
            "SELECT id, name FROM filter_list 
             WHERE id NOT IN (SELECT filter_id FROM group_filters WHERE group_id = ?) 
             ORDER BY name",
            [(int)$group_id]
        );
    } else {
        $t_filters = get_records_sql($db, "SELECT id, name FROM filter_list ORDER BY name");
    }
    
    foreach ($t_filters as $row) {
        print_select_item(htmlspecialchars($row['name']), $row['id'], 0);
    }
    echo "</select>\n";
}
    
function get_filter($db, $filter_value)
{   
    if (!isset($filter_value)) {
        return '';
    }
    
    $filter = get_record_sql($db, "SELECT name FROM filter_list WHERE id = ?", [(int)$filter_value]);
    if (!empty($filter) && isset($filter['name'])) { 
        return $filter['name']; 
    }
    return '';
}
    
function get_login($db, $user_id)
{   
    if (!isset($user_id)) {
        return '';
    }
    
    $login = get_record_sql($db, "SELECT login FROM user_list WHERE id = ?", [(int)$user_id]);
    if (!empty($login) && isset($login['login'])) { 
        return $login['login']; 
    }
    return '';
}
    
function get_auth_count($db, $user_id)
{   
    if (!isset($user_id)) {
        return 0;
    }
    
    $count = get_record_sql($db, "SELECT COUNT(id) as cnt FROM user_auth WHERE user_id = ? AND deleted = 0", [(int)$user_id]);
    if (!empty($count) && isset($count['cnt'])) { 
        return (int)$count['cnt']; 
    }
    return 0;
}

function print_login_select($db, $login_name, $current_login)
{
    echo "<select id=\"" . htmlspecialchars($login_name) . "\" name=\"" . htmlspecialchars($login_name) . "\" class=\"js-select-single\">\n";
    $t_login = get_records_sql($db, "SELECT id, login FROM user_list WHERE deleted=0 ORDER BY login");
    print_select_item('None', 0, $current_login);
    
    foreach ($t_login as $row) {
        print_select_item(htmlspecialchars($row['login']), $row['id'], $current_login);
    }
    echo "</select>\n";
}

function print_auth_select($db, $login_name, $current_auth)
{
    echo "<select id=\"" . htmlspecialchars($login_name) . "\" name=\"" . htmlspecialchars($login_name) . "\" class=\"js-select-single\">\n";
    
    $params = [];
    $sql = "SELECT U.login, U.description, A.ip, A.id 
            FROM user_list AS U, user_auth AS A 
            WHERE A.user_id = U.id 
              AND A.deleted = 0 
              AND (A.id NOT IN (SELECT device_ports.auth_id FROM device_ports) OR A.id = ?) 
            ORDER BY U.login, U.description, A.ip";
    
    $params[] = (int)$current_auth;
    $t_login = get_records_sql($db, $sql, $params);
    
    print_select_item('Empty', 0, $current_auth);
    
    foreach ($t_login as $row) {
        $display = htmlspecialchars($row['login']) . "[" . htmlspecialchars($row['description']) . "] - " . htmlspecialchars($row['ip']);
        print_select_item($display, $row['id'], $current_auth);
    }
    echo "</select>\n";
}

function print_auth_select_mac($db, $login_name, $current_auth)
{
    echo "<select id=\"" . htmlspecialchars($login_name) . "\" name=\"" . htmlspecialchars($login_name) . "\" class=\"js-select-single\">\n";
    
    $params = [];
    $sql = "SELECT U.login, U.description, A.ip, A.mac, A.id 
            FROM user_list AS U, user_auth AS A 
            WHERE A.user_id = U.id 
              AND A.deleted = 0 
              AND (A.id NOT IN (SELECT device_ports.auth_id FROM device_ports) OR A.id = ?) 
            ORDER BY U.login, U.description, A.ip";
    
    $params[] = (int)$current_auth;
    $t_login = get_records_sql($db, $sql, $params);
    
    print_select_item('Empty', 0, $current_auth);
    
    foreach ($t_login as $row) {
        $display = htmlspecialchars($row['login']) . "[" . htmlspecialchars($row['mac']) . "] - " . htmlspecialchars($row['ip']);
        print_select_item($display, $row['id'], $current_auth);
    }
    echo "</select>\n";
}

function compact_port_name($port)
{
    $result = $port;
    $result = preg_replace('/XGigabitEthernet/', 'X', $result);
    $result = preg_replace('/TenGigabitEthernet/', 'Te', $result);
    $result = preg_replace('/GigabitEthernet/', 'Gi', $result);
    return $result;
}

function print_device_port_select($db, $field_name, $device_id, $target_id)
{   
    echo "<select id=\"" . htmlspecialchars($field_name) . "\" name=\"" . htmlspecialchars($field_name) . "\" class=\"js-select-single\">\n";
    
    $device_id = (int)$device_id;
    $target_id = (int)$target_id;
    
    $t_device = get_records_sql($db,
        "SELECT D.device_name, DP.port, DP.device_id, DP.id, DP.ifname 
         FROM devices AS D, device_ports AS DP 
         WHERE D.deleted = 0 
           AND D.id = DP.device_id 
           AND (DP.device_id != ? OR DP.id = ?) 
           AND (DP.id NOT IN (SELECT target_port_id FROM device_ports WHERE target_port_id IS NOT NULL))
         ORDER BY D.device_name, DP.port",
        [$device_id, $target_id]
    );
    
    print_select_item('Empty', 0, $target_id);
    
    foreach ($t_device as $row) {
        $ifName = !empty($row['ifname']) ? $row['ifname'] : $row['port'];
        $display = htmlspecialchars($row['device_name']) . "[" . htmlspecialchars($row['port']) . "] - " . htmlspecialchars(compact_port_name($ifName));
        print_select_item($display, $row['id'], $target_id);
    }
    echo "</select>\n";
}
    
function print_device_select($db, $field_name, $device_id)
{   
    echo "<select id=\"" . htmlspecialchars($field_name) . "\" name=\"" . htmlspecialchars($field_name) . "\" class=\"js-select-single\">\n";
    
    $t_device = get_records_sql($db,
        "SELECT D.device_name, D.id 
         FROM devices AS D 
         WHERE D.deleted = 0 
         ORDER BY D.device_name ASC"
    );
    
    print_select_item(WEB_select_item_every, 0, $device_id);
    
    foreach ($t_device as $row) {
        print_select_item(htmlspecialchars($row['device_name']), $row['id'], $device_id);
    }
    echo "</select>\n";
}

function print_netdevice_select($db, $field_name, $device_id)
{
    echo "<select id=\"" . htmlspecialchars($field_name) . "\" name=\"" . htmlspecialchars($field_name) . "\" class=\"js-select-single\">\n";
    
    $t_device = get_records_sql($db,
        "SELECT D.device_name, D.id 
         FROM devices AS D 
         WHERE D.deleted = 0 AND D.device_type <= 2 
         ORDER BY D.device_name ASC"
    );
    
    print_select_item(WEB_select_item_every, 0, $device_id);
    
    foreach ($t_device as $row) {
        print_select_item(htmlspecialchars($row['device_name']), $row['id'], $device_id);
    }
    echo "</select>\n";
}

function print_vlan_select($db, $field_name, $vlan)
{
    echo "<select id=\"" . htmlspecialchars($field_name) . "\" name=\"" . htmlspecialchars($field_name) . "\" class=\"js-select-single\" style=\"width: 100px;\">\n";
    
    $v_device = get_records_sql($db, "SELECT DISTINCT vlan FROM device_ports ORDER BY vlan DESC");
    
    if (!isset($vlan) || empty($vlan)) {
        $vlan = 1;
    }
    
    print_select_item('1', 1, $vlan);
    
    foreach ($v_device as $row) {
        if ($row['vlan'] === '1') {
            continue;
        }
        // VLAN-ы обычно числовые, но на всякий случай экранируем
        $vlan_val = htmlspecialchars($row['vlan']);
        print_select_item($vlan_val, $vlan_val, $vlan);
    }
    echo "</select>\n";
}

function print_device_select_ip($db, $field_name, $device_ip)
{
    echo "<select id=\"" . htmlspecialchars($field_name) . "\" name=\"" . htmlspecialchars($field_name) . "\" class=\"js-select-single\">\n";
    
    $t_device = get_records_sql($db,
        "SELECT D.device_name, D.ip 
         FROM devices AS D 
         WHERE D.deleted = 0 
         ORDER BY D.device_name ASC"
    );
    
    print_select_item(WEB_select_item_every, '', $device_ip);
    
    foreach ($t_device as $row) {
        $display = htmlspecialchars($row['device_name']);
        $value = htmlspecialchars($row['ip']); // IP безопасен, но для единообразия
        print_select_item($display, $value, $device_ip);
    }
    echo "</select>\n";
}

function print_syslog_device_select($db, $field_name, $syslog_filter, $device_ip)
{
    // Валидация фильтра для предотвращения SQL-инъекций
    $allowed_filters = [
        'facility = 1',
        'facility = 2',
        'priority <= 3',
        'message LIKE "%error%"',
        'message LIKE "%warning%"',
        '1=1' // для случая "все записи"
        // Добавьте другие допустимые значения по необходимости
    ];
    
    if (!in_array($syslog_filter, $allowed_filters)) {
        $syslog_filter = '1=1';
    }
    
    echo "<select id=\"" . htmlspecialchars($field_name) . "\" name=\"" . htmlspecialchars($field_name) . "\" class=\"js-select-single\">\n";
    
    $t_device = get_records_sql($db,
        "SELECT R.ip, D.device_name 
         FROM (SELECT DISTINCT ip FROM remote_syslog WHERE $syslog_filter) AS R 
         LEFT JOIN (SELECT ip, device_name FROM devices WHERE deleted = 0) AS D ON R.ip = D.ip 
         ORDER BY R.ip ASC"
    );
    
    print_select_item(WEB_select_item_every, '', $device_ip);
    
    foreach ($t_device as $row) {
        $display_name = !empty($row['device_name']) ? $row['device_name'] : $row['ip'];
        $display = htmlspecialchars($display_name);
        $value = htmlspecialchars($row['ip']);
        print_select_item($display, $value, $device_ip);
    }
    echo "</select>\n";
}

function print_gateway_select($db, $field_name, $device_id)
{
    echo "<select id=\"" . htmlspecialchars($field_name) . "\" name=\"" . htmlspecialchars($field_name) . "\">\n";
    
    $t_device = get_records_sql($db,
        "SELECT D.device_name, D.id 
         FROM devices AS D 
         WHERE D.deleted = 0 AND D.device_type = 2 
         ORDER BY D.device_name ASC"
    );
    
    print_select_item(WEB_select_item_every, 0, $device_id);
    
    foreach ($t_device as $row) {
        print_select_item(htmlspecialchars($row['device_name']), $row['id'], $device_id);
    }
    echo "</select>\n";
}

function get_gateways($db)
{
    $t_device = get_records_sql($db,
        "SELECT D.device_name, D.id 
         FROM devices AS D 
         WHERE D.deleted = 0 AND D.device_type = 2 
         ORDER BY D.device_name ASC"
    );
    
    $result = [];
    foreach ($t_device as $row) {
        $result[$row['id']] = $row['device_name'];
    }
    return $result;
}

function print_device_port($db, $target_id)
{
    $t_device = get_records_sql($db,
        "SELECT D.device_name, DP.port, DP.device_id 
         FROM devices AS D, device_ports AS DP 
         WHERE D.id = DP.device_id AND DP.id = ? AND D.deleted = 0",
        [(int)$target_id]
    );
    
    foreach ($t_device as $row) {
        $device_name = htmlspecialchars($row['device_name']);
        $port = htmlspecialchars($row['port']);
        $device_id = (int)$row['device_id'];
        echo "<a href=\"/admin/devices/switchport.php?id={$device_id}\">{$device_name}[{$port}]</a>\n";
    }
}

function get_device_ips($db, $device_id)
{
    $switch = get_record($db, 'devices', 'id = ?', [(int)$device_id]);
    $result = [];
    
    if (!empty($switch['user_id'])) {
        $auth_ips = get_records($db, 'user_auth', 'deleted = 0 AND user_id = ?', [(int)$switch['user_id']]);
        foreach ($auth_ips as $value) {
            if (isset($value['ip'])) {
                $result[] = $value['ip'];
            }
        }
    } else {
        if (isset($switch['ip'])) {
            $result[] = $switch['ip'];
        }
    }
    
    return $result;
}

function get_device_id($db, $device_name)
{
    if (empty($device_name)) {
        return null;
    }
    
    $dev = get_record_sql($db, 
        "SELECT id FROM devices WHERE device_name = ? AND deleted = 0", 
        [$device_name]
    );
    
    if (empty($dev)) {
        return null;
    }
    return $dev["id"];
}

function get_device_name($db, $device_id)
{
    if (!isset($device_id)) {
        return null;
    }
    
    $dev = get_record_sql($db, 
        "SELECT device_name FROM devices WHERE id = ?", 
        [(int)$device_id]
    );
    
    if (empty($dev)) {
        return null;
    }
    return $dev["device_name"];
}

function get_auth_by_ip($db, $ip)
{
    if (empty($ip)) {
        return null;
    }
    
    $auth = get_record_sql($db, 
        "SELECT id FROM user_auth WHERE ip = ? AND deleted = 0", 
        [$ip]
    );
    
    if (empty($auth)) {
        return null;
    }
    return $auth["id"];
}

function get_user_by_ip($db, $ip)
{
    if (empty($ip)) {
        return null;
    }
    
    $auth = get_record_sql($db, 
        "SELECT user_id FROM user_auth WHERE ip = ? AND deleted = 0", 
        [$ip]
    );
    
    if (empty($auth)) {
        return null;
    }
    return $auth["user_id"];
}

function get_device_by_auth($db, $id)
{
    if (!isset($id)) {
        return null;
    }
    
    $f_dev = get_record_sql($db, 
        "SELECT id FROM devices WHERE user_id = ? AND deleted = 0", 
        [(int)$id]
    );
    
    if (empty($f_dev)) {
        return null;
    }
    return $f_dev['id'];
}

function print_auth_port($db, $port_id, $new_window = false)
{
    $t_auth = get_records_sql($db,
        "SELECT A.ip, A.ip_int, A.mac, A.id, A.dns_name, A.user_id 
         FROM user_auth AS A, connections AS C 
         WHERE C.port_id = ? AND A.id = C.auth_id AND A.deleted = 0 
         ORDER BY A.ip_int",
        [(int)$port_id]
    );
    
    foreach ($t_auth as $row) {
        // Определяем отображаемое имя
        $name = !empty($row['dns_name']) ? $row['dns_name'] : $row['ip'];
        
        // Формируем title
        $login = get_login($db, $row['user_id']);
        $title = htmlspecialchars($login) . " =>" . htmlspecialchars($row['ip']) . "[" . htmlspecialchars($row['mac']) . "]";
        if (!empty($row['dns_name'])) {
            $title .= " | " . htmlspecialchars($row['dns_name']);
        }
        
        // Экранируем данные для вывода
        $display_name = htmlspecialchars($name);
        $display_ip = htmlspecialchars($row['ip']);
        $auth_id = (int)$row['id'];
        
        if ($new_window) {
            $url = "/admin/users/editauth.php?id=" . $auth_id;
            echo "<a href=\"\" title=\"" . $title . "\" onclick=\"" . open_window_url($url) . " return false;\">" . $display_name . " [" . $display_ip . "]</a><br>\n";
        } else {
            echo "<a href=\"/admin/users/editauth.php?id=" . $auth_id . "\" title=\"" . $title . "\">" . $display_name . " [" . $display_ip . "]</a><br>\n";
        }
    }
}

function get_port_description($db, $port_id, $port_description = '')
{
    $t_auth = get_records_sql($db,
        "SELECT A.ip_int, A.description 
         FROM user_auth AS A, connections AS C 
         WHERE C.port_id = ? AND A.id = C.auth_id AND A.deleted = 0 
         ORDER BY A.ip_int",
        [(int)$port_id]
    );
    
    $description_found = false;
    $result = '';
    
    foreach ($t_auth as $row) {
        $desc = !empty($row['description']) ? $row['description'] : '';
        if (!empty($desc)) {
            $description_found = true;
        }
        $result .= $desc . '<br>';
    }
    
    if (!$description_found) {
        return $port_description;
    }
    
    if (!empty($port_description)) {
        $result .= '(' . $port_description . ')';
    }
    
    return $result;
}

function print_auth_simple($db, $auth_id)
{
    $auth = get_record($db, "user_auth", "id = ?", [(int)$auth_id]);
    
    if (empty($auth)) {
        return;
    }
    
    // Определяем отображаемое имя
    $name = !empty($auth['dns_name']) ? $auth['dns_name'] : 
            (!empty($auth['description']) ? $auth['description'] : $auth['ip']);
    
    $display_name = $name;
    $safe_auth_id = (int)$auth_id;
    
    echo "<a href=\"/admin/users/editauth.php?id={$safe_auth_id}\">{$display_name}</a><br>\n";
}


function print_auth($db, $auth_id)
{
    $auth = get_record($db, "user_auth", "id = ?", [(int)$auth_id]);
    
    if (empty($auth)) {
        return;
    }
    
    // Формируем отображаемое имя
    if (!empty($auth['dns_name'])) {
        $name = $auth['dns_name'];
        if (!empty($auth['description'])) {
            $name .= " (" . $auth['description'] . ")";
        }
    } else {
        $name = !empty($auth['description']) ? $auth['description'] : $auth['ip'];
    }
    
    if (!empty($name) && !empty($auth['ip'])) {
        $name .= " [" . $auth['ip'] . "]";
    }
    
    $display_name = htmlspecialchars($name);
    $safe_auth_id = (int)$auth_id;
    
    echo "<a href=\"/admin/users/editauth.php?id={$safe_auth_id}\">{$display_name}</a><br>\n";
}

function print_auth_detail($db, $auth_id)
{
    $auth = get_record($db, "user_auth", "id = ?", [(int)$auth_id]);
    
    if (empty($auth)) {
        return;
    }
    
    // Формируем отображаемое имя
    if (!empty($auth['dns_name'])) {
        $name = $auth['dns_name'];
        if (!empty($auth['description'])) {
            $name .= " (" . $auth['description'] . ")";
        }
    } else {
        $name = !empty($auth['description']) ? $auth['description'] : $auth['ip'];
    }
    
    if (!empty($name) && !empty($auth['ip'])) {
        $name .= " [" . $auth['ip'] . "]";
    }
    
    // Добавляем информацию о последнем обнаружении
    if (!empty($auth['last_found'])) {
        $name .= " last: [" . $auth['last_found'] . "] ";
    }
    
    // Добавляем статус удаления
    if ($auth['deleted'] == 1) {
        $name .= " <font color='red'>DELETED!!!</font>";
    }
    
    $display_name = htmlspecialchars($name);
    $safe_auth_id = (int)$auth_id;
    
    echo "<a href=\"/admin/users/editauth.php?id={$safe_auth_id}\">{$display_name}</a><br>\n";
}

function get_auth_port_count($db, $port_id)
{
    $t_device = get_record_sql($db,
        "SELECT COUNT(A.id) as cnt 
         FROM user_auth AS A, connections AS C 
         WHERE C.port_id = ? AND A.id = C.auth_id AND A.deleted = 0",
        [(int)$port_id]
    );
    
    if (empty($t_device)) { 
        return 0; 
    }
    return (int)$t_device['cnt'];
}

function get_connection($db, $auth_id)
{
    $t_device = get_record_sql($db,
        "SELECT D.device_name, DP.port 
         FROM devices AS D, device_ports AS DP, connections AS C 
         WHERE D.deleted = 0 AND D.id = DP.device_id AND DP.id = C.port_id AND C.auth_id = ?",
        [(int)$auth_id]
    );
    
    if (!empty($t_device) && isset($t_device['device_name'])) {
        $device_name = expand_device_name($db, $t_device['device_name']);
        $port = $t_device['port'];
        return $device_name . "[" . $port . "]";
    }
    return '';
}

function get_connection_string($db, $auth_id)
{
    $t_device = get_record_sql($db,
        "SELECT D.device_name, DP.port 
         FROM devices AS D, device_ports AS DP, connections AS C 
         WHERE D.deleted = 0 AND D.id = DP.device_id AND DP.id = C.port_id AND C.auth_id = ?",
        [(int)$auth_id]
    );
    
    if (!empty($t_device) && isset($t_device['device_name'])) {
        return $t_device['device_name'] . "[" . $t_device['port'] . "]";
    }
    return '';
}

function get_port($db, $port_id)
{
    $t_device = get_record_sql($db,
        "SELECT D.device_name, DP.port 
         FROM devices AS D, device_ports AS DP 
         WHERE D.deleted = 0 AND D.id = DP.device_id AND DP.id = ?",
        [(int)$port_id]
    );
    
    if (!empty($t_device) && isset($t_device['device_name'])) {
        $device_name = expand_device_name($db, $t_device['device_name']);
        return $device_name . "[" . $t_device['port'] . "]";
    }
    return '';
}

function print_option_select($db, $option_name)
{
    echo "<select id=\"" . htmlspecialchars($option_name) . "\" name=\"" . htmlspecialchars($option_name) . "\">\n";
    
    // Неуникальные опции
    $t_option = get_records_sql($db, 
        "SELECT id, option_name FROM config_options 
         WHERE uniq = 0 AND draft = 0 AND id != 68 
         ORDER BY option_name"
    );
    
    if (!empty($t_option)) {
        foreach ($t_option as $row) {
            echo "<option value=\"" . (int)$row['id'] . "\">" . htmlspecialchars($row['option_name']) . "</option>\n";
        }
    }
    
    // Уникальные опции (которые ещё не используются)
    $t_option = get_records_sql($db,
        "SELECT id, option_name FROM config_options 
         WHERE draft = 0 AND uniq = 1 AND id != 68 AND id NOT IN (SELECT option_id FROM config WHERE draft = 0) 
         ORDER BY option_name"
    );
    
    if (!empty($t_option)) {
        foreach ($t_option as $row) {
            echo "<option value=\"" . (int)$row['id'] . "\">" . htmlspecialchars($row['option_name']) . "</option>\n";
        }
    }
    
    echo "</select>\n";
}

function ResolveIP($db, $ip_int)
{
    $ip_name = "-";
    
    if (empty($ip_int)) {
        return $ip_name;
    }
    
    // Проверяем кэш
    $dns_cache = get_record_sql($db, "SELECT dns FROM dns_cache WHERE ip = ?", [(int)$ip_int]);
    
    if (empty($dns_cache) || empty($dns_cache['dns'])) {
        $ip_str = long2ip((int)$ip_int);
        $ip_name = gethostbyaddr($ip_str);
        
        // Проверяем результат разрешения
        if (empty($ip_name) || $ip_name == $ip_str) {
            $ip_name = "-";
        }
        
        // Сохраняем в кэш
        run_sql($db, "INSERT INTO dns_cache(dns, ip) VALUES(?, ?)", [$ip_name, (int)$ip_int]);
    } else {
        $ip_name = $dns_cache['dns'];
    }
    
    return $ip_name;
}

function clean_dns_cache($db)
{
    $date = time() - 86400; // 24 часа назад
    $clean_date = date('Y-m-d H:i:s', $date);
    run_sql($db, "DELETE FROM dns_cache WHERE ts <= ?", [$clean_date]);
}

function clean_unreferensed_rules($db)
{
    run_sql($db, "DELETE FROM auth_rules WHERE user_id NOT IN (SELECT id FROM user_list)");
}

function FormatDateStr($format = 'Y-m-d H:i:s', $date_str)
{
    $date1 = GetDateTimeFromString($date_str);
    $result = $date1->format($format);
    return $result;
}

function GetDateTimeFromString($date_str)
{
    if (!is_a($date_str, 'DateTime')) {
        $t_date_str = urldecode($date_str);
        $t_date_str = preg_replace('/(\'|\")/', '', $t_date_str);
        $t_date_str = preg_replace('/T/', ' ', $t_date_str);
        $date1 = DateTime::createFromFormat('Y-m-d H:i:s', $t_date_str);
        if (!$date1) {
            $date1 = DateTime::createFromFormat('Y.m.d H:i:s', $t_date_str);
        }
        if (!$date1) {
            $date1 = DateTime::createFromFormat('Y/m/d H:i:s', $t_date_str);
        }
        if (!$date1) {
            $date1 = DateTime::createFromFormat('Y-m-d H:i', $t_date_str);
        }
        if (!$date1) {
            $date1 = DateTime::createFromFormat('Y.m.d H:i', $t_date_str);
        }
        if (!$date1) {
            $date1 = DateTime::createFromFormat('Y/m/d H:i', $t_date_str);
        }
        if (!$date1) {
            $date1 = DateTime::createFromFormat('Y-m-d|', $t_date_str);
        }
        if (!$date1) {
            $date1 = DateTime::createFromFormat('Y.m.d|', $t_date_str);
        }
        if (!$date1) {
            $date1 = DateTime::createFromFormat('Y/m/d|', $t_date_str);
        }
        if (!$date1) {
            $date1 = new DateTime;
            $date1->setTime(0, 0, 0, 1);
        }
    } else {
        return $date_str;
    }
    return $date1;
}

function GetNowTimeString()
{
    $now = new DateTimeImmutable('now');
    $result = $now->format('Y-m-d H:i:s');
    return $result;
}

function GetNowDayString()
{
    $now = new DateTimeImmutable('now');
    $result = $now->format('Y-m-d');
    return $result;
}

function get_ip_subnet($db, $ip)
{
    if (empty($ip)) {
        return null;
    }
    
    $ip_aton = ip2long($ip);
    if ($ip_aton === false) {
        return null;
    }
    
    // Исправлено: добавлены скобки для правильного порядка операций
    $user_subnet = get_record_sql($db,
        "SELECT * FROM subnets 
         WHERE (hotspot = 1 OR office = 1) 
           AND ? >= ip_int_start 
           AND ? <= ip_int_stop",
        [$ip_aton, $ip_aton]
    );
    
    if (empty($user_subnet)) {
        return null;
    }
    return $user_subnet;
}

function find_mac_in_subnet($db, $ip, $mac)
{
    if (empty($ip) || empty($mac)) {
        return null;
    }
    
    $ip_subnet = get_ip_subnet($db, $ip);
    if (empty($ip_subnet)) {
        return null;
    }
    
    $t_auth = get_records_sql($db,
        "SELECT id, mac, user_id 
         FROM user_auth 
         WHERE ip_int >= ? 
           AND ip_int <= ? 
           AND mac = ? 
           AND deleted = 0 
         ORDER BY id",
        [$ip_subnet['ip_int_start'], $ip_subnet['ip_int_stop'], $mac]
    );
    
    if (empty($t_auth)) {
        return ['count' => 0, 'users_id' => []];
    }
    
    $result = ['count' => 0, 'users_id' => []];
    foreach ($t_auth as $row) {
        if (!empty($row['id'])) {
            $result['count']++;
            $result[$result['count']] = $row['id'];
            $result['users_id'][] = $row['user_id'];
        }
    }
    
    return $result;
}

function apply_auth_rule($db, $auth_record, $user_id)
{
    if (empty($auth_record)) {
        return null;
    }
    
    if (empty($user_id)) {
        return $auth_record;
    }
    
    $user_rec = get_record($db, 'user_list', "id = ?", [(int)$user_id]);
    if (empty($user_rec)) {
        return $auth_record;
    }
    
    // Set filter and status by user
    $auth_record['ou_id'] = $user_rec['ou_id'];
    $auth_record['user_id'] = $user_rec['id'];
    $auth_record['filter_group_id'] = $user_rec['filter_group_id'];
    $auth_record['queue_id'] = $user_rec['queue_id'];
    $auth_record['enabled'] = $user_rec['enabled'];
    $auth_record['changed'] = 1;
    
    // Maybe fill description?
    if (!empty($user_rec['description']) && empty($auth_record['description'])) {
        $auth_record['description'] = $user_rec['description'];
    }
    
    return $auth_record;
}

function fix_auth_rules($db)
{
    // Cleanup hotspot subnet rules
    $t_hotspot = get_records_sql($db, "SELECT id FROM ou WHERE default_users = 1 OR default_hotspot = 1");
    if (!empty($t_hotspot)) {
        foreach ($t_hotspot as $row) {
            delete_record($db, "auth_rules", "ou_id = ?", [(int)$row['id']]);
        }
    }
    
    $t_hotspot_subnets = get_records_sql($db, "SELECT subnet FROM subnets WHERE hotspot = 1");
    if (!empty($t_hotspot_subnets)) {
        foreach ($t_hotspot_subnets as $row) {
            delete_record($db, "auth_rules", "rule = ?", [$row['subnet']]);
        }
    }
}

#---------------------------------------------------------------------------------------------------------------

function new_user($db, $user_info)
{
    if (empty($user_info)) {
        return null;
    }
    
    // Формируем логин и ФИО
    if (!empty($user_info['mac'])) {
        $user['login'] = mac_dotted($user_info['mac']);
    } else {
        $user['login'] = $user_info['ip'] ?? '';
    }
    
    if (!empty($user_info['dhcp_hostname'])) {
        $user['description'] = ($user_info['ip'] ?? '') . '[' . $user_info['dhcp_hostname'] . ']';
    } else {
        $user['description'] = $user_info['ip'] ?? '';
    }
    
    // Проверяем существование логина и формируем уникальный
    $base_login = $user['login'];
    $login_count = get_count_records($db, "user_list", 
        "(login LIKE ?) OR (login = ?)", 
        [$base_login . '(%)', $base_login]
    );
    
    if (!empty($login_count) && $login_count > 0) {
        $user['login'] = $base_login . "(" . ($login_count + 1) . ")";
    }
    
    // Назначаем OU и наследуем настройки
    $user['ou_id'] = (int)($user_info['ou_id'] ?? 0);
    
    if ($user['ou_id'] > 0) {
        $ou_info = get_record_sql($db, "SELECT * FROM ou WHERE id = ?", [$user['ou_id']]);
        if (!empty($ou_info)) {
            $user['enabled'] = isset($ou_info['enabled']) ? (int)$ou_info['enabled'] : 0;
            $user['queue_id'] = isset($ou_info['queue_id']) ? (int)$ou_info['queue_id'] : 0;
            $user['filter_group_id'] = isset($ou_info['filter_group_id']) ? (int)$ou_info['filter_group_id'] : 0;
        } else {
            // Значения по умолчанию, если OU не найден
            $user['enabled'] = 0;
            $user['queue_id'] = 0;
            $user['filter_group_id'] = 0;
        }
    } else {
        // Значения по умолчанию при отсутствии OU
        $user['enabled'] = 0;
        $user['queue_id'] = 0;
        $user['filter_group_id'] = 0;
    }
    
    // Создаём пользователя
    $result = insert_record($db, "user_list", $user);
    
    // Создаём автоматическое правило по MAC (если включено)
    if (!empty($result)) {
        $auto_mac_rule = (int)get_option($db, 64);
        if ($auto_mac_rule && !empty($user_info['mac'])) {
            $auth_rule = [
                'user_id' => $result,
                'rule_type' => 2,
                'rule' => mac_dotted($user_info['mac'])
            ];
            insert_record($db, "auth_rules", $auth_rule);
        }
    }
    
    return $result;
}

function new_auth($db, $ip, $mac, $user_id)
{   
    if (empty($ip)) {
        return null;
    }
    
    $ip_aton = ip2long($ip);
    if ($ip_aton === false) {
        return null;
    }
    
    $msg = '';
    $user_id = (int)$user_id;
    
    // Проверяем существование пары IP-MAC
    if (!empty($mac)) {
        $dotted_mac = mac_dotted($mac);
        $auth_record = get_record_sql($db, 
            "SELECT * FROM user_auth WHERE ip_int = ? AND mac = ? AND deleted = 0", 
            [$ip_aton, $dotted_mac]
        );
        
        if (!empty($auth_record)) {
            LOG_WARNING($db, "Pair ip-mac already exists! Skip creating $ip [$mac] auth_id: " . $auth_record["id"]);
            return $auth_record['id'];
        }
    }
    
    // Настройки сохранения трафика
    $save_traf = (int)get_option($db, 23);
    $resurrection_id = null;
    
    // Ищем удалённую запись с теми же IP и MAC
    if (!empty($mac)) {
        $old_auth_id = get_id_record($db, 'user_auth', 
            "deleted = 1 AND ip_int = ? AND mac = ?", 
            [$ip_aton, $mac]
        );
    } else {
        $old_auth_id = get_id_record($db, 'user_auth', 
            "deleted = 1 AND ip_int = ? AND mac IS NULL", 
            [$ip_aton]
        );
    }
    
    if (!empty($old_auth_id)) {
        $resurrection_id = $old_auth_id;
        $msg .= "Recovered auth_id: $resurrection_id with ip: $ip and mac: $mac ";
        $auth = [
            'user_id' => $user_id,
            'deleted' => 0,
            'save_traf' => $save_traf
        ];
        update_record($db, "user_auth", "id = ?", $auth, [$resurrection_id]);
    } else {
        // Создаём новую запись
        $msg .= "Create new ip record \r\nip: $ip\r\nmac: $mac\r\n";
        $auth = [
            'deleted' => 0,
            'user_id' => $user_id,
            'ip' => $ip,
            'ip_int' => $ip_aton,
            'save_traf' => $save_traf
        ];
        
        if (!empty($mac)) {
            $auth['mac'] = $mac;
        }
        
        $resurrection_id = insert_record($db, "user_auth", $auth);
    }
    
    // Применяем правила и обновляем запись
    if (!empty($resurrection_id)) {
        $auth_final = apply_auth_rule($db, $auth, $user_id);
        update_record($db, "user_auth", "id = ?", $auth_final, [$resurrection_id]);
        
        if (!is_hotspot($db, $ip) && !empty($msg)) {
            LOG_WARNING($db, $msg);
        }
        if (is_hotspot($db, $ip) && !empty($msg)) {
            LOG_INFO($db, $msg);
        }
    }
    
    return $resurrection_id;
}

function resurrection_auth($db, $ip_record)
{
    if (empty($ip_record) || empty($ip_record['ip'])) {
        return null;
    }
    
    $ip = $ip_record['ip'];
    $mac = $ip_record['mac'] ?? '';
    $action = $ip_record['type'] ?? '';
    $dhcp_hostname = $ip_record['hostname'] ?? '';
    $hotspot_found = !empty($ip_record['hotspot']);
    
    $ip_aton = ip2long($ip);
    if ($ip_aton === false) {
        return null;
    }
    
    // Проверяем существующую активную запись с теми же IP и MAC
    $auth_record = get_record_sql($db,
        "SELECT * FROM user_auth WHERE ip_int = ? AND mac = ? AND deleted = 0",
        [$ip_aton, $mac]
    );
    
    if (!empty($auth_record)) {
        $user_info = get_record_sql($db, 
            "SELECT * FROM user_list WHERE id = ?", 
            [(int)$auth_record['user_id']]
        );
        
        LOG_DEBUG($db, "external dhcp user " . ($user_info['login'] ?? '') . " [" . $ip . "] auth_id: " . $auth_record['id']);
        
        $auth_update = [];
        if (isset($dhcp_hostname) && !empty($dhcp_hostname)) {
            $auth_update['dhcp_hostname'] = $dhcp_hostname;
        }
        $auth_update['dhcp_action'] = $action;
        $auth_update['dhcp_time'] = GetNowTimeString();
        
        if ($action === 'add') {
            $auth_update['last_found'] = GetNowTimeString();
        }
        
        update_record($db, "user_auth", "id = ?", $auth_update, [$auth_record['id']]);
        return $auth_record['id'];
    }
    
    // Проверяем статическую подсеть
    $ip_subnet = get_ip_subnet($db, $ip);
    if (!empty($ip_subnet) && !empty($ip_subnet['static'])) {
        LOG_WARNING($db, "Unknown pair ip+mac in static subnet! ip: $ip mac: [" . mac_dotted($mac) . "]. Skip");
        return null;
    }
    
    $msg = '';
    
    // Ищем запись с тем же IP (возможно, другой MAC)
    $auth_record = get_record_sql($db,
        "SELECT * FROM user_auth WHERE ip_int = ? AND deleted = 0",
        [$ip_aton]
    );
    
    if (!empty($auth_record)) {
        if (empty($auth_record['mac'])) {
            // Обновляем пустой MAC
            $auth_update = [
                'mac' => mac_dotted($mac),
                'dhcp_action' => $action,
                'dhcp_time' => GetNowTimeString()
            ];
            
            if (!empty($dhcp_hostname)) {
                $auth_update['dhcp_hostname'] = $dhcp_hostname;
            }
            if ($action === 'add') {
                $auth_update['last_found'] = GetNowTimeString();
            }
            
            LOG_INFO($db, "for ip: $ip mac not found! Use empty record...");
            update_record($db, "user_auth", "id = ?", $auth_update, [$auth_record['id']]);
            return $auth_record['id'];
        } else {
            // MAC изменился - помечаем старую запись как удалённую
            if (!$hotspot_found) {
                LOG_WARNING($db, "for ip: $ip mac change detected! Old mac: [" . $auth_record['mac'] . "] New mac: [" . mac_dotted($mac) . "]. Disable old auth_id: " . $auth_record['id']);
            }
            update_record($db, "user_auth", "id = ?", ['changed' => 1, 'deleted' => 1], [$auth_record['id']]);
        }
    }
    
    // Создаём/находим пользователя
    $new_user_info = get_new_user_id($db, $ip, $mac, $dhcp_hostname);
    $new_user_id = null;
    
    if (!empty($new_user_info['user_id'])) {
        $new_user_id = $new_user_info['user_id'];
    }
    if (empty($new_user_id)) {
        $new_user_id = new_user($db, $new_user_info);
    }
    
    if (empty($new_user_id)) {
        return null;
    }
    
    $new_user_id = (int)$new_user_id;
    $save_traf = (int)get_option($db, 23);
    $resurrection_id = null;
    
    // Ищем удалённую запись с теми же IP и MAC для восстановления
    $auth_record = get_record_sql($db,
        "SELECT * FROM user_auth WHERE ip_int = ? AND mac = ?",
        [$ip_aton, $mac]
    );
    
    if (!empty($auth_record)) {
        // Восстанавливаем существующую запись
        $resurrection_id = $auth_record['id'];
        $msg .= "Recovered auth_id: $resurrection_id with ip: $ip and mac: $mac ";
        $auth = [
            'dhcp_action' => $action,
            'user_id' => $new_user_id,
            'deleted' => 0,
            'dhcp_time' => GetNowTimeString(),
            'save_traf' => $save_traf
        ];
        
        if (!empty($dhcp_hostname)) {
            $auth['dhcp_hostname'] = $dhcp_hostname;
        }
        if ($action === 'add') {
            $auth['last_found'] = GetNowTimeString();
        }
        
        update_record($db, "user_auth", "id = ?", $auth, [$resurrection_id]);
    } else {
        // Создаём новую запись
        $msg .= "Создаём новый ip-адрес \r\nip: $ip\r\nmac: $mac\r\n";
        $auth = [
            'deleted' => 0,
            'user_id' => $new_user_id,
            'ip' => $ip,
            'ip_int' => $ip_aton,
            'mac' => $mac,
            'dhcp_action' => $action,
            'dhcp_time' => GetNowTimeString(),
            'save_traf' => $save_traf
        ];
        
        if (!empty($dhcp_hostname)) {
            $auth['dhcp_hostname'] = $dhcp_hostname;
        }
        if ($action === 'add') {
            $auth['last_found'] = GetNowTimeString();
        }
        
        $resurrection_id = insert_record($db, "user_auth", $auth);
    }
    
    // Применяем правила авторизации
    if (!empty($resurrection_id)) {
        $auth_final = apply_auth_rule($db, $auth, $new_user_id);
        update_record($db, "user_auth", "id = ?", $auth_final, [$resurrection_id]);
        
        $msg .= "filter: " . ($auth_final['filter_group_id'] ?? '') . 
                "\r\n queue_id: " . ($auth_final['queue_id'] ?? '') . 
                "\r\n enabled: " . ($auth_final['enabled'] ?? '') . 
                "\r\nid: $resurrection_id";
        
        if (!$hotspot_found && !empty($msg)) {
            LOG_WARNING($db, $msg);
        }
        if ($hotspot_found && !empty($msg)) {
            LOG_INFO($db, $msg);
        }
    }
    
    return $resurrection_id;
}

function get_auth($db, $current_auth)
{
    if (!isset($current_auth) || $current_auth == 0) {
        return null;
    }
    
    $t_login = get_record_sql($db,
        "SELECT U.login, A.ip 
         FROM user_list AS U, user_auth AS A 
         WHERE A.user_id = U.id AND A.id = ?",
        [(int)$current_auth]
    );
    
    if (!empty($t_login) && isset($t_login['login'])) {
        return $t_login['login'] . "[" . $t_login['ip'] . "]";
    }
    return '';
}

function get_auth_by_mac($db, $mac)
{
    if (empty($mac)) {
        return ['auth' => 'Unknown', 'mac' => ''];
    }
    
    $mac_dotted = mac_dotted($mac);
    $t_login = get_record_sql($db,
        "SELECT U.id, U.login, A.id as auth_id, A.ip 
         FROM user_list AS U, user_auth AS A 
         WHERE A.user_id = U.id AND A.mac = ? AND A.deleted = 0 
         ORDER BY A.last_found DESC",
        [$mac_dotted]
    );
    
    if (!empty($t_login) && isset($t_login['id'])) {
        $user_id = (int)$t_login['id'];
        $auth_id = (int)$t_login['auth_id'];
        $login = htmlspecialchars($t_login['login']);
        $ip = htmlspecialchars($t_login['ip']);
        $result['auth'] = '<a href="/admin/users/edituser.php?id=' . $user_id . '">' . $login . '</a> / ip: <a href="/admin/users/editauth.php?id=' . $auth_id . '">' . $ip . '</a>';
    } else {
        $result['auth'] = 'Unknown';
    }
    
    $result['mac'] = expand_mac($db, $mac_dotted);
    return $result;
}

function get_auth_mac($db, $current_auth)
{
    if (!isset($current_auth) || $current_auth == 0) {
        return null;
    }
    
    $t_login = get_record_sql($db,
        "SELECT U.login, A.mac 
         FROM user_list AS U, user_auth AS A 
         WHERE A.user_id = U.id AND A.id = ?",
        [(int)$current_auth]
    );
    
    // Исправлена опечатка: было $t_loing, стало $t_login
    if (!empty($t_login) && isset($t_login['login'])) {
        return $t_login['login'] . " [" . $t_login['mac'] . "]";
    }
    return '';
}

function add_auth_rule($db, $rule, $type, $user_id)
{
    $user_id = (int)$user_id;
    $type = (int)$type;
    
    // Проверяем существование правила
    $auth_rules = get_record_sql($db,
        "SELECT * FROM auth_rules WHERE rule = ? AND rule_type = ?",
        [$rule, $type]
    );
    
    if (empty($auth_rules)) {
        // Создаём новое правило
        $new = [
            'user_id' => $user_id,
            'rule_type' => $type,
            'rule' => $rule
        ];
        $rule_id = insert_record($db, "auth_rules", $new);
        LOG_INFO($db, "Create auto rule for user_id: " . $user_id . " rule: " . $rule . " rule_type: " . $type);
    } else {
        if ($auth_rules['user_id'] !== $user_id) {
            LOG_WARNING($db, "Create auto rule for user_id: " . $user_id . " rule: " . $rule . " rule_type: " . $type . " failed! Already exists at user_id: " . $auth_rules['user_id']);
            $rule_id = 0;
        } else {
            $rule_id = $auth_rules['id'];
        }
    }
    return $rule_id;
}

function update_auth_rule($db, $new, $rule_id = 0)
{
    if (empty($new) || !isset($new['rule_type']) || !isset($new['rule'])) {
        return 0;
    }
    
    $rule_id = (int)$rule_id;
    $type = (int)$new['rule_type'];
    $rule = $new['rule'];
    
    // Проверяем существование другого правила с теми же параметрами
    $auth_rules = get_record_sql($db,
        "SELECT * FROM auth_rules WHERE rule = ? AND rule_type = ? AND id != ?",
        [$rule, $type, $rule_id]
    );
    
    if (empty($auth_rules)) {
        // Обновляем правило
        $updated_id = update_record($db, "auth_rules", "id = ?", $new, [$rule_id]);
        return $updated_id !== false ? $rule_id : 0;
    } else {
        // Правило уже существует у другого пользователя
        LOG_WARNING($db, "Update auto rule id: " . $rule_id . " rule: " . $rule . " rule_type: " . $type . " failed! Already exists at user_id: " . $auth_rules['user_id']);
        return 0;
    }
}

function LOG_INFO($db, $msg, $auth_id = 0)
{
    if (get_const('log_level') < L_INFO) {
        return;
    }
    write_log($db, $msg, L_INFO, $auth_id);
}

function LOG_ERROR($db, $msg, $auth_id = 0)
{
    if (get_const('log_level') < L_ERROR) {
        return;
    }
    email(L_ERROR,$msg);
    write_log($db, $msg, L_ERROR, $auth_id);
}

function LOG_VERBOSE($db, $msg, $auth_id = 0)
{
    if (get_const('log_level') < L_VERBOSE) {
        return;
    }
    write_log($db, $msg, L_VERBOSE, $auth_id);
}

function LOG_WARNING($db, $msg, $auth_id = 0)
{
    if (get_const('log_level') < L_WARNING) {
        return;
    }
    write_log($db, $msg, L_WARNING, $auth_id);
}

function LOG_DEBUG($db, $msg, $auth_id = 0)
{
    if (!empty(get_const('debug')) and get_const('debug')) {
        write_log($db, $msg, L_DEBUG, $auth_id);
    }
}

function truncateByWords($string, $length = 100)
{
    if (strlen($string) <= $length) {
        return $string;
    }
    $wrapped = wordwrap($string, $length);
    $shortened = substr($wrapped, 0, strpos($wrapped, "\n"));
    return $shortened;
}

function get_first_line($msg)
{
    if (empty($msg)) {
        return;
    }
    preg_match('/(.*)(\n|\<br\>)/', $msg, $matches);
    if (!empty($matches[1])) {
        return $matches[1];
    }
    return truncateByWords($msg, 80);
}

function email($level = L_WARNING, $msg = '') {
    if (empty($msg)) { return; }
    // Безопасное получение данных сессии
    $currentIp = filter_var($_SESSION['ip'] ?? '127.0.0.1', FILTER_VALIDATE_IP) ?: '127.0.0.1';
    $currentLogin = htmlspecialchars($_SESSION['login'] ?? 'http', ENT_QUOTES, 'UTF-8');
    // Обработка сообщения
    $subjectPrefix = ($level === L_WARNING) ? "WARN: " : "ERROR: ";
    $subject = $subjectPrefix . htmlspecialchars(get_first_line($msg), ENT_QUOTES, 'UTF-8') . "...";
    $messageType = ($level === L_WARNING) ? 'WARNING' : 'ERROR';
    // Формирование HTML-сообщения с экранированием
    $safeMsg = nl2br(htmlspecialchars($msg, ENT_QUOTES, 'UTF-8'));
    $htmlMessage = "<html>
        <body>
            <h1>$messageType!</h1>
            <p>Manager: $currentLogin</p>
            <p>From: $currentIp</p>
            <div>$safeMsg</div>
        </body>
    </html>";
    // Заголовки письма
    $senderEmail = filter_var(get_const('sender_email'), FILTER_VALIDATE_EMAIL);
    if (!$senderEmail) {
        error_log("Invalid sender email address");
        return;
    }
    $boundary = md5(uniqid(time(), true));
    $headers = [
        'From' => $senderEmail,
        'Reply-To' => $senderEmail,
        'X-Mailer' => 'PHP',
        'MIME-Version' => '1.0',
        'Content-Type' => 'multipart/mixed; boundary=' . $boundary,
        'Content-Transfer-Encoding' => 'base64'
    ];
    // Формирование тела письма
    $message = "--$boundary\r\n" .
               "Content-Type: text/html; charset=UTF-8\r\n" .
               "Content-Transfer-Encoding: base64\r\n\r\n" .
               chunk_split(base64_encode($htmlMessage)) . "\r\n" .
               "--$boundary--";
    // Отправка письма
    $adminEmail = filter_var(get_const('admin_email'), FILTER_VALIDATE_EMAIL);
    $additional_parameters = "-f ".$senderEmail;
    if ($adminEmail) {
        if (!mail($adminEmail, $subject, $message, $headers, $additional_parameters)) {
            error_log("Failed to send email to $adminEmail");
        }
    } else {
        error_log("Invalid admin email address");
    }
}

function write_log($db, $msg, $level = L_INFO, $auth_id = 0)
{
    if (!isset($msg)) { return; }

    // Безопасное получение данных сессии
    $currentIp = filter_var($_SESSION['ip'] ?? '127.0.0.1', FILTER_VALIDATE_IP) ?: '127.0.0.1';
    $currentLogin = htmlspecialchars($_SESSION['login'] ?? 'http', ENT_QUOTES, 'UTF-8');

    // Для уровня L_DEBUG пишем в error_log
    if ($level === L_DEBUG) {
        error_log("DEBUG: " . $msg);
        return;
    }

    try {
        // Используем подготовленный запрос PDO напрямую
        $stmt = $db->prepare("INSERT INTO worklog(customer, message, level, auth_id, ip) 
                               VALUES (:customer, :message, :level, :auth_id, :ip)");
        
        $result = $stmt->execute([
            ':customer' => $currentLogin,
            ':message' => $msg,
            ':level' => $level,
            ':auth_id' => $auth_id,
            ':ip' => $currentIp
        ]);
        
        return $result;
        
    } catch (PDOException $e) {
        // В случае ошибки логируем в error_log, чтобы избежать рекурсии
        error_log("Error writing log to database: " . $e->getMessage());
        return false;
    }
}

function print_year_select($year_name, $year)
{
    print "<select id=\"$year_name\" name=\"$year_name\" >\n";
    for ($i = $year - 10; $i <= $year + 10; $i++) {
        print_select_item($i, $i, $year);
    }
    print "</select>\n";
}

function print_date_select($dd, $mm, $yy)
{
    if ($dd >= 1) {
        print "<b>День</b>\n";
        print "<select id=\"day\" name=\"day\" >\n";
        for ($i = 1; $i <= 31; $i++) {
            print_select_item($i, $i, $dd);
        }
        print "</select>\n";
    }

    if ($mm >= 1) {
        print "<b>Месяц</b>\n";
        print "<select id=\"month\"  name=\"month\" >\n";
        for ($i = 1; $i <= 12; $i++) {
            $tmp_date = DateTimeImmutable::createFromFormat('U', strtotime("$i/01/$yy"));
            $month_name = $tmp_date->format('F');
            print_select_item($month_name, $i, $mm);
        }
        print "</select>\n";
    }

    print "<b>Год</b>\n";
    print_year_select('year', $yy);
}

function print_date_select2($dd, $mm, $yy)
{
    if ($dd >= 1) {
        print "<b>День</b>\n";
        print "<select id=\"day2\" name=\"day2\" >\n";
        for ($i = 1; $i <= 31; $i++) {
            print_select_item($i, $i, $dd);
        }
        print "</select>\n";
    }

    if ($mm >= 1) {
        print "<b>Месяц</b>\n";
        print "<select id=\"month2\" name=\"month2\" >\n";
        for ($i = 1; $i <= 12; $i++) {
            $tmp_date = DateTimeImmutable::createFromFormat('U', strtotime("$i/01/$yy"));
            $month_name = $tmp_date->format('F');
            print_select_item($month_name, $i, $mm);
        }
        print "</select>\n";
    }

    print "<b>Год</b>\n";
    print_year_select('year2', $yy);
}

function is_up($ip)
{
    if (!isset($ip) or strlen($ip) == 0) {
        return false;
    }
    exec(sprintf('ping -i .3 -c 1 -W 5 %s', escapeshellarg($ip)), $res, $rval);
    return $rval == 0;
}

function apply_device_lock($db, $device_id, $iteration = 0)
{   
    $iteration++;
    if ($iteration > 2) {
        return false;
    }
        
    // Извлекаем исходную временную метку (без UNIX_TIMESTAMP)
    $dev = get_record_sql($db, 
        'SELECT discovery_locked, locked_timestamp FROM devices WHERE id = ? AND discovery_locked > 0', 
        [(int)$device_id]
    );
    
    // Проверяем, есть ли запись и валидна ли временная метка
    if (empty($dev) || is_empty_datetime($dev['locked_timestamp'])) {
        LOG_DEBUG($db, "Snmp discovery lock not found. Set and discovery.");
        return set_lock_discovery($db, (int)$device_id);
    }
        
    // Преобразуем строку даты в Unix timestamp
    try {
        // Удаляем микросекунды (если есть, как в PostgreSQL)
        $ts_str = preg_replace('/\.\d+$/', '', $dev['locked_timestamp']);
        $dt = new DateTime($ts_str);
        $u_locked_timestamp = $dt->getTimestamp();
    } catch (Exception $e) {
        // Если парсинг не удался — считаем блокировку недействительной
        LOG_DEBUG($db, "Invalid lock timestamp format. Resetting lock.");
        return set_lock_discovery($db, (int)$device_id);
    }
        
    $now = time();
    $wait_time = ($u_locked_timestamp + SNMP_LOCK_TIMEOUT) - $now;
    
    LOG_DEBUG($db, "Check snmp lock for device id: " . $device_id . ". Lock timestamp: " . $u_locked_timestamp . ", now: " . $now);
    
    if ($wait_time <= 0) {
        LOG_DEBUG($db, "The lock is already expired. Set new lock.");
        return set_lock_discovery($db, (int)$device_id);
    }
        
    LOG_VERBOSE($db, "Snmp discovery lock for device id: $device_id found! Need wait " . $wait_time . " sec.");
    sleep($wait_time);
    LOG_VERBOSE($db, "Try set new lock and continue discovery for device id: " . $device_id);
    
    return apply_device_lock($db, (int)$device_id, $iteration);
}   

function set_lock_discovery($db, $device_id)
{   
    $new['discovery_locked'] = 1;
    $new['locked_timestamp'] = GetNowTimeString();
    if (update_record($db, 'devices', 'id = ?', $new, [(int)$device_id])) {
        return true;
    }
    return false;
}

function unset_lock_discovery($db, $device_id)
{
    $new['discovery_locked'] = 0;
    $new['locked_timestamp'] = GetNowTimeString();
    if (update_record($db, 'devices', 'id = ?', $new, [(int)$device_id])) {
        return true;
    }
    return false;
}

function set_port_for_group($db, $group_id, $place_id, $state)
{
    $group_id = (int)$group_id;
    $place_id = (int)$place_id;
    $state = (int)$state;
    
    // Получаем список авторизаций для группы
    $auth_list = get_records_sql($db,
        'SELECT user_auth.id, user_auth.dns_name, user_auth.ip 
         FROM user_auth, user_list 
         WHERE user_auth.user_id = user_list.id 
           AND user_auth.deleted = 0 
           AND user_list.ou_id = ?',
        [$group_id]
    );
    
    LOG_VERBOSE($db, 'Mass port state change started!');
    
    // Обработка списка авторизаций
    foreach ($auth_list as $row) {
        $auth_id = (int)$row['id'];
        
        // Формируем фильтр по месту
        if ($place_id == 0) {
            $place_condition = '1=1';
            $place_params = [];
        } else {
            $place_condition = 'D.building_id = ?';
            $place_params = [$place_id];
        }
        
        // Получение информации об устройстве
        $devSQL = 'SELECT D.id, D.device_name, D.vendor_id, D.device_model, D.ip, 
                          DP.port, DP.snmp_index  
                   FROM devices AS D, device_ports AS DP, connections AS C 
                   WHERE ' . $place_condition . ' 
                         AND D.id = DP.device_id 
                         AND DP.id = C.port_id 
                         AND C.auth_id = ?';
        
        $params = array_merge($place_params, [$auth_id]);
        $dev_info = get_record_sql($db, $devSQL, $params);
        
        if (empty($dev_info)) { 
            continue; 
        }
        
        // Получение устройства
        $device = get_record($db, 'devices', "id = ?", [(int)$dev_info['id']]);
        $snmp = getSnmpAccess($device);
        
        // Определение режима и обновление nagios_handler
        if ($state == 1) {
            $mode = 'enable';
            update_record($db, 'user_auth', 
                'id = ? AND nagios_handler = ?', 
                ['nagios_handler' => 'restart-port'], 
                [$auth_id, 'manual-mode']
            );
        } else {
            $mode = 'disable';
            update_record($db, 'user_auth', 
                'id = ? AND nagios_handler = ?', 
                ['nagios_handler' => 'manual-mode'], 
                [$auth_id, 'restart-port']
            );
        }
        
        // Логирование
        LOG_INFO($db, "At device " . htmlspecialchars($dev_info['device_name']) . 
                      " [" . htmlspecialchars($dev_info['ip']) . "] " . 
                      $mode . " port " . htmlspecialchars($dev_info['port']) . 
                      " for auth_id: " . $auth_id . 
                      " (" . htmlspecialchars($row['ip']) . " [" . htmlspecialchars($row['dns_name']) . "])");
        
        // Установка состояния порта
        set_port_state($dev_info['vendor_id'], $dev_info['snmp_index'], 
                       $dev_info['ip'], $snmp, $state);
        
        // Установка состояния PoE
        set_port_poe_state($dev_info['vendor_id'], $dev_info['port'], 
                           $dev_info['snmp_index'], $dev_info['ip'], 
                           $snmp, $state);
    }
    
    LOG_VERBOSE($db, 'Mass port state change stopped.');
}

function get_mac_vendor($db, $mac)
{
    if (empty($mac)) { 
        return ''; 
    }
    
    $mac = mac_dotted($mac);
    $mac_prefixes = [
        $mac,           // полный MAC
        substr($mac, 0, 14), // OUI + 2 байта
        substr($mac, 0, 11), // OUI + 1 байт  
        substr($mac, 0, 8)   // только OUI
    ];
    
    $vendor = null;
    foreach ($mac_prefixes as $oui) {
        if (empty($oui)) continue;
        $vendor = get_record_sql($db, 'SELECT companyname,companyaddress FROM mac_vendors WHERE oui = ?', [$oui]);
        if (!empty($vendor)) { break; }
    }
    if (empty($vendor)) { return ''; }

    $address = $vendor['companyaddress'] ?? null;
    $name = $vendor['companyname'] ?? null;
    
    if (!empty($address)) {
        return $address;
    }
    return $name ?? '';
}

function strHexToBin($number)
{
    $result = '';
    for ($i = 0; $i < strlen($number); $i++) {
        $conv = base_convert($number[$i], 16, 2);
        $result .= str_pad($conv, 4, '0', STR_PAD_LEFT);
    }
    return $result;
}

function dec_to_hex($mac)
{
    if (!isset($mac)) {
        return;
    }
    $mac_array = explode('.', $mac);
    for ($i = 0; $i < count($mac_array); $i++) {
        $hex_i = dechex($mac_array[$i]);
        if (strlen($hex_i) == 1) {
            $hex_i = "0" . $hex_i;
        }
        $mac_array[$i] = $hex_i;
    }
    $hex_mac = implode(':', $mac_array);
    return $hex_mac;
}

function mac_simplify($mac)
{
    if (!isset($mac)) {
        return;
    }
    $mac = strtolower(trim($mac));
    $mac = preg_replace('/(\.|:|-)/', '', $mac);
    return $mac;
}

function mac_dotted($mac)
{
    if (!isset($mac)) {
        return;
    }
    $mac = mac_simplify($mac);
    $mac = preg_replace('/(\S{2})(\S{2})?(\S{2})?(\S{2})?(\S{2})?(\S{2})?/', '$1:$2:$3:$4:$5:$6', $mac);
    $mac = preg_replace('/\:+$/', '', $mac, 5);
    return $mac;
}

function unbind_ports($db, $device_id)
{
    $device_id = (int)$device_id;
    
    $target = get_records_sql($db, 
        "SELECT target_port_id, id FROM device_ports WHERE device_id = ?", 
        [$device_id]
    );
    
    foreach ($target as $row) {
        $port_id = (int)$row['id'];
        $target_port_id = (int)$row['target_port_id'];
        
        // Обнуляем ссылки на этот порт у других портов
        update_record($db, "device_ports", "target_port_id = ?", ['target_port_id' => 0], [$port_id]);
        
        // Обнуляем ссылку этого порта на другой порт
        update_record($db, "device_ports", "id = ?", ['target_port_id' => 0], [$port_id]);
    }
}

function bind_ports($db, $port_id, $target_id)
{
    $port_id = (int)$port_id;
    $target_id = (int)$target_id;
    
    // Отвязываем текущее соединение
    $new = ['target_port_id' => 0];
    $old_target = get_record_sql($db, 
        "SELECT target_port_id FROM device_ports WHERE id = ?", 
        [$port_id]
    );
    
    if (!empty($old_target) && !empty($old_target['target_port_id'])) {
        $old_target_id = (int)$old_target['target_port_id'];
        update_record($db, "device_ports", "id = ?", $new, [$old_target_id]);
    }
    
    // Обнуляем текущий порт
    update_record($db, "device_ports", "id = ?", $new, [$port_id]);
    
    // Создаём новое соединение
    if ($target_id > 0) {
        // Связываем port_id -> target_id
        update_record($db, "device_ports", "id = ?", ['target_port_id' => $target_id], [$port_id]);
        // Связываем target_id -> port_id
        update_record($db, "device_ports", "id = ?", ['target_port_id' => $port_id], [$target_id]);
    }
}

function expand_device_name($db, $name)
{
    if (empty($name)) {
        return '';
    }
    
    $device_id = get_device_id($db, $name);
    if (isset($device_id) && $device_id > 0) {
        $safe_name = htmlspecialchars($name);
        $safe_id = (int)$device_id;
        return '<a href="/admin/devices/editdevice.php?id=' . $safe_id . '">' . $safe_name . '</a>';
    }
    return $name;
}

function expand_mac($db, $mac)
{
    if (empty($mac)) {
        return '';
    }
    
    $mac = mac_dotted($mac);
    $safe_vendor = get_mac_vendor($db, $mac);
    $safe_mac = $mac;
    $safe_url_mac = urlencode($mac);
    if (!empty($safe_vendor)) {
        return '<a href="/admin/logs/mac.php?mac=' . $safe_url_mac . '"><p title="' . $safe_vendor . '">' . $safe_mac . '</p></a>';
    } else {
        return '<a href="/admin/logs/mac.php?mac=' . $safe_url_mac . '">' . $safe_mac . '</a>';
    }
}


function expand_log_str($db, $msg)
{
    if (empty($msg)) {
        return '';
    }
    
    $result = $msg;

    // === Замена auth_id ===
    $auth_pattern = '/(auth_id:|auth|auth id:|auth id)\s+(\d+)/i';
    $result = preg_replace_callback($auth_pattern, function($matches) {
        $id = (int)$matches[2];
        $safe_id = htmlspecialchars($id, ENT_QUOTES, 'UTF-8');
        return '<a href="/admin/users/editauth.php?id=' . $id . '">auth_id:' . $safe_id . '</a>';
    }, $result);

    // === Замена user_id ===
    $user_pattern = '/(user_id:|user|user id:|user id)\s+(\d+)/i';
    $result = preg_replace_callback($user_pattern, function($matches) {
        $id = (int)$matches[2];
        $safe_id = htmlspecialchars($id, ENT_QUOTES, 'UTF-8');
        return '<a href="/admin/users/edituser.php?id=' . $id . '">user_id:' . $safe_id . '</a>';
    }, $result);

    // === Замена MAC-адресов в формате [1234567890ab] ===
    $mac_pattern1 = '/\s*\[(\w{12})\]\s*/i';
    $result = preg_replace_callback($mac_pattern1, function($matches) {
        $mac_raw = $matches[1];
        $mac_dotted = mac_dotted($mac_raw);
        $safe_mac = htmlspecialchars($mac_dotted, ENT_QUOTES, 'UTF-8');
        $url_mac = urlencode($mac_dotted);
        return ' <a href="/admin/logs/mac.php?mac=' . $url_mac . '">' . $safe_mac . '</a> ';
    }, $result);

    // === Замена MAC-адресов в формате "mac: xx:xx:xx:xx:xx:xx" ===
    $mac_pattern2 = '/\s*mac:\s+([\w:]{17})/i';
    $result = preg_replace_callback($mac_pattern2, function($matches) {
        $mac_raw = $matches[1];
        $mac_dotted = mac_dotted($mac_raw);
        $safe_mac = htmlspecialchars($mac_dotted, ENT_QUOTES, 'UTF-8');
        $url_mac = urlencode($mac_dotted);
        return ' mac: <a href="/admin/logs/mac.php?mac=' . $url_mac . '">' . $safe_mac . '</a> ';
    }, $result);

    // === Замена device name ===
    $device_pattern1 = '/at device\s+([\w.\-]+)/i';
    $result = preg_replace_callback($device_pattern1, function($matches) use ($db) {
        $device_name = $matches[1];
        $device_id = get_device_id($db, $device_name);
        if ($device_id && $device_id > 0) {
            $safe_name = htmlspecialchars($device_name, ENT_QUOTES, 'UTF-8');
            return 'at device <a href="/admin/devices/editdevice.php?id=' . (int)$device_id . '">' . $safe_name . '</a>';
        }
        return $matches[0];
    }, $result);

    // === Замена device_id ===
    $device_pattern2 = '/(device_id:|device id:|device id|device_id)\s+(\d+)/i';
    $result = preg_replace_callback($device_pattern2, function($matches) {
        $id = (int)$matches[2];
        $safe_id = htmlspecialchars($id, ENT_QUOTES, 'UTF-8');
        return 'device_id: <a href="/admin/devices/editdevice.php?id=' . $id . '">' . $safe_id . '</a>';
    }, $result);

    // === Замена IP-адресов ===
    $ip_pattern = '/\s*ip:\s+(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})\s*/i';
    $result = preg_replace_callback($ip_pattern, function($matches) use ($db) {
        $ip = $matches[1];
        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            $auth_id = get_auth_by_ip($db, $ip);
            if ($auth_id && $auth_id > 0) {
                $safe_ip = htmlspecialchars($ip, ENT_QUOTES, 'UTF-8');
                return ' ip: <a href="/admin/users/editauth.php?id=' . (int)$auth_id . '">' . $safe_ip . '</a> ';
            }
        }
        return $matches[0];
    }, $result);

    return $result;
}

function is_auth_bind_changed($db, $id, $ip, $mac)
{
    $old_record = get_record_sql($db, 
        "SELECT ip, mac FROM user_auth WHERE id = ?", 
        [(int)$id]
    );
    
    if (empty($old_record) || empty($old_record["ip"]) || empty($old_record["mac"])) {
        return 0;
    }
    
    if ($old_record["ip"] !== $ip || $old_record["mac"] !== $mac) {
        LOG_VERBOSE($db, "Changed ip or mac for auth record!");
        return 1;
    }
    
    return 0;
}

function copy_auth($db, $id, $new_auth)
{
    $id = (int)$id;
    $old_record = get_record_sql($db, 
        "SELECT * FROM user_auth WHERE id = ?", 
        [$id]
    );
    
    if (empty($old_record)) {
        return null;
    }
    
    delete_record($db, "user_auth", "id = ?", [$id]);
    
    $new_auth["user_id"] = $old_record["user_id"];
    $new_auth["changed"] = 1;
    $new_auth["changed_time"] = GetNowTimeString();
    
    $new_id = insert_record($db, "user_auth", $new_auth);
    
    if (!empty($new_id)) {
        LOG_VERBOSE($db, "Old record with id: $id deleted. Created new auth record for new ip+mac id: $new_id!");
    }
    
    return $new_id;
}

function get_dns_name($db, $id)
{
    $auth_record = get_record_sql($db, 
        "SELECT dns_name FROM user_auth WHERE id = ?", 
        [(int)$id]
    );
    
    if (!empty($auth_record) && !empty($auth_record['dns_name'])) {
        return $auth_record['dns_name'];
    }
    
    return '';
}

function get_cacti_graph($host_ip, $port_index)
{
    // Проверка конфигурации Cacti
    if (empty(get_const('cacti_url'))) {
        return null;
    }
    
    if (empty(CACTI_DB_HOST) || empty(CACTI_DB_USER) || empty(CACTI_DB_PASS) || empty(CACTI_DB_NAME)) {
        return null;
    }
    
    // Установка соединения с БД Cacti
    $cacti_db_link = new_connection('mysql', CACTI_DB_HOST, CACTI_DB_USER, CACTI_DB_PASS, CACTI_DB_NAME);
    if (!$cacti_db_link) {
        return false;
    }
    
    // Поиск хоста по IP-адресу
    $cacti_host = get_record_sql($cacti_db_link, 
        "SELECT * FROM host WHERE hostname = ?", 
        [$host_ip]
    );
    
    if (empty($cacti_host) || empty($cacti_host["id"])) {
        return null;
    }
    
    $host_id = (int)$cacti_host["id"];
    $port_index = (string)$port_index; // SNMP index может быть строкой
    
    // Получение ID шаблонов графиков для трафика интерфейсов
    $traffic_templates = get_records_sql($cacti_db_link,
        "SELECT id FROM graph_templates WHERE name LIKE 'Interface - Traffic%'"
    );
    
    if (empty($traffic_templates)) {
        return null;
    }
    
    $template_ids = [];
    foreach ($traffic_templates as $template) {
        $template_ids[] = (int)$template['id'];
    }
    
    // Формирование условия IN для параметризованного запроса
    $placeholders = str_repeat('?,', count($template_ids) - 1) . '?';
    
    // Поиск графика по хосту, SNMP-индексу и шаблону
    $cacti_graph = get_record_sql($cacti_db_link,
        "SELECT * FROM graph_local 
         WHERE host_id = ? 
           AND snmp_index = ? 
           AND graph_template_id IN ($placeholders) 
         ORDER BY id ASC",
        array_merge([$host_id, $port_index], $template_ids)
    );
    
    if (empty($cacti_graph) || empty($cacti_graph["id"])) {
        return null;
    }
    
    $graph_id = (int)$cacti_graph["id"];
    $result = rtrim(get_const('cacti_url'), '/') . "/graph_image.php?local_graph_id=" . $graph_id;
    
    return $result;
}

function print_select_item($description, $value, $current)
{
    if ((string)$value === (string)$current) {
        print "<option value='" . $value . "' selected>$description</option>";
    } else {
        print "<option value='" . $value . "'>$description</option>";
    }
}

function print_select_simple($description, $value)
{
    print "<option value=$value>$description</option>";
}

function print_select_item_ext($description, $value, $current, $disabled)
{
    if ((string)$value === (string)$current) {
        print "<option value=$value selected>$description</option>";
    } else {
        if (!$disabled) {
            print "<option value=$value>$description</option>";
        } else {
            print "<option disabled value=$value>$description</option>";
        }
    }
}

function print_row_at_pages($name, $value)
{
    print "<select id='" . $name . "' name='" . $name . "'>\n";
    print_select_item(WEB_select_item_more, pow(10, 10), $value);
    print_select_item('25', 25, $value);
    print_select_item('50', 50, $value);
    print_select_item('100', 100, $value);
    print_select_item('200', 200, $value);
    print_select_item('500', 500, $value);
    print_select_item('1000', 1000, $value);
    print_select_item('2000', 2000, $value);
    print "</select>\n";
}

function print_navigation($url, $page, $displayed, $count_records, $total)
{
    if ($total <= 1) {
        print "<div align=left class=records >";
        print "| Total records: $count_records";
        print "</div>";
        return;
    }
    $v_char = "?";
    if (preg_match('/\.php\?/', $url)) {
        $v_char = "&";
    }
    //две назад
    print "<div align=left class=records >";
    if (($page - 2) > 0) :
        $pagetwoleft = "<a class='first_page_link' href=" . $url . $v_char . "page=" . ($page - 2) . ">" . ($page - 2) . "</a>  ";
    else :
        $pagetwoleft = null;
    endif;

    //одна назад
    if (($page - 1) > 0) :
        $pageoneleft = "<a class='first_page_link' href=" . $url . $v_char . "page=" . ($page - 1) . ">" . ($page - 1) . "</a>  ";
        $pagetemp = ($page - 1);
    else :
        $pageoneleft = null;
        $pagetemp = null;
    endif;

    //две вперед
    if (($page + 2) <= $total) :
        $pagetworight = "  <a class='first_page_link' href=" . $url . $v_char . "page=" . ($page + 2) . ">" . ($page + 2) . "</a>";
    else :
        $pagetworight = null;
    endif;

    //одна вперед
    if (($page + 1) <= $total) :
        $pageoneright = "  <a class='first_page_link' href=" . $url . $v_char . "page=" . ($page + 1) . ">" . ($page + 1) . "</a>";
        $pagetemp2 = ($page + 1);
    else :
        $pageoneright = null;
        $pagetemp2 = null;
    endif;

    // в начало
    if ($page != 1 && $pagetemp != 1 && $pagetemp != 2) :
        $pagerevp = "<a href=" . $url . $v_char . "page=1 class='first_page_link' title='В начало'><<</a> ";
    else :
        $pagerevp = null;
    endif;

    //в конец (последняя)
    if ($page != $total && $pagetemp2 != ($total - 1) && $pagetemp2 != $total) :
        $nextp = " ...  <a href=" . $url . $v_char . "page=" . $total . " class='first_page_link'>$total</a>";
    else :
        $nextp = null;
    endif;

    print $pagerevp . $pagetwoleft . $pageoneleft . '<span class="num_page_not_link"><b>' . $page . '</b></span>' . $pageoneright . $pagetworight . $nextp;
    print " | Total records: $count_records";
    print "</div>";
}

function get_option($db, $option_id)
{
    // Валидация входного параметра
    if (!is_numeric($option_id) || $option_id <= 0) {
        return null;
    }
    
    $sql = "
        SELECT
            COALESCE(c.value, co.default_value) AS value,
            co.option_type
        FROM config_options co
        LEFT JOIN config c ON c.option_id = co.id
        WHERE co.id = ?
    ";
    $record = get_record_sql($db, $sql, [(int)$option_id]);
    
    if ($record && isset($record['value'])) {
        return $record['value'];
    }
    return null;
}

function is_option($db, $option_id)
{
    if (!is_numeric($option_id) || $option_id <= 0) {
        return false;
    }
    
    $option = get_record($db, "config", "option_id = ?", [(int)$option_id]);
    if (empty($option) || empty($option['value'])) {
        return false;
    }
    return true;
}

function set_option($db, $option_id, $value)
{
    if (!is_numeric($option_id) || $option_id <= 0) {
        return false;
    }
    
    $option = ['value' => $value];
    $result = update_record($db, 'config', "option_id = ?", $option, [(int)$option_id]);
    return $result !== false;
}

function is_subnet_aton($subnet, $ip)
{
    if (empty($subnet) || empty($ip)) {
        return false;
    }
    
    // Проверяем корректность IP-адреса
    if (!is_numeric($ip)) {
        return false;
    }
    
    $range = cidrToRange($subnet);
    if ($range === false) {
        return false;
    }
    
    $ip_start = ip2long($range[0]);
    $ip_end = ip2long($range[1]);
    
    if ($ip_start === false || $ip_end === false) {
        return false;
    }
    
    if ($ip >= $ip_start && $ip <= $ip_end) {
        return true;
    }
    return false;
}

function get_new_user_id($db, $ip, $mac, $hostname)
{
    $result = [
        'ip' => $ip,
        'mac' => !empty($mac) ? mac_dotted($mac) : '',
        'hostname' => $hostname,
        'user_id' => null,
        'ou_id' => null
    ];
    
    $ip_aton = ip2long($ip);
    if ($ip_aton === false) {
        // Некорректный IP - используем значения по умолчанию
        if (empty($result['ou_id'])) {
            $result['ou_id'] = get_const('default_user_ou_id');
        }
        return $result;
    }
    
    // Проверка hotspot
    if (is_hotspot($db, $ip)) {
        $result['ou_id'] = get_const('default_hotspot_ou_id');
        return $result;
    }
    
    // === Правила для пользователей ===
    
    // IP правила (rule_type = 1)
    if (!empty($ip)) {
        $t_rules = get_records_sql($db, 
            "SELECT * FROM auth_rules WHERE rule_type = 1 AND LENGTH(rule) > 0 AND user_id IS NOT NULL"
        );
        foreach ($t_rules as $row) {
            if (!empty($row['rule']) && is_subnet_aton($row['rule'], $ip_aton)) {
                $result['user_id'] = (int)$row['user_id'];
                return $result;
            }
        }
    }
    
    // MAC правила (rule_type = 2)
    if (!empty($mac)) {
        $mac_simplified = mac_simplify($mac);
        $mac_rules = get_records_sql($db, 
            "SELECT * FROM auth_rules WHERE rule_type = 2 AND LENGTH(rule) > 0 AND user_id IS NOT NULL"
        );
        foreach ($mac_rules as $row) {
            if (!empty($row['rule'])) {
                $pattern = '/' . preg_quote(mac_simplify($row['rule']), '/') . '/';
                if (preg_match($pattern, $mac_simplified)) {
                    $result['user_id'] = (int)$row['user_id'];
                    return $result;
                }
            }
        }
    }
    
    // Hostname правила (rule_type = 3)
    if (!empty($hostname)) {
        $hostname_rules = get_records_sql($db, 
            "SELECT * FROM auth_rules WHERE rule_type = 3 AND LENGTH(rule) > 0 AND user_id IS NOT NULL"
        );
        foreach ($hostname_rules as $row) {
            if (!empty($row['rule'])) {
                // Добавляем делимитеры к регулярному выражению, если их нет
                $pattern = $row['rule'];
                if (@preg_match($pattern, '') === false) {
                    // Если шаблон некорректен, пропускаем
                    continue;
                }
                if (preg_match($pattern, $hostname)) {
                    $result['user_id'] = (int)$row['user_id'];
                    return $result;
                }
            }
        }
    }
    
    // === Правила для OU ===
    
    // IP правила для OU (rule_type = 1)
    if (!empty($ip)) {
        $t_rules = get_records_sql($db, 
            "SELECT * FROM auth_rules WHERE rule_type = 1 AND LENGTH(rule) > 0 AND ou_id IS NOT NULL"
        );
        foreach ($t_rules as $row) {
            if (!empty($row['rule']) && is_subnet_aton($row['rule'], $ip_aton)) {
                $result['ou_id'] = (int)$row['ou_id'];
                return $result;
            }
        }
    }
    
    // MAC правила для OU (rule_type = 2)
    if (!empty($mac)) {
        $mac_simplified = mac_simplify($mac);
        $mac_rules = get_records_sql($db, 
            "SELECT * FROM auth_rules WHERE rule_type = 2 AND LENGTH(rule) > 0 AND ou_id IS NOT NULL"
        );
        foreach ($mac_rules as $row) {
            if (!empty($row['rule'])) {
                $pattern = '/' . preg_quote(mac_simplify($row['rule']), '/') . '/';
                if (preg_match($pattern, $mac_simplified)) {
                    $result['ou_id'] = (int)$row['ou_id'];
                    return $result;
                }
            }
        }
    }
    
    // Hostname правила для OU (rule_type = 3)
    if (!empty($hostname)) {
        $hostname_rules = get_records_sql($db, 
            "SELECT * FROM auth_rules WHERE rule_type = 3 AND LENGTH(rule) > 0 AND ou_id IS NOT NULL"
        );
        foreach ($hostname_rules as $row) {
            if (!empty($row['rule'])) {
                $pattern = $row['rule'];
                if (@preg_match($pattern, '') === false) {
                    continue;
                }
                if (preg_match($pattern, $hostname)) {
                    $result['ou_id'] = (int)$row['ou_id'];
                    return $result;
                }
            }
        }
    }
    
    // Значение по умолчанию
    if (empty($result['ou_id'])) {
        $result['ou_id'] = get_const('default_user_ou_id');
    }
    
    return $result;
}

function get_subnet_range($db, $subnet_id)
{
    if (empty($subnet_id)) {
        return null;
    }
    
    $t_option = get_record_sql($db, 
        "SELECT ip_int_start, ip_int_stop FROM subnets WHERE id = ?", 
        [(int)$subnet_id]
    );
    
    if (empty($t_option)) {
        return null;
    }
    
    return [
        'start' => isset($t_option['ip_int_start']) ? (int)$t_option['ip_int_start'] : 0,
        'stop' => isset($t_option['ip_int_stop']) ? (int)$t_option['ip_int_stop'] : 0
    ];
}

function int_between($value, $start, $end)
{
    return in_array($value, range($start, $end));
}

function is_gray_network($ip)
{
    if (empty($ip)) {
        return 0;
    }
    $ip_aton = ip2long($ip);
    $gray_nets = array('10.0.0.0/8', '192.168.0.0/16', '172.16.0.0/12', '100.64.0.0/10');
    foreach ($gray_nets as &$net) {
        $net_cidr = cidrToRange($net);
        if (int_between($ip_aton, ip2long($net_cidr[0]), ip2long($net_cidr[1]))) {
            return $net;
        }
    }
    return 0;
}

function is_hotspot($db, $ip)
{   
    if (empty($ip)) {
        return false;
    }
    
    $ip_aton = ip2long($ip);
    if ($ip_aton === false) {
        LOG_DEBUG($db, "Invalid IP address: $ip");
        return false;
    }
    
    LOG_DEBUG($db, "Check hotspot network for ip: $ip");
    $t_option = get_records_sql($db, "SELECT subnet, ip_int_start, ip_int_stop FROM subnets WHERE hotspot = 1");
    
    foreach ($t_option as $row) {
        if ($ip_aton >= (int)$row['ip_int_start'] && $ip_aton <= (int)$row['ip_int_stop']) {
            LOG_DEBUG($db, "ip: $ip [$ip_aton] found in hotspot network " . $row['subnet'] . ": [" . $row['ip_int_start'] . ".." . $row['ip_int_stop'] . "]");
            return true;
        }
    }
    
    LOG_DEBUG($db, "ip $ip not found in hotspot network!");
    return false;
}

function is_office($db, $ip)
{   
    if (empty($ip)) {
        return false;
    }
    
    $ip_aton = ip2long($ip);
    if ($ip_aton === false) {
        LOG_DEBUG($db, "Invalid IP address: $ip");
        return false;
    }
    
    LOG_DEBUG($db, "Check office network for ip: $ip");
    $t_option = get_records_sql($db, "SELECT subnet, ip_int_start, ip_int_stop FROM subnets WHERE office = 1");
    
    foreach ($t_option as $row) {
        if ($ip_aton >= (int)$row['ip_int_start'] && $ip_aton <= (int)$row['ip_int_stop']) {
            LOG_DEBUG($db, "ip: $ip [$ip_aton] found in office network " . $row['subnet'] . ": [" . $row['ip_int_start'] . ".." . $row['ip_int_stop'] . "]");
            return true;
        }
    }
    
    LOG_DEBUG($db, "ip $ip not found in office network!");
    return false;
}

function get_office_subnet($db, $ip)
{   
    if (empty($ip)) {
        return null;
    }
    
    $ip_aton = ip2long($ip);
    if ($ip_aton === false) {
        LOG_DEBUG($db, "Invalid IP address: $ip");
        return null;
    }
    
    LOG_DEBUG($db, "Check office network for ip: $ip");
    $subnets = get_records_sql($db, 'SELECT * FROM subnets WHERE office = 1');
    
    foreach ($subnets as $row) {
        if ($ip_aton >= (int)$row['ip_int_start'] && $ip_aton <= (int)$row['ip_int_stop']) {
            LOG_DEBUG($db, "ip: $ip [$ip_aton] found in office {$row['subnet']}: [" . $row['ip_int_start'] . ".." . $row['ip_int_stop'] . "]");
            return $row;
        }
    }
    
    LOG_DEBUG($db, "ip $ip not found in office network!");
    return null;
}

function get_notify_subnet($db, $ip)
{   
    if (empty($ip)) {
        return 0;
    }
    
    $office_subnet = get_office_subnet($db, $ip);
    if ($office_subnet && isset($office_subnet['notify'])) {
        return (int)$office_subnet['notify'];
    }
    return 0;
}

function is_our_network($db, $ip)
{
    if (!isset($ip)) {
        return 0;
    }
    if (is_hotspot($db, $ip)) {
        return 1;
    }
    if (is_office($db, $ip)) {
        return 1;
    }
    return 0;
}

function get_const($const_name)
{
    global $config;
    if (isset($config[$const_name])) {
        return $config[$const_name];
    }
    return NULL;
}

function get_eye_version($db)
{
    $v_table = get_record_sql($db, "SELECT version FROM version");
    if (!empty($v_table)) {
        return $v_table['version'];
    }
    return NULL;
}


function getNotifyFlags(): array {
    return [
        WEB_NOTIFY_NONE   => NOTIFY_NONE,
        WEB_NOTIFY_CREATE => NOTIFY_CREATE,
        WEB_NOTIFY_UPDATE => NOTIFY_UPDATE,
        WEB_NOTIFY_DELETE => NOTIFY_DELETE,
    ];
}

function getNotifyLabels(): array {
    return [
        NOTIFY_NONE   => WEB_NOTIFY_NONE,
        NOTIFY_CREATE => WEB_NOTIFY_CREATE,
        NOTIFY_UPDATE => WEB_NOTIFY_UPDATE,
        NOTIFY_DELETE => WEB_NOTIFY_DELETE,
    ];
}


function printFlagsByFirstLetter(int $flags): string {
    if ($flags === 0) {
        return 'x';
    }

    $flagLabels = getNotifyLabels();
    
    $activeLetters = [];
    $fullLabels = [];
    
    foreach ($flagLabels as $flagValue => $label) {
        if ($flagValue === 0) continue;
        
        if (($flags & $flagValue) === $flagValue) {
            $firstLetter = mb_substr($label, 0, 1, 'UTF-8');
            $activeLetters[] = $firstLetter;
            $fullLabels[] = $label;
        }
    }
    
    sort($activeLetters);
    $letters = implode('', $activeLetters);
    $tooltipText = implode(', ', $fullLabels);
    
    return '<span title="' . htmlspecialchars($tooltipText) . '">' . htmlspecialchars($letters) . '</span>';
}


function renderNotifyCombobox(string $name, int $selectedFlags = 0, array $attributes = []): string {
    $labels = getNotifyLabels();
    $flags = getNotifyFlags();
    
    // Собираем атрибуты
    $attrString = '';
    foreach ($attributes as $key => $value) {
        $attrString .= ' ' . htmlspecialchars($key) . '="' . htmlspecialchars($value) . '"';
    }
    
    // Предопределенные комбинации с читаемыми названиями
    $combinations = [
        NOTIFY_NONE => $labels[NOTIFY_NONE],
        NOTIFY_CREATE => $labels[NOTIFY_CREATE],
        NOTIFY_UPDATE => $labels[NOTIFY_UPDATE],
        NOTIFY_DELETE => $labels[NOTIFY_DELETE],
        NOTIFY_CREATE | NOTIFY_UPDATE => $labels[NOTIFY_CREATE] . ' + ' . $labels[NOTIFY_UPDATE],
        NOTIFY_CREATE | NOTIFY_DELETE => $labels[NOTIFY_CREATE] . ' + ' . $labels[NOTIFY_DELETE],
        NOTIFY_UPDATE | NOTIFY_DELETE => $labels[NOTIFY_UPDATE] . ' + ' . $labels[NOTIFY_DELETE],
        NOTIFY_CREATE | NOTIFY_UPDATE | NOTIFY_DELETE => $labels[NOTIFY_CREATE] . ' + ' . $labels[NOTIFY_UPDATE] . ' + ' . $labels[NOTIFY_DELETE],
    ];
    
    $html = '<select name="' . htmlspecialchars($name) . '"' . $attrString . '>';
    
    foreach ($combinations as $value => $label) {
        $isSelected = ($selectedFlags === $value);
        $selected = $isSelected ? ' selected' : '';
        
        $html .= '<option value="' . $value . '"' . $selected . '>'
               . htmlspecialchars($label)
               . '</option>';
    }
    
    $html .= '</select>';
    return $html;
}

/**
 * Проверяет, установлен ли флаг создания
 */
function isNotifyCreate(int $flags): bool {
    return ($flags & NOTIFY_CREATE) === NOTIFY_CREATE;
}

/**
 * Проверяет, установлен ли флаг изменения
 */
function isNotifyUpdate(int $flags): bool {
    return ($flags & NOTIFY_UPDATE) === NOTIFY_UPDATE;
}

/**
 * Проверяет, установлен ли флаг удаления
 */
function isNotifyDelete(int $flags): bool {
    return ($flags & NOTIFY_DELETE) === NOTIFY_DELETE;
}

/**
 * Проверяет, отключены ли все уведомления
 */
function isNotifyNone(int $flags): bool {
    return $flags === NOTIFY_NONE;
}

/**
 * Проверяет, установлен ли конкретный флаг
 */
function hasNotifyFlag(int $flags, int $flagToCheck): bool {
    return ($flags & $flagToCheck) === $flagToCheck;
}

/**
 * Устанавливает флаг(и)
 */
function setNotifyFlag(int &$flags, int $flagToSet): void {
    $flags |= $flagToSet;
}

/**
 * Снимает флаг(и)
 */
function unsetNotifyFlag(int &$flags, int $flagToUnset): void {
    $flags &= ~$flagToUnset;
}

/**
 * Преобразует массив выбранных значений в битовую маску
 */
function arrayToNotifyFlags(array $selectedValues): int {
    $flags = NOTIFY_NONE;
    foreach ($selectedValues as $value) {
        $flags |= (int)$value;
    }
    return $flags;
}

/**
 * Проверяет, является ли OU системным (используется по умолчанию для пользователей или хотспотов)
 *
 * @param PDO $db
 * @param int $ou_id
 * @return bool
 */
function is_system_ou($db, $ou_id = null) {
    if (empty($ou_id) || !is_numeric($ou_id) || $ou_id <= 0) {
        return false;
    }
    $sql = "SELECT 1 FROM ou WHERE id = ? AND (default_users = 1 OR default_hotspot = 1)";
    return !empty(get_record_sql($db, $sql, [$ou_id]));
}

$config["org_name"] = get_option($db_link, 32);

$config["version"] = get_eye_version($db_link);

$config["KB"] = get_option($db_link, 1);
if ($config["KB"] == 0) {
    $config["KB"] = 1000;
}
if ($config["KB"] == 1) {
    $config["KB"] = 1024;
}

$config["debug"] = get_option($db_link, 34);
$config["log_level"] = get_option($db_link, 53);
if ($config["debug"]) {
    $config["log_level"] = 255;
}
$config["send_email"] = get_option($db_link, 51);
$config["admin_email"] = get_option($db_link, 21);
$config["sender_email"] = get_option($db_link, 52);
$config["snmp_default_version"] = get_option($db_link, 9);
$config["snmp_default_community"] = get_option($db_link, 11);
$config["auto_mac_rule"] = get_option($db_link, 64);

$config["traffic_ipstat_history"] = get_option($db_link, 56);

$config["cacti_url"] = rtrim(get_option($db_link, 58), '/');
if (preg_match('/127.0.0.1/', $config["cacti_url"])) {
    $config["cacti_url"] = NULL;
}

$config["nagios_url"] = rtrim(get_option($db_link, 57), '/') . '/cgi-bin/';
if (preg_match('/127.0.0.1/', $config["nagios_url"])) {
    $config["nagios_url"] = NULL;
}

$config["torrus_url"] = rtrim(get_option($db_link, 59), '/') . '?nodeid=if//HOST_IP//IF_NAME////inoutbps';
if (preg_match('/127.0.0.1/', $config["torrus_url"])) {
    $config["torrus_url"] = NULL;
}

$config["dns_server"] = get_option($db_link, 3);
$config["dns_server_type"] = get_option($db_link, 70);

$ou = get_record_sql($db_link, "SELECT id FROM ou WHERE default_users = 1");
if (empty($ou)) {
    $config["default_user_ou_id"] = 0;
} else {
    $config["default_user_ou_id"] = $ou['id'];
}

$ou = get_record_sql($db_link, "SELECT id FROM ou WHERE default_hotspot=1");
if (empty($ou)) {
    $config["default_hotspot_ou_id"] = $config["default_user_ou_id"];
} else {
    $config["default_hotspot_ou_id"] = $ou['id'];
}

$config["init"] = 1;

clean_dns_cache($db_link);
//clean_unreferensed_rules($db_link);

$config["debug"] = 1;