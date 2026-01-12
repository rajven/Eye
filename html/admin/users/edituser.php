<?php
require_once($_SERVER["DOCUMENT_ROOT"] . "/inc/auth.php");
require_once($_SERVER["DOCUMENT_ROOT"] . "/inc/languages/" . HTML_LANG . ".php");
require_once($_SERVER["DOCUMENT_ROOT"] . "/inc/idfilter.php");

$default_sort = 'ip_int';
require_once($_SERVER['DOCUMENT_ROOT'] . "/inc/sortfilter.php");

$msg_error = "";

$sSQL = "SELECT * FROM user_list WHERE id = ?";
$user_info = get_record_sql($db_link, $sSQL, [$id]);

if (empty($user_info)) {
    header("Location: /admin/");
    exit;
}

// === РЕДАКТИРОВАНИЕ ПОЛЬЗОВАТЕЛЯ ===============================================
if (getPOST("edituser") !== null) {
    $new = [];

    $f_ou = (int)getPOST("f_ou", null, 0);
    if ($f_ou > 0) {
        $new["ou_id"] = $f_ou;
    }

    $new["filter_group_id"] = (int)getPOST("f_filter", null, 0);
    $new["queue_id"]         = (int)getPOST("f_queue", null, 0);
    
    $user_name = trim(getPOST("f_login", null, $user_info['login']));
    if ($user_name !== '') {
        $new["login"] = $user_name;
    }
    
    $new["description"] = trim(getPOST("f_description", null, ''));

    // Настройки по OU
    if (get_const('default_user_ou_id') == ($new["ou_id"] ?? 0) || 
        get_const('default_hotspot_ou_id') == ($new["ou_id"] ?? 0)) {
        $new["enabled"]      = 0;
        $new["blocked"]      = 0;
        $new["day_quota"]    = 0;
        $new["month_quota"]  = 0;
        $new["permanent"]    = 0;
    } else {
        $new["enabled"]      = (int)getPOST("f_enabled", null, 0);
        $new["blocked"]      = (int)getPOST("f_blocked", null, 0);
        $new["day_quota"]    = (int)trim(getPOST("f_perday", null, 0));
        $new["month_quota"]  = (int)trim(getPOST("f_permonth", null, 0));
        $new["permanent"]    = (int)getPOST("f_permanent", null, 0);
    }

    $changes = get_diff_rec($db_link, "user_list", "id = ?", $new, 0, [$id]);
    if (!empty($changes)) {
        LOG_WARNING($db_link, "Changed user id: $id login: " . ($new["login"] ?? '') . ". \r\nApply: $changes");
    }
    update_record($db_link, "user_list", "id = ?", $new, [$id]);

    // Отключаем авторизацию, если пользователь выключен
    if (!$new["enabled"]) {
        update_records($db_link, 'user_auth', 'user_id = ?', ['enabled' => 0, 'changed' => 1], [$id]);
    }

    // Обновляем описание в user_auth
    if (!empty($new["description"])) {
        update_records($db_link, 'user_auth',
            "user_id = ? AND deleted = 0 AND (description IS NULL OR description = '' OR description = ?)",
            ['description' => $new["description"]],
            [$id, $user_info["description"]]
        );
    }

    // Обновление ou_id в user_auth
    update_records($db_link, 'user_auth', 'user_id = ? AND deleted = 0', ['ou_id' => $new["ou_id"]], [$id]);

    // Обновление device_name в devices
    update_record($db_link, 'devices', 'user_id = ?', ['device_name' => $new["login"]], [$id]);

    header("Location: " . $_SERVER["REQUEST_URI"]);
    exit;
}

// === АВТОПРАВИЛА ПО MAC =========================================================
if (getPOST("addMacRule") !== null) {
    $first_auth = get_records_sql($db_link, 
        "SELECT mac FROM user_auth WHERE user_id = ? AND deleted = 0 AND LENGTH(mac) > 0 ORDER BY id", 
        [$id]
    );
    foreach ($first_auth as $row) {
        if (!empty($row['mac'])) {
            add_auth_rule($db_link, $row['mac'], 2, $id);
        }
    }
    header("Location: " . $_SERVER["REQUEST_URI"]);
    exit;
}

if (getPOST("delMacRule") !== null) {
    delete_records($db_link, "auth_rules", "user_id = ? AND rule_type = 2", [$id]);
    LOG_INFO($db_link, "All autorules removed for id: $id login: " . $user_info["login"] . " by mac");
    header("Location: " . $_SERVER["REQUEST_URI"]);
    exit;
}

