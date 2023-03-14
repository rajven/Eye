<?php
$default_displayed = 500;
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/auth.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/languages/" . HTML_LANG . ".php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/header.php");
$default_ou=get_const('default_user_ou_id');

require_once ($_SERVER['DOCUMENT_ROOT']."/inc/oufilter.php");
$default_sort='login';
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/sortfilter.php");

$msg_error = "";

if (isset($_POST["create"])) {
    $login = trim($_POST["newlogin"]);
    if (!empty($login)) {
        $lcount = get_count_records($db_link,"User_list","LCase(login)=LCase('$login')");
        if ($lcount > 0) {
            $msg_error = "WEB_cell_login $login $msg_exists!";
            unset($_POST);
        } else {
            $new['login'] = $login;
            $new['ou_id'] = $rou;
            $ou_info = get_record_sql($db,"SELECT * FROM OU WHERE id=".$rou);
	    if (!empty($ou_info)) {
	        $new['enabled'] = $ou_info['enabled'];
	        $new['queue_id'] = $ou_info['queue_id'];
	        $new['filter_group_id'] = $ou_info['filter_group_id'];
	        }
            $lid=insert_record($db_link, "User_list", $new);
            LOG_WARNING($db_link,"Создан новый пользователь: Login => $login");
            header("Location: edituser.php?id=$lid");
            exit;
        }
    }
    header("Location: " . $_SERVER["REQUEST_URI"]);
    exit;
}

if (isset($_POST["ApplyForAll"])) {
    $auth_id = $_POST["fid"];
    $a_enabled = $_POST["a_enabled"] * 1;
    $a_day = $_POST["a_day_q"] * 1;
    $a_month = $_POST["a_month_q"] * 1;
    $a_queue = $_POST["a_queue_id"] * 1;
    $a_group = $_POST["a_group_id"] * 1;
    $a_ou_id = $_POST["a_new_ou"] * 1;
    $msg="Массовое изменение пользователей!";
    foreach ($auth_id as $key => $val) {
        if ($val) {
            unset($auth);
            unset($user);
            $user['day_quota'] = $a_day;
            $user['month_quota'] = $a_month;
            $user['enabled'] = $a_enabled;
            $user['ou_id'] = $a_ou_id;
            $login = get_record($db_link,"User_list","id='$val'");
            $msg.="Всем адресам доступа пользователя id: ".$val." login: ".$login['login']." установлено: \r\n";
            $msg.= get_diff_rec($db_link,"User_list","id='$val'", $user, 1);
            update_record($db_link, "User_list", "id='" . $val . "'", $user);
            run_sql($db_link, "UPDATE User_auth SET ou_id=$a_ou_id, queue_id=$a_queue, filter_group_id=$a_group, enabled=$a_enabled, changed=1 WHERE user_id=".$val);
        }
    }
    LOG_WARNING($db_link,$msg);
    header("Location: " . $_SERVER["REQUEST_URI"]);
    exit;
}

if (isset($_POST["remove"])) {
    $fid = $_POST["fid"];
    foreach ($fid as $key => $val) {
        if ($val) {
            $login = get_record($db_link,"User_list","id='$val'");
            LOG_INFO($db_link, "Delete device for user id: $val");
            $device= get_record($db_link,"devices","user_id='$val'");
	        if (!empty($device)) {
                unbind_ports($db_link, $device['id']);
	            run_sql($db_link, "DELETE FROM connections WHERE device_id=".$device['id']);
    	        run_sql($db_link, "DELETE FROM device_l3_interfaces WHERE device_id=".$device['id']);
    		    run_sql($db_link, "DELETE FROM device_ports WHERE device_id=".$device['id']);
                delete_record($db_link, "devices", "id=".$device['id']);
		        }
            run_sql($db_link,"DELETE FROM auth_rules WHERE user_id=$val");
            run_sql($db_link,"UPDATE User_auth SET deleted=1 WHERE user_id=$val");
            delete_record($db_link, "User_list", "id=$val");
            LOG_WARNING($db_link,"Удалён пользователь id: $val login: ".$login['login']."\r\n");
    	    }
	}
    header("Location: " . $_SERVER["REQUEST_URI"]);
    exit;
}

unset($_POST);


?>
<div id="cont">

<?php
if ($msg_error) {
    print "<div id='msg'><b>$msg_error</b></div><br>\n";
}


