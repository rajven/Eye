<?php
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/auth.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/languages/" . HTML_LANG . ".php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/idfilter.php");

$device=get_record($db_link,'devices',"id=".$id);

if (isset($_POST["remove"])) {
    $fid = $_POST["f_id"];
    foreach ($fid as $key => $val) {
        if (isset($val) and $val != 1) {
                LOG_VERBOSE($db_link, "Remove connection id: $val ".dump_record($db_link,'connections','id='.$val));
                delete_record($db_link, "connections", "id=" . $val);
            }
        }
        header("Location: " . $_SERVER["REQUEST_URI"]);
        exit;
    }

unset($_POST);

$user_info = get_record_sql($db_link,"SELECT * FROM User_list WHERE id=".$device['user_id']);

require_once ($_SERVER['DOCUMENT_ROOT']."/inc/header.php");

print_device_submenu($page_url);
print_editdevice_submenu($page_url,$id,$device['device_type'],$user_info['login']);

?>
<div id="contsubmenu">
<form name="def" action="switchport-conn.php?id=<?php echo $id; ?>" method="post">
<br>

<?php print "<b>".WEB_device_port_connections."&nbsp".$device['device_name']." - ".$device['ip']."</b><br>\n"; ?>

<table class="data">
<tr>
<td width=20><input type="checkbox" onClick="checkAll(this.checked);"></td>
<td width=40><b><?php echo WEB_device_port_number; ?></b></td>
<td ><b><?php echo WEB_cell_login; ?></b></td>
<td width=100><input type="submit" onclick="return confirm('<?php echo WEB_msg_delete; ?>?')" name="remove" value="<?php echo WEB_btn_delete; ?>"></td>
</tr>

<?php

$connections = get_records($db_link,"connections","device_id=$id ORDER BY port_id ASC");
foreach ($connections as $key => $value) {
print "<tr align=center>\n";
print "<td class=\"data\" style='padding:0'><input type=checkbox name=f_id[] value='{$value['id']}'></td>\n";
$port = get_record($db_link,"device_ports","id=".$value['port_id']);
print "<td class=\"data\">". $port['port'] . "</a></td>\n";
print "<td class=\"data\">";
print_auth_detail($db_link, $value['auth_id']);
print "</td>\n";
print "<td class=\"data\"></td>\n";
print "</tr>";
}
print "</table>\n";
?>
</form>

<?php
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/footer.small.php");
?>
