<?php

require_once ($_SERVER['DOCUMENT_ROOT']."/inc/auth.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/languages/" . HTML_LANG . ".php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/idfilter.php");

$device=get_record($db_link,'devices',"id=".$id);
$user_info = get_record_sql($db_link,"SELECT * FROM User_list WHERE id=".$device['user_id']);

if (isset($_POST["s_remove"])) {
    $s_id = $_POST["gs_id"];
    foreach ($s_id as $key => $val) {
        if (isset($val)) {
            LOG_INFO($db_link, "Remove filter instances from gateway id: $val ". dump_record($db_link,'device_filter_instances','id='.$val));
            delete_record($db_link, "device_filter_instances", "id=" . $val);
        }
    }
    header("Location: " . $_SERVER["REQUEST_URI"]);
    exit;
}

if (isset($_POST["s_create"])) {
    if (!empty($_POST["new_instance"])) {
        $new['instance_id'] = trim($_POST["new_instance"]);
        $new['device_id'] = $id;
        LOG_INFO($db_link, "Add instance id: ".$new['instance_id']." for gateway id: ".$id);
        insert_record($db_link, "device_filter_instances", $new);
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
<?php print "<form name=def action='edit_gw_instances.php?id=".$id."' method=post>"; ?>
<?php 
print WEB_group_instances."<b>";
print_url($device['device_name'],"/admin/devices/editdevice.php?id=$id"); ?>
</b>
<br>
<br>
<table class="data">
<tr align="center">
        <td></td>
        <td width=10><b>id</b></td>
        <td><b><?php echo WEB_group_instance_name; ?></b></td>
        <td>
        <input type="submit" onclick="return confirm('<?php print WEB_msg_delete; ?>?')" name="s_remove" value="<?php print WEB_btn_remove; ?>">
        </td>
</tr>
<?php
$gateway_instances = get_records_sql($db_link,'SELECT device_filter_instances.*,filter_instances.name,filter_instances.comment FROM device_filter_instances LEFT JOIN filter_instances ON device_filter_instances.instance_id = filter_instances.id WHERE device_filter_instances.device_id='.$id);
foreach ( $gateway_instances as $row ) {
    print "<tr align=center>\n";
    print "<td class=\"data\" style='padding:0' width=30><input type=checkbox name=gs_id[] value='{$row['id']}'></td>\n";
    print "<td class=\"data\"><input type=\"hidden\" name='n_id[]' value='{$row['id']}'>{$row['id']}</td>\n";
    print "<td class=\"data\">"; print get_filter_instance_description($db_link,$row['instance_id']); print "</td>\n";
    print "<td class=\"data\"></td></tr>\n";
    }
?>
<tr>
<td colspan=3><?php print WEB_btn_add; print_add_gw_instances($db_link,$id,"new_instance"); ?>
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
