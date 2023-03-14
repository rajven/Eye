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
$interfaces = get_snmp_interfaces($device['ip'], $device['community'], $device['snmp_version']);
$dev_info = walk_snmp($device['ip'], $device['community'], $device['snmp_version'],SYSINFO_MIB);
foreach ($dev_info as $key => $value) {
list ($v_type,$v_data)=explode(':',$value);
$v_clean = preg_replace('/\s/', '', $v_data);
if (empty($v_clean)) { continue; }
print "$v_data<br>";
}
print "<table  class=\"data\" cellspacing=\"1\" cellpadding=\"4\">\n";
print "<tr><td><b>Interface index</b></td><td><b>Interface name</b></td></tr>\n";
foreach ($interfaces as $key => $int) { 
list ($v_type,$v_data)=explode(':',$int);
print "<tr><td class=\"data\">$key</td><td class=\"data\"> $v_data</td></tr>"; 
}
print "</table>\n";
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/footer.php");
?>