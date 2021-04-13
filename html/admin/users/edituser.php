<?php
require_once ($_SERVER["DOCUMENT_ROOT"]."/inc/auth.php");
require_once ($_SERVER["DOCUMENT_ROOT"]."/inc/languages/" . $language . ".php");
require_once ($_SERVER["DOCUMENT_ROOT"]."/inc/idfilter.php");

global $default_user_id;
global $hotspot_user_id;

$default_sort='ip_int';
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/sortfilter.php");

$msg_error = "";

if (isset($_POST["edituser"])) {
    unset($new);
    unset($auth);
    $new["ou_id"] = $_POST["f_ou"] * 1;
    $new["filter_group_id"] = $_POST["f_filter"]*1;
    $new["queue_id"] = $_POST["f_queue"]*1;
    if ($default_user_id == $id or $hotspot_user_id == $id) {
        $new["enabled"] = 0;
        $new["blocked"] = 0;
        $new["day_quota"] = 0;
        $new["month_quota"] = 0;
        $auth["enabled"] = 0;
        $auth["blocked"] = 0;
    } else {
        $new["login"] = trim($_POST["f_login"]);
	$new["fio"] = trim($_POST["f_fio"]);
        $new["enabled"] = $_POST["f_enabled"] * 1;
        $new["blocked"] = $_POST["f_blocked"] * 1;
        $new["day_quota"] = trim($_POST["f_perday"]) * 1;
        $new["month_quota"] = trim($_POST["f_permonth"]) * 1;
        $auth["enabled"] = $new["enabled"];
        $auth["blocked"] = $new["blocked"];
    }
    $changes = get_diff_rec($db_link,"User_list","id='$id'", $new, 0);
    if (!empty($changes)) { LOG_WARNING($db_link,"Изменён пользователь id: $id. \r\nПрименено: $changes"); }
    update_record($db_link, "User_list", "id='$id'", $new);
    update_record($db_link, "User_auth", "user_id='" . $id . "'", $auth);
    header("Location: " . $_SERVER["REQUEST_URI"]);
}

if (isset($_POST["addauth"])) {
    $fip = substr(trim($_POST["newip"]), 0, 18);
    if (isset($_POST["newmac"])) { $fmac = mac_dotted(substr(trim($_POST["newmac"]), 0, 17)); }
    if ($fip) {
        if (checkValidIp($fip)) {
    		$fid = new_auth($db_link, $fip, $fmac, $id);
                LOG_WARNING($db_link,"Создан новый адрес доступа: ip => $fip, mac => $fmac");
                if (isset($fid)) { header("location: /admin/users/editauth.php?id=$fid"); }
	        header("Location: " . $_SERVER["REQUEST_URI"]);
    	    } else {
                $msg_error = "$msg_ip_error xxx.xxx.xxx.xxx/xx";
    	    }
	}
    header("Location: " . $_SERVER["REQUEST_URI"]);
}

if (isset($_POST["removeauth"])) {
    $auth_id = $_POST["f_auth_id"];
    foreach ($auth_id as $key => $val) {
        if ($val) {
            delete_record($db_link, 'connections', "auth_id=" . $val);
            delete_record($db_link, 'User_auth_alias', "auth_id=" . $val);
            $auth["deleted"] = 1;
            $changes = get_diff_rec($db_link,"User_auth","id='$val'", '', 0);
            if (!empty($changes)) { LOG_WARNING($db_link,"Удалён адрес доступа: \r\n $changes"); }
            update_record($db_link, "User_auth", "id=" . $val, $auth);
            delete_record($db_link, "connections", "auth_id=" . $val);
        }
    }
    header("Location: " . $_SERVER["REQUEST_URI"]);
}

