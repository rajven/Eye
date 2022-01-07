<?php
require_once ($_SERVER["DOCUMENT_ROOT"]."/inc/auth.php");
require_once ($_SERVER["DOCUMENT_ROOT"]."/inc/languages/" . $language . ".php");
require_once ($_SERVER["DOCUMENT_ROOT"]."/inc/idfilter.php");

global $default_user_ou_id;
global $default_hotspot_ou_id;

$default_sort='ip_int';
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/sortfilter.php");

$msg_error = "";

if (isset($_POST["edituser"])) {
    unset($new);
    $new["ou_id"] = $_POST["f_ou"] * 1;
    $new["filter_group_id"] = $_POST["f_filter"]*1;
    $new["queue_id"] = $_POST["f_queue"]*1;
    $new["login"] = trim($_POST["f_login"]);
    $new["fio"] = trim($_POST["f_fio"]);
    if ($default_user_ou_id == $new["ou_id"] or $default_hotspot_ou_id == $new["ou_id"]) {
        $new["enabled"] = 0;
        $new["blocked"] = 0;
        $new["day_quota"] = 0;
        $new["month_quota"] = 0;
        $auth["enabled"] = 0;
        $auth["blocked"] = 0;
    } else {
        $new["enabled"] = $_POST["f_enabled"] * 1;
        $new["blocked"] = $_POST["f_blocked"] * 1;
        $new["day_quota"] = trim($_POST["f_perday"]) * 1;
        $new["month_quota"] = trim($_POST["f_permonth"]) * 1;
    }
    $changes = get_diff_rec($db_link,"User_list","id='$id'", $new, 0);
    if (!empty($changes)) { LOG_WARNING($db_link,"Изменён пользователь id: $id. \r\nПрименено: $changes"); }
    update_record($db_link, "User_list", "id='$id'", $new);
    run_sql($db_link, "UPDATE User_auth SET ou_id=".$new["ou_id"]." WHERE user_id=".$id);
    run_sql($db_link, "UPDATE devices SET device_name='".$new["login"]."' WHERE user_id=".$id);
    header("Location: " . $_SERVER["REQUEST_URI"]);
    exit;
}

if (isset($_POST["addMacRule"])) {
    unset($new);
    $first_auth = get_record_sql($db_link,"SELECT mac FROM User_auth WHERE user_id=".$id." AND deleted=0 AND LENGTH(mac)>0 ORDER BY id");
    if (!empty($first_auth) and !empty($first_auth['mac'])) {
        $new['user_id']=$id;
        $new['type']=2;
        $new['rule']=$first_auth['mac'];
	insert_record($db_link,"auth_rules",$new);
	LOG_INFO($db_link,"Создано правило атоназначения юзеру id: ".$id." для мака ".$first_auth['mac']);
	}
    header("Location: " . $_SERVER["REQUEST_URI"]);
    exit;
}

if (isset($_POST["delMacRule"])) {
    run_sql($db_link,"DELETE FROM auth_rules WHERE user_id=".$id." AND type=2");
    LOG_INFO($db_link,"Удалены все правила атоназначения юзеру id: $id по маку");
    header("Location: " . $_SERVER["REQUEST_URI"]);
    exit;
}

if (isset($_POST["addIPRule"])) {
    unset($new);
    $first_auth = get_record_sql($db_link,"SELECT ip FROM User_auth WHERE user_id=".$id." AND deleted=0 AND LENGTH(ip)>0 ORDER BY id");
    if (!empty($first_auth) and !empty($first_auth['ip'])) {
        $new['user_id']=$id;
        $new['type']=1;
        $new['rule']=$first_auth['ip'];
	insert_record($db_link,"auth_rules",$new);
	LOG_INFO($db_link,"Создано правило атоназначения юзеру id: ".$id." для IP ".$first_auth['IP']);
	}
    header("Location: " . $_SERVER["REQUEST_URI"]);
    exit;
}

if (isset($_POST["delIPRule"])) {
    run_sql($db_link,"DELETE FROM auth_rules WHERE user_id=".$id." AND type=1");
    LOG_INFO($db_link,"Удалены все правила атоназначения юзеру id: $id по ip");
    header("Location: " . $_SERVER["REQUEST_URI"]);
    exit;
}

