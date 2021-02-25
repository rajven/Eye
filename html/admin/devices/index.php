<?php
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/auth.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/languages/" . $language . ".php");

if (isset($_POST["create"])) {
    $fname = $_POST["newswitches"];
    if ($fname) {
        global $snmp_default_version;
        global $snmp_default_community;
        $new[device_name] = $fname;
        $new[community] = $snmp_default_community;
        $new[snmp_version] = $snmp_default_version;
        insert_record($db_link, "devices", $new);
        $sSQL = "Select id from devices where device_name='$fname' order by id DESC";
        list ($new_id) = mysqli_fetch_array(mysqli_query($db_link, $sSQL));
        LOG_INFO($db_link, "Created new device device_name=$fname");
        unset($_POST);
        header("location: editswitches.php?id=$new_id");
    }
}

if (isset($_POST["building_id"])) {
    $f_building_id = $_POST["building_id"] * 1;
} else {
    $f_building_id = 0;
}

if (isset($_POST["remove"])) {
    $fid = $_POST["fid"];
    while (list ($key, $val) = @each($fid)) {
        if ($val) {
            LOG_INFO($db_link, "Delete device id: $val");
            unbind_ports($db_link, $val);
            delete_record($db_link, "connections", "device_id=$val");
            delete_record($db_link, "device_ports", "device_id=$val");
            $new[deleted] = 1;
            update_record($db_link, "devices", "id='$val'", $new);
        }
    }
    header("Location: " . $_SERVER["REQUEST_URI"]);
}
unset($_POST);
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/header.php");
print_device_submenu($page_url);
?>
<div id="cont">
<br>
<form name="def" action="index.php" method="post">
<table class="data">
<tr class="info" align="center">
<td class="info" colspan=5>Показать оборудование из</td>
<td class="info" colspan=5> <?php  print_building_select($db_link, "building_id", $f_building_id); ?> <input type="submit" name="apply" value="Apply"></td>
</tr>
<tr align="center">
<td><input type="checkbox" onClick="checkAll(this.checked);"></td>
<td><b>id</b></td>
<td><b>Название</b></td>
<td><b>IP</b></td>
<td><b>Модель</b></td>
<td><b>Расположен</b></td>
<td><b>Портов</b></td>
<td><b>Nagios</b></td>
<td><b>Router</b></td>
<td><b>Discavery</b></td>
</tr>
<?
$filter = '';
if ($f_building_id > 0) { $filter = ' and building_id=' . $f_building_id; }

$switches = get_records($db_link,'devices','deleted=0 '.$filter.' ORDER BY ip');
foreach ($switches as $row) {
    print "<tr align=center>\n";
    $cl = "data";
    if (isset($row['nagios_status'])) {
        if ($row['nagios_status'] == 'DOWN') { $cl = 'shutdown'; }
        if ($row['nagios_status'] == 'UP') { $cl = 'up'; }
    }
    print "<td class=\"$cl\" style='padding:0'><input type=checkbox name=fid[] value=".$row['id']."></td>\n";
    print "<td class=\"$cl\"><input type=hidden name=\"id\" value=".$row['id'].">".$row['id']."</td>\n";
    print "<td class=\"$cl\" align=left><a href=editswitches.php?id=".$row['id'].">" . $row['device_name'] . "</a></td>\n";
    if (isset($row['user_id']) and $row['user_id']>0) {
        print "<td class=\"$cl\"><a href=/admin/users/edituser.php?id=".$row['user_id'].">".$row['ip']."</a></td>\n";
        } else {
        print "<td class=\"$cl\">".$row['ip']."</td>\n";
        }
    print "<td class=\"$cl\">" . get_vendor_name($db_link, $row['vendor_id']) . " " . $row['device_model'] . "</td>\n";
    print "<td class=\"$cl\">" . get_building($db_link, $row['building_id']) . "(" . $row['comment'] . ")</td>\n";
    print "<td class=\"$cl\">".$row['port_count']."</td>\n";
    print "<td class=\"$cl\">" . get_qa($row['nagios']) . "</td>\n";
    print "<td class=\"$cl\">" . get_qa($row['is_router']) . "</td>\n";
    print "<td class=\"$cl\">" . get_qa($row['discovery']) . "</td>\n";
}
?>
</table>
<table class="data">
<tr align=left>
<td>Название <input type=text name=newswitches value="Unknown"></td>
<td><input type="submit" name="create" value="Добавить"></td>
<td align="right"><input type="submit" name="remove" value="Удалить"></td>
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
