<?php
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/auth.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/languages/" . $language . ".php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/devtypesfilter.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/buildingfilter.php");
$default_sort='device_name';
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/sortfilter.php");

if (isset($_POST["create"])) {
    $fname = $_POST["newswitches"];
    if ($fname) {
        global $snmp_default_version;
        global $snmp_default_community;
        $new['device_name'] = $fname;
        $new['community'] = $snmp_default_community;
        $new['snmp_version'] = $snmp_default_version;
        $new_id=insert_record($db_link, "devices", $new);
        LOG_INFO($db_link, "Created new device device_name=$fname");
        unset($_POST);
        header("location: editdevice.php?id=$new_id");
    }
}

if (isset($_POST["remove"])) {
    $fid = $_POST["fid"];
    foreach ($fid as $key => $val) {
        if ($val) {
            LOG_INFO($db_link, "Delete device id: $val");
            unbind_ports($db_link, $val);
            delete_record($db_link, "connections", "device_id=$val");
            delete_record($db_link, "device_ports", "device_id=$val");
            $new['deleted'] = 1;
            update_record($db_link, "devices", "id='$val'", $new);
        }
    }
    header("Location: " . $_SERVER["REQUEST_URI"]);
}
unset($_POST);
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/header.php");
print_device_submenu($page_url);

$sort_sql=" ORDER BY device_name";
if (!empty($sort_field) and !empty($order)) { $sort_sql = " ORDER BY $sort_field $order"; }

?>
<div id="cont">
<br>
<form name="def" action="index.php" method="post">
<table class="data">
<tr class="info" align="center">
<td class="info" colspan=3 > Тип оборудования: </td>
<td class="info" colspan=2 > <?php  print_devtypes_select($db_link, "devtypes", $f_devtype_id); ?>
<td class="info" >Показать оборудование из</td>
<td class="info" > <?php  print_building_select($db_link, "building_id", $f_building_id); ?></td>
<td class="info" colspan=3> <input type="submit" onclick="return confirm('Применить?')" name="apply" value="Apply"></td>
</tr>
<tr align="center">
<td><input type="checkbox" onClick="checkAll(this.checked);"></td>
<td><b><a href=index.php?sort=id&order=<?php print $new_order; ?>>id</a></b></td>
<td><b><a href=index.php?sort=device_type&order=<?php print $new_order; ?>>Тип</a></b></td>
<td><b><a href=index.php?sort=device_name&order=<?php print $new_order; ?>>Название</a></b></td>
<td><b><a href=index.php?sort=ip&order=<?php print $new_order; ?>>IP</a></b></td>
<td><b><a href=index.php?sort=device_model_id&order=<?php print $new_order; ?>>Модель</a></b></td>
<td><b><a href=index.php?sort=building_id&order=<?php print $new_order; ?>>Расположен</a></b></td>
<td><b>Портов</b></td>
<td><b>Nagios</b></td>
<td><b>Discavery</b></td>
</tr>
<?
$filter = '';
if ($f_building_id > 0) { $filter .= ' and building_id=' . $f_building_id; }
if ($f_devtype_id > 0) { $filter .= ' and device_type=' . $f_devtype_id; }

$dSQL = 'SELECT * FROM devices WHERE deleted=0 '.$filter.' '.$sort_sql;
$switches = get_records_sql($db_link,$dSQL);
foreach ($switches as $row) {
    print "<tr align=center>\n";
    $cl = "data";
    if (isset($row['nagios_status'])) {
        $cl = 'shutdown';
        if ($row['nagios_status'] == 'UP') { $cl = 'up'; }
        }
    print "<td class=\"$cl\" style='padding:0'><input type=checkbox name=fid[] value=".$row['id']."></td>\n";
    print "<td class=\"$cl\"><input type=hidden name=\"id\" value=".$row['id'].">".$row['id']."</td>\n";
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
<table class="data">
<tr align=left>
<td>Название <input type=text name=newswitches value="Unknown"></td>
<td><input type="submit" name="create" value="Добавить"></td>
<td align="right"><input type="submit" onclick="return confirm('Удалить?')" name="remove" value="Удалить"></td>
</tr>
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
<?
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/footer.php");
?>
