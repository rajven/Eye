<?php
if (! defined("CONFIG")) { die("Not defined"); }

require_once ($_SERVER['DOCUMENT_ROOT']."/inc/class.simple.mail.php");

$config["init"]=0;

#ValidIpAddressRegex = "^(([0-9]|[1-9][0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5])\.){3}([0-9]|[1-9][0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5])$";
#ValidHostnameRegex = "^(([a-zA-Z0-9]|[a-zA-Z0-9][a-zA-Z0-9\-]*[a-zA-Z0-9])\.)*([A-Za-z0-9]|[A-Za-z0-9][A-Za-z0-9\-]*[A-Za-z0-9])$";
#$ValidMacAddressRegex="^([0-9A-Fa-f]{2}[:-]){5}[0-9A-Fa-f]{2}|([0-9a-fA-F]{4}[\\.-][0-9a-fA-F]{4}[\\.-][0-9a-fA-F]{4})|[0-9A-Fa-f]{12}$";

$port_status_oid = '.1.3.6.1.2.1.2.2.1.8.';
$port_admin_status_oid = '.1.3.6.1.2.1.2.2.1.7.';
$port_speed_oid = '.1.3.6.1.2.1.2.2.1.5.';
$port_errors_oid = '.1.3.6.1.2.1.2.2.1.14.';
$port_vlan_oid = '.1.3.6.1.2.1.17.7.1.4.5.1.1.';

$mac_table_oid = '.1.3.6.1.2.1.17.7.1.2.2.1.2';
$mac_table_oid2 = '.1.3.6.1.2.1.17.4.3.1.2';

$eltex_sfp_status = '.1.3.6.1.4.1.89.90.1.2.1.3';
$eltex_sfp_vendor = '.1.3.6.1.4.1.35265.1.23.53.1.1.1.5';
$eltex_sfp_sn = '.1.3.6.1.4.1.35265.1.23.53.1.1.1.6';
$eltex_sfp_freq = '.1.3.6.1.4.1.35265.1.23.53.1.1.1.4';
$eltex_sfp_length = '.1.3.6.1.4.1.35265.1.23.53.1.1.1.8';

$cisco_descr = '.1.3.6.1.2.1.1.1.0';
$cisco_modules = '.1.3.6.1.2.1.47.1.1.1.1.7';
$cisco_sfp_sensors = '.1.3.6.1.4.1.9.9.91.1.1.1.1.4';
$cisco_sfp_precision = '.1.3.6.1.4.1.9.9.91.1.1.1.1.3';
$cisco_vlan_oid = '.1.3.6.1.4.1.9.9.9.46.1.3.1.1.2';

$ifmib_ifindex  = '.1.3.6.1.2.1.2.2.1.1';
$ifmib_ifdescr  = '.1.3.6.1.2.1.2.2.1.2';
$ifmib_ifname   = '.1.3.6.1.2.1.31.1.1.1.1';

$huawei_sfp_vendor = '.1.3.6.1.4.1.2011.5.25.31.1.1.2.1.11';
$huawei_sfp_speed = '.1.3.6.1.4.1.2011.5.25.31.1.1.2.1.2';
$huawei_sfp_volt = '.1.3.6.1.4.1.2011.5.25.31.1.1.3.1.6';
$huawei_sfp_optrx = '.1.3.6.1.4.1.2011.5.25.31.1.1.3.1.32';
$huawei_sfp_opttx = '.1.3.6.1.4.1.2011.5.25.31.1.1.3.1.33';
$huawei_sfp_biascurrent = '.1.3.6.1.4.1.2011.5.25.31.1.1.3.1.31';
$huawei_sfp_rx = '.1.3.6.1.4.1.2011.5.25.31.1.1.3.1.8';
$huawei_sfp_tx = '.1.3.6.1.4.1.2011.5.25.31.1.1.3.1.9';

$pethPsePortAdminEnable = '.1.3.6.1.2.1.105.1.1.1.3.1';
$huawei_poe_oid = '.1.3.6.1.4.1.2011.5.25.195.3.1.3';
$allied_poe_oid = '.1.3.6.1.2.1.105.1.1.1.3.1';
$hp_poe_oid = '.1.3.6.1.2.1.105.1.1.1.3.1';
$netgear_poe_oid = '.1.3.6.1.4.1.4526.11.15.1.1.1.6.1';
$mikrotik_poe_oid = '.1.3.6.1.4.1.14988.1.1.15.1.1.3';

// interface id
$mikrotik_poe_int = '.1.3.6.1.4.1.14988.1.1.15.1.1.1';
// interface names
$mikrotik_poe_int_names = '.1.3.6.1.4.1.14988.1.1.15.1.1.2';
// voltage in dV (decivolt)
$mikrotik_poe_volt = '.1.3.6.1.4.1.14988.1.1.15.1.1.4';
// current in mA
$mikrotik_poe_current = '.1.3.6.1.4.1.14988.1.1.15.1.1.5';
// power usage in dW (deviwatt)
$mikrotik_poe_usage = '.1.3.6.1.4.1.14988.1.1.15.1.1.6';

$sysinfo_mib = '.1.3.6.1.2.1.1';

$L_ERROR = 0;
$L_WARNING = 1;
$L_INFO = 2;
$L_VERBOSE = 3;
$L_DEBUG = 255;

$log_level = 2;

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

$admin_email = "admin";
$sender_email = "root";
$send_email = 0;



