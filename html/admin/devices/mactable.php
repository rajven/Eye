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
$dev = get_record($db_link,'devices',"id=$id");
$ports = get_records($db_link,'device_ports',"device_id=$id AND uplink=0 ORDER BY port");
print "<b>Список маков активных на свиче ".$dev['device_name']." (".$dev['ip']."):</b>\n";
$fdb = get_fdb_table($dev['ip'], $dev['community'], $dev['snmp_version']);
print "<table class=\"data\" cellspacing=\"1\" cellpadding=\"4\">\n";
print "<tr>";
print "<td>Port</td>\n";
print "<td>User</td>\n";
print "<td>Mac</td>\n";
print "</tr>";
foreach ($ports as $port) {
    foreach ($fdb as $a_mac => $a_port) {
	if ($a_port == $port['port']) {
		print "<tr>";
		print "<td class=\"data\">" . $a_port . "</td>\n";
	        $auth = get_auth_by_mac($db_link, dec_to_hex($a_mac));
                print "<td class=\"data\">" .$auth['auth'] . "</td><td class=\"data\">". $auth['mac']."</td>\n";
		print "</tr>";
		}
    }
}
print "</table>\n";
?>
</body>
</html>