if (isset($_POST["ApplyForAll"])) {
    $auth_id = $_POST["f_auth_id"];
    $a_enabled = $_POST["a_enabled"] * 1;
    $a_dhcp = $_POST["a_dhcp"] * 1;
    $a_day = $_POST["a_day_q"] * 1;
    $a_month = $_POST["a_month_q"] * 1;
    $a_queue = $_POST["a_queue_id"] * 1;
    $a_group = $_POST["a_group_id"] * 1;
    foreach ($auth_id as $key => $val) {
        if ($val) {
            unset($new);
            if ($default_user_id == $id or $hotspot_user_id == $id) {
                $new["dhcp"] = 0;
                $new["enabled"] = 0;
                $new["day_quota"] = 0;
                $new["month_quota"] = 0;
                $new["queue_id"] = 0;
                $new["filter_group_id"] = 0;
            } else {
                $new["dhcp"] = $a_dhcp;
                $new["enabled"] = $a_enabled;
                $new["day_quota"] = $a_day;
                $new["month_quota"] = $a_month;
                $new["queue_id"] = $a_queue;
                $new["filter_group_id"] = $a_group;
            }
            $changes = get_diff_rec($db_link,"User_auth","id='$val'", $new, 0);
            if (!empty($changes)) { LOG_WARNING($db_link,"Изменён адрес доступа id: $val. Применено: $changes"); }
            update_record($db_link, "User_auth", "id='" . $val . "'", $new);
        }
    }
    header("Location: " . $_SERVER["REQUEST_URI"]);
}

if (isset($_POST["moveauth"]) and isset($_POST["new_parent"])) {
    $new_user_id = $_POST["new_parent"]*1;
    $auth_id = $_POST["f_auth_id"];
    if ($new_user_id <> $id) {
	$user_rec = get_record($db_link, 'User_list', "id=".$new_user_id);
    foreach ($auth_id as $key => $val) {
	    if ($val) {
        	$new["filter_group_id"]=$user_rec["filter_group_id"];
	        $new["queue_id"] = $user_rec["queue_id"];
                $new["enabled"] = $user_rec["enabled"];
                $new["user_id"] = $new_user_id;
                $changes = get_diff_rec($db_link,"User_auth","id='$val'", $new, 0);
                if (!empty($changes)) { LOG_WARNING($db_link,"Адрес доступа перемещён к другому пользователю id: $val (".$user_rec["login"]."). Применено: $changes"); }
	        update_record($db_link, "User_auth", "id='" . $val . "'", $new);
    		}
	    }
	}
    header("Location: " . $_SERVER["REQUEST_URI"]);
}

if (isset($_POST["new_user"])) {
    $auth_id = $_POST["f_auth_id"];
    $save_traf = get_option($db_link, 23) * 1;
    foreach ($auth_id as $key => $val) {
        if ($val) {
            $flist = mysqli_query($db_link, "SELECT ip, comments, dns_name, dhcp_hostname from User_auth WHERE id=$val");
            list ($f_auth_ip, $f_auth_comments, $f_dns_name, $f_dhcp_name) = mysqli_fetch_array($flist);
            $ou_id = $_POST["f_new_ou"] * 1;
            if (!isset($ou_id)) { $ou_id = 0; }
            $login = $f_auth_ip;
            if (isset($f_auth_comments) and strlen($f_auth_comments) > 0) { $login = $f_auth_comments; }
            if (isset($f_dhcp_name) and strlen($f_dhcp_name) > 0) { $login = $f_dhcp_name;  }
            if (isset($f_dns_name) and strlen($f_dns_name) > 0) { $login = $f_dns_name; }
            list ($l_id) = mysqli_fetch_array(mysqli_query($db_link, "Select id from User_list where LCase(login)=LCase('$login') and deleted=0"));
            if (isset($l_id) and $l_id > 0) {
                // move auth
                $auth["user_id"] = $l_id;
                $auth["save_traf"] = $save_traf;
                update_record($db_link, "User_auth", "id='" . $val . "'", $auth);
                apply_auth_rule($db_link,$val,$l_id);
                $changes = get_diff_rec($db_link,"User_auth","id='$val'", $new, 0);
                if (!empty($changes)) { LOG_WARNING($db_link,"Изменён адрес доступа id: $val. Применено: $changes"); }
            } else {
                $new["login"] = $login;
                $new["ou_id"] = $ou_id;
                $l_id=insert_record($db_link, "User_list", $new);
                $auth["user_id"] = $l_id;
                $auth["save_traf"] = $save_traf;
                update_record($db_link, "User_auth", "id='" . $val . "'", $auth);
                $changes = get_diff_rec($db_link,"User_auth","id='$val'", '', 0);
                LOG_WARNING($db_link,"Создан новый пользователь из адреса доступа: login => $login. Адрес доступа перемещён к созданному пользователю: $changes");
            }
        }
    }
    header("Location: " . $_SERVER["REQUEST_URI"]);
}

unset($_POST);

$sSQL = "SELECT * FROM User_list WHERE id=$id";
$user_info = get_record_sql($db_link, $sSQL);

