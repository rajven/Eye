<?php

require_once ($_SERVER['DOCUMENT_ROOT']."/inc/auth.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/languages/" . HTML_LANG . ".php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/idfilter.php");

$device = get_record($db_link,'devices',"id=?",[$id]);
$snmp=getSnmpAccess($device);
$user_info = get_record_sql($db_link,"SELECT * FROM user_list WHERE id=?",[$device['user_id']]);
$int_list = getIpAdEntIfIndex($db_link,$device['ip'],$snmp);

if (isset($_POST["s_remove"])) {
    $s_id = $_POST["s_id"];
    foreach ($s_id as $key => $val) {
        if (isset($val)) {
            LOG_INFO($db_link, "Remove l3_interface id: $val ". dump_record($db_link,'device_l3_interfaces','id=?',[$val]));
            delete_record($db_link, "device_l3_interfaces", "id=?", [$val]);
        }
    }
    header("Location: " . $_SERVER["REQUEST_URI"]);
    exit;
}

if (isset($_POST['s_save'])) {
    $len = is_array($_POST['s_save']) ? count($_POST['s_save']) : 0;
    for ($i = 0; $i < $len; $i ++) {
        $save_id = intval($_POST['s_save'][$i]);
        $len_all = is_array($_POST['n_id']) ? count($_POST['n_id']) : 0;
        for ($j = 0; $j < $len_all; $j ++) {
            if (intval($_POST['n_id'][$j]) != $save_id) { continue; }
            $new['interface_type'] = $_POST['s_type'][$j]*1;
            update_record($db_link, "device_l3_interfaces", "id=?", $new, [$save_id]);
        }
    }
    header("Location: " . $_SERVER["REQUEST_URI"]);
    exit;
}

if (isset($_POST["s_create"])) {
    if (!empty($_POST["s_create_name"])) {
        $new = NULL;
        list($new['name'],$new['snmpin'],$new['interface_type']) = explode(";", trim($_POST["s_create_name"]));
        $new['device_id'] = $id;
        $new['name']=preg_replace('/\"/','',$new['name']);
        LOG_INFO($db_link, "Create new l3_interface ".$new['name']);
        insert_record($db_link, "device_l3_interfaces", $new);
    }
    header("Location: " . $_SERVER["REQUEST_URI"]);
    exit;
}

unset($_POST);

require_once ($_SERVER['DOCUMENT_ROOT']."/inc/header.php");

print_device_submenu($page_url);
print_editdevice_submenu($page_url,$id,$device['device_type'],$user_info['login']);

?>
<div id="contsubmenu">
<br>
<?php print "<form name=def action='edit_l3int.php?id=".$id."' method=post>"; ?>
<?php print WEB_list_l3_interfaces."<b>"; print_url($device['device_name'],"/admin/devices/editdevice.php?id=$id"); ?>
</b> <br>
<table class="data">
<tr align="center">
	<td></td>
	<td width=30><b>id</b></td>
	<td><b><?php echo WEB_cell_name; ?></b></td>
	<td><b><?php echo WEB_cell_type; ?></b></td>
	<td>
	<input type="submit" onclick="return confirm('<?php print WEB_msg_delete; ?>?')" name="s_remove" value="<?php print WEB_btn_remove; ?>">
	</td>
</tr>
<?php

$t_l3_interface = get_records_sql($db_link,"SELECT * FROM device_l3_interfaces WHERE device_id=? ORDER BY name", [ $id ]);

$int_by_name = [];
foreach ($int_list as $row) {
    $row['name'] = preg_replace('/\"/','',$row['name']);
    $int_by_name[$row['name']]=$row;
}
$fixed = 0;

//fixing snmp index if not exists by interface name
foreach ( $t_l3_interface as $row ) {
    $fix = NULL;
    if (empty($row['snmpin']) and !empty($int_by_name[$row['name']])) {
        $fix['snmpin']=$int_by_name[$row['name']]['index'];
        if (!empty($fix)) {
            update_record($db_link,'device_l3_interfaces','id=?',$fix, [ $row['id'] ]);
            }
        $fixed = 1;
        }
    }

//updating interface name by snmp index
foreach ( $t_l3_interface as $row ) {
    $fix = NULL;
    if (!empty($int_list[$row['snmpin']]) and $int_list[$row['snmpin']]['name'] !== $row['name']) {
        $fix['name']=$int_list[$row['snmpin']]['name'];
        if (!empty($fix)) {
            update_record($db_link,'device_l3_interfaces','id=?', $fix, [$row['id']]);
            }
        $fixed = 1;
        }
    }

if ($fixed) {
    $t_l3_interface = get_records_sql($db_link,"SELECT * FROM device_l3_interfaces WHERE device_id=? ORDER BY name", [ $id ]);
    }

foreach ( $t_l3_interface as $row ) {
    print "<tr align=center>\n";
    print "<td class=\"data\" style='padding:0'><input type=checkbox name=s_id[] value='{$row['id']}'></td>\n";
    print "<td class=\"data\"><input type=\"hidden\" name='n_id[]' value='{$row['id']}'>{$row['snmpin']}</td>\n";
    print "<td class=\"data\">".$row['name'].'/'.$int_list[$row['snmpin']]['ip']."</td>\n";
    print "<td class=\"data\">"; print_qa_l3int_select('s_type[]',$row['interface_type']); print "</td>\n";
    print "<td class=\"data\"><button name='s_save[]' value='{$row['id']}'>".WEB_btn_save."</button></td>\n";
    print "</tr>\n";
    }
?>
<tr>
<td colspan=4><?php print WEB_l3_interface_add; print_add_dev_interface($db_link, $id, $int_list, 's_create_name');?>
</td>
<td>
<input type="submit" name="s_create" value="<?php echo WEB_btn_add; ?>">
</td>
</tr>
</table>
</form>
<?php
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/footer.php");
?>
