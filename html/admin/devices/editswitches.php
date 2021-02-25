<?php
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/auth.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/languages/" . $language . ".php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/idfilter.php");

if (isset($_POST["editswitches"]) and isset($id)) {
    if (isset($_POST["f_port_count"])) {
        $sw_ports = $_POST["f_port_count"] * 1;
    } else {
        $sw_ports = 0;
    }
    $sSQL = "SELECT count(id) from device_ports WHERE device_ports.device_id=$id";
    $flist = mysqli_query($db_link, $sSQL);
    list ($d_ports) = mysqli_fetch_array($flist);
    if ($d_ports != $sw_ports) {
        LOG_DEBUG($db_link, "Device id: $id changed port count!");
        if ($sw_ports > $d_ports) {
            $start_port = $d_ports + 1;
            LOG_DEBUG($db_link, "Device id: $id add connection for port from $start_port to $sw_ports.");
            for ($port = $start_port; $port <= $sw_ports; $port ++) {
                $new[device_id] = $id;
                $new[snmp_index] = $port;
                $new[port] = $port;
                insert_record($db_link, "device_ports", $new);
            }
        }
        if ($sw_ports < $d_ports) {
            LOG_DEBUG($db_link, "Device id: $id remove connection for port from $d_ports to $sw_ports");
            for ($port = $d_ports; $port > $sw_ports; $port --) {
                $port_id = get_id_record($db_link, 'device_ports', "device_id='" . $id . "' and port='" . $port . "'");
                if ($port_id) {
                    delete_record($db_link, "device_ports", "id='" . $port_id . "'");
                    delete_record($db_link, "connections", "port_id='" . $port_id . "'");
                } else {
                    LOG_DEBUG($db_link, "Device id: $id port_id not found for port: $port!");
                }
            }
        }
    }
    unset($new);
    $new[device_name] = substr($_POST["f_device_name"], 0, 50);
    $new[device_model] = substr($_POST["f_device_model"], 0, 50);
    $new[comment] = $_POST["f_comment"];
    $new[ip] = substr($_POST["f_ip"], 0, 15);
    $new[mac] = mac_dotted($_POST["f_mac"]);
    $new[snmp_version] = $_POST["f_snmp_version"] * 1;
    $new[community] = substr($_POST["f_community"], 0, 50);
    $new[rw_community] = substr($_POST["f_rw_community"], 0, 50);
    $new[queue_enabled] = $_POST["f_queue_enabled"] * 1;
    $new[connected_user_only] = $_POST["f_connected_user_only"] * 1;
    $new[snmp3_user_rw] = substr($_POST["f_snmp3_user_rw"], 0, 20);
    $new[snmp3_user_ro] = substr($_POST["f_snmp3_user_ro"], 0, 20);
    $new[snmp3_user_rw_password] = substr($_POST["f_snmp3_user_rw_password"], 0, 20);
    $new[snmp3_user_ro_password] = substr($_POST["f_snmp3_user_ro_password"], 0, 20);
    $new[fdb_snmp_index] = $_POST["f_fdb_snmp"];
    $new[discovery] = $_POST["f_discovery"];
    $new[dhcp] = $_POST["f_dhcp"] * 1;
    $new[internet_gateway] = $_POST["f_gateway"] * 1;
    $new[wan_int] = $_POST["f_wan"];
    $new[lan_int] = $_POST["f_lan"];
    $new[building_id] = $_POST["f_building_id"] * 1;
    $new[user_id] = $_POST["f_user_id"] * 1;
    if ($new[internet_gateway]) {
        $new[is_router] = 1;
    } else {
        $new[is_router] = $_POST["f_router"];
    }
    $new[nagios] = $_POST["f_nagios"] * 1;
    if (! $new[nagios]) {
        $new[nagios_status] = 'UP';
    }
    $new[vendor_id] = $_POST["f_vendor_id"] * 1;
    $new[port_count] = $sw_ports;
    update_record($db_link, "devices", "id='$id'", $new);
    header("Location: " . $_SERVER["REQUEST_URI"]);
}

if (isset($_POST["undelete"]) and isset($id)) {
    unset($new);
    $new[deleted] = 0;
    LOG_INFO($db_link, "Recovery deleted device id: $id");
    update_record($db_link, "devices", "id='$id'", $new);
    header("Location: " . $_SERVER["REQUEST_URI"]);
}

$switch=get_record($db_link,'devices',"id=".$id);

unset($_POST);

require_once ($_SERVER['DOCUMENT_ROOT']."/inc/header.php");
print_editdevice_submenu($page_url,$id);

