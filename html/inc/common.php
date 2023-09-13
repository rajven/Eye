<?php
if (!defined("CONFIG")) {
    die("Not defined");
}

require_once($_SERVER['DOCUMENT_ROOT'] . "/inc/class.simple.mail.php");

require_once($_SERVER['DOCUMENT_ROOT'] . "/inc/consts.php");

#ValidIpAddressRegex = "^(([0-9]|[1-9][0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5])\.){3}([0-9]|[1-9][0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5])$";
#ValidHostnameRegex = "^(([a-zA-Z0-9]|[a-zA-Z0-9][a-zA-Z0-9\-]*[a-zA-Z0-9])\.)*([A-Za-z0-9]|[A-Za-z0-9][A-Za-z0-9\-]*[A-Za-z0-9])$";
#$ValidMacAddressRegex="^([0-9A-Fa-f]{2}[:-]){5}[0-9A-Fa-f]{2}|([0-9a-fA-F]{4}[\\.-][0-9a-fA-F]{4}[\\.-][0-9a-fA-F]{4})|[0-9A-Fa-f]{12}$";


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

    // Checks for a valid IP address or optionally a cidr notation range
    // e.g. 1.2.3.4 or 1.2.3.0/24

    // if(!preg_match("^[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}(/[0-9]{1,2}){0,1}$", $cidr)) {
    $ip_pattern = '/^(?:25[0-5]|2[0-4]\d|1\d\d|[1-9]\d|\d)(?:[.](?:25[0-5]|2[0-4]\d|1\d\d|[1-9]\d|\d)){3}/';
    if (!preg_match($ip_pattern, $cidr)) {
        $return = FALSE;
    } else {
        $return = TRUE;
    }

    if ($return == TRUE) {
        $parts = explode("/", $cidr);
        $ip = $parts[0];
        if (empty($parts[1])) {
            $parts[1] = "32";
        }
        $netmask = $parts[1];
        $octets = explode(".", $ip);
        foreach ($octets as $octet) {
            if ($octet > 255) {
                $return = FALSE;
            }
        }
        if (($netmask != "") && ($netmask > 32)) {
            $return = FALSE;
        }
    }
    return $return;
}

function checkValidMac($mac)
{
    $ValidMacAddressRegex = "/^([0-9A-Fa-f]{2}[:-]){5}[0-9A-Fa-f]{2}|([0-9a-fA-F]{4}[\\.-][0-9a-fA-F]{4}[\\.-][0-9a-fA-F]{4})|[0-9A-Fa-f]{12}$/";
    if (!preg_match($ValidMacAddressRegex, $mac)) {
        $return = FALSE;
    } else {
        $return = TRUE;
    }
    return $return;
}

function checkValidHostname($dnsname)
{
    $host_pattern = "/^(([a-zA-Z0-9]|[a-zA-Z0-9][a-zA-Z0-9\-]*[a-zA-Z0-9])\.)*([A-Za-z0-9]|[A-Za-z0-9][A-Za-z0-9\-]*[A-Za-z0-9])$/";
    if (!preg_match($host_pattern, $dnsname)) {
        $result = FALSE;
    } else {
        $result = TRUE;
    }
    return $result;
}

function checkUniqHostname($db, $id, $hostname)
{
    $count = get_count_records($db, 'User_auth', 'deleted=0 and id !="' . $id . '" and dns_name ="' . mysqli_real_escape_string($db, $hostname) . '"');
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
    #dhcp
    $dhcp_size = round(($stop - $start) / 2, PHP_ROUND_HALF_UP);
    $dhcp_start = $start + round($dhcp_size / 2, PHP_ROUND_HALF_UP);
    $range[3] = long2ip($dhcp_start);
    $range[4] = long2ip($dhcp_start + $dhcp_size);
    $range[5] = long2ip($start + 1);
    return $range;
}

function crypt_string($simple_string)
{
    // Storingthe cipher method
    $ciphering = "aes-128-cbc";
    // Using OpenSSl Encryption method
    $iv_length = openssl_cipher_iv_length($ciphering);
    $options = 0;
    // Using openssl_encrypt() function to encrypt the data
    return openssl_encrypt($simple_string, $ciphering, ENCRYPTION_KEY, $options, ENCRYPTION_IV);
}

function decrypt_string($crypted_string)
{
    // Storingthe cipher method
    $ciphering = "aes-128-cbc";
    // Using OpenSSl Encryption method
    $iv_length = openssl_cipher_iv_length($ciphering);
    $options = 0;
    // Using openssl_decrypt() function to decrypt the data
    return  openssl_decrypt($crypted_string, $ciphering, ENCRYPTION_KEY, $options, ENCRYPTION_IV);
}

function print_ou_select($db, $ou_name, $ou_value)
{
    print "<select name=\"$ou_name\" >\n";
    $t_ou = mysqli_query($db, "SELECT id,ou_name FROM OU ORDER BY ou_name");
    while (list($f_ou_id, $f_ou_name) = mysqli_fetch_array($t_ou)) {
        print_select_item($f_ou_name, $f_ou_id, $ou_value);
    }
    print "</select>\n";
}

function print_ou_set($db, $ou_name, $ou_value)
{
    print "<select name=\"$ou_name\">\n";
    $t_ou = mysqli_query($db, "SELECT id,ou_name FROM OU WHERE id>=1 ORDER BY ou_name");
    while (list($f_ou_id, $f_ou_name) = mysqli_fetch_array($t_ou)) {
        print_select_item($f_ou_name, $f_ou_id, $ou_value);
    }
    print "</select>\n";
}

function print_subnet_select($db, $subnet_name, $subnet_value)
{
    print "<select name=\"$subnet_name\" >\n";
    $t_subnet = mysqli_query($db, "SELECT id,subnet FROM subnets ORDER BY ip_int_start");
    print_select_item(WEB_select_item_all_ips, 0, $subnet_value);
    while (list($f_subnet_id, $f_subnet_name) = mysqli_fetch_array($t_subnet)) {
        print_select_item($f_subnet_name, $f_subnet_id, $subnet_value);
    }
    print "</select>\n";
}

function print_device_ip_select($db, $ip_name, $ip, $user_id)
{
    print "<select name=\"$ip_name\">\n";
    $auth_list = get_records_sql($db, "SELECT ip FROM User_auth WHERE user_id=$user_id AND deleted=0 ORDER BY ip_int");
    foreach ($auth_list as $row) {
        print_select_item($row['ip'], $row['ip'], $ip);
    }
    print "</select>\n";
}

function print_subnet_select_office($db, $subnet_name, $subnet_value)
{
    print "<select name=\"$subnet_name\" >\n";
    $t_subnet = mysqli_query($db, "SELECT id,subnet FROM subnets WHERE office=1 ORDER BY ip_int_start");
    print_select_item(WEB_select_item_all_ips, 0, $subnet_value);
    while (list($f_subnet_id, $f_subnet_name) = mysqli_fetch_array($t_subnet)) {
        print_select_item($f_subnet_name, $f_subnet_id, $subnet_value);
    }
    print "</select>\n";
}

function print_loglevel_select($item_name, $value)
{
    print "<select name=\"$item_name\">\n";
    print_select_item('Error', L_ERROR, $value);
    print_select_item('Warning', L_WARNING, $value);
    print_select_item('Info', L_INFO, $value);
    print_select_item('Verbose', L_VERBOSE, $value);
    print_select_item('Debug', L_DEBUG, $value);
    print "</select>\n";
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
    print_submenu_url(WEB_submenu_network, '/admin/customers/control-subnets.php', $current_page, 0);
    print_submenu_url(WEB_submenu_network_stats, '/admin/customers/control-subnets-usage.php', $current_page, 0);
    print_submenu_url(WEB_submenu_options, '/admin/customers/control-options.php', $current_page, 0);
    print_submenu_url(WEB_submenu_customers, '/admin/customers/index.php', $current_page, 1);
    print "</div>\n";
}

function print_filters_submenu($current_page)
{
    print "<div id='submenu'>\n";
    print_submenu_url(WEB_submenu_filter_list, '/admin/filters/index.php', $current_page, 0);
    print_submenu_url(WEB_submenu_filter_group, '/admin/filters/groups.php', $current_page, 1);
    print "</div>\n";
}

function print_reports_submenu($current_page)
{
    print "<div id='submenu'>\n";
    print_submenu_url(WEB_submenu_traffic_ip_report, '/admin/reports/index-full.php', $current_page, 0);
    print_submenu_url(WEB_submenu_traffic_login_report, '/admin/reports/index.php', $current_page, 1);
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
    print_submenu_url(WEB_submenu_buildings, '/admin/devices/building.php', $current_page, 0);
    print_submenu_url(WEB_submenu_hierarchy, '/admin/devices/index-tree.php', $current_page, 0);
    print_submenu_url(WEB_submenu_device_models, '/admin/devices/devmodels.php', $current_page, 0);
    print_submenu_url(WEB_submenu_vendors, '/admin/devices/devvendors.php', $current_page, 0);
    print_submenu_url(WEB_submenu_ports_vlan, '/admin/devices/portsbyvlan.php', $current_page, 1);
    print "</div>\n";
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
    print "<select name=\"$device_model_name\" class=\"js-select-single\">\n";
    $t_device_model = mysqli_query($db, "SELECT M.id,M.model_name,V.name FROM device_models M,vendors V WHERE M.vendor_id = V.id ORDER BY V.name,M.model_name");
    while (list($f_device_model_id, $f_device_model_name, $f_vendor_name) = mysqli_fetch_array($t_device_model)) {
        print_select_item($f_vendor_name . " " . $f_device_model_name, $f_device_model_id, $device_model_value);
    }
    print "</select>\n";
}

