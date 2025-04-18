<?php
require_once($_SERVER["DOCUMENT_ROOT"] . "/inc/auth.php");
require_once($_SERVER["DOCUMENT_ROOT"] . "/inc/languages/" . HTML_LANG . ".php");
require_once($_SERVER["DOCUMENT_ROOT"] . "/inc/idfilter.php");


$default_sort = 'ip_int';
require_once($_SERVER['DOCUMENT_ROOT'] . "/inc/sortfilter.php");

$msg_error = "";

$sSQL = "SELECT * FROM User_list WHERE id=$id";
$user_info = get_record_sql($db_link, $sSQL);

if (empty($user_info)) {
    header("Location: /admin/");
}

if (isset($_POST["edituser"])) {
    unset($new);
    $new["ou_id"] = $_POST["f_ou"] * 1;
    $new["filter_group_id"] = $_POST["f_filter"] * 1;
    $new["queue_id"] = $_POST["f_queue"] * 1;
    $new["login"] = trim($_POST["f_login"]);
    $new["fio"] = trim($_POST["f_fio"]);
    if (get_const('default_user_ou_id') == $new["ou_id"] or get_const('default_hotspot_ou_id') == $new["ou_id"]) {
        $new["enabled"] = 0;
        $new["blocked"] = 0;
        $new["day_quota"] = 0;
        $new["month_quota"] = 0;
        $new["permanent"] = 0;
    } else {
        $new["enabled"] = get_int($_POST["f_enabled"]);
        $new["blocked"] = get_int($_POST["f_blocked"]);
        $new["day_quota"] = get_int(trim($_POST["f_perday"]));
        $new["month_quota"] = get_int(trim($_POST["f_permonth"]));
        $new["permanent"] = $_POST["f_permanent"] * 1;
    }
    $changes = get_diff_rec($db_link, "User_list", "id='$id'", $new, 0);
    if (!empty($changes)) {
        LOG_WARNING($db_link, "Changed user id: $id login: " . $new["login"] . ". \r\nApply: $changes");
    }
    update_record($db_link, "User_list", "id='$id'", $new);
    if (!$new["enabled"]) {
        run_sql($db_link, "UPDATE User_auth SET enabled=0, changed=1 WHERE user_id=" . $id);
    }
    if (!empty($new["fio"])) {
        run_sql($db_link, "UPDATE User_auth SET `comments`='" . mysqli_real_escape_string($db_link, $new["fio"]) . "' WHERE `user_id`=" . $id . " AND `deleted`=0 AND (`comments` IS NULL or `comments`='' or `comments`='" . $user_info["fio"] . "')");
    }
    run_sql($db_link, "UPDATE User_auth SET ou_id=" . $new["ou_id"] . " WHERE user_id=" . $id);
    run_sql($db_link, "UPDATE devices SET device_name='" . $new["login"] . "' WHERE user_id=" . $id);
    header("Location: " . $_SERVER["REQUEST_URI"]);
    exit;
}

if (isset($_POST["addMacRule"])) {
    unset($new);
    $first_auth = get_records_sql($db_link, "SELECT mac FROM User_auth WHERE user_id=" . $id . " AND deleted=0 AND LENGTH(mac)>0 ORDER BY id");
    foreach ($first_auth as $row) {
        if (!empty($row['mac'])) { add_auth_rule($db_link, $row['mac'], 2, $id); }
        }
    header("Location: " . $_SERVER["REQUEST_URI"]);
    exit;
}

if (isset($_POST["delMacRule"])) {
    run_sql($db_link, "DELETE FROM auth_rules WHERE user_id=" . $id . " AND type=2");
    LOG_INFO($db_link, "All autorules removed for id: $id login: " . $user_info["login"] . " by mac");
    header("Location: " . $_SERVER["REQUEST_URI"]);
    exit;
}

if (isset($_POST["addIPRule"])) {
    unset($new);
    $first_auth = get_records_sql($db_link, "SELECT ip FROM User_auth WHERE user_id=" . $id . " AND deleted=0 AND LENGTH(ip)>0 ORDER BY id");
    foreach ($first_auth as $row) {
        if (!empty($row['ip'])) { add_auth_rule($db_link, $row['ip'], 1, $id); }
        }
    header("Location: " . $_SERVER["REQUEST_URI"]);
    exit;
}

