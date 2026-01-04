<?php
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/auth.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/languages/" . HTML_LANG . ".php");

if (isset($_POST['save'])) {
    $len = is_array($_POST['r_id']) ? count($_POST['r_id']) : 0;
    for ($i = 0; $i < $len; $i ++) {
        $id = intval($_POST['r_id'][$i]);
        $new['queue_name'] = trim($_POST['f_queue_name'][$i]);
        $new['Download'] = $_POST['f_down'][$i] * 1;
        $new['Upload'] = $_POST['f_up'][$i] * 1;
        update_record($db_link, "queue_list", "id='{$id}'", $new);
    }
    header("Location: " . $_SERVER["REQUEST_URI"]);
    exit;
}

if (isset($_POST["create"])) {
    $queue_name = $_POST["new_queue"];
    if (isset($queue_name)) {
        $q['queue_name'] = $queue_name;
        insert_record($db_link, "queue_list", $q);
    }
    header("Location: " . $_SERVER["REQUEST_URI"]);
    exit;
}

unset($_POST);
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/header.php");
?>
<div id="cont">
<b><?php echo WEB_list_queues; ?></b> <br>
<form name="def" action="index.php" method="post">
<table class="data">
<tr align="center">
	<td><input type="checkbox" onClick="checkAll(this.checked);"></td>
	<td><b>Id</b></td>
	<td><b><?php echo WEB_cell_name; ?></b></td>
	<td><b>Download</b></td>
	<td><b>Upload</b></td>
	<td><input type="submit" onclick="return confirm('<?php echo WEB_msg_delete; ?>?')" name="remove" value="<?php echo WEB_btn_delete; ?>"></td>
</tr>
<?php
$t_queue=get_records($db_link, "queue_list",'TRUE ORDER BY id');
foreach ($t_queue as $row) {
    print "<tr align=center>\n";
    print "<td class=\"data\" style='padding:0'><input type=checkbox name=f_id[] value='{$row['id']}'></td>\n";
    print "<td class=\"data\"><input type=\"hidden\" name='r_id[]' value='{$row['id']}'>{$row['id']}</td>\n";
    print "<td class=\"data\"><input type=\"text\" name='f_queue_name[]' value='{$row['queue_name']}'></td>\n";
    print "<td class=\"data\"><input type=\"text\" name='f_down[]' value='{$row['Download']}'></td>\n";
    print "<td class=\"data\"><input type=\"text\" name='f_up[]' value='{$row['Upload']}'></td>\n";
    print "<td class=\"data\"><input type=\"submit\" name=\"save\" value='".WEB_btn_save."'></td>\n";
    print "</tr>\n";
}
?>
</table>
<div>
<input type=text name=new_queue value="New_queue">
<input type="submit" name="create" value="<?php echo WEB_btn_add; ?>">
</div>
</form>
<?php
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/footer.php");
?>