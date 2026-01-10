<?php
$default_displayed = 500;
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/auth.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/languages/" . HTML_LANG . ".php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/header.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/oufilter.php");
$default_sort='login';
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/sortfilter.php");

$msg_error = "";

if (getPOST("create") !== null) {
    $login = trim(getPOST("newlogin", null, ''));
    
    if ($login !== '') {
        // Проверка существования логина
        $lcount = get_count_records($db_link, "user_list", "LOWER(login) = LOWER(?)", [$login]);
        
        if ($lcount > 0) {
            $msg_error = WEB_cell_login . " " . $login . " " . $msg_exists . "!";
        } else {
            $new = ['login' => $login];
            // Определение OU
            if ($rou > 0) {
                $new['ou_id'] = $rou;
            } else {
                $rou = 3;
                $ou_exists = get_record_sql($db_link, "SELECT id FROM ou WHERE id = ?", [$rou]);
                if (empty($ou_exists)) {
                    $new['ou_id'] = $default_user_ou_id; // по умолчанию
                } else {
                    $new['ou_id'] = $rou;
                }
            }
            // Наследование настроек от OU
            $ou_info = get_record_sql($db_link, "SELECT * FROM ou WHERE id = ?", [$new['ou_id']]);
            if (!empty($ou_info)) {
                $new['enabled']           = isset($ou_info['enabled']) ? (int)$ou_info['enabled'] : 0;
                $new['queue_id']          = isset($ou_info['queue_id']) ? (int)$ou_info['queue_id'] : 0;
                $new['filter_group_id']   = isset($ou_info['filter_group_id']) ? (int)$ou_info['filter_group_id'] : 0;
            } else {
                // Если OU не найден — значения по умолчанию
                $new['enabled']           = 0;
                $new['queue_id']          = 0;
                $new['filter_group_id']   = 0;
            }
            $lid = insert_record($db_link, "user_list", $new);
            LOG_WARNING($db_link, "Создан новый пользователь: Login => $login");
            if (!empty($lid)) {
                header("Location: edituser.php?id=$lid");
                exit;
            }
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
        <tr><td><input type=checkbox class="putField" name="e_enabled" value='1'></td><td align=left><?php print WEB_cell_enabled."</td><td align=right>";print_qa_select('a_enabled', 0); ?></td></tr>
        <tr><td><input type=checkbox class="putField" name="e_group_id" value='1'></td><td align=left><?php print WEB_cell_filter."</td><td align=right>";print_filter_group_select($db_link, 'a_group_id', 0); ?></td></tr>
        <tr><td><input type=checkbox class="putField" name="e_queue_id" value='1'></td><td align=left><?php print WEB_cell_shaper."</td><td align=right>";print_queue_select($db_link, 'a_queue_id', 0); ?></td></tr>
        <tr><td><input type=checkbox class="putField" name="e_day_q" value='1'></td><td align=left><?php print WEB_cell_perday."</td><td align=right>"; ?><input type="text" name="a_day_q" value="0" size=5></td></tr>
        <tr><td><input type=checkbox class="putField" name="e_month_q" value='1'></td><td align=left><?php print WEB_cell_permonth."</td><td align=right>"; ?><input type="text" name="a_month_q" value="0" size=5></td></tr>
        <tr><td><input type=checkbox class="putField" name="e_new_ou" value='1'></td><td align=left><?php print WEB_cell_ou."</td><td align=right>";print_ou_select($db_link, 'a_new_ou', $rou); ?></td></tr>
        <tr><td><input type=checkbox class="putField" name="e_bind_mac" value='1'></td><td align=left><?php print WEB_user_bind_mac."</td><td align=right>";print_qa_select('a_bind_mac', 1);?></td></tr>
        <tr><td><input type=checkbox class="putField" name="e_bind_ip" value='1'></td><td align=left><?php print WEB_user_bind_ip."</td><td align=right>";print_qa_select('a_bind_ip', 1);?></td></tr>
        <tr><td><input type=checkbox class="putField" name="e_create_netdev" value='1'></td><td align=left><?php print WEB_user_create_netdev."</td><td align=right>";print_qa_select('a_create_netdev', 1);?></td></tr>
        <tr><td><input type=checkbox class="putField" name="e_permanent" value='1'></td><td align=left><?php print WEB_user_permanent."</td><td align=right>";print_qa_select('a_permanent', 0);?></td></tr>
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

$sort_url = "<a href=/admin/users/index.php?";

// === 1. Базовые условия ===
$params = [];
$conditions = ["U.deleted = 0", "U.ou_id = O.id"];

if ($rou != 0) {
    $conditions[] = "U.ou_id = ?";
    $params[] = (int)$rou;
}

$whereClause = implode(' AND ', $conditions);

// === 2. Безопасная сортировка (БЕЛЫЙ СПИСОК!) ===
$allowed_sort_fields = ['id', 'login', 'fio', 'ou_name', 'enabled', 'day_quota', 'month_quota', 'blocked', 'permanent'];
$allowed_order = ['ASC', 'DESC'];

$sort_field = in_array($sort_field, $allowed_sort_fields, true) ? $sort_field : 'id';
$order = in_array(strtoupper($order), $allowed_order, true) ? strtoupper($order) : 'ASC';

// === 3. Подсчёт записей ===
$countSQL = "SELECT COUNT(*) FROM user_list U JOIN ou O ON U.ou_id = O.id WHERE $whereClause";
$count_records = (int)get_single_field($db_link, $countSQL, $params);

// === 4. Пагинация ===
$total = ceil($count_records / $displayed);
$page = max(1, min($page, $total));
$start = ($page - 1) * $displayed;

print_navigation($page_url, $page, $displayed, $count_records, $total);

// === 5. Запрос данных ===
$limit = (int)$displayed;
$offset = (int)$start;

$dataParams = array_merge($params, [$limit, $offset]);

$sSQL = "
    SELECT 
        U.id, U.login, U.fio, O.ou_name, U.enabled, 
        U.day_quota, U.month_quota, U.blocked, U.permanent
    FROM user_list U
    JOIN ou O ON U.ou_id = O.id
    WHERE $whereClause
    ORDER BY U.$sort_field $order
    LIMIT ? OFFSET ?
";

$users = get_records_sql($db_link, $sSQL, $dataParams);

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


foreach ($users as $row) {
    $auth_customs = get_count_records($db_link,"user_auth","user_id=? AND deleted=0 AND enabled <>?", [ $row['id'],$row['enabled'] ] );
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
    $cl_id = $cl;
    if (!empty($row['permanent']) and $row['permanent'] == 1) { $cl_id = 'warn'; }
    print "<td class=\"$cl_id\">".$row['id']."</td>\n";
    if (empty($row['login'])) { $row['login']=$row['id']; }
    print "<td class=\"$cl\" align=left><a href=edituser.php?id=".$row['id'].">" . $row['login'] . "</a></td>\n";
    print "<td class=\"$cl\">".$row['fio']."</td>\n";
    $rules_count = get_count_records($db_link,"auth_rules","user_id=?", [$row['id']]);
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
print_navigation($page_url,$page,$displayed,$count_records,$total);
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
<td class="warn"><?php echo WEB_color_user_permanent; ?></td>
</table>

<script src="/js/remodal/remodal.min.js"></script>
<script src="/js/remodal-user.js"></script>

<script>
document.getElementById('ou').addEventListener('change', function(event) {
  const buttonApply = document.getElementById('btn_filter');
  buttonApply.click();
});

document.getElementById('rows').addEventListener('change', function(event) {
  const buttonApply = document.getElementById('btn_filter');
  buttonApply.click();
});

</script>

<?php
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/footer.simple.php");
?>
