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
<b><?php echo WEB_device_ports_by_vlan; ?> &nbsp</b> <?php print_vlan_select($db_link,'id',$id); ?>
<input type="submit" name="show_vlan" value="<?php echo WEB_btn_show; ?>">
<?php print WEB_rows_at_page; print_row_at_pages('rows',$displayed); ?>
</form>

<?php
$countSQL="SELECT Count(*) FROM device_ports AS DP, devices AS D WHERE D.id = DP.device_id AND DP.vlan=$id";
$count_records = get_single_field($db_link,$countSQL);
$total=ceil($count_records/$displayed);
if ($page>$total) { $page=$total; }
if ($page<1) { $page=1; }
$start = ($page * $displayed) - $displayed; 
print_navigation($page_url,$page,$displayed,$count_records,$total);
?>

<table class="data">
<tr>
<td><?php echo WEB_cell_name; ?></td>
<td><?php echo WEB_device_port_name; ?></td>
</tr>
<?php
$sSQL = "SELECT DP.id, DP.port, DP.device_id, D.device_name FROM device_ports AS DP, devices AS D WHERE D.id = DP.device_id AND DP.vlan=$id";
$ports_info = get_records_sql($db_link, $sSQL);
foreach ($ports_info as $row) {
    print "<tr>";
    print "<td class=\"data\"><a href=\"/admin/devices/editdevice.php?id=".$row['device_id']."\">" . $row['device_name']. "</a></td>\n";
    print "<td class=\"data\"><a href=\"/admin/devices/editport.php?id=".$row['id']."\">" . $row['port'] . "</a></td>\n";
    print "</tr>";
}
?>
</table>
<?php print_navigation($page_url,$page,$displayed,$count_records[0],$total); 
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/footer.php");
?>
