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
                $new['device_id'] = $id;
                $new['snmp_index'] = $port;
                $new['port'] = $port;
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
    if (isset($_POST["f_device_name"])) { $new['device_name'] = substr($_POST["f_device_name"], 0, 50); }
    if (isset($_POST["f_device_model"])) { $new['device_model'] = substr($_POST["f_device_model"], 0, 50); }
    if (isset($_POST["f_devtype_id"])) { $new['device_type'] = $_POST["f_devtype_id"]*1; }
    if (isset($_POST["f_comment"])) { $new['comment'] = $_POST["f_comment"]; }
    if (isset($_POST["f_SN"])) { $new['SN'] = $_POST["f_SN"]; }
    if (isset($_POST["f_ip"])) { $new['ip'] = substr($_POST["f_ip"], 0, 15); }
    if (isset($_POST["f_snmp_version"])) { $new['snmp_version'] = $_POST["f_snmp_version"] * 1; }
    if (isset($_POST["f_community"])) { $new['community'] = substr($_POST["f_community"], 0, 50); }
    if (isset($_POST["f_rw_community"])) { $new['rw_community'] = substr($_POST["f_rw_community"], 0, 50); }
    if (isset($_POST["f_queue_enabled"])) { $new['queue_enabled'] = $_POST["f_queue_enabled"] * 1; }
    if (isset($_POST["f_connected_user_only"])) { $new['connected_user_only'] = $_POST["f_connected_user_only"] * 1; }
    if (isset($_POST["f_snmp3_user_rw"])) { $new['snmp3_user_rw'] = substr($_POST["f_snmp3_user_rw"], 0, 20); }
    if (isset($_POST["f_snmp3_user_ro"])) { $new['snmp3_user_ro'] = substr($_POST["f_snmp3_user_ro"], 0, 20); }
    if (isset($_POST["f_snmp3_user_rw_password"])) { $new['snmp3_user_rw_password'] = substr($_POST["f_snmp3_user_rw_password"], 0, 20); }
    if (isset($_POST["f_snmp3_user_ro_password"])) { $new['snmp3_user_ro_password'] = substr($_POST["f_snmp3_user_ro_password"], 0, 20); }
    if (isset($_POST["f_fdb_snmp"])) { $new['fdb_snmp_index'] = $_POST["f_fdb_snmp"]; }
    if (isset($_POST["f_discovery"])) { $new['discovery'] = $_POST["f_discovery"]; }
    if (isset($_POST["f_dhcp"])) { $new['dhcp'] = $_POST["f_dhcp"] * 1; }
    if (isset($_POST["f_user_acl"])) { $new['user_acl'] = $_POST["user_acl"] * 1; }
    if (isset($_POST["f_wan"])) { $new['wan_int'] = $_POST["f_wan"]; }
    if (isset($_POST["f_lan"])) { $new['lan_int'] = $_POST["f_lan"]; }
    if (isset($_POST["f_building_id"])) { $new['building_id'] = $_POST["f_building_id"] * 1; }
    if (isset($_POST["f_user_id"])) { $new['user_id'] = $_POST["f_user_id"] * 1; }
    if (isset($_POST["f_nagios"])) { $new['nagios'] = $_POST["f_nagios"] * 1; }
    if (empty($new['nagios'])) { $new['nagios_status'] = 'UP'; }
    if (isset($_POST["f_vendor_id"])) { $new['vendor_id'] = $_POST["f_vendor_id"] * 1; }
    if (isset($_POST["f_port_count"])) { $new['port_count'] = $sw_ports; }
    update_record($db_link, "devices", "id='$id'", $new);
    header("Location: " . $_SERVER["REQUEST_URI"]);
}

if (isset($_POST["undelete"]) and isset($id)) {
    unset($new);
    $new['deleted'] = 0;
    LOG_INFO($db_link, "Recovery deleted device id: $id");
    update_record($db_link, "devices", "id='$id'", $new);
    header("Location: " . $_SERVER["REQUEST_URI"]);
}

$device=get_record($db_link,'devices',"id=".$id);

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
<td>Тип</td>
<td>Портов</td>
</tr>
<?php
print "<tr>\n";
print "<td class=\"data\"><input type=\"text\" name='f_device_name' value='".$device['device_name']."'></td>\n";
print "<td class=\"data\"><input type=\"text\" name='f_ip' value='".$device['ip']."'></td>\n";
print "<td class=\"data\">"; print_devtype_select($db_link,'f_devtype_id',$device['device_type']); print "</td>\n";
print "<td class=\"data\"><input type=\"text\" name='f_port_count' value='".$device['port_count']."' size=5></td>\n";
print "</tr>\n";
?>
</tr>
<td>Вендор</td>
<td>Модель</td>
<td colspan=2>SN</td>