if (isset($_POST["delIPRule"])) {
    run_sql($db_link, "DELETE FROM auth_rules WHERE user_id=" . $id . " AND type=1");
    LOG_INFO($db_link, "Removed all auto rules for id: $id login: " . $user_info["login"] . " by ip");
    header("Location: " . $_SERVER["REQUEST_URI"]);
    exit;
}

if (isset($_POST["showDevice"])) {
    $device = get_record_sql($db_link, "SELECT * FROM devices WHERE user_id=" . $id);
    $auth = get_record_sql($db_link, "SELECT * FROM User_auth WHERE user_id=" . $id);
    if (empty($device) and !empty($auth)) {
        $new['user_id'] = $id;
        $new['device_name'] = $user_info['login'];
        $new['device_type'] = 5;
        $new['ip'] = $auth['ip'];
        $new['ip_int'] = $auth['ip_int'];
        $new['community'] = get_const('snmp_default_community');
        $new['snmp_version'] = get_const('snmp_default_version');
        $new['login'] = get_option($db_link, 28);
        $new['password'] = get_option($db_link, 29);
        //default ssh
        $new['protocol'] = 0;
        $new['control_port'] = get_option($db_link, 30);
        $new_id = insert_record($db_link, "devices", $new);
        unset($_POST);
        if (!empty($new_id)) {
            LOG_INFO($db_link, "Created device with id: $new_id for auth_id: $id");
            header("Location: /admin/devices/editdevice.php?id={$new_id}");
            exit;
        } else {
            header("Location: " . $_SERVER["REQUEST_URI"]);
            exit;
        }
    }
    header("Location: /admin/devices/editdevice.php?id=" . $device['id']);
    exit;
}

