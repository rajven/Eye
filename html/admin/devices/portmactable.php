<?php
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/auth.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/languages/" . HTML_LANG . ".php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/idfilter.php");

$port_id = $id;
$sSQL = "SELECT DP.device_id, DP.port, DP.snmp_index, D.device_name, D.ip, D.snmp_version, D.community, D.vendor_id FROM `device_ports` AS DP, devices AS D WHERE D.id = DP.device_id AND DP.id=$port_id";
$port_info = get_record_sql($db_link, $sSQL);

$device_id = $port_info["device_id"];

$sSQL = "SELECT port, snmp_index FROM `device_ports` WHERE device_id=".$device_id;
$ports_info = get_records_sql($db_link, $sSQL);
$ports_by_snmp_index=NULL;
foreach ($ports_info as &$row) { $ports_by_snmp_index[$row["snmp_index"]]=$row["port"]; }

$device=get_record($db_link,'devices',"id=".$device_id);
$user_info = get_record_sql($db_link,"SELECT * FROM User_list WHERE id=".$device['user_id']);

require_once ($_SERVER['DOCUMENT_ROOT']."/inc/header.php");

if (!apply_device_lock($db_link,$device_id)) {
    header("Location: /admin/devices/editdevice.php?id=".$device_id."&status=locked");
    exit;
}

print_device_submenu($page_url);
print_editdevice_submenu($page_url,$device_id,$device['device_type'],$user_info['login']);

?>

<div id="contsubmenu">
<?php

$display_name = " ".$port_info['port']." свича ".$port_info['device_name'];
print "<b>".$port_info['device_name']." [".$port_info['port']."] </b><br>\n";

$sw_auth=NULL;
$sw_mac=NULL;
if ($port_info['vendor_id'] == 9) {
    $sw_auth = get_record_sql($db_link,"SELECT mac FROM User_auth WHERE deleted=0 and ip='".$port_info['ip']."'");
    $sw_mac = mac_simplify($sw_auth['mac']);
    $sw_mac = preg_replace("/.{2}$/","",$sw_mac);
    }

$snmp_ok = 0;
if (!empty($device['ip']) and $device['snmp_version'] > 0) {
        $snmp_ok = check_snmp_access($device['ip'], $device['community'], $device['snmp_version']);
    }

if ($snmp_ok and $port_info['snmp_index'] > 0) {
    print "<table class=\"data\" cellspacing=\"1\" cellpadding=\"4\">\n";
    print "<tr><td colspan=2><b>".WEB_device_port_mac_table_show."</b></td></tr>\n";
    $fdb = get_fdb_table($port_info['ip'], $port_info['community'], $port_info['snmp_version']);
    $f_port = $port_info['snmp_index'];
    $port_by_snmp = 0;
    foreach ($fdb as $a_mac => $a_port) {
        if (!empty($ports_by_snmp_index[$a_port])) { $port_by_snmp=1; break; }
    }
    if (!$port_by_snmp) { $f_port = $port_info['port']; }
    foreach ($fdb as $a_mac => $a_port) {
        if ($a_port == $f_port) {
            $a_mac = dec_to_hex($a_mac);
            //mikrotik patch
            if (!empty($sw_mac) and preg_match('/^'.$sw_mac.'/',mac_simplify($a_mac))) { continue; }
            print "<tr>";
            $auth = get_auth_by_mac($db_link, $a_mac);
            print "<td class=\"data\">" .$auth['auth'] . "</td><td class=\"data\">". $auth['mac']."</td>\n";
            print "</tr>";
            }
        }
    print "</table>\n";
    } else { print "No SNMP access!"; }
    unset_lock_discovery($db_link,$device_id);
?>
<table class="data">
<tr>
<td><?php echo WEB_cell_mac; ?></td>
<td><?php echo WEB_cell_login; ?></td>
<td><?php echo WEB_cell_last_found; ?></td>
</tr>
<?php
print "<b>".WEB_device_port_mac_table_history."</b><br>\n";
$d_sql = "select A.ip,A.ip_int,A.mac,A.id,A.dns_name,A.last_found from User_auth as A, connections as C where C.port_id=$port_id and A.id=C.auth_id order by A.ip_int";
$t_device = mysqli_query($db_link, $d_sql);
while (list ($f_ip, $f_int, $f_mac, $f_auth_id, $f_dns, $f_last) = mysqli_fetch_array($t_device)) {
    $name = $f_ip;
    if (isset($f_dns) and $f_dns != '') {
        $name = $f_dns;
    }
    print "<tr>";
    print "<td class=\"data\">" . expand_mac($db_link,$f_mac) . "</td>\n";
    print "<td class=\"data\"><a href=\"/admin/users/editauth.php?id=$f_auth_id\">" . $name . "</a></td>\n";
    print "<td class=\"data\">$f_last</td>\n";
    print "</tr>";
}

$maclist = mysqli_query($db_link, "SELECT mac,timestamp from Unknown_mac where port_id=$port_id order by timestamp desc");
while (list ($fmac, $f_last) = mysqli_fetch_array($maclist)) {
    print "<tr>";
    print "<td class=\"data\">" . expand_mac($db_link,$fmac) . "</td>\n";
    print "<td class=\"data\">Unknown</td>\n";
    print "<td class=\"data\">$f_last</td>\n";
    print "</tr>";
}
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/footer.php");
?>