require_once ($_SERVER["DOCUMENT_ROOT"]."/inc/header.php");
?>
<div id="cont">
<?php
if ($msg_error) {
    print "<div id='msg'><b>$msg_error</b></div><br>\n";
}
?>
<form name="def" action="edituser.php?id=<?php echo $id; ?>" method="post">
<input type="hidden" name="id" value=<? echo $id; ?>>
<table class="data">
<tr>
<td colspan=2><?php print $cell_login; ?></td>
<td colspan=2><?php print $cell_fio; ?></td>
<td colspan=2><?php print $cell_ou; ?></td>
</tr>
<tr>
<td colspan=2><input type="text" name="f_login" value="<?php print $user_info["login"]; ?>" size=25></td>
<td colspan=2><input type="text" name="f_fio" value="<?php print $user_info["fio"]; ?>" size=25></td>
<td colspan=2><?php print_ou_set($db_link, 'f_ou', $user_info["ou_id"]); ?></td>
</tr>
<tr>
<td>Фильтр</td>
<td>Шейпер</td>
<td><?php print $cell_enabled; ?></td>
<td><?php print $cell_perday; ?></td>
<td><?php print $cell_permonth; ?></td>
<td><?php print $cell_blocked; ?></td>
</tr>
<tr>
<td><?php print_group_select($db_link, 'f_filter', $user_info["filter_group_id"]); ?></td>
<td><?php print_queue_select($db_link, 'f_queue', $user_info["queue_id"]); ?></td>
<td><?php print_qa_select('f_enabled', $user_info["enabled"]); ?></td>
<td><input type="text" name="f_perday" value="<? echo $user_info["day_quota"]; ?>" size=5></td>
<td><input type="text" name="f_permonth" value="<? echo $user_info["month_quota"]; ?>" size=5></td>
<td><?php print_qa_select('f_blocked', $user_info["blocked"]); ?></td>
</tr>
<tr>
<?php
print "<td>"; print_url("Список правил","/admin/users/edit_rules.php?id=$id"); print "</td>";
$rule_count = get_count_records($db_link,"auth_rules","user_id=".$id);
if ($rule_count>0) { print "<td colspan=3> Count: ".$rule_count."</td>"; } else { print "<td colspan=3></td>"; }
?>
<td colspan=2>Created: <?php print $user_info["timestamp"]; ?></td><td></td>
</tr>
<tr>
<?php
print "<td colspan=2>"; print_url("Трафик за день","/admin/reports/userday.php?id=$id"); print "</td>";
?>
<td colspan=3></td>
<td><input type="submit" name="edituser" value=<?php print $btn_save; ?>></td>
</tr>
</table>
<br>
<?php
if ($msg_error) { print "<div id='msg'><b>$msg_error</b></div><br>\n"; }
?>
<table class="data">
<tr>
<td>Для выделенных установить: Включен&nbsp<?php print_qa_select('a_enabled', 0); ?></td>
<td>DHCP&nbsp<?php print_qa_select('a_dhcp', 0); ?></td>
<td>Фильтр&nbsp<?php print_group_select($db_link, 'a_group_id', 0); ?></td>
<td>Шейпер&nbsp<?php print_queue_select($db_link, 'a_queue_id', 0); ?></td>
<td>В день&nbsp<input type="text" name="a_day_q" value="0" size=5></td>
<td>В месяц&nbsp<input type="text" name="a_month_q" value="0" size=5></td>
<td>&nbsp<input type="submit" name="ApplyForAll" value="Apply"></td>
</tr>
<tr>
<?php
print "<td colspan=6>Переместить выделенных к пользователю "; print_login_select($db_link, 'new_parent', $id); print "<input type=\"submit\" name=\"moveauth\" value=".$btn_move.">"; print "</td>";
print "</tr><tr>";
print "<td colspan=4>Создать пользователей по выделению в группе ";  print_ou_select($db_link, 'f_new_ou', $user_info["ou_id"]); print "<button name='new_user'>Создать</button>\n"; print "</td>";
print "<td colspan=2 align=\"right\">Удалить выделенных <input type=\"submit\" name=\"removeauth\" value=".$btn_remove.">";
?>
</tr>
</table>

<?php
$sort_table = 'User_auth';
$sort_url = "<a href=edituser.php?id=" . $id;
if ($id == $default_user_id or $id == $hotspot_user_id) { $default_sort = 'last_found DESC'; }
?>