<?php
print "<tr>\n";
print "<td class=\"data\">"; print_vendor_select($db_link, 'f_vendor_id', $device['vendor_id']); print "</td>\n";
print "<td class=\"data\"><input type=\"text\" name='f_device_model' value='".$device['device_model']."'></td>\n";
print "<td class=\"data\" colspan=2><input type=\"text\" size=50 name='f_SN' value='".$device['SN']."'></td>\n";
print "</tr>\n";

print "<tr><td>Расположен</td><td colspan=2>Комментарий</td>";
if (isset($device['user_id']) and $device['user_id']>0) { print "<td align=right><a href=/admin/users/edituser.php?id=".$device['user_id'].">Auth user</a><td>\n"; } else { print "<td>Auth user<td>"; }
print "</tr><tr>";
print "<td class=\"data\">"; print_building_select($db_link, 'f_building_id', $device['building_id']); print "</td>\n";
print "<td class=\"data\" colspan=2><input type=\"text\" size=50 name='f_comment' value='".$device['comment']."'></td>\n";
print "<td class=\"data\">"; print_login_select($db_link,'f_user_id', $device['user_id']); print "</td>\n";
print "</tr>";

if ($device['device_type']==2) {
    print "<tr><td>Управление доступом</td><td>DHCP-Server</td><td>Шейперы</td><td>Только connected юзеры</td>";
    print "<tr>";
    print "<td class=\"data\">"; print_qa_select('f_user_acl', $device['user_acl']); print "</td>\n"; 
    print "<td class=\"data\">"; print_qa_select('f_dhcp', $device['dhcp']); print "</td>\n";
    print "<td class=\"data\">"; print_qa_select('f_queue_enabled', $device['queue_enabled']); print "</td>\n";
    print "<td class=\"data\">"; print_qa_select('f_connected_user_only', $device['connected_user_only']); print "</td>\n";
    print "</tr>\n";
    print "<tr><td colspan=4>"; print_url("Список интерфейсов","/admin/devices/edit_l3int.php?id=$id"); print "</td></tr>";
    print "<tr>\n";
    print "<td colspan=4 class=\"data\">"; print get_l3_interfaces($db_link,$device['id']); print "</td>\n";
    print "<tr>\n";
    }
?>
</tr>
<td>Snmp Version</td>
<td>fdb by snmp</td>
<td>Discovery</td>
<td>Nagios</td>
<td>
</td>
<?php
print "<tr>\n";
print "<td class=\"data\">"; print_snmp_select('f_snmp_version', $device['snmp_version']); print "</td>\n";
print "<td class=\"data\">"; print_qa_select('f_fdb_snmp', $device['fdb_snmp_index']); print "</td>\n";
print "<td class=\"data\">"; print_qa_select('f_discovery', $device['discovery']); print "</td>\n";
print "<td class=\"data\">"; print_qa_select('f_nagios', $device['nagios']); print "</td>\n";
print "</tr>\n";

if ($device['snmp_version'] ==3) {
    print "<tr><td>Snmpv3 RO user</td><td>Snmpv3 RW user</td><td>Snmpv3 RO password</td><td>Snmpv3 RW password</td><td></td>";
    print "</tr><tr>";
    print "<td class=\"data\"><input type=\"text\" name='f_snmp3_user_ro' value=".$device['snmp3_user_ro']."></td>\n";
    print "<td class=\"data\"><input type=\"text\" name='f_snmp3_user_rw' value=".$device['snmp3_user_rw']."></td>\n";
    print "<td class=\"data\"><input type=\"text\" name='f_snmp3_user_ro_password' value=".$device['snmp3_user_ro_password']."></td>\n";
    print "<td class=\"data\"><input type=\"text\" name='f_snmp3_user_rw_password' value=".$device['snmp3_user_rw_password']."></td>\n";
    print "<td></td></tr>\n";
    }
?>
<tr>
<td>Snmp RO Community</td>
<td>Snmp RW Community</td>
<td></td>
<td></td>
</tr>
<?php
print "<tr>\n";
print "<td class=\"data\"><input type=\"text\" name='f_community' value=".$device['community']."></td>\n";
print "<td class=\"data\"><input type=\"text\" name='f_rw_community' value=".$device['rw_community']."></td>\n";
print "<td><button name=\"port_walk\" onclick=\"window.open('mactable.php?id=" . $id . "')\">Mac table</button>\n";
print "<button name=\"port_walk\" onclick=\"window.open('snmpwalk.php?id=" . $id . "')\">Port Walk</button>";
print "<td align=right>";
if ($device['deleted']) { print "<input type=\"submit\" name=\"undelete\" value=\"Воскресить\">"; }
print "<input type=\"submit\" name=\"editswitches\" value=\"Сохранить\"></td>\n";
print "</tr>\n";
print "</table>\n";
?>
</form>
<?
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/footer.small.php");
?>
