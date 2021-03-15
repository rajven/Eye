<?php
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/auth.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/languages/" . $language . ".php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/idfilter.php");
?>
<html>
<head>
<title>Панель администратора</title>
<link rel="stylesheet" type="text/css"	href=<? echo "\"/$style.css\""; ?>>
<meta http-equiv="content-type" content="application/xhtml+xml">
<meta charset="UTF-8">
</head>
<body>
<div id="cont">
<?php
$port_id = $id;
$sSQL = "SELECT DP.port, DP.snmp_index, D.device_name, D.ip, D.snmp_version, D.community, D.fdb_snmp_index FROM `device_ports` AS DP, devices AS D WHERE D.id = DP.device_id AND DP.id=$port_id";
$port_info = mysqli_query($db_link, $sSQL);
list ($f_port, $f_snmp_index, $f_switch, $f_ip, $f_version, $f_community, $f_snmp) = mysqli_fetch_array($port_info);
$display_name = " $f_port свича $f_switch";
print "<b>$f_switch [$f_port] </b><br>\n";

if ($f_snmp_index > 0) {
    print "<table class=\"data\" cellspacing=\"1\" cellpadding=\"4\">\n";
    print "<tr><td><b>Список маков активных на порту</b></td></tr>\n";
    if (! $f_snmp) {
        $f_snmp_index = $f_port;
    }
    $fdb = get_fdb_port_table($f_ip, $f_snmp_index, $f_community, $f_version);
    foreach ($fdb as $a_mac => $a_port) {
        print "<tr>";
        $auth = get_auth_by_mac($db_link, dec_to_hex($a_mac));
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