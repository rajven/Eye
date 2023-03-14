<?php
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/auth.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/languages/" . HTML_LANG . ".php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/idfilter.php");

if (isset($_POST["editdevice"]) and isset($id)) {
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
                    run_sql($db_link, "DELETE FROM connections WHERE port_id='" . $port_id . "'");
                } else {
                    LOG_DEBUG($db_link, "Device id: $id port_id not found for port: $port!");
                }
            }
        }
    }
    unset($new);
    if (isset($_POST["f_ip"])) { $new['ip'] = $_POST["f_ip"]; }
    $cur_device = get_record_sql($db_link,"SELECT * FROM devices WHERE id=".$id);
    if (!empty($new['ip'])) { $cur_auth = get_record_sql($db_link,"SELECT * FROM User_auth WHERE deleted=0 AND ip='".$new['ip']."'"); }
    if (isset($_POST["f_device_model_id"])) { $new['device_model_id'] = $_POST["f_device_model_id"]*1; }
    if (isset($_POST["f_devtype_id"])) { $new['device_type'] = $_POST["f_devtype_id"]*1; }
    if (isset($_POST["f_comment"])) { $new['comment'] = $_POST["f_comment"]; }
    if (isset($_POST["f_SN"])) { $new['SN'] = $_POST["f_SN"]; }
    if (isset($_POST["f_firmware"])) { $new['firmware'] = $_POST["f_firmware"]; }
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
    if (isset($_POST["f_user_acl"])) { $new['user_acl'] = $_POST["f_user_acl"] * 1; }
    if (isset($_POST["f_wan"])) { $new['wan_int'] = $_POST["f_wan"]; }
    if (isset($_POST["f_lan"])) { $new['lan_int'] = $_POST["f_lan"]; }
    if (isset($_POST["f_building_id"])) { $new['building_id'] = $_POST["f_building_id"] * 1; }
    if (isset($_POST["f_nagios"])) {
	$new['nagios'] = $_POST["f_nagios"] * 1;
        if ($new['nagios'] ==0) { $new['nagios_status']='UP'; }
	} else {
	if (!empty($cur_auth)) {
	    $new['nagios']=0;
	    $new['nagios_status']=$cur_auth['nagios_status'];
	    }
	}
    $new['vendor_id'] = get_device_model_vendor($db_link,$new['device_model_id']);
    if (isset($_POST["f_port_count"])) { $new['port_count'] = $sw_ports; }
    update_record($db_link, "devices", "id='$id'", $new);
    header("Location: " . $_SERVER["REQUEST_URI"]);
    exit;
}

$device=get_record($db_link,'devices',"id=".$id);
$user_info = get_record_sql($db_link,"SELECT * FROM User_list WHERE id=".$device['user_id']);
unset($_POST);

require_once ($_SERVER['DOCUMENT_ROOT']."/inc/header.php");

print_device_submenu($page_url);
print_editdevice_submenu($page_url,$id,$device['device_type']);

?>
<div id="contsubmenu">
<form name="def" action="editdevice.php?id=<?php echo $id; ?>" method="post">
<table class="data">
<tr>
<td><?php echo WEB_cell_name; ?></td>
<td><?php echo WEB_cell_ip; ?></td>
<td><?php echo WEB_cell_type; ?></td>
<?php
if ($device['device_type']<=2) { print "<td>".WEB_device_port_count."</td>"; } else { print "<td></td>"; }
print "</tr>";
print "<tr>\n";
print "<td class='data'><a href=/admin/users/edituser.php?id=".$device['user_id'].">".$user_info['login']."</a></td>\n";
print "<td class='data'>"; print_device_ip_select($db_link,'f_ip',$device['ip'],$device['user_id']); print "</td>\n";
print "<td class='data'>"; print_devtype_select($db_link,'f_devtype_id',$device['device_type']); print "</td>\n";
if ($device['device_type']<=2) { print "<td class='data'><input type='text' name='f_port_count' value='".$device['port_count']."' size=5></td>\n"; } else { print "<td></td>"; }
print "</tr>\n";
?>
</tr>
<td colspan=2><?php echo WEB_cell_host_model; ?></td>
<td><?php echo WEB_cell_host_firmware; ?></td>
<td><?php echo WEB_cell_sn; ?></td>
<?php
print "<tr>\n";
print "<td class='data' colspan=2>"; print_device_model_select($db_link,'f_device_model_id',$device['device_model_id']); print "</td>\n";
print "<td class='data' ><input type='text' name='f_firmware' value='".$device['firmware']."'></td>\n";
print "<td class='data' ><input type='text' name='f_SN' value='".$device['SN']."'></td>\n";
print "</tr>\n";
print "<tr><td>".WEB_location_name."</td><td colspan=2>".WEB_cell_comment."</td><td><td>";
print "</tr><tr>";
print "<td class='data'>"; print_building_select($db_link, 'f_building_id', $device['building_id']); print "</td>\n";
print "<td class='data' colspan=3><input type='text' size=50 name='f_comment' value='".$device['comment']."'></td>\n";
print "</tr>";
if ($device['device_type']==2) {
    print "<tr><td>".WEB_device_access_control."</td><td>".WEB_device_dhcp_server."</td><td>".WEB_device_queues_enabled."</td><td>".WEB_device_connected_only."</td></tr>";
    print "<tr>";
    print "<td class='data'>"; print_qa_select('f_user_acl', $device['user_acl']); print "</td>\n"; 
    print "<td class='data'>"; print_qa_select('f_dhcp', $device['dhcp']); print "</td>\n";
    print "<td class='data'>"; print_qa_select('f_queue_enabled', $device['queue_enabled']); print "</td>\n";
    print "<td class='data'>"; print_qa_select('f_connected_user_only', $device['connected_user_only']); print "</td>\n";
    print "</tr>\n";
    print "<tr><td colspan=4>"; print_url(WEB_list_l3_interfaces,"/admin/devices/edit_l3int.php?id=$id"); print "</td></tr>";
    print "<tr><td colspan=4 class='data'>"; print get_l3_interfaces($db_link,$device['id']); print "</td></tr>";
    }
