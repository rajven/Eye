<?php
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/auth.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/languages/" . HTML_LANG . ".php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/idfilter.php");

$device=get_record($db_link,'devices',"id=".$id);
$user_info = get_record_sql($db_link,"SELECT * FROM User_list WHERE id=".$device['user_id']);

require_once ($_SERVER['DOCUMENT_ROOT']."/inc/header.php");

print_device_submenu($page_url);
print_editdevice_submenu($page_url,$id,$device['device_type'],$user_info['login']);

?>

<div id="contsubmenu">
<?php
$ports = get_records($db_link,'device_ports',"device_id=$id AND uplink=0 ORDER BY port");
print "<b>".WEB_device_mac_table_show."&nbsp".$device['device_name']." (".$device['ip']."):</b>\n";
$fdb = get_fdb_table($device['ip'], $device['community'], $device['snmp_version']);
print "<table class=\"data\" cellspacing=\"1\" cellpadding=\"4\">\n";
print "<tr>";
print "<td>Port</td>\n";
print "<td>User</td>\n";
print "<td>Mac</td>\n";
print "</tr>";
foreach ($ports as $port) {
    if (!$device['fdb_snmp_index']) { $port['snmp_index'] = $port['port']; }
    foreach ($fdb as $a_mac => $a_port) {
	if ($a_port == $port['snmp_index']) {
		print "<tr>";
		print "<td class=\"data\">" . $port['port'] . "</td>\n";
	        $auth = get_auth_by_mac($db_link, dec_to_hex($a_mac));
                print "<td class=\"data\">" .$auth['auth'] . "</td><td class=\"data\">". $auth['mac']."</td>\n";
		print "</tr>";
		}
    }
}
print "</table>\n";
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/footer.php");
?>