// === АВТОПРАВИЛА ПО IP ==========================================================
if (getPOST("addIPRule") !== null) {
    $first_auth = get_records_sql($db_link,
        "SELECT ip FROM user_auth WHERE user_id = ? AND deleted = 0 AND ip IS NOT NULL ORDER BY id",
        [$id]
    );
    foreach ($first_auth as $row) {
        if (!empty($row['ip'])) {
            add_auth_rule($db_link, $row['ip'], 1, $id);
        }
    }
    header("Location: " . $_SERVER["REQUEST_URI"]);
    exit;
}

if (getPOST("delIPRule") !== null) {
    delete_records($db_link, "auth_rules", "user_id = ? AND rule_type = 1", [$id]);
    LOG_INFO($db_link, "Removed all auto rules for id: $id login: " . $user_info["login"] . " by ip");
    header("Location: " . $_SERVER["REQUEST_URI"]);
    exit;
}

// === СОЗДАНИЕ УСТРОЙСТВА ========================================================
if (getPOST("showDevice") !== null) {
    $device = get_record_sql($db_link, "SELECT * FROM devices WHERE user_id = ?", [$id]);
    $auth   = get_record_sql($db_link, "SELECT * FROM user_auth WHERE user_id = ?", [$id]);

    if (empty($device) && !empty($auth)) {
        $new = [
            'user_id'          => $id,
            'device_name'      => $user_info['login'],
            'device_type'      => 5,
            'ip'               => $auth['ip'],
            'ip_int'           => $auth['ip_int'],
            'community'        => get_const('snmp_default_community'),
            'snmp_version'     => get_const('snmp_default_version'),
            'login'            => get_option($db_link, 28),
            'password'         => get_option($db_link, 29),
            'protocol'         => 0,
            'control_port'     => get_option($db_link, 30)
        ];

        $new_id = insert_record($db_link, "devices", $new);
        if (!empty($new_id)) {
            LOG_INFO($db_link, "Created device with id: $new_id for auth_id: $id");
            header("Location: /admin/devices/editdevice.php?id={$new_id}");
            exit;
        }
    }

    if (!empty($device['id'])) {
        header("Location: /admin/devices/editdevice.php?id=" . $device['id']);
    } else {
        header("Location: " . $_SERVER["REQUEST_URI"]);
    }
    exit;
}

// === ДОБАВЛЕНИЕ ЗАПИСИ АВТОРИЗАЦИИ ==============================================
if (getPOST("addauth") !== null) {
    $fip = normalizeIpAddress(substr(trim(getPOST("newip", null, '')), 0, 18));
    $fdescription = null;
    $fmac = trim(getPOST("newmac", null, ''));

    if (!empty($fmac)) {
        if (!checkValidMac($fmac)) {
            $fdescription = $fmac;
            $fmac = null;
        } else {
            $fmac = mac_dotted($fmac);
        }
    }

    if (!empty($fip)) {
        $ip_aton = ip2long($fip);
        $f_dhcp = 1;

        // Проверка MAC
        if (!empty($fmac)) {
            $mac_exists = find_mac_in_subnet($db_link, $fip, $fmac);
            if (!empty($mac_exists) && ($mac_exists['count'] ?? 0) >= 1 && !in_array($id, $mac_exists['users_id'] ?? [])) {
                $dup_info = get_record_sql($db_link, "SELECT * FROM user_list WHERE id = ?", [$mac_exists['users_id'][0] ?? 0]);
                $msg_error = "Mac already exists at another user in this subnet! Skip creating $fip [$fmac].<br>Old user id: " . ($dup_info['id'] ?? '') . " login: " . ($dup_info['login'] ?? '');
                $_SESSION[$page_url]['msg'] = $msg_error;
                LOG_ERROR($db_link, $msg_error);
                header("Location: " . $_SERVER["REQUEST_URI"]);
                exit;
            }

            // DHCP для вторичного IP
            if (!empty($mac_exists) && in_array($id, $mac_exists['users_id'] ?? [])) {
                $f_dhcp = 0;
            }
        }

        // Проверка дубликата IP
        $dup_ip_record = get_record_sql($db_link, "SELECT * FROM user_auth WHERE ip_int = ? AND user_id <> ? AND deleted = 0", [$ip_aton, $id]);
        if (!empty($dup_ip_record)) {
            $dup_info = get_record_sql($db_link, "SELECT * FROM user_list WHERE id = ?", [$dup_ip_record['user_id']]);
            $msg_error = "$fip already exists. Skip creating $fip [$fmac].<br>Old user id: " . $dup_info['id'] . " login: " . $dup_info['login'];
            $_SESSION[$page_url]['msg'] = $msg_error;
            LOG_ERROR($db_link, $msg_error);
            header("Location: " . $_SERVER["REQUEST_URI"]);
            exit;
        }

        $fid = new_auth($db_link, $fip, $fmac, $id);
        if (!empty($fid)) {
            $new_auth = ['dhcp' => $f_dhcp, 'created_by' => 'manual'];
            if (!empty($fdescription)) {
                $new_auth['description'] = $fdescription;
            }
            update_record($db_link, "user_auth", "id = ?", $new_auth, [$fid]);
            LOG_WARNING($db_link, "Add ip for login: " . $user_info["login"] . ": ip => $fip, mac => $fmac", $fid);
            header("Location: /admin/users/editauth.php?id=" . $fid);
            exit;
        }
    } else {
        $msg_error = "IP-address format error!";
        $_SESSION[$page_url]['msg'] = $msg_error;
    }

    header("Location: " . $_SERVER["REQUEST_URI"]);
    exit;
}

