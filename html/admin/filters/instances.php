<?php
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/auth.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/languages/" . HTML_LANG . ".php");

if (isset($_POST['save'])) {
    $len = is_array($_POST['f_id']) ? count($_POST['f_id']) : 0;
    for ($i = 0; $i < $len; $i ++) {
        $save_id = intval($_POST['f_id'][$i]);
        $len_all = is_array($_POST['r_id']) ? count($_POST['r_id']) : 0;
        for ($j = 0; $j < $len_all; $j ++) {
            if (intval($_POST['r_id'][$j]) != $save_id) { continue; }
            $id = intval($_POST['r_id'][$j]);
            $new['name'] = trim($_POST['f_name'][$j]);
            $new['comment'] = trim($_POST['f_comment'][$j]);
            update_record($db_link, "filter_instances", "id='$id'", $new);
        }
    }
    header("Location: " . $_SERVER["REQUEST_URI"]);
    exit;
}

if (isset($_POST["create"])) {
    $instance_name = trim($_POST["new_instance"]);
    if (!empty($instance_name)) {
        $instance['name'] = $instance_name;
        insert_record($db_link, "filter_instances", $instance);
    }
    header("Location: " . $_SERVER["REQUEST_URI"]);
    exit;
}

if (isset($_POST["remove"])) {
    $len = is_array($_POST['r_id']) ? count($_POST['r_id']) : 0;
    for ($i = 0; $i < $len; $i ++) {
        $id = intval($_POST['r_id'][$i]);
        if (!empty($id) and $id>1) {
	    $deleted_groups = get_records_sql($db_link,"SELECT * FROM Group_list WHERE `instance_id`>1 AND `instance_id`=".$id);
	    foreach ($deleted_groups as $d_group) {
	        run_sql($db_link, "UPDATE User_auth SET filter_group_id=0, changed = 1 WHERE deleted=0 AND filter_group_id=" . $d_group['id']);
		delete_record($db_link, "Group_list", "id=" . $d_group['id']);
		}
            delete_record($db_link, "filter_instances", "id=" . $id * 1);
        }
    }
    header("Location: " . $_SERVER["REQUEST_URI"]);
    exit;
}

unset($_POST);
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/header.php");
print_filters_submenu($page_url);
?>
<div id="cont">
<form name="def" action="instances.php" method="post">
<table class="data">
<tr align="center">
	<td><input type="checkbox" onClick="checkAll(this.checked);"></td>
	<td><b>Id</b></td>
	<td><b><?php echo WEB_group_instance_name; ?></b></td>
	<td><b><?php echo WEB_cell_comment; ?></b></td>
	<td><input type="submit" onclick="return confirm('<?php echo WEB_msg_delete; ?>?')" name="remove" value="<?php echo WEB_btn_delete; ?>"></td>
        <?php print "<td><input type=\"submit\" name=\"save\" value='".WEB_btn_save."'></td>"; ?>
</tr>
<?php
$t_instance=get_records_sql($db_link, "SELECT * FROM filter_instances");
foreach ($t_instance as $row) {
    print "<tr align=center>";
    print "<td class=\"data\" style='padding:0'><input type=checkbox name=f_id[] value='".$row['id']."'></td>";
    print "<td class=\"data\"><input type=\"hidden\" name='r_id[]' value='".$row['id']."'>".$row['id']."</td>";
    print "<td class=\"data\"><input type=\"text\" name='f_name[]' value='".$row['name']."'></td>";
    print "<td class=\"data\"><input type=\"text\" name='f_comment[]' value='".$row['comment']."'></td>";
    print "<td colspan=2 class=\"data\"></td>";
    print "</tr>";
}
?>
</table>
<div>
<input type=text name=new_instance value="New_instance">
<input type="submit" name="create" value="<?php echo WEB_btn_add; ?>">
</div>
</form>
<?php
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/footer.php");
?>