<?php
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/auth.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/languages/" . $language . ".php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/idfilter.php");
?>
<html>
<head>
<title>Панель администратора</title>
<link rel="stylesheet" type="text/css" href=<? echo "\"/$style.css\""; ?>>
<meta http-equiv="content-type" content="application/xhtml+xml">
<meta charset="UTF-8">
</head>
<body>
<div id="cont">
<?php
$dev_info = get_record($db_link,'devices','id='.$id);
$interfaces = get_snmp_interfaces($dev_info['ip'], $dev_info['community'], $dev_info['snmp_version']);
global $sysinfo_mib;
$dev_info = walk_snmp($dev_info['ip'], $dev_info['community'], $dev_info['snmp_version'],$sysinfo_mib);
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
?>
</div>
</body>
</html>
