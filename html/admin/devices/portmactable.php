<?php
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/auth.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/languages/" . HTML_LANG . ".php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/idfilter.php");
?>
<html>
<head>
<title>Панель администратора</title>
<link rel="stylesheet" type="text/css"	href=<?php echo "\"/HTML_STYLE.css\""; ?>>
<meta http-equiv="content-type" content="application/xhtml+xml">
<meta charset="UTF-8">
</head>
<body>
<div id="cont">
<?php
$port_id = $id;
$sSQL = "SELECT DP.port, DP.snmp_index, D.device_name, D.ip, D.snmp_version, D.community, D.fdb_snmp_index, D.vendor_id FROM `device_ports` AS DP, devices AS D WHERE D.id = DP.device_id AND DP.id=$port_id";
$port_info = get_record_sql($db_link, $sSQL);

$display_name = " ".$port_info['port']." свича ".$port_info['device_name'];
print "<b>".$port_info['device_name']." [".$port_info['port']."] </b><br>\n";

$sw_auth=NULL;
$sw_mac=NULL;
if ($port_info['vendor_id'] == 9) {
    $sw_auth = get_record_sql($db_link,"SELECT mac FROM User_auth WHERE deleted=0 and ip='".$port_info['ip']."'");
    $sw_mac = mac_simplify($sw_auth['mac']);
    $sw_mac = preg_replace("/.{2}$/","",$sw_mac);
    }

if ($port_info['snmp_index'] > 0) {
    print "<table class=\"data\" cellspacing=\"1\" cellpadding=\"4\">\n";
    print "<tr><td><b>Список маков активных на порту</b></td></tr>\n";
    if (! $port_info['fdb_snmp_index']) { $port_info['snmp_index'] = $port_info['port']; }
    $fdb = get_fdb_port_table($port_info['ip'], $port_info['snmp_index'], $port_info['community'], $port_info['snmp_version']);
    foreach ($fdb as $a_mac => $a_port) {
        $a_mac = dec_to_hex($a_mac);
        if (!empty($sw_mac) and preg_match('/^'.$sw_mac.'/',mac_simplify($a_mac))) { continue; }
        print "<tr>";
        $auth = get_auth_by_mac($db_link, $a_mac);
        print "<td class=\"data\">" .$auth['auth'] . "</td><td class=\"data\">". $auth['mac']."</td>\n";
        print "</tr>";
        }
    print "</table>\n";
    }
?>
<table class="data">
<tr>
<td>Mac</td>
<td>User</td>
<td>Last found</td>
</tr>
<?php
print "<b>Список маков когда-либо обнаруженных на порту</b><br>\n";
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
?>
</table>
</body>
</html>