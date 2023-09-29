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

$snmp_ok = 0;
if (!empty($device['ip']) and $device['snmp_version'] > 0) {
	$snmp_ok = check_snmp_access($device['ip'], $device['community'], $device['snmp_version']);
	}

if ($snmp_ok) {
    $interfaces = get_snmp_interfaces($device['ip'], $device['community'], $device['snmp_version']);
    $dev_info = walk_snmp($device['ip'], $device['community'], $device['snmp_version'],SYSINFO_MIB);
    foreach ($dev_info as $key => $value) {
        $v_data = trim(parse_snmp_value($value));
        if (!empty($v_data)) { print "$v_data<br>"; }
        }
    print "<table  class=\"data\" cellspacing=\"1\" cellpadding=\"4\">\n";
    print "<tr><td><b>".WEB_snmp_interface_index."</div></b></td><td><b>".WEB_snmp_interface_name."</b></td></tr>\n";
    foreach ($interfaces as $key => $int) { 
        print "<tr><td class=\"data\">$key</td><td class=\"data\"> $int</td></tr>"; 
        }
    print "</table>\n";
    } else { print "No SNMP access!"; }

require_once ($_SERVER['DOCUMENT_ROOT']."/inc/footer.php");
?>