<br> <b>Список адресов доступа</b><br>
<table class="data">
<tr>
<td class="data">Новый адрес доступа IP:&nbsp<input type=text name=newip value=""></td>
<td class="data">Mac (необязательно):&nbsp<input type=text name=newmac value=""></td>
<td class="data"><input type="submit" name="addauth" value="Добавить"></td>
</tr>
</table>

<table class="data">
<tr>
<td class="data"><input type="checkbox" onClick="checkAll(this.checked);"></td>
<td class="data"><?php print $sort_url . "&sort=ip_int&order=$new_order>" . $cell_ip . "</a>"; ?></td>
<td class="data"><?php print $sort_url . "&sort=mac&order=$new_order>" . $cell_mac . "</a>"; ?></td>
<td class="data"><?php print $cell_comment; ?></td>
<td class="data"><?php print $sort_url . "&sort=dns_name&order=$new_order>" . $cell_dns_name . "</a>"; ?></td>
<td class="data"><?php print $cell_enabled; ?></td>
<td class="data"><?php print $cell_dhcp; ?></td>
<td class="data"><?php print $cell_filter; ?></td>
<td class="data"><?php print $cell_shaper; ?></td>
<td class="data"><?php print $cell_perday."/<br>".$cell_permonth.", Mb"; ?></td>
<td class="data"><?php print $cell_connection; ?></td>
<td class="data"><?php print $sort_url . "&sort=timestamp&order=$new_order>Created</a>"; ?></td>
<td class="data">Last DHCP/ARP Event</td>
<td class="data"><?php print $sort_url . "&sort=last_found&order=$new_order>Last found</a>"; ?></td>
</tr>

<?php

$flist=get_records($db_link,'User_auth',"user_id=".$id." and deleted=0 ORDER BY $sort_table.$sort_field $order");
if (!empty($flist)) {
    foreach ( $flist as $row ) {
        if ($row["dhcp_time"] == '0000-00-00 00:00:00') {
            $dhcp_str = '';
            } else {
            $dhcp_str = FormatDateStr('Y.m.d H:m',$row["dhcp_time"]) . " (" . $row["dhcp_action"] . ")";
            }
        if ($row["last_found"] == '0000-00-00 00:00:00') { $row["last_found"] = ''; }
        print "<tr align=center>\n";
        print "<td class=\"data\" style='padding:0'><input type=checkbox name=f_auth_id[] value=".$row["id"]." ></td>\n";
        print "<td class=\"data\" align=left><a href=editauth.php?id=".$row["id"].">" . $row["ip"] . "</a></td>\n";
        print "<td class=\"data\" >" . expand_mac($db_link,$row["mac"]) . "</td>\n";
        if (isset($row["dhcp_hostname"]) and strlen($row["dhcp_hostname"]) > 0) {
            print "<td class=\"data\" >".$row["comments"]." [" . $row["dhcp_hostname"] . "]</td>\n";
            } else {
            print "<td class=\"data\" >".$row["comments"]."</td>\n";
            }
        print "<td class=\"data\" >".$row["dns_name"]."</td>\n";
        $ip_status = 1;
        if ($row["blocked"] or !$row["enabled"]) { $ip_status = 0; }
        print "<td class=\"data\" >" . get_qa($ip_status). "</td>\n";
        print "<td class=\"data\" >" . get_qa($row["dhcp"]). "</td>\n";
        print "<td class=\"data\" >" . get_group($db_link, $row["filter_group_id"]) . "</td>\n";
        print "<td class=\"data\" >" . get_queue($db_link, $row["queue_id"]) . "</td>\n";
        print "<td class=\"data\" >".$row["day_quota"]."/".$row["month_quota"]."</td>\n";
        print "<td class=\"data\" >" . get_connection($db_link, $row["id"]) . "</td>\n";
        print "<td class=\"data\" >" . FormatDateStr('Y.m.d',$row["timestamp"]) . "</td>\n";
        print "<td class=\"data\" >" . $dhcp_str . "</td>\n";
        print "<td class=\"data\" >" . FormatDateStr('Y.m.d H:i',$row["last_found"]) . "</td>\n";
        print "</tr>";
        }
    }
?>
</table>
</form>
<?
require_once ($_SERVER["DOCUMENT_ROOT"]."/inc/footer.php");
?>