$mac_table_str_oid = '.1.3.6.1.2.1.17.4.3.1.2';
$mac_table_str_oid2 = '1.3.6.1.2.1.17.7.1.2.2.1.2';

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
    $units = array(
        "",
        "k",
        "M",
        "G",
        "T"
    );
    $KB = 1024;
    if ($traff) {
        $index = min(((int) log($traff, $KB)), count($units) - 1);
        $result = round($traff / pow($KB, $index), 3) . ' ' . $units[$index] . 'b';
    } else {
        $result = '0 b';
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
    $KB = 1000;
    if ($packets) {
        $index = min(((int) log($packets, $KB)), count($units) - 1);
        $result = round($packets / pow($KB, $index), 3) . ' ' . $units[$index] . 'pkt/s';
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
    if (! preg_match($ip_pattern, $cidr)) {
        $return = FALSE;
    } else {
        $return = TRUE;
    }

    if ($return == TRUE) {
        $parts = explode("/", $cidr);
        $ip = $parts[0];
        if (empty($parts[1])) { $parts[1]="32"; }
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

function checkValidMac($mac) {
$ValidMacAddressRegex="/^([0-9A-Fa-f]{2}[:-]){5}[0-9A-Fa-f]{2}|([0-9a-fA-F]{4}[\\.-][0-9a-fA-F]{4}[\\.-][0-9a-fA-F]{4})|[0-9A-Fa-f]{12}$/";
if (! preg_match($ValidMacAddressRegex, $mac)) { $return = FALSE; } else { $return = TRUE; }
return $return;
}

function checkValidHostname($dnsname)
{
$host_pattern="/^(([a-zA-Z0-9]|[a-zA-Z0-9][a-zA-Z0-9\-]*[a-zA-Z0-9])\.)*([A-Za-z0-9]|[A-Za-z0-9][A-Za-z0-9\-]*[A-Za-z0-9])$/";
    if (! preg_match($host_pattern, $dnsname)) {
        $result = FALSE;
    } else {
        $result = TRUE;
    }
return $result;
}

function checkUniqHostname($db,$id,$hostname)
{
$count=get_count_records($db,'User_auth','deleted=0 and id !="'.$id.'" and dns_name ="'.mysqli_real_escape_string($db,$hostname).'"');
if ($count >0 ) { return FALSE; }
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
    if (! isset($cidr[1])) { $cidr[1] = 32; }
    $start=(ip2long($cidr[0])) & ((- 1 << (32 - (int) $cidr[1])));
    $stop=$start + pow(2, (32 - (int) $cidr[1])) - 1;
    $range[0] = long2ip($start);
    $range[1] = long2ip($stop);
    $range[2] = $cidr;
#dhcp
    $dhcp_size=round(($stop-$start)/2,PHP_ROUND_HALF_UP);
    $dhcp_start=$start+round($dhcp_size/2,PHP_ROUND_HALF_UP);
    $range[3]=long2ip($dhcp_start);
    $range[4]=long2ip($dhcp_start+$dhcp_size);
    $range[5]=long2ip($start+1);
    return $range;
}

function print_ou_select($db, $ou_name, $ou_value)
{
    print "<select name=\"$ou_name\" class=\"js-select-single\">\n";
    $t_ou = mysqli_query($db, "SELECT id,ou_name FROM OU ORDER BY ou_name");
    while (list ($f_ou_id, $f_ou_name) = mysqli_fetch_array($t_ou)) {
	print_select_item($f_ou_name,$f_ou_id,$ou_value);
    }
    print "</select>\n";
}

function print_ou_set($db, $ou_name, $ou_value)
{
    print "<select name=\"$ou_name\" class=\"js-select-single\">\n";
    $t_ou = mysqli_query($db, "SELECT id,ou_name FROM OU WHERE id>=1 ORDER BY ou_name");
    while (list ($f_ou_id, $f_ou_name) = mysqli_fetch_array($t_ou)) {
	print_select_item($f_ou_name,$f_ou_id,$ou_value);
    }
    print "</select>\n";
}

function print_subnet_select($db, $subnet_name, $subnet_value)
{
    print "<select name=\"$subnet_name\" >\n";
    $t_subnet = mysqli_query($db, "SELECT id,subnet FROM subnets ORDER BY ip_int_start");
    print_select_item('Всe ip',0,$subnet_value);
    while (list ($f_subnet_id, $f_subnet_name) = mysqli_fetch_array($t_subnet)) {
	print_select_item($f_subnet_name,$f_subnet_id,$subnet_value);
    }
    print "</select>\n";
}

function print_subnet_select_office($db, $subnet_name, $subnet_value)
{
    print "<select name=\"$subnet_name\" >\n";
    $t_subnet = mysqli_query($db, "SELECT id,subnet FROM subnets WHERE office=1 ORDER BY ip_int_start");
    print_select_item('Всe ip',0,$subnet_value);
    while (list ($f_subnet_id, $f_subnet_name) = mysqli_fetch_array($t_subnet)) {
	print_select_item($f_subnet_name,$f_subnet_id,$subnet_value);
    }
    print "</select>\n";
}

function print_loglevel_select($item_name, $value)
{
    print "<select name=\"$item_name\">\n";
    global $L_INFO;
    global $L_WARNING;
    global $L_ERROR;
    global $L_VERBOSE;
    global $L_DEBUG;
    print_select_item('Error',$L_ERROR,$value);
    print_select_item('Warning',$L_WARNING,$value);
    print_select_item('Info',$L_INFO,$value);
    print_select_item('Verbose',$L_VERBOSE,$value);
    print_select_item('Debug',$L_DEBUG,$value);
    print "</select>\n";
}

function reencodeurl($url) {
$url_arr = explode('?', $url);
$fpage = $url_arr[0];
if (isset($url_arr[1])) {
    $params = $url_arr[1];
    $params_arr = explode('&', $params);
    $new_params = '';
    foreach ($params_arr as $row) {
        $param = explode ('=',$row);
        $key = $param[0]; 
        $value = urlencode(urldecode($param[1]));
        $new_params.="&".$key."=".$value;
        }
    $new_params = preg_replace('/^&/','',$new_params);
    } else { $new_params='='; }
if ($new_params === '=') { $new_url = $fpage; } else { $new_url = $fpage."?".$new_params; }
return $new_url;
}

function print_submenu_url($display_name,$page,$current_page,$last) {
$url_arr = explode('?', $page);
$fpage = $url_arr[0];
$new_url = reencodeurl($page);
if ($fpage === $current_page) { print "<b>$display_name</b>"; } else { print "<a href='".$new_url."'> $display_name </a>"; }
if (!isset($last) or $last==0) { print " | "; }
}

function print_url($display_name,$page) {
print "<a href='".reencodeurl($page)."'> $display_name </a>";
}

function print_log_submenu ($current_page) {
print "<div id='submenu'>\n";
print_submenu_url('Журнал dhcp','/admin/logs/dhcp.php',$current_page,0);
print_submenu_url('Журнал работы ','/admin/logs/index.php',$current_page,0);
print_submenu_url('Приключения маков','/admin/logs/mac.php',$current_page,0);
print_submenu_url('История ip-адресов','/admin/logs/ip.php',$current_page,0);
print_submenu_url('Неизвестные','/admin/logs/unknown.php',$current_page,0);
print_submenu_url('Трафик','/admin/logs/detaillog.php',$current_page,0);
print_submenu_url('syslog','/admin/logs/syslog.php',$current_page,1);
print "</div>\n";
}

function print_control_submenu ($current_page) {
print "<div id='submenu'>\n";
print_submenu_url('Управление','/admin/customers/control.php',$current_page,0);
print_submenu_url('Сети','/admin/customers/control-subnets.php',$current_page,0);
print_submenu_url('Сети (Статистика)','/admin/customers/control-subnets-usage.php',$current_page,0);
print_submenu_url('Параметры','/admin/customers/control-options.php',$current_page,0);
print_submenu_url('Пользователи','/admin/customers/index.php',$current_page,1);
print "</div>\n";
}

function print_filters_submenu ($current_page) {
print "<div id='submenu'>\n";
print_submenu_url('Список фильтров','/admin/filters/index.php',$current_page,0);
print_submenu_url('Группы фильтров','/admin/filters/groups.php',$current_page,1);
print "</div>\n";
}

function print_reports_submenu ($current_page) {
print "<div id='submenu'>\n";
print_submenu_url('Отчёт по трафику (ip)','/admin/reports/index-full.php',$current_page,0);
print_submenu_url('Отчёт по трафику (login)','/admin/reports/index.php',$current_page,1);
print "</div>\n";
}

function print_trafdetail_submenu ($current_page,$params,$description) {
print "<div id='submenu'>\n";
print "$description\n";
print_submenu_url('TOP 10 по трафику','/admin/reports/userdaydetail.php'."?$params",$current_page,0);
print_submenu_url('Подробный лог','/admin/reports/userdaydetaillog.php'."?$params",$current_page,1);
print "</div>\n";
}

function print_device_submenu ($current_page) {
print "<div id='submenu'>\n";
print_submenu_url('Активное сетевое оборудование','/admin/devices/index.php',$current_page,0);
print_submenu_url('Пассивное оборудование','/admin/devices/index-passive.php',$current_page,0);
print_submenu_url('Расположение','/admin/devices/building.php',$current_page,0);
print_submenu_url('Структура','/admin/devices/index-tree.php',$current_page,0);
print_submenu_url('Удалённые','/admin/devices/deleted.php',$current_page,0);
print_submenu_url('Модели устройств','/admin/devices/devmodels.php',$current_page,0);
print_submenu_url('Vendors','/admin/devices/devvendors.php',$current_page,0);
print_submenu_url('Порты по вланам','/admin/devices/portsbyvlan.php',$current_page,1);
print "</div>\n";
}

function print_editdevice_submenu ($current_page,$id) {
print "<div id='submenu'>\n";
$dev_id='';
if (isset($id)) { $dev_id='?id='.$id; }
print_submenu_url('Параметры','/admin/devices/editdevice.php'.$dev_id,$current_page,0);
print_submenu_url('Порты','/admin/devices/switchport.php'.$dev_id,$current_page,0);
print_submenu_url('Состояние','/admin/devices/switchstatus.php'.$dev_id,$current_page,0);
print_submenu_url('Соединения','/admin/devices/switchport-conn.php'.$dev_id,$current_page,1);
print "</div>\n";
}

function print_ip_submenu ($current_page) {
print "<div id='submenu'>\n";
print_submenu_url('Список адресов','/admin/iplist/index.php',$current_page,0);
print_submenu_url('Информация для nagios','/admin/iplist/nagios.php',$current_page,0);
print_submenu_url('Дубли','/admin/iplist/doubles.php',$current_page,0);
print_submenu_url('Удалённые адреса','/admin/iplist/deleted.php',$current_page,1);
print "</div>\n";
}

function get_nagios_name ($auth)
{
if (!empty($auth['dns_name'])) { return $auth['dns_name']; }
if (!empty($auth['dhcp_hostname'])) { return $auth['dhcp_hostname']; }
if (!empty($auth['comments'])) {
    $result = transliterate($auth['comments']);
    $result = preg_replace('/\(/', '-', $result);
    $result = preg_replace('/\)/', '-', $result);
    $result = preg_replace('/--/', '-', $result);
    return $result;
    }
if (empty($auth['login'])) { $auth['login']='host'; }
return $auth['login']."_".$auth['id'];
}

function get_ou($db, $ou_value)
{
    if (!isset($ou_value)) { return; }
    $ou_name = get_record_sql($db, "SELECT ou_name FROM OU WHERE id=$ou_value");
    if (empty($ou_name)) { return; }
    return $ou_name['ou_name'];
}

function get_device_model($db, $model_value)
{
    if (!isset($model_value)) { return; }
    $model_name = get_record_sql($db, "SELECT model_name FROM device_models WHERE id=$model_value");
    if (empty($model_name)) { return; }
    return $model_name['model_name'];
}

function get_device_model_name($db, $model_value)
{
    if (!isset($model_value)) { return ''; }
    $model_name = get_record_sql($db,"SELECT M.id,M.model_name,V.name FROM device_models M,vendors V WHERE M.vendor_id = V.id AND M.id=$model_value");
    if (empty($model_name)) { return ''; }
    return $model_name['name'].' '.$model_name['model_name'];
}

function get_device_model_vendor($db, $model_value)
{
    if (!isset($model_value)) { return ''; }
    $model_name = get_record_sql($db, "SELECT vendor_id FROM device_models WHERE id=$model_value");
    if (empty($model_name)) { return ''; }
    return $model_name['vendor_id'];
}

function get_building($db, $building_value)
{
    if (!isset($building_value)) { return; }
    $building_name = get_record_sql($db, "SELECT name FROM building WHERE id=$building_value");
    if (empty($building_name)) { return; }
    return $building_name['name'];
}

function print_device_model_select($db, $device_model_name, $device_model_value)
{
    print "<select name=\"$device_model_name\" class=\"js-select-single\" style=\"width: 100%\">\n";
    $t_device_model = mysqli_query($db, "SELECT M.id,M.model_name,V.name FROM device_models M,vendors V WHERE M.vendor_id = V.id ORDER BY V.name,M.model_name");
    while (list ($f_device_model_id, $f_device_model_name,$f_vendor_name) = mysqli_fetch_array($t_device_model)) {
	print_select_item($f_vendor_name." ".$f_device_model_name,$f_device_model_id,$device_model_value);
    }
    print "</select>\n";
}

function print_group_select($db, $group_name, $group_value)
{
    print "<select name=\"$group_name\">\n";
    $t_group = mysqli_query($db, "SELECT id,group_name FROM Group_list Order by group_name");
    while (list ($f_group_id, $f_group_name) = mysqli_fetch_array($t_group)) {
	print_select_item($f_group_name,$f_group_id,$group_value);
    }
    print "</select>\n";
}

function print_building_select($db, $building_name, $building_value)
{
    print "<select name=\"$building_name\">\n";
    print_select_item('Всё',0,$building_value);
    $t_building = mysqli_query($db, "SELECT id,name FROM building Order by name");
    while (list ($f_building_id, $f_building_name) = mysqli_fetch_array($t_building)) {
	print_select_item($f_building_name,$f_building_id,$building_value);
    }
    print "</select>\n";
}

function print_devtypes_select($db, $devtype_name, $devtype_value)
{
    print "<select name=\"$devtype_name\">\n";
    print_select_item('Всё',0,$devtype_value);
    $t_devtype = mysqli_query($db, "SELECT id,name FROM device_types Order by name");
    while (list ($f_devtype_id, $f_devtype_name) = mysqli_fetch_array($t_devtype)) {
	print_select_item($f_devtype_name,$f_devtype_id,$devtype_value);
    }
    print "</select>\n";
}

function print_devtype_select($db, $devtype_name, $devtype_value)
{
    print "<select name=\"$devtype_name\">\n";
    $t_devtype = mysqli_query($db, "SELECT id,name FROM device_types Order by name");
    while (list ($f_devtype_id, $f_devtype_name) = mysqli_fetch_array($t_devtype)) {
	print_select_item($f_devtype_name,$f_devtype_id,$devtype_value);
    }
    print "</select>\n";
}

function get_group($db, $group_value)
{
    list ($group_name) = mysqli_fetch_array(mysqli_query($db, "SELECT group_name FROM Group_list WHERE id=$group_value"));
    return $group_name;
}

function get_devtype_name($db, $device_type_id)
{
    list ($type_name) = mysqli_fetch_array(mysqli_query($db, "SELECT name FROM device_types WHERE id=$device_type_id"));
    return $type_name;
}

function get_l3_interfaces($db, $device_id)
{
    $wan='';
    $lan='';
    $t_l3int = mysqli_query($db, "SELECT name,interface_type FROM device_l3_interfaces WHERE device_id=$device_id ORDER BY name");
    while (list ($f_name,$f_type) = mysqli_fetch_array($t_l3int)) {
        if ($f_type==0) { $lan=$lan." ".$f_name; }
        if ($f_type==1) { $wan=$wan." ".$f_name; }
        }
    $wan=trim($wan);
    $lan=trim($lan);
    $result='';
    if (!empty($wan)) { $result.=' WAN: '.$wan.'<br>'; }
    if (!empty($lan)) { $result.=' LAN: '.$lan; }
    return trim($result);
}

function print_queue_select($db, $queue_name, $queue_value)
{
    print "<select name=\"$queue_name\">\n";
    $t_queue = mysqli_query($db, "SELECT id,queue_name FROM Queue_list Order by queue_name");
    while (list ($f_queue_id, $f_queue_name) = mysqli_fetch_array($t_queue)) {
	print_select_item($f_queue_name,$f_queue_id,$queue_value);
    }
    print "</select>\n";
}

function get_queue($db, $queue_value)
{
    list ($queue_name) = mysqli_fetch_array(mysqli_query($db, "SELECT queue_name FROM Queue_list WHERE id=$queue_value"));
    return $queue_name;
}

function print_qa_l3int_select($qa_name, $qa_value)
{
    print "<select name=\"$qa_name\">\n";
    print_select_item('Внутренний',0,$qa_value);
    print_select_item('Внешний',1,$qa_value);
    print "</select>\n";
}

function print_qa_rule_select($qa_name, $qa_value)
{
    print "<select name=\"$qa_name\">\n";
    print_select_item('Subnet',1,$qa_value);
    print_select_item('Mac',2,$qa_value);
    print_select_item('Hostname',3,$qa_value);
    print "</select>\n";
}

function print_qa_select($qa_name, $qa_value)
{
    print "<select name=\"$qa_name\">\n";
    print_select_item('Да',1,$qa_value);
    print_select_item('Нет',0,$qa_value);
    print "</select>\n";
}

function print_qa_select_ext($qa_name, $qa_value, $readonly)
{
    $state = '';
    if ($readonly) { $state='disabled=true'; }
    print "<select name=\"$qa_name\">\n";
    print_select_item_ext('Да',1,$qa_value, $readonly);
    print_select_item_ext('Нет',0,$qa_value, $readonly);
    print "</select>\n";
}

function print_snmp_select($qa_name, $qa_value)
{
    print "<select name=\"$qa_name\">\n";
    print_select_item('Disabled',0,$qa_value);
    print_select_item('v1',1,$qa_value);
    print_select_item('v2',2,$qa_value);
    print_select_item('v3',3,$qa_value);
    print "</select>\n";
}

function print_dhcp_select($qa_name, $qa_value)
{
    print "<select name=\"$qa_name\">\n";
    if (! isset($qa_value) or strlen($qa_value) == 0) {
        $qa_value = 'all';
    }
    print_select_item('Все события','all',$qa_value);
    print_select_item('Аренда адреса','add',$qa_value);
    print_select_item('Обновление аренды','old',$qa_value);
    print_select_item('Освобождение адреса','del',$qa_value);
    print "</select>\n";
}

function print_nagios_handler_select($qa_name)
{
    print "<select name=\"$qa_name\">\n";
    print_select_simple('Нет','');
    print_select_simple('restart-port','restart-port');
    print "</select>\n";
}

function print_dhcp_acl_select($qa_name)
{
    print "<select name=\"$qa_name\">\n";
    print_select_simple('Нет','');
    print_select_simple('hotspot-free','hotspot-free');
    print "</select>\n";
}

function print_enabled_select($qa_name, $qa_value)
{
    print "<select name=\"$qa_name\">\n";
    if (! isset($qa_value) or strlen($qa_value) == 0) { $qa_value = 0; }
    print_select_item('Все',0,$qa_value);
    print_select_item('Выключенные',1,$qa_value);
    print_select_item('Включенные',2,$qa_value);
    print "</select>\n";
}

function print_vendor_select($db, $qa_name, $qa_value)
{
    print "<select name=\"$qa_name\" class=\"js-select-single\">\n";
    $sSQL = "SELECT id,`name` FROM `vendors` order by `name`";
    $vendors = mysqli_query($db, $sSQL);
    print_select_item('Всё',0,$qa_value);
    while (list ($v_id, $v_name) = mysqli_fetch_array($vendors)) {
	print_select_item($v_name,$v_id,$qa_value);
    }
    print "</select>\n";
}

function print_vendor_set($db, $qa_name, $qa_value)
{
    print "<select name=\"$qa_name\" class=\"js-select-single\" style=\"width: 100%\">\n";
    $sSQL = "SELECT id,`name` FROM `vendors` order by `name`";
    $vendors = mysqli_query($db, $sSQL);
    while (list ($v_id, $v_name) = mysqli_fetch_array($vendors)) {
	print_select_item($v_name,$v_id,$qa_value);
    }
    print "</select>\n";
}

function get_vendor_name($db, $v_id)
{
    $sSQL = "SELECT `name` FROM `vendors` WHERE id=$v_id";
    $vendors = mysqli_query($db, $sSQL);
    (list ($v_name) = mysqli_fetch_array($vendors));
    return $v_name;
}

function get_qa($qa_value)
{
    if ($qa_value) { return "Да"; }
    return "Нет";
}

function print_action_select($action_name, $action_value)
{
    print "<select name=\"$action_name\">\n";
	print_select_item('Разрешить',1,$action_value);
	print_select_item('Запретить',0,$action_value);
    print "</select>\n";
}

function get_action($action_value)
{
    if ($action_value) { return "Разрешить"; }
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
    while (list ($filter_id, $filter_name) = mysqli_fetch_array($t_filters)) {
	print_select_item($filter_name,$filter_id,0);
    }
    print "</select>\n";
}

function get_filter($db, $filter_value)
{
    list ($filter) = mysqli_fetch_array(mysqli_query($db, "SELECT name FROM Filter_list WHERE id=".$filter_value));
    return $filter;
}

function get_login($db, $user_id)
{
    list ($login) = mysqli_fetch_array(mysqli_query($db, "SELECT login FROM User_list WHERE id=$user_id"));
    return $login;
}

function get_auth_count($db, $user_id)
{
    list ($count) = mysqli_fetch_array(mysqli_query($db, "SELECT count(id) FROM User_auth WHERE user_id=$user_id and deleted=0"));
    return $count;
}

function print_login_select($db, $login_name, $current_login)
{
    print "<select name=\"$login_name\" class=\"js-select-single\">\n";
    $t_login = mysqli_query($db, "SELECT id,login FROM User_list Order by Login");
    print_select_item('None',0,$current_login);
    while (list ($f_user_id, $f_login) = mysqli_fetch_array($t_login)) {
	print_select_item($f_login,$f_user_id,$current_login);
    }
    print "</select>\n";
}

function print_auth_select($db, $login_name, $current_auth)
{
    print "<select name=\"$login_name\" class=\"js-select-single\">\n";
    $t_login = mysqli_query($db, "SELECT U.login,U.fio,A.ip,A.id FROM User_list as U, User_auth as A WHERE A.user_id=U.id and A.deleted=0 and (A.id not in (select device_ports.auth_id FROM device_ports) or A.id=$current_auth) order by U.login,U.fio,A.ip");
    print_select_item('Empty',0,$current_auth);
    while (list ($f_login, $f_fio, $f_ip, $f_auth_id) = mysqli_fetch_array($t_login)) {
	print_select_item($f_login."[" . $f_fio . "] - ".$f_ip,$f_auth_id,$current_auth);
    }
    print "</select>\n";
}

function print_auth_select_mac($db, $login_name, $current_auth)
{
    print "<select name=\"$login_name\" class=\"js-select-single\">\n";
    $t_login = mysqli_query($db, "SELECT U.login,U.fio,A.ip,A.mac,A.id FROM User_list as U, User_auth as A WHERE A.user_id=U.id and A.deleted=0 and (A.id not in (select device_ports.auth_id FROM device_ports) or A.id=$current_auth) order by U.login,U.fio,A.ip");

    print_select_item('Empty',0,$current_auth);
    while (list ($f_login, $f_fio, $f_ip, $f_mac, $f_auth_id) = mysqli_fetch_array($t_login)) {
	print_select_item($f_login."[" . $f_mac . "] - ".$f_ip,$f_auth_id,$current_auth);
    }
    print "</select>\n";
}

function compact_port_name($port)
{
$result = $port;
$result = preg_replace('/XGigabitEthernet/','X',$result);
$result = preg_replace('/TenGigabitEthernet/','Te',$result);
$result = preg_replace('/GigabitEthernet/','Gi',$result);
return $result;
}

function print_device_port_select($db, $field_name, $device_id, $target_id)
{
    print "<select name=\"$field_name\" class=\"js-select-single\">\n";
    if (! isset($target_id)) {
        $target_id = 0;
    }
    if (! isset($device_id)) {
        $device_id = 0;
    }
    $d_sql = "SELECT D.device_name, DP.port, DP.device_id, DP.id, DP.ifName FROM devices AS D, device_ports AS DP WHERE D.deleted=0 and D.id = DP.device_id AND (DP.device_id<>$device_id or DP.id=$target_id) and (DP.id not in (select target_port_id FROM device_ports WHERE target_port_id>0 and target_port_id<>$target_id)) ORDER BY D.device_name,DP.port";
    $t_device = mysqli_query($db, $d_sql);
    print_select_item('Empty',0,$target_id);
    while (list ($f_name, $f_port, $f_device_id, $f_target_id, $f_ifname) = mysqli_fetch_array($t_device)) {
        if (empty($f_ifname)) { $f_ifname=$f_port; }
	print_select_item($f_name."[" . compact_port_name($f_ifname) . "]",$f_target_id,$target_id);
    }
    print "</select>\n";
}

function print_device_select($db, $field_name, $device_id)
{
    print "<select name=\"$field_name\" class=\"js-select-single\" >\n";
    $d_sql = "SELECT D.device_name, D.id FROM devices AS D Where D.deleted=0 order by D.device_name ASC";
    $t_device = mysqli_query($db, $d_sql);
    print_select_item('Все',0,$device_id);
    while (list ($f_name, $f_device_id) = mysqli_fetch_array($t_device)) {
	print_select_item($f_name,$f_device_id,$device_id);
    }
    print "</select>\n";
}

function print_vlan_select($db, $field_name, $vlan)
{
    print "<select name=\"$field_name\" class=\"js-select-single\">\n";
    $d_sql = "SELECT DISTINCT vlan FROM device_ports ORDER BY vlan DESC";
    $v_device = mysqli_query($db, $d_sql);
    if (!isset($vlan) or $vlan ==='') { $vlan=1; };
    print_select_item('1',1,$vlan);
    while (list ($f_vlan) = mysqli_fetch_array($v_device)) {
        if ($f_vlan === '1') { continue; }
	print_select_item($f_vlan,$f_vlan,$vlan);
    }
    print "</select>\n";
}

function print_device_select_ip($db, $field_name, $device_ip)
{
    print "<select name=\"$field_name\" class=\"js-select-single\" >\n";
    $d_sql = "SELECT D.device_name, D.ip FROM devices AS D Where D.deleted=0 order by D.device_name ASC";
    $t_device = mysqli_query($db, $d_sql);
    print_select_item('Все','',$device_ip);
    while (list ($f_name, $f_device_ip) = mysqli_fetch_array($t_device)) {
	print_select_item($f_name,$f_device_ip,$device_ip);
    }
    print "</select>\n";
}

function print_syslog_device_select($db, $field_name, $syslog_filter, $device_ip)
{
    print "<select name=\"$field_name\" class=\"js-select-single\" >\n";
    $d_sql = "SELECT R.ip, D.device_name FROM (SELECT DISTINCT ip FROM remote_syslog WHERE $syslog_filter) AS R LEFT JOIN (SELECT ip, device_name FROM devices WHERE deleted=0) AS D ON R.ip=D.ip ORDER BY R.ip ASC";
    $t_device = mysqli_query($db, $d_sql);
    print_select_item('Все','',$device_ip);
    while (list ($f_ip, $f_name) = mysqli_fetch_array($t_device)) {
        if (!isset($f_name) or $f_name === '') { $f_name=$f_ip; }
	print_select_item($f_name,$f_ip,$device_ip);
    }
    print "</select>\n";
}

function print_gateway_select($db, $field_name, $device_id)
{
    print "<select name=\"$field_name\" >\n";
    $d_sql = "SELECT D.device_name, D.id FROM devices AS D Where D.deleted=0 and D.device_type=2 order by D.device_name ASC";
    $t_device = mysqli_query($db, $d_sql);
    print_select_item('Все',0,$device_id);
    while (list ($f_name, $f_device_id) = mysqli_fetch_array($t_device)) {
	print_select_item($f_name,$f_device_id,$device_id);
    }
    print "</select>\n";
}

function get_gateways($db)
{
    $d_sql = "SELECT D.device_name, D.id FROM devices AS D Where D.deleted=0 and D.device_type=2 order by D.device_name ASC";
    $t_device = mysqli_query($db, $d_sql);
    unset($result);
    while (list ($f_name, $f_device_id) = mysqli_fetch_array($t_device)) {
        $result[$f_device_id] = $f_name;
    }
    return $result;
}

function print_device_port($db, $target_id)
{
    $d_sql = "SELECT D.device_name, DP.port, DP.device_id FROM devices AS D, device_ports AS DP WHERE D.id = DP.device_id AND DP.id=$target_id and D.deleted=0";
    $t_device = mysqli_query($db, $d_sql);
    while (list ($f_name, $f_port, $f_device_id) = mysqli_fetch_array($t_device)) {
        print "<a href=\"/admin/devices/switchport.php?id=$f_device_id\">" . $f_name . "[" . $f_port . "]</a>\n";
    }
}

function get_device_ips($db, $device_id)
{
    $switch=get_record($db,'devices','id='.$device_id);
    $index=0;
    if ($switch['user_id']) {
        $auth_ips=get_records($db,'User_auth','deleted=0 and user_id='.$switch['user_id']);
        foreach ( $auth_ips as $key => $value ) {
    	    if (isset($value['ip'])) { $result[$index]=$value['ip']; $index++; }
    	    }
	} else {
        if (isset($switch['ip'])) { $result[$index]=$switch['ip']; $index++; }
	}
    return $result;
}

function get_device_id($db, $device_name)
{
    $d_sql = "SELECT id FROM devices WHERE device_name='$device_name' and deleted=0";
    $t_device = mysqli_query($db, $d_sql);
    list ($f_device_id) = mysqli_fetch_array($t_device);
    return $f_device_id;
}

function get_device_name($db, $device_id)
{
    $d_sql = "SELECT device_name FROM devices WHERE id='$device_id'";
    $t_device = mysqli_query($db, $d_sql);
    list ($f_device_name) = mysqli_fetch_array($t_device);
    return $f_device_name;
}

function get_auth_by_ip($db, $ip)
{
    $d_sql = "SELECT id FROM User_auth WHERE ip='$ip' and deleted=0";
    $auth_q = mysqli_query($db, $d_sql);
    list ($f_auth_id) = mysqli_fetch_array($auth_q);
    return $f_auth_id;
}

function get_user_by_ip($db, $ip)
{
    $d_sql = "SELECT user_id FROM User_auth WHERE ip='$ip' and deleted=0";
    $auth_q = mysqli_query($db, $d_sql);
    list ($f_auth_id) = mysqli_fetch_array($auth_q);
    return $f_auth_id;
}

function get_device_by_auth($db, $id)
{
    $d_sql = "SELECT id FROM devices WHERE user_id=$id and deleted=0";
    $f_dev = get_record_sql($db,$d_sql);
    return $f_dev['id'];
}

function print_auth_port($db, $port_id)
{
    $d_sql = "SELECT A.ip,A.ip_int,A.mac,A.id,A.dns_name FROM User_auth as A, connections as C WHERE C.port_id=$port_id and A.id=C.auth_id and A.deleted=0 order by A.ip_int";
    $t_device = mysqli_query($db, $d_sql);
    while (list ($f_ip, $f_int, $f_mac, $f_auth_id, $f_dns) = mysqli_fetch_array($t_device)) {
        $name = $f_ip;
        if (isset($f_dns) and $f_dns != '') {
            $name = $f_dns;
        }
        print "<a href=\"/admin/users/editauth.php?id=$f_auth_id\">" . $name . " [" . $f_ip . "]</a><br>";
    }
}

function print_auth_simple($db, $auth_id)
{
    $auth = get_record($db,"User_auth","id=$auth_id");
    $name = $auth['dns_name'];
    if (empty($name)) { $name = $auth['comments']; } 
    if (empty($name)) { $name = $auth['ip']; } 
    print "<a href=\"/admin/users/editauth.php?id=$auth_id\">" . $name . "</a><br>";
}

function print_auth($db, $auth_id)
{
    $auth = get_record($db,"User_auth","id=$auth_id");
    $name = $auth['dns_name'];
    if (empty($name)) { $name = $auth['comments']; } else { $name.=" (".$auth['comments'].")"; }
    if (empty($name)) { $name = $auth['ip']; } else { $name.=" [".$auth['ip']."]"; }
    print "<a href=\"/admin/users/editauth.php?id=$auth_id\">" . $name . "</a><br>";
}

function print_auth_detail($db, $auth_id)
{
    $auth = get_record($db,"User_auth","id=$auth_id");
    $name = $auth['dns_name'];
    if (empty($name)) { $name = $auth['comments']; } else { $name.=" (".$auth['comments'].")"; }
    if (empty($name)) { $name = $auth['ip']; } else { $name.=" [".$auth['ip']."]"; }
    $name.=" last: [".$auth['last_found']."] ";
    if ($auth['deleted']) { $name.=" <font color='red'>DELETED!!!</font>"; }
    print "<a href=\"/admin/users/editauth.php?id=$auth_id\">" . $name . "</a><br>";
}

function get_auth_port_count($db, $port_id)
{
    $d_sql = "SELECT count(A.id) FROM User_auth as A, connections as C WHERE C.port_id=$port_id and A.id=C.auth_id and A.deleted=0";
    $t_device = mysqli_query($db, $d_sql);
    list ($f_count) = mysqli_fetch_array($t_device);
    if (! isset($f_count)) {
        $f_count = 0;
    }
    return $f_count;
}

function get_connection($db, $auth_id)
{
    $d_sql = "SELECT D.device_name, DP.port FROM devices AS D, device_ports AS DP, connections AS C WHERE D.deleted=0 and D.id = DP.device_id AND DP.id = C.port_id AND C.auth_id=$auth_id";
    $t_device = mysqli_query($db, $d_sql);
    list ($f_name, $f_port) = mysqli_fetch_array($t_device);
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
    list ($f_name, $f_port) = mysqli_fetch_array($t_device);
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
    while (list ($f_id, $f_name) = mysqli_fetch_array($t_option)) {
        print "<option value=$f_id>$f_name</option>";
    }
    $t_option = mysqli_query($db, "SELECT id,option_name FROM config_options WHERE uniq=1 and id not in (select option_id FROM config) order by option_name");
    while (list ($f_id, $f_name) = mysqli_fetch_array($t_option)) {
        print "<option value=$f_id>$f_name</option>";
    }
    print "</select>\n";
}

function run_sql($db, $query)
{
    return mysqli_query($db, $query);
}

function get_count_records($db, $table, $filter)
{
    if (isset($filter)) { $filter = 'where ' . $filter;  }
    $t_count = mysqli_query($db, "SELECT count(*) FROM $table $filter");
    list ($count) = mysqli_fetch_array($t_count);
    if (! isset($count)) { $count = 0; }
    return $count;
}

function get_id_record($db, $table, $filter)
{
    if (isset($filter)) {
        $filter = 'WHERE ' . $filter;
    }
    $t_record = mysqli_query($db, "SELECT id FROM $table $filter limit 1");
    list ($id) = mysqli_fetch_array($t_record);
    return $id;
}

function set_changed($db,$id)
{
$auth['changed'] = 1;
update_record($db, "User_auth", "id=" . $id, $auth);
}

function ResolveIP($db,$ip_int) {
$ip_name="-";
if (empty($ip_int)) { return $ip_name; }
$dns_cache=get_record_sql($db,"SELECT * FROM dns_cache WHERE ip=$ip_int");
if (empty($dns_cache) or empty($dns_cache['dns'])) {
    $ip_name = gethostbyaddr(long2ip($ip_int));
    if (empty($ip_name) or $ip_name === long2ip($ip_int)) { $ip_name="-"; }
    run_sql($db,"INSERT INTO dns_cache(dns,ip) VALUES('".$ip_name."',".$ip_int.")");
    } else { $ip_name=$dns_cache['dns']; }
return $ip_name;
}

function clean_dns_cache($db) {
$date = time();
$date = $date - 86400;
$now = strftime('%Y-%m-%d %H:%M:%S',time());
$clean_date=strftime('%Y-%m-%d %H:%M:%S',$date);
LOG_DEBUG($db,"Clean dns cache before $clean_date at $now");
run_sql($db,"DELETE FROM dns_cache WHERE `timestamp`<='".$clean_date."'");
}

function FormatDateStr($format = 'Y-m-d H:i:s', $date_str) {
$date1 = GetDateTimeFromString($date_str);
$result = $date1->format($format);
return $result;
}

function GetDateTimeFromString($date_str) {
if (!is_a($date_str,'DateTime')) {
    $t_date_str = urldecode($date_str);
    $t_date_str = preg_replace('/(\'|\")/','',$t_date_str);
    $t_date_str = preg_replace('/T/',' ',$t_date_str);
    $date1 = DateTime::createFromFormat('Y-m-d H:i:s',$t_date_str);
    if (!$date1) { $date1 = DateTime::createFromFormat('Y.m.d H:i:s',$t_date_str); }
    if (!$date1) { $date1 = DateTime::createFromFormat('Y/m/d H:i:s',$t_date_str); }
    if (!$date1) { $date1 = DateTime::createFromFormat('Y-m-d H:i',$t_date_str); }
    if (!$date1) { $date1 = DateTime::createFromFormat('Y.m.d H:i',$t_date_str); }
    if (!$date1) { $date1 = DateTime::createFromFormat('Y/m/d H:i',$t_date_str); }
    if (!$date1) { $date1 = DateTime::createFromFormat('Y-m-d|',$t_date_str); }
    if (!$date1) { $date1 = DateTime::createFromFormat('Y.m.d|',$t_date_str); }
    if (!$date1) { $date1 = DateTime::createFromFormat('Y/m/d|',$t_date_str); }
    if (!$date1) { $date1 = new DateTime; $date1->setTime(0,0,0,1); }
    } else { return $date_str; }
return $date1;
}

function GetNowTimeString() {
$now = strftime('%Y-%m-%d %H:%M:%S',time());
return $now;
}

function GetNowDayString() {
$now = strftime('%Y-%m-%d',time());
return $now;
}

function get_ip_subnet($db,$ip)
{
if (empty($ip)) { return; }
$ip_aton = ip2long($ip);
$t_option = mysqli_query($db, "SELECT id,subnet,ip_int_start,ip_int_stop FROM `subnets` WHERE hotspot=1 or office=1");
while (list ($f_net_id,$f_net,$f_start,$f_stop) = mysqli_fetch_array($t_option)) {
    if ($ip_aton >= $f_start and $ip_aton <= $f_stop) {
	    $result['subnet_id']=$f_net_id;
	    $result['subnet']=$f_net;
	    $result['int_start']=$f_start;
	    $result['int_stop']=$f_stop;
            return $result;
        }
    }
return;
}

function find_mac_in_subnet($db,$ip,$mac)
{
if (empty($ip)) { return; }
if (empty($mac)) { return; }
$ip_subnet=get_ip_subnet($db,$ip);
if (!isset($ip_subnet)) { return; }
$t_auth=get_records_sql($db, "SELECT id,mac,user_id FROM User_auth WHERE ip_int>=".$ip_subnet['int_start']." and ip_int<=".$ip_subnet['int_stop']." and mac='" . $mac . "' and deleted=0 ORDER BY id");
$auth_count=0;
$result['count']=0;
$result['users_id']=[];
foreach ($t_auth as $row) {
    if (!empty($row['id'])) {
	$auth_count++;
	$result['count']=$auth_count;
	$result[$auth_count]=$row['id'];
	array_push($result['users_id'],$row['user_id']);
	}
    }
return $result;
}

function apply_auth_rule($db,$auth_id,$user_id) {
if (empty($auth_id)) { return; }
if (empty($user_id)) { return; }
$user_rec = get_record($db, 'User_list', "id=".$user_id);
if (empty($user_rec)) { return; }
//set filter and status by user
$set_auth['filter_group_id']=$user_rec['filter_group_id'];
$set_auth['queue_id']= $user_rec['queue_id'];
$set_auth['enabled'] = $user_rec['enabled'];
update_record($db, "User_auth", "id=$auth_id", $set_auth);
}

function fix_auth_rules($db) {
global $default_user_id;
global $hotspot_user_id;
//cleanup hotspot subnet rules
delete_record($db,"auth_rules","user_id=".$default_user_id);
delete_record($db,"auth_rules","user_id=".$hotspot_user_id);
$t_hotspot = get_records_sql($db,"subnets","hotspot=1");
foreach ($t_hotspot as $row) { delete_record($db,"auth_rules","rule='".$row['subnet']."'"); }
}

function new_auth($db, $ip, $mac, $user_id)
{
    $ip_aton = ip2long($ip);
    $msg = '';

    if (!empty($mac)) {
        list ($lid, $aid) = mysqli_fetch_array(mysqli_query($db, "Select user_id,id FROM User_auth WHERE ip_int=$ip_aton and mac='" . $mac . "' and deleted=0 limit 1"));
	    if ($lid > 0) {
	        LOG_WARNING($db, "Pair ip-mac already exists! Skip creating $ip [$mac] auth_id: $aid");
    		return $aid;
	    }
	}

    // default id
    $save_traf = get_option($db, 23);
    $resurrection_id = NULL;

    // seek old auth with same ip and mac
    $resurrection_id = get_id_record($db, 'User_auth', " deleted=1 AND ip_int=" . $ip_aton . " AND mac='" . $mac . "'");
    if (!empty($resurrection_id)) {
        $msg.="Восстанавливаем доступ для auth_id: $resurrection_id with ip: $ip and mac: $mac ";
        $auth['user_id'] = $user_id;
        $auth['deleted'] = 0;
        $auth['save_traf'] = $save_traf *1;
        update_record($db, "User_auth", "id=$resurrection_id", $auth);
        } else {
        // not found ->create new record
        $msg.="Создаём новый ip-адрес \r\nip: $ip\r\nmac: $mac\r\n";
        $auth['deleted'] = 0;
        $auth['user_id'] = $user_id;
        $auth['ip'] = $ip;
        $auth['ip_int'] = $ip_aton;
        $auth['mac'] = $mac;
        $auth['save_traf'] = $save_traf *1;
        $resurrection_id=insert_record($db, "User_auth", $auth);
        }

    //check rules, update filter and state for new record
    if (!empty($resurrection_id)) {
        apply_auth_rule($db,$resurrection_id,$user_id);
        if (!is_hotspot($db,$ip) and !empty($msg)) { LOG_WARNING($db, $msg); }
        if (is_hotspot($db,$ip) and !empty($msg)) { LOG_INFO($db, $msg); }
        }
    return $resurrection_id;
}

function resurrection_auth($db, $ip, $mac, $action, $dhcp_hostname)
{
    $ip_aton = ip2long($ip);

    list ($lid, $aid) = mysqli_fetch_array(mysqli_query($db, "Select user_id,id FROM User_auth WHERE ip_int=$ip_aton and mac='" . $mac . "' and deleted=0 limit 1"));
    if ($lid > 0) {
        list ($lname) = mysqli_fetch_array(mysqli_query($db, "Select login FROM User_list WHERE id=$lid"));
        LOG_DEBUG($db, "external dhcp user $lname [$ip] auth_id: $aid");
        if (isset($dhcp_hostname) and !empty($dhcp_hostname)) { $auth['dhcp_hostname'] = $dhcp_hostname; }
        $auth['dhcp_action'] = $action;
        $auth['dhcp_time'] = GetNowTimeString();
        if ($action == 'add') { $auth['last_found'] = GetNowTimeString();  }
        update_record($db, "User_auth", "id=" . $aid, $auth);
        return $aid;
    }

    // default id
    $new_id = get_new_user_id($db, $ip, $mac, $dhcp_hostname);
    $save_traf = get_option($db, 23);
    $msg = '';
    // search changed mac
    list ($aid, $amac) = mysqli_fetch_array(mysqli_query($db, "Select id,mac FROM User_auth WHERE ip_int=$ip_aton and deleted=0 limit 1"));
    if ($aid > 0) {
        if (empty($amac)) {
            $auth['user_id'] = $new_id;
            $auth['ip'] = $ip;
            $auth['ip_int'] = $ip_aton;
            $auth['mac'] = $mac;
            $auth['deleted'] = 0;
            $auth['save_traf'] = $save_traf *1;
            $auth['dhcp_action'] = $action;
            $auth['dhcp_time'] = GetNowTimeString();
            if (!empty($dhcp_hostname)) { $auth['dhcp_hostname'] = $dhcp_hostname; }
            if ($action == 'add') { $auth['last_found'] = GetNowTimeString(); }
            LOG_INFO($db, "for ip: $ip mac not found! Use empty record...");
            update_record($db, "User_auth", "id=" . $aid, $auth);
            return $aid;
        } else {
            LOG_WARNING($db, "for ip: $ip mac change detected! Old mac: [$amac] New mac: [$mac]. Disable old auth_id: $aid");
            run_sql($db, "Update User_auth set deleted=1 WHERE id=" . $aid);
        }
    }
    $resurrection_id=NULL;
    // seek old auth with same ip and mac
    if (get_count_records($db, 'User_auth', "ip_int=" . $ip_aton . " and mac='" . $mac . "'")) {
        // found ->Resurrection old record
        $resurrection_id = get_id_record($db, 'User_auth', "ip_int=" . $ip_aton . " and mac='" . $mac . "'");
        $msg .="Восстанавливаем доступ для auth_id: $resurrection_id with ip: $ip and mac: $mac ";
        $auth['dhcp_action'] = $action;
        $auth['user_id'] = $new_id;
        $auth['deleted'] = 0;
        $auth['dhcp_time'] = GetNowTimeString();
        $auth['save_traf'] = $save_traf *1;
        if (!empty($dhcp_hostname)) { $auth['dhcp_hostname'] = $dhcp_hostname; }
        if ($action == 'add') { $auth['last_found'] = GetNowTimeString(); }
        update_record($db, "User_auth", "id=$resurrection_id", $auth);
    } else {
        // not found ->create new record
        $msg.="Создаём новый ip-адрес \r\nip: $ip\r\nmac: $mac\r\n";
        $auth['deleted'] = 0;
        $auth['user_id'] = $new_id;
        $auth['ip'] = $ip;
        $auth['ip_int'] = $ip_aton;
        $auth['mac'] = $mac;
        $auth['dhcp_action'] = $action;
        $auth['dhcp_time'] = GetNowTimeString();
        $auth['save_traf'] = $save_traf *1;
        if (!empty($dhcp_hostname)) { $auth['dhcp_hostname'] = $dhcp_hostname; }
        if ($action == 'add') { $auth['last_found'] = GetNowTimeString(); }
        $resurrection_id=insert_record($db, "User_auth", $auth);
    }
    //check rules, update filter and state for new record
    if (!empty($resurrection_id)) {
	    $user_rec = get_record($db, 'User_list', "id=".$new_id);
	    //set filter and status by user
	    $set_auth['filter_group_id']=$user_rec['filter_group_id'];
	    $set_auth['queue_id']= $user_rec['queue_id'];
	    $set_auth['enabled'] = $user_rec['enabled'];
	    update_record($db, "User_auth", "id=$resurrection_id", $set_auth);
	    $msg.="filter: ".$user_rec['filter_group_id']."\r\n queue_id: ".$user_rec['queue_id']."\r\n enabled: ".$user_rec['enabled']."\r\nid: $resurrection_id";
	    }
    if (!is_hotspot($db,$ip) and !empty($msg)) { LOG_WARNING($db, $msg); }
    if (is_hotspot($db,$ip) and !empty($msg)) { LOG_INFO($db, $msg); }
    return $resurrection_id;
}

function get_auth($db, $current_auth)
{
    if (! isset($current_auth)) {
        return;
    }
    if ($current_auth == 0) {
        return;
    }
    $t_login = mysqli_query($db, "SELECT U.login,A.ip FROM User_list as U, User_auth as A WHERE A.user_id=U.id and A.id=$current_auth");
    list ($f_login, $f_ip) = mysqli_fetch_array($t_login);
    $result = $f_login . "[" . $f_ip . "]";
    return $result;
}

function get_auth_by_mac($db, $mac)
{
    if (! isset($mac)) { return; }
    $mac = mac_dotted($mac);
    $t_login = mysqli_query($db, "SELECT U.id,U.login,A.id,A.ip FROM User_list as U, User_auth as A WHERE A.user_id=U.id and A.mac='" . $mac . "' and A.deleted=0 ORDER BY A.last_found DESC limit 1");
    list ($f_id, $f_login, $f_auth_id, $f_ip) = mysqli_fetch_array($t_login);
    if (isset($f_id)) {
        $result['auth'] = '<a href=/admin/users/edituser.php?id=' . $f_id . '>' . $f_login . '</a> / ip: <a href=/admin/users/editauth.php?id=' . $f_auth_id . '>' . $f_ip . '</a>';
	} else { $result['auth']='Unknown'; }
    $result['mac'] = expand_mac($db,$mac);
    return $result;
}

function get_auth_mac($db, $current_auth)
{
    if (! isset($current_auth)) {
        return;
    }
    if ($current_auth == 0) {
        return;
    }
    $t_login = mysqli_query($db, "SELECT U.login,A.mac FROM User_list as U, User_auth as A WHERE A.user_id=U.id and A.id=$current_auth");
    list ($f_login, $f_mac) = mysqli_fetch_array($t_login);
    $result = $f_login . "[" . $f_mac . "]";
    return $result;
}

function isRO($db)
{
    $result = 1;
    if (isset($_SESSION['login'])) {
        $work_user = $_SESSION['login'];
    }
    if (isset($_SESSION['user_id'])) {
        $work_id = $_SESSION['user_id'];
    }
    if (! isset($work_user) or ! isset($work_id)) {
        return $result;
    }
    $t_login = mysqli_query($db, "SELECT readonly FROM Customers WHERE Login='" . $work_user . "' and id='" . $work_id . "'");
    list ($f_ro) = mysqli_fetch_array($t_login);
    if (! isset($f_ro)) {
        return $result;
    }
    return $f_ro;
}

function LOG_INFO($db,$msg) {
global $L_INFO;
global $log_level;
if ($log_level < $L_INFO) { return; }
write_log($db,$msg,$L_INFO);
}

function LOG_ERROR($db,$msg) {
global $L_ERROR;
global $log_level;
if ($log_level < $L_ERROR) { return; }
email($L_ERROR,$msg);
write_log($db,$msg,$L_ERROR);
}

function LOG_VERBOSE($db,$msg) {
global $L_VERBOSE;
global $log_level;
if ($log_level < $L_VERBOSE) { return; }
write_log($db,$msg,$L_VERBOSE);
}

function LOG_WARNING($db,$msg) {
global $L_WARNING;
global $log_level;
if ($log_level < $L_WARNING) { return; }
email($L_WARNING,$msg);
write_log($db,$msg,$L_WARNING);
}

function LOG_DEBUG($db,$msg) {
global $debug;
global $L_DEBUG;
if (isset($debug) and $debug) { write_log($db,$msg,$L_DEBUG); }
}

function email ($level,$msg) {
global $send_email;
global $admin_email;
global $sender_email;
global $L_WARNING;
global $L_ERROR;

if (!$send_email) { return; }
if (!($level === $L_WARNING or $level === $L_ERROR)) { return; }

$subject = substr($msg,0,80);

if ($level === $L_WARNING) { $subject = "WARN: ".$subject."..."; $message = 'WARNING! Manager: '.$_SESSION['login'].' </br>'; }
if ($level === $L_ERROR) { $subject = "ERROR: ".$subject."..."; $message = 'ERROR! Manager: '.$_SESSION['login'].' </br>'; }

$msg_lines = preg_replace("/\r\n/","</br>",$msg);
$message .= $msg_lines;

$send = SimpleMail::make()
    ->setTo($admin_email, 'Administrator')
    ->setFrom($sender_email, 'Stat')
    ->setSubject($subject)
    ->setMessage($message)
    ->setHtml()
    ->setWrap(80)
    ->send();
}

function write_log($db, $msg, $level)
{
    $work_user = 'http';
    if (isset($_SESSION['login'])) {
        $work_user = $_SESSION['login'];
    }
    if (! isset($msg)) {
        $msg = 'ERROR! Empty log string!';
    }
    global $L_INFO;
    if (!isset($level)) { $level = $L_INFO; }
    $msg = str_replace("'", '', $msg);
    $sSQL = "insert into syslog(customer,message,level) values('$work_user','$msg',$level)";
    mysqli_query($db, $sSQL);
}

function print_year_select($year_name, $year)
{
    print "<select name=\"$year_name\" class=\"js-select-single\" >\n";
    for ($i = $year - 10; $i <= $year + 10; $i ++) {
	print_select_item($i,$i,$year);
    }
    print "</select>\n";
}

function print_date_select($dd, $mm, $yy)
{
    if ($dd >= 1) {
        print "<b>День</b>\n";
        print "<select name=\"day\" class=\"js-select-single\" >\n";
        for ($i = 1; $i <= 31; $i ++) {
	    print_select_item($i,$i,$dd);
        }
        print "</select>\n";
    }

    if ($mm >= 1) {
        print "<b>Месяц</b>\n";
        print "<select name=\"month\" class=\"js-select-single\" >\n";
        for ($i = 1; $i <= 12; $i ++) {
            $month_name = strftime("%B", strtotime("$i/01/$yy"));
	    print_select_item($month_name,$i,$mm);
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
        print "<select name=\"day2\" class=\"js-select-single\" >\n";
        for ($i = 1; $i <= 31; $i ++) {
	    print_select_item($i,$i,$dd);
        }
        print "</select>\n";
    }

    if ($mm >= 1) {
        print "<b>Месяц</b>\n";
        print "<select name=\"month2\" class=\"js-select-single\" >\n";
        for ($i = 1; $i <= 12; $i ++) {
            $month_name = strftime("%B", strtotime("$i/01/$yy"));
	    print_select_item($month_name,$i,$mm);
        }
        print "</select>\n";
    }

    print "<b>Год</b>\n";
    print_year_select('year2', $yy);
}

function is_up($ip)
{
    if (! isset($ip) or strlen($ip) == 0) {
        return false;
    }
    exec(sprintf('ping -i .3 -c 1 -W 5 %s', escapeshellarg($ip)), $res, $rval);
    return $rval === 0;
}

function get_mac_port_table($ip, $port_index, $community, $version, $oid)
{
    if (! isset($ip)) {
        return;
    }
    if (! isset($port_index)) {
        return;
    }
    if (! isset($oid)) {
        return;
    }
    if (! isset($community)) {
        $community = 'public';
    }
    if (! isset($version)) {
        $version = '2';
    }
    $mac_table = walk_snmp($ip, $community, $version, $oid);
    if (isset($mac_table) and count($mac_table) > 0) {
        foreach ($mac_table as $key => $value) {
            $key = trim($key);
            $value = intval(trim(str_replace('INTEGER:', '', $value)));
            if ($value == $port_index) {
                $pattern = '/\.(\d{1,3})\.(\d{1,3})\.(\d{1,3})\.(\d{1,3})\.(\d{1,3})\.(\d{1,3})$/';
                $result = preg_match($pattern, $key, $matches);
                if ($result) {
                    $mac_key = preg_replace('/^\./', '', $matches[0]);
                    $fdb_port_table[$mac_key] = $value;
                }
            }
        }
    }
    return $fdb_port_table;
}

function get_fdb_port_table($ip, $port_index, $community, $version)
{
    global $mac_table_str_oid;
    global $mac_table_str_oid2;
    global $mac_table_oid;
    global $mac_table_oid2;

    if (! isset($ip)) {
        return;
    }
    if (! isset($port_index)) {
        return;
    }
    if (! isset($community)) {
        $community = 'public';
    }
    if (! isset($version)) {
        $version = '2';
    }

    $fdb_port_table = get_mac_port_table($ip, $port_index, $community, $version, $mac_table_oid2);
    if (! isset($fdb_port_table) or ! $fdb_port_table or count($fdb_port_table) == 0) {
        $fdb_port_table = get_mac_port_table($ip, $port_index, $community, $version, $mac_table_oid);
    }
    // maybe cisco?!
    if (! isset($fdb_port_table) or ! $fdb_port_table or count($fdb_port_table) == 0) {
        global $cisco_vlan_oid;
        $vlan_table = walk_snmp($ip, $community, $version, $cisco_vlan_oid);
        if (! $vlan_table) {
            return;
        }
        //fucking cisco!!!
        foreach ($vlan_table as $vlan_oid => $value) {
            if (! $vlan_oid) { continue; }
            $pattern = '/\.(\d{1,4})$/';
            $result = preg_match($pattern, $vlan_oid, $matches);
            if ($result) {
                $vlan_id = preg_replace('/^\./', '', $matches[0]);
                if ($vlan_id > 1000 and $vlan_id < 1009) { continue; }
                $fdb_vlan_table = get_mac_port_table($ip, $port_index, $community . '@' . $vlan_id, $version, $mac_table_oid2);
                if (! isset($fdb_vlan_table) or ! $fdb_vlan_table or count($fdb_vlan_table) == 0) {
                    $fdb_vlan_table = get_mac_port_table($ip, $port_index, $community, $version, $mac_table_oid);
                }
                foreach ($fdb_vlan_table as $mac => $port) {
                    if (! isset($mac)) { continue; }
                    $fdb_port_table[$mac] = $port;
                }
            }
        }
    }
    return $fdb_port_table;
}

function get_mac_table($ip, $community, $version, $oid)
{
    if (! isset($ip)) {
        return;
    }
    if (! isset($oid)) {
        return;
    }
    if (! isset($community)) {
        $community = 'public';
    }
    if (! isset($version)) {
        $version = '2';
    }
    $mac_table = walk_snmp($ip, $community, $version, $oid);
    if (isset($mac_table) and count($mac_table) > 0) {
        foreach ($mac_table as $key => $value) {
            $key = trim($key);
            $value = intval(trim(str_replace('INTEGER:', '', $value)));
            $pattern = '/\.(\d{1,3})\.(\d{1,3})\.(\d{1,3})\.(\d{1,3})\.(\d{1,3})\.(\d{1,3})$/';
            $result = preg_match($pattern, $key, $matches);
            if ($result) {
                    $mac_key = preg_replace('/^\./', '', $matches[0]);
                    $fdb_port_table[$mac_key] = $value;
                }
        }
    }
    return $fdb_port_table;
}

function get_fdb_table($ip, $community, $version)
{
    global $mac_table_str_oid;
    global $mac_table_str_oid2;
    global $mac_table_oid;
    global $mac_table_oid2;

    if (! isset($ip)) {
        return;
    }
    if (! isset($community)) {
        $community = 'public';
    }
    if (! isset($version)) {
        $version = '2';
    }

    $fdb_port_table = get_mac_table($ip, $community, $version, $mac_table_oid2);
    if (! isset($fdb_port_table) or ! $fdb_port_table or count($fdb_port_table) == 0) {
        $fdb_port_table = get_mac_table($ip, $community, $version, $mac_table_oid);
    }
    // maybe cisco?!
    if (! isset($fdb_port_table) or ! $fdb_port_table or count($fdb_port_table) == 0) {
        global $cisco_vlan_oid;
        $vlan_table = walk_snmp($ip, $community, $version, $cisco_vlan_oid);
        if (! $vlan_table) {
            return;
        }
        foreach ($vlan_table as $vlan_oid => $value) {
            if (! $vlan_oid) { continue; }
            $pattern = '/\.(\d{1,4})$/';
            $result = preg_match($pattern, $vlan_oid, $matches);
            if ($result) {
                $vlan_id = preg_replace('/^\./', '', $matches[0]);
                if ($vlan_id > 1000 and $vlan_id < 1009) { continue; }
                $fdb_vlan_table = get_mac_table($ip, $community . '@' . $vlan_id, $version, $mac_table_oid2);
                if (! isset($fdb_vlan_table) or ! $fdb_vlan_table or count($fdb_vlan_table) == 0) {
                    $fdb_vlan_table = get_mac_table($ip, $community, $version, $mac_table_oid);
                }
                foreach ($fdb_vlan_table as $mac => $port) {
                    if (! isset($mac)) { continue; }
                    $fdb_port_table[$mac] = $port;
                }
            }
        }
    }
    return $fdb_port_table;
}

function check_snmp_access($ip, $community, $version)
{
    if (! isset($ip)) {
        return;
    }
    if (! isset($community)) {
        $community = 'public';
    }
    if (! isset($version)) {
        $version = '2';
    }
    $result = get_snmp($ip, $community, $version, '.1.3.6.1.2.1.1.1.0');
    if (!isset($result)) { return; }
    return 1;
}

function get_port_state($port, $ip, $community, $version)
{
    if (! isset($port)) {
        return;
    }
    if (! isset($ip)) {
        return;
    }
    if (! isset($community)) {
        $community = 'public';
    }
    if (! isset($version)) {
        $version = '2';
    }
    global $port_status_oid;
    $port_oid = $port_status_oid . $port;
    $port_state = get_snmp($ip, $community, $version, $port_oid);
    return $port_state;
}

function get_last_digit($oid)
{
    if (! isset($oid)) {
        return;
    }
    $pattern = '/\.(\d{1,})$/';
    preg_match($pattern, $oid, $matches);
    return $matches[1];
}

function get_cisco_sensors($ip, $community, $version, $mkey)
{
    $index = get_last_digit($mkey);
    global $cisco_sfp_sensors;
    global $cisco_sfp_precision;
    $result = parse_snmp_value(get_snmp($ip, $community, $version, $cisco_sfp_sensors . "." . $index));
    $prec = parse_snmp_value(get_snmp($ip, $community, $version, $cisco_sfp_precision . "." . $index));
    if (! isset($prec)) { $prec = 1; }
    $result = round(trim($result) / (10 * $prec), 2);
    return $result;
}

function get_snmp_ifname1($ip, $community, $version, $port)
{
    global $ifmib_ifname;
    $port_name = parse_snmp_value(get_snmp($ip, $community, $version, $ifmib_ifname . "." . $port));
    return $port_name;
}

function get_snmp_ifname2($ip, $community, $version, $port)
{
    global $ifmib_ifdescr;
    $port_name = parse_snmp_value(get_snmp($ip, $community, $version, $ifmib_ifdescr . "." . $port));
    return $port_name;
}

function get_snmp_interfaces($ip, $community, $version)
{
    global $ifmib_ifname;
    global $ifmib_ifdescr;
    $result = walk_snmp($ip, $community, $version, $ifmib_ifname);
    if (empty($result)) { $result = walk_snmp($ip, $community, $version, $ifmib_ifdescr); }
    return $result;
}

function walk_snmp($ip, $community, $version, $oid)
{
    if ($version == 2) {
        $result = snmp2_real_walk($ip, $community, $oid);
    }
    if ($version == 1) {
        $result = snmprealwalk($ip, $community, $oid);
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
    if (! isset($vendor_id)) {
        return;
    }
    if (! isset($port)) {
        return;
    }
    if (! isset($ip)) {
        return;
    }
    if (! isset($community)) {
        $community = 'public';
    }
    if (! isset($version)) {
        $version = '2';
    }
    // if (!is_up($ip)) { return; }

    global $ifmib_ifname;
    global $ifmib_ifdescr;

    $status = '';
    // eltex
    if ($vendor_id == 2) {
        global $eltex_sfp_status;
        global $eltex_sfp_vendor;
        global $eltex_sfp_sn;
        global $eltex_sfp_freq;
        global $eltex_sfp_length;
        $sfp_vendor = parse_snmp_value(get_snmp($ip, $community, $version, $eltex_sfp_vendor . "." . $port));
        if (isset($sfp_vendor)) {
            $sfp_status_temp = $eltex_sfp_status . "." . $port . ".5";
            $sfp_status_volt = $eltex_sfp_status . "." . $port . ".6";
            $sfp_status_circut = $eltex_sfp_status . "." . $port . ".7";
            $sfp_status_tx = $eltex_sfp_status . "." . $port . ".8";
            $sfp_status_rx = $eltex_sfp_status . "." . $port . ".9";
            $temp = parse_snmp_value(get_snmp($ip, $community, $version, $sfp_status_temp));
            $volt = parse_snmp_value(get_snmp($ip, $community, $version, $sfp_status_volt));
            $circut = parse_snmp_value(get_snmp($ip, $community, $version, $sfp_status_circut));
            $tx = parse_snmp_value(get_snmp($ip, $community, $version, $sfp_status_tx));
            $rx = parse_snmp_value(get_snmp($ip, $community, $version, $sfp_status_rx));
            $sfp_sn = parse_snmp_value(get_snmp($ip, $community, $version, $eltex_sfp_sn . "." . $port));
            $sfp_freq = parse_snmp_value(get_snmp($ip, $community, $version, $eltex_sfp_freq . "." . $port));
            if (! isset($sfp_freq) or $sfp_freq == 65535) {  $sfp_freq = 'unspecified';  }
            $sfp_length = parse_snmp_value(get_snmp($ip, $community, $version, $eltex_sfp_length . "." . $port));
            $status = 'Vendor: ' . $sfp_vendor . ' Serial: ' . $sfp_sn . ' Laser: ' . $sfp_freq . ' Distance: ' . $sfp_length . '<br>';
            if (isset($sfp_status_temp) and $temp > 0.1) { $status .= 'Temp: ' . $temp . " C"; }
            if (isset($sfp_status_volt) and $volt > 0.1) { $status .= ' Volt: ' . round($volt / 1000000, 2) . ' V'; }
            if (isset($sfp_status_circut) and $circut > 0.1) { $status .= ' Circut: ' . round($circut / 1000, 2) . ' mA'; }
            if (isset($sfp_status_tx) and $tx > 0.1) { $status .= ' Tx: ' . round($tx / 1000, 2) . ' dBm'; }
            if (isset($sfp_status_rx) and $rx > 0.1) { $status .= ' Rx: ' . round($rx / 1000, 2) . ' dBm'; }
            $status .= '<br>';
            return $status;
        }
        return;
    }
    // cisco
    if ($vendor_id == 16) {
        global $cisco_descr;
        global $cisco_modules;
        // get interface names
        $port_name = parse_snmp_value(get_snmp($ip, $community, $version, $ifmib_ifname . "." . $port));
        if (empty($port_name)) {
            $port_name = parse_snmp_value(get_snmp($ip, $community, $version, $ifmib_ifdescr . "." . $port));
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
        if (isset($temp) and $temp > 0) {
            $status .= 'Temp: ' . $temp . " C";
        }
        if (isset($volt) and $volt > 0) {
            $status .= ' Volt: ' . $volt . ' V';
        }
        if (isset($circut) and $circut > 0) {
            $status .= ' Circut: ' . $circut . ' mA';
        }
        if (isset($tx) and abs($tx)>0.1) {
            $status .= ' Tx: ' . $tx . ' dBm';
        }
        if (isset($rx) and abs($rx)>0.1) {
            $status .= ' Rx: ' . $rx . ' dBm';
        }
        if (isset($status)) {
            $status = preg_replace('/"/', '', $status);
            $status .= '<br>';
        }
        return $status;
    }

    // huawei
    if ($vendor_id == 3) {
        global $huawei_sfp_vendor;
        global $huawei_sfp_speed;
        global $huawei_sfp_db;
        
        global $huawei_sfp_volt;
        global $huawei_sfp_optrx;
        global $huawei_sfp_opttx;
        global $huawei_sfp_rx;
        global $huawei_sfp_tx;
        global $huawei_sfp_biascurrent;
        
        // get interface names
        $port_name = parse_snmp_value(get_snmp($ip, $community, $version, $ifmib_ifname . "." . $port));
        if (empty($port_name)) {
            $port_name = parse_snmp_value(get_snmp($ip, $community, $version, $ifmib_ifdescr . "." . $port));
    	    }
        // search module indexes
        $port_name = preg_quote(trim($port_name), '/');
        foreach ($modules_oids as $key => $value) {
            $pattern = '/' . $port_name . '/i';
            preg_match($pattern, $value, $matches);
            if (isset($matches[0])) {
                $module_id = get_last_digit($key);
                unset($result);
                $result = parse_snmp_value(get_snmp($ip, $community, $version, $huawei_sfp_vendor . "." . $module_id));
                if (isset($result)) { $sfp_vendor = $result; }
                unset($result);
                $result = parse_snmp_value(get_snmp($ip, $community, $version, $huawei_sfp_speed . "." . $module_id));
                if (isset($result)) {
                    list ($sfp_speed, $spf_lenght, $sfp_type) = explode('-', $result);
                    if ($sfp_type == 0) { $sfp_type = 'MultiMode'; }
                    if ($sfp_type == 1) { $sfp_type = 'SingleMode'; }
                }

                $volt = parse_snmp_value(get_snmp($ip, $community, $version, $huawei_sfp_volt . "." . $module_id));
                $circut = parse_snmp_value(get_snmp($ip, $community, $version, $huawei_sfp_biascurrent . "." . $module_id));
                $tx = parse_snmp_value(get_snmp($ip, $community, $version, $huawei_sfp_opttx . "." . $module_id));
                $rx = parse_snmp_value(get_snmp($ip, $community, $version, $huawei_sfp_optrx . "." . $module_id));
		if (!isset($tx)) { $tx = parse_snmp_value(get_snmp($ip, $community, $version, $huawei_sfp_tx . "." . $module_id)); }
            	if (!isset($rx)) { $rx = parse_snmp_value(get_snmp($ip, $community, $version, $huawei_sfp_rx . "." . $module_id)); }
                if (isset($sfp_vendor)) {  $status .= ' Name:' . $sfp_vendor.'<br>';  }
//                if (isset($sfp_speed)) { $status .= ' ' . $sfp_speed; }
//                if (isset($spf_lenght)) { $status .= ' ' . $spf_lenght; }
                if ($volt > 0) { $status .= ' Volt: ' . round($volt / 1000, 2) . ' V';  }
                if (isset($circut)) { $status .= ' Circut: ' . $circut . ' mA <br>'; }
                if (isset($tx)) {
	            $tx = preg_replace('/"/', '', $tx);
            	    list($tx_dbm,$pattern) = explode('.', $tx); 
            	    $tx_dbm=round(trim($tx_dbm) / 100,2);
            	    if (abs($tx_dbm)>0.1) { $status .= ' Tx: '.$tx_dbm.' dBm'; }
            	    }
                if (isset($rx)) {
	            $rx = preg_replace('/"/', '', $rx);
            	    list($rx_dbm,$pattern) = explode('.', $rx);
            	    $rx_dbm=round(trim($rx_dbm) / 100,2);
            	    if (abs($rx_dbm)>0.1) { $status .= ' Rx: '.$rx_dbm.' dBm'; }
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

function get_port_vlan($port, $port_index, $ip, $community, $version, $fdb_by_snmp)
{
    if (! isset($port)) {
        return;
    }
    if (! isset($ip)) {
        return;
    }
    if (! isset($community)) {
        $community = 'public';
    }
    if (! isset($version)) {
        $version = '2';
    }
    // if (!is_up($ip)) { return; }

    if ($fdb_by_snmp) { $port = $port_index; }

    global $port_vlan_oid;
    $port_oid = $port_vlan_oid . $port;
    $port_vlan = get_snmp($ip, $community, $version, $port_oid);
    $port_vlan = preg_replace('/.*\:/','',$port_vlan);
    $port_vlan = intval(trim($port_vlan));
    return $port_vlan;
}

function get_port_poe_state($vendor_id, $port, $ip, $community, $version)
{
    // port = snmp_index!!!!
    if (! isset($port)) {
        return;
    }
    if (! isset($ip)) {
        return;
    }
    if (! isset($community)) {
        $community = 'public';
    }
    if (! isset($version)) {
        $version = '2';
    }
    // if (!is_up($ip)) { return; }

    // default poe oid
    global $pethPsePortAdminEnable;
    $poe_status = $pethPsePortAdminEnable . "." . $port;

    if ($vendor_id == 3) {
        global $huawei_poe_oid;
        $poe_status = $huawei_poe_oid . "." . $port;
    }

    if ($vendor_id == 8) {
        global $allied_poe_oid;
        $poe_status = $allied_poe_oid . "." . $port;
    }

    if ($vendor_id == 15) {
        global $hp_poe_oid;
        $poe_status = $hp_poe_oid . "." . $port;
    }

    if ($vendor_id == 9) {
        global $mikrotik_poe_oid;
        $poe_status = $mikrotik_poe_oid . "." . $port;
    }

    if ($vendor_id == 10) {
        global $netgear_poe_oid;
        $poe_status = $netgear_poe_oid . "." . $port;
    }

    $result = '';
    $c_state = get_snmp($ip, $community, $version, $poe_status);
    if (!empty($c_state)) {
        $p_state = parse_snmp_value($c_state);
        if ($vendor_id == 9) {
            if ($p_state == 1) { return 2; }
            if ($p_state > 1) { return 1; }
        }
        return $p_state;
    }
    return;
}

function set_port_poe_state($vendor_id, $port, $ip, $community, $version, $state)
{
    // port -> snmp_index!!!
    if (! isset($port)) {
        return;
    }
    if (! isset($ip)) {
        return;
    }
    if (! isset($community)) {
        $community = 'public';
    }
    if (! isset($version)) {
        $version = '2';
    }
    // if (!is_up($ip)) { return; }
    // default poe oid
    global $pethPsePortAdminEnable;
    $poe_status = $pethPsePortAdminEnable . "." . $port;
    if ($vendor_id == 3) {
        global $huawei_poe_oid;
        $poe_status = $huawei_poe_oid . "." . $port;
    }
    if ($vendor_id == 8) {
        global $allied_poe_oid;
        $poe_status = $allied_poe_oid . "." . $port;
    }
    if ($vendor_id == 15) {
        global $hp_poe_oid;
        $poe_status = $hp_poe_oid . "." . $port;
    }
    if ($vendor_id == 10) {
        global $netgear_poe_oid;
        $poe_status = $netgear_poe_oid . "." . $port;
    }

    if ($state) {
        // enable port
        $c_state = set_snmp($ip, $community, $version, $poe_status, 'i', 1);
        return $c_state;
    } else {
        // disable port
        $c_state = set_snmp($ip, $community, $version, $poe_status, 'i', 2);
        return $c_state;
    }
}

function get_port_poe_detail($vendor_id, $port, $ip, $community, $version)
{
    // port = snmp_index!!!!
    if (! isset($port)) {
        return;
    }
    if (! isset($ip)) {
        return;
    }
    if (! isset($community)) {
        $community = 'public';
    }
    if (! isset($version)) {
        $version = '2';
    }
    // if (!is_up($ip)) { return; }

    $result = '';

    $poe_class = '.1.3.6.1.2.1.105.1.1.1.10.1.' . $port;

    // eltex
    if ($vendor_id == 2) {
        $poe_power = '.1.3.6.1.4.1.89.108.1.1.5.1.' . $port;
        $poe_current = '.1.3.6.1.4.1.89.108.1.1.4.1.' . $port;
        $poe_volt = '.1.3.6.1.4.1.89.108.1.1.3.1.' . $port;
    }

    // huawei
    if ($vendor_id == 3) {
        $poe_power = '.1.3.6.1.4.1.2011.5.25.195.3.1.10.' . $port;
        $poe_current = '.1.3.6.1.4.1.4526.11.15.1.1.1.3.1.' . $port;
        $poe_volt = '.1.3.6.1.4.1.2011.5.25.195.3.1.14.' . $port;
    }

    // AT
    if ($vendor_id == 8) {
        $poe_power = '.1.3.6.1.4.1.89.108.1.1.5.1.' . $port;
        $poe_current = '.1.3.6.1.4.1.89.108.1.1.4.1.' . $port;
        $poe_volt = '.1.3.6.1.4.1.89.108.1.1.3.1.' . $port;
    }

    // mikrotik
    if ($vendor_id == 9) {
        global $mikrotik_poe_volt;
        global $mikrotik_poe_current;
        global $mikrotik_poe_usage;
        $poe_power = $mikrotik_poe_usage . '.' . $port;
        $poe_current = $mikrotik_poe_current . '.' . $port;
        $poe_volt = $mikrotik_poe_volt . '.' . $port;
    }

    // netgear
    if ($vendor_id == 10) {
        $poe_power = '.1.3.6.1.4.1.4526.11.15.1.1.1.2.1.' . $port;
        $poe_current = '.1.3.6.1.4.1.4526.11.15.1.1.1.3.1.' . $port;
        $poe_volt = '.1.3.6.1.4.1.4526.11.15.1.1.1.4.1.' . $port;
    }

    // HP
    if ($vendor_id == 15) {
        $poe_power = '.1.3.6.1.4.1.25506.2.14.1.1.4.1.' . $port;
        $poe_volt = '.1.3.6.1.4.1.25506.2.14.1.1.3.1.' . $port;
    }

    if (isset($poe_power)) {
        $c_power = get_snmp($ip, $community, $version, $poe_power);
        if (isset($c_power)) {
            $p_power = parse_snmp_value($c_power);
            if ($vendor_id == 9) {
                $p_power = round($p_power / 10, 2);
            } else {
                $p_power = round($p_power / 1000, 2);
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
            if ($vendor_id == 2 or $vendor_id == 8) {
                $p_volt = round($p_volt / 1000, 2);
            }
            if ($vendor_id == 9) {
                $p_volt = round($p_volt / 10, 2);
            }
            if ($vendor_id == 15) {
                $p_volt = round($p_volt / 100, 2);
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
            if ($p_class > 0 and $p_power > 0) {
                $result .= ' Class: ' . ($p_class - 1);
            }
        }
    }

    return $result;
}

function get_snmp($ip, $community, $version, $oid)
{
    if ($version == 2) {
        $result = snmp2_get($ip, $community, $oid);
    }
    if ($version == 1) {
        $result = snmpget($ip, $community, $oid);
    }
    return $result;
}

function set_snmp($ip, $community, $version, $oid, $field, $value)
{
    if ($version == 2) {
        $result = snmp2_set($ip, $community, $oid, $field, $value);
    }
    if ($version == 1) {
        $result = snmpset($ip, $community, $oid, $field, $value);
    }
    return $result;
}

function set_port_state($vendor_id, $port, $ip, $community, $version, $state)
{
    // port -> snmp_index!!!
    if (! isset($port)) {
        return;
    }
    if (! isset($ip)) {
        return;
    }
    if (! isset($community)) {
        $community = 'public';
    }
    if (! isset($version)) {
        $version = '2';
    }
    global $port_admin_status_oid;
    $port_status = $port_admin_status_oid . $port;
    if ($state) {
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
    while (list ($a_id, $a_name, $a_ip) = mysqli_fetch_array($auth_list)) {
        // get device and port for auth
        if ($place_id == 0) {
            $place_filter = '';
        } else {
            $place_filter = 'D.building_id=' . $place_id . ' and ';
        }
        $devSQL = 'SELECT D.id, D.device_name, D.vendor_id, D.device_model, D.ip, D.snmp_version, D.rw_community, DP.port, DP.snmp_index  FROM devices AS D, device_ports AS DP, connections AS C WHERE ' . $place_filter . ' D.id = DP.device_id AND DP.id = C.port_id AND C.auth_id=' . $a_id . ' LIMIT 1';
        $dev_info = mysqli_query($db, $devSQL);
        list ($d_id, $d_name, $d_vendor_id, $d_model, $d_ip, $d_snmp, $d_community, $d_port, $d_snmp_index) = mysqli_fetch_array($dev_info);
        if (! isset($d_id)) {
            continue;
        }
        if ($state) {
            $mode = 'enable';
            run_sql($db, "Update User_auth set nagios_handler='restart-port' WHERE id=$a_id and nagios_handler='manual-mode'");
        } else {
            $mode = 'disable';
            run_sql($db, "Update User_auth set nagios_handler='manual-mode' WHERE id=$a_id and nagios_handler='restart-port'");
        }
        LOG_INFO($db, "At device $d_name [$d_ip] $mode port $d_port for auth_id: $a_id ($a_ip [$a_name])");
        set_port_state($d_vendor_id, $d_snmp_index, $d_ip, $d_community, $d_snmp, $state);
        set_port_poe_state($d_vendor_id, $d_snmp_index, $d_ip, $d_community, $d_snmp, $state);
    }
    LOG_INFO($db, 'Mass port state change stopped.');
}

function get_vendor($db,$mac)
{
    $mac = mac_simplify($mac);
    $vendor = mysqli_query($db,'select companyName,companyAddress FROM mac_vendors WHERE oui="'.substr($mac,0,6).'" or oui="'.substr($mac,0,8).'"');
    list ($f_name,$f_addr) = mysqli_fetch_array($vendor);
    $result = $f_name." ".$f_addr;
    return $result;
}

function get_port_state_detail($port, $ip, $community, $version)
{
    if (! isset($port)) {
        return;
    }
    if (! isset($ip)) {
        return;
    }
    if (! isset($community)) {
        $community = 'public';
    }
    if (! isset($version)) {
        $version = '2';
    }
    // if (!is_up($ip)) { return; }

    global $port_status_oid;
    global $port_admin_status_oid;
    global $port_speed_oid;
    global $port_errors_oid;

    $oper = $port_status_oid . $port;
    $admin = $port_admin_status_oid . $port;
    $speed = $port_speed_oid . $port;
    $errors = $port_errors_oid . $port;
    $result = '';
    $c_state = get_snmp($ip, $community, $version, $oper);
    $p_state = parse_snmp_value($c_state);
    $c_admin = get_snmp($ip, $community, $version, $admin);
    $p_admin = parse_snmp_value ($c_admin);
    if ($p_state == 1) { $c_speed = get_snmp($ip, $community, $version, $speed); } else { $c_speed = 'INT:0'; }
    $p_speed = parse_snmp_value($c_speed);
    $c_errors = get_snmp($ip, $community, $version, $errors);
    $p_errors = parse_snmp_value($c_errors);
    $result = $p_state . ";" . $p_admin . ";" . $p_speed . ";" . $p_errors;
    return $result;
}

function parse_snmp_value($value) {
if (empty($value)) { return NULL; }
list ($p_type, $p_value) = explode(':', $value);
$p_value = trim($p_value);
$p_value= preg_replace('/^\"/','',$p_value);
$p_value= preg_replace('/\"$/','',$p_value);
$p_value = trim($p_value);
return $p_value;
}

function dec_to_hex($mac)
{
    if (! isset($mac)) {
        return;
    }
    $mac_array = explode('.', $mac);
    for ($i = 0; $i < count($mac_array); $i ++) {
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
    if (! isset($mac)) {
        return;
    }
    $mac = strtolower(trim($mac));
    $mac = preg_replace('/(\.|:|-)/', '', $mac);
    return $mac;
}

function mac_dotted($mac)
{
    if (! isset($mac)) {
        return;
    }
    $mac = mac_simplify($mac);
    $mac = preg_replace('/(\S{2})(\S{2})(\S{2})(\S{2})(\S{2})(\S{2})/', '$1:$2:$3:$4:$5:$6', $mac);
    return $mac;
}

function unbind_ports($db, $device_id)
{
    $target = mysqli_query($db, "SELECT U.target_port_id,U.id FROM device_ports U WHERE U.device_id=$device_id");
    while (list ($target_id, $id) = mysqli_fetch_array($target)) {
        $new['target_port_id'] = 0;
        update_record($db, "device_ports", "target_port_id='$id'", $new);
        update_record($db, "device_ports", "id='$id'", $new);
    }
}

function bind_ports($db, $port_id, $target_id)
{
    $old_target = mysqli_query($db, "SELECT U.target_port_id FROM device_ports U WHERE U.id=$port_id");
    list ($old_target_id) = mysqli_fetch_array($old_target);
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

function expand_mac($db,$msg)
{
    if (! isset($msg)) { return; }
    $mac = mac_dotted($msg);
    $vendor_info = get_vendor($db,$mac);
    $result = ' <p title="'.$vendor_info.'"><a href=/admin/logs/mac.php?mac='.$mac.'>'.$mac.'</a></p>';
    return $result;
}

function expand_log_str($db, $msg)
{
    if (! isset($msg)) {
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
        $mac_replace = ' <a href=/admin/logs/mac.php?mac='.$mac.'>'.$mac.'</a> ';
        $result = preg_replace($mac_pattern, $mac_replace, $result);
    }

    $mac_pattern = '/\s+mac:\s+([\w\:]{17})$/i';
    preg_match($mac_pattern, $result, $matches);
    if (isset($matches[1])) {
        $mac = $matches[1];
        $mac = mac_dotted($mac);
#        $vendor_info = get_vendor($db,$mac);
#        $mac_replace = ' mac: <p title="'.$vendor_info.'"><a href=/admin/logs/mac.php?mac='.$mac.'>'.$mac.'</a></p>';
        $mac_replace = ' mac: <a href=/admin/logs/mac.php?mac='.$mac.'>'.$mac.'</a> ';
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
    if (! isset($table)) {
        LOG_ERROR($db, "Search in unknown table! Skip command.");
        return;
    }
    if (! isset($filter)) {
        LOG_ERROR($db, "Search filter is empty! Skip command.");
        return;
    }
    if (! isset($field)) {
        LOG_ERROR($db, "Search field is empty! Skip command.");
        return;
    }
    if (preg_match('/=$/', $filter)) {
        LOG_ERROR($db, "Search record ($table) with illegal filter $filter! Skip command.");
        return;
    }
    $old_sql = "SELECT $field FROM $table WHERE $filter LIMIT 1";
    $old_record = mysqli_query($db, $old_sql);
    $old = mysqli_fetch_array($old_record, MYSQLI_ASSOC);
    foreach ($old as $key => $value) {
        if (! isset($value) or $value==='NULL') { $value = ''; }
        $result[$key] = $value;
    }
    return $result[$field];
}

function get_record($db, $table, $filter)
{
    if (! isset($table)) {
        LOG_ERROR($db, "Search in unknown table! Skip command.");
        return;
    }
    if (! isset($filter)) {
        LOG_ERROR($db, "Search filter is empty! Skip command.");
        return;
    }
    if (preg_match('/=$/', $filter)) {
        LOG_ERROR($db, "Search record ($table) with illegal filter $filter! Skip command.");
        return;
    }
    $old_sql = "SELECT * FROM $table WHERE $filter LIMIT 1";
    $old_record = mysqli_query($db, $old_sql);
    $old = mysqli_fetch_array($old_record, MYSQLI_ASSOC);
    $result = NULL;
    if (!empty($old)) {
        foreach ($old as $key => $value) {
            if (!isset($value) or $value==='NULL') { $value = ''; }
            if (!empty($key)) { $result[$key] = $value; }
            }
        }
    return $result;
}

function get_records($db, $table, $filter)
{
    if (! isset($table)) {
        LOG_ERROR($db, "Search in unknown table! Skip command.");
        return;
    }
    if (isset($filter) and preg_match('/=$/', $filter)) {
        LOG_ERROR($db, "Search record ($table) with illegal filter $filter! Skip command.");
        return;
    }
    $s_filter='';
    if (isset($filter)) { $s_filter = 'WHERE '.$filter; }
    $old_sql = "SELECT * FROM $table $s_filter";
    $old_record = mysqli_query($db, $old_sql);
    $result = NULL;
    $index = 0;
    while ($old = mysqli_fetch_array($old_record, MYSQLI_ASSOC)) {
        foreach ($old as $key => $value) {
	    if (! isset($value) or $value==='NULL') { $value = ''; }
    	    $result[$index][$key] = $value;
	}
	$index++;
    }
    return $result;
}

function get_records_sql($db, $sql)
{
    if (! isset($sql)) {
        LOG_ERROR($db, "Empty query! Skip command.");
        return;
    }
    $record = mysqli_query($db, $sql);
    $index = 0;
    $result = NULL;
    while ($rec = mysqli_fetch_array($record, MYSQLI_ASSOC)) {
        foreach ($rec as $key => $value) {
	    if (! isset($value) or $value==='NULL') { $value = ''; }
	    if (!empty($key)) { $result[$index][$key] = $value; }
	}
	$index++;
    }
    return $result;
}

function get_record_sql($db, $sql)
{
    if (! isset($sql)) {
        LOG_ERROR($db, "Empty query! Skip command.");
        return;
    }
    $record = mysqli_query($db, $sql." LIMIT 1");
    $result = NULL;
    if ($rec = mysqli_fetch_array($record, MYSQLI_ASSOC)) {
        foreach ($rec as $key => $value) {
	    if (! isset($value) or $value==='NULL') { $value = ''; }
    	    $result[$key] = $value;
	    }
	}
    return $result;
}

function is_auth_bind_changed ($db, $id, $ip,$mac) {
$old_sql = "SELECT ip,mac FROM User_auth WHERE id=$id";
$old_record = get_record_sql($db, $old_sql);
if (empty($old_record["ip"]) or empty($old_record["mac"])) { return 0; }
if ($old_record["ip"] !== $ip or $old_record["mac"] !== $mac) {
        LOG_VERBOSE($db, "Changed ip or mac for auth record!");
        return 1;
        }
return 0;
}

function copy_auth($db, $id, $new_auth) {
$old_record = get_record_sql($db, "SELECT * FROM User_auth WHERE id=$id");
delete_record($db,"User_auth","id=".$id);
$new_auth["user_id"] = $old_record["user_id"];
$new_auth["changed"] = 1;
$changed_time = GetNowTimeString();
$new_auth["changed_time"]=$changed_time;
$new_id = insert_record($db,"User_auth",$new_auth);
LOG_VERBOSE($db, "Old record with id: $id deleted. Created new auth record for new ip+mac id: $new_id!");
return $new_id;
}

function update_record($db, $table, $filter, $newvalue)
{
    if (isRO($db)) {
        LOG_ERROR($db, "User does not have write permission");
        return;
    }
    if (! isset($table)) {
        LOG_ERROR($db, "Change record for unknown table! Skip command.");
        return;
    }
    if (! isset($filter)) {
        LOG_ERROR($db, "Change record ($table) with empty filter! Skip command.");
        return;
    }
    if (preg_match('/=$/', $filter)) {
        LOG_ERROR($db, "Change record ($table) with illegal filter $filter! Skip command.");
        return;
    }
    if (! isset($newvalue)) {
        LOG_ERROR($db, "Change record ($table [ $filter ]) with empty data! Skip command.");
        return;
    }
    $old_sql = "SELECT * FROM $table WHERE $filter";
    $old_record = mysqli_query($db, $old_sql);
    $old = mysqli_fetch_array($old_record, MYSQLI_ASSOC);
    $changed_log = '';
    $run_sql = '';
    $network_changed = 0;

    $acl_fields = [
    'ip' => '1',
    'ip_int' => '1',
    'enabled'=>'1',
    'dhcp'=>'1',
    'filter_group_id'=>'1',
    'deleted'=>'1',
    'dhcp_acl'=>'1',
    'queue_id'=>'1',
    'mac'=>'1',
    'blocked'=>'1',
    ];

    foreach ($newvalue as $key => $value) {
        if (! isset($value)) {
            $value = '';
        }
        $value = trim($value);
        if (strcmp($old[$key], $value) == 0) {
            continue;
        }
        if ($table==="User_auth") {
    	    if (!empty($acl_fields["$key"])) { $network_changed = 1; }
    	    }
        $changed_log = $changed_log . " $key => $value (old: $old[$key]),";
        $run_sql = $run_sql . " `" . $key . "`='" . mysqli_real_escape_string($db, $value) . "',";
    }
    if ($run_sql == '') { return; }

    if ($network_changed) { $run_sql = $run_sql . " `changed`='1',"; }

    $changed_log = substr_replace($changed_log, "", - 1);
    $run_sql = substr_replace($run_sql, "", - 1);

    if ($table === 'User_auth') {
        $changed_time = GetNowTimeString();
        $run_sql = $run_sql . ", `changed_time`='".$changed_time."'";
        }

    $new_sql = "UPDATE $table SET $run_sql WHERE $filter";
    LOG_DEBUG($db, "Run sql: $new_sql");
    mysqli_query($db, $new_sql);
    LOG_VERBOSE($db, "Change table $table WHERE $filter set $changed_log");
}

function delete_record($db, $table, $filter)
{
    if (isRO($db)) {
        LOG_ERROR($db, "User does not have write permission");
        return;
    }
    if (! isset($table)) {
        LOG_ERROR($db, "Delete FROM unknown table! Skip command.");
        return;
    }
    if (! isset($filter)) {
        LOG_ERROR($db, "Delete FROM table $table with empty filter! Skip command.");
        return;
    }
    if (preg_match('/=$/', $filter)) {
        LOG_ERROR($db, "Change record ($table) with illegal filter $filter! Skip command.");
        return;
    }
    $old_sql = "SELECT * FROM $table WHERE $filter";
    $old_record = mysqli_query($db, $old_sql);
    $old = mysqli_fetch_array($old_record, MYSQLI_ASSOC);
    $changed_log = 'record: ';
    foreach ($old as $key => $value) {
        if (! isset($value)) { $value = ''; }
        $changed_log = $changed_log . " $key => $value,";
    }
    //never delete user ip record
    if ($table === 'User_auth') {
        $changed_time = GetNowTimeString();
        $new_sql = "UPDATE $table SET deleted=1, changed=1, `changed_time`='".$changed_time."' WHERE $filter";
        LOG_DEBUG($db, "Run sql: $new_sql");
        mysqli_query($db, $new_sql);
        } else {
        $new_sql = "DELETE FROM $table WHERE $filter";
        LOG_DEBUG($db, "Run sql: $new_sql");
        mysqli_query($db, $new_sql);
        }
    LOG_VERBOSE($db, "Delete FROM table $table WHERE $filter $changed_log");
}

function insert_record($db, $table, $newvalue)
{
    if (isRO($db)) {
        LOG_ERROR($db, "User does not have write permission");
        return;
    }
    if (! isset($table)) {
        LOG_ERROR($db, "Create record for unknown table! Skip command.");
        return;
    }
    if (! isset($newvalue)) {
        LOG_ERROR($db, "Create record ($table) with empty data! Skip command.");
        return;
    }
    $changed_log = '';
    $field_list = '';
    $value_list = '';
    foreach ($newvalue as $key => $value) {
        if (! isset($value)) { $value = ''; }
        $changed_log = $changed_log . " $key => $value,";
        $field_list = $field_list . "`" . $key . "`,";
        $value = trim($value);
        $value_list = $value_list . "'" . mysqli_real_escape_string($db, $value) . "',";
    }
    if ($value_list == '') {
        return;
    }
    $changed_log = substr_replace($changed_log, "", - 1);
    $field_list = substr_replace($field_list, "", - 1);
    $value_list = substr_replace($value_list, "", - 1);
    $new_sql = "insert into $table(" . $field_list . ") values(" . $value_list . ")";
    LOG_DEBUG($db, "Run sql: $new_sql");
    if (mysqli_query($db, $new_sql)) {
            $last_id = mysqli_insert_id($db);
            LOG_VERBOSE($db, "Create record in table $table: $changed_log with id: $last_id");
            return $last_id;
            }
}

function get_diff_rec($db, $table, $filter, $newvalue, $only_changed)
{
    if (! isset($table)) {
        return;
    }
    if (! isset($filter)) {
        return;
    }
    if (! isset($newvalue)) {
        return;
    }
    
    if (!isset($only_changed)) { $only_changed=0; }

    $old_sql = "SELECT * FROM $table WHERE $filter";
    $old_record = mysqli_query($db, $old_sql);
    $old = mysqli_fetch_array($old_record, MYSQLI_ASSOC);
    $changed_log = "\r\n";
    foreach ($newvalue as $key => $value) {
            if (strcmp($old[$key], $value) !== 0) {
                $changed_log = $changed_log . " $key => cur: $value old: $old[$key],\r\n";
                }
            }
    $old_record = '';
    if (!$only_changed) {
        $old_record = "\r\n Не изменялось:\r\n";
	foreach ($old as $key => $value) {
            if (!$newvalue[$key]) { $old_record = $old_record . " $key = $value,\r\n"; }
            }
	$old_record = substr_replace($old_record, "", -3);
	}
    // print $changed_log;
    return $changed_log.$old_record;
}

function get_cacti_graph($host_ip, $port_index)
{
    global $cacti_dbname;
    global $cacti_dbhost;
    global $cacti_url;
    global $dbuser;
    global $dbpass;

    if (! isset($cacti_url)) {
        return;
    }

    $cacti_db_link = mysqli_connect($cacti_dbhost, $dbuser, $dbpass, $cacti_dbname);
    if (! $cacti_db_link) {
        echo "Ошибка: Невозможно установить соединение с MySQL with $cacti_dbhost [$cacti_dbname] for $dbuser." . PHP_EOL;
        echo "Код ошибки errno: " . mysqli_connect_errno() . PHP_EOL;
        echo "Текст ошибки error: " . mysqli_connect_error() . PHP_EOL;
        return FALSE;
    }

    $host_sql = 'SELECT id FROM host Where hostname="' . $host_ip . '"';
    $tmpArray = mysqli_fetch_array(mysqli_query($cacti_db_link, $host_sql), MYSQLI_ASSOC);
    if (isset($tmpArray) and sizeof($tmpArray)) {
        foreach ($tmpArray as $key => $value) {
            if ($key == 'id') {
                $host_id = $value;
            }
        }
    } else {
        return;
    }

    $graph_sql = 'SELECT id FROM graph_local Where graph_template_id=2 and host_id="' . $host_id . '" and snmp_index="' . $port_index . '"';
    $tmpArray = mysqli_fetch_array(mysqli_query($cacti_db_link, $graph_sql), MYSQLI_ASSOC);
    if (isset($tmpArray) and sizeof($tmpArray)) {
        foreach ($tmpArray as $key => $value) {
            if ($key == 'id') {
                $graph_id = $value;
            }
        }
    } else {
        return;
    }

    $result = $cacti_url . "/graph_image.php?local_graph_id=" . $graph_id;
    return $result;
}

function print_select_item ($description,$value,$current) {
if ($value == $current) { print "<option value=$value selected>$description</option>"; } else { print "<option value=$value>$description</option>"; }
}

function print_select_simple ($description,$value) {
print "<option value=$value>$description</option>";
}

function print_select_item_ext ($description,$value,$current,$disabled) {
if ($value == $current) { print "<option value=$value selected>$description</option>"; } 
    else {
    if (!$disabled) { print "<option value=$value>$description</option>"; } else { print "<option disabled value=$value>$description</option>"; }
    }
}

function print_row_at_pages ($name,$value) {
print "<select name='".$name."'>\n";
print_select_item('Много',pow(10,10),$value);
print_select_item('25',25,$value);
print_select_item('50',50,$value);
print_select_item('100',100,$value);
print_select_item('200',200,$value);
print_select_item('500',500,$value);
print_select_item('1000',1000,$value);
print_select_item('2000',2000,$value);
print "</select>\n";
}

function print_navigation($url,$page,$displayed,$count_records,$total) {
if ($total<=1) { return; }
#две назад
    print "<br><div align=left>";
    if(($page-2)>0):
      $pagetwoleft="<a class='first_page_link' href=".$url."?page=".($page-2).">".($page-2)."</a>  ";
    else:
      $pagetwoleft=null;
    endif;

#одна назад
    if(($page-1)>0):
      $pageoneleft="<a class='first_page_link' href=".$url."?page=".($page-1).">".($page-1)."</a>  ";
      $pagetemp=($page-1);
    else:
      $pageoneleft=null;
      $pagetemp=null;
    endif;

#две вперед
    if(($page+2)<=$total):
      $pagetworight="  <a class='first_page_link' href=".$url."?page=".($page+2).">".($page+2)."</a>";
    else:
      $pagetworight=null;
    endif;

#одна вперед
    if(($page+1)<=$total):
      $pageoneright="  <a class='first_page_link' href=".$url."?page=".($page+1).">".($page+1)."</a>";
      $pagetemp2=($page+1);
    else:
      $pageoneright=null;
      $pagetemp2=null;
    endif;

# в начало
    if($page!=1 && $pagetemp!=1 && $pagetemp!=2):
      $pagerevp="<a href=".$url."?page=1 class='first_page_link' title='В начало'><<</a> ";
    else:
      $pagerevp=null;
    endif;

#в конец (последняя)
    if($page!=$total && $pagetemp2!=($total-1) && $pagetemp2!=$total):
      $nextp=" ...  <a href=".$url."?page=".$total." class='first_page_link'>$total</a>";
    else:
      $nextp=null;
    endif;

print "<br>".$pagerevp.$pagetwoleft.$pageoneleft.'<span class="num_page_not_link"><b>'.$page.'</b></span>'.$pageoneright.$pagetworight.$nextp;
print " | Total records: $count_records";
print "</div>";
}

function get_option($db, $option_id)
{
    $option = get_record($db, "config", "option_id=".$option_id);
    if (empty($option) or empty($option['value'])) {
        $default = get_record($db, "config_options","id=$option_id");
	return $default['default_value'];
	}
    return $option['value'];
}

function is_option($db, $option_id)
{
    list ($option) = mysqli_fetch_array(mysqli_query($db, "SELECT value FROM `config` WHERE option_id=$option_id"));
    if (! isset($option) or empty($option) or $option === '') {
        return;
    }
    return 1;
}

function set_option($db, $option_id, $value)
{
    $option['value'] = $value;
    update_record($db, 'config', "option_id=$option_id", $option);
}

function is_subnet_aton($subnet,$ip) {
if (!isset($subnet)) { return 0; }
if (!isset($ip)) { return 0; }
$range = cidrToRange($subnet);
if ($ip>=ip2long($range[0]) and $ip <=ip2long($range[1])) { return 1; }
return 0;
}

function get_new_user_id($db, $ip, $mac, $hostname)
{
    global $hotspot_user_id;
    global $default_user_id;
    //ip
    if (!empty($ip)) {
        if (is_hotspot($db, $ip)) { return $hotspot_user_id; }
        $ip_aton = ip2long($ip);
        $t_rules = get_records_sql($db, "SELECT * FROM auth_rules WHERE type=1 and LENGTH(rule)>0");
        foreach ($t_rules as $row) {
            if (!empty($row['rule']) and is_subnet_aton($row['rule'],$ip_aton)) { return $row['user_id']; }
            }
        }
    //mac
    if (!empty($mac)) {
        $mac_rules=get_records_sql($db,"SELECT * FROM auth_rules WHERE type=2 AND LENGTH(rule)>0");
        foreach ($mac_rules as $row) {
            if (!empty($row['rule']) and preg_match($row['rule'], $mac)) { return $row['user_id']; }
            }
        }
    //hostname
    if (!empty($hostname)) {
        $mac_rules=get_records_sql($db,"SELECT * FROM auth_rules WHERE type=3 AND LENGTH(rule)>0");
        foreach ($mac_rules as $row) {
            if (!empty($row['rule']) and preg_match($row['rule'], $mac)) { return $row['user_id']; }
            }
        }
    return $default_user_id;
}

function get_subnet_range($db,$subnet_id) {
if (empty($subnet_id)) { return; }
$t_option = get_record_sql($db, "SELECT ip_int_start,ip_int_stop FROM `subnets` WHERE id=$subnet_id");
if (!isset($t_option['ip_int_start'])) { $t_option['ip_int_start']=0; }
if (!isset($t_option['ip_int_stop'])) { $t_option['ip_int_stop']=0; }
$subnet['start']=$t_option['ip_int_start'];
$subnet['stop']=$t_option['ip_int_stop'];
return $subnet;
}

function is_hotspot($db, $ip)
{
    if (! isset($ip)) { return 0; }
    LOG_DEBUG($db,"Check hotspot network for ip: $ip");
    $ip_aton = ip2long($ip);
    $t_option = mysqli_query($db, "SELECT subnet,ip_int_start,ip_int_stop FROM `subnets` WHERE hotspot=1");
    while (list ($f_net,$f_start,$f_stop) = mysqli_fetch_array($t_option)) {
        if ($ip_aton >= $f_start and $ip_aton <= $f_stop) {
    	    LOG_DEBUG($db,"ip: $ip [$ip_aton] found in network $f_net: [".$f_start."..".$f_stop."]");
            return 1;
        }
    }
    LOG_DEBUG($db,"ip $ip not found in hotspot network!");
    return 0;
}

function is_office($db, $ip)
{
    if (! isset($ip)) { return 0; }
    LOG_DEBUG($db,"Check office network for ip: $ip");
    $ip_aton = ip2long($ip);
    $t_option = mysqli_query($db, "SELECT subnet,ip_int_start,ip_int_stop FROM `subnets` WHERE office=1");
    while (list ($f_net,$f_start,$f_stop) = mysqli_fetch_array($t_option)) {
        if ($ip_aton >= $f_start and $ip_aton <= $f_stop) {
    	    LOG_DEBUG($db,"ip: $ip [$ip_aton] found in office $f_net: [".$f_start."..".$f_stop."]");
            return 1;
        }
    }
    LOG_DEBUG($db,"ip $ip not found in office network!");
    return 0;
}

function is_our_network($db, $ip)
{
    if (! isset($ip)) { return 0; }
    if (is_hotspot($db, $ip)) { return 1; }
    if (is_office($db, $ip)) { return 1; }
    return 0;
}

function init_option($db)
{
    global $org_name;
    $org_name = get_option($db, 32);

    global $KB;
    $KB = get_option($db, 1);

    global $debug;
    $debug = get_option($db, 34);

    global $log_level;
    $log_level = get_option($db, 53);

    if ($debug) { $log_level = 255; }

    global $send_email;
    $send_email = get_option($db, 51);

    global $admin_email;
    $admin_email = get_option($db, 21);

    global $sender_email;
    $sender_email = get_option($db, 52);

    global $mac_discovery;
    $mac_discovery = get_option($db, 17);

    global $snmp_default_version;
    $snmp_default_version = get_option($db, 9);

    global $snmp_default_community;
    $snmp_default_community = get_option($db, 11);

    global $default_user_id;
    $default_user_id = get_option($db, 20);

    global $hotspot_user_id;
    $hotspot_user_id = get_option($db, 43);

    if (! isset($hotspot_user_id)) { $hotspot_user_id = $default_user_id; }

    global $cacti_url;
    $cacti_url = rtrim(get_option($db, 58),'/');
    if (preg_match('/127.0.0.1/', $cacti_url)) { $cacti_url=NULL; }

    global $nagios_url;
    $nagios_url = rtrim(get_option($db, 57),'/').'/cgi-bin/';
    if (preg_match('/127.0.0.1/', $nagios_url)) { $nagios_url=NULL; }

    global $torrus_url;
    $torrus_url = rtrim(get_option($db, 59),'/').'?nodeid=if//HOST_IP//IF_NAME////inoutbps';
    if (preg_match('/127.0.0.1/', $torrus_url)) { $torrus_url=NULL; }

}

init_option($db_link);
clean_dns_cache($db_link);

?>
