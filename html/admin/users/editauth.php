<?php
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/auth.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/languages/" . $language . ".php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/idfilter.php");

global $default_user_id;
global $hotspot_user_id;
$msg_error = "";

$sSQL = "SELECT * FROM User_auth WHERE id=$id";
$old_auth_info = get_record_sql($db_link, $sSQL);

if (isset($_POST["editauth"]) and !$old_auth_info[deleted]) {
    $ip = trim($_POST["f_ip"]);
    if (checkValidIp($ip)) {
	$mac=mac_dotted($_POST["f_mac"]);
	$mac_exists=find_mac_in_subnet($db_link,$ip,$mac);
	if (isset($mac_exists) and $mac_exists['count']==1 and $mac_exists['1']!=$id) {
	        LOG_ERROR($db_link, "Mac $mac already exists in this subnet! Skip creating $ip [$mac] auth_id: ".$mac_exists['1']);
	        header("Location: " . $_SERVER["REQUEST_URI"]);
	        exit;
		}
	if (isset($mac_exists) and $mac_exists['count']>1) {
	        LOG_ERROR($db_link, "Mac $mac already exists in this subnet! Skip creating $ip [$mac] auth_id: ".$mac_exists['2']);
	        header("Location: " . $_SERVER["REQUEST_URI"]);
	        exit;
		}
        $range = cidrToRange($ip);
        $first_user_ip = $range[0];
        $last_user_ip = $range[1];
        $cidr = $range[2][1];
        if (isset($cidr) and $cidr < 32) {
            $ip = $first_user_ip . '/' . $cidr;
        } else {
            $ip = $first_user_ip;
        }
        $ip_aton = ip2long($first_user_ip);
        $ip_aton_end = ip2long($last_user_ip);
        list ($parent_id) = mysqli_fetch_array(mysqli_query($db_link, "Select user_id from User_auth where id=$id"));
        list ($lid) = mysqli_fetch_array(mysqli_query($db_link, "Select user_id from User_auth where ($ip_aton BETWEEN ip_int and ip_int_end) and id<>$id and deleted=0"));
        if (isset($lid) and ($lid != $parent_id)) {
            list ($lname) = mysqli_fetch_array(mysqli_query($db_link, "Select login from User_list where id=$lid"));
            $msg_error = "$ip $msg_exists Принадлежит пользователю $lname.";
            unset($_POST);
    	    } else {
            $new[ip] = $ip;
            $new[ip_int] = $ip_aton;
            $new[ip_int_end] = $ip_aton_end;
            $new[mac] = mac_dotted($_POST["f_mac"]);
            $new[clientid] = $_POST["f_clientid"];
            $new[comments] = $_POST["f_comments"];
            $f_dnsname=trim($_POST["f_dns_name"]);
            if (!empty($f_dnsname) and checkValidHostname($f_dnsname) and checkUniqHostname($db_link,$id,$f_dnsname)) { $new[dns_name] = $f_dnsname; }
            if (empty($f_dnsname)) { $new[dns_name] = ''; }
            $new[host_model] = $_POST["f_host_model"];
            $new[save_traf] = $_POST["f_save_traf"] * 1;
            $new[dhcp_acl] = trim($_POST["f_acl"]);
            if ($default_user_id == $parent_id or $hotspot_user_id == $parent_id) {
                $new[nagios_handler] = '';
                $new[enabled] = 0;
                $new[link_check] = 0;
                $new[nagios] = 0;
                $new[blocked] = 0;
                $new[day_quota] = 0;
                $new[month_quota] = 0;
                $new[queue_id] = 0;
                $new[filter_group_id] = 0;
            } else {
                $new[nagios_handler] = $_POST["f_handler"];
                $new[enabled] = $_POST["f_enabled"] * 1;
                $new[link_check] = $_POST["f_link"] * 1;
                $new[nagios] = $_POST["f_nagios"] * 1;
                $new[dhcp] = $_POST["f_dhcp"] * 1;
                $new[blocked] = $_POST["f_blocked"] * 1;
                $new[day_quota] = $_POST["f_day_q"] * 1;
                $new[month_quota] = $_POST["f_month_q"] * 1;
                $new[queue_id] = $_POST["f_queue_id"] * 1;
                $new[filter_group_id] = $_POST["f_group_id"] * 1;
            }
            $changes = get_diff_rec($db_link,"User_auth","id='$id'", $new, 1);
            if (!empty($changes)) { LOG_WARNING($db_link,"Изменен адрес доступа! Список изменений: $changes"); }
            update_record($db_link, "User_auth", "id='$id'", $new);
        }
    } else {
        $msg_error = "$msg_ip_error xxx.xxx.xxx.xxx/xx";
    }
    header("Location: " . $_SERVER["REQUEST_URI"]);
}

