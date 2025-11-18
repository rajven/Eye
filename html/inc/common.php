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

function getParam($name, $page_url, $default = null, $filter = FILTER_DEFAULT) {
    $value = filter_input(INPUT_GET, $name, $filter) ?? filter_input(INPUT_POST, $name, $filter);
    return $value !== null ? $value : ($_SESSION[$page_url][$name] ?? $default);
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

function mb_ucfirst($str)
{
    $str = mb_strtolower($str);
    $fc = mb_strtoupper(mb_substr($str, 0, 1));
    return $fc . mb_substr($str, 1);
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
        return NULL;
    }

    $result = '';
    $domain_zone = get_option($db, 33);

    $a_search_filter = 'SELECT * FROM User_auth WHERE deleted=0 and id !="' . $id . '" and (dns_name ="' . mysqli_real_escape_string($db, $hostname) . '" or dns_name ="' . mysqli_real_escape_string($db, $hostname . '.' . $domain_zone) . '")';
//        LOG_DEBUG($db, "A search-filter: ".$a_search_filter);
    $a_records = get_records_sql($db, $a_search_filter);
    foreach ($a_records as $a_rec) {
        $result .= 'auth_id:' . $a_rec['id'] . ' ip: ' . $a_rec['ip'] . '; ';
    }
    if (!empty($result)) {
        $result = 'A-record: ' . $result;
    }

    $result_cname = '';
    $cname_search_filter = 'SELECT * FROM User_auth_alias WHERE auth_id !="' . $id . '" and (alias ="' . mysqli_real_escape_string($db, $hostname) . '" or alias ="' . mysqli_real_escape_string($db, $hostname . '.' . $domain_zone) . '")';
//        LOG_DEBUG($db, "CNAME search-filter: ".$cname_search_filter);
    $a_records = get_records_sql($db, $cname_search_filter);
    foreach ($a_records as $a_rec) {
        $result_cname .= 'auth_id:' . $a_rec['auth_id'] . ';';
    }
    if (!empty($result_cname)) {
        $result_cname = 'CNAME-record: ' . $result_cname;
    }

    $result = trim($result . ' ' . $result_cname);
    return $result;
}