// === УДАЛЕНИЕ ЗАПИСЕЙ АВТОРИЗАЦИИ ==============================================
if (getPOST("removeauth") !== null) {
    $auth_id = getPOST("f_auth_id", null, []);
    if (!empty($auth_id) && is_array($auth_id)) {
        foreach ($auth_id as $val) {
            $val = trim($val);
            if ($val !== '') {
                delete_user_auth($db_link, (int)$val);
            }
        }
    }
    header("Location: " . $_SERVER["REQUEST_URI"]);
    exit;
}

// === СОЗДАНИЕ НОВОГО ПОЛЬЗОВАТЕЛЯ ИЗ ЗАПИСИ АВТОРИЗАЦИИ =========================
if (getPOST("new_user") !== null) {
    $auth_id = getPOST("f_auth_id", null, []);
    $save_traf = (int)get_option($db_link, 23);

    if (!empty($auth_id) && is_array($auth_id)) {
        foreach ($auth_id as $val) {
            $val = (int)$val;
            if ($val <= 0) continue;

            $auth_info = get_record_sql($db_link, "SELECT * FROM user_auth WHERE id = ?", [$val]);
            if (empty($auth_info)) continue;

            $ou_id = $user_info["ou_id"];
            $login = null;

            if (!empty($auth_info["dns_name"])) {
                $login = $auth_info["dns_name"];
            } elseif (!empty($auth_info["description"])) {
                $login = transliterate($auth_info["description"]);
            } elseif (!empty($auth_info["dhcp_hostname"])) {
                $login = $auth_info["dhcp_hostname"];
            } elseif (!empty($auth_info["mac"])) {
                $login = $auth_info["mac"];
            } else {
                $login = $auth_info["ip"];
            }

            $new_user = get_record_sql($db_link, "SELECT * FROM user_list WHERE LOWER(login) = LOWER(?) AND deleted = 0", [$login]);
            if (!empty($new_user)) {
                // Перенос записи авторизации
                $auth_update = [
                    'user_id' => $new_user["id"],
                    'ou_id'   => $new_user["ou_id"],
                    'save_traf' => $save_traf
                ];
                $auth_update = apply_auth_rule($db_link, $auth_update, $new_user["id"]);
                update_record($db_link, "user_auth", "id = ?", $auth_update, [$val]);
                LOG_WARNING($db_link, "ip from id: $val moved to another user user_id: " . $new_user["id"], $val);
            } else {
                $new_user_data = [
                    'login' => $login,
                    'ou_id' => $ou_id
                ];
                if (!empty($auth_info["description"])) {
                    $new_user_data["description"] = $auth_info["description"];
                } elseif (!empty($auth_info["dns_name"])) {
                    $new_user_data["description"] = $auth_info["dns_name"];
                } elseif (!empty($auth_info["dhcp_hostname"])) {
                    $new_user_data["description"] = $auth_info["dhcp_hostname"];
                }

                $new_user_data["enabled"] = $auth_info["enabled"];
                $l_id = insert_record($db_link, "user_list", $new_user_data);

                if (!empty($l_id)) {
                    $auth_update = ['user_id' => $l_id, 'save_traf' => $save_traf];
                    update_record($db_link, "user_auth", "id = ?", $auth_update, [$val]);
                    LOG_WARNING($db_link, "Create user from ip: login => $login. ip-record auth_id: $val moved to this user.", $val);
                }
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
                <td colspan=2><?php print WEB_cell_description; ?></td>
                <td colspan=2><?php print WEB_cell_ou; ?></td>
                <td ><?php print WEB_user_permanent; ?></td>
            </tr>
            <tr>
                <td colspan=2><input type="text" name="f_login" value="<?php print $user_info["login"]; ?>" size=25></td>
                <td colspan=2><input type="text" name="f_description" value="<?php print $user_info["description"]; ?>" size=25></td>
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
                <td colspan=2><?php print_filter_group_select($db_link, 'f_filter', $user_info["filter_group_id"]); ?></td>
                <td colspan=2><?php print_queue_select($db_link, 'f_queue', $user_info["queue_id"]); ?></td>
                <td colspan=3></td>
            </tr>
            <tr>
                <?php
                print "<td>";
                print_url(WEB_user_rule_list, "/admin/users/edit_rules.php?id=$id");
                print "</td>";
                $rule_count = get_count_records($db_link, "auth_rules", "user_id=?", [ $id ]);
                print "<td > Count: " . $rule_count . "</td>";
                $first_auth = get_record_sql($db_link, "SELECT id FROM user_auth WHERE user_id=? AND deleted=0 ORDER BY id", [ $id ]);
                if (!empty($first_auth)) {
                    //mac
                    $mac_rule_count = get_count_records($db_link, "auth_rules", "user_id=? AND rule_type=2", [ $id ]);
                    if (!empty($mac_rule_count)) {
                        print "<td><input type=\"submit\" name=\"delMacRule\" value=" . WEB_btn_mac_del . " ></td>";
                    } else {
                        print "<td><input type=\"submit\" name=\"addMacRule\" value=" . WEB_btn_mac_add . " ></td>";
                    }
                    //ip
                    $ip_rule_count = get_count_records($db_link, "auth_rules", "user_id=? AND rule_type=1", [ $id ]);
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
                                            print $user_info["ts"]; ?></td>
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

        $sort_table = 'user_auth';
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
                <td class="data"><?php print WEB_cell_description; ?></td>
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

            $flist = get_records($db_link, 'user_auth', "user_id=? and deleted=0 ORDER BY $sort_table.$sort_field $order", [ $id ]);
            if (!empty($flist)) {
                foreach ($flist as $row) {
                    $dhcp_str = '';
                    if ($row["dhcp_time"] !== '0000-00-00 00:00:00') {
                        if (!empty($row["dhcp_action"])) { $dhcp_str = FormatDateStr('Y.m.d H:m', $row["dhcp_time"]) . " (" . $row["dhcp_action"] . ")"; }
                    }
                    if ($row["last_found"] == '0000-00-00 00:00:00') {
                        $row["last_found"] = '';
                    }
                    if ($row["mac_found"] == '0000-00-00 00:00:00') {
                        $row["mac_found"] = '';
                    }
                    if ($row["arp_found"] == '0000-00-00 00:00:00') {
                        $row["arp_found"] = '';
                    }
                    print "<tr align=center>";
                    print "<td class=\"data\" style='padding:0'><input type=checkbox name=f_auth_id[] value=" . $row["id"] . " ></td>";

                    print "<td class=\"data\" align=left><a href=editauth.php?id=" . $row["id"] . ">" . $row["ip"] . "</a>";
                    if (!empty($row["arp_found"])) { print "<p class='ts'>".FormatDateStr('Y.m.d H:i', $row["arp_found"])."</p>"; }
                    print "</td>";

                    print "<td class=\"data\" >" . expand_mac($db_link, $row["mac"]);
                    if (!empty($row["mac_found"])) { print "<p class='ts'>".FormatDateStr('Y.m.d H:i', $row["mac_found"])."</p>"; }
                    print "</td>";

                    if (isset($row["dhcp_hostname"]) and strlen($row["dhcp_hostname"]) > 0) {
                        print "<td class=\"data\" >" . $row["description"] . " [" . $row["dhcp_hostname"] . "]</td>";
                    } else {
                        print "<td class=\"data\" >" . $row["description"] . "</td>";
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
                    if (!empty($row["dhcp_acl"]) or !empty($row["dhcp_option_set"])) {
                            print "<p class='ts'>";
                            if (!empty($row["dhcp_acl"])) { print $row["dhcp_acl"]; }
                            if (!empty($row["dhcp_acl"]) and !empty($row["dhcp_option_set"])) { print "&nbsp/&nbsp"; }
                            if (!empty($row["dhcp_option_set"])) { print $row["dhcp_option_set"]; }
                            print "</p>";
                            }
                    if (!empty($dhcp_str)) { print "<p class='ts'>".$dhcp_str. "</p>"; }
                    print "</td>";

                    print "<td class=\"data\" >" . get_group($db_link, $row["filter_group_id"]) . "</td>";
                    print "<td class=\"data\" >" . get_queue($db_link, $row["queue_id"]) . "</td>";
                    print "<td class=\"data\" >" . $row["day_quota"] . "/" . $row["month_quota"] . "</td>";

                    if ($row['dynamic']) { $cl = "data_red"; } else { $cl = "data_green"; }
                    print "<td class=\"$cl\" >". get_qa($row['dynamic']);
                    if ($row['dynamic'] and !empty($row["end_life"])) { print "<p class='ts'>".FormatDateStr('Y.m.d H:i', $row["end_life"])."</p>"; } else { print "&nbsp"; }
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