function print_group_select($db, $group_name, $group_value)
{
    print "<select name=\"$group_name\">\n";
    $t_group = mysqli_query($db, "SELECT id,group_name FROM Group_list Order by group_name");
    while (list($f_group_id, $f_group_name) = mysqli_fetch_array($t_group)) {
        print_select_item($f_group_name, $f_group_id, $group_value);
    }
    print "</select>\n";
}

function print_building_select($db, $building_name, $building_value)
{
    print "<select name=\"$building_name\">\n";
    print_select_item(WEB_select_item_all, 0, $building_value);
    $t_building = mysqli_query($db, "SELECT id,name FROM building Order by name");
    while (list($f_building_id, $f_building_name) = mysqli_fetch_array($t_building)) {
        print_select_item($f_building_name, $f_building_id, $building_value);
    }
    print "</select>\n";
}

function print_devtypes_select($db, $devtype_name, $devtype_value, $mode)
{
    print "<select name=\"$devtype_name\">\n";
    print_select_item(WEB_select_item_all, 0, $devtype_value);
    $filter = '';
    if (!empty($mode)) {
        $filter = "WHERE $mode";
    }
    $t_devtype = mysqli_query($db, "SELECT id,`name.".HTML_LANG."` FROM device_types $filter ORDER BY `name.".HTML_LANG."`");
    while (list($f_devtype_id, $f_devtype_name) = mysqli_fetch_array($t_devtype)) {
        print_select_item($f_devtype_name, $f_devtype_id, $devtype_value);
    }
    print "</select>\n";
}