?>
<div id="cont">
<form name="def" action="editswitches.php?id=<? echo $id; ?>" method="post">
<table class="data">
<tr>
<td>Название</td>
<td>IP</td>
<td>Mac</td>
<td>Портов</td>
<td>Расположен</td>
</tr>
<?php
print "<tr>\n";
print "<td class=\"data\"><input type=\"text\" name='f_device_name' value='$switch[device_name]'></td>\n";
print "<td class=\"data\"><input type=\"text\" name='f_ip' value='$switch[ip]'></td>\n";
print "<td class=\"data\"><input type=\"text\" name='f_mac' value='$switch[mac]'></td>\n";
print "<td class=\"data\"><input type=\"text\" name='f_port_count' value=$switch[port_count] size=5></td>\n";
print "<td class=\"data\">";
print_building_select($db_link, 'f_building_id', $switch[building_id]);
print "</td>\n";
print "</tr>\n";
?>
</tr>
<td>Вендор</td>
<td>Модель</td>
<td colspan=3>Комментарий</td>
</tr>
<?php
print "<tr>\n";
print "<td class=\"data\">";
print_vendor_select($db_link, 'f_vendor_id', $switch[vendor_id]);
print "</td>\n";
print "<td class=\"data\"><input type=\"text\" name='f_device_model' value='$switch[device_model]'></td>\n";
print "<td class=\"data\" colspan=3><input type=\"text\" size=70 name='f_comment' value='$switch[comment]'></td>\n";
print "</tr>\n";
?>
<tr>
<td>Шлюз в интернет</td>
<td>Роутер</td>
<td>DHCP-Server</td>
<td>Шейперы</td>
<td>Только connected юзеры</td>
</tr>
<?php
print "<td class=\"data\">";
print_qa_select('f_gateway', $switch[internet_gateway]);
print "</td>\n";
print "<td class=\"data\">";
print_qa_select('f_router', $switch[is_router]);
print "</td>\n";
print "<td class=\"data\">";
print_qa_select('f_dhcp', $switch[dhcp]);
print "</td>\n";
print "<td class=\"data\">";
print_qa_select('f_queue_enabled', $switch[queue_enabled]);
print "</td>\n";
print "<td class=\"data\">";
print_qa_select('f_connected_user_only', $switch[connected_user_only]);
print "</td></tr>\n";
if ($switch[is_router] or $switch[internet_gateway] or $switch[dhcp]) {
    print "<tr><td colspan=2>WAN интерфейс</td><td colspan=2>LAN интерфейсы</td><td></td></tr>";
    print "<tr>\n";
    print "<td colspan=2 class=\"data\"><input type=\"text\" size=50 name='f_wan' value=$switch[wan_int]></td>\n";
    print "<td colspan=2 class=\"data\"><input type=\"text\" size=50 name='f_lan' value=$switch[lan_int]></td>\n";
    print "<td class=\"data\"></td>\n";
    print "<tr>\n";
    }
?>
</tr>
<td>Snmp Version</td>
<td>fdb by snmp</td>
<td>Discovery</td>
<td>Nagios</td>
<td>
<?php
if (isset($switch[user_id]) and $switch[user_id]>0) { print "<a href=/admin/users/edituser.php?id=$switch[user_id]>Auth user</a>\n"; } else { print "Auth user"; }
?>
</td>
</tr>
<?php
print "<td class=\"data\">";
print_snmp_select('f_snmp_version', $switch[snmp_version]);
print "</td>\n";
print "<td class=\"data\">";
print_qa_select('f_fdb_snmp', $switch[fdb_snmp_index]);
print "</td>\n";
print "<td class=\"data\">";
print_qa_select('f_discovery', $switch[discovery]);
print "</td>\n";
print "<td class=\"data\">";
print_qa_select('f_nagios', $switch[nagios]);
print "</td>\n";
print "<td class=\"data\">";
print_login_select($db_link,'f_user_id', $switch[user_id]);
print "</td>\n";
print "</tr>\n";
?>
<tr>
<td>Snmpv3 RO user</td>
<td>Snmpv3 RW user</td>
<td>Snmp RO Community</td>
<td>Snmp RW Community</td>
<?php
print "<td><button name=\"port_walk\" onclick=\"window.open('snmpwalk.php?id=" . $id . "')\">Port Walk</button>";
?>
<tr>
<?php
print "<td class=\"data\"><input type=\"text\" name='f_snmp3_user_ro' value=$switch[snmp3_user_ro]></td>\n";
print "<td class=\"data\"><input type=\"text\" name='f_snmp3_user_rw' value=$switch[snmp3_user_rw]></td>\n";
print "<td class=\"data\"><input type=\"text\" name='f_community' value=$switch[community]></td>\n";
print "<td class=\"data\"><input type=\"text\" name='f_rw_community' value=$switch[rw_community]></td>\n";
print "<td><button name=\"port_walk\" onclick=\"window.open('mactable.php?id=" . $id . "')\">Mac table</button></td>\n";
print "</tr>\n";
?>
<tr>
<td>Snmpv3 RO password</td>
<td>Snmpv3 RW password</td>
<td></td>
<td></td>
</tr>
<?php
print "<tr>\n";
print "<td class=\"data\"><input type=\"text\" name='f_snmp3_user_ro_password' value=$switch[snmp3_user_ro_password]></td>\n";
print "<td class=\"data\"><input type=\"text\" name='f_snmp3_user_rw_password' value=$switch[snmp3_user_rw_password]></td>\n";
print "<td colspan=2>";
if ($switch[deleted]) { print "<input type=\"submit\" name=\"undelete\" value=\"Воскресить\">"; }
print "</td>\n";
print "<td><input type=\"submit\" name=\"editswitches\" value=\"Сохранить\"></td>\n";
print "</tr>\n";
print "</table>\n";
?>
</form>

<?
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/footer.small.php");
?>