function checkUniqHostname($db, $id, $hostname)
{
    if (empty($hostname)) {
        return TRUE;
    }

    $domain_zone = get_option($db, 33);

    $check_A_filter = 'deleted=0 and id !="' . $id . '" and (dns_name ="' . mysqli_real_escape_string($db, $hostname) . '" or dns_name ="' . mysqli_real_escape_string($db, $hostname . '.' . $domain_zone) . '")';
//        LOG_DEBUG($db, "CNAME filter: ".$check_A_filter);

    $count = get_count_records($db, 'User_auth', $check_A_filter);
    if ($count > 0) {
        return FALSE;
    }

    $check_CNAME_filter = 'auth_id !="' . $id . '" and (alias ="' . mysqli_real_escape_string($db, $hostname) . '" or alias ="' . mysqli_real_escape_string($db, $hostname . '.' . $domain_zone) . '")';

//        LOG_DEBUG($db, "CNAME filter: ".$check_CNAME_filter);

    $count = get_count_records($db, 'User_auth_alias', $check_CNAME_filter);
    if ($count > 0) {
        return FALSE;
    }

    return TRUE;
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
    if (!isset($cidr[1])) {
        $cidr[1] = 32;
    }
    $start = (ip2long($cidr[0])) & ((-1 << (32 - (int) $cidr[1])));
    $stop = $start + pow(2, (32 - (int) $cidr[1])) - 1;
    $range[0] = long2ip($start);
    $range[1] = long2ip($stop);
    $range[2] = $cidr;
    //dhcp
    $dhcp_size = round(($stop - $start) / 2, PHP_ROUND_HALF_UP);
    $dhcp_start = $start + round($dhcp_size / 2, PHP_ROUND_HALF_UP);
    $range[3] = long2ip($dhcp_start);
    $range[4] = long2ip($dhcp_start + $dhcp_size);
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

function print_ou_select($db, $ou_name, $ou_value)
{
    print "<select id=\"$ou_name\" name=\"$ou_name\" >\n";
    $t_ou = mysqli_query($db, "SELECT id,ou_name FROM OU ORDER BY ou_name");
    while (list($f_ou_id, $f_ou_name) = mysqli_fetch_array($t_ou)) {
        print_select_item($f_ou_name, $f_ou_id, $ou_value);
    }
    print "</select>\n";
}

function print_instance_select($db, $instance_name, $instance_value)
{
    print "<select id=\"$instance_name\" name=\"$instance_name\" >\n";
    $t_instance = mysqli_query($db, "SELECT * FROM filter_instances ORDER BY id");
    while (list($f_instance_id, $f_instance_name, $f_instance_comment) = mysqli_fetch_array($t_instance)) {
        print_select_item($f_instance_name, $f_instance_id, $instance_value);
    }
    print "</select>\n";
}

function get_subnet_description($db, $subnet_id)
{
    if (empty($subnet_id)) {
        return '';
    }
    $subnet = get_record_sql($db, 'SELECT * FROM subnets WHERE id=' . $subnet_id);
    if (empty($subnet)) {
        return '';
    }
    $result = $subnet['subnet'] . '&nbsp(' . $subnet['comment'] . ')';
    return $result;
}

function get_filter_instance_description($db, $instance_id)
{
    if (empty($instance_id)) {
        return '';
    }
    $instance = get_record_sql($db, 'SELECT * FROM filter_instances WHERE id=' . $instance_id);
    if (empty($instance)) {
        return '';
    }
    $result = $instance['name'] . '&nbsp(' . $instance['comment'] . ')';
    return $result;
}

function print_add_gw_subnets($db, $device_id, $gs_name)
{
    print "<select id=\"$gs_name\" name=\"$gs_name\" >\n";
    $t_gs = mysqli_query($db, "SELECT id,subnet,comment FROM subnets WHERE subnets.free=0 AND subnets.id NOT IN (SELECT subnet_id FROM gateway_subnets WHERE gateway_subnets.device_id=" . $device_id . ") ORDER BY subnet");
    while (list($f_gs_id, $f_gs_name, $f_gs_comment) = mysqli_fetch_array($t_gs)) {
        print_select_item($f_gs_name . '(' . $f_gs_comment . ')', $f_gs_id, 0);
    }
    print "</select>\n";
}

function print_add_gw_instances($db, $device_id, $gs_name)
{
    print "<select id=\"$gs_name\" name=\"$gs_name\" >\n";
    $t_gs = mysqli_query($db, "SELECT id,name,comment FROM filter_instances WHERE filter_instances.id NOT IN (SELECT instance_id FROM device_filter_instances WHERE device_filter_instances.device_id=" . $device_id . ") ORDER BY name");
    while (list($f_gs_id, $f_gs_name, $f_gs_comment) = mysqli_fetch_array($t_gs)) {
        print_select_item($f_gs_name . '(' . $f_gs_comment . ')', $f_gs_id, 0);
    }
    print "</select>\n";
}

function print_add_dev_interface($db, $device_id, $int_list, $int_name)
{
    print "&nbsp<select id=\"$int_name\" name=\"$int_name\" >\n";
    $t_int = get_records_sql($db, "SELECT * FROM device_l3_interfaces WHERE device_id=" . $device_id);
    $int_exists = [];
    foreach ($t_int as $interface) {
        $int_exists[$interface['snmpin']] = $interface;
    }
    foreach ($int_list as $interface) {
        if (!empty($int_exists[$interface['index']])) {
            continue;
        }
        $value = $interface['name'] . ';' . $interface['index'] . ';' . $interface['type'];
        if ($interface['type'] == 1) {
            $interface['type'] = WEB_select_item_wan;
        }
        if ($interface['type'] == 0) {
            $interface['type'] = WEB_select_item_lan;
        }
        $display_str = $interface['name'] . '&nbsp|' . $interface['ip'] . '|' . $interface['type'];
        print_select_item($display_str, $value, 0);
    }
    print "</select>\n";
}

function print_ou_set($db, $ou_name, $ou_value)
{
    print "<select id=\"$ou_name\" name=\"$ou_name\">\n";
    $t_ou = mysqli_query($db, "SELECT id,ou_name FROM OU WHERE id>=1 ORDER BY ou_name");
    while (list($f_ou_id, $f_ou_name) = mysqli_fetch_array($t_ou)) {
        print_select_item($f_ou_name, $f_ou_id, $ou_value);
    }
    print "</select>\n";
}

function print_subnet_select($db, $subnet_name, $subnet_value)
{
    print "<select id=\"$subnet_name\" name=\"$subnet_name\" >\n";
    $t_subnet = mysqli_query($db, "SELECT id,subnet FROM subnets ORDER BY ip_int_start");
    print_select_item(WEB_select_item_all_ips, 0, $subnet_value);
    while (list($f_subnet_id, $f_subnet_name) = mysqli_fetch_array($t_subnet)) {
        print_select_item($f_subnet_name, $f_subnet_id, $subnet_value);
    }
    print "</select>\n";
}

function print_acl_select($db, $acl_name, $acl_value)
{
    print "<select id=\"$acl_name\" name=\"$acl_name\" >\n";
    $t_acl = mysqli_query($db, "SELECT id,name FROM acl ORDER BY id");
    while (list($f_acl_id, $f_acl_name) = mysqli_fetch_array($t_acl)) {
        print_select_item($f_acl_name, $f_acl_id, $acl_value);
    }
    print "</select>\n";
}

function print_device_ip_select($db, $ip_name, $ip, $user_id)
{
    print "<select id=\"$ip_name\" name=\"$ip_name\">\n";
    $auth_list = get_records_sql($db, "SELECT ip FROM User_auth WHERE user_id=$user_id AND deleted=0 ORDER BY ip_int");
    foreach ($auth_list as $row) {
        print_select_item($row['ip'], $row['ip'], $ip);
    }
    print "</select>\n";
}

function print_subnet_select_office($db, $subnet_name, $subnet_value)
{
    print "<select id=\"$subnet_name\" name=\"$subnet_name\" >\n";
    $t_subnet = mysqli_query($db, "SELECT id,subnet FROM subnets WHERE office=1 ORDER BY ip_int_start");
    print_select_item(WEB_select_item_all_ips, 0, $subnet_value);
    while (list($f_subnet_id, $f_subnet_name) = mysqli_fetch_array($t_subnet)) {
        print_select_item($f_subnet_name, $f_subnet_id, $subnet_value);
    }
    print "</select>\n";
}

function print_subnet_select_office_splitted($db, $subnet_name, $subnet_value)
{
    print "<select id=\"$subnet_name\" name=\"$subnet_name\" >\n";
    $t_subnet = mysqli_query($db, "SELECT id,subnet,ip_int_start,ip_int_stop FROM subnets WHERE office=1 ORDER BY ip_int_start");
    print_select_item(WEB_select_item_all_ips, 0, $subnet_value);
    while (list($f_subnet_id, $f_subnet_name, $f_start_ip, $f_stop_ip) = mysqli_fetch_array($t_subnet)) {
        print_select_item($f_subnet_name, $f_subnet_name, $subnet_value);
        $cidr = cidrToRange($f_subnet_name);
        if ($cidr[2][1] < 24) {
            while ($f_start_ip <= $f_stop_ip) {
                print_select_item("&nbsp&nbsp-&nbsp" . long2ip($f_start_ip) . "/24", long2ip($f_start_ip) . "/24", $subnet_value);
                $f_start_ip += 256;
            }
        }
    }
    print "</select>\n";
}

function print_loglevel_select($item_name, $value)
{
    print "<select id=\"$item_name\" name=\"$item_name\">\n";
    print_select_item('Error', L_ERROR, $value);
    print_select_item('Warning', L_WARNING, $value);
    print_select_item('Info', L_INFO, $value);
    print_select_item('Verbose', L_VERBOSE, $value);
    print_select_item('Debug', L_DEBUG, $value);
    print "</select>\n";
}

function print_timeshift_select($value)
{
    print "<select id='date_shift' name='date_shift' onchange=\"updateDates()\">\n";
    print_select_item('-', '-', $value);
    print_select_item(WEB_date_shift_hour, 'h', $value);
    print_select_item(WEB_date_shift_8hour, '8h', $value);
    print_select_item(WEB_date_shift_day, 'd', $value);
    print_select_item(WEB_date_shift_month, 'm', $value);
    print "</select>\n";
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
    if (!empty($auth['comments'])) {
        $result = transliterate($auth['comments']);
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
        return;
    }
    $ou_name = get_record_sql($db, "SELECT ou_name FROM OU WHERE id=$ou_value");
    if (empty($ou_name)) {
        return;
    }
    return $ou_name['ou_name'];
}

function get_device_model($db, $model_value)
{
    if (!isset($model_value)) {
        return;
    }
    $model_name = get_record_sql($db, "SELECT model_name FROM device_models WHERE id=$model_value");
    if (empty($model_name)) {
        return;
    }
    return $model_name['model_name'];
}

function get_device_model_name($db, $model_value)
{
    if (!isset($model_value)) {
        return '';
    }
    $model_name = get_record_sql($db, "SELECT M.id,M.model_name,V.name FROM device_models M,vendors V WHERE M.vendor_id = V.id AND M.id=$model_value");
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
    $model_name = get_record_sql($db, "SELECT vendor_id FROM device_models WHERE id=$model_value");
    if (empty($model_name)) {
        return '';
    }
    return $model_name['vendor_id'];
}

function get_building($db, $building_value)
{
    if (!isset($building_value)) {
        return;
    }
    $building_name = get_record_sql($db, "SELECT name FROM building WHERE id=$building_value");
    if (empty($building_name)) {
        return;
    }
    return $building_name['name'];
}

function print_device_model_select($db, $device_model_name, $device_model_value)
{
    print "<select id=\"$device_model_name\" name=\"$device_model_name\" class=\"js-select-single\">\n";
    $t_device_model = mysqli_query($db, "SELECT M.id,M.model_name,V.name FROM device_models M,vendors V WHERE M.vendor_id = V.id ORDER BY V.name,M.model_name");
    while (list($f_device_model_id, $f_device_model_name, $f_vendor_name) = mysqli_fetch_array($t_device_model)) {
        print_select_item($f_vendor_name . " " . $f_device_model_name, $f_device_model_id, $device_model_value);
    }
    print "</select>\n";
}

function print_filter_group_select($db, $group_name, $group_value)
{
    print "<select id=\"$group_name\" name=\"$group_name\">\n";
    $t_group = mysqli_query($db, "SELECT id,group_name FROM Group_list Order by group_name");
    while (list($f_group_id, $f_group_name) = mysqli_fetch_array($t_group)) {
        print_select_item($f_group_name, $f_group_id, $group_value);
    }
    print "</select>\n";
}

function print_building_select($db, $building_name, $building_value)
{
    print "<select id=\"$building_name\" name=\"$building_name\">\n";
    print_select_item(WEB_select_item_all, 0, $building_value);
    $t_building = mysqli_query($db, "SELECT id,name FROM building Order by name");
    while (list($f_building_id, $f_building_name) = mysqli_fetch_array($t_building)) {
        print_select_item($f_building_name, $f_building_id, $building_value);
    }
    print "</select>\n";
}

function print_devmodels_select($db, $devmodel_name, $devmodel_value, $dev_filter = 'device_type<=2')
{
    print "<select id=\"$devmodel_name\" name=\"$devmodel_name\">\n";
    print_select_item(WEB_select_item_all, -1, $devmodel_value);
    $t_devmodel = mysqli_query($db, "SELECT M.id,V.name,M.model_name FROM device_models M,vendors V WHERE M.vendor_id = V.id and M.id in (SELECT device_model_id FROM devices WHERE $dev_filter) ORDER BY V.name,M.model_name");
    while (list($f_devmodel_id, $f_devmodel_vendor, $f_devmodel_name) = mysqli_fetch_array($t_devmodel)) {
        print_select_item($f_devmodel_vendor . " " . $f_devmodel_name, $f_devmodel_id, $devmodel_value);
    }
    print "</select>\n";
}

function print_devtypes_select($db, $devtype_name, $devtype_value, $mode)
{
    print "<select id=\"$devtype_name\" name=\"$devtype_name\">\n";
    print_select_item(WEB_select_item_all, -1, $devtype_value);
    $filter = '';
    if (!empty($mode)) {
        $filter = "WHERE $mode";
    }
    $t_devtype = mysqli_query($db, "SELECT id,`name." . HTML_LANG . "` FROM device_types $filter ORDER BY `name." . HTML_LANG . "`");
    while (list($f_devtype_id, $f_devtype_name) = mysqli_fetch_array($t_devtype)) {
        print_select_item($f_devtype_name, $f_devtype_id, $devtype_value);
    }
    print "</select>\n";
}

function print_devtype_select($db, $devtype_name, $devtype_value)
{
    print "<select id=\"$devtype_name\" name=\"$devtype_name\">\n";
    $t_devtype = mysqli_query($db, "SELECT id,`name." . HTML_LANG . "` FROM device_types ORDER BY `name." . HTML_LANG . "`");
    while (list($f_devtype_id, $f_devtype_name) = mysqli_fetch_array($t_devtype)) {
        print_select_item($f_devtype_name, $f_devtype_id, $devtype_value);
    }
    print "</select>\n";
}

function get_group($db, $group_value)
{
    list($group_name) = mysqli_fetch_array(mysqli_query($db, "SELECT group_name FROM Group_list WHERE id=$group_value"));
    return $group_name;
}

function get_devtype_name($db, $device_type_id)
{
    list($type_name) = mysqli_fetch_array(mysqli_query($db, "SELECT `name." . HTML_LANG . "` FROM device_types WHERE id=$device_type_id"));
    return $type_name;
}

function get_l3_interfaces($db, $device_id)
{
    $wan = '';
    $lan = '';
    $t_l3int = mysqli_query($db, "SELECT name,interface_type FROM device_l3_interfaces WHERE device_id=$device_id ORDER BY name");
    while (list($f_name, $f_type) = mysqli_fetch_array($t_l3int)) {
        if ($f_type == 0) {
            $lan = $lan . " " . $f_name;
        }
        if ($f_type == 1) {
            $wan = $wan . " " . $f_name;
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
    $l3_wan_sql = "SELECT id,name,snmpin FROM device_l3_interfaces WHERE device_id='" . $device_id . "' and interface_type=1 ORDER BY name";
    $t_l3int = get_records_sql($db, $l3_wan_sql);
    for ($i = 0; $i < count($t_l3int); ++$i) {
        $t_l3int[$i]['comment'] = '';
        if (empty($t_l3int[$i]['snmpin'])) {
            continue;
        }
        $con_sql = "SELECT * FROM `device_ports` WHERE device_id='" . $device_id . "' AND snmp_index='" . $t_l3int[$i]['snmpin'] . "'";
        $conn = get_record_sql($db, $con_sql);
        if (isset($conn) and !empty($conn['comment'])) {
            $t_l3int[$i]['comment'] = $conn['comment'];
        }
    }
    return $t_l3int;
}

function get_gw_subnets($db, $device_id)
{
    $gw_subnets_sql = 'SELECT gateway_subnets.*,subnets.subnet,subnets.comment FROM gateway_subnets LEFT JOIN subnets ON gateway_subnets.subnet_id = subnets.id WHERE gateway_subnets.device_id=' . $device_id . ' ORDER BY subnets.subnet ASC';
    $gw_subnets = get_records_sql($db, $gw_subnets_sql);
    $result = '';
    foreach ($gw_subnets as $row) {
        if (!empty($row)) {
            $result .= ' ' . $row['subnet'] . '<br>';
        }
    }
    return trim($result);
}

function print_queue_select($db, $queue_name, $queue_value)
{
    print "<select id=\"$queue_name\" name=\"$queue_name\">\n";
    $t_queue = mysqli_query($db, "SELECT id,queue_name FROM Queue_list Order by queue_name");
    while (list($f_queue_id, $f_queue_name) = mysqli_fetch_array($t_queue)) {
        print_select_item($f_queue_name, $f_queue_id, $queue_value);
    }
    print "</select>\n";
}

function get_queue($db, $queue_value)
{
    list($queue_name) = mysqli_fetch_array(mysqli_query($db, "SELECT queue_name FROM Queue_list WHERE id=$queue_value"));
    return $queue_name;
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

function print_nagios_handler_select($db,$qa_name)
{
$nagios_handler = get_records_sql($db,"SELECT DISTINCT `nagios_handler` FROM User_auth WHERE `nagios_handler` IS NOT NULL AND `nagios_handler` != '' AND  `deleted`=0");
if (!empty($nagios_handler) and count($nagios_handler)>0) {
    print "<select name=\"$qa_name\">\n";
    print_select_simple(WEB_select_item_no, '');
    foreach ($nagios_handler as $handler) {
        print_select_simple($handler['nagios_handler'],$handler['nagios_handler']);
        }
    print "</select>\n";
    } else {
    print "<input type=\"text\" name=\"$qa_name\" value=\"\" size=10/>";
    }
}

function print_dhcp_acl($db,$qa_name)
{
$dhcp_acl = get_records_sql($db,"SELECT DISTINCT `dhcp_acl` FROM User_auth WHERE `dhcp_acl` IS NOT NULL AND `dhcp_acl` != '' AND  `deleted`=0");
if (!empty($dhcp_acl) and count($dhcp_acl)>0) {
    print "<select name=\"$qa_name\">\n";
    print_select_simple(WEB_select_item_no, '');
    foreach ($dhcp_acl as $acl) {
        print_select_simple($acl['dhcp_acl'],$acl['dhcp_acl']);
        }
    print "</select>\n";
    } else {
    print "<input type=\"text\" name=\"$qa_name\" value=\"\" size=10/>";
    }
}

function print_dhcp_option_set($db,$qa_name)
{
$dhcp_option_sets = get_records_sql($db,"SELECT DISTINCT `dhcp_option_set` FROM User_auth WHERE `dhcp_option_set` IS NOT NULL AND `dhcp_option_set` != '' AND `deleted`=0");
if (!empty($dhcp_option_sets) and count($dhcp_option_sets)>0) {
    print "<select name=\"$qa_name\">\n";
    print_select_simple(WEB_select_item_no, '');
    foreach ($dhcp_option_sets as $dhcp_option_set) {
        print_select_simple($dhcp_option_set['dhcp_option_set'],$dhcp_option_set['dhcp_option_set']);
        }
    print "</select>\n";
    } else {
    print "<input type=\"text\" name=\"$qa_name\" value=\"\" size=10/>";
    }
}

function print_dhcp_acl_list($db,$qa_name,$value='')
{
$dhcp_acl = get_records_sql($db,"SELECT DISTINCT `dhcp_acl` FROM User_auth WHERE `dhcp_acl` IS NOT NULL AND `dhcp_acl` != '' AND  `deleted`=0");
if (!empty($dhcp_acl) and count($dhcp_acl)>0) {
    print "<input list=\"dhcp_acl\" id=\"$qa_name\" name=\"$qa_name\" value=\"$value\"/>";
    print "<datalist id=\"dhcp_acl\">";
    print "<option value=\"\">";
    foreach ($dhcp_acl as $acl) {
        print "<option value=\"{$acl['dhcp_acl']}\">";
        }
    print "</datalist>";
    } else {
    print "<input type=\"text\" name=\"$qa_name\" value=\"\" size=10/>";
    }
}

function print_dhcp_option_set_list($db,$qa_name,$value='')
{
$dhcp_option_sets = get_records_sql($db,"SELECT DISTINCT `dhcp_option_set` FROM User_auth WHERE `dhcp_option_set` IS NOT NULL AND `dhcp_option_set` != '' AND `deleted`=0");
if (!empty($dhcp_option_sets) and count($dhcp_option_sets)>0) {
    print "<input list=\"dhcp_option_set\" id=\"$qa_name\" name=\"$qa_name\" value=\"$value\"/>";
    print "<datalist id=\"dhcp_option_set\">";
    print "<option value=\"\">";
    foreach ($dhcp_option_sets as $dhcp_option_set) {
        print "<option value=\"{$dhcp_option_set['dhcp_option_set']}\">";
        }
    print "</datalist>";
    } else {
    print "<input type=\"text\" name=\"$qa_name\" value=\"\" size=10/>";
    }
}

function print_enabled_select($qa_name, $qa_value)
{
    print "<select id=\"$qa_name\" name=\"$qa_name\">\n";
    if (!isset($qa_value) or strlen($qa_value) == 0) {
        $qa_value = 0;
    }
    print_select_item('-', 0, $qa_value);
    print_select_item(WEB_select_item_disabled, 1, $qa_value);
    print_select_item(WEB_select_item_enabled, 2, $qa_value);
    print "</select>\n";
}

function print_rule_target_select($qa_name, $qa_value)
{
    print "<select id=\"$qa_name\" name=\"$qa_name\">\n";
    if (!isset($qa_value) or strlen($qa_value) == 0) {
        $qa_value = 0;
    }
    print_select_item('-', 0, $qa_value);
    print_select_item(WEB_rules_target_user, 1, $qa_value);
    print_select_item(WEB_rules_target_group, 2, $qa_value);
    print "</select>\n";
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
    print "<select id=\"$qa_name\" name=\"$qa_name\"  style=\"width: 100%\">\n";
    $sSQL = "SELECT id,`name` FROM `vendors` order by `name`";
    $vendors = mysqli_query($db, $sSQL);
    print_select_item(WEB_select_item_all, 0, $qa_value);
    while (list($v_id, $v_name) = mysqli_fetch_array($vendors)) {
        print_select_item($v_name, $v_id, $qa_value);
    }
    print "</select>\n";
}

function print_vendor_set($db, $qa_name, $qa_value)
{
    print "<select id=\"$qa_name\" name=\"$qa_name\" style=\"width: 100%\">\n";
    $sSQL = "SELECT id,`name` FROM `vendors` order by `name`";
    $vendors = mysqli_query($db, $sSQL);
    while (list($v_id, $v_name) = mysqli_fetch_array($vendors)) {
        print_select_item($v_name, $v_id, $qa_value);
    }
    print "</select>\n";
}

function get_vendor_name($db, $v_id)
{
    $vendor = get_record_sql($db, "SELECT * FROM `vendors` WHERE id=" . $v_id);
    if (empty($vendor)) {
        return NULL;
    }
    return $vendor['name'];
}

function get_qa($qa_value, $text = FALSE)
{
    if ($text) {
        if ($qa_value == 1) { return "Да"; }
        return "Нет";
        } else {
        if ($qa_value == 1) { return '<span style="font-size: 16px; font-weight: bold;">✓</span>'; }
        return '<span style="font-size: 16px;">✗</span>';
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
    print "<select id=\"$filter_name\" name=\"$filter_name\" class=\"js-select-single\">\n";
    if (isset($group_id)) {
        $sSQL = "SELECT id,name FROM Filter_list WHERE Filter_list.id not in (Select filter_id FROM Group_filters WHERE group_id=$group_id)";
    } else {
        $sSQL = "SELECT id,name FROM Filter_list Order by name";
    }

    $t_filters = mysqli_query($db, $sSQL);
    while (list($filter_id, $filter_name) = mysqli_fetch_array($t_filters)) {
        print_select_item($filter_name, $filter_id, 0);
    }
    print "</select>\n";
}

function get_filter($db, $filter_value)
{
    list($filter) = mysqli_fetch_array(mysqli_query($db, "SELECT name FROM Filter_list WHERE id=" . $filter_value));
    return $filter;
}

function get_login($db, $user_id)
{
    list($login) = mysqli_fetch_array(mysqli_query($db, "SELECT login FROM User_list WHERE id=$user_id"));
    return $login;
}

function get_auth_count($db, $user_id)
{
    list($count) = mysqli_fetch_array(mysqli_query($db, "SELECT count(id) FROM User_auth WHERE user_id=$user_id and deleted=0"));
    return $count;
}

function print_login_select($db, $login_name, $current_login)
{
    print "<select id=\"$login_name\" name=\"$login_name\" class=\"js-select-single\">\n";
    $t_login = mysqli_query($db, "SELECT id,login FROM User_list Order by Login");
    print_select_item('None', 0, $current_login);
    while (list($f_user_id, $f_login) = mysqli_fetch_array($t_login)) {
        print_select_item($f_login, $f_user_id, $current_login);
    }
    print "</select>\n";
}

function print_auth_select($db, $login_name, $current_auth)
{
    print "<select id=\"$login_name\" name=\"$login_name\" class=\"js-select-single\">\n";
    $t_login = mysqli_query($db, "SELECT U.login,U.fio,A.ip,A.id FROM User_list as U, User_auth as A WHERE A.user_id=U.id and A.deleted=0 and (A.id not in (select device_ports.auth_id FROM device_ports) or A.id=$current_auth) order by U.login,U.fio,A.ip");
    print_select_item('Empty', 0, $current_auth);
    while (list($f_login, $f_fio, $f_ip, $f_auth_id) = mysqli_fetch_array($t_login)) {
        print_select_item($f_login . "[" . $f_fio . "] - " . $f_ip, $f_auth_id, $current_auth);
    }
    print "</select>\n";
}

function print_auth_select_mac($db, $login_name, $current_auth)
{
    print "<select id=\"$login_name\" name=\"$login_name\" class=\"js-select-single\">\n";
    $t_login = mysqli_query($db, "SELECT U.login,U.fio,A.ip,A.mac,A.id FROM User_list as U, User_auth as A WHERE A.user_id=U.id and A.deleted=0 and (A.id not in (select device_ports.auth_id FROM device_ports) or A.id=$current_auth) order by U.login,U.fio,A.ip");

    print_select_item('Empty', 0, $current_auth);
    while (list($f_login, $f_fio, $f_ip, $f_mac, $f_auth_id) = mysqli_fetch_array($t_login)) {
        print_select_item($f_login . "[" . $f_mac . "] - " . $f_ip, $f_auth_id, $current_auth);
    }
    print "</select>\n";
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
    print "<select id=\"$field_name\" name=\"$field_name\" class=\"js-select-single\" >\n";
    if (empty($target_id)) {
        $target_id = 0;
    }
    if (empty($device_id)) {
        $device_id = 0;
    }
    $d_sql = "SELECT D.device_name, DP.port, DP.device_id, DP.id, DP.ifName FROM devices AS D, device_ports AS DP WHERE D.deleted=0 and D.id = DP.device_id AND (DP.device_id<>$device_id or DP.id=$target_id) and (DP.id not in (select target_port_id FROM device_ports WHERE target_port_id>0 and target_port_id<>$target_id)) ORDER BY D.device_name,DP.port";
    $t_device = mysqli_query($db, $d_sql);
    print_select_item('Empty', 0, $target_id);
    while (list($f_name, $f_port, $f_device_id, $f_target_id, $f_ifname) = mysqli_fetch_array($t_device)) {
        if (empty($f_ifname)) {
            $f_ifname = $f_port;
        }
        print_select_item($f_name . "[" . $f_port . "] - " . compact_port_name($f_ifname), $f_target_id, $target_id);
    }
    print "</select>\n";
}

function print_device_select($db, $field_name, $device_id)
{
    print "<select id=\"$field_name\" name=\"$field_name\" class=\"js-select-single\" >\n";
    $d_sql = "SELECT D.device_name, D.id FROM devices AS D Where D.deleted=0 order by D.device_name ASC";
    $t_device = mysqli_query($db, $d_sql);
    print_select_item(WEB_select_item_every, 0, $device_id);
    while (list($f_name, $f_device_id) = mysqli_fetch_array($t_device)) {
        print_select_item($f_name, $f_device_id, $device_id);
    }
    print "</select>\n";
}

function print_netdevice_select($db, $field_name, $device_id)
{
    print "<select id=\"$field_name\" name=\"$field_name\" class=\"js-select-single\" >\n";
    $d_sql = "SELECT D.device_name, D.id FROM devices AS D Where D.deleted=0 and D.device_type<=2 order by D.device_name ASC";
    $t_device = mysqli_query($db, $d_sql);
    print_select_item(WEB_select_item_every, 0, $device_id);
    while (list($f_name, $f_device_id) = mysqli_fetch_array($t_device)) {
        print_select_item($f_name, $f_device_id, $device_id);
    }
    print "</select>\n";
}

function print_vlan_select($db, $field_name, $vlan)
{
    print "<select id=\"$field_name\" name=\"$field_name\" class=\"js-select-single\" style=\"width: 100px;\" >\n";
    $d_sql = "SELECT DISTINCT vlan FROM device_ports ORDER BY vlan DESC";
    $v_device = mysqli_query($db, $d_sql);
    if (!isset($vlan) or empty($vlan)) {
        $vlan = 1;
    };
    print_select_item('1', 1, $vlan);
    while (list($f_vlan) = mysqli_fetch_array($v_device)) {
        if ($f_vlan === '1') {
            continue;
        }
        print_select_item($f_vlan, $f_vlan, $vlan);
    }
    print "</select>\n";
}

function print_device_select_ip($db, $field_name, $device_ip)
{
    print "<select id=\"$field_name\" name=\"$field_name\" class=\"js-select-single\" >\n";
    $d_sql = "SELECT D.device_name, D.ip FROM devices AS D Where D.deleted=0 order by D.device_name ASC";
    $t_device = mysqli_query($db, $d_sql);
    print_select_item(WEB_select_item_every, '', $device_ip);
    while (list($f_name, $f_device_ip) = mysqli_fetch_array($t_device)) {
        print_select_item($f_name, $f_device_ip, $device_ip);
    }
    print "</select>\n";
}

function print_syslog_device_select($db, $field_name, $syslog_filter, $device_ip)
{
    print "<select id=\"$field_name\" name=\"$field_name\" class=\"js-select-single\" >\n";
    $d_sql = "SELECT R.ip, D.device_name FROM (SELECT DISTINCT ip FROM remote_syslog WHERE $syslog_filter) AS R LEFT JOIN (SELECT ip, device_name FROM devices WHERE deleted=0) AS D ON R.ip=D.ip ORDER BY R.ip ASC";
    $t_device = mysqli_query($db, $d_sql);
    print_select_item(WEB_select_item_every, '', $device_ip);
    while (list($f_ip, $f_name) = mysqli_fetch_array($t_device)) {
        if (!isset($f_name) or empty($f_name)) {
            $f_name = $f_ip;
        }
        print_select_item($f_name, $f_ip, $device_ip);
    }
    print "</select>\n";
}

function print_gateway_select($db, $field_name, $device_id)
{
    print "<select id=\"$field_name\" name=\"$field_name\" >\n";
    $d_sql = "SELECT D.device_name, D.id FROM devices AS D Where D.deleted=0 and D.device_type=2 order by D.device_name ASC";
    $t_device = mysqli_query($db, $d_sql);
    print_select_item(WEB_select_item_every, 0, $device_id);
    while (list($f_name, $f_device_id) = mysqli_fetch_array($t_device)) {
        print_select_item($f_name, $f_device_id, $device_id);
    }
    print "</select>\n";
}

function get_gateways($db)
{
    $d_sql = "SELECT D.device_name, D.id FROM devices AS D Where D.deleted=0 and D.device_type=2 order by D.device_name ASC";
    $t_device = mysqli_query($db, $d_sql);
    unset($result);
    while (list($f_name, $f_device_id) = mysqli_fetch_array($t_device)) {
        $result[$f_device_id] = $f_name;
    }
    return $result;
}

function print_device_port($db, $target_id)
{
    $d_sql = "SELECT D.device_name, DP.port, DP.device_id FROM devices AS D, device_ports AS DP WHERE D.id = DP.device_id AND DP.id=$target_id and D.deleted=0";
    $t_device = mysqli_query($db, $d_sql);
    while (list($f_name, $f_port, $f_device_id) = mysqli_fetch_array($t_device)) {
        print "<a href=\"/admin/devices/switchport.php?id=$f_device_id\">" . $f_name . "[" . $f_port . "]</a>\n";
    }
}

function get_device_ips($db, $device_id)
{
    $switch = get_record($db, 'devices', 'id=' . $device_id);
    $index = 0;
    if (!empty($switch['user_id'])) {
        $auth_ips = get_records($db, 'User_auth', 'deleted=0 and user_id=' . $switch['user_id']);
        foreach ($auth_ips as $key => $value) {
            if (isset($value['ip'])) {
                $result[$index] = $value['ip'];
                $index++;
            }
        }
    } else {
        if (isset($switch['ip'])) {
            $result[$index] = $switch['ip'];
            $index++;
        }
    }
    return $result;
}

function get_device_id($db, $device_name)
{
    $d_sql = "SELECT id FROM devices WHERE device_name='$device_name' and deleted=0";
    $dev = get_record_sql($db, $d_sql);
    if (empty($dev)) {
        return NULL;
    }
    return $dev["id"];
}

function get_device_name($db, $device_id)
{
    $d_sql = "SELECT device_name FROM devices WHERE id='$device_id'";
    $dev = get_record_sql($db, $d_sql);
    if (empty($dev)) {
        return NULL;
    }
    return $dev["device_name"];
}

function get_auth_by_ip($db, $ip)
{
    $d_sql = "SELECT id FROM User_auth WHERE ip='$ip' and deleted=0";
    $auth = get_record_sql($db, $d_sql);
    if (empty($auth)) {
        return NULL;
    }
    return $auth["id"];
}

function get_user_by_ip($db, $ip)
{
    $d_sql = "SELECT user_id FROM User_auth WHERE ip='$ip' and deleted=0";
    $auth = get_record_sql($db, $d_sql);
    if (empty($auth)) {
        return NULL;
    }
    return $auth["user_id"];
}

function get_device_by_auth($db, $id)
{
    $d_sql = "SELECT id FROM devices WHERE user_id=$id and deleted=0";
    $f_dev = get_record_sql($db, $d_sql);
    if (empty($f_dev)) {
        return NULL;
    }
    return $f_dev['id'];
}

function print_auth_port($db, $port_id, $new_window = FALSE)
{
    $d_sql = "SELECT A.ip, A.ip_int, A.mac, A.id, A.dns_name, A.user_id FROM User_auth as A, connections as C WHERE C.port_id=$port_id and A.id=C.auth_id and A.deleted=0 order by A.ip_int";
    $t_auth = mysqli_query($db, $d_sql);
    while (list($f_ip, $f_int, $f_mac, $f_auth_id, $f_dns, $f_user_id) = mysqli_fetch_array($t_auth)) {
        $name = $f_ip;
        if (!empty($f_dns)) {
            $name = $f_dns;
        }
        $title = get_login($db, $f_user_id) . " =>" . $f_ip . "[" . $f_mac . "]";
        if (!empty($f_dns)) {
            $title .= " | " . $f_dns;
        }
        if ($new_window) {
            print "<a href=\"\" title=\"" . $title . "\" onclick=\"" . open_window_url("/admin/users/editauth.php?id=" . $f_auth_id) . " return false;\">" . $name . " [" . $f_ip . "]</a><br>";
        } else {
            print "<a href=/admin/users/editauth.php?id=" . $f_auth_id . " title=\"" . $title . "\" >" . $name . " [" . $f_ip . "]</a><br>";
        }
    }
}

function get_port_comment($db, $port_id, $port_comment = '')
{
    $d_sql = "SELECT A.ip_int, A.comments FROM User_auth as A, connections as C WHERE C.port_id=$port_id and A.id=C.auth_id and A.deleted=0 order by A.ip_int";
    $t_auth = mysqli_query($db, $d_sql);
    $comment_found = 0;
    $result = '';
    while (list($f_int, $f_comment) = mysqli_fetch_array($t_auth)) {
        if (!empty($f_comment)) {
            $comment_found = 1;
        } else {
            $f_comment = '';
        }
        $result .= $f_comment . '<br>';
    }
    if (!$comment_found) {
        return $port_comment;
    }
    if (!empty($port_comment)) {
        $result .= '(' . $port_comment . ')';
    }
    return $result;
}

function print_auth_simple($db, $auth_id)
{
    $auth = get_record($db, "User_auth", "id=$auth_id");
    $name = $auth['dns_name'];
    if (empty($name)) {
        $name = $auth['comments'];
    }
    if (empty($name)) {
        $name = $auth['ip'];
    }
    print "<a href=\"/admin/users/editauth.php?id=$auth_id\">" . $name . "</a><br>";
}

function print_auth($db, $auth_id)
{
    $auth = get_record($db, "User_auth", "id=$auth_id");
    $name = $auth['dns_name'];
    if (empty($name)) {
        $name = $auth['comments'];
    } else {
        $name .= " (" . $auth['comments'] . ")";
    }
    if (empty($name)) {
        $name = $auth['ip'];
    } else {
        $name .= " [" . $auth['ip'] . "]";
    }
    print "<a href=\"/admin/users/editauth.php?id=$auth_id\">" . $name . "</a><br>";
}

function print_auth_detail($db, $auth_id)
{
    $auth = get_record($db, "User_auth", "id=$auth_id");
    $name = $auth['dns_name'];
    if (empty($name)) {
        $name = $auth['comments'];
    } else {
        $name .= " (" . $auth['comments'] . ")";
    }
    if (empty($name)) {
        $name = $auth['ip'];
    } else {
        $name .= " [" . $auth['ip'] . "]";
    }
    $name .= " last: [" . $auth['last_found'] . "] ";
    if ($auth['deleted'] == 1) {
        $name .= " <font color='red'>DELETED!!!</font>";
    }
    print "<a href=\"/admin/users/editauth.php?id=$auth_id\">" . $name . "</a><br>";
}

function get_auth_port_count($db, $port_id)
{
    $d_sql = "SELECT count(A.id) FROM User_auth as A, connections as C WHERE C.port_id=$port_id and A.id=C.auth_id and A.deleted=0";
    $t_device = mysqli_query($db, $d_sql);
    list($f_count) = mysqli_fetch_array($t_device);
    if (!isset($f_count)) {
        $f_count = 0;
    }
    return $f_count;
}

function get_connection($db, $auth_id)
{
    $d_sql = "SELECT D.device_name, DP.port FROM devices AS D, device_ports AS DP, connections AS C WHERE D.deleted=0 and D.id = DP.device_id AND DP.id = C.port_id AND C.auth_id=$auth_id";
    $t_device = mysqli_query($db, $d_sql);
    list($f_name, $f_port) = mysqli_fetch_array($t_device);
    if (isset($f_name)) {
        $result = expand_device_name($db, $f_name) . "[" . $f_port . "]";
    } else {
        $result = '';
    }
    return $result;
}

function get_connection_string($db, $auth_id)
{
    $d_sql = "SELECT D.device_name, DP.port FROM devices AS D, device_ports AS DP, connections AS C WHERE D.deleted=0 and D.id = DP.device_id AND DP.id = C.port_id AND C.auth_id=$auth_id";
    $t_device = mysqli_query($db, $d_sql);
    list($f_name, $f_port) = mysqli_fetch_array($t_device);
    if (isset($f_name)) {
        $result = $f_name . "[" . $f_port . "]";
    } else {
        $result = '';
    }
    return $result;
}

function get_port($db, $port_id)
{
    $d_sql = "SELECT D.device_name, DP.port FROM devices AS D, device_ports AS DP WHERE D.deleted=0 and D.id = DP.device_id AND DP.id = $port_id";
    $t_device = mysqli_query($db, $d_sql);
    list($f_name, $f_port) = mysqli_fetch_array($t_device);
    if (isset($f_name)) {
        $result = expand_device_name($db, $f_name) . "[" . $f_port . "]";
    } else {
        $result = '';
    }
    return $result;
}

function print_option_select($db, $option_name)
{
    print "<select id=\"$option_name\" name=\"$option_name\">\n";
    $t_option = mysqli_query($db, "SELECT id,option_name FROM config_options WHERE uniq=0 AND draft=0 order by option_name");
    while (list($f_id, $f_name) = mysqli_fetch_array($t_option)) {
        print "<option value=$f_id>$f_name</option>";
    }
    $t_option = mysqli_query($db, "SELECT id,option_name FROM config_options WHERE draft=0 AND uniq=1 AND id NOT IN (select option_id FROM config where draft=0) order by option_name");
    while (list($f_id, $f_name) = mysqli_fetch_array($t_option)) {
        print "<option value=$f_id>$f_name</option>";
    }
    print "</select>\n";
}

function ResolveIP($db, $ip_int)
{
    $ip_name = "-";
    if (empty($ip_int)) {
        return $ip_name;
    }
    $dns_cache = get_record_sql($db, "SELECT * FROM dns_cache WHERE ip=$ip_int");
    if (empty($dns_cache) or empty($dns_cache['dns'])) {
        $ip_name = gethostbyaddr(long2ip($ip_int));
        if (empty($ip_name) or $ip_name == long2ip($ip_int)) {
            $ip_name = "-";
        }
        run_sql($db, "INSERT INTO dns_cache(dns,ip) VALUES('" . $ip_name . "'," . $ip_int . ")");
    } else {
        $ip_name = $dns_cache['dns'];
    }
    return $ip_name;
}

function clean_dns_cache($db)
{
    $date = time();
    $date = $date - 86400;
    $date_clean = DateTimeImmutable::createFromFormat('U', $date);
    $clean_date = $date_clean->format('Y-m-d H:i:s');
    run_sql($db, "DELETE FROM dns_cache WHERE `timestamp`<='" . $clean_date . "'");
}

function clean_unreferensed_rules($db)
{
    run_sql($db, "DELETE FROM `auth_rules` WHERE user_id NOT IN (SELECT id FROM User_list)");
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
        return;
    }
    $ip_aton = ip2long($ip);
    $user_subnet = get_record_sql($db, "SELECT * FROM `subnets` WHERE hotspot=1 or office=1 and ( $ip_aton >= ip_int_start and $ip_aton <= ip_int_stop)");
    if (empty($user_subnet)) {
        return;
    }
    return $user_subnet;
}

function find_mac_in_subnet($db, $ip, $mac)
{
    if (empty($ip)) {
        return;
    }
    if (empty($mac)) {
        return;
    }
    $ip_subnet = get_ip_subnet($db, $ip);
    if (empty($ip_subnet)) {
        return;
    }
    $t_auth = get_records_sql($db, "SELECT id,mac,user_id FROM User_auth WHERE ip_int>=" . $ip_subnet['ip_int_start'] . " and ip_int<=" . $ip_subnet['ip_int_stop'] . " and mac='" . $mac . "' and deleted=0 ORDER BY id");
    $auth_count = 0;
    $result['count'] = 0;
    $result['users_id'] = [];
    foreach ($t_auth as $row) {
        if (!empty($row['id'])) {
            $auth_count++;
            $result['count'] = $auth_count;
            $result[$auth_count] = $row['id'];
            array_push($result['users_id'], $row['user_id']);
        }
    }
    return $result;
}

function apply_auth_rule($db, $auth_record, $user_id)
{
    if (empty($auth_record)) {
        return;
    }
    if (empty($user_id)) {
        return $auth_record;
    }

    $user_rec = get_record($db, 'User_list', "id=" . $user_id);
    if (empty($user_rec)) {
        return $auth_record;
    }

    //set filter and status by user
    $auth_record['ou_id'] = $user_rec['ou_id'];
    $auth_record['user_id'] = $user_rec['id'];
    $auth_record['filter_group_id'] = $user_rec['filter_group_id'];
    $auth_record['queue_id'] = $user_rec['queue_id'];
    $auth_record['enabled'] = $user_rec['enabled'];
    $auth_record['changed'] = 1;
    //maybe fill comments?
    if (!empty($user_rec['fio']) and empty($auth_record['comments'])) {
        $auth_record['comments'] = $user_rec['fio'];
    }

    return $auth_record;
}

function fix_auth_rules($db)
{
    //cleanup hotspot subnet rules
    $t_hotspot = get_records_sql($db, "SELECT * FROM `OU` WHERE default_users=1 or default_hotspot=1");
    if (!empty($t_hotspot)) {
        foreach ($t_hotspot as $row) {
            delete_record($db, "auth_rules", "ou_id='" . $row['id'] . "'");
        }
    }
    $t_hotspot = get_records_sql($db, "SELECT * FROM subnets WHERE hotspot=1");
    if (!empty($t_hotspot)) {
        foreach ($t_hotspot as $row) {
            delete_record($db, "auth_rules", "rule='" . $row['subnet'] . "'");
        }
    }
}

#---------------------------------------------------------------------------------------------------------------

function new_user($db, $user_info)
{
    if (!empty($user_info['mac'])) {
        $user['login'] = mac_dotted($user_info['mac']);
    } else {
        $user['login'] = $user_info['ip'];
    }
    if (!empty($user_info['dhcp_hostname'])) {
        $user['fio'] = $user_info['ip'] . '[' . $user_info['dhcp_hostname'] . ']';
    } else {
        $user['fio'] = $user_info['ip'];
    }

    $login_count = get_count_records($db, "User_list", "(login LIKE '" . $user['login'] . "(%)') OR (login='" . $user['login'] . "')");
    if (!empty($login_count) and $login_count > 0) {
        $login_count++;
        $user['login'] = $user['login'] . "(" . $login_count . ")";
    }

    $user['ou_id'] = $user_info['ou_id'];
    $ou_info = get_record_sql($db, "SELECT * FROM OU WHERE id=" . $user_info['ou_id']);
    if (!empty($ou_info)) {
        $user['enabled'] = $ou_info['enabled'];
        if (empty($user['enabled'])) {
            $user['enabled'] = 0;
        }
        $user['queue_id'] = $ou_info['queue_id'];
        if (empty($user['queue_id'])) {
            $user['queue_id'] = 0;
        }
        $user['filter_group_id'] = $ou_info['filter_group_id'];
        if (empty($user['filter_group_id'])) {
            $user['filter_group_id'] = 0;
        }
    }

    $result = insert_record($db, "User_list", $user);
    $auto_mac_rule = get_option($db, 64);
    if (!empty($result) and $auto_mac_rule and $user_info['mac']) {
        $auth_rule['user_id'] = $result;
        $auth_rule['type'] = 2;
        $auth_rule['rule'] = mac_dotted($user_info['mac']);
        insert_record($db, "auth_rules", $auth_rule);
    }
    return $result;
}

function new_auth($db, $ip, $mac, $user_id)
{
    $ip_aton = ip2long($ip);
    $msg = '';

    if (!empty($mac)) {
        $auth_record = get_record_sql($db, "SELECT * FROM User_auth WHERE ip_int=$ip_aton AND mac='" . mac_dotted($mac) . "' AND deleted=0");
        if (!empty($auth_record)) {
            LOG_WARNING($db, "Pair ip-mac already exists! Skip creating $ip [$mac] auth_id: " . $auth_record["id"]);
            return $auth_record['id'];
        }
    }

    // save traffic detailization
    $save_traf = get_option($db, 23);
    $resurrection_id = NULL;

    // seek old auth with same ip and mac
    $resurrection_id = get_id_record($db, 'User_auth', " deleted=1 AND ip_int=" . $ip_aton . " AND mac='" . $mac . "'");
    if (!empty($resurrection_id)) {
        $msg .= "Recovered auth_id: $resurrection_id with ip: $ip and mac: $mac ";
        $auth['user_id'] = $user_id;
        $auth['deleted'] = 0;
        $auth['save_traf'] = $save_traf * 1;
        update_record($db, "User_auth", "id=$resurrection_id", $auth);
    } else {
        // not found ->create new record
        $msg .= "Create new ip record \r\nip: $ip\r\nmac: $mac\r\n";
        $auth['deleted'] = 0;
        $auth['user_id'] = $user_id;
        $auth['ip'] = $ip;
        $auth['ip_int'] = $ip_aton;
        $auth['mac'] = $mac;
        $auth['save_traf'] = $save_traf * 1;
        $resurrection_id = insert_record($db, "User_auth", $auth);
    }

    //check rules, update filter and state for new record
    if (!empty($resurrection_id)) {
        $auth = apply_auth_rule($db, $auth, $user_id);
        update_record($db, "User_auth", "id=$resurrection_id", $auth);
        if (!is_hotspot($db, $ip) and !empty($msg)) {
            LOG_WARNING($db, $msg);
        }
        if (is_hotspot($db, $ip) and !empty($msg)) {
            LOG_INFO($db, $msg);
        }
    }
    return $resurrection_id;
}

function resurrection_auth($db, $ip_record)
{
    $ip = $ip_record['ip'];
    $mac = $ip_record['mac'];
    $action = $ip_record['type'];
    $dhcp_hostname = $ip_record['hostname'];
    $hotspot_found = $ip_record['hotspot'];

    $ip_aton = ip2long($ip);

    $auth_record = get_record_sql($db, "SELECT * FROM User_auth WHERE ip_int=$ip_aton AND mac='" . $mac . "' AND deleted=0");
    if (!empty($auth_record)) {
        $user_info = get_record_sql($db, "SELECT * FROM User_list WHERE id=" . $auth_record['user_id']);
        LOG_DEBUG($db, "external dhcp user " . $user_info['login'] . " [" . $ip . "] auth_id: " . $auth_record['id']);
        if (isset($dhcp_hostname) and !empty($dhcp_hostname)) {
            $auth['dhcp_hostname'] = $dhcp_hostname;
        }
        $auth['dhcp_action'] = $action;
        $auth['dhcp_time'] = GetNowTimeString();
        if ($action === 'add') {
            $auth['last_found'] = GetNowTimeString();
        }
        update_record($db, "User_auth", "id=" . $auth_record['id'], $auth);
        return $auth_record['id'];
    }

    $ip_subnet = get_ip_subnet($db, $ip);
    if ($ip_subnet['static']) {
        LOG_WARNING($db, "Unknown pair ip+mac in static subnet! ip: $ip mac: [" . mac_dotted($mac) . "]. Skip");
        return;
    }

    $msg = '';
    // search changed mac
    $auth_record = get_record_sql($db, "SELECT * FROM User_auth WHERE ip_int=$ip_aton AND deleted=0");
    if (!empty($auth_record)) {
        if (empty($auth_record['mac'])) {
            $auth['mac'] = mac_dotted($mac);
            $auth['dhcp_action'] = $action;
            $auth['dhcp_time'] = GetNowTimeString();
            if (!empty($dhcp_hostname)) {
                $auth['dhcp_hostname'] = $dhcp_hostname;
            }
            if ($action === 'add') {
                $auth['last_found'] = GetNowTimeString();
            }
            LOG_INFO($db, "for ip: $ip mac not found! Use empty record...");
            update_record($db, "User_auth", "id=" . $auth_record['id'], $auth);
            return $auth_record['id'];
        } else {
            if (!$hotspot_found) {
                LOG_WARNING($db, "for ip: $ip mac change detected! Old mac: [" . $auth_record['mac'] . "] New mac: [" . mac_dotted($mac) . "]. Disable old auth_id: " . $auth_record['id']);
            }
            run_sql($db, "UPDATE User_auth SET changed=1, deleted=1 WHERE id=" . $auth_record['id']);
        }
    }

    // default id
    $new_user_info = get_new_user_id($db, $ip, $mac, $dhcp_hostname);
    if (!empty($new_user_info['user_id'])) {
        $new_user_id = $new_user_info['user_id'];
    }
    if (empty($new_user_id)) {
        $new_user_id = new_user($db, $new_user_info);
    }

    $resurrection_id = NULL;
    $save_traf = get_option($db, 23);

    $auth_record = get_record_sql($db, "SELECT * FROM User_auth WHERE ip_int=" . $ip_aton . " and mac='" . $mac . "'");
    // seek old auth with same ip and mac
    if (!empty($auth_record)) {
        // found ->Resurrection old record
        $resurrection_id = $auth_record['id'];
        $msg .= "Recovered auth_id: $resurrection_id with ip: $ip and mac: $mac ";
        $auth['dhcp_action'] = $action;
        $auth['user_id'] = $new_user_id;
        $auth['deleted'] = 0;
        $auth['dhcp_time'] = GetNowTimeString();
        $auth['save_traf'] = $save_traf * 1;
        if (!empty($dhcp_hostname)) {
            $auth['dhcp_hostname'] = $dhcp_hostname;
        }
        if ($action === 'add') {
            $auth['last_found'] = GetNowTimeString();
        }
        update_record($db, "User_auth", "id=$resurrection_id", $auth);
    } else {
        // not found ->create new record
        $msg .= "Создаём новый ip-адрес \r\nip: $ip\r\nmac: $mac\r\n";
        $auth['deleted'] = 0;
        $auth['user_id'] = $new_user_id;
        $auth['ip'] = $ip;
        $auth['ip_int'] = $ip_aton;
        $auth['mac'] = $mac;
        $auth['dhcp_action'] = $action;
        $auth['dhcp_time'] = GetNowTimeString();
        $auth['save_traf'] = $save_traf * 1;
        if (!empty($dhcp_hostname)) {
            $auth['dhcp_hostname'] = $dhcp_hostname;
        }
        if ($action == 'add') {
            $auth['last_found'] = GetNowTimeString();
        }
        $resurrection_id = insert_record($db, "User_auth", $auth);
    }
    //check rules, update filter and state for new record
    if (!empty($resurrection_id)) {
        $auth = apply_auth_rule($db, $auth, $new_user_id);
        update_record($db, "User_auth", "id=$resurrection_id", $auth);
        $msg .= "filter: " . $auth['filter_group_id'] . "\r\n queue_id: " . $auth['queue_id'] . "\r\n enabled: " . $auth['enabled'] . "\r\nid: $resurrection_id";
        if (!$hotspot_found and !empty($msg)) {
            LOG_WARNING($db, $msg);
        }
        if ($hotspot_found and !empty($msg)) {
            LOG_INFO($db, $msg);
        }
    }

    return $resurrection_id;
}

function get_auth($db, $current_auth)
{
    if (!isset($current_auth)) {
        return;
    }
    if ($current_auth == 0) {
        return;
    }
    $t_login = mysqli_query($db, "SELECT U.login,A.ip FROM User_list as U, User_auth as A WHERE A.user_id=U.id and A.id=$current_auth");
    list($f_login, $f_ip) = mysqli_fetch_array($t_login);
    $result = $f_login . "[" . $f_ip . "]";
    return $result;
}

function get_auth_by_mac($db, $mac)
{
    if (!isset($mac)) {
        return;
    }
    $mac = mac_dotted($mac);
    $t_login = mysqli_query($db, "SELECT U.id,U.login,A.id,A.ip FROM User_list as U, User_auth as A WHERE A.user_id=U.id and A.mac='" . $mac . "' and A.deleted=0 ORDER BY A.last_found DESC limit 1");
    list($f_id, $f_login, $f_auth_id, $f_ip) = mysqli_fetch_array($t_login);
    if (isset($f_id)) {
        $result['auth'] = '<a href=/admin/users/edituser.php?id=' . $f_id . '>' . $f_login . '</a> / ip: <a href=/admin/users/editauth.php?id=' . $f_auth_id . '>' . $f_ip . '</a>';
    } else {
        $result['auth'] = 'Unknown';
    }
    $result['mac'] = expand_mac($db, $mac);
    return $result;
}

function get_auth_mac($db, $current_auth)
{
    if (!isset($current_auth)) {
        return;
    }
    if ($current_auth == 0) {
        return;
    }
    $t_login = mysqli_query($db, "SELECT U.login,A.mac FROM User_list as U, User_auth as A WHERE A.user_id=U.id and A.id=$current_auth");
    list($f_login, $f_mac) = mysqli_fetch_array($t_login);
    $result = $f_login . "[" . $f_mac . "]";
    return $result;
}

function add_auth_rule($db, $rule, $type, $user_id)
{
    $new['user_id'] = $user_id;
    $new['type'] = $type;
    $new['rule'] = $rule;
    $rule_id = 0;
    $auth_rules = get_record_sql($db, "SELECT * FROM auth_rules WHERE rule='" . $rule . "' AND type=" . $type);
    if (empty($auth_rules)) {
        $rule_id = insert_record($db, "auth_rules", $new);
        LOG_INFO($db, "Create auto rule for user_id: " . $user_id . " rule: " . $rule . " type: " . $type);
    } else {
        if ($auth_rules['user_id'] !== $user_id) {
            LOG_WARNING($db, "Create auto rule for user_id: " . $user_id . " rule: " . $rule . " type: " . $type . " failed! Already exists at user_id: " . $auth_rules['user_id']);
            $rule_id = 0;
            } else { $rule_id =  $auth_rules['id']; }
    }
    return $rule_id;
}

function update_auth_rule($db, $new, $rule_id = 0)
{
    $type = $new['type'];
    $rule = $new['rule'];
    $auth_rules = get_record_sql($db, "SELECT * FROM auth_rules WHERE rule='" . $rule . "' AND type=" . $type . " AND id<>" . $rule_id);
    if (empty($auth_rules)) {
        $rule_id = update_record($db, "auth_rules", "id=" . $rule_id, $new);
    } else {
        LOG_WARNING($db, "Create auto rule id: " . $rule_id . " rule: " . $rule . " type: " . $type . " failed! Already exists at user_id: " . $auth_rules['user_id']);
        $rule_id = 0;
    }
    return $rule_id;
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
    // Безопасное получение данных сессии
    $currentIp = filter_var($_SESSION['ip'] ?? '127.0.0.1', FILTER_VALIDATE_IP) ?: '127.0.0.1';
    $currentLogin = htmlspecialchars($_SESSION['login'] ?? 'http', ENT_QUOTES, 'UTF-8');
    if (!isset($msg)) { return; }
    // Для уровня L_DEBUG пишем в error_log
    if ($level === L_DEBUG) {
        error_log("DEBUG: " . $msg);
//        return;
    }
    // пишем в БД
    $stmt = mysqli_prepare($db, "INSERT INTO worklog(customer, message, level, auth_id, ip) VALUES (?, ?, ?, ?, ?)");
    mysqli_stmt_bind_param($stmt, 'ssiis', $currentLogin, $msg, $level, $auth_id, $currentIp);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
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
    $dev = get_record_sql($db, 'SELECT discovery_locked,UNIX_TIMESTAMP(locked_timestamp) as u_locked_timestamp FROM devices WHERE id=' . $device_id . ' AND discovery_locked > 0');
    if (empty($dev) or empty($dev['u_locked_timestamp'])) {
        LOG_DEBUG($db, "Snmp discovery lock not found. Set and discovery.");
        return set_lock_discovery($db, $device_id);
    }
    //wait for discovery
    $now = time();
    $wait_time = ($dev['u_locked_timestamp'] + SNMP_LOCK_TIMEOUT) - $now;
    LOG_DEBUG($db, "Check snmp lock for device id: " . $device_id . ". Lock timestamp: " . $dev['u_locked_timestamp'] . ", now: " . $now);
    if ($wait_time < 0) {
        LOG_DEBUG($db, "The lock is already expired. Set new lock.");
        return set_lock_discovery($db, $device_id);
    }
    LOG_VERBOSE($db, "Snmp discovery lock for device id: $device_id found! Need wait " . $wait_time . " sec.");
    sleep($wait_time);
    LOG_VERBOSE($db, "Try set new lock and continue discovery for device id:" . $device_id);
    return apply_device_lock($db, $device_id, $iteration);
}

function set_lock_discovery($db, $device_id)
{
    $new['discovery_locked'] = 1;
    $new['locked_timestamp'] = GetNowTimeString();
    if (update_record($db, 'devices', 'id=' . $device_id, $new)) {
        return true;
    }
    return false;
}

function unset_lock_discovery($db, $device_id)
{
    $new['discovery_locked'] = 0;
    $new['locked_timestamp'] = GetNowTimeString();
    if (update_record($db, 'devices', 'id=' . $device_id, $new)) {
        return true;
    }
    return false;
}

function set_port_for_group($db, $group_id, $place_id, $state)
{
    $authSQL = 'SELECT User_auth.id,User_auth.dns_name,User_auth.ip FROM User_auth, User_list WHERE User_auth.user_id = User_list.id AND User_auth.deleted=0 and User_list.ou_id=' . $group_id;
    $auth_list = mysqli_query($db, $authSQL);
    LOG_VERBOSE($db, 'Mass port state change started!');
    // get auth list for group
    while (list($a_id, $a_name, $a_ip) = mysqli_fetch_array($auth_list)) {
        // get device and port for auth
        if ($place_id == 0) {
            $place_filter = '';
        } else {
            $place_filter = 'D.building_id=' . $place_id . ' and ';
        }
        $devSQL = 'SELECT D.id, D.device_name, D.vendor_id, D.device_model, D.ip, DP.port, DP.snmp_index  FROM devices AS D, device_ports AS DP, connections AS C WHERE ' . $place_filter . ' D.id = DP.device_id AND DP.id = C.port_id AND C.auth_id=' . $a_id . ' LIMIT 1';
        $dev_info = mysqli_query($db, $devSQL);
        list($d_id, $d_name, $d_vendor_id, $d_model, $d_ip, $d_port, $d_snmp_index) = mysqli_fetch_array($dev_info);
        
        if (!isset($d_id)) {
            continue;
        }
        
        $device=get_record($db,'devices',"id=".$d_id);
        $snmp = getSnmpAccess($device);

        if ($state == 1) {
            $mode = 'enable';
            run_sql($db, "Update User_auth set nagios_handler='restart-port' WHERE id=$a_id and nagios_handler='manual-mode'");
        } else {
            $mode = 'disable';
            run_sql($db, "Update User_auth set nagios_handler='manual-mode' WHERE id=$a_id and nagios_handler='restart-port'");
        }
        LOG_INFO($db, "At device $d_name [$d_ip] $mode port $d_port for auth_id: $a_id ($a_ip [$a_name])");
        set_port_state($d_vendor_id, $d_snmp_index, $d_ip, $snmp, $state);
        set_port_poe_state($d_vendor_id, $d_port, $d_snmp_index, $d_ip, $snmp, $state);
    }
    LOG_VERBOSE($db, 'Mass port state change stopped.');
}

function get_vendor($db, $mac)
{
    $mac = mac_dotted($mac);
    $mac5 = substr($mac, 0, 14);
    $mac4 = substr($mac, 0, 11);
    $mac3 = substr($mac, 0, 8);
    $vendor = get_record_sql($db, 'SELECT companyName,companyAddress FROM mac_vendors WHERE oui="' . $mac . '"');
    if (empty($vendor)) {
        $vendor = get_record_sql($db, 'SELECT companyName,companyAddress FROM mac_vendors WHERE oui="' . $mac5 . '"');
    }
    if (empty($vendor)) {
        $vendor = get_record_sql($db, 'SELECT companyName,companyAddress FROM mac_vendors WHERE oui="' . $mac4 . '"');
    }
    if (empty($vendor)) {
        $vendor = get_record_sql($db, 'SELECT companyName,companyAddress FROM mac_vendors WHERE oui="' . $mac3 . '"');
    }
    $result = '';
    if (!empty($vendor)) {
        $result = $vendor['companyName'];
        if (!empty($vendor['companyAddress'])) {
            $result = $vendor['companyAddress'];
        }
    }
    return $result;
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
    $target = mysqli_query($db, "SELECT U.target_port_id,U.id FROM device_ports U WHERE U.device_id=$device_id");
    while (list($target_id, $id) = mysqli_fetch_array($target)) {
        run_sql($db, "UPDATE device_ports SET target_port_id=0 WHERE target_port_id=" . $id);
        run_sql($db, "UPDATE device_ports SET target_port_id=0 WHERE id=" . $id);
    }
}

function bind_ports($db, $port_id, $target_id)
{
    $old_target = mysqli_query($db, "SELECT U.target_port_id FROM device_ports U WHERE U.id=$port_id");
    list($old_target_id) = mysqli_fetch_array($old_target);
    // unbind current connection
    $new['target_port_id'] = 0;
    update_record($db, "device_ports", "id='$port_id'", $new);
    if (isset($old_target_id)) {
        update_record($db, "device_ports", "id='$old_target_id'", $new);
    }
    // new link
    if (isset($target_id) and $target_id > 0) {
        $new['target_port_id'] = $target_id;
        update_record($db, "device_ports", "id='$port_id'", $new);
        $new['target_port_id'] = $port_id;
        update_record($db, "device_ports", "id='$target_id'", $new);
    }
}

function expand_device_name($db, $name)
{
    $device_id = get_device_id($db, $name);
    $result = $name;
    if (isset($device_id) and $device_id > 0) {
        $result = '<a href=/admin/devices/editdevice.php?id=' . $device_id . '>' . $name . '</a>';
    }
    return $result;
}

function expand_mac($db, $msg)
{
    if (!isset($msg)) {
        return;
    }
    $mac = mac_dotted($msg);
    $vendor_info = get_vendor($db, $mac);
    if (!empty($vendor_info)) {
//        $result = '<p title="' . $vendor_info . '"><a href=/admin/logs/mac.php?mac=' . $mac . '>' . $mac . '</a></p>';
        $result = '<a href=/admin/logs/mac.php?mac=' . $mac . '><p title="' . $vendor_info . '">'. $mac . '</p></a>';
        } else {
        $result = '<a href=/admin/logs/mac.php?mac=' . $mac . '>' . $mac . '</a>';
        }
    return $result;
}

function expand_log_str($db, $msg)
{
    if (!isset($msg)) {
        return;
    }

    $auth_pattern = '/(auth_id:|auth|auth id:|auth id)\s+(\d+)\s+/i';
    $auth_replace = '<a href=/admin/users/editauth.php?id=${2}>auth_id:${2}</a> ';
    $result = preg_replace($auth_pattern, $auth_replace, $msg);

    $user_pattern = '/(user_id:|user|user id:|user id)\s+(\d+)\s+/i';
    $user_replace = '<a href=/admin/users/edituser.php?id=${2}>user_id:${2}</a> ';
    $result = preg_replace($user_pattern, $user_replace, $result);

    $mac_pattern = '/\s+\[(\w{12})\]\s+/i';
    preg_match($mac_pattern, $result, $matches);
    if (isset($matches[1])) {
        $mac = $matches[1];
        $mac = mac_dotted($mac);
        //        $vendor_info = get_vendor($db,$mac);
        //        $mac_replace = ' <p title="'.$vendor_info.'"><a href=/admin/logs/mac.php?mac='.$mac.'>'.$mac.'</a></p>';
        $mac_replace = ' <a href=/admin/logs/mac.php?mac=' . $mac . '>' . $mac . '</a> ';
        $result = preg_replace($mac_pattern, $mac_replace, $result);
    }

    $mac_pattern = '/\s+mac:\s+([\w\:]{17})$/i';
    preg_match($mac_pattern, $result, $matches);
    if (isset($matches[1])) {
        $mac = $matches[1];
        $mac = mac_dotted($mac);
        //        $vendor_info = get_vendor($db,$mac);
        //        $mac_replace = ' mac: <p title="'.$vendor_info.'"><a href=/admin/logs/mac.php?mac='.$mac.'>'.$mac.'</a></p>';
        $mac_replace = ' mac: <a href=/admin/logs/mac.php?mac=' . $mac . '>' . $mac . '</a> ';
        $result = preg_replace($mac_pattern, $mac_replace, $result);
    }

    $device_pattern = '/at device\s+([\w\.\-]+)/i';
    preg_match($device_pattern, $result, $matches);
    if (isset($matches[1])) {
        $device_name = $matches[1];
        $device_id = get_device_id($db, $device_name);
        if (isset($device_id) and $device_id > 0) {
            $device_replace = 'at device <a href=/admin/devices/editdevice.php?id=' . $device_id . '>${1}</a> ';
            $result = preg_replace($device_pattern, $device_replace, $result);
        }
    }

    $device_pattern = '/(device_id:|device id:|device id|device_id)\s+(\d+)\s+/i';
    $device_replace = 'device_id: <a href=/admin/devices/editdevice.php?id=${2}>${2}</a> ';
    $result = preg_replace($device_pattern, $device_replace, $result);

    $ip_pattern = '/\s+ip\:\s+(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})\s+/i';
    preg_match($ip_pattern, $result, $matches);
    if (isset($matches[1])) {
        $ip = $matches[1];
        $auth_id = get_auth_by_ip($db, $ip);
        if (isset($auth_id) and $auth_id > 0) {
            $auth_replace = ' ip: <a href=/admin/users/editauth.php?id=' . $auth_id . '>${1}</a> ';
            $result = preg_replace($ip_pattern, $auth_replace, $result);
        }
    }
    return $result;
}

function is_auth_bind_changed($db, $id, $ip, $mac)
{
    $old_sql = "SELECT ip,mac FROM User_auth WHERE id=$id";
    $old_record = get_record_sql($db, $old_sql);
    if (empty($old_record["ip"]) or empty($old_record["mac"])) {
        return 0;
    }
    if ($old_record["ip"] !== $ip or $old_record["mac"] !== $mac) {
        LOG_VERBOSE($db, "Changed ip or mac for auth record!");
        return 1;
    }
    return 0;
}

function copy_auth($db, $id, $new_auth)
{
    $old_record = get_record_sql($db, "SELECT * FROM User_auth WHERE id=$id");
    delete_record($db, "User_auth", "id=" . $id);
    $new_auth["user_id"] = $old_record["user_id"];
    $new_auth["changed"] = 1;
    $changed_time = GetNowTimeString();
    $new_auth["changed_time"] = $changed_time;
    $new_id = insert_record($db, "User_auth", $new_auth);
    LOG_VERBOSE($db, "Old record with id: $id deleted. Created new auth record for new ip+mac id: $new_id!");
    return $new_id;
}

function get_dns_name($db, $id)
{
    $auth_record = get_record_sql($db, "SELECT dns_name FROM User_auth WHERE id=" . $id);
    if (!empty($auth_record) and !empty($auth_record['dns_name'])) {
        return $auth_record['dns_name'];
    }
    return '';
}

function get_cacti_graph($host_ip, $port_index)
{

    if (empty(get_const('cacti_url'))) {
        return;
    }
    if (CACTI_DB_HOST == null or CACTI_DB_USER == null or CACTI_DB_PASS == null or CACTI_DB_NAME == null) {
        return;
    }
    if (empty(CACTI_DB_HOST) or empty(CACTI_DB_USER) or empty(CACTI_DB_PASS) or empty(CACTI_DB_NAME)) {
        return;
    }

    $cacti_db_link = new_connection(CACTI_DB_HOST, CACTI_DB_USER, CACTI_DB_PASS, CACTI_DB_NAME);
    if (!$cacti_db_link) {
        return FALSE;
    }

    $host_sql = 'SELECT * FROM host WHERE hostname="' . $host_ip . '"';
    $cacti_host = get_record_sql($cacti_db_link, $host_sql);
    $host_id = $cacti_host["id"];
    if (empty($host_id)) {
        return;
    }

    $graph_sql = 'SELECT * FROM graph_local WHERE host_id="' . $host_id . '" and snmp_index="' . $port_index . '" and graph_template_id IN (SELECT id FROM graph_templates WHERE name LIKE "Interface - Traffic%") ORDER BY id ASC';
    $cacti_graph = get_record_sql($cacti_db_link, $graph_sql);
    $graph_id = $cacti_graph["id"];

    if (empty($graph_id)) {
        return;
    }
    $result = get_const('cacti_url') . "/graph_image.php?local_graph_id=" . $graph_id;
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
    $option = get_record($db, "config", "option_id=" . $option_id);
    if (empty($option) or empty($option['value'])) {
        $default = get_record($db, "config_options", "id=$option_id");
        return $default['default_value'];
    }
    return $option['value'];
}

function is_option($db, $option_id)
{
    $option = get_record($db, "config", "option_id=" . $option_id);
    if (empty($option) or empty($option['value'])) {
        return;
    }
    return 1;
}

function set_option($db, $option_id, $value)
{
    $option['value'] = $value;
    update_record($db, 'config', "option_id=$option_id", $option);
}

function is_subnet_aton($subnet, $ip)
{
    if (!isset($subnet)) {
        return 0;
    }
    if (!isset($ip)) {
        return 0;
    }
    $range = cidrToRange($subnet);
    if ($ip >= ip2long($range[0]) and $ip <= ip2long($range[1])) {
        return 1;
    }
    return 0;
}

function get_new_user_id($db, $ip, $mac, $hostname)
{

    $result['ip'] = $ip;
    $result['mac'] = mac_dotted($mac);
    $result['hostname'] = $hostname;
    $result['user_id'] = NULL;
    $result['ou_id'] = NULL;
    $ip_aton = ip2long($ip);

    if (is_hotspot($db, $ip)) {
            $result['ou_id'] = get_const('default_hotspot_ou_id');
            return $result;
        }

    //personal user rules
    //ip
    if (!empty($ip)) {
        $t_rules = get_records_sql($db, "SELECT * FROM auth_rules WHERE type=1 and LENGTH(rule)>0 AND user_id IS NOT NULL");
        foreach ($t_rules as $row) {
            if (!empty($row['rule']) and is_subnet_aton($row['rule'], $ip_aton)) {
                $result['user_id'] = $row['user_id'];
                return $result;
            }
        }
    }
    //mac
    if (!empty($mac)) {
        $mac_rules = get_records_sql($db, "SELECT * FROM auth_rules WHERE type=2 AND LENGTH(rule)>0 AND user_id IS NOT NULL");
        foreach ($mac_rules as $row) {
            $pattern = '/' . mac_simplify($row['rule']) . '/';
            if (!empty($row['rule']) and preg_match($pattern, mac_simplify($mac))) {
                $result['user_id'] = $row['user_id'];
                return $result;
            }
        }
    }
    //hostname
    if (!empty($hostname)) {
        $mac_rules = get_records_sql($db, "SELECT * FROM auth_rules WHERE type=3 AND LENGTH(rule)>0 AND user_id IS NOT NULL");
        foreach ($mac_rules as $row) {
            if (!empty($row['rule']) and preg_match($row['rule'], $hostname)) {
                $result['user_id'] = $row['user_id'];
                return $result;
            }
        }
    }

    //ou rules
    //ip
    if (!empty($ip)) {
        $t_rules = get_records_sql($db, "SELECT * FROM auth_rules WHERE type=1 and LENGTH(rule)>0 AND ou_id IS NOT NULL");
        foreach ($t_rules as $row) {
            if (!empty($row['rule']) and is_subnet_aton($row['rule'], $ip_aton)) {
                $result['ou_id'] = $row['ou_id'];
                return $result;
            }
        }
    }
    //mac
    if (!empty($mac)) {
        $mac_rules = get_records_sql($db, "SELECT * FROM auth_rules WHERE type=2 AND LENGTH(rule)>0 AND ou_id IS NOT NULL");
        foreach ($mac_rules as $row) {
            $pattern = '/' . mac_simplify($row['rule']) . '/';
            if (!empty($row['rule']) and preg_match($pattern, mac_simplify($mac))) {
                $result['ou_id'] = $row['ou_id'];
                return $result;
            }
        }
    }
    //hostname
    if (!empty($hostname)) {
        $mac_rules = get_records_sql($db, "SELECT * FROM auth_rules WHERE type=3 AND LENGTH(rule)>0 AND ou_id IS NOT NULL");
        foreach ($mac_rules as $row) {
            if (!empty($row['rule']) and preg_match($row['rule'], $hostname)) {
                $result['ou_id'] = $row['ou_id'];
                return $result;
            }
        }
    }

    if (empty($result['ou_id'])) {
        $result['ou_id'] = get_const('default_user_ou_id');
    }

    return $result;
}

function get_subnet_range($db, $subnet_id)
{
    if (empty($subnet_id)) {
        return;
    }
    $t_option = get_record_sql($db, "SELECT ip_int_start,ip_int_stop FROM `subnets` WHERE id=$subnet_id");
    if (!isset($t_option['ip_int_start'])) {
        $t_option['ip_int_start'] = 0;
    }
    if (!isset($t_option['ip_int_stop'])) {
        $t_option['ip_int_stop'] = 0;
    }
    $subnet['start'] = $t_option['ip_int_start'];
    $subnet['stop'] = $t_option['ip_int_stop'];
    return $subnet;
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
    if (!isset($ip)) {
        return 0;
    }
    LOG_DEBUG($db, "Check hotspot network for ip: $ip");
    $ip_aton = ip2long($ip);
    $t_option = mysqli_query($db, "SELECT subnet,ip_int_start,ip_int_stop FROM `subnets` WHERE hotspot=1");
    while (list($f_net, $f_start, $f_stop) = mysqli_fetch_array($t_option)) {
        if ($ip_aton >= $f_start and $ip_aton <= $f_stop) {
            LOG_DEBUG($db, "ip: $ip [$ip_aton] found in network $f_net: [" . $f_start . ".." . $f_stop . "]");
            return 1;
        }
    }
    LOG_DEBUG($db, "ip $ip not found in hotspot network!");
    return 0;
}

function is_office($db, $ip)
{
    if (!isset($ip)) {
        return 0;
    }
    LOG_DEBUG($db, "Check office network for ip: $ip");
    $ip_aton = ip2long($ip);
    $t_option = mysqli_query($db, "SELECT subnet,ip_int_start,ip_int_stop FROM `subnets` WHERE office=1");
    while (list($f_net, $f_start, $f_stop) = mysqli_fetch_array($t_option)) {
        if ($ip_aton >= $f_start and $ip_aton <= $f_stop) {
            LOG_DEBUG($db, "ip: $ip [$ip_aton] found in office $f_net: [" . $f_start . ".." . $f_stop . "]");
            return 1;
        }
    }
    LOG_DEBUG($db, "ip $ip not found in office network!");
    return 0;
}

function get_office_subnet($db, $ip)
{
    if (!isset($ip)) {
        return 0;
    }
    LOG_DEBUG($db, "Check office network for ip: $ip");
    $ip_aton = ip2long($ip);
    $subnets = get_records_sql($db, 'SELECT * FROM `subnets` WHERE office=1');
    foreach ($subnets as $row) {
        if ($ip_aton >= $row['ip_int_start'] and $ip_aton <= $row['ip_int_stop']) {
            LOG_DEBUG($db, "ip: $ip [$ip_aton] found in office {$row['subnet']}: [" . $row['ip_int_start'] . ".." . $row['ip_int_stop'] . "]");
            return $row;
        }
    }
    LOG_DEBUG($db, "ip $ip not found in office network!");
    return 0;
}

function get_notify_subnet($db, $ip)
{
    if (!isset($ip)) {
        return 0;
    }
    $office_subnet = get_office_subnet($db, $ip);
    if ($office_subnet) {
        return $office_subnet['notify'];
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

$ou = get_record_sql($db_link, "SELECT id FROM OU WHERE default_users = 1");
if (empty($ou)) {
    $config["default_user_ou_id"] = 0;
} else {
    $config["default_user_ou_id"] = $ou['id'];
}

$ou = get_record_sql($db_link, "SELECT id FROM OU WHERE default_hotspot=1");
if (empty($ou)) {
    $config["default_hotspot_ou_id"] = $config["default_user_ou_id"];
} else {
    $config["default_hotspot_ou_id"] = $ou['id'];
}

$config["init"] = 1;

clean_dns_cache($db_link);
//clean_unreferensed_rules($db_link);