function print_devtype_select($db, $devtype_name, $devtype_value)
{
    print "<select name=\"$devtype_name\">\n";
    $t_devtype = mysqli_query($db, "SELECT id,`name.".HTML_LANG."` FROM device_types ORDER BY `name.".HTML_LANG."`");
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
    list($type_name) = mysqli_fetch_array(mysqli_query($db, "SELECT `name.".HTML_LANG."` FROM device_types WHERE id=$device_type_id"));
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

function print_queue_select($db, $queue_name, $queue_value)
{
    print "<select name=\"$queue_name\">\n";
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

function print_qa_l3int_select($qa_name, $qa_value)
{
    print "<select name=\"$qa_name\">\n";
    print_select_item(WEB_select_item_lan, 0, $qa_value);
    print_select_item(WEB_select_item_wan, 1, $qa_value);
    print "</select>\n";
}

function print_qa_rule_select($qa_name, $qa_value)
{
    print "<select name=\"$qa_name\">\n";
    print_select_item('Subnet', 1, $qa_value);
    print_select_item('Mac', 2, $qa_value);
    print_select_item('Hostname', 3, $qa_value);
    print "</select>\n";
}

function print_qa_select($qa_name, $qa_value)
{
    print "<select name=\"$qa_name\">\n";
    print_select_item(WEB_select_item_yes, 1, $qa_value);
    print_select_item(WEB_select_item_no, 0, $qa_value);
    print "</select>\n";
}

function print_qa_select_ext($qa_name, $qa_value, $readonly)
{
    $state = '';
    if ($readonly) {
        $state = 'disabled=true';
    }
    print "<select name=\"$qa_name\">\n";
    print_select_item_ext(WEB_select_item_yes, 1, $qa_value, $readonly);
    print_select_item_ext(WEB_select_item_no, 0, $qa_value, $readonly);
    print "</select>\n";
}

function print_control_proto_select($qa_name, $qa_value)
{
    print "<select name=\"$qa_name\">\n";
    print_select_item('Disabled', -1, $qa_value);
    print_select_item('Ssh', 0, $qa_value);
    print_select_item('Telnet', 1, $qa_value);
 //   print_select_item('Mikrotik rest api', 2, $qa_value);
    print "</select>\n";
}

function print_snmp_select($qa_name, $qa_value)
{
    print "<select name=\"$qa_name\">\n";
    print_select_item('Disabled', 0, $qa_value);
    print_select_item('v1', 1, $qa_value);
    print_select_item('v2', 2, $qa_value);
    print_select_item('v3', 3, $qa_value);
    print "</select>\n";
}

function print_dhcp_select($qa_name, $qa_value)
{
    print "<select name=\"$qa_name\">\n";
    if (!isset($qa_value) or strlen($qa_value) == 0) {
        $qa_value = 'all';
    }
    print_select_item(WEB_select_item_events, 'all', $qa_value);
    print_select_item(WEB_select_item_lease, 'add', $qa_value);
    print_select_item(WEB_select_item_lease_refresh, 'old', $qa_value);
    print_select_item(WEB_select_item_lease_free, 'del', $qa_value);
    print "</select>\n";
}

function print_nagios_handler_select($qa_name)
{
    print "<select name=\"$qa_name\">\n";
    print_select_simple(WEB_select_item_no, '');
    print_select_simple('restart-port', 'restart-port');
    print "</select>\n";
}

function print_dhcp_acl_select($qa_name)
{
    print "<select name=\"$qa_name\">\n";
    print_select_simple(WEB_select_item_no, '');
    print_select_simple('hotspot-free', 'hotspot-free');
    print "</select>\n";
}

function print_enabled_select($qa_name, $qa_value)
{
    print "<select name=\"$qa_name\">\n";
    if (!isset($qa_value) or strlen($qa_value) == 0) {
        $qa_value = 0;
    }
    print_select_item(WEB_select_item_every, 0, $qa_value);
    print_select_item(WEB_select_item_disabled, 1, $qa_value);
    print_select_item(WEB_select_item_enabled, 2, $qa_value);
    print "</select>\n";
}

function print_vendor_select($db, $qa_name, $qa_value)
{
    print "<select name=\"$qa_name\" class=\"js-select-single\">\n";
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
    print "<select name=\"$qa_name\" class=\"js-select-single\" style=\"width: 100%\">\n";
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

function get_qa($qa_value)
{
    if ($qa_value == 1) {
        return "Да";
    }
    return "Нет";
}

function print_action_select($action_name, $action_value)
{
    print "<select name=\"$action_name\">\n";
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
    print "<select name=\"$filter_name\" class=\"js-select-single\">\n";
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
    print "<select name=\"$login_name\" class=\"js-select-single\">\n";
    $t_login = mysqli_query($db, "SELECT id,login FROM User_list Order by Login");
    print_select_item('None', 0, $current_login);
    while (list($f_user_id, $f_login) = mysqli_fetch_array($t_login)) {
        print_select_item($f_login, $f_user_id, $current_login);
    }
    print "</select>\n";
}

function print_auth_select($db, $login_name, $current_auth)
{
    print "<select name=\"$login_name\" class=\"js-select-single\">\n";
    $t_login = mysqli_query($db, "SELECT U.login,U.fio,A.ip,A.id FROM User_list as U, User_auth as A WHERE A.user_id=U.id and A.deleted=0 and (A.id not in (select device_ports.auth_id FROM device_ports) or A.id=$current_auth) order by U.login,U.fio,A.ip");
    print_select_item('Empty', 0, $current_auth);
    while (list($f_login, $f_fio, $f_ip, $f_auth_id) = mysqli_fetch_array($t_login)) {
        print_select_item($f_login . "[" . $f_fio . "] - " . $f_ip, $f_auth_id, $current_auth);
    }
    print "</select>\n";
}

function print_auth_select_mac($db, $login_name, $current_auth)
{
    print "<select name=\"$login_name\" class=\"js-select-single\">\n";
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
    print "<select name=\"$field_name\" class=\"js-select-single\">\n";
    if (!isset($target_id)) {
        $target_id = 0;
    }
    if (!isset($device_id)) {
        $device_id = 0;
    }
    $d_sql = "SELECT D.device_name, DP.port, DP.device_id, DP.id, DP.ifName FROM devices AS D, device_ports AS DP WHERE D.deleted=0 and D.id = DP.device_id AND (DP.device_id<>$device_id or DP.id=$target_id) and (DP.id not in (select target_port_id FROM device_ports WHERE target_port_id>0 and target_port_id<>$target_id)) ORDER BY D.device_name,DP.port";
    $t_device = mysqli_query($db, $d_sql);
    print_select_item('Empty', 0, $target_id);
    while (list($f_name, $f_port, $f_device_id, $f_target_id, $f_ifname) = mysqli_fetch_array($t_device)) {
        if (empty($f_ifname)) {
            $f_ifname = $f_port;
        }
        print_select_item($f_name . "[" . compact_port_name($f_ifname) . "]", $f_target_id, $target_id);
    }
    print "</select>\n";
}

function print_device_select($db, $field_name, $device_id)
{
    print "<select name=\"$field_name\" class=\"js-select-single\" >\n";
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
    print "<select name=\"$field_name\" class=\"js-select-single\" >\n";
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
    print "<select name=\"$field_name\" class=\"js-select-single\">\n";
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
    print "<select name=\"$field_name\" class=\"js-select-single\" >\n";
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
    print "<select name=\"$field_name\" class=\"js-select-single\" >\n";
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
    print "<select name=\"$field_name\" >\n";
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

function print_auth_port($db, $port_id)
{
    $d_sql = "SELECT A.ip,A.ip_int,A.mac,A.id,A.dns_name FROM User_auth as A, connections as C WHERE C.port_id=$port_id and A.id=C.auth_id and A.deleted=0 order by A.ip_int";
    $t_device = mysqli_query($db, $d_sql);
    while (list($f_ip, $f_int, $f_mac, $f_auth_id, $f_dns) = mysqli_fetch_array($t_device)) {
        $name = $f_ip;
        if (isset($f_dns) and $f_dns != '') {
            $name = $f_dns;
        }
        print "<a href=\"/admin/users/editauth.php?id=$f_auth_id\">" . $name . " [" . $f_ip . "]</a><br>";
    }
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
    print "<select name=\"$option_name\">\n";
    $t_option = mysqli_query($db, "SELECT id,option_name FROM config_options WHERE uniq=0 order by option_name");
    while (list($f_id, $f_name) = mysqli_fetch_array($t_option)) {
        print "<option value=$f_id>$f_name</option>";
    }
    $t_option = mysqli_query($db, "SELECT id,option_name FROM config_options WHERE uniq=1 and id not in (select option_id FROM config) order by option_name");
    while (list($f_id, $f_name) = mysqli_fetch_array($t_option)) {
        print "<option value=$f_id>$f_name</option>";
    }
    print "</select>\n";
}

function run_sql($db, $query)
{
    $sql_result = mysqli_query($db, $query);
    if (!$sql_result) {
        LOG_ERROR($db, "At simple SQL: $query :" . mysqli_error($db));
        return;
    }
    return $sql_result;
}

function get_count_records($db, $table, $filter)
{
    if (!empty($filter)) {
        $filter = 'where ' . $filter;
    }
    $t_count = mysqli_query($db, "SELECT count(*) FROM $table $filter");
    list($count) = mysqli_fetch_array($t_count);
    if (!isset($count)) {
        $count = 0;
    }
    return $count;
}

function get_id_record($db, $table, $filter)
{
    if (isset($filter)) {
        $filter = 'WHERE ' . $filter;
    }
    $t_record = mysqli_query($db, "SELECT id FROM $table $filter limit 1");
    list($id) = mysqli_fetch_array($t_record);
    return $id;
}

function set_changed($db, $id)
{
    $auth['changed'] = 1;
    update_record($db, "User_auth", "id=" . $id, $auth);
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

function apply_auth_rule($db, $auth_id, $user_id)
{
    if (empty($auth_id)) {
        return;
    }
    if (empty($user_id)) {
        return;
    }
    $user_rec = get_record($db, 'User_list', "id=" . $user_id);
    if (empty($user_rec)) {
        return;
    }
    //set filter and status by user
    $set_auth['ou_id'] = $user_rec['ou_id'];
    $set_auth['user_id'] = $user_rec['id'];
    $set_auth['filter_group_id'] = $user_rec['filter_group_id'];
    $set_auth['queue_id'] = $user_rec['queue_id'];
    $set_auth['enabled'] = $user_rec['enabled'];
    $set_auth['changed'] = 1;
    update_record($db, "User_auth", "id=$auth_id", $set_auth);
    return $set_auth;
}

function fix_auth_rules($db)
{
    //cleanup hotspot subnet rules
    delete_record($db, "auth_rules", "ou_id=" . get_const('default_user_ou_id'));
    delete_record($db, "auth_rules", "ou_id=" . get_const('default_hotspot_ou_id'));
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

    // default id
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
        apply_auth_rule($db, $resurrection_id, $user_id);
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
        $user_rec = apply_auth_rule($db, $resurrection_id, $new_user_id);
        $msg .= "filter: " . $user_rec['filter_group_id'] . "\r\n queue_id: " . $user_rec['queue_id'] . "\r\n enabled: " . $user_rec['enabled'] . "\r\nid: $resurrection_id";
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

function isRO($db, $table)
{
    $result = 1;
    if (isset($_SESSION['login'])) {
        $work_user = $_SESSION['login'];
    }
    if (isset($_SESSION['user_id'])) {
        $work_id = $_SESSION['user_id'];
    }
    if (!isset($work_user) or !isset($work_id)) {
        return $result;
    }
    if (preg_match('/^(variables|dns_cache|syslog)$/', $table)) {
        return $result;
    }
    $t_login = mysqli_query($db, "SELECT readonly FROM Customers WHERE Login='" . $work_user . "' and id='" . $work_id . "'");
    list($f_ro) = mysqli_fetch_array($t_login);
    if (!isset($f_ro)) {
        return $result;
    }
    return $f_ro;
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
    email(L_ERROR, $msg);
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
    email(L_WARNING, $msg);
    write_log($db, $msg, L_WARNING, $auth_id);
}

function LOG_DEBUG($db, $msg, $auth_id = 0)
{
    if (!empty(get_const('debug')) and get_const('debug')) {
        write_log($db, $msg, L_DEBUG, $auth_id);
    }
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
    return;
}

function email($level, $msg)
{

    if (!get_const('send_email')) {
        return;
    }
    if (!($level === L_WARNING or $level === L_ERROR)) {
        return;
    }

    $subject = get_first_line($msg);

    if ($level === L_WARNING) {
        $subject = "WARN: " . $subject . "...";
        $message = 'WARNING! Manager: ' . $_SESSION['login'] . ' </br>';
    }
    if ($level === L_ERROR) {
        $subject = "ERROR: " . $subject . "...";
        $message = 'ERROR! Manager: ' . $_SESSION['login'] . ' </br>';
    }

    $msg_lines = preg_replace("/\r\n/", "</br>", $msg);
    $message .= $msg_lines;

    $send = SimpleMail::make()
        ->setTo(get_const('admin_email'), 'Administrator')
        ->setFrom(get_const('sender_email'), 'Stat')
        ->setSubject($subject)
        ->setMessage($message)
        ->setHtml()
        ->setWrap(80)
        ->send();
}

function write_log($db, $msg, $level, $auth_id = 0)
{
    $work_user = 'http';
    if (isset($_SESSION['login'])) {
        $work_user = $_SESSION['login'];
    }
    if (!isset($msg)) {
        $msg = 'ERROR! Empty log string!';
    }
    if (!isset($level)) {
        $level = L_INFO;
    }
    $msg = str_replace("'", '', $msg);
    $sSQL = "insert into syslog(customer,message,level,auth_id) values('$work_user','$msg',$level,$auth_id)";
    mysqli_query($db, $sSQL);
}

function print_year_select($year_name, $year)
{
    print "<select name=\"$year_name\" >\n";
    for ($i = $year - 10; $i <= $year + 10; $i++) {
        print_select_item($i, $i, $year);
    }
    print "</select>\n";
}

function print_date_select($dd, $mm, $yy)
{
    if ($dd >= 1) {
        print "<b>День</b>\n";
        print "<select name=\"day\" >\n";
        for ($i = 1; $i <= 31; $i++) {
            print_select_item($i, $i, $dd);
        }
        print "</select>\n";
    }

    if ($mm >= 1) {
        print "<b>Месяц</b>\n";
        print "<select name=\"month\" >\n";
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
        print "<select name=\"day2\" >\n";
        for ($i = 1; $i <= 31; $i++) {
            print_select_item($i, $i, $dd);
        }
        print "</select>\n";
    }

    if ($mm >= 1) {
        print "<b>Месяц</b>\n";
        print "<select name=\"month2\" >\n";
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

function get_ifmib_index_table($ip, $community, $version)
{
    $ifmib_map = NULL;

    $is_mikrotik = walk_snmp($ip, $community, $version, MIKROTIK_DHCP_SERVER);
    $mk_ros_version = 0;

    if ($is_mikrotik) {
        $mikrotik_version = walk_snmp($ip, $community, $version, MIKROTIK_ROS_VERSION);
        $mk_ros_version = 6491;
        $result = preg_match('/RouterOS\s+(\d)\.(\d{1,3})\.(\d{1,3})\s+/', $mikrotik_version[MIKROTIK_ROS_VERSION], $matches);
        if ($result) {
            $mk_ros_version = $matches[1] * 1000 + $matches[2] * 10 + $matches[3];
        }
    }

    if ($mk_ros_version == 0 or $mk_ros_version > 6468) {
        #fdb_index => snmp_index
        $index_map_table = walk_snmp($ip, $community, $version, IFMIB_IFINDEX_MAP);
        #get map snmp interfaces to fdb table
        if (isset($index_map_table) and count($index_map_table) > 0) {
            foreach ($index_map_table as $key => $value) {
                $key = trim($key);
                $value = intval(trim(str_replace('INTEGER:', '', $value)));
                $result = preg_match('/\.(\d{1,10})$/', $key, $matches);
                if ($result) {
                    $fdb_index = preg_replace('/^\./', '', $matches[0]);
                    $ifmib_map[$fdb_index] = $value;
                }
            }
        }
    }

    #return simple map snmp_port_index = snmp_port_index
    if (empty($ifmib_map)) {
        #ifindex
        $index_table = walk_snmp($ip, $community, $version, IFMIB_IFINDEX);
        if (isset($index_table) and count($index_table) > 0) {
            foreach ($index_table as $key => $value) {
                $key = trim($key);
                $value = intval(trim(str_replace('INTEGER:', '', $value)));
                $result = preg_match('/\.(\d{1,10})$/', $key, $matches);
                if ($result) {
                    $fdb_index = preg_replace('/^\./', '', $matches[0]);
                    $ifmib_map[$fdb_index] = $value;
                }
            }
        }
    }
    return $ifmib_map;
}

#get mac table by selected snmp oid
function get_mac_table($ip, $community, $version, $oid, $index_map)
{
    if (!isset($ip)) {
        return;
    }
    if (!isset($oid)) {
        return;
    }
    if (!isset($community)) {
        $community = 'public';
    }
    if (!isset($version)) {
        $version = '2';
    }

    $mac_table = walk_snmp($ip, $community, $version, $oid);
    if (isset($mac_table) and count($mac_table) > 0) {
        foreach ($mac_table as $key => $value) {
            if (empty($value)) {
                continue;
            }
            if (empty($key)) {
                continue;
            }
            $key = trim($key);
            $value_raw = intval(trim(str_replace('INTEGER:', '', $value)));
            if (empty($value_raw)) {
                continue;
            }
            if (!empty($index_map)) {
                if (empty($index_map[$value_raw])) {
                    $value = $value_raw;
                } else {
                    $value = $index_map[$value_raw];
                }
            } else {
                $value = $value_raw;
            }
            $pattern = '/\.(\d{1,3})\.(\d{1,3})\.(\d{1,3})\.(\d{1,3})\.(\d{1,3})\.(\d{1,3})$/';
            $result = preg_match($pattern, $key, $matches);
            if (!empty($result)) {
                $mac_key = preg_replace('/^\./', '', $matches[0]);
                $fdb_table[$mac_key] = $value;
            }
        }
    }
    return $fdb_table;
}

#get mac table by analyze all available tables
function get_fdb_table($ip, $community, $version)
{

    if (!isset($ip)) {
        return;
    }
    if (!isset($community)) {
        $community = 'public';
    }
    if (!isset($version)) {
        $version = '2';
    }

    $ifindex_map = get_ifmib_index_table($ip, $community, $version);
    $fdb1_table = get_mac_table($ip, $community, $version, MAC_TABLE_OID, $ifindex_map);
    if (!empty($fdb1_table)) {
        $fdb_table = $fdb1_table;
    } else {
        $fdb2_table = get_mac_table($ip, $community, $version, MAC_TABLE_OID2, $ifindex_map);
        if (!empty($fdb2_table)) {
            $fdb_table = $fdb2_table;
        }
    }

    // maybe cisco?!
    if (!isset($fdb_table) or empty($fdb_table) or count($fdb_table) == 0) {
        $vlan_table = walk_snmp($ip, $community, $version, CISCO_VLAN_OID);
        if (empty($vlan_table)) {
            return;
        }
        foreach ($vlan_table as $vlan_oid => $value) {
            if (empty($vlan_oid)) {
                continue;
            }
            $pattern = '/\.(\d{1,4})$/';
            $result = preg_match($pattern, $vlan_oid, $matches);
            if (!empty($result)) {
                $vlan_id = preg_replace('/^\./', '', $matches[0]);
                if ($vlan_id > 1000 and $vlan_id < 1009) {
                    continue;
                }
                $fdb_vlan_table = get_mac_table($ip, $community . '@' . $vlan_id, $version, MAC_TABLE_OID, $ifindex_map);
                if (!isset($fdb_vlan_table) or !$fdb_vlan_table or count($fdb_vlan_table) == 0) {
                    $fdb_vlan_table = get_mac_table($ip, $community, $version, MAC_TABLE_OID2, $ifindex_map);
                }
                foreach ($fdb_vlan_table as $mac => $port) {
                    if (!isset($mac)) {
                        continue;
                    }
                    $fdb_table[$mac] = $port;
                }
            }
        }
    }
    return $fdb_table;
}

function check_snmp_access($ip, $community, $version)
{
    if (!isset($ip)) {
        return;
    }
    if (!isset($community)) {
        $community = 'public';
    }
    if (!isset($version)) {
        $version = '2';
    }
    #check host up
    $status = exec(escapeshellcmd("ping -W 1 -i 1 -c 3 " . $ip));
    if (empty($status)) {
        return;
    }
    #check snmp
    $result = get_snmp($ip, $community, $version, SYS_DESCR_MIB);
    if (empty($result)) {
        return;
    }
    return 1;
}

function get_port_state($port, $ip, $community, $version)
{
    if (!isset($port)) {
        return;
    }
    if (!isset($ip)) {
        return;
    }
    if (!isset($community)) {
        $community = 'public';
    }
    if (!isset($version)) {
        $version = '2';
    }
    $port_oid = PORT_STATUS_OID . $port;
    $port_state = get_snmp($ip, $community, $version, $port_oid);
    return $port_state;
}

function get_last_digit($oid)
{
    if (!isset($oid)) {
        return;
    }
    $pattern = '/\.(\d{1,})$/';
    preg_match($pattern, $oid, $matches);
    return $matches[1];
}

function get_cisco_sensors($ip, $community, $version, $mkey)
{
    $index = get_last_digit($mkey);
    $result = parse_snmp_value(get_snmp($ip, $community, $version, CISCO_SFP_SENSORS . "." . $index));
    $prec = parse_snmp_value(get_snmp($ip, $community, $version, CISCO_SFP_PRECISION . "." . $index));
    if (!isset($prec)) {
        $prec = 1;
    }
    $result = round(trim($result) / (10 * $prec), 2);
    return $result;
}

function get_snmp_ifname1($ip, $community, $version, $port)
{
    $port_name = parse_snmp_value(get_snmp($ip, $community, $version, IFMIB_IFNAME . "." . $port));
    return $port_name;
}

function get_snmp_ifname2($ip, $community, $version, $port)
{
    $port_name = parse_snmp_value(get_snmp($ip, $community, $version, IFMIB_IFDESCR . "." . $port));
    return $port_name;
}

function get_snmp_interfaces($ip, $community, $version)
{
    $result = walk_snmp($ip, $community, $version, IFMIB_IFNAME);
    if (empty($result)) {
        $result = walk_snmp($ip, $community, $version, IFMIB_IFDESCR);
    }
    return $result;
}

function walk_snmp($ip, $community, $version, $oid)
{
    snmp_set_oid_output_format(SNMP_OID_OUTPUT_NUMERIC);

    if ($version == 2) {
        $result = snmp2_real_walk($ip, $community, $oid, SNMP_timeout, SNMP_retry);
    }
    if ($version == 1) {
        $result = snmprealwalk($ip, $community, $oid, SNMP_timeout, SNMP_retry);
    }
    return $result;
}

function get_snmp_module_id($modules_oids, $port_name)
{
    $port_name = preg_quote(trim($port_name), '/');
    foreach ($modules_oids as $key => $value) {
        $pattern = '/' . $port_name . '/i';
        preg_match($pattern, $value, $matches);
        if (isset($matches[0])) {
            $module_id = get_last_digit($key);
            return $module_id;
        }
    }
    return;
}

function get_sfp_status($vendor_id, $port, $ip, $community, $version, $modules_oids)
{
    if (!isset($vendor_id)) {
        return;
    }
    if (!isset($port)) {
        return;
    }
    if (!isset($ip)) {
        return;
    }
    if (!isset($community)) {
        $community = 'public';
    }
    if (!isset($version)) {
        $version = '2';
    }

    $status = '';
    // eltex
    if ($vendor_id == 2) {
        $sfp_vendor = parse_snmp_value(get_snmp($ip, $community, $version, ELTEX_SFP_VENDOR . "." . $port));
        if (isset($sfp_vendor)) {
            $sfp_status_temp = ELTEX_SFP_STATUS . "." . $port . ".5";
            $sfp_status_volt = ELTEX_SFP_STATUS . "." . $port . ".6";
            $sfp_status_circut = ELTEX_SFP_STATUS . "." . $port . ".7";
            $sfp_status_tx = ELTEX_SFP_STATUS . "." . $port . ".8";
            $sfp_status_rx = ELTEX_SFP_STATUS . "." . $port . ".9";
            $temp = parse_snmp_value(get_snmp($ip, $community, $version, $sfp_status_temp));
            $volt = parse_snmp_value(get_snmp($ip, $community, $version, $sfp_status_volt));
            $circut = parse_snmp_value(get_snmp($ip, $community, $version, $sfp_status_circut));
            $tx = parse_snmp_value(get_snmp($ip, $community, $version, $sfp_status_tx));
            $rx = parse_snmp_value(get_snmp($ip, $community, $version, $sfp_status_rx));
            $sfp_sn = parse_snmp_value(get_snmp($ip, $community, $version, ELTEX_SFP_SN . "." . $port));
            $sfp_freq = parse_snmp_value(get_snmp($ip, $community, $version, ELTEX_SFP_FREQ . "." . $port));
            if (!isset($sfp_freq) or $sfp_freq == 65535) {
                $sfp_freq = 'unspecified';
            }
            $sfp_length = parse_snmp_value(get_snmp($ip, $community, $version, ELTEX_SFP_LENGTH . "." . $port));
            $status = 'Vendor: ' . $sfp_vendor . ' Serial: ' . $sfp_sn . ' Laser: ' . $sfp_freq . ' Distance: ' . $sfp_length . '<br>';
            if (!empty($sfp_status_temp) and $temp > 0.1) {
                $status .= 'Temp: ' . $temp . " C";
            }
            if (!empty($sfp_status_volt) and $volt > 0.1) {
                $status .= ' Volt: ' . round($volt / 1000000, 2) . ' V';
            }
            if (!empty($sfp_status_circut) and $circut > 0.1) {
                $status .= ' Circut: ' . round($circut / 1000, 2) . ' mA';
            }
            if (!empty($sfp_status_tx) and $tx > 0.1) {
                $status .= ' Tx: ' . round($tx / 1000, 2) . ' dBm';
            }
            if (!empty($sfp_status_rx) and $rx > 0.1) {
                $status .= ' Rx: ' . round($rx / 1000, 2) . ' dBm';
            }
            $status .= '<br>';
            return $status;
        }
        return;
    }
    // cisco
    if ($vendor_id == 16) {
        // get interface names
        $port_name = parse_snmp_value(get_snmp($ip, $community, $version, IFMIB_IFNAME . "." . $port));
        if (empty($port_name)) {
            $port_name = parse_snmp_value(get_snmp($ip, $community, $version, IFMIB_IFDESCR . "." . $port));
        }
        // search module indexes
        $port_name = preg_quote(trim($port_name), '/');
        foreach ($modules_oids as $key => $value) {
            $pattern = '/(' . $port_name . ' Module Temperature Sensor)/i';
            preg_match($pattern, $value, $matches);
            if (isset($matches[0])) {
                $temp = get_cisco_sensors($ip, $community, $version, $key);
                continue;
            }
            $pattern = '/(' . $port_name . ' Supply Voltage Sensor)/i';
            preg_match($pattern, $value, $matches);
            if (isset($matches[0])) {
                $volt = get_cisco_sensors($ip, $community, $version, $key);
                continue;
            }
            $pattern = '/(' . $port_name . ' Bias Current Sensor)/i';
            preg_match($pattern, $value, $matches);
            if (isset($matches[0])) {
                $circut = get_cisco_sensors($ip, $community, $version, $key);
                continue;
            }
            $pattern = '/(' . $port_name . ' Transmit Power Sensor)/i';
            preg_match($pattern, $value, $matches);
            if (isset($matches[0])) {
                $tx = get_cisco_sensors($ip, $community, $version, $key);
                continue;
            }
            $pattern = '/(' . $port_name . ' Receive Power Sensor)/i';
            preg_match($pattern, $value, $matches);
            if (isset($matches[0])) {
                $rx = get_cisco_sensors($ip, $community, $version, $key);
                continue;
            }
        }
        if (!empty($temp) and $temp > 0) {
            $status .= 'Temp: ' . $temp . " C";
        }
        if (!empty($volt) and $volt > 0) {
            $status .= ' Volt: ' . $volt . ' V';
        }
        if (!empty($circut) and $circut > 0) {
            $status .= ' Circut: ' . $circut . ' mA';
        }
        if (!empty($tx) and abs($tx) > 0.1) {
            $status .= ' Tx: ' . $tx . ' dBm';
        }
        if (!empty($rx) and abs($rx) > 0.1) {
            $status .= ' Rx: ' . $rx . ' dBm';
        }
        if (!empty($status)) {
            $status = preg_replace('/"/', '', $status);
            $status .= '<br>';
        }
        return $status;
    }

    // huawei
    if ($vendor_id == 3) {

        // get interface names
        $port_name = parse_snmp_value(get_snmp($ip, $community, $version, IFMIB_IFNAME . "." . $port));
        if (empty($port_name)) {
            $port_name = parse_snmp_value(get_snmp($ip, $community, $version, IFMIB_IFDESCR . "." . $port));
        }
        // search module indexes
        $port_name = preg_quote(trim($port_name), '/');
        foreach ($modules_oids as $key => $value) {
            $pattern = '/' . $port_name . '/i';
            preg_match($pattern, $value, $matches);
            if (isset($matches[0])) {
                $module_id = get_last_digit($key);
                unset($result);
                $result = parse_snmp_value(get_snmp($ip, $community, $version, HUAWEI_SFP_VENDOR . "." . $module_id));
                if (!empty($result)) {
                    $sfp_vendor = $result;
                }
                unset($result);
                $result = parse_snmp_value(get_snmp($ip, $community, $version, HUAWEI_SFP_SPEED . "." . $module_id));
                if (!empty($result)) {
                    list($sfp_speed, $spf_lenght, $sfp_type) = explode('-', $result);
                    if ($sfp_type == 0) {
                        $sfp_type = 'MultiMode';
                    }
                    if ($sfp_type == 1) {
                        $sfp_type = 'SingleMode';
                    }
                }

                $volt = parse_snmp_value(get_snmp($ip, $community, $version, HUAWEI_SFP_VOLT . "." . $module_id));
                $circut = parse_snmp_value(get_snmp($ip, $community, $version, HUAWEI_SFP_BIASCURRENT . "." . $module_id));
                $tx = parse_snmp_value(get_snmp($ip, $community, $version, HUAWEI_SFP_OPTTX . "." . $module_id));
                $rx = parse_snmp_value(get_snmp($ip, $community, $version, HUAWEI_SFP_OPTRX . "." . $module_id));
                if (!isset($tx)) {
                    $tx = parse_snmp_value(get_snmp($ip, $community, $version, HUAWEI_SFP_TX . "." . $module_id));
                }
                if (!isset($rx)) {
                    $rx = parse_snmp_value(get_snmp($ip, $community, $version, HUAWEI_SFP_RX . "." . $module_id));
                }
                if (!empty($sfp_vendor)) {
                    $status .= ' Name:' . $sfp_vendor . '<br>';
                }
                //                if (isset($sfp_speed)) { $status .= ' ' . $sfp_speed; }
                //                if (isset($spf_lenght)) { $status .= ' ' . $spf_lenght; }
                if ($volt > 0) {
                    $status .= ' Volt: ' . round($volt / 1000, 2) . ' V';
                }
                if (!empty($circut) and $circut > 0) {
                    $status .= ' Circut: ' . $circut . ' mA <br>';
                }
                if (!empty($tx)) {
                    $tx = preg_replace('/"/', '', $tx);
                    list($tx_dbm, $pattern) = explode('.', $tx);
                    $tx_dbm = round(trim($tx_dbm) / 100, 2);
                    if (abs($tx_dbm) > 0.1) {
                        $status .= ' Tx: ' . $tx_dbm . ' dBm';
                    }
                }
                if (!empty($rx)) {
                    $rx = preg_replace('/"/', '', $rx);
                    list($rx_dbm, $pattern) = explode('.', $rx);
                    $rx_dbm = round(trim($rx_dbm) / 100, 2);
                    if (abs($rx_dbm) > 0.1) {
                        $status .= ' Rx: ' . $rx_dbm . ' dBm';
                    }
                }

                break;
            }
        }
        if (isset($status)) {
            $status = preg_replace('/"/', '', $status);
            $status .= '<br>';
        }
        return $status;
    }
    return;
}

function get_port_vlan($vendor, $port_index, $ip, $community, $version)
{
    if (!isset($port_index)) {
        return;
    }
    if (!isset($ip)) {
        return;
    }
    if (!isset($community)) {
        $community = 'public';
    }
    if (!isset($version)) {
        $version = '2';
    }

    if ($vendor == 69) {
        $port_oid = TPLINK_VLAN_PVID . "." . $port_index;
    } else {
        $port_oid = PORT_VLAN_OID . "." . $port_index;
    }

    $port_vlan = get_snmp($ip, $community, $version, $port_oid);
    $port_vlan = preg_replace('/.*\:/', '', $port_vlan);
    $port_vlan = intval(trim($port_vlan));
    return $port_vlan;
}

function get_port_poe_state($vendor_id, $port, $port_snmp_index, $ip, $community = 'public', $version = '2')
{
    if (!isset($port)) {
        return;
    }
    if (!isset($ip)) {
        return;
    }

    // default poe oid
    $poe_status = PETH_PSE_PORT_ADMIN_ENABLE . "." . $port_snmp_index;

    if ($vendor_id == 3) {
        $poe_status = HUAWEI_POE_OID . "." . $port_snmp_index;
    }

    if ($vendor_id == 6) {
        $poe_status = SNR_POE_OID . "." . $port_snmp_index;
    }

    if ($vendor_id == 8) {
        $poe_status = ALLIED_POE_OID . "." . $port_snmp_index;
    }

    if ($vendor_id == 15) {
        $poe_status = HP_POE_OID . "." . $port_snmp_index;
    }

    if ($vendor_id == 9) {
        $poe_status = MIKROTIK_POE_OID . "." . $port_snmp_index;
    }

    if ($vendor_id == 10) {
        $poe_status = NETGEAR_POE_OID . "." . $port_snmp_index;
    }

    if ($vendor_id == 69) {
        $poe_status = TPLINK_POE_OID . "." . $port;
    }

    $result = '';
    $c_state = get_snmp($ip, $community, $version, $poe_status);
    if (!empty($c_state)) {
        $p_state = parse_snmp_value($c_state);
        // patch for mikrotik
        if ($vendor_id == 9) {
            if ($p_state == 1) {
                return 2;
            }
            if ($p_state > 1) {
                return 1;
            }
        }
        //patch for tplink
        if ($vendor_id == 69) {
            if ($p_state == 0) {
                return 2;
            }
            if ($p_state >= 1) {
                return 1;
            }
        }
        return $p_state;
    }
    return;
}

function set_port_poe_state($vendor_id, $port, $port_snmp_index, $ip, $community, $version, $state)
{
    if (!isset($ip)) {
        return;
    }

    if (!isset($community)) {
        $community = 'public';
    }
    if (!isset($version)) {
        $version = '2';
    }

    //default poe status
    $poe_enable = 1;
    $poe_disable = 2;

    // default poe oid
    $poe_status = PETH_PSE_PORT_ADMIN_ENABLE . "." . $port_snmp_index;

    if ($vendor_id == 3) {
        $poe_status = HUAWEI_POE_OID . "." . $port_snmp_index;
    }

    if ($vendor_id == 8) {
        $poe_status = ALLIED_POE_OID . "." . $port_snmp_index;
    }

    if ($vendor_id == 15) {
        $poe_status = HP_POE_OID . "." . $port_snmp_index;
    }

    if ($vendor_id == 10) {
        $poe_status = NETGEAR_POE_OID . "." . $port_snmp_index;
    }

    if ($vendor_id == 69) {
        $poe_status = TPLINK_POE_OID . "." . $port;
        $poe_enable = 1;
        $poe_disable = 0;
    }

    if ($state) {
        // enable port
        $c_state = set_snmp($ip, $community, $version, $poe_status, 'i', $poe_enable);
        return $c_state;
    } else {
        // disable port
        $c_state = set_snmp($ip, $community, $version, $poe_status, 'i', $poe_disable);
        return $c_state;
    }
}

function get_port_poe_detail($vendor_id, $port, $port_snmp_index, $ip, $community, $version)
{
    if (!isset($port) or !isset($port_snmp_index)) {
        return;
    }
    if (!isset($ip)) {
        return;
    }
    if (!isset($community)) {
        $community = 'public';
    }
    if (!isset($version)) {
        $version = '2';
    }

    $result = '';

    $poe_class = PETH_PSE_PORT_POE_CLASS . $port_snmp_index;

    // eltex
    if ($vendor_id == 2) {
        $poe_power = ELTEX_POE_USAGE . '.' . $port_snmp_index;
        $poe_current = ELTEX_POE_CURRENT . '.' . $port_snmp_index;
        $poe_volt = ELTEX_POE_VOLT . '.' . $port_snmp_index;
    }

    // huawei
    if ($vendor_id == 3) {
        $poe_power = HUAWEI_POE_USAGE . '.' . $port_snmp_index;
        $poe_current = HUAWEI_POE_CURRENT . '.' . $port_snmp_index;
        $poe_volt = HUAWEI_POE_VOLT . '.' . $port_snmp_index;
    }

    // snr
    if ($vendor_id == 6) {
        $poe_class = SNR_POE_CLASS . '.' . $port_snmp_index;
        $poe_power = SNR_POE_USAGE . '.' . $port_snmp_index;
        $poe_current = SNR_POE_CURRENT . '.' . $port_snmp_index;
        $poe_volt = SNR_POE_VOLT . '.' . $port_snmp_index;
    }

    // AT
    if ($vendor_id == 8) {
        $poe_power = ALLIED_POE_USAGE . '.' . $port_snmp_index;
        $poe_current = ALLIED_POE_CURRENT . '.' . $port_snmp_index;
        $poe_volt = ALLIED_POE_VOLT . '.' . $port_snmp_index;
    }

    // mikrotik
    if ($vendor_id == 9) {
        $poe_power = MIKROTIK_POE_USAGE . '.' . $port_snmp_index;
        $poe_current = MIKROTIK_POE_CURRENT . '.' . $port_snmp_index;
        $poe_volt = MIKROTIK_POE_VOLT . '.' . $port_snmp_index;
    }

    // netgear
    if ($vendor_id == 10) {
        $poe_power = NETGEAR_POE_USAGE . '.' . $port_snmp_index;
        $poe_current = NETGEAR_POE_CURRENT . '.' . $port_snmp_index;
        $poe_volt = NETGEAR_POE_VOLT . '.' . $port_snmp_index;
    }

    // HP
    if ($vendor_id == 15) {
        $poe_power = HP_POE_USAGE . '.' . $port_snmp_index;
        $poe_volt = HP_POE_VOLT . '.' . $port_snmp_index;
    }

    // TP-Link
    if ($vendor_id == 69) {
        $poe_power = TPLINK_POE_USAGE . '.' . $port;
        $poe_current = TPLINK_POE_CURRENT . '.' . $port;
        $poe_volt = TPLINK_POE_VOLT . '.' . $port;
        $poe_class = TPLINK_POE_CLASS . "." . $port;
    }

    if (isset($poe_power)) {
        $c_power = get_snmp($ip, $community, $version, $poe_power);
        if (isset($c_power)) {
            $p_power = parse_snmp_value($c_power);
            switch ($vendor_id) {
                case 9:
                    $p_power = round($p_power / 10, 2);
                    break;
                case 69:
                    $p_power = round($p_power / 10, 2);
                    break;
                default:
                    $p_power = round($p_power / 1000, 2);
                    break;
            }
            if ($p_power > 0) {
                $result .= ' P: ' . $p_power . ' W';
            }
        }
    }

    if (isset($poe_current)) {
        $c_current = get_snmp($ip, $community, $version, $poe_current);
        if (isset($c_current)) {
            $p_current = parse_snmp_value($c_current);
            if ($p_current > 0) {
                $result .= ' C: ' . $p_current . ' mA';
            }
        }
    }

    if (isset($poe_volt)) {
        $c_volt = get_snmp($ip, $community, $version, $poe_volt);
        if (isset($c_volt)) {
            $p_volt = parse_snmp_value($c_volt);
            switch ($vendor_id) {
                case 2:
                case 8:
                    $p_volt = round($p_volt / 1000, 2);
                    break;
                case 9:
                case 69:
                    $p_volt = round($p_volt / 10, 2);
                    break;
                case 15:
                    $p_volt = round($p_volt / 100, 2);
                    break;
            }
            if ($p_volt > 0 and $p_power > 0) {
                $result .= ' V: ' . $p_volt . " V";
            }
        }
    }

    if (isset($poe_class)) {
        $c_class = get_snmp($ip, $community, $version, $poe_class);
        if (isset($c_class)) {
            $p_class = parse_snmp_value($c_class);
            switch ($vendor_id) {
                case 69:
                    if ($p_class > 0 and $p_power > 0) {
                        if ($p_class == 7) {
                            $p_class = 'class-not-defined';
                        }
                        $result .= ' Class: ' . $p_class;
                    }
                    break;
                default:
                    if ($p_class > 0 and $p_power > 0) {
                        $result .= ' Class: ' . ($p_class - 1);
                    }
                    break;
            }
        }
    }

    return $result;
}

function get_snmp($ip, $community, $version, $oid)
{
    snmp_set_oid_output_format(SNMP_OID_OUTPUT_NUMERIC);
    if ($version == 2) {
        $result = snmp2_get($ip, $community, $oid, SNMP_timeout, SNMP_retry);
    }
    if ($version == 1) {
        $result = snmpget($ip, $community, $oid, SNMP_timeout, SNMP_retry);
    }
    return $result;
}

function set_snmp($ip, $community, $version, $oid, $field, $value)
{
    if ($version == 2) {
        $result = snmp2_set($ip, $community, $oid, $field, $value, SNMP_timeout, SNMP_retry);
    }
    if ($version == 1) {
        $result = snmpset($ip, $community, $oid, $field, $value, SNMP_timeout, SNMP_retry);
    }
    return $result;
}

function set_port_state($vendor_id, $port, $ip, $community, $version, $state)
{
    // port -> snmp_index!!!
    if (!isset($port)) {
        return;
    }
    if (!isset($ip)) {
        return;
    }
    if (!isset($community)) {
        $community = 'public';
    }
    if (!isset($version)) {
        $version = '2';
    }
    $port_status = PORT_ADMIN_STATUS_OID . $port;
    if ($state == 1) {
        // enable port
        $c_state = set_snmp($ip, $community, $version, $port_status, 'i', 1);
        return $c_state;
    } else {
        // disable port
        $c_state = set_snmp($ip, $community, $version, $port_status, 'i', 2);
        return $c_state;
    }
}

function set_port_for_group($db, $group_id, $place_id, $state)
{
    $authSQL = 'SELECT User_auth.id,User_auth.dns_name,User_auth.ip FROM User_auth, User_list WHERE User_auth.user_id = User_list.id AND User_auth.deleted=0 and User_list.ou_id=' . $group_id;
    $auth_list = mysqli_query($db, $authSQL);
    LOG_INFO($db, 'Mass port state change started!');
    // get auth list for group
    while (list($a_id, $a_name, $a_ip) = mysqli_fetch_array($auth_list)) {
        // get device and port for auth
        if ($place_id == 0) {
            $place_filter = '';
        } else {
            $place_filter = 'D.building_id=' . $place_id . ' and ';
        }
        $devSQL = 'SELECT D.id, D.device_name, D.vendor_id, D.device_model, D.ip, D.snmp_version, D.rw_community, DP.port, DP.snmp_index  FROM devices AS D, device_ports AS DP, connections AS C WHERE ' . $place_filter . ' D.id = DP.device_id AND DP.id = C.port_id AND C.auth_id=' . $a_id . ' LIMIT 1';
        $dev_info = mysqli_query($db, $devSQL);
        list($d_id, $d_name, $d_vendor_id, $d_model, $d_ip, $d_snmp, $d_community, $d_port, $d_snmp_index) = mysqli_fetch_array($dev_info);
        if (!isset($d_id)) {
            continue;
        }
        if ($state == 1) {
            $mode = 'enable';
            run_sql($db, "Update User_auth set nagios_handler='restart-port' WHERE id=$a_id and nagios_handler='manual-mode'");
        } else {
            $mode = 'disable';
            run_sql($db, "Update User_auth set nagios_handler='manual-mode' WHERE id=$a_id and nagios_handler='restart-port'");
        }
        LOG_INFO($db, "At device $d_name [$d_ip] $mode port $d_port for auth_id: $a_id ($a_ip [$a_name])");
        set_port_state($d_vendor_id, $d_snmp_index, $d_ip, $d_community, $d_snmp, $state);
        set_port_poe_state($d_vendor_id, $d_port, $d_snmp_index, $d_ip, $d_community, $d_snmp, $state);
    }
    LOG_INFO($db, 'Mass port state change stopped.');
}

function get_vendor($db, $mac)
{
    $mac = mac_dotted($mac);
    $vendor = get_record_sql($db, 'SELECT companyName,companyAddress FROM mac_vendors WHERE oui="' . substr($mac, 0, 8) . '" or oui="' . substr($mac, 0, 11) . '"');
    $result = '';
    if (!empty($vendor)) {
        $result = $vendor['companyName'];
        if (!empty($vendor['companyAddress'])) {
            $result = $vendor['companyAddress'];
        }
    }
    return $result;
}

function get_port_state_detail($port, $ip, $community, $version)
{
    if (!isset($port)) {
        return;
    }
    if (!isset($ip)) {
        return;
    }
    if (!isset($community)) {
        $community = 'public';
    }
    if (!isset($version)) {
        $version = '2';
    }
    // if (!is_up($ip)) { return; }

    $oper = PORT_STATUS_OID . $port;
    $admin = PORT_ADMIN_STATUS_OID . $port;
    $speed = PORT_SPEED_OID . $port;
    $errors = PORT_ERRORS_OID . $port;
    $result = '';
    $c_state = get_snmp($ip, $community, $version, $oper);
    $p_state = parse_snmp_value($c_state);
    $c_admin = get_snmp($ip, $community, $version, $admin);
    $p_admin = parse_snmp_value($c_admin);
    if ($p_state == 1) {
        $c_speed = get_snmp($ip, $community, $version, $speed);
    } else {
        $c_speed = 'INT:0';
    }
    $p_speed = parse_snmp_value($c_speed);
    $c_errors = get_snmp($ip, $community, $version, $errors);
    $p_errors = parse_snmp_value($c_errors);
    $result = $p_state . ";" . $p_admin . ";" . $p_speed . ";" . $p_errors;
    return $result;
}

function parse_snmp_value($value)
{
    if (empty($value)) {
        return NULL;
    }
    list($p_type, $p_value) = explode(':', $value);
    $p_value = trim($p_value);
    $p_value = preg_replace('/^\"/', '', $p_value);
    $p_value = preg_replace('/\"$/', '', $p_value);
    $p_value = trim($p_value);
    return $p_value;
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
    $mac = preg_replace('/(\S{2})(\S{2})(\S{2})(\S{2})(\S{2})(\S{2})/', '$1:$2:$3:$4:$5:$6', $mac);
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
    $result = ' <p title="' . $vendor_info . '"><a href=/admin/logs/mac.php?mac=' . $mac . '>' . $mac . '</a></p>';
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
    $user_replace = '<a href=/admin/users/edituser.php?id=${2}>user_id:${2}</a>';
    $result = preg_replace($user_pattern, $user_replace, $result);

    $mac_pattern = '/\s+\[(\w{12})\]\s+/i';
    preg_match($mac_pattern, $result, $matches);
    if (isset($matches[1])) {
        $mac = $matches[1];
        $mac = mac_dotted($mac);
        #        $vendor_info = get_vendor($db,$mac);
        #        $mac_replace = ' <p title="'.$vendor_info.'"><a href=/admin/logs/mac.php?mac='.$mac.'>'.$mac.'</a></p>';
        $mac_replace = ' <a href=/admin/logs/mac.php?mac=' . $mac . '>' . $mac . '</a> ';
        $result = preg_replace($mac_pattern, $mac_replace, $result);
    }

    $mac_pattern = '/\s+mac:\s+([\w\:]{17})$/i';
    preg_match($mac_pattern, $result, $matches);
    if (isset($matches[1])) {
        $mac = $matches[1];
        $mac = mac_dotted($mac);
        #        $vendor_info = get_vendor($db,$mac);
        #        $mac_replace = ' mac: <p title="'.$vendor_info.'"><a href=/admin/logs/mac.php?mac='.$mac.'>'.$mac.'</a></p>';
        $mac_replace = ' mac: <a href=/admin/logs/mac.php?mac=' . $mac . '>' . $mac . '</a> ';
        $result = preg_replace($mac_pattern, $mac_replace, $result);
    }

    $device_pattern = '/at device\s+([\w\.\-]+)/i';
    preg_match($device_pattern, $result, $matches);
    if (isset($matches[1])) {
        $device_name = $matches[1];
        $device_id = get_device_id($db, $device_name);
        if (isset($device_id) and $device_id > 0) {
            $device_replace = 'at device <a href=/admin/devices/editdevice.php?id=' . $device_id . '>${1}</a>';
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

function get_record_field($db, $table, $field, $filter)
{
    if (!isset($table)) {
        LOG_ERROR($db, "Search in unknown table! Skip command.");
        return;
    }
    if (!isset($filter)) {
        LOG_ERROR($db, "Search filter is empty! Skip command.");
        return;
    }
    if (!isset($field)) {
        LOG_ERROR($db, "Search field is empty! Skip command.");
        return;
    }
    if (preg_match('/=$/', $filter)) {
        LOG_ERROR($db, "Search record ($table) with illegal filter $filter! Skip command.");
        return;
    }
    $old_sql = "SELECT $field FROM $table WHERE $filter LIMIT 1";
    $old_record = mysqli_query($db, $old_sql) or LOG_ERROR($db, "SQL: $old_sql :" . mysqli_error($db));
    $old = mysqli_fetch_array($old_record, MYSQLI_ASSOC);
    foreach ($old as $key => $value) {
        if (!isset($value) or $value === 'NULL') {
            $value = '';
        }
        $result[$key] = $value;
    }
    return $result[$field];
}

function get_record($db, $table, $filter)
{
    if (!isset($table)) {
        LOG_ERROR($db, "Search in unknown table! Skip command.");
        return;
    }
    if (!isset($filter)) {
        LOG_ERROR($db, "Search filter is empty! Skip command.");
        return;
    }
    if (preg_match('/=$/', $filter)) {
        LOG_ERROR($db, "Search record ($table) with illegal filter $filter! Skip command.");
        return;
    }
    $old_sql = "SELECT * FROM $table WHERE $filter LIMIT 1";
    $old_record = mysqli_query($db, $old_sql) or LOG_ERROR($db, "SQL: $old_sql :" . mysqli_error($db));
    $old = mysqli_fetch_array($old_record, MYSQLI_ASSOC);
    $result = NULL;
    if (!empty($old)) {
        foreach ($old as $key => $value) {
            if (!isset($value) or $value === 'NULL') {
                $value = '';
            }
            if (!empty($key)) {
                $result[$key] = $value;
            }
        }
    }
    return $result;
}

function get_records($db, $table, $filter)
{
    if (!isset($table)) {
        LOG_ERROR($db, "Search in unknown table! Skip command.");
        return;
    }
    if (isset($filter) and preg_match('/=$/', $filter)) {
        LOG_ERROR($db, "Search record ($table) with illegal filter $filter! Skip command.");
        return;
    }
    $s_filter = '';
    if (isset($filter)) {
        $s_filter = 'WHERE ' . $filter;
    }
    $old_sql = "SELECT * FROM $table $s_filter";
    $old_record = mysqli_query($db, $old_sql) or LOG_ERROR($db, "SQL: $old_sql :" . mysqli_error($db));
    $result = NULL;
    $index = 0;
    while ($old = mysqli_fetch_array($old_record, MYSQLI_ASSOC)) {
        foreach ($old as $key => $value) {
            if (!isset($value) or $value === 'NULL') {
                $value = '';
            }
            $result[$index][$key] = $value;
        }
        $index++;
    }
    return $result;
}

function get_records_sql($db, $sql)
{
    if (empty($sql)) {
        LOG_ERROR($db, "Empty query! Skip command.");
        return;
    }
    $record = mysqli_query($db, $sql) or LOG_ERROR($db, "SQL: $sql :" . mysqli_error($db));
    $index = 0;
    $result = NULL;
    while ($rec = mysqli_fetch_array($record, MYSQLI_ASSOC)) {
        foreach ($rec as $key => $value) {
            if (!isset($value) or $value === 'NULL') {
                $value = '';
            }
            if (!empty($key)) {
                $result[$index][$key] = $value;
            }
        }
        $index++;
    }
    return $result;
}

function get_record_sql($db, $sql)
{
    $result = NULL;
    if (!isset($sql)) {
        LOG_ERROR($db, "Empty query! Skip command.");
        return $result;
    }
    $record = mysqli_query($db, $sql . " LIMIT 1") or LOG_ERROR($db, "SQL: $sql LIMIT 1: " . mysqli_error($db));
    if (!isset($record)) {
        return $result;
    }
    $rec = mysqli_fetch_array($record, MYSQLI_ASSOC);
    if (!empty($rec)) {
        foreach ($rec as $key => $value) {
            if (!isset($value) or $value === 'NULL') {
                $value = '';
            }
            $result[$key] = $value;
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

function update_record($db, $table, $filter, $newvalue)
{
    if (isRO($db, $table)) {
        LOG_ERROR($db, "User does not have write permission");
        return;
    }
    if (!isset($table)) {
        LOG_ERROR($db, "Change record for unknown table! Skip command.");
        return;
    }
    if (!isset($filter)) {
        LOG_ERROR($db, "Change record ($table) with empty filter! Skip command.");
        return;
    }
    if (preg_match('/=$/', $filter)) {
        LOG_ERROR($db, "Change record ($table) with illegal filter $filter! Skip command.");
        return;
    }
    if (!isset($newvalue)) {
        LOG_ERROR($db, "Change record ($table [ $filter ]) with empty data! Skip command.");
        return;
    }
    $old_sql = "SELECT * FROM $table WHERE $filter";
    $old_record = mysqli_query($db, $old_sql) or LOG_ERROR($db, "SQL: $old_sql :" . mysqli_error($db));
    $old = mysqli_fetch_array($old_record, MYSQLI_ASSOC);
    $changed_log = '';
    $run_sql = '';
    $network_changed = 0;

    $acl_fields = [
        'ip' => '1',
        'ip_int' => '1',
        'enabled' => '1',
        'dhcp' => '1',
        'filter_group_id' => '1',
        'deleted' => '1',
        'dhcp_acl' => '1',
        'queue_id' => '1',
        'mac' => '1',
        'blocked' => '1',
    ];

    foreach ($newvalue as $key => $value) {
        if (!isset($value)) {
            $value = '';
        }
        $value = trim($value);
        if (strcmp($old[$key], $value) == 0) {
            continue;
        }
        if ($table === "User_auth") {
            if (!empty($acl_fields["$key"])) {
                $network_changed = 1;
            }
        }
        $changed_log = $changed_log . " $key => $value (old: $old[$key]),";
        $run_sql = $run_sql . " `" . $key . "`='" . mysqli_real_escape_string($db, $value) . "',";
    }
    if (empty($run_sql)) {
        return;
    }

    if ($network_changed) {
        $run_sql = $run_sql . " `changed`='1',";
    }

    $changed_log = substr_replace($changed_log, "", -1);
    $run_sql = substr_replace($run_sql, "", -1);

    if ($table === 'User_auth') {
        $changed_time = GetNowTimeString();
        $run_sql = $run_sql . ", `changed_time`='" . $changed_time . "'";
    }

    $new_sql = "UPDATE $table SET $run_sql WHERE $filter";
    LOG_DEBUG($db, "Run sql: $new_sql");
    $sql_result = mysqli_query($db, $new_sql) or LOG_ERROR($db, "SQL: $new_sql :" . mysqli_error($db));
    if (!$sql_result) {
        LOG_ERROR($db, "UPDATE Request: $new_sql :" . mysqli_error($db));
        return;
    }
    if ($table !== "sessions") {
        LOG_VERBOSE($db, "Change table $table WHERE $filter set $changed_log");
    }
}

function delete_record($db, $table, $filter)
{
    if (isRO($db, $table)) {
        LOG_ERROR($db, "User does not have write permission");
        return;
    }
    if (!isset($table)) {
        LOG_ERROR($db, "Delete FROM unknown table! Skip command.");
        return;
    }
    if (!isset($filter)) {
        LOG_ERROR($db, "Delete FROM table $table with empty filter! Skip command.");
        return;
    }
    if (preg_match('/=$/', $filter)) {
        LOG_ERROR($db, "Change record ($table) with illegal filter $filter! Skip command.");
        return;
    }
    $old_sql = "SELECT * FROM $table WHERE $filter";
    $old_record = mysqli_query($db, $old_sql) or LOG_ERROR($db, "SQL: $old_sql :" . mysqli_error($db));
    $old = mysqli_fetch_array($old_record, MYSQLI_ASSOC);
    $changed_log = 'record: ';
    if (!empty($old)) {
        foreach ($old as $key => $value) {
            if (!isset($value)) {
                $value = '';
            }
            $changed_log = $changed_log . " $key => $value,";
        }
    }
    //never delete user ip record
    if ($table === 'User_auth') {
        $changed_time = GetNowTimeString();
        $new_sql = "UPDATE $table SET deleted=1, changed=1, `changed_time`='" . $changed_time . "' WHERE $filter";
        LOG_DEBUG($db, "Run sql: $new_sql");
        $sql_result = mysqli_query($db, $new_sql) or LOG_ERROR($db, "SQL: $new_sql :" . mysqli_error($db));
        if (!$sql_result) {
            LOG_ERROR($db, "UPDATE Request (from delete): " . mysqli_error($db));
            return;
        }
    } else {
        $new_sql = "DELETE FROM $table WHERE $filter";
        LOG_DEBUG($db, "Run sql: $new_sql");
        $sql_result = mysqli_query($db, $new_sql) or LOG_ERROR($db, "SQL: $new_sql :" . mysqli_error($db));
        if (!$sql_result) {
            LOG_ERROR($db, "DELETE Request: $new_sql : " . mysqli_error($db));
            return;
        }
    }
    if ($table !== "sessions") {
        LOG_VERBOSE($db, "Delete FROM table $table WHERE $filter $changed_log");
    }
    return $changed_log;
}

function insert_record($db, $table, $newvalue)
{
    if (isRO($db, $table)) {
        LOG_ERROR($db, "User does not have write permission");
        return;
    }
    if (!isset($table)) {
        LOG_ERROR($db, "Create record for unknown table! Skip command.");
        return;
    }
    if (empty($newvalue)) {
        LOG_ERROR($db, "Create record ($table) with empty data! Skip command.");
        return;
    }

    $changed_log = '';
    $field_list = '';
    $value_list = '';
    foreach ($newvalue as $key => $value) {
        if (empty($value) and $value !== 0) {
            $value = '';
        }
        $changed_log = $changed_log . " $key => $value,";
        $field_list = $field_list . "`" . $key . "`,";
        $value = trim($value);
        $value_list = $value_list . "'" . mysqli_real_escape_string($db, $value) . "',";
    }
    if (empty($value_list)) {
        return;
    }
    $changed_log = substr_replace($changed_log, "", -1);
    $field_list = substr_replace($field_list, "", -1);
    $value_list = substr_replace($value_list, "", -1);
    $new_sql = "insert into $table(" . $field_list . ") values(" . $value_list . ")";
    LOG_DEBUG($db, "Run sql: $new_sql");
    $sql_result = mysqli_query($db, $new_sql) or LOG_ERROR($db, "SQL: $new_sql :" . mysqli_error($db));
    if (!$sql_result) {
        LOG_ERROR($db, "INSERT Request:" . mysqli_error($db));
        return;
    }
    $last_id = mysqli_insert_id($db);
    if ($table !== "sessions") {
        LOG_VERBOSE($db, "Create record in table $table: $changed_log with id: $last_id");
    }
    if ($table === 'User_auth') {
        run_sql($db, "UPDATE User_auth SET changed=1 WHERE id=" . $last_id);
    }
    return $last_id;
}

function get_rec_str($array)
{
    $result = '';
    foreach ($array as $key => $value) {
        $result .= "[" . $key . "]=" . $value . ", ";
    }
    $result = preg_replace('/,\s+$/', '', $result);
    return $result;
}

function get_diff_rec($db, $table, $filter, $newvalue, $only_changed)
{
    if (!isset($table)) {
        return;
    }
    if (!isset($filter)) {
        return;
    }
    if (!isset($newvalue)) {
        return;
    }

    if (!isset($only_changed)) {
        $only_changed = 0;
    }

    $old_sql = "SELECT * FROM $table WHERE $filter";
    $old_record = mysqli_query($db, $old_sql) or LOG_ERROR($db, "SQL: $old_sql :" . mysqli_error($db));
    $old = mysqli_fetch_array($old_record, MYSQLI_ASSOC);
    $changed_log = "\r\n";
    foreach ($newvalue as $key => $value) {
        if (strcmp($old[$key], $value) !== 0) {
            $changed_log = $changed_log . " $key => cur: $value old: $old[$key],\r\n";
        }
    }
    $old_record = '';
    if (!$only_changed) {
        $old_record = "\r\n Has not changed:\r\n";
        foreach ($old as $key => $value) {
            if (!empty($newvalue[$key])) {
                $old_record = $old_record . " $key = $value,\r\n";
            }
        }
        $old_record = substr_replace($old_record, "", -3);
    }
    // print $changed_log;
    return $changed_log . $old_record;
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
        print "<option value=$value selected>$description</option>";
    } else {
        print "<option value=$value>$description</option>";
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
    print "<select name='" . $name . "'>\n";
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
        return;
    }
    $v_char = "?";
    if (preg_match('/\.php\?/', $url)) {
        $v_char = "&";
    }
    #две назад
    print "<div align=left class=records >";
    if (($page - 2) > 0) :
        $pagetwoleft = "<a class='first_page_link' href=" . $url . $v_char . "page=" . ($page - 2) . ">" . ($page - 2) . "</a>  ";
    else :
        $pagetwoleft = null;
    endif;

    #одна назад
    if (($page - 1) > 0) :
        $pageoneleft = "<a class='first_page_link' href=" . $url . $v_char . "page=" . ($page - 1) . ">" . ($page - 1) . "</a>  ";
        $pagetemp = ($page - 1);
    else :
        $pageoneleft = null;
        $pagetemp = null;
    endif;

    #две вперед
    if (($page + 2) <= $total) :
        $pagetworight = "  <a class='first_page_link' href=" . $url . $v_char . "page=" . ($page + 2) . ">" . ($page + 2) . "</a>";
    else :
        $pagetworight = null;
    endif;

    #одна вперед
    if (($page + 1) <= $total) :
        $pageoneright = "  <a class='first_page_link' href=" . $url . $v_char . "page=" . ($page + 1) . ">" . ($page + 1) . "</a>";
        $pagetemp2 = ($page + 1);
    else :
        $pageoneright = null;
        $pagetemp2 = null;
    endif;

    # в начало
    if ($page != 1 && $pagetemp != 1 && $pagetemp != 2) :
        $pagerevp = "<a href=" . $url . $v_char . "page=1 class='first_page_link' title='В начало'><<</a> ";
    else :
        $pagerevp = null;
    endif;

    #в конец (последняя)
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

    //personal user rules
    //ip
    if (!empty($ip)) {
        $t_rules = get_records_sql($db, "SELECT * FROM auth_rules WHERE type=1 and LENGTH(rule)>0 AND user_id IS NOT NULL");
        foreach ($t_rules as $row) {
            if (!empty($row['rule']) and is_subnet_aton($row['rule'], $ip_aton)) {
                $result['user_id'] = $row['user_id'];
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
            }
        }
    }
    //hostname
    if (!empty($hostname)) {
        $mac_rules = get_records_sql($db, "SELECT * FROM auth_rules WHERE type=3 AND LENGTH(rule)>0 AND user_id IS NOT NULL");
        foreach ($mac_rules as $row) {
            if (!empty($row['rule']) and preg_match($row['rule'], $hostname)) {
                $result['user_id'] = $row['user_id'];
            }
        }
    }

    if (!empty($result['user_id'])) {
        return $result;
    }

    //ou rules
    //ip
    if (!empty($ip)) {
        if (is_hotspot($db, $ip)) {
            $result['ou_id'] = get_const('default_hotspot_ou_id');
        }
        $t_rules = get_records_sql($db, "SELECT * FROM auth_rules WHERE type=1 and LENGTH(rule)>0 AND ou_id IS NOT NULL");
        foreach ($t_rules as $row) {
            if (!empty($row['rule']) and is_subnet_aton($row['rule'], $ip_aton)) {
                $result['ou_id'] = $row['ou_id'];
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
            }
        }
    }
    //hostname
    if (!empty($hostname)) {
        $mac_rules = get_records_sql($db, "SELECT * FROM auth_rules WHERE type=3 AND LENGTH(rule)>0 AND ou_id IS NOT NULL");
        foreach ($mac_rules as $row) {
            if (!empty($row['rule']) and preg_match($row['rule'], $hostname)) {
                $result['ou_id'] = $row['ou_id'];
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

$config["org_name"] = get_option($db_link, 32);

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
$config["mac_discovery"] = get_option($db_link, 17);
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

snmp_set_valueretrieval(SNMP_VALUE_LIBRARY);
snmp_set_enum_print(1);
