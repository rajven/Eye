<?php
$default_displayed=100;
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/auth.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/languages/" . HTML_LANG . ".php");
$default_id=1;
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/idfilter.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/header.php");
print_device_submenu($page_url);
?>
<div id="cont">
<br>
<form name="def" action="portsbyvlan.php" method="post">
<b>Список портов в влане &nbsp</b> <?php print_vlan_select($db_link,'id',$id); ?>
<input type="submit" name="show_vlan" value="Показать">
Отображать:<?php print_row_at_pages('rows',$displayed); ?>
</form>

<?php
$countSQL="SELECT Count(*) FROM `device_ports` AS DP, devices AS D WHERE D.id = DP.device_id AND DP.vlan=$id";
$res = mysqli_query($db_link, $countSQL);
$count_records = mysqli_fetch_array($res);
$total=ceil($count_records[0]/$displayed);
if ($page>$total) { $page=$total; }
if ($page<1) { $page=1; }
$start = ($page * $displayed) - $displayed; 
print_navigation($page_url,$page,$displayed,$count_records[0],$total);
?>

<table class="data">
<tr>
<td>Device</td>
<td>Port</td>
</tr>
<?php
$sSQL = "SELECT DP.id, DP.port, D.id, D.device_name FROM `device_ports` AS DP, devices AS D WHERE D.id = DP.device_id AND DP.vlan=$id";
$ports_info = mysqli_query($db_link, $sSQL);
while (list ($f_port_id,$f_port,$f_switch_id,$f_switch) = mysqli_fetch_array($ports_info)) {
    print "<tr>";
    print "<td class=\"data\"><a href=\"/admin/devices/editdevice.php?id=$f_switch_id\">" . $f_switch . "</a></td>\n";
    print "<td class=\"data\"><a href=\"/admin/devices/editport.php?id=$f_port_id\">" . $f_port . "</a></td>\n";
    print "</tr>";
}
?>
</table>
<?php print_navigation($page_url,$page,$displayed,$count_records[0],$total); ?>
</body>
</html>