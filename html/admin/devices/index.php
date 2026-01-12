<?php
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/auth.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/languages/" . HTML_LANG . ".php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/devtypesfilter.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/devmodelsfilter.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/buildingfilter.php");
$default_sort='device_name';
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/sortfilter.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/header.php");

if (getPOST("remove_device") !== null) {
    $dev_ids = getPOST("fid", null, []);
    
    if (!empty($dev_ids) && is_array($dev_ids)) {
        foreach ($dev_ids as $val) {
            $val = trim($val);
            if ($val !== '') {
                delete_device($db_link, (int)$val);
            }
        }
    }
    
    header("Location: " . $_SERVER["REQUEST_URI"]);
    exit;
}

print_device_submenu($page_url);

?>

<div id="cont">
<br>
<form name="def" action="index.php" method="post">
<table class="data">
<tr class="info" align="right">
<td class="info" colspan=3> <?php  print WEB_device_type_show; print_devtypes_select($db_link, "devtypes", $f_devtype_id, "id<3"); ?>
<td class="info" colspan=3 align=left> <?php  print WEB_models; print_devmodels_select($db_link, "devmodels", $f_devmodel_id); ?>
<?php print WEB_device_show_location; print_building_select($db_link, "building_id", $f_building_id); ?></td>
<td class="info"><input type="submit" id="apply" name="apply" value="<?php echo WEB_btn_show; ?>"></td>
<td class="info" colspan=3><input type="submit" onclick="return confirm('<?php echo WEB_msg_delete; ?>?')" name="remove_device" value="<?php echo WEB_btn_delete; ?>"></td>
</tr>
<tr align="center">
<td align=Center><input type="checkbox" onClick="checkAll(this.checked);"></td>
<td><b><a href=index.php?sort=device_type&order=<?php print $new_order; ?>><?php echo WEB_cell_type; ?></a></b></td>
<td><b><a href=index.php?sort=device_name&order=<?php print $new_order; ?>><?php echo WEB_cell_name; ?></a></b></td>
<td><b><a href=index.php?sort=ip_int&order=<?php print $new_order; ?>><?php echo WEB_cell_ip; ?></a></b></td>
<td><b><a href=index.php?sort=model_name&order=<?php print $new_order; ?>><?php echo WEB_cell_host_model; ?></a></b></td>
<td style="width: 1%; white-space: nowrap;"><b><?php echo WEB_cell_sn; ?></b></td>
<td><b><a href=index.php?sort=building_name&order=<?php print $new_order; ?>><?php echo WEB_location_name; ?></a></b></td>
<td><b><?php echo WEB_device_port_count; ?></b></td>
<td><b><?php echo WEB_nagios; ?></b></td>
<td><b><?php echo WEB_network_discovery; ?></b></td>
</tr>
<?php
$filter = '';
$params=[];
if ($f_building_id > 0) { $filter .= ' and building_id=?'; $params[]=$f_building_id; }
if ($f_devtype_id >= 0) { $filter .= ' and device_type=?'; $params[]=$f_devtype_id; } else { $filter .= ' and device_type<=2'; }
if ($f_devmodel_id > 0) { $filter .= ' and device_model_id=?'; $params[]= $f_devmodel_id; }

#$countSQL = "SELECT COUNT(*)  FROM devices D
#LEFT JOIN device_models DM ON D.device_model_id = DM.id
#LEFT JOIN building B ON D.building_id = B.id
#WHERE D.deleted = 0 $filter";
#$count_records = get_single_field($db_link, $countSQL, $params);

#$total=ceil($count_records/$displayed);
#if ($page>$total) { $page=$total; }
#if ($page<1) { $page=1; }
#$start = ($page * $displayed) - $displayed;
#print_navigation($page_url,$page,$displayed,$count_records,$total);

$sort_sql=" ORDER BY device_name";
if (!empty($sort_field) and !empty($order)) { $sort_sql = " ORDER BY $sort_field $order"; }

$dSQL = 'SELECT D.*, DM.model_name, B.name AS building_name FROM devices D
LEFT JOIN device_models DM ON D.device_model_id = DM.id
LEFT JOIN building B ON D.building_id = B.id
WHERE D.deleted = 0 ' . $filter . ' ' . $sort_sql;

$switches = get_records_sql($db_link,$dSQL, $params);
foreach ($switches as $row) {
    print "<tr align=center>\n";
    $cl = "data";
    if (isset($row['nagios_status'])) {
        $cl = 'shutdown';
        if ($row['nagios_status'] == 'UP') { $cl = 'up'; }
        }
    print "<td class=\"$cl\" style='padding:0'><input type=checkbox name=fid[] value=".$row['id']."></td>\n";
    print "<td class=\"$cl\">".get_devtype_name($db_link,$row['device_type'])."</td>\n";
    print "<td class=\"$cl\" align=left><a href=editdevice.php?id=".$row['id'].">" . $row['device_name'] . "</a></td>\n";
    if (isset($row['user_id']) and $row['user_id']>0) {
        print "<td class=\"$cl\"><a href=/admin/users/edituser.php?id=".$row['user_id'].">".$row['ip']."</a></td>\n";
        } else {
        print "<td class=\"$cl\">".$row['ip']."</td>\n";
        }
    print "<td class=\"$cl\">" . get_vendor_name($db_link, $row['vendor_id']) . " " . $row['model_name'] . "</td>\n";
    print '<td class="'.$cl.'" style="width: 1%; white-space: nowrap;">' . $row['SN'] ."</td>\n";
    print "<td class=\"$cl\">" . get_building($db_link, $row['building_id']);
    if (!empty($row['description'])) { print  '<hr style="opacity: 0;">' . $row['description']; }
    print "</td>\n";
    print "<td class=\"$cl\">" . $row['port_count'] . "</td>\n";
    print "<td class=\"$cl\">" . get_qa($row['nagios']) . "</td>\n";
    print "<td class=\"$cl\">" . get_qa($row['discovery']) . "</td>\n";
}
?>
</table>
</form>
<table class="data">
<tr><td><?php echo WEB_color_device_description; ?></td></tr>
<tr>
<td class="shutdown"><?php echo WEB_device_down; ?></td>
<td class="up"><?php echo WEB_device_online; ?></td>
<tr>
</table>

<script>
document.getElementById('devtypes').addEventListener('change', function(event) {
  const buttonApply = document.getElementById('apply');
  buttonApply.click();
});

document.getElementById('devmodels').addEventListener('change', function(event) {
  const buttonApply = document.getElementById('apply');
  buttonApply.click();
});

document.getElementById('building_id').addEventListener('change', function(event) {
  const buttonApply = document.getElementById('apply');
  buttonApply.click();
});

</script>

<?php
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/footer.php");
?>
