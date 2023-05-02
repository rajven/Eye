<?php
require_once($_SERVER['DOCUMENT_ROOT'] . "/inc/auth.php");
require_once($_SERVER['DOCUMENT_ROOT'] . "/inc/languages/" . HTML_LANG . ".php");
require_once($_SERVER['DOCUMENT_ROOT'] . "/inc/idfilter.php");

$msg_error = "";

$old_auth_info = get_record_sql($db_link, "SELECT * FROM User_auth WHERE id=" . $id);
$parent_id = $old_auth_info['user_id'];

$user_info = get_record_sql($db_link, "SELECT * FROM User_list WHERE id=" . $parent_id);
$parent_ou_id = $user_info['ou_id'];

if (isset($_POST["editauth"]) and !$old_auth_info['deleted']) {
    $ip = trim($_POST["f_ip"]);
    if (checkValidIp($ip)) {
        $ip_aton = ip2long($ip);
        $mac = mac_dotted($_POST["f_mac"]);
        //search mac
        $mac_exists = find_mac_in_subnet($db_link, $ip, $mac);
        if (isset($mac_exists) and $mac_exists['count'] >= 1 and !in_array($parent_id, $mac_exists['users_id'])) {
            $dup_sql = "SELECT * FROM User_list WHERE id=" . $mac_exists['users_id']['0'];
            $dup_info = get_record_sql($db_link, $dup_sql);
            $msg_error = "Mac already exists at another user in this subnet! Skip creating $ip [$mac].<br>Old user id: " . $dup_info['id'] . " login: " . $dup_info['login'];
            $_SESSION[$page_url]['msg'] = $msg_error;
            LOG_ERROR($db_link, $msg_error);
            header("Location: " . $_SERVER["REQUEST_URI"]);
            exit;
        }
        //disable dhcp for secondary ip
        $f_dhcp = $_POST["f_dhcp"] * 1;
        if (!empty($mac_exists) and in_array($parent_id, $mac_exists['users_id'])) {
            if ($parent_id != $mac_exists['users_id'][0]) {
                $f_dhcp = 0;
            }
        }
        //search ip
        $dup_ip_record = get_record_sql($db_link, "SELECT * FROM User_auth WHERE `ip_int`=$ip_aton AND id<>$id AND deleted=0");
        if (!empty($dup_ip_record)) {
            $dup_info = get_record_sql($db_link, "SELECT * FROM User_list WHERE id=" . $dup_ip_record['user_id']);
            $msg_error = "$ip already exists. Skip creating $ip [$mac].<br>Old user id: " . $dup_info['id'] . " login: " . $dup_info['login'];
            $_SESSION[$page_url]['msg'] = $msg_error;
            LOG_ERROR($db_link, $msg_error);
            header("Location: " . $_SERVER["REQUEST_URI"]);
            exit;
        }
        $new['ip'] = $ip;
        $new['ou_id'] = $parent_ou_id;
        $new['ip_int'] = $ip_aton;
        $new['mac'] = mac_dotted($_POST["f_mac"]);
        $new['comments'] = $_POST["f_comments"];
        //        $new['firmware'] = $_POST["f_firmware"];
        $new['WikiName'] = $_POST["f_wiki"];
        $f_dnsname = trim($_POST["f_dns_name"]);
        //        if (!empty($f_dnsname) and checkValidHostname($f_dnsname) and checkUniqHostname($db_link,$id,$f_dnsname)) { $new['dns_name'] = $f_dnsname; }
        if (!empty($f_dnsname) and checkValidHostname($f_dnsname)) {
            $new['dns_name'] = $f_dnsname;
        }
        if (empty($f_dnsname)) {
            $new['dns_name'] = '';
        }
        $new['save_traf'] = $_POST["f_save_traf"] * 1;
        $new['dhcp_acl'] = trim($_POST["f_acl"]);
        if (get_const('default_user_ou_id') == $parent_ou_id or get_const('default_hotspot_ou_id') == $parent_ou_id) {
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
        if ($new['nagios'] == 0) {
            $new['nagios_status'] = 'UP';
            }
        $changes = get_diff_rec($db_link, "User_auth", "id='$id'", $new, 0);
        if (!empty($changes)) {
            LOG_WARNING($db_link, "Changed record for $ip! Log: " . $changes, $id);
            }
        if (is_auth_bind_changed($db_link, $id, $ip, $mac)) {
            $new_id = copy_auth($db_link, $id, $new);
            header("Location: /admin/users/editauth.php?id=" . $new_id, TRUE, 302);
            exit;
            } else {
            update_record($db_link, "User_auth", "id='$id'", $new);
            }
    } else {
        $msg_error = "$msg_ip_error xxx.xxx.xxx.xxx";
        $_SESSION[$page_url]['msg'] = $msg_error;
    }
    header("Location: " . $_SERVER["REQUEST_URI"]);
    exit;
}

if (isset($_POST["moveauth"]) and !$old_auth_info['deleted']) {
    $new_parent_id = $_POST["f_new_parent"] * 1;
    $changes = apply_auth_rule($db_link, $id, $new_parent_id);
    LOG_WARNING($db_link, "IP-address moved to another user! Applyed: " . get_rec_str($changes), $id);
    header("Location: " . $_SERVER["REQUEST_URI"]);
    exit;
}

if (isset($_POST["recovery"]) and $old_auth_info['deleted']) {
    $ip = trim($_POST["f_ip"]);
    if (checkValidIp($ip)) {
        $ip_aton = ip2long($ip);
        $mac = mac_dotted($_POST["f_mac"]);
        //search mac
        $mac_exists = find_mac_in_subnet($db_link, $ip, $mac);
        if (isset($mac_exists) and $mac_exists['count'] >= 1 and !in_array($parent_id, $mac_exists['users_id'])) {
            $dup_sql = "SELECT * FROM User_list WHERE id=" . $mac_exists['users_id']['0'];
            $dup_info = get_record_sql($db_link, $dup_sql);
            $msg_error = "Mac already exists at another user in this subnet! Skip creating $ip [$mac].<br>Old user id: " . $dup_info['id'] . " login: " . $dup_info['login'];
            $_SESSION[$page_url]['msg'] = $msg_error;
            LOG_ERROR($db_link, $msg_error);
            header("Location: " . $_SERVER["REQUEST_URI"]);
            exit;
        }
        //disable dhcp for secondary ip
        $f_dhcp = $_POST["f_dhcp"] * 1;
        if (in_array($parent_id, $mac_exists['users_id'])) {
            if ($parent_id != $mac_exists['users_id'][0]) {
                $f_dhcp = 0;
            }
        }
        //search ip
        $dup_ip_record = get_record_sql($db_link, "SELECT * FROM User_auth WHERE `ip_int`=$ip_aton AND id<>$id AND deleted=0");
        if (!empty($dup_ip_record)) {
            $dup_info = get_record_sql($db_link, "SELECT * FROM User_list WHERE id=" . $dup_ip_record['user_id']);
            $msg_error = "$ip already exists. Skip creating $ip [$mac].<br>Old user id: " . $dup_info['id'] . " login: " . $dup_info['login'];
            $_SESSION[$page_url]['msg'] = $msg_error;
            LOG_ERROR($db_link, $msg_error);
            header("Location: " . $_SERVER["REQUEST_URI"]);
            exit;
        }
        $new['deleted'] = 0;

        if (!empty($_POST["f_nagios"])) {
            $a_nagios = $_POST["f_nagios"] * 1;
        } else {
            $a_nagios = 0;
        }
        if (!empty($_POST["f_link"])) {
            $a_link = $_POST["f_link"] * 1;
        } else {
            $a_link = 0;
        }

        $new_parent = get_record_sql($db_link, "User_list", "id=" . $parent_id);
        if (!empty($new_parent)) {
            $new['user_id'] = $parent_id;
            $new['ou_id'] = $new_parent['ou_id'];
        } else {
            $new_user_info = get_new_user_id($db_link, $ip, $mac, NULL);
            if ($new_user_info['user_id']) {
                $new_user_id = $new_user_info['user_id'];
            }
            if (empty($new_user_id)) {
                $new_user_id = new_user($db_link, $new_user_info);
            }
            $new['user_id'] = $new_user_id;
        }

        if (get_const('default_user_ou_id') == $parent_ou_id or get_const('default_hotspot_ou_id') == $parent_ou_id) {
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
            $new['link_check'] = $a_link;
            $new['nagios'] = $a_nagios;
            $new['dhcp'] = $_POST["f_dhcp"] * 1;
            $new['blocked'] = $_POST["f_blocked"] * 1;
            $new['day_quota'] = $_POST["f_day_q"] * 1;
            $new['month_quota'] = $_POST["f_month_q"] * 1;
            $new['queue_id'] = $_POST["f_queue_id"] * 1;
            $new['filter_group_id'] = $_POST["f_group_id"] * 1;
        }
        $changes = get_diff_rec($db_link, "User_auth", "id='$id'", $new, 0);
        if (!empty($changes)) {
            LOG_WARNING($db_link, "Recovered ip-address. Applyed: $changes", $id);
        }
        update_record($db_link, "User_auth", "id='$id'", $new);
        apply_auth_rule($db_link, $id, $new['user_id']);
    } else {
        $msg_error = "$msg_ip_error xxx.xxx.xxx.xxx/xx";
        $_SESSION[$page_url]['msg'] = $msg_error;
    }
    header("Location: " . $_SERVER["REQUEST_URI"]);
    exit;
}

unset($_POST);

require_once($_SERVER['DOCUMENT_ROOT'] . "/inc/header.php");

$sSQL = "SELECT * FROM User_auth WHERE id=$id";
$auth_info = get_record_sql($db_link, $sSQL);
$device = get_record_sql($db_link, "SELECT * FROM devices WHERE user_id=" . $auth_info['user_id']);

$parent_name = get_login($db_link, $auth_info['user_id']);
if ($auth_info['dhcp_time'] == '0000-00-00 00:00:00') {
    $dhcp_str = '';
} else {
    $dhcp_str = $auth_info['dhcp_time'] . " (" . $auth_info['dhcp_action'] . ")";
}
if ($auth_info['last_found'] == '0000-00-00 00:00:00') {
    $auth_info['last_found'] = '';
}
?>
<div id="cont">
    <?php
    if (!empty($_SESSION[$page_url]['msg'])) {
        print '<div id="msg">' . $_SESSION[$page_url]['msg'] . '</div>';
        unset($_SESSION[$page_url]['msg']);
    }
    print "<b>" . WEB_user_title . "&nbsp<a href=/admin/users/edituser.php?id=" . $auth_info['user_id'] . ">" . $parent_name . "</a> </b>";
    ?>
    <form name="def" action="editauth.php?id=<?php echo $id; ?>" method="post">
        <input type="hidden" name="id" value=<?php echo $id; ?>>
        <table class="data">
            <tr>
                <td width=200><?php print WEB_cell_dns_name . " &nbsp | &nbsp ";
                                print_url("Альясы", "/admin/users/edit_alias.php?id=$id"); ?></td>
                <td width=200><?php print WEB_cell_comment; ?></td>
                <td width=70><?php print WEB_cell_enabled; ?></td>
                <td><?php print WEB_cell_traf; ?></td>
                <td></td>
            </tr>
            <tr>
                <td><input type="text" name="f_dns_name" value="<?php echo $auth_info['dns_name']; ?>" pattern="^(([a-zA-Z0-9]|[a-zA-Z0-9][a-zA-Z0-9\-]*[a-zA-Z0-9])\.)*([A-Za-z0-9]|[A-Za-z0-9][A-Za-z0-9\-]*[A-Za-z0-9])$"></td>
                <td><input type="text" name="f_comments" value="<?php echo $auth_info['comments']; ?>"></td>
                <td><?php print_qa_select('f_enabled', $auth_info['enabled']); ?></td>
                <td><?php print_qa_select('f_save_traf', $auth_info['save_traf']); ?></td>
                <td></td>
            </tr>
            <tr>
                <td><?php print WEB_cell_ip; ?></td>
                <td><?php print WEB_cell_mac; ?></td>
                <td><?php print WEB_cell_dhcp; ?></td>
                <td><?php print WEB_cell_acl; ?></td>
                <td></td>
            <tr>
                <td><input type="text" name="f_ip" value="<?php echo $auth_info['ip']; ?>" pattern="^(([0-9]|[1-9][0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5])\.){3}([0-9]|[1-9][0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5])$"></td>
                <td><input type="text" name="f_mac" value="<?php echo $auth_info['mac']; ?>" pattern="^([0-9A-Fa-f]{2}[:-]){5}[0-9A-Fa-f]{2}|([0-9a-fA-F]{4}[\\.-][0-9a-fA-F]{4}[\\.-][0-9a-fA-F]{4})|[0-9A-Fa-f]{12}$"></td>
                <td><?php print_qa_select('f_dhcp', $auth_info['dhcp']); ?></td>
                <td colspan=2><input type="text" name="f_acl" value="<?php echo $auth_info['dhcp_acl']; ?>"></td>
            </tr>
            <tr>
                <td><?php print WEB_cell_filter; ?></td>
                <td><?php print WEB_cell_shaper; ?></td>
                <td><?php print WEB_cell_blocked; ?></td>
                <td><?php print WEB_cell_perday; ?></td>
                <td><?php print WEB_cell_permonth; ?></td>
            </tr>
            <tr>
                <td><?php print_group_select($db_link, 'f_group_id', $auth_info['filter_group_id']); ?> </td>
                <td><?php print_queue_select($db_link, 'f_queue_id', $auth_info['queue_id']); ?> </td>
                <td><?php print_qa_select('f_blocked', $auth_info['blocked']); ?></td>
                <td><input type="text" name="f_day_q" value="<?php echo $auth_info['day_quota']; ?>" size=5></td>
                <td><input type="text" name="f_month_q" value="<?php echo $auth_info['month_quota']; ?>" size=5></td>
            </tr>
            <tr>
                <td><?php print WEB_cell_nagios_handler; ?></td>
                <td width=200>
                    <?php
                    if (!empty($auth_info['WikiName'])) {
                        $wiki_url = rtrim(get_option($db_link, 60), '/');
                        if (preg_match('/127.0.0.1/', $wiki_url)) {
                            print WEB_cell_wikiname;
                        } else {
                            $wiki_web = rtrim(get_option($db_link, 63), '/');
                            $wiki_web = ltrim($wiki_web, '/');
                            $wiki_link = $wiki_url . '/' . $wiki_web . '/' . $auth_info['WikiName'];
                            print_url(WEB_cell_wikiname, $wiki_link);
                        }
                    } else {
                        print WEB_cell_wikiname;
                    }
                    $dev_id = get_device_by_auth($db_link, $auth_info['user_id']);
                    if (isset($dev_id)) {
                        print "&nbsp|&nbsp";
                        print_url('Device', '/admin/devices/editdevice.php?id=' . $dev_id);
                    }
                    ?>
                </td>
                <td><?php if (empty($device) or (!empty($device) and $device['device_type'] > 2)) {
                        print WEB_cell_nagios;
                    } ?></td>
                <td><?php if (empty($device) or (!empty($device) and $device['device_type'] > 2)) {
                        print WEB_cell_link;
                    } ?></td>
            <tr>
                <td><input type="text" name="f_handler" value="<?php echo $auth_info['nagios_handler']; ?>"></td>
                <td><input type="text" name="f_wiki" value="<?php echo $auth_info['WikiName']; ?>"></td>
                <td><?php if (empty($device) or (!empty($device) and $device['device_type'] > 2)) {
                        print_qa_select('f_nagios', $auth_info['nagios']);
                    } ?></td>
                <td><?php if (empty($device) or (!empty($device) and $device['device_type'] > 2)) {
                        print_qa_select('f_link', $auth_info['link_check']);
                    } ?></td>
                <td></td>
            </tr>
            <tr>
                <td colspan=2><input type="submit" name="moveauth" value=<?php print WEB_btn_move; ?>><?php print_login_select($db_link, 'f_new_parent', $auth_info['user_id']); ?></td>
                <td><a href=/admin/logs/authlog.php?auth_id=<?php print $id; ?>><?php print WEB_log; ?></a></td>
                <?php
                if ($auth_info['deleted']) {
                    print "<td >" . WEB_deleted . ": " . $auth_info['changed_time'] . "</td>";
                    print "<td align=right><input type=\"submit\" name=\"recovery\" value='" . WEB_btn_recover . "'></td>";
                } else {
                    print "<td ></td>";
                    print "<td align=right><input type=\"submit\" name=\"editauth\" value='" . WEB_btn_save . "'></td>";
                }
                ?>
            </tr>
        </table>
        <table class="data">
            <tr>
                <td class="data" colspan=5><?php echo WEB_status . ":"; ?></td>
            </tr>
            <tr>
                <td colspan=2><?php print WEB_cell_dhcp_hostname . ": " . $auth_info['dhcp_hostname']; ?></td>
                <td width=100>&nbsp</td>
                <td align=right><?php print "Dhcp event: " . $dhcp_str; ?></td>
            </tr>
            <tr>
                <td><?php print WEB_cell_created . ": "; ?></td>
                <td><?php print $auth_info['timestamp']; ?></td>
                <td align=right colspan=2><?php print_url(WEB_report_by_day, "/admin/reports/authday.php?id=$id"); ?></td>
            </tr>
            <tr>
                <td><?php print WEB_cell_last_found . ": "; ?></td>
                <td><?php print $auth_info['last_found'] . "<br>"; ?></td>
                <td align=right><?php print WEB_cell_connection . ": "; ?></td>
                <td align=right><?php print get_connection($db_link, $id) . "<br>"; ?></td>
            </tr>
        </table>
        <?php
        if ($msg_error) {
            print "<div id='msg'><b>$msg_error</b></div><br>\n";
        }
        ?>
    </form>
    <br>
    <?php require_once($_SERVER['DOCUMENT_ROOT'] . "/inc/footer.php"); ?>