if (isset($_POST["showDevice"])) {
    $device = get_record_sql($db_link,"SELECT * FROM devices WHERE user_id=".$id);
    $auth = get_record_sql($db_link,"SELECT * FROM User_auth WHERE user_id=".$id);
    if (empty($device) and !empty($auth)) {
	$sSQL = "SELECT * FROM User_list WHERE id=$id";
	$user_info = get_record_sql($db_link, $sSQL);
        global $snmp_default_version;
        global $snmp_default_community;
	$new['user_id']=$id;
        $new['device_name'] = $user_info['login'];
        $new['device_type'] = 5;
        $new['ip']=$auth['ip'];
        $new['community'] = $snmp_default_community;
        $new['snmp_version'] = $snmp_default_version;
        $new_id=insert_record($db_link, "devices", $new);
        unset($_POST);
        if (!empty($new_id)) {
            LOG_INFO($db_link, "Created device with id: $new_id for auth_id: $id");
	    header("Location: /admin/devices/editdevice.php?id={$new_id}");
	    exit;
	    } else {
	    header("Location: ".$_SERVER["REQUEST_URI"]);
	    exit;
	    }
	}
    header("Location: /admin/devices/editdevice.php?id=".$device['id']);
    exit;
}

if (isset($_POST["addauth"])) {
    $fip = substr(trim($_POST["newip"]), 0, 18);
    if (isset($_POST["newmac"])) { $fmac = mac_dotted(substr(trim($_POST["newmac"]), 0, 17)); }
    if ($fip) {
        if (checkValidIp($fip)) {
            $ip_aton = ip2long($fip);
            //search mac
            $mac_exists=find_mac_in_subnet($db_link,$fip,$fmac);
            if (isset($mac_exists) and $mac_exists['count']>=1 and !in_array($id,$mac_exists['users_id'])) {
                $dup_sql = "SELECT * FROM User_list WHERE id=".$mac_exists['users_id']['0'];
                $dup_info = get_record_sql($db_link, $dup_sql);
                $msg_error="Mac already exists at another user in this subnet! Skip creating $fip [$fmac].<br>Old user id: ".$dup_info['id']." login: ".$dup_info['login'];
                $_SESSION[$page_url]['msg'] = $msg_error;
                LOG_ERROR($db_link, $msg_error);
                header("Location: " . $_SERVER["REQUEST_URI"]);
                exit;
                }
            //disable dhcp for secondary ip
            $f_dhcp = 1;
            if (in_array($id,$mac_exists['users_id'])) { $f_dhcp = 0; }
            //search ip
            $dup_ip_record = get_record_sql($db_link, "SELECT * FROM User_auth WHERE `ip_int`=$ip_aton AND user_id<>".$id." AND deleted=0");
            if (!empty($dup_ip_record)) {
                $dup_info = get_record_sql($db_link, "SELECT * FROM User_list WHERE id=".$dup_ip_record['user_id']);
                $msg_error = "$fip already exists. Skip creating $fip [$fmac].<br>Old user id: ".$dup_info['id']." login: ".$dup_info['login'];
                $_SESSION[$page_url]['msg'] = $msg_error;
                LOG_ERROR($db_link, $msg_error);
                header("Location: " . $_SERVER["REQUEST_URI"]);
                exit;
                }
            $fid = new_auth($db_link, $fip, $fmac, $id);
            if (!empty($fid)) {
                $new['dhcp']=$f_dhcp;
                update_record($db_link,"User_auth","id=".$fid,$new);
                apply_auth_rule($db_link,$fid,$id);
                LOG_WARNING($db_link,"Создан новый адрес доступа: ip => $fip, mac => $fmac",$fid);
                header("Location: /admin/users/editauth.php?id=".$fid);
                exit;
                }
	    header("Location: " . $_SERVER["REQUEST_URI"]);
	    exit;
    	    } else {
                $msg_error = "$msg_ip_error xxx.xxx.xxx.xxx";
                $_SESSION[$page_url]['msg'] = $msg_error;
    	    }
	}
    header("Location: " . $_SERVER["REQUEST_URI"]);
    exit;
    }

if (isset($_POST["removeauth"])) {
    $auth_id = $_POST["f_auth_id"];
    foreach ($auth_id as $key => $val) {
        if ($val) {
            run_sql($db_link, 'DELETE FROM connections WHERE auth_id='.$val);
            run_sql($db_link, 'DELETE FROM User_auth_alias WHERE auth_id='.$val);
            $auth["deleted"] = 1;
            $changes = get_diff_rec($db_link,"User_auth","id='$val'", '', 0);
            if (!empty($changes)) { LOG_WARNING($db_link,"Удалён адрес доступа: \r\n $changes",$val); }
            update_record($db_link, "User_auth", "id=" . $val, $auth);
        }
    }
    header("Location: " . $_SERVER["REQUEST_URI"]);
    exit;
}

