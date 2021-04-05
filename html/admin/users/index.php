<?php
$default_displayed = 500;
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/auth.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/languages/" . $language . ".php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/header.php");
$default_ou=3;
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/oufilter.php");
$default_sort='login';
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/sortfilter.php");

$msg_error = "";

if (isset($_POST["create"])) {
    $login = $_POST["newlogin"];
    if ($login) {
        list ($lcount) = mysqli_fetch_array(mysqli_query($db_link, "Select count(id) from User_list where LCase(Login)=LCase('$login')"));
        if ($lcount > 0) {
            $msg_error = "$cell_login $login $msg_exists!";
            unset($_POST);
        } else {
            $new['login'] = $login;
            $new['ou_id'] = $rou;
            $lid=insert_record($db_link, "User_list", $new);
            LOG_WARNING($db_link,"Создан новый пользователь: Login => $login");
            header("location: edituser.php?id=$id");
        }
    }
    header("Location: " . $_SERVER["REQUEST_URI"]);
}

if (isset($_POST["ApplyForAll"])) {
    $auth_id = $_POST["fid"];
    $a_enabled = $_POST["a_enabled"] * 1;
    $a_day = $_POST["a_day_q"] * 1;
    $a_month = $_POST["a_month_q"] * 1;
    $a_queue = $_POST["a_queue_id"] * 1;
    $a_group = $_POST["a_group_id"] * 1;
    $msg="Массовое изменение пользователей!";
    foreach ($auth_id as $key => $val) {
        if ($val) {
            unset($auth);
            unset($user);
            $user['day_quota'] = $a_day;
            $user['month_quota'] = $a_month;
            $user['enabled'] = $a_enabled;
            $login = get_record($db_link,"User_list","id='$val'");
            $msg.="Всем адресам доступа пользователя id: ".$val." login: ".$login['login']." установлено: \r\n";
            $msg.= get_diff_rec($db_link,"User_list","id='$val'", $user, 1);
            update_record($db_link, "User_list", "id='" . $val . "'", $user);
            $auth['queue_id'] = $a_queue;
            $auth['filter_group_id'] = $a_group;
            $auth['enabled'] = $a_enabled;
            update_record($db_link, "User_auth", "user_id='" . $val . "'", $auth);
        }
    }
    LOG_WARNING($db_link,$msg);
    header("Location: " . $_SERVER["REQUEST_URI"]);
}

if (isset($_POST["remove"])) {
    $fid = $_POST["fid"];
    $default_user_id = get_option($db_link, 20);
    if (! isset($default_user_id)) {
        $default_user_id = 1;
    }
    foreach ($fid as $key => $val) {
        if ($val) {
            if ($val == 1) { continue; }
            $auth['user_id'] = $default_user_id;
            $changes = get_diff_rec($db_link,"User_list","id='$val'", '', 1);
            $login = get_record($db_link,"User_list","id='$val'");
            LOG_WARNING($db_link,"Удалён пользователь id: $val login: ".$login['login']."\r\n$changes\r\nАдреса доступа перемещены к пользователю по умолчанию.");
            update_record($db_link, "User_auth", "user_id=$val", $auth);
            delete_record($db_link, "User_list", "id=$val");
        }
    }
    header("Location: " . $_SERVER["REQUEST_URI"]);
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
<table class="data">
<tr>
<td><b><?php print $list_ou?> - </b><?php print_ou_select($db_link, 'ou', $rou); ?> Отображать:<?php print_row_at_pages('rows',$displayed); ?>
<input type="submit" value="Показать"></td>
</tr>
</table>
<table class="data">
<tr>
<td>Применить к списку</td>
<td>Включен&nbsp<?php print_qa_select('a_enabled', 0); ?></td>
<td>Фильтр&nbsp<?php print_group_select($db_link, 'a_group_id', 0); ?></td>
<td>Шейпер&nbsp<?php print_queue_select($db_link, 'a_queue_id', 0); ?></td>
<td>В день&nbsp<input type="text" name="a_day_q" value="0" size=5></td>
<td>В месяц&nbsp<input type="text" name="a_month_q" value="0" size=5></td>
<td>&nbsp<input type="submit" name="ApplyForAll" value="Apply"></td>
</tr>
<tr>
<td><input type="submit" name="create" value="Добавить пользователя"></td>
<td><input type=text name=newlogin value="Unknown"></td>
<td align="right" colspan=3><input type="submit" name="remove" value="Удалить пользователей"></td>
<td align="right" colspan=2></td>
</tr>
</table>

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
<td><b><?php print $sort_url . "sort=login&order=$new_order>" . $cell_login . "</a>"; ?></b></td>
<td><b><?php print $sort_url . "sort=fio&order=$new_order>" . $cell_fio . "</a>"; ?></b></td>
<td><b><?php print $cell_rule; ?></b></td>
<td><b><?php print $cell_ou; ?></b></td>
<td><b><?php print $cell_enabled; ?></b></td>
<td><b><?php print $cell_perday; ?></b></td>
<td><b><?php print $cell_permonth; ?></b></td>
<td><b><?php print $cell_report; ?></b></td>
</tr>
<?

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
    print "<td class=\"$cl\" ><input type=hidden name=\"id\" value=".$row['id'].">".$row['id']."</td>\n";
    if (empty($row['login'])) { $row['login']=$row['id']; }
    print "<td class=\"$cl\" align=left><a href=edituser.php?id=".$row['id'].">" . $row['login'] . "</a></td>\n";
    print "<td class=\"$cl\">".$row['fio']."</td>\n";
    $rules_count = get_count_records($db_link,"auth_rules","user_id=".$row['id']);
    print "<td class=\"$cl\">".$rules_count."</td>\n";
    print "<td class=\"$cl\">".$row['ou_name']."</td>\n";
    print "<td class=\"$cl\">".get_qa($row['enabled']) . "</td>\n";
    print "<td class=\"$cl\">".$row['day_quota']."</td>\n";
    print "<td class=\"$cl\">".$row['month_quota']."</td>\n";
    print "<td class=\"$cl\" align=left><a href=../reports/userday.php?id=".$row['id'].">Просмотр</a></td>\n";
}
?>
</table>

<?php
print_navigation($page_url,$page,$displayed,$count_records[0],$total);
?>

</form>
<table class="data">
<tr>
<td>Цветовая маркировка</td>
</tr>
<tr>
<td class="nb">Логин пуст</td>
<td class="warn">Пользователь выключен</td>
<td class="error">Блокировка по трафику</td>
</table>
<?
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/footer.php");
?>
