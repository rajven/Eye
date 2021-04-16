<?php
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/auth.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/languages/" . $language . ".php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/idfilter.php");

global $default_user_id;
global $hotspot_user_id;
$msg_error = "";

$sSQL = "SELECT * FROM User_auth WHERE id=$id";
$old_auth_info = get_record_sql($db_link, $sSQL);

if (isset($_POST["editauth"]) and !$old_auth_info['deleted']) {
    $ip = trim($_POST["f_ip"]);
    if (checkValidIp($ip)) {
        $ip_aton = ip2long($ip);
	$mac=mac_dotted($_POST["f_mac"]);
        $parent_id = $old_auth_info['user_id'];
        //search mac
	$mac_exists=find_mac_in_subnet($db_link,$ip,$mac);
	if (isset($mac_exists) and $mac_exists['count']>=1 and !in_array($parent_id,$mac_exists['users_id'])) {
	        $dup_sql = "SELECT * FROM User_list WHERE id=".$mac_exists['users_id']['0'];
	        $dup_info = get_record_sql($db_link, $dup_sql);
		$msg_error="Mac already exists at another user in this subnet! Skip creating $ip [$mac].<br>Old user id: ".$dup_info['id']." login: ".$dup_info['login'];
		$_SESSION[$page_url]['msg'] = $msg_error;
	        LOG_ERROR($db_link, $msg_error);
	        header("Location: " . $_SERVER["REQUEST_URI"]);
	        exit;
		}
	//disable dhcp for secondary ip
	$f_dhcp = $_POST["f_dhcp"] * 1;
	if (in_array($parent_id,$mac_exists['users_id'])) {
	    if ($parent_id != $mac_exists['users_id'][0]) { $f_dhcp = 0; }
	    }
	//search ip
        $dup_ip_record = get_record_sql($db_link, "SELECT * FROM User_auth WHERE `ip_int`=$ip_aton AND id<>$id AND deleted=0");
        if (!empty($dup_ip_record)) {
            $dup_info = get_record_sql($db_link, "SELECT * FROM User_list WHERE id=".$dup_ip_record['user_id']);
            $msg_error = "$ip already exists. Skip creating $ip [$mac].<br>Old user id: ".$dup_info['id']." login: ".$dup_info['login'];
	    $_SESSION[$page_url]['msg'] = $msg_error;
            LOG_ERROR($db_link, $msg_error);
            header("Location: " . $_SERVER["REQUEST_URI"]);
            exit;
    	    }
        $new['ip'] = $ip;
        $new['ip_int'] = $ip_aton;
        $new['mac'] = mac_dotted($_POST["f_mac"]);
//      $new['clientid'] = $_POST["f_clientid"];
        $new['comments'] = $_POST["f_comments"];
        $new['firmware'] = $_POST["f_firmware"];
        $new['WikiName'] = $_POST["f_wiki"];
        $f_dnsname=trim($_POST["f_dns_name"]);
        if (!empty($f_dnsname) and checkValidHostname($f_dnsname) and checkUniqHostname($db_link,$id,$f_dnsname)) { $new['dns_name'] = $f_dnsname; }
        if (empty($f_dnsname)) { $new['dns_name'] = ''; }
        $new['device_model_id'] = $_POST["f_device_model_id"]*1;
        $new['save_traf'] = $_POST["f_save_traf"] * 1;
        $new['dhcp_acl'] = trim($_POST["f_acl"]);
        if ($default_user_id == $parent_id or $hotspot_user_id == $parent_id) {
                $new['nagios_handler'] = '';
                $new['enabled'] = 0;
                $new['link_check'] = 0;
                $new['nagios'] = 0;
                $new['blocked'] = 0;
                $new['day_quota'] = 0;
                $new['month_quota'] = 0;
                $new['queue_id'] = 0;
                $new['filter_group_id'] = 0;
            } else {
                $new['nagios_handler'] = $_POST["f_handler"];
                $new['enabled'] = $_POST["f_enabled"] * 1;
                $new['link_check'] = $_POST["f_link"] * 1;
                $new['nagios'] = $_POST["f_nagios"] * 1;
                $new['dhcp'] = $f_dhcp;
                $new['blocked'] = $_POST["f_blocked"] * 1;
                $new['day_quota'] = $_POST["f_day_q"] * 1;
                $new['month_quota'] = $_POST["f_month_q"] * 1;
                $new['queue_id'] = $_POST["f_queue_id"] * 1;
                $new['filter_group_id'] = $_POST["f_group_id"] * 1;
            }
        $changes = get_diff_rec($db_link,"User_auth","id='$id'", $new, 0);
        if (!empty($changes)) { LOG_WARNING($db_link,"Изменен адрес доступа! Список изменений: $changes"); }
        update_record($db_link, "User_auth", "id='$id'", $new);
    } else {
	$msg_error = "$msg_ip_error xxx.xxx.xxx.xxx";
        $_SESSION[$page_url]['msg'] = $msg_error;
	}
    header("Location: " . $_SERVER["REQUEST_URI"]);
    }

