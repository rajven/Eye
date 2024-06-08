<?php

require_once ($_SERVER['DOCUMENT_ROOT']."/inc/auth.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/languages/" . HTML_LANG . ".php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/idfilter.php");

$device=get_record($db_link,'devices',"id=".$id);
$user_info = get_record_sql($db_link,"SELECT * FROM User_list WHERE id=".$device['user_id']);

if (isset($_POST["s_remove"])) {
    $s_id = $_POST["s_id"];
    foreach ($s_id as $key => $val) {
        if (isset($val)) {
            LOG_INFO($db_link, "Remove l3_interface id: $val ". dump_record($db_link,'device_l3_interfaces','id='.$val));
            delete_record($db_link, "device_l3_interfaces", "id=" . $val);
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
            $new['name'] = trim($_POST['s_name'][$j]);
            $new['interface_type'] = $_POST['s_type'][$j]*1;
            update_record($db_link, "device_l3_interfaces", "id='{$save_id}'", $new);
        }
    }
    header("Location: " . $_SERVER["REQUEST_URI"]);
    exit;
}

if (isset($_POST["s_create"])) {
    if (!empty($_POST["s_create_name"])) {
        $new['name'] = trim($_POST["s_create_name"]);
        $new['device_id'] = $id;
        $new['interface_type'] = 0;
        LOG_INFO($db_link, "Create new l3_interface ".$new['name']." as local");
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
$t_l3_interface = get_records($db_link,'device_l3_interfaces',"device_id=$id ORDER BY name");
foreach ( $t_l3_interface as $row ) {
    print "<tr align=center>\n";
    print "<td class=\"data\" style='padding:0'><input type=checkbox name=s_id[] value='{$row['id']}'></td>\n";
    print "<td class=\"data\"><input type=\"hidden\" name='n_id[]' value='{$row['id']}'>{$row['id']}</td>\n";
    print "<td class=\"data\"><input type=\"text\" name='s_name[]' value='{$row['name']}'></td>\n";
    print "<td class=\"data\">"; print_qa_l3int_select('s_type[]',$row['interface_type']); print "</td>\n";
    print "<td class=\"data\"><button name='s_save[]' value='{$row['id']}'>".WEB_btn_save."</button></td>\n";
    print "</tr>\n";
    }
?>
<tr>
<td colspan=4><?php print WEB_l3_interface_add; print "&nbsp:<input type=\"text\" name='s_create_name' value=''";?>
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