if ($device['device_type']<=2) {
    print "<tr><td>".WEB_snmp_version."</td><td><p title='".WEB_device_snmp_comment."'>".WEB_device_mac_by_oid."</p></td>";
    print "<td>".WEB_network_discovery."</td><td>".WEB_nagios."</td></tr>";
    print "<tr><td class='data'>"; print_snmp_select('f_snmp_version', $device['snmp_version']); print "</td>\n";
    print "<td class='data'>"; print_qa_select('f_fdb_snmp', $device['fdb_snmp_index']); print "</td>\n";
    print "<td class='data'>"; print_qa_select('f_discovery', $device['discovery']); print "</td>\n";
    print "<td class='data'>"; print_qa_select('f_nagios', $device['nagios']); print "</td>\n";
    print "</tr>";
    if ($device['snmp_version'] ==3) {
        print "<tr><td>".WEB_snmp_v3_user_ro."</td><td>".WEB_snmp_v3_user_rw."</td><td>".WEB_snmp_v3_ro_password."</td><td>".WEB_snmp_v3_rw_password."</td><td></td>";
	    print "</tr><tr>";
        print "<td class='data'><input type='text' name='f_snmp3_user_ro' value=".$device['snmp3_user_ro']."></td>\n";
	    print "<td class='data'><input type='text' name='f_snmp3_user_rw' value=".$device['snmp3_user_rw']."></td>\n";
        print "<td class='data'><input type='text' name='f_snmp3_user_ro_password' value=".$device['snmp3_user_ro_password']."></td>\n";
	    print "<td class='data'><input type='text' name='f_snmp3_user_rw_password' value=".$device['snmp3_user_rw_password']."></td>\n";
        print "</tr>\n";
	    }
    print "<tr><td>".WEB_snmp_community_ro."</td><td>".WEB_snmp_community_rw."</td><td></td><td></td></tr>";
    print "<tr>\n";
    print "<td class='data'><input type='text' name='f_community' value=".$device['community']."></td>\n";
    print "<td class='data'><input type='text' name='f_rw_community' value=".$device['rw_community']."></td>\n";
    print "<td><button name='mac_walk' onclick=\"window.open('mactable.php?id=" . $id . "')\">".WEB_device_mac_table."</button>";
    print "<button name='port_walk' onclick=\"window.open('snmpwalk.php?id=" . $id . "')\">".WEB_device_walk_port_list."</button></td>";
    print "<td></td>";
    print "</tr>";
    }
if ($device['device_type']>2) {
    print "<tr><td>".WEB_snmp_version."</td><td>".WEB_snmp_community_ro."</td><td>".WEB_snmp_community_rw."</td><td></td></tr>";
    print "<tr><td class='data'>"; print_snmp_select('f_snmp_version', $device['snmp_version']); print "</td>\n";
    print "<td class='data'><input type='text' name='f_community' value=".$device['community']."></td>\n";
    print "<td class='data'><input type='text' name='f_rw_community' value=".$device['rw_community']."></td>\n";
    print "<dtd></td></tr>";
    if ($device['snmp_version'] ==3) {
        print "<tr><td>".WEB_snmp_v3_user_ro."</td><td>".WEB_snmp_v3_user_rw."</td><td>".WEB_snmp_v3_ro_password."</td><td>".WEB_snmp_v3_rw_password."</td><td></td>";
	    print "</tr><tr>";
        print "<td class='data'><input type='text' name='f_snmp3_user_ro' value=".$device['snmp3_user_ro']."></td>\n";
	    print "<td class='data'><input type='text' name='f_snmp3_user_rw' value=".$device['snmp3_user_rw']."></td>\n";
        print "<td class='data'><input type='text' name='f_snmp3_user_ro_password' value=".$device['snmp3_user_ro_password']."></td>\n";
	    print "<td class='data'><input type='text' name='f_snmp3_user_rw_password' value=".$device['snmp3_user_rw_password']."></td>\n";
        print "<td></td></tr>\n";
	    }
    }
    print "<tr><td colspan=4 align=right><input type='submit' name='editdevice' value='".WEB_btn_save."'></td></tr>";
    print "</table>\n";
?>
</form>
<?php require_once ($_SERVER['DOCUMENT_ROOT']."/inc/footer.small.php"); ?>