if (isset($_POST["moveauth"]) and !$old_auth_info['deleted']) {
    $new['user_id'] = $_POST["new_parent"];
    $changes = get_diff_rec($db_link,"User_auth","id='$id'", $new, 0);
    if (!empty($changes)) { LOG_WARNING($db_link,"Адрес доступа перемещён к другому пользователю! Применено: $changes"); }
    update_record($db_link, "User_auth", "id='$id'", $new);
    header("Location: " . $_SERVER["REQUEST_URI"]);
    }

if (isset($_POST["recovery"])) {
    $ip = trim($_POST["f_ip"]);
    if (checkValidIp($ip)) {
        $ip_aton = ip2long($ip);
	$mac=mac_dotted($_POST["f_mac"]);
        $parent_id = $old_auth_info['user_id'];
        //search mac
	$mac_exists=find_mac_in_subnet($db_link,$ip,$mac);
	if (isset($mac_exists) and $mac_exists['count']>=1 and !in_array($parent_id,$mac_exists['users_id'])) {
	        $dup_sql = "SELECT * FROM User_list WHERE id=".$mac_exists['users_id']['0'];
	        $dup_info = get_record_sql($db_link, $dup_sql);
		$msg_error="Mac already exists at another user in this subnet! Skip creating $ip [$mac].<br>Old user id: ".$dup_info['id']." login: ".$dup_info['login'];
		$_SESSION[$page_url]['msg'] = $msg_error;
	        LOG_ERROR($db_link, $msg_error);
	        header("Location: " . $_SERVER["REQUEST_URI"]);
	        exit;
		}
	//disable dhcp for secondary ip
	$f_dhcp = $_POST["f_dhcp"] * 1;
	if (in_array($parent_id,$mac_exists['users_id'])) {
	    if ($parent_id != $mac_exists['users_id'][0]) { $f_dhcp = 0; }
	    }
	//search ip
        $dup_ip_record = get_record_sql($db_link, "SELECT * FROM User_auth WHERE `ip_int`=$ip_aton AND id<>$id AND deleted=0");
        if (!empty($dup_ip_record)) {
            $dup_info = get_record_sql($db_link, "SELECT * FROM User_list WHERE id=".$dup_ip_record['user_id']);
            $msg_error = "$ip already exists. Skip creating $ip [$mac].<br>Old user id: ".$dup_info['id']." login: ".$dup_info['login'];
	    $_SESSION[$page_url]['msg'] = $msg_error;
            LOG_ERROR($db_link, $msg_error);
            header("Location: " . $_SERVER["REQUEST_URI"]);
            exit;
    	    }
        $new['deleted'] = 0;
        if ($default_user_id == $parent_id or $hotspot_user_id == $parent_id) {
                $new['nagios_handler'] = '';
                $new['enabled'] = 0;
                $new['link_check'] = 0;
                $new['nagios'] = 0;
                $new['blocked'] = 0;
                $new['day_quota'] = 0;
                $new['month_quota'] = 0;
                $new['queue_id'] = 0;
                $new['filter_group_id'] = 0;
            } else {
                $new['nagios_handler'] = $_POST["f_handler"];
                $new['enabled'] = $_POST["f_enabled"] * 1;
                $new['link_check'] = $_POST["f_link"] * 1;
                $new['nagios'] = $_POST["f_nagios"] * 1;
                $new['dhcp'] = $_POST["f_dhcp"] * 1;
                $new['blocked'] = $_POST["f_blocked"] * 1;
                $new['day_quota'] = $_POST["f_day_q"] * 1;
                $new['month_quota'] = $_POST["f_month_q"] * 1;
                $new['queue_id'] = $_POST["f_queue_id"] * 1;
                $new['filter_group_id'] = $_POST["f_group_id"] * 1;
            }
        $changes = get_diff_rec($db_link,"User_auth","id='$id'", $new, 0);
        if (!empty($changes)) { LOG_WARNING($db_link,"Восстановлен адрес доступа! Применено: $changes"); }
        update_record($db_link, "User_auth", "id='$id'", $new);
	} else {
        $msg_error = "$msg_ip_error xxx.xxx.xxx.xxx/xx";
        $_SESSION[$page_url]['msg'] = $msg_error;
	}
    header("Location: " . $_SERVER["REQUEST_URI"]);
    }

