<?php
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/auth.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/languages/" . HTML_LANG . ".php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/idfilter.php");

$device=get_record($db_link,'devices',"id=".$id);
$snmp = getSnmpAccess($device);
$user_info = get_record_sql($db_link,"SELECT * FROM user_list WHERE id=".$device['user_id']);

require_once ($_SERVER['DOCUMENT_ROOT']."/inc/header.php");

print_device_submenu($page_url);
print_editdevice_submenu($page_url,$id,$device['device_type'],$user_info['login']);

$sSQL = "SELECT port, snmp_index FROM `device_ports` WHERE device_id=".$id;
$ports_info = get_records_sql($db_link, $sSQL);
$ports_by_snmp_index=NULL;
foreach ($ports_info as &$row) { $ports_by_snmp_index[$row["snmp_index"]]=$row["port"]; }

if (!apply_device_lock($db_link,$id)) {
    header("Location: /admin/devices/editdevice.php?id=".$id."&status=locked");
    exit;
}

?>

<div id="contsubmenu">
<?php
$ports = get_records($db_link,'device_ports',"device_id=$id AND uplink=0 ORDER BY port");
print "<b>".WEB_device_mac_table_show."&nbsp".$device['device_name']." (".$device['ip']."):</b>\n";

$snmp_ok = 0;
if (!empty($device['ip']) and $device['snmp_version'] > 0) {
	$snmp_ok = check_snmp_access($device['ip'], $snmp);
	}

if ($snmp_ok) {
	$fdb = get_fdb_table($device['ip'], $snmp);

	$port_by_snmp = 0;
   	foreach ($fdb as $a_mac => $a_port) {
		if (!empty($ports_by_snmp_index[$a_port])) { $port_by_snmp=1; break; }
	}

	print "<table class=\"data\" cellspacing=\"1\" cellpadding=\"4\">\n";
	print "<tr>";
	print "<td>Port</td>\n";
	print "<td>User</td>\n";
	print "<td>Mac</td>\n";
	print "</tr>";
	foreach ($ports as $port) {
    	foreach ($fdb as $a_mac => $a_port) {
			if ($port_by_snmp) { $f_port =$port['snmp_index']; } else { $f_port = $port['port']; }
			if ($a_port == $f_port) {
				print "<tr>";
				print "<td class=\"data\">" . $port['port'] . "</td>\n";
	    		$auth = get_auth_by_mac($db_link, dec_to_hex($a_mac));
        		print "<td class=\"data\">" .$auth['auth'] . "</td><td class=\"data\">". $auth['mac']."</td>\n";
				print "</tr>";
				}
    	}
	}
	print "</table>\n";
	} else {
	print "No SNMP access!";
	}

unset_lock_discovery($db_link,$id);
require_once($_SERVER['DOCUMENT_ROOT'] . "/inc/footer.simple.php");
?>