if (isset($_POST["moveauth"]) and !$old_auth_info[deleted]) {
    $new[user_id] = $_POST["new_parent"];
    $changes = get_diff_rec($db_link,"User_auth","id='$id'", $new, 1);
    if (!empty($changes)) { LOG_WARNING($db_link,"Адрес доступа перемещён к другому пользователю! Применено: $changes"); }
    update_record($db_link, "User_auth", "id='$id'", $new);
    header("Location: " . $_SERVER["REQUEST_URI"]);
}

if (isset($_POST["recovery"])) {
    $ip = trim($_POST["f_ip"]);
    if (checkValidIp($ip)) {
        $range = cidrToRange($ip);
        $first_user_ip = $range[0];
        $last_user_ip = $range[1];
        $cidr = $range[2][1];
        if (isset($cidr) and $cidr < 32) {
            $ip = $first_user_ip . '/' . $cidr;
        } else {
            $ip = $first_user_ip;
        }
        $ip_aton = ip2long($first_user_ip);
        $ip_aton_end = ip2long($last_user_ip);
        list ($parent_id) = mysqli_fetch_array(mysqli_query($db_link, "Select user_id from User_auth where id=$id"));
        list ($lid) = mysqli_fetch_array(mysqli_query($db_link, "Select user_id from User_auth where ($ip_aton BETWEEN ip_int and ip_int_end) and id<>$id and deleted=0"));
        if (isset($lid) and ($lid != $parent_id)) {
            list ($lname) = mysqli_fetch_array(mysqli_query($db_link, "Select login from User_list where id=$lid"));
            $msg_error = "$ip $msg_exists Принадлежит пользователю $lname.";
            unset($_POST);
        } else {
            $new[deleted] = 0;
            if ($default_user_id == $parent_id or $hotspot_user_id == $parent_id) {
                $new[nagios_handler] = '';
                $new[enabled] = 0;
                $new[link_check] = 0;
                $new[nagios] = 0;
                $new[blocked] = 0;
                $new[day_quota] = 0;
                $new[month_quota] = 0;
                $new[queue_id] = 0;
                $new[filter_group_id] = 0;
            } else {
                $new[nagios_handler] = $_POST["f_handler"];
                $new[enabled] = $_POST["f_enabled"] * 1;
                $new[link_check] = $_POST["f_link"] * 1;
                $new[nagios] = $_POST["f_nagios"] * 1;
                $new[dhcp] = $_POST["f_dhcp"] * 1;
                $new[blocked] = $_POST["f_blocked"] * 1;
                $new[day_quota] = $_POST["f_day_q"] * 1;
                $new[month_quota] = $_POST["f_month_q"] * 1;
                $new[queue_id] = $_POST["f_queue_id"] * 1;
                $new[filter_group_id] = $_POST["f_group_id"] * 1;
            }
            $changes = get_diff_rec($db_link,"User_auth","id='$id'", $new, 1);
            if (!empty($changes)) { LOG_WARNING($db_link,"Восстановлен адрес доступа! Применено: $changes"); }
            update_record($db_link, "User_auth", "id='$id'", $new);
        }
    } else {
        $msg_error = "$msg_ip_error xxx.xxx.xxx.xxx/xx";
    }
    header("Location: " . $_SERVER["REQUEST_URI"]);
}

unset($_POST);

require_once ($_SERVER['DOCUMENT_ROOT']."/inc/header.php");

$sSQL = "SELECT * FROM User_auth WHERE id=$id";
$auth_info = get_record_sql($db_link, $sSQL);