unset($_POST);

$sSQL = "SELECT * FROM User_list WHERE id=$id";
$user_info = get_record_sql($db_link, $sSQL);

require_once ($_SERVER["DOCUMENT_ROOT"]."/inc/header.php");
?>
<div id="cont">
<?php
if (!empty($_SESSION[$page_url]['msg'])) {
    print '<div id="msg">'.$_SESSION[$page_url]['msg'].'</div>';
    unset($_SESSION[$page_url]['msg']);
    }
?>
<form name="def" action="edituser.php?id=<?php echo $id; ?>" method="post">
<input type="hidden" name="id" value=<?php echo $id; ?>>
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
<td colspan=2><?php print $cell_perday; ?></td>
<td colspan=2><?php print $cell_permonth; ?></td>
<td><?php print $cell_blocked; ?></td>
<td><?php print $cell_enabled; ?></td>
</tr>
<tr>
<td colspan=2><input type="text" name="f_perday" value="<?php echo $user_info["day_quota"]; ?>" size=5></td>
<td colspan=2><input type="text" name="f_permonth" value="<?php echo $user_info["month_quota"]; ?>" size=5></td>
<td ><?php print_qa_select('f_blocked', $user_info["blocked"]); ?></td>
<td ><?php print_qa_select('f_enabled', $user_info["enabled"]); ?></td>
</tr>
<tr><td class=data colspan=6>Параметры для автоназначенных адресов:</td></tr>
<tr>
<td colspan=2><?php print $cell_filter; ?></td>
<td colspan=2><?php print $cell_shaper; ?></td>
<td colspan=2></td>
</tr>
<tr>
<td colspan=2><?php print_group_select($db_link, 'f_filter', $user_info["filter_group_id"]); ?></td>
<td colspan=2><?php print_queue_select($db_link, 'f_queue', $user_info["queue_id"]); ?></td>
<td colspan=2></td>
</tr>
<tr>
<?php
print "<td>"; print_url("Список правил","/admin/users/edit_rules.php?id=$id"); print "</td>";
$rule_count = get_count_records($db_link,"auth_rules","user_id=".$id);
print "<td > Count: ".$rule_count."</td>";
$first_auth = get_record_sql($db_link,"SELECT id FROM User_auth WHERE user_id=".$id." AND deleted=0 ORDER BY id");
if (!empty($first_auth)) {
    //mac
    $mac_rule_count = get_count_records($db_link,"auth_rules","user_id=".$id." AND type=2");
    if (!empty($mac_rule_count)) { 
	print "<td><input type=\"submit\" name=\"delMacRule\" value=".$btn_mac_del." ></td>";
	} else {
	print "<td><input type=\"submit\" name=\"addMacRule\" value=".$btn_mac_add." ></td>";
	}
    //ip
    $ip_rule_count = get_count_records($db_link,"auth_rules","user_id=".$id." AND type=1");
    if (!empty($ip_rule_count)) { 
	print "<td><input type=\"submit\" name=\"delIPRule\" value=".$btn_ip_del." ></td>";
	} else {
	print "<td><input type=\"submit\" name=\"addIPRule\" value=".$btn_ip_add." ></td>";
	}
    } else { print "<td colspan=2></td>"; }
?>
<td colspan=2 align=right>Created: <?php print $user_info["timestamp"]; ?></td>
</tr>
<tr>
<?php print "<td colspan=2>"; print_url("Трафик за день","/admin/reports/userday.php?id=$id"); ?></td>
<td></td>
<td><input type="submit" name="showDevice" value=<?php print $btn_device; ?>></td>
<td></td>
<td align=right><input type="submit" name="edituser" value=<?php print $btn_save; ?>></td>
</tr>
</table>
<?php
if ($msg_error) { print "<div id='msg'><b>$msg_error</b></div><br>\n"; }

$sort_table = 'User_auth';
$sort_url = "<a href=edituser.php?id=" . $id;
if ($id == $default_user_id or $id == $hotspot_user_id) { $default_sort = 'last_found DESC'; }
?>

<br><b>Список адресов доступа</b><br>
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
<td class="data"><?php print "<input type=\"submit\" onclick=\"return confirm('Применить для выделенных?')\" name=\"removeauth\" value=".$btn_remove.">"; ?></td>
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
        print "<td class=\"data\" ></td></tr>";
        }
    }
?>
</table>
</form>
<?php
require_once ($_SERVER["DOCUMENT_ROOT"]."/inc/footer.php");
?>
