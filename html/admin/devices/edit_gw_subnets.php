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
            LOG_INFO($db_link, "Remove subnet from gateway id: $val");
            delete_record($db_link, "gateway_subnets", "id=" . $val);
        }
    }
    header("Location: " . $_SERVER["REQUEST_URI"]);
    exit;
}

if (isset($_POST["s_create"])) {
    if (!empty($_POST["new_subnet"])) {
        $new['subnet_id'] = trim($_POST["new_subnet"]);
        $new['device_id'] = $id;
        LOG_INFO($db_link, "Add subnet id: ".$new['subnet_id']." for gateway id: ".$id);
        insert_record($db_link, "gateway_subnets", $new);
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
<?php print "<form name=def action='edit_gw_subnets.php?id=".$id."' method=post>"; ?>
<?php print WEB_list_gateway_subnets."<b>"; print_url($device['device_name'],"/admin/devices/editdevice.php?id=$id"); ?>
</b> <br>
<table class="data">
<tr align="center">
	<td></td>
	<td width=30><b>id</b></td>
	<td><b><?php echo WEB_network_subnet; ?></b></td>
	<td>
	<input type="submit" onclick="return confirm('<?php print WEB_msg_delete; ?>?')" name="s_remove" value="<?php print WEB_btn_remove; ?>">
	</td>
</tr>
<?php
$gateway_subnets = get_records_sql($db_link,'SELECT gateway_subnets.*,subnets.subnet,subnets.comment FROM gateway_subnets LEFT JOIN subnets ON gateway_subnets.subnet_id = subnets.id WHERE gateway_subnets.device_id='.$id.' ORDER BY subnets.subnet ASC');
foreach ( $gateway_subnets as $row ) {
    print "<tr align=center>\n";
    print "<td class=\"data\" style='padding:0'><input type=checkbox name=gs_id[] value='{$row['id']}'></td>\n";
    print "<td class=\"data\"><input type=\"hidden\" name='n_id[]' value='{$row['id']}'>{$row['id']}</td>\n";
    print "<td class=\"data\">".get_subnet_description($db_link,$row['subnet_id'])."</td>\n";
    print "</tr>\n";
    }
?>
<tr>
<td colspan=2><?php print WEB_btn_add; print_gateway_select($db_link,"new_subnet",$id); ?>
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