$parent_name = get_login($db_link, $auth_info[user_id]);
if ($auth_info[dhcp_time] == '0000-00-00 00:00:00') { $dhcp_str = ''; } else { $dhcp_str = $auth_info[dhcp_time] . " (" . $auth_info[dhcp_action] . ")"; }
if ($auth_info[last_found] == '0000-00-00 00:00:00') { $auth_info[last_found] = ''; }
?>
<div id="cont">
<?
print "<b> Адрес доступа пользователя <a href=/admin/users/edituser.php?id=$auth_info[user_id]>$parent_name</a> <b>";
?>
<form name="def" action="editauth.php?id=<? echo $id; ?>" method="post">
<input type="hidden" name="id" value=<? echo $id; ?>>
<table class="data">
<tr>
<td width=200><?php print $cell_dns_name." &nbsp | &nbsp "; print_url("Альясы","/admin/users/edit_alias.php?id=$id"); ?></td>
<td width=200><?php print $cell_comment; ?></td>
<td width=70><?php print $cell_enabled; ?></td>
<td width=70><?php print $cell_blocked; ?></td>
<td width=70><?php print $cell_perday; ?></td>
<td width=70><?php print $cell_permonth; ?></td>
<td width=70><?php print $cell_connection; ?></td>
</tr>
<tr>
<td><input type="text" name="f_dns_name" value="<? echo $auth_info[dns_name]; ?>" pattern="^(([a-zA-Z0-9]|[a-zA-Z0-9][a-zA-Z0-9\-]*[a-zA-Z0-9])\.)*([A-Za-z0-9]|[A-Za-z0-9][A-Za-z0-9\-]*[A-Za-z0-9])$"></td>
<td><input type="text" name="f_comments" value="<? echo $auth_info[comments]; ?>"></td>
<td><?php print_qa_select('f_enabled', $auth_info[enabled]); ?></td>
<td><?php print_qa_select('f_blocked', $auth_info[blocked]); ?></td>
<td><input type="text" name="f_day_q" value="<? echo $auth_info[day_quota]; ?>" size=5></td>
<td><input type="text" name="f_month_q"	value="<? echo $auth_info[month_quota]; ?>" size=5></td>
<td><?php print get_connection($db_link, $id); ?></td>
</tr>
<tr>
<td><?php print $cell_ip; ?></td>
<td><?php print $cell_mac; ?></td>
<td><?php print $cell_clientid; ?></td>
<td><?php print $cell_dhcp; ?></td>
<td><?php print $cell_filter; ?></td>
<td><?php print $cell_shaper; ?></td>
<td></td>
<tr>
<td><input type="text" name="f_ip" value="<? echo $auth_info[ip]; ?>" pattern="^(([0-9]|[1-9][0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5])\.){3}([0-9]|[1-9][0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5])$"></td>
<td><input type="text" name="f_mac" value="<? echo $auth_info[mac]; ?>"></td>
<td><input type="text" name="f_clientid" value="<? echo $auth_info[clientid]; ?>"></td>
<td><?php print_qa_select('f_dhcp', $auth_info[dhcp]); ?></td>
<td><?php print_group_select($db_link, 'f_group_id', $auth_info[filter_group_id]); ?> </td>
<td><?php print_queue_select($db_link, 'f_queue_id', $auth_info[queue_id]); ?> </td>
<td></td>
</tr>
<tr>
<td><?php print $cell_host_model; ?></td>
<td><?php print $cell_nagios_handler; ?></td>
<td><?php print $cell_acl; ?></td>
<td><?php print $cell_nagios; ?></td>
<td><?php print $cell_link; ?></td>
<td><?php print $cell_traf; ?></td>
<td></td>
<tr>
<td><input type="text" name="f_host_model" value="<? echo $auth_info[host_model]; ?>"></td>
<td><input type="text" name="f_handler"	value="<? echo $auth_info[nagios_handler]; ?>"></td>
<td><input type="text" name="f_acl" value="<? echo $auth_info[dhcp_acl]; ?>"></td>
<td><?php print_qa_select('f_nagios', $auth_info[nagios]); ?></td>
<td><?php print_qa_select('f_link', $auth_info[link_check]); ?></td>
<td><?php print_qa_select('f_save_traf', $auth_info[save_traf]); ?></td>
<td></td>
</tr>
<tr>
<td><?php print "Created: " . $auth_info[timestamp]; ?> </td>
<td colspan=2><?php print "Dhcp status: " . $dhcp_str; ?></td>
<td colspan=2><?php print "Dhcp hostname: " . $auth_info[dhcp_hostname]; ?></td>
<td colspan=2><?php print "Last found: " . $auth_info[last_found]; ?></td>
<td></td>
</tr>
<tr>
<td colspan=2><input type="submit" name="moveauth" value=<?php print $btn_move; ?>><?php print_login_select($db_link, 'new_parent', $auth_info[user_id]); ?></td>
<td><a href=/admin/logs/authlog.php?auth_id=<?php print $id; ?>>Лог</a></td>
<?php
if ($auth_info[deleted]) {
    print "<td colspan=2>Deleted: " . $auth_info[changed_time]."</td>";
    print "<td colspan=2 align=right><input type=\"submit\" name=\"recovery\" value=\"Восстановить\"></td>";
} else {
    print "<td colspan=2></td>";
    print "<td colspan=2 align=right><input type=\"submit\" name=\"editauth\" value=\"$btn_save\"></td>";
}
?>
</tr>
</table>
<?
if ($msg_error) {
    print "<div id='msg'><b>$msg_error</b></div><br>\n";
}
?>
</form>
<?
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/footer.php");
?>