?>
<form name="def" action="index.php" method="post">
<div><b><?php print WEB_cell_ou; ?> - </b>
<?php print_ou_select($db_link, 'ou', $rou); 
print WEB_rows_at_page."&nbsp"; print_row_at_pages('rows',$displayed); ?>
<input type="submit" value="<?php echo WEB_btn_show; ?>">
</div>
<table class="data">
<tr>
<td><?php echo WEB_user_list_apply; ?></td>
<td><?php print WEB_cell_enabled."&nbsp";print_qa_select('a_enabled', 0); ?></td>
<td><?php print WEB_cell_filter."&nbsp";print_group_select($db_link, 'a_group_id', 0); ?></td>
<td><?php print WEB_cell_shaper."&nbsp";print_queue_select($db_link, 'a_queue_id', 0); ?></td>
<td><?php print WEB_cell_perday."&nbsp"; ?><input type="text" name="a_day_q" value="0" size=5></td>
<td><?php print WEB_cell_permonth."&nbsp"; ?><input type="text" name="a_month_q" value="0" size=5></td>
<td><?php print WEB_cell_ou."&nbsp";print_ou_select($db_link, 'a_new_ou', $rou); ?></td>
<td>&nbsp<input type="submit" onclick="return confirm('<?php echo WEB_msg_apply_selected; ?>?')" name="ApplyForAll" value="<?php echo WEB_btn_apply; ?>"></td>
</tr>
</table>

<div>
<?php echo WEB_new_user."&nbsp"; ?>
<input type=text name=newlogin value="Unknown">
<input type="submit" name="create" value="<?php echo WEB_btn_add; ?>">
</div>

<?php

$sort_table = 'U';
$sort_url = "<a href=/admin/users/index.php?";

if ($rou == 0) { $filter = "U.ou_id=O.id and U.deleted=0"; } else { $filter = "U.OU_id=O.id and U.deleted=0 and U.ou_id=$rou"; }

$countSQL = "SELECT Count(*) FROM User_list U, OU O WHERE $filter";

$res = mysqli_query($db_link, $countSQL);
$count_records = mysqli_fetch_array($res);
$total=ceil($count_records[0]/$displayed);
if ($page>$total) { $page=$total; }
if ($page<1) { $page=1; }
$start = ($page * $displayed) - $displayed;
print_navigation($page_url,$page,$displayed,$count_records[0],$total);

$sSQL = "SELECT U.id, U.login, U.fio, O.ou_name, U.enabled, U.day_quota, U.month_quota, U.blocked FROM User_list U, OU O WHERE $filter ORDER BY $sort_table.$sort_field $order LIMIT $start,$displayed";

?>

<table class="data">
<tr align="center">
<td><input type="checkbox" onClick="checkAll(this.checked);"></td>
<td><b><?php print $sort_url . "sort=id&order=$new_order>id</a>"; ?></b></td>
<td><b><?php print $sort_url . "sort=login&order=$new_order>" . WEB_cell_login . "</a>"; ?></b></td>
<td><b><?php print $sort_url . "sort=fio&order=$new_order>" . WEB_cell_fio . "</a>"; ?></b></td>
<td><b><?php print WEB_cell_rule; ?></b></td>
<td><b><?php print WEB_cell_ou; ?></b></td>
<td><b><?php print WEB_cell_enabled; ?></b></td>
<td><b><?php print WEB_cell_perday; ?></b></td>
<td><b><?php print WEB_cell_permonth; ?></b></td>
<td><b><?php print WEB_cell_report; ?></b></td>
<td><input type="submit" onclick="return confirm('<?php echo WEB_msg_delete; ?>?')" name="remove" value="<?php echo WEB_btn_delete; ?>"></td>
</tr>
<?php

$users = get_records_sql($db_link, $sSQL);

foreach ($users as $row) {
    $cl = "data";
    if (! $row['enabled']) {
        $cl = "warn";
    }
    if ($row['blocked']) {
        $cl = "error";
    }
    if (! get_auth_count($db_link, $row['id'])) {
        $cl = 'nb';
    }
    print "<tr align=center>\n";
    print "<td class=\"$cl\" style='padding:0'><input type=checkbox name=fid[] value=".$row['id']."></td>\n";
    print "<td class=\"$cl\">".$row['id']."</td>\n";
    if (empty($row['login'])) { $row['login']=$row['id']; }
    print "<td class=\"$cl\" align=left><a href=edituser.php?id=".$row['id'].">" . $row['login'] . "</a></td>\n";
    print "<td class=\"$cl\">".$row['fio']."</td>\n";
    $rules_count = get_count_records($db_link,"auth_rules","user_id=".$row['id']);
    print "<td class=\"$cl\">".$rules_count."</td>\n";
    print "<td class=\"$cl\">".$row['ou_name']."</td>\n";
    print "<td class=\"$cl\">".get_qa($row['enabled']) . "</td>\n";
    print "<td class=\"$cl\">".$row['day_quota']."</td>\n";
    print "<td class=\"$cl\">".$row['month_quota']."</td>\n";
    print "<td class=\"$cl\" align=center colspan=2><a href=../reports/userday.php?id=".$row['id'].">Просмотр</a></td>\n";
}
?>
</table>

<?php
print_navigation($page_url,$page,$displayed,$count_records[0],$total);
?>

</form>
<table class="data">
<tr>
<td colspan = 3><?php echo WEB_color_description; ?></td>
</tr>
<tr>
<td class="nb"><?php echo WEB_color_user_empty; ?></td>
<td class="warn"><?php echo WEB_color_user_disabled; ?></td>
<td class="error"><?php echo WEB_color_user_blocked; ?></td>
</table>
<?php
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/footer.php");
?>