if (isset($_POST["addauth"])) {
    $fip = substr(trim($_POST["newip"]), 0, 18);
    $fmac = NULL;
    if (isset($_POST["newmac"])) {
        $fmac = mac_dotted(substr(trim($_POST["newmac"]), 0, 17));
    }
    if ($fip) {
        if (checkValidIp($fip)) {
            $ip_aton = ip2long($fip);
            $f_dhcp = 1;
            //search mac
            if (!empty($fmac) and !empty($fip)) {
                $mac_exists = find_mac_in_subnet($db_link, $fip, $fmac);
                if (!empty($mac_exists) and $mac_exists['count'] >= 1 and !in_array($id, $mac_exists['users_id'])) {
                    $dup_sql = "SELECT * FROM User_list WHERE id=" . $mac_exists['users_id']['0'];
                    $dup_info = get_record_sql($db_link, $dup_sql);
                    $msg_error = "Mac already exists at another user in this subnet! Skip creating $fip [$fmac].<br>Old user id: " . $dup_info['id'] . " login: " . $dup_info['login'];
                    $_SESSION[$page_url]['msg'] = $msg_error;
                    LOG_ERROR($db_link, $msg_error);
                    header("Location: " . $_SERVER["REQUEST_URI"]);
                    exit;
                }
                //disable dhcp for secondary ip
                if (empty($mac_exists)) {
                    $f_dhcp = 1;
                } else {
                    if (in_array($id, $mac_exists['users_id'])) {
                        $f_dhcp = 0;
                    }
                }
            }
            //search ip
            $dup_ip_record = get_record_sql($db_link, "SELECT * FROM User_auth WHERE `ip_int`=$ip_aton AND user_id<>" . $id . " AND deleted=0");
            if (!empty($dup_ip_record)) {
                $dup_info = get_record_sql($db_link, "SELECT * FROM User_list WHERE id=" . $dup_ip_record['user_id']);
                $msg_error = "$fip already exists. Skip creating $fip [$fmac].<br>Old user id: " . $dup_info['id'] . " login: " . $dup_info['login'];
                $_SESSION[$page_url]['msg'] = $msg_error;
                LOG_ERROR($db_link, $msg_error);
                header("Location: " . $_SERVER["REQUEST_URI"]);
                exit;
            }
            $fid = new_auth($db_link, $fip, $fmac, $id);
            if (!empty($fid)) {
                $new['dhcp'] = $f_dhcp;
                $new['created_by'] = 'manual';
                update_record($db_link, "User_auth", "id=" . $fid, $new);
                LOG_WARNING($db_link, "Add ip for login: " . $user_info["login"] . ": ip => $fip, mac => $fmac", $fid);
                header("Location: /admin/users/editauth.php?id=" . $fid);
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
        if ($val) { delete_user_auth($db_link, $val); }
    }
    header("Location: " . $_SERVER["REQUEST_URI"]);
    exit;
}

if (isset($_POST["new_user"])) {
    $auth_id = $_POST["f_auth_id"];
    $save_traf = get_option($db_link, 23) * 1;
    foreach ($auth_id as $key => $val) {
        if ($val) {
            $auth_info = get_record_sql($db_link, "SELECT * FROM User_auth WHERE id=$val");
            $ou_id = $user_info["ou_id"];
            $login = NULL;
            if (!empty($auth_info["dns_name"])) {
                $login = $auth_info["dns_name"];
            }
            if (empty($login) and !empty($auth_info["comments"])) {
                $login = transliterate($auth_info["comments"]);
            }
            if (empty($login) and !empty($auth_info["dhcp_hostname"])) {
                $login = $auth_info["dhcp_hostname"];
            }
            if (empty($login) and !empty($auth_info["mac"])) {
                $login = $auth_info["mac"];
            }
            if (empty($login)) {
                $login = $auth_info["ip"];
            }
            $new_user = get_record_sql($db_link, "SELECT * FROM User_list WHERE LCase(login)=LCase('$login') and deleted=0");
            if (!empty($new_user)) {
                // move auth
                $auth["user_id"] = $new_user["id"];
                $auth["ou_id"] = $new_user["ou_id"];
                $auth["save_traf"] = $save_traf;
                $auth = apply_auth_rule($db_link, $auth, $l_id);
                update_record($db_link, "User_auth", "id='" . $val . "'", $auth);
                LOG_WARNING($db_link, "ip from id: $val moved to another user user_id: " . $new_user["id"], $val);
            } else {
                $new["login"] = $login;
                $new["ou_id"] = $ou_id;
                if (!empty($auth_info["comments"])) { $new["fio"] = $auth_info["comments"]; }
                if (!isset($new["fio"]) and !empty($auth_info["dns_name"])) { $new["fio"] = $auth_info["dns_name"]; }
                if (!isset($new["fio"]) and !empty($auth_info["dhcp_hostname"])) { $new["fio"] = $auth_info["dhcp_hostname"]; }
                $new["enabled"] = $auth_info["enabled"];
                $l_id = insert_record($db_link, "User_list", $new);
                $auth["user_id"] = $l_id;
                $auth["save_traf"] = $save_traf;
                update_record($db_link, "User_auth", "id='" . $val . "'", $auth);
                LOG_WARNING($db_link, "Create user from ip: login => $login. ip-record auth_id: $val moved to this user.", $val);
            }
        }
    }
    header("Location: " . $_SERVER["REQUEST_URI"]);
    exit;
}

unset($_POST);

require_once($_SERVER["DOCUMENT_ROOT"] . "/inc/header.php");
?>
<div id="cont">
    <?php
    if (!empty($_SESSION[$page_url]['msg'])) {
        print '<div id="msg">' . $_SESSION[$page_url]['msg'] . '</div>';
        unset($_SESSION[$page_url]['msg']);
    }
    ?>
    <form name="def" action="edituser.php?id=<?php echo $id; ?>" method="post">
        <input type="hidden" name="id" value=<?php echo $id; ?>>
        <table class="data">
            <tr>
                <td colspan=2><?php print WEB_cell_login; ?></td>
                <td colspan=2><?php print WEB_cell_fio; ?></td>
                <td colspan=2><?php print WEB_cell_ou; ?></td>
                <td ><?php print WEB_user_permanent; ?></td>
            </tr>
            <tr>
                <td colspan=2><input type="text" name="f_login" value="<?php print $user_info["login"]; ?>" size=25></td>
                <td colspan=2><input type="text" name="f_fio" value="<?php print $user_info["fio"]; ?>" size=25></td>
                <td colspan=2><?php print_ou_set($db_link, 'f_ou', $user_info["ou_id"]); ?></td>
                <td><?php print_qa_select('f_permanent', $user_info["permanent"]); ?></td>
            </tr>
            <tr>
                <td colspan=2><?php print WEB_cell_perday; ?></td>
                <td colspan=2><?php print WEB_cell_permonth; ?></td>
                <td><?php print WEB_cell_blocked; ?></td>
                <td></td>
                <td><?php print WEB_cell_enabled; ?></td>
            </tr>
            <tr>
                <td colspan=2><input type="text" name="f_perday" value="<?php echo $user_info["day_quota"]; ?>" size=5></td>
                <td colspan=2><input type="text" name="f_permonth" value="<?php echo $user_info["month_quota"]; ?>" size=5></td>
                <td><?php print_qa_select('f_blocked', $user_info["blocked"]); ?></td>
                <td></td>
                <td><?php print_qa_select('f_enabled', $user_info["enabled"]); ?></td>
            </tr>
            <tr>
                <td class=data colspan=7><?php echo WEB_user_rules_for_autoassign; ?>:</td>
            </tr>
            <tr>
                <td colspan=2><?php print WEB_cell_filter; ?></td>
                <td colspan=2><?php print WEB_cell_shaper; ?></td>
                <td colspan=3></td>
            </tr>
            <tr>
                <td colspan=2><?php print_group_select($db_link, 'f_filter', $user_info["filter_group_id"]); ?></td>
                <td colspan=2><?php print_queue_select($db_link, 'f_queue', $user_info["queue_id"]); ?></td>
                <td colspan=3></td>
            </tr>
            <tr>
                <?php
                print "<td>";
                print_url(WEB_user_rule_list, "/admin/users/edit_rules.php?id=$id");
                print "</td>";
                $rule_count = get_count_records($db_link, "auth_rules", "user_id=" . $id);
                print "<td > Count: " . $rule_count . "</td>";
                $first_auth = get_record_sql($db_link, "SELECT id FROM User_auth WHERE user_id=" . $id . " AND deleted=0 ORDER BY id");
                if (!empty($first_auth)) {
                    //mac
                    $mac_rule_count = get_count_records($db_link, "auth_rules", "user_id=" . $id . " AND type=2");
                    if (!empty($mac_rule_count)) {
                        print "<td><input type=\"submit\" name=\"delMacRule\" value=" . WEB_btn_mac_del . " ></td>";
                    } else {
                        print "<td><input type=\"submit\" name=\"addMacRule\" value=" . WEB_btn_mac_add . " ></td>";
                    }
                    //ip
                    $ip_rule_count = get_count_records($db_link, "auth_rules", "user_id=" . $id . " AND type=1");
                    if (!empty($ip_rule_count)) {
                        print "<td><input type=\"submit\" name=\"delIPRule\" value=" . WEB_btn_ip_del . " ></td>";
                    } else {
                        print "<td><input type=\"submit\" name=\"addIPRule\" value=" . WEB_btn_ip_add . " ></td>";
                    }
                } else {
                    print "<td colspan=2></td>";
                }
                ?>
                <td colspan=3 align=right><?php print WEB_cell_created . ":&nbsp";
                                            print $user_info["timestamp"]; ?></td>
            </tr>
            <tr>
                <?php print "<td colspan=2>";
                print_url(WEB_report_by_day, "/admin/reports/userday.php?id=$id"); ?></td>
                <td></td>
                <td><input type="submit" name="showDevice" value=<?php print WEB_btn_device; ?>></td>
                <td colspan=2></td>
                <td align=right><input type="submit" name="edituser" value=<?php print WEB_btn_save; ?>></td>
            </tr>
        </table>
        <?php
        if ($msg_error) {
            print "<div id='msg'><b>$msg_error</b></div><br>\n";
        }

        $sort_table = 'User_auth';
        $sort_url = "<a href=edituser.php?id=" . $id;
        ?>

        <br><b><?php echo WEB_user_ip_list; ?></b><br>
        <table class="data">
            <tr>
                <td class="data"><?php echo WEB_user_add_ip; ?>:&nbsp<input type=text name=newip value=""></td>
                <td class="data"><?php echo WEB_user_add_mac; ?>:&nbsp<input type=text name=newmac value=""></td>
                <td class="data"><input type="submit" name="addauth" value="<?php echo WEB_btn_add; ?>"></td>
                <td class="data" align=right><input type="submit" name="new_user" value="<?php echo WEB_btn_transfom; ?>"></td>
            </tr>
        </table>

        <table class="data" width=120%>
            <tr align=center>
                <td class="data"><input type="checkbox" onClick="checkAll(this.checked);"></td>
                <td class="data"><?php print $sort_url . "&sort=ip_int&order=$new_order>" . WEB_cell_ip . "</a>"; ?></td>
                <td class="data"><?php print $sort_url . "&sort=mac&order=$new_order>" . WEB_cell_mac . "</a>"; ?></td>
                <td class="data"><?php print WEB_cell_comment; ?></td>
                <td class="data"><?php print $sort_url . "&sort=dns_name&order=$new_order>" . WEB_cell_dns_name . "</a>"; ?></td>
                <td class="data"><?php print WEB_cell_enabled; ?></td>
                <td class="data"><?php print WEB_cell_dhcp; ?></td>
                <td class="data"><?php print WEB_cell_filter; ?></td>
                <td class="data"><?php print WEB_cell_shaper; ?></td>
                <td class="data"><?php print WEB_cell_perday . "/<br>" . WEB_cell_permonth . ", Mb"; ?></td>
                <td class="data"><?php print WEB_cell_temporary; ?></td>
                <td class="data"><?php print "<input type=\"submit\" onclick=\"return confirm('" . WEB_msg_apply_selected . "?')\" name=\"removeauth\" value=" . WEB_btn_remove . ">"; ?></td>
            </tr>

            <?php

            $flist = get_records($db_link, 'User_auth', "user_id=" . $id . " and deleted=0 ORDER BY $sort_table.$sort_field $order");
            if (!empty($flist)) {
                foreach ($flist as $row) {
                    $dhcp_str = '';
                    if ($row["dhcp_time"] !== '0000-00-00 00:00:00') {
                        if (!empty($row["dhcp_action"])) { $dhcp_str = FormatDateStr('Y.m.d H:m', $row["dhcp_time"]) . " (" . $row["dhcp_action"] . ")"; }
                    }
                    if ($row["last_found"] == '0000-00-00 00:00:00') {
                        $row["last_found"] = '';
                    }
                    print "<tr align=center>";
                    print "<td class=\"data\" style='padding:0'><input type=checkbox name=f_auth_id[] value=" . $row["id"] . " ></td>";

                    print "<td class=\"data\" align=left><a href=editauth.php?id=" . $row["id"] . ">" . $row["ip"] . "</a>";
                    if (!empty($row["arp_found"])) { print "<p class='timestamp'>".FormatDateStr('Y.m.d H:i', $row["arp_found"])."</p>"; }
                    print "</td>";

                    print "<td class=\"data\" >" . expand_mac($db_link, $row["mac"]);
                    if (!empty($row["last_found"])) { print "<p class='timestamp'>".FormatDateStr('Y.m.d H:i', $row["last_found"])."</p>"; }
                    print "</td>";

                    if (isset($row["dhcp_hostname"]) and strlen($row["dhcp_hostname"]) > 0) {
                        print "<td class=\"data\" >" . $row["comments"] . " [" . $row["dhcp_hostname"] . "]</td>";
                    } else {
                        print "<td class=\"data\" >" . $row["comments"] . "</td>";
                    }
                    $f_dns_type = 'A';
                    if ($row["dns_ptr_only"]) { $f_dns_type = 'ptr'; }
                    $f_dns_row = '';
                    if (!empty($row["dns_name"])) { $f_dns_row = $row["dns_name"]."<hr>".$f_dns_type; }
                    print "<td class=\"data\" >" . $f_dns_row . "</td>";
                    $ip_status = 1;
                    if ($row["blocked"] or !$row["enabled"]) {
                        $ip_status = 0;
                    }

                    print_td_qa($ip_status);

                    $cl = "data_green";
                    if (!$row["dhcp"]) { $cl = "data_red"; }
                    print "<td class=\"$cl\" >" . get_qa($row["dhcp"]);
                    if (!empty($row["dhcp_acl"])) { print "<p class='timestamp'>".$row["dhcp_acl"]. "</p>"; }
                    if (!empty($dhcp_str)) { print "<p class='timestamp'>".$dhcp_str. "</p>"; }
                    print "</td>";

                    print "<td class=\"data\" >" . get_group($db_link, $row["filter_group_id"]) . "</td>";
                    print "<td class=\"data\" >" . get_queue($db_link, $row["queue_id"]) . "</td>";
                    print "<td class=\"data\" >" . $row["day_quota"] . "/" . $row["month_quota"] . "</td>";

                    if ($row['dynamic']) { $cl = "data_red"; } else { $cl = "data_green"; }
                    print "<td class=\"$cl\" >". get_qa($row['dynamic']);
                    if ($row['dynamic'] and !empty($row["eof"])) { print "<p class='timestamp'>".FormatDateStr('Y.m.d H:i', $row["eof"])."</p>"; } else { print "&nbsp"; }
                    print "</td>";

                    print "<td class=\"data\" >";
                    if (!empty($row["created_by"])) { print $row["created_by"]; }  else { print "&nbsp"; }
                    print "</td>";
                    print "</tr>";
                }
            }
            ?>
        </table>
    </form>
    <?php
    require_once($_SERVER["DOCUMENT_ROOT"] . "/inc/footer.php");
    ?>