unset($_POST);

require_once ($_SERVER['DOCUMENT_ROOT']."/inc/header.php");

$sSQL = "SELECT * FROM User_auth WHERE id=$id";
$auth_info = get_record_sql($db_link, $sSQL);

$parent_name = get_login($db_link, $auth_info['user_id']);
if ($auth_info['dhcp_time'] == '0000-00-00 00:00:00') { $dhcp_str = ''; } else { $dhcp_str = $auth_info['dhcp_time'] . " (" . $auth_info['dhcp_action'] . ")"; }
if ($auth_info['last_found'] == '0000-00-00 00:00:00') { $auth_info['last_found'] = ''; }
?>
<div id="cont">
<?php
if (!empty($_SESSION[$page_url]['msg'])) {
    print '<div id="msg">'.$_SESSION[$page_url]['msg'].'</div>';
    unset($_SESSION[$page_url]['msg']);
    }
print "<b> Адрес доступа пользователя <a href=/admin/users/edituser.php?id=".$auth_info['user_id'].">".$parent_name."</a> </b>";
?>
<form name="def" action="editauth.php?id=<? echo $id; ?>" method="post">
<input type="hidden" name="id" value=<? echo $id; ?>>
<table class="data">
<tr>
<td width=200><?php print $cell_dns_name." &nbsp | &nbsp "; print_url("Альясы","/admin/users/edit_alias.php?id=$id"); ?></td>
<td width=200><?php print $cell_comment; ?></td>
<td width=200><?php print $cell_wikiname; ?></td>
<td width=70><?php print $cell_enabled; ?></td>
<td width=70><?php print $cell_blocked; ?></td>
<td width=70><?php print $cell_perday; ?></td>
<td width=70><?php print $cell_permonth; ?></td>
</tr>
<tr>
<td><input type="text" name="f_dns_name" value="<? echo $auth_info['dns_name']; ?>" pattern="^(([a-zA-Z0-9]|[a-zA-Z0-9][a-zA-Z0-9\-]*[a-zA-Z0-9])\.)*([A-Za-z0-9]|[A-Za-z0-9][A-Za-z0-9\-]*[A-Za-z0-9])$"></td>
<td><input type="text" name="f_comments" value="<? echo $auth_info['comments']; ?>"></td>
<td><input type="text" name="f_wiki" value="<? echo $auth_info['WikiName']; ?>"></td>
<td><?php print_qa_select('f_enabled', $auth_info['enabled']); ?></td>
<td><?php print_qa_select('f_blocked', $auth_info['blocked']); ?></td>
<td><input type="text" name="f_day_q" value="<? echo $auth_info['day_quota']; ?>" size=5></td>
<td><input type="text" name="f_month_q"	value="<? echo $auth_info['month_quota']; ?>" size=5></td>
</tr>
<tr>
<td><?php print $cell_ip; ?></td>
<td><?php print $cell_mac; ?></td>
<td><?php print $cell_acl; ?></td>
<td><?php print $cell_dhcp; ?></td>
<td><?php print $cell_filter; ?></td>
<td><?php print $cell_shaper; ?></td>
<td></td>
<tr>
<td><input type="text" name="f_ip" value="<? echo $auth_info['ip']; ?>" pattern="^(([0-9]|[1-9][0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5])\.){3}([0-9]|[1-9][0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5])$"></td>
<td><input type="text" name="f_mac" value="<? echo $auth_info['mac']; ?>"></td>
<td><input type="text" name="f_acl" value="<? echo $auth_info['dhcp_acl']; ?>"></td>
<td><?php print_qa_select('f_dhcp', $auth_info['dhcp']); ?></td>
<td><?php print_group_select($db_link, 'f_group_id', $auth_info['filter_group_id']); ?> </td>
<td><?php print_queue_select($db_link, 'f_queue_id', $auth_info['queue_id']); ?> </td>
<td></td>
</tr>
<tr>
<td><?php print $cell_host_model; ?></td>
<td><?php print $cell_host_firmware; ?></td>
<td><?php print $cell_nagios_handler; ?></td>
<td><?php print $cell_nagios; ?></td>
<td><?php print $cell_link; ?></td>
<td><?php print $cell_traf; ?></td>
<td></td>
<tr>
<td><?php print_device_model_select($db_link,'f_device_model_id',$auth_info['device_model_id']); ?></td>
<td><input type="text" name="f_firmware" value="<? echo $auth_info['firmware']; ?>"></td>
<td><input type="text" name="f_handler"	value="<? echo $auth_info['nagios_handler']; ?>"></td>
<td><?php print_qa_select('f_nagios', $auth_info['nagios']); ?></td>
<td><?php print_qa_select('f_link', $auth_info['link_check']); ?></td>
<td><?php print_qa_select('f_save_traf', $auth_info['save_traf']); ?></td>
<td></td>
</tr>
<tr>
<td colspan=2><input type="submit" name="moveauth" value=<?php print $btn_move; ?>><?php print_login_select($db_link, 'new_parent', $auth_info['user_id']); ?></td>
<td><a href=/admin/logs/authlog.php?auth_id=<?php print $id; ?>>Лог</a></td>
<td></td>
<?php
if ($auth_info['deleted']) {
    print "<td colspan=2>Deleted: " . $auth_info['changed_time']."</td>";
    print "<td colspan=1 align=right><input type=\"submit\" name=\"recovery\" value=\"Восстановить\"></td>";
} else {
    print "<td colspan=2></td>";
    print "<td colspan=1 align=right><input type=\"submit\" name=\"editauth\" value=\"$btn_save\"></td>";
}
?>
</tr>
<tr ><td class="data" colspan=7>Status:</td></tr>
<tr >
<td>
<?php
print "Created: " . $auth_info['timestamp']."<br>";
?>
</td>
<td colspan=2>
<?php
print "Dhcp event: " . $dhcp_str."<br>";
print "Dhcp hostname: " . $auth_info['dhcp_hostname']."<br>";
?>
</td>
<td><?php print_url("Трафик за день","/admin/reports/authday.php?id=$id"); ?></td>
<td colspan=3>
<?php
print "Last found: " . $auth_info['last_found']."<br>";
print "Connected: ".get_connection($db_link, $id)."<br>";
?>
</td>
</tr>
</table>
<?
if ($msg_error) {
    print "<div id='msg'><b>$msg_error</b></div><br>\n";
}
?>
</form>
<br>
<?
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/footer.php");
?>
