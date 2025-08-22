<?php
require_once($_SERVER['DOCUMENT_ROOT'] . "/inc/auth.php");
require_once($_SERVER['DOCUMENT_ROOT'] . "/inc/languages/" . HTML_LANG . ".php");
require_once($_SERVER['DOCUMENT_ROOT'] . "/inc/idfilter.php");

$msg_error = "";

$old_auth_info = get_record_sql($db_link, "SELECT * FROM User_auth WHERE id=" . $id);
if (empty($old_auth_info)) {
    header("Location: /admin/");
    }

$parent_id = $old_auth_info['user_id'];

$user_info = get_record_sql($db_link, "SELECT * FROM User_list WHERE id=" . $parent_id);
$parent_ou_id = $user_info['ou_id'];
$user_enabled = $user_info['enabled'];

if (isset($_POST["editauth"]) and !$old_auth_info['deleted']) {
    $ip = normalizeIpAddress(substr(trim($_POST["f_ip"]), 0, 18));
    if (!empty($ip)) {
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
        $new['WikiName'] = $_POST["f_wiki"];
        $f_dnsname = trim($_POST["f_dns_name"]);
        $new['dns_ptr_only']=0;
        if (isset($_POST["f_dns_ptr"]) or !empty($f_dns_name)) { $new['dns_ptr_only']=1; }

        //update device managment ip
        $device = get_record_sql($db_link,"SELECT * FROM devices WHERE ip_int=".$old_auth_info['ip_int']);
        if (!empty($device)) {
            $dev['ip'] = $ip;
            $dev['ip_int']=$ip_aton;
            update_record($db_link,"devices","id=".$device['id'],$dev);
            }

        $dns_alias_count = get_count_records($db_link,'User_auth_alias','auth_id='.$id);
        if (!empty($f_dnsname) and !$new['dns_ptr_only']) {
            $domain_zone = get_option($db_link, 33);
            $domain_zone = ltrim($domain_zone, '.');
            $f_dnsname = preg_replace('/\.' . str_replace('.', '\.', $domain_zone) . '$/', '', $f_dnsname);
//            $f_dnsname = preg_replace('/\.$/','',$f_dnsname);
            $f_dnsname = preg_replace('/\s+/','-',$f_dnsname);
//            $f_dnsname = preg_replace('/\./','-',$f_dnsname);
            //disable change dns name when exists aliases
            if ($dns_alias_count >0 and $f_dnsname !== $old_auth_info['dns_name']) {
                $f_dnsname =  $old_auth_info['dns_name'];
                } else {
                $valid_dns = checkValidHostname($f_dnsname);
                $uniq_dns = checkUniqHostname($db_link,$id,$f_dnsname);
                if ($valid_dns and $uniq_dns) {
                        $new['dns_name'] = $f_dnsname;
                        } else {
                        if (!$uniq_dns) {
                            $msg_error = "DNS $f_dnsname already exists at: ".searchHostname($db_link,$id,$f_dnsname)." Discard changes!";
                            } else {
                            $msg_error = "DNS $f_dnsname not valid! Discard changes!";
                            }
                        $_SESSION[$page_url]['msg'] = $msg_error;
                        LOG_ERROR($db_link, $msg_error);
                        header("Location: " . $_SERVER["REQUEST_URI"]);
                        exit;
                        }
                }
            }

        if (empty($f_dnsname) or $new['dns_ptr_only']) {
            //remove all dns aliases
            $new['dns_name'] = '';
            $t_User_auth_alias = get_records($db_link,'User_auth_alias',"auth_id=$id ORDER BY alias");
            if (!empty($t_User_auth_alias)) {
                foreach ( $t_User_auth_alias as $row ) {
                    LOG_INFO($db_link, "Remove alias id: ".$row['id']." for auth_id: $id :: ".dump_record($db_link,'User_auth_alias','id='.$row['id']));
                    delete_record($db_link,'User_auth_alias','id='.$row['id']);
                    }
                }
            }

        if ($old_auth_info['dns_ptr_only'] and !$new['dns_ptr_only']) {
            $new['dns_name'] = ''; 
            }

        if (!empty($f_dnsname) and $new['dns_ptr_only']) {
            $domain_zone = get_option($db_link, 33);
            $domain_zone = ltrim($domain_zone, '.');
            $f_dnsname = preg_replace('/\.' . str_replace('.', '\.', $domain_zone) . '$/', '', $f_dnsname);
//            $f_dnsname = preg_replace('/\.$/','',$f_dnsname);
            $f_dnsname = preg_replace('/\s+/','-',$f_dnsname);
//            $f_dnsname = preg_replace('/\./','-',$f_dnsname);
            $new['dns_name'] = $f_dnsname;
            }

        $new['save_traf'] = $_POST["f_save_traf"] * 1;
        $new['dhcp_acl'] = trim($_POST["f_acl"]);
        $new['dhcp_option_set'] = trim($_POST["f_dhcp_option_set"]);
        $new['dynamic'] = trim($_POST["f_dynamic"]);
        if ($new['dynamic']) { $new['eof'] =  trim($_POST["f_eof"]); }
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
            $new['enabled'] = get_int($_POST["f_enabled"]);
            $new['link_check'] = get_int($_POST["f_link"]);
            $new['nagios'] = get_int($_POST["f_nagios"]);
            $new['dhcp'] = $f_dhcp;
            $new['blocked'] = get_int($_POST["f_blocked"]);
            $new['day_quota'] = get_int($_POST["f_day_q"]);
            $new['month_quota'] = get_int($_POST["f_month_q"]);
            $new['queue_id'] = get_int($_POST["f_queue_id"]);
            $new['filter_group_id'] = get_int($_POST["f_group_id"]);
        }
        if ($new['nagios'] == 0) {
            $new['nagios_status'] = 'UP';
            }
        if (!$user_enabled) { $new['enabled']=0; }
        $changes = get_diff_rec($db_link, "User_auth", "id='$id'", $new, 0);
        if (!empty($changes)) {
            LOG_WARNING($db_link, "Changed record for $ip! Log: " . $changes, $id);
            }
        if (is_auth_bind_changed($db_link, $id, $ip, $mac)) {
            $new_id = copy_auth($db_link, $id, $new);
            if (!empty($new_id)) {
                header("Location: /admin/users/editauth.php?id=" . $new_id, TRUE, 302);
                } else {
                header("Location: " . $_SERVER["REQUEST_URI"]);
                }
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
    $moved_auth = get_record_sql($db_link,"SELECT comments FROM User_auth WHERE id=".$id);
    $changes = apply_auth_rule($db_link, $moved_auth, $new_parent_id);
    update_record($db_link, "User_auth", "id='$id'", $changes);
    LOG_WARNING($db_link, "IP-address moved to another user! Applyed: " . get_rec_str($changes), $id);
    run_sql($db_link,"DELETE FROM auth_rules WHERE user_id=".$old_auth_info["user_id"]." AND rule='".$old_auth_info["mac"]."' AND type=2");
    run_sql($db_link,"DELETE FROM auth_rules WHERE user_id=".$old_auth_info["user_id"]." AND rule='".$old_auth_info["ip"]."' AND type=1");
    LOG_INFO($db_link,"Autorules removed for user_id: ".$old_auth_info["user_id"]." login: ".$user_info["login"]." by mac and ip");
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
        $new['dynamic'] = 0;
        $new['dns_name']='';

        $parent_id = $old_auth_info['user_id'];

        $old_parent = get_record_sql($db_link, "SELECT * FROM User_list WHERE id=".$parent_id);
        if (empty($old_parent)) {
            $new_user_info = get_new_user_id($db_link, $ip, $mac, NULL);
            if ($new_user_info['user_id']) { $new_user_id = $new_user_info['user_id']; }
            if (empty($new_user_id)) { $new_user_id = new_user($db_link, $new_user_info); }
            $new['user_id'] = $new_user_id;
            }

        //save comments
        $new['comments']=$old_parent['comments'];

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
            $new['enabled'] = get_int($_POST["f_enabled"]);
            $new['link_check'] = get_int($_POST["f_link"]);
            $new['nagios'] = get_int($_POST["f_nagios"]);
            $new['dhcp'] = get_int($_POST["f_dhcp"]);
            $new['blocked'] = get_int($_POST["f_blocked"]);
            $new['day_quota'] = get_int($_POST["f_day_q"]);
            $new['month_quota'] = get_int($_POST["f_month_q"]);
            $new['queue_id'] = get_int($_POST["f_queue_id"]);
            $new['filter_group_id'] = get_int($_POST["f_group_id"]);
        }
        $changes = get_diff_rec($db_link, "User_auth", "id='$id'", $new, 0);
        if (!empty($changes)) {
            LOG_WARNING($db_link, "Recovered ip-address. Applyed: $changes", $id);
        }
        $new = apply_auth_rule($db_link, $new, $new['user_id']);
        update_record($db_link, "User_auth", "id='$id'", $new);
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
if (empty($parent_name)) { $parent_name=$auth_info['user_id']; }

if ($auth_info['dhcp_time'] == '0000-00-00 00:00:00') {
    $dhcp_str = '';
} else {
    $dhcp_str = $auth_info['dhcp_time'] . " (" . $auth_info['dhcp_action'] . ")";
}
if ($auth_info['last_found'] == '0000-00-00 00:00:00') { $auth_info['last_found'] = ''; }

if ($auth_info['arp_found'] == '0000-00-00 00:00:00') { $auth_info['arp_found'] = ''; }

$now = DateTime::createFromFormat("Y-m-d H:i:s",date('Y-m-d H:i:s'));
$created = DateTime::createFromFormat("Y-m-d H:i:s",$auth_info['timestamp']);

if (empty($auth_info['eof']) or $auth_info['eof'] == '0000-00-00 00:00:00') { 
    $now->modify('+1 day');
    $auth_info['eof'] = $now->format('Y-m-d H:i:s');
    }

?>


<div id="cont">
    <?php
    if (!empty($_SESSION[$page_url]['msg'])) {
        print '<div id="msg">' . $_SESSION[$page_url]['msg'] . '</div>';
        unset($_SESSION[$page_url]['msg']);
    }
    print "<b>" . WEB_user_title . "&nbsp<a href=/admin/users/edituser.php?id=" . $auth_info['user_id'] . ">" . $parent_name . "</a> </b>";

    $alias_link = '';
    if (!empty($auth_info['dns_name']) and !$auth_info['dns_ptr_only']) { $alias_link="/admin/users/edit_alias.php?id=".$id; }
    if (empty($auth_info['created_by'])) { $auth_info['created_by'] = '-'; }
    $f_dns_ptr = '';
    if ($auth_info['dns_ptr_only']) { $f_dns_ptr = 'checked'; }
    ?>

    <form name="def" action="editauth.php?id=<?php echo $id; ?>" method="post">
        <input type="hidden" name="id" value=<?php echo $id; ?>>
        <table class="data">
            <tr>
                <td width=230><?php print WEB_cell_dns_name . " &nbsp | &nbsp "; print_url(WEB_cell_aliases, $alias_link); ?></td>
                <td width=200><?php print WEB_cell_comment; ?></td>
                <td width=70><?php print WEB_cell_enabled; ?></td>
                <td><?php print WEB_cell_traf; ?></td>
                <td></td>
            </tr>
            <tr>
                <td><input type="text" name="f_dns_name" size="14"  value="<?php echo $auth_info['dns_name']; ?>" pattern="^([a-zA-Z0-9-]{1,63})(\.[a-zA-Z0-9-]{1,63})*\.?$">
                    <input type="checkbox" id="f_dns_ptr" name="f_dns_ptr" value="1" <?php echo $f_dns_ptr; ?>> &nbsp ptr
                </td>
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
                <td><?php print WEB_cell_option_set; ?></td>
                <td></td>
            <tr>
                <td><input type="text" name="f_ip" value="<?php echo $auth_info['ip']; ?>" pattern="^(([0-9]|[1-9][0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5])[\.ю]){3}([0-9]|[1-9][0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5])$"></td>
                <td><input type="text" name="f_mac" value="<?php echo $auth_info['mac']; ?>" pattern="^(([0-9A-Fa-f]{2}[\.\:\-]){5}[0-9A-Fa-f]{2}|([0-9a-fA-F]{4}[\.\-][0-9a-fA-F]{4}[\.\-][0-9a-fA-F]{4})|[0-9A-Fa-f]{12})$"></td>
                <td><?php print_qa_select('f_dhcp', $auth_info['dhcp']); ?></td>
                <td><?php print_dhcp_acl_list($db_link,"f_acl",$auth_info['dhcp_acl']); ?></td>
                <td><?php print_dhcp_option_set_list($db_link,"f_dhcp_option_set",$auth_info['dhcp_option_set']); ?></td>
            </tr>
            <tr>
                <td><?php print WEB_cell_filter; ?></td>
                <td><?php print WEB_cell_shaper; ?></td>
                <td><?php print WEB_cell_blocked; ?></td>
                <td><?php print WEB_cell_perday; ?></td>
                <td><?php print WEB_cell_permonth; ?></td>
            </tr>
            <tr>
                <td><?php print_filter_group_select($db_link, 'f_group_id', $auth_info['filter_group_id']); ?> </td>
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
                    } ?>
                </td>
                <td><?php if (empty($device) or (!empty($device) and $device['device_type'] > 2)) {
                        print_qa_select('f_link', $auth_info['link_check']);
                    } ?>
                </td>
                <td></td>
            </tr>
            <tr>
                <td><?php print WEB_cell_temporary; ?></td>
                <td><?php print WEB_cell_eof; ?></td>
                <td></td>
                <td></td>
                <td></td>
            </tr>
            <tr>
                <td><?php print_qa_select('f_dynamic',$auth_info['dynamic']); ?></td>
                <td><input type="datetime-local" id="f_eof" name="f_eof" min="<?php print $created->format('Y-m-d H:i:s'); ?>" value="<?php print $auth_info['eof']; ?>" 
                <?php if (!$auth_info['dynamic']) { print "disabled"; } ?>
                step=1 ></td>
                <td></td>
                <td></td>
                <td></td>
            </tr>
            <tr>
                <td colspan=3><input type="submit" name="moveauth" value=<?php print WEB_btn_move; ?>><?php print_login_select($db_link, 'f_new_parent', $auth_info['user_id']); ?></td>
                <?php
                if ($auth_info['deleted']) {
                    print "<td ><font color=red>" . WEB_deleted . ": " . $auth_info['changed_time'] . "</font></td>";
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
                <td class="data" colspan=2><?php echo WEB_status . ":"; ?></td>
                <td align=right ><a href=/admin/logs/authlog.php?auth_id=<?php print $id; ?>><?php print WEB_log; ?></a></td>
                <td align=right ><?php print_url(WEB_report_by_day, "/admin/reports/authday.php?id=$id"); ?></td>
            </tr>
            <tr>
                <td ><?php echo WEB_cell_created_by . ":"; ?></td>
                <td class="data" ></td>
                <td class="data" colspan=2 align=right><?php echo $auth_info['created_by']; ?></td>
            </tr>
            <tr>
                <td><?php print WEB_cell_created; ?></td>
                <td class="data" align=right><?php print $auth_info['timestamp']; ?></td>
                <td><?php print WEB_cell_connection . ": "; ?></td>
                <td class="data" align=right><?php print get_connection($db_link, $id) ; ?></td>
            </tr>
            <tr>
                <td ><?php print WEB_cell_dhcp_hostname.":"; ?></td>
                <td class="data"><?php print $auth_info['dhcp_hostname']; ?></td>
                <td ><?php print "Dhcp event: "; ?></td>
                <td class="data" align=right><?php print $dhcp_str; ?></td>
            </tr>
            <tr>
                <td ><?php print WEB_cell_arp_found . ": "; ?></td>
                <td class="data" align=right><?php print $auth_info['arp_found'] ; ?></td>
                <td ><?php print WEB_cell_last_found . ": "; ?></td>
                <td class="data" align=right><?php print $auth_info['last_found'] ; ?></td>
            </tr>
            <tr>
            </tr>
        </table>
        <?php
        if ($msg_error) {
            print "<div id='msg'><b>$msg_error</b></div><br>\n";
        }
        ?>


</form>
<br>


<script>
document.getElementById('f_dynamic').addEventListener('change', function(event) {
  const selectValue = this.value;
  const inputField = document.getElementById('f_eof');
  if (selectValue === '1') {
    inputField.disabled = false;
  } else {
    inputField.disabled = true;
  }
});
</script>

<?php require_once($_SERVER['DOCUMENT_ROOT'] . "/inc/footer.simple.php"); ?>
