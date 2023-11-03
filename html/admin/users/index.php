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

?>
<div id="cont">

<?php
if ($msg_error) {
    print "<div id='msg'><b>$msg_error</b></div><br>\n";
    }
?>

<form id="filter" name="filter" action="index.php" method="post">
<div>
<b><?php print WEB_cell_ou; ?> - </b>
<?php print_ou_select($db_link, 'ou', $rou); print WEB_rows_at_page."&nbsp"; print_row_at_pages('rows',$displayed); ?>
<input id="btn_filter" name="btn_filter" type="submit" value="<?php echo WEB_btn_show; ?>">
</div>
<br>
<div>
<?php echo WEB_new_user."&nbsp"; ?>
<input type=text name=newlogin value="Unknown">
<input type="submit" name="create" value="<?php echo WEB_btn_add; ?>">
</div>
</form>
<br>
<a class="mainButton" href="#modal"><?php print WEB_btn_apply_selected; ?></a>
<div class="remodal" data-remodal-options="closeOnConfirm: true" data-remodal-id="modal" role="dialog" aria-labelledby="modal1Title" aria-describedby="modal1Desc">
 <div class="remodalBorder">
  <button data-remodal-action="close" class="remodal-close" aria-label="Close"></button>
      <form id="formUserApply">
        <h2 id="modal1Title"><?php print WEB_selection_title; ?></h2>
        <input type="hidden" name="ApplyForAll" value="MassChange">
        <table class="data" align=center>
        <tr><td><input type=checkbox class="putField" name="e_enabled" value='1'></td><td><?php print WEB_cell_enabled."&nbsp";print_qa_select('a_enabled', 0); ?></td></tr>
        <tr><td><input type=checkbox class="putField" name="e_group_id" value='1'></td><td><?php print WEB_cell_filter."&nbsp";print_group_select($db_link, 'a_group_id', 0); ?></td></tr>
        <tr><td><input type=checkbox class="putField" name="e_queue_id" value='1'></td><td><?php print WEB_cell_shaper."&nbsp";print_queue_select($db_link, 'a_queue_id', 0); ?></td></tr>
        <tr><td><input type=checkbox class="putField" name="e_day_q" value='1'></td><td><?php print WEB_cell_perday."&nbsp"; ?><input type="text" name="a_day_q" value="0" size=5></td></tr>
        <tr><td><input type=checkbox class="putField" name="e_month_q" value='1'></td><td><?php print WEB_cell_permonth."&nbsp"; ?><input type="text" name="a_month_q" value="0" size=5></td></tr>
        <tr><td><input type=checkbox class="putField" name="e_new_ou" value='1'></td><td><?php print WEB_cell_ou."&nbsp";print_ou_select($db_link, 'a_new_ou', $rou); ?></td></tr>
        <tr><td><input type=checkbox class="putField" name="e_bind_mac" value='1'></td><td><?php print WEB_user_bind_mac."&nbsp";print_qa_select('a_bind_mac', 1);?></td></tr>
        <tr><td><input type=checkbox class="putField" name="e_bind_ip" value='1'></td><td><?php print WEB_user_bind_ip."&nbsp";print_qa_select('a_bind_ip', 1);?></td></tr>
        <tr><td><input type=checkbox class="putField" name="e_create_netdev" value='1'></td><td><?php print WEB_user_create_netdev."&nbsp";print_qa_select('a_create_netdev', 1);?></td></tr>
        </table>
        <input type="submit" name="submit" class="btn" value="<?php echo WEB_btn_apply; ?>">
    </form>
</div>
</div>

<a class="delButton" href="#modalDel"><?php print WEB_btn_delete; ?></a>
<div class="remodal" data-remodal-options="closeOnConfirm: true" data-remodal-id="modalDel" role="dialog" aria-labelledby="modal1Title" aria-describedby="modal1Desc">
 <div class="remodalBorder">
  <button data-remodal-action="close" class="remodal-close" aria-label="Close"></button>
    <form id="formUserDel">
        <h2 id="modal1Title"><?php print WEB_msg_delete_selected; ?></h2>
        <input type="hidden" name="RemoveUser" value="MassChange">
        <?php print_qa_select('f_deleted', 0);?><br><br>
        <input type="submit" name="submit" class="btn" value="<?php echo WEB_btn_apply; ?>">
    </form>
</div>
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

<form id="def" name="def" >

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
</tr>
<?php

$users = get_records_sql($db_link, $sSQL);

foreach ($users as $row) {
    $auth_customs = get_count_records($db_link,"User_auth","user_id=".$row['id']." AND deleted=0 AND enabled <>'".$row['enabled']."'");
    $cl = "data";
    if (! $row['enabled']) {
        $cl = "off";
    }
    if ($row['blocked']) {
        $cl = "error";
    }
    if ($auth_customs > 0) {
	$cl = "custom";
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
<td class="off"><?php echo WEB_color_user_disabled; ?></td>
<td class="error"><?php echo WEB_color_user_blocked; ?></td>
<td class="custom"><?php echo WEB_color_user_custom; ?></td>
</table>

<script src="/js/remodal/remodal.min.js"></script>
<script src="/js/remodal-user.js"></script>

<?php
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/footer.php");
?>
