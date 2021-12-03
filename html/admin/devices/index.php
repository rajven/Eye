<?php
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/auth.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/languages/" . $language . ".php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/devtypesfilter.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/buildingfilter.php");
$default_sort='device_name';
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/sortfilter.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/header.php");

print_device_submenu($page_url);

$sort_sql=" ORDER BY device_name";
if (!empty($sort_field) and !empty($order)) { $sort_sql = " ORDER BY $sort_field $order"; }

?>
<div id="cont">
<br>
<form name="def" action="index.php" method="post">
<table class="data">
<tr class="info" align="right">
<td class="info" colspan=6> Тип оборудования: <?php  print_devtypes_select($db_link, "devtypes", $f_devtype_id, "id<3"); ?>
Показать оборудование из <?php  print_building_select($db_link, "building_id", $f_building_id); ?></td>
<td class="info" colspan=2> <input type="submit" name="apply" value="Показать"></td>
</tr>
<tr align="center">
<td><b><a href=index.php?sort=device_type&order=<?php print $new_order; ?>>Тип</a></b></td>
<td><b><a href=index.php?sort=device_name&order=<?php print $new_order; ?>>Название</a></b></td>
<td><b><a href=index.php?sort=ip&order=<?php print $new_order; ?>>IP</a></b></td>
<td><b><a href=index.php?sort=device_model_id&order=<?php print $new_order; ?>>Модель</a></b></td>
<td><b><a href=index.php?sort=building_id&order=<?php print $new_order; ?>>Расположен</a></b></td>
<td><b>Портов</b></td>
<td><b>Nagios</b></td>
<td><b>Discavery</b></td>
</tr>
<?php
$filter = '';
if ($f_building_id > 0) { $filter .= ' and building_id=' . $f_building_id; }
if ($f_devtype_id > 0) { $filter .= ' and device_type=' . $f_devtype_id; } else { $filter .= ' and device_type<=2'; }

$dSQL = 'SELECT * FROM devices WHERE deleted=0 '.$filter.' '.$sort_sql;
$switches = get_records_sql($db_link,$dSQL);
foreach ($switches as $row) {
    print "<tr align=center>\n";
    $cl = "data";
    if (isset($row['nagios_status'])) {
        $cl = 'shutdown';
        if ($row['nagios_status'] == 'UP') { $cl = 'up'; }
        }
    print "<td class=\"$cl\">".get_devtype_name($db_link,$row['device_type'])."</td>\n";
    print "<td class=\"$cl\" align=left><a href=editdevice.php?id=".$row['id'].">" . $row['device_name'] . "</a></td>\n";
    if (isset($row['user_id']) and $row['user_id']>0) {
        print "<td class=\"$cl\"><a href=/admin/users/edituser.php?id=".$row['user_id'].">".$row['ip']."</a></td>\n";
        } else {
        print "<td class=\"$cl\">".$row['ip']."</td>\n";
        }
    print "<td class=\"$cl\">" . get_vendor_name($db_link, $row['vendor_id']) . " " . get_device_model($db_link,$row['device_model_id']) . "</td>\n";
    print "<td class=\"$cl\">" . get_building($db_link, $row['building_id']) . "(" . $row['comment'] . ")</td>\n";
    print "<td class=\"$cl\">".$row['port_count']."</td>\n";
    print "<td class=\"$cl\">" . get_qa($row['nagios']) . "</td>\n";
    print "<td class=\"$cl\">" . get_qa($row['discovery']) . "</td>\n";
}
?>
</table>
</form>
<table class="data">
<tr>
<td>Device status</td>
</tr>
<tr>
<td class="shutdown">Down</td>
<td class="up">Online</td>
<tr>
</table>
<?php
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/footer.php");
?>
