<?php
require_once($_SERVER['DOCUMENT_ROOT'] . "/inc/auth.php");
require_once($_SERVER['DOCUMENT_ROOT'] . "/inc/languages/" . HTML_LANG . ".php");
require_once($_SERVER['DOCUMENT_ROOT'] . "/inc/idfilter.php");

$msg_error = "";

$old_auth_info = get_record_sql($db_link, "SELECT * FROM user_auth WHERE id = ?", [$id]);
if (empty($old_auth_info)) {
    header("Location: /admin/");
    exit;
}

$parent_id = $old_auth_info['user_id'];
$user_info = get_record_sql($db_link, "SELECT * FROM user_list WHERE id = ?", [$parent_id]);
$parent_ou_id = $user_info['ou_id'];
$user_enabled = $user_info['enabled'];

// === РЕДАКТИРОВАНИЕ ЗАПИСИ АВТОРИЗАЦИИ ==========================================
if (getPOST("editauth") !== null && !$old_auth_info['deleted']) {
    $ip = normalizeIpAddress(substr(trim(getPOST("f_ip", null, '')), 0, 18));
    
    if (!empty($ip)) {
        $ip_aton = ip2long($ip);
        $mac = mac_dotted(getPOST("f_mac", null, ''));
        // Проверка MAC
        $mac_exists = find_mac_in_subnet($db_link, $ip, $mac);
        if (!empty($mac_exists) && ($mac_exists['count'] ?? 0) >= 1 && !in_array($parent_id, $mac_exists['users_id'] ?? [])) {
            $dup_info = get_record_sql($db_link, "SELECT * FROM user_list WHERE id = ?", [$mac_exists['users_id'][0] ?? 0]);
            $msg_error = "Mac already exists at another user in this subnet! Skip creating $ip [$mac].<br>Old user id: " . ($dup_info['id'] ?? '') . " login: " . ($dup_info['login'] ?? '');
            $_SESSION[$page_url]['msg'] = $msg_error;
            LOG_ERROR($db_link, $msg_error);
            header("Location: " . $_SERVER["REQUEST_URI"]);
            exit;
        }

        // DHCP для вторичного IP
        $f_dhcp = (int)getPOST("f_dhcp", null, 0);
        if (!empty($mac_exists) && in_array($parent_id, $mac_exists['users_id'] ?? [])) {
            if ($parent_id != ($mac_exists['users_id'][0] ?? null)) {
                $f_dhcp = 0;
            }
        }

        // Проверка дубликата IP
        $dup_ip_record = get_record_sql($db_link, "SELECT * FROM user_auth WHERE ip_int = ? AND id <> ? AND deleted = 0", [$ip_aton, $id]);
        if (!empty($dup_ip_record)) {
            $dup_info = get_record_sql($db_link, "SELECT * FROM user_list WHERE id = ?", [$dup_ip_record['user_id']]);
            $msg_error = "$ip already exists. Skip creating $ip [$mac].<br>Old user id: " . $dup_info['id'] . " login: " . $dup_info['login'];
            $_SESSION[$page_url]['msg'] = $msg_error;
            LOG_ERROR($db_link, $msg_error);
            header("Location: " . $_SERVER["REQUEST_URI"]);
            exit;
        }

        $new = [
            'ip'          => $ip,
            'ou_id'       => $parent_ou_id,
            'ip_int'      => $ip_aton,
            'mac'         => $mac,
            'description' => trim(getPOST("f_description", null, '')),
            'wikiname'    => trim(getPOST("f_wiki", null, ''))
        ];

        $f_dnsname = trim(getPOST("f_dns_name", null, ''));
        $f_dns_ptr_present = (getPOST("f_dns_ptr", null, null) !== null);
        if (empty($f_dnsname)) {
            $new['dns_ptr_only'] = 0;
            $new['dns_name'] = '';
            } else {
            $new['dns_ptr_only'] = $f_dns_ptr_present ? 1 : 0;
            }
        
        // Обновление IP в devices
        $device = get_record_sql($db_link, "SELECT * FROM devices WHERE ip_int = ?", [$old_auth_info['ip_int']]);
        if (!empty($device)) {
            update_record($db_link, "devices", "id = ?", [
                'ip'     => $ip,
                'ip_int' => $ip_aton
            ], [$device['id']]);
        }

        // Обработка DNS-имени и алиасов
        $dns_alias_count = get_count_records($db_link, 'user_auth_alias', 'auth_id = ?', [$id]);
        if (!empty($f_dnsname) && !$new['dns_ptr_only']) {
            $domain_zone = ltrim(get_option($db_link, 33), '.');
            $escaped_zone = preg_quote($domain_zone, '/');
            $f_dnsname = preg_replace('/\.' . $escaped_zone . '$/i', '', $f_dnsname);
            $f_dnsname = preg_replace('/\s+/', '-', $f_dnsname);

            if ($dns_alias_count > 0 && $f_dnsname !== $old_auth_info['dns_name']) {
                $f_dnsname = $old_auth_info['dns_name'];
            } else {
                $valid_dns = checkValidHostname($f_dnsname);
                $uniq_dns = checkUniqHostname($db_link, $id, $f_dnsname);
                if ($valid_dns && $uniq_dns) {
                    $new['dns_name'] = $f_dnsname;
                } else {
                    $msg_error = !$uniq_dns 
                        ? "DNS $f_dnsname already exists at: " . searchHostname($db_link, $id, $f_dnsname) . " Discard changes!"
                        : "DNS $f_dnsname not valid! Discard changes!";
                    $_SESSION[$page_url]['msg'] = $msg_error;
                    LOG_ERROR($db_link, $msg_error);
                    header("Location: " . $_SERVER["REQUEST_URI"]);
                    exit;
                }
            }
        }

        // Удаление алиасов при отключении DNS
        if (empty($f_dnsname) || $new['dns_ptr_only']) {
            $new['dns_name'] = '';
            $t_user_auth_alias = get_records($db_link, 'user_auth_alias', "auth_id = ? ORDER BY alias", [$id]);
            if (!empty($t_user_auth_alias)) {
                foreach ($t_user_auth_alias as $row) {
                    LOG_INFO($db_link, "Remove alias id: " . $row['id'] . " for auth_id: $id :: " . dump_record($db_link, 'user_auth_alias', 'id = ?', [$row['id']]));
                    delete_record($db_link, 'user_auth_alias', 'id = ?', [$row['id']]);
                }
            }
        }

        // PTR-only режим
        if ($old_auth_info['dns_ptr_only'] && !$new['dns_ptr_only']) {
            $new['dns_name'] = '';
        }
        if (!empty($f_dnsname) && $new['dns_ptr_only']) {
            $domain_zone = ltrim(get_option($db_link, 33), '.');
            $escaped_zone = preg_quote($domain_zone, '/');
            $f_dnsname = preg_replace('/\.' . $escaped_zone . '$/i', '', $f_dnsname);
            $f_dnsname = preg_replace('/\s+/', '-', $f_dnsname);
            $new['dns_name'] = $f_dnsname;
        }

        // Остальные поля
        $new['save_traf']         = (int)getPOST("f_save_traf", null, 0);
        $new['dhcp_acl']          = trim(getPOST("f_acl", null, ''));
        $new['dhcp_option_set']   = trim(getPOST("f_dhcp_option_set", null, ''));
        $new['dynamic']           = (int)(getPOST("f_dynamic", null, 0));
        if ($new['dynamic']) {
            $new['end_life'] = trim(getPOST("f_end_life"));
        }

        // Настройки по OU
        if (is_system_ou($db_link, $parent_ou_id)) {
            $new += [
                'nagios_handler'    => '',
                'enabled'           => 0,
                'link_check'        => 0,
                'nagios'            => 0,
                'blocked'           => 0,
                'day_quota'         => 0,
                'month_quota'       => 0,
                'queue_id'          => 0,
                'filter_group_id'   => 0
            ];
        } else {
            $new += [
                'nagios_handler'    => trim(getPOST("f_handler", null, '')),
                'enabled'           => (int)getPOST("f_enabled", null, 0),
                'link_check'        => (int)getPOST("f_link", null, 0),
                'nagios'            => (int)getPOST("f_nagios", null, 0),
                'dhcp'              => $f_dhcp,
                'blocked'           => (int)getPOST("f_blocked", null, 0),
                'day_quota'         => (int)getPOST("f_day_q", null, 0),
                'month_quota'       => (int)getPOST("f_month_q", null, 0),
                'queue_id'          => (int)getPOST("f_queue_id", null, 0),
                'filter_group_id'   => (int)getPOST("f_group_id", null, 0)
            ];
        }

        if ($new['day_quota']>0) {
            if ($new['month_quota'] <= $new['day_quota']) { $new['month_quota'] = $new['day_quota'] * 31; }
            }

        if ($new['nagios'] == 0) {
            $new['nagios_status'] = 'UP';
        }
        if (!$user_enabled) {
            $new['enabled'] = 0;
        }

        if (is_auth_bind_changed($db_link, $id, $ip, $mac)) {
            $new_id = copy_auth($db_link, $id, $new);
            if (!empty($new_id)) {
                header("Location: /admin/users/editauth.php?id=" . $new_id, true, 302);
            } else {
                header("Location: " . $_SERVER["REQUEST_URI"]);
            }
            exit;
        } else {
            update_record($db_link, "user_auth", "id = ?", $new, [$id]);
        }
    } else {
        $msg_error = "$msg_ip_error xxx.xxx.xxx.xxx";
        $_SESSION[$page_url]['msg'] = $msg_error;
    }
    header("Location: " . $_SERVER["REQUEST_URI"]);
    exit;
}

// === ПЕРЕМЕЩЕНИЕ ЗАПИСИ =========================================================
if (getPOST("moveauth") !== null && !$old_auth_info['deleted']) {
    $new_parent_id = (int)getPOST("f_new_parent", null, 0);
    $moved_auth = get_record_sql($db_link, "SELECT description FROM user_auth WHERE id = ?", [$id]);
    $changes = apply_auth_rule($db_link, $moved_auth, $new_parent_id);

    update_record($db_link, "user_auth", "id = ?", $changes, [$id]);

    // Удаляем старые правила
    delete_records($db_link, "auth_rules", "user_id = ? AND rule = ? AND rule_type = 2", [$old_auth_info["user_id"], $old_auth_info["mac"]]);
    delete_records($db_link, "auth_rules", "user_id = ? AND rule = ? AND rule_type = 1", [$old_auth_info["user_id"], $old_auth_info["ip"]]);

    header("Location: " . $_SERVER["REQUEST_URI"]);
    exit;
}

// === ВОССТАНОВЛЕНИЕ УДАЛЁННОЙ ЗАПИСИ ===========================================
if (getPOST("recovery") !== null && $old_auth_info['deleted']) {
    $ip = trim(getPOST("f_ip", null, ''));
    if (checkValidIp($ip)) {
        $ip_aton = ip2long($ip);
        $mac = mac_dotted(getPOST("f_mac", null, ''));
        
        // Проверка MAC
        $mac_exists = find_mac_in_subnet($db_link, $ip, $mac);
        if (!empty($mac_exists) && ($mac_exists['count'] ?? 0) >= 1 && !in_array($parent_id, $mac_exists['users_id'] ?? [])) {
            $dup_info = get_record_sql($db_link, "SELECT * FROM user_list WHERE id = ?", [$mac_exists['users_id'][0] ?? 0]);
            $msg_error = "Mac already exists at another user in this subnet! Skip creating $ip [$mac].<br>Old user id: " . ($dup_info['id'] ?? '') . " login: " . ($dup_info['login'] ?? '');
            $_SESSION[$page_url]['msg'] = $msg_error;
            LOG_ERROR($db_link, $msg_error);
            header("Location: " . $_SERVER["REQUEST_URI"]);
            exit;
        }

        // DHCP для вторичного IP
        $f_dhcp = (int)getPOST("f_dhcp", null, 0);
        if (!empty($mac_exists) && in_array($parent_id, $mac_exists['users_id'] ?? [])) {
            if ($parent_id != ($mac_exists['users_id'][0] ?? null)) {
                $f_dhcp = 0;
            }
        }

        // Проверка дубликата IP
        $dup_ip_record = get_record_sql($db_link, "SELECT * FROM user_auth WHERE ip_int = ? AND id <> ? AND deleted = 0", [$ip_aton, $id]);
        if (!empty($dup_ip_record)) {
            $dup_info = get_record_sql($db_link, "SELECT * FROM user_list WHERE id = ?", [$dup_ip_record['user_id']]);
            $msg_error = "$ip already exists. Skip creating $ip [$mac].<br>Old user id: " . $dup_info['id'] . " login: " . $dup_info['login'];
            $_SESSION[$page_url]['msg'] = $msg_error;
            LOG_ERROR($db_link, $msg_error);
            header("Location: " . $_SERVER["REQUEST_URI"]);
            exit;
        }

        $new = ['deleted' => 0, 'dynamic' => 0, 'dns_name' => ''];

        $old_parent = get_record_sql($db_link, "SELECT * FROM user_list WHERE id = ?", [$parent_id]);
        if (empty($old_parent)) {
            $new_user_info = get_new_user_id($db_link, $ip, $mac, null);
            $new_user_id = $new_user_info['user_id'] ?? null;
            if (empty($new_user_id)) {
                $new_user_id = new_user($db_link, $new_user_info);
            }
            $new['user_id'] = $new_user_id;
        }

        $new['description'] = $old_parent['description'] ?? '';

        // Настройки по OU
        if (get_const('default_user_ou_id') == $parent_ou_id || get_const('default_hotspot_ou_id') == $parent_ou_id) {
            $new += [
                'nagios_handler'    => '',
                'enabled'           => 0,
                'link_check'        => 0,
                'nagios'            => 0,
                'blocked'           => 0,
                'day_quota'         => 0,
                'month_quota'       => 0,
                'queue_id'          => 0,
                'filter_group_id'   => 0
            ];
        } else {
            $new += [
                'nagios_handler'    => trim(getPOST("f_handler", null, '')),
                'enabled'           => (int)getPOST("f_enabled", null, 0),
                'link_check'        => (int)getPOST("f_link", null, 0),
                'nagios'            => (int)getPOST("f_nagios", null, 0),
                'dhcp'              => (int)getPOST("f_dhcp", null, 0),
                'blocked'           => (int)getPOST("f_blocked", null, 0),
                'day_quota'         => (int)getPOST("f_day_q", null, 0),
                'month_quota'       => (int)getPOST("f_month_q", null, 0),
                'queue_id'          => (int)getPOST("f_queue_id", null, 0),
                'filter_group_id'   => (int)getPOST("f_group_id", null, 0)
            ];
        }

        $new = apply_auth_rule($db_link, $new, $new['user_id']);
        update_record($db_link, "user_auth", "id = ?", $new, [$id]);
    } else {
        $msg_error = "$msg_ip_error xxx.xxx.xxx.xxx/xx";
        $_SESSION[$page_url]['msg'] = $msg_error;
    }
    header("Location: " . $_SERVER["REQUEST_URI"]);
    exit;
}

unset($_POST);

require_once($_SERVER['DOCUMENT_ROOT'] . "/inc/header.php");

$sSQL = "SELECT * FROM user_auth WHERE id=?";
$auth_info = get_record_sql($db_link, $sSQL, [ $id ]);
$device = get_record_sql($db_link, "SELECT * FROM devices WHERE user_id=?", [ $auth_info['user_id'] ]);

$parent_name = get_login($db_link, $auth_info['user_id']);
if (empty($parent_name)) { $parent_name=$auth_info['user_id']; }

if (is_empty_datetime($auth_info['dhcp_time'])) {
    $dhcp_str = '';
} else {
    $dhcp_str = $auth_info['dhcp_time'] . " (" . $auth_info['dhcp_action'] . ")";
}
if (is_empty_datetime($auth_info['last_found'])) { $auth_info['last_found'] = ''; }
if (is_empty_datetime($auth_info['mac_found'])) { $auth_info['mac_found'] = ''; }
if (is_empty_datetime($auth_info['arp_found'])) { $auth_info['arp_found'] = ''; }

$now = DateTime::createFromFormat("Y-m-d H:i:s",date('Y-m-d H:i:s'));
$created = new DateTime($auth_info['ts']);

if (empty($auth_info['end_life']) || is_empty_datetime($auth_info['end_life'])) { 
    $now->modify('+1 day');
    $auth_info['end_life'] = $now->format('Y-m-d H:i:s');
    }

$is_system_ou = is_system_ou($db_link, $parent_ou_id);
$disabled_attr = $is_system_ou ? 'disabled' : '';

?>

<div id="cont">
    <?php if (!empty($_SESSION[$page_url]['msg'])): ?>
        <div id="msg"><?php echo $_SESSION[$page_url]['msg']; unset($_SESSION[$page_url]['msg']); ?></div>
    <?php endif; ?>

    <b><?php echo WEB_user_title; ?>&nbsp;
        <a href="/admin/users/edituser.php?id=<?php echo $auth_info['user_id']; ?>">
            <?php echo $parent_name; ?>
        </a>
    </b>

    <?php
    $alias_link = '';
    if (!empty($auth_info['dns_name']) && !$auth_info['dns_ptr_only']) {
        $alias_link = "/admin/users/edit_alias.php?id=" . $id;
    }
    if (empty($auth_info['created_by'])) {
        $auth_info['created_by'] = '-';
    }
    $f_dns_ptr = $auth_info['dns_ptr_only'] ? 'checked' : '';
    ?>

    <form name="def" action="editauth.php?id=<?php echo $id; ?>" method="post">
        <input type="hidden" name="id" value="<?php echo $id; ?>">

        <table class="data">
            <!-- 1. СЕТЕВЫЕ ДАННЫЕ -->
            <tr>
                <td><?php echo WEB_cell_ip; ?></td>
                <td><?php echo WEB_cell_mac; ?></td>
                <td><?php echo WEB_cell_description; ?></td>
                <td></td>
                <td><?php echo WEB_cell_traf; ?></td>
            </tr>
            <tr>
                <td><input type="text" name="f_ip" value="<?php echo htmlspecialchars($auth_info['ip']); ?>" pattern="^(\d{1,3}\.){3}\d{1,3}$" class='full-width'></td>
                <td><input type="text" name="f_mac" value="<?php echo htmlspecialchars($auth_info['mac']); ?>" pattern="^([0-9A-Fa-f]{2}[:-]){5}([0-9A-Fa-f]{2})$" class='full-width'></td>
                <td colspan=2><input type="text" name="f_description" value="<?php echo htmlspecialchars($auth_info['description']); ?>" class='full-width'></td>
                <td><?php print_qa_select('f_save_traf', $auth_info['save_traf'],$is_system_ou); ?></td>
            </tr>

            <!-- 2. ДОСТУП И ОГРАНИЧЕНИЯ -->
            <tr style="height: 10px; background: #f5f5f5;"><td colspan="5"></td></tr>
            <tr>
                <td><?php echo WEB_cell_enabled; ?></td>
                <td><?php echo WEB_cell_blocked; ?></td>
                <td><?php echo WEB_cell_filter; ?></td>
                <td><?php echo WEB_cell_shaper; ?></td>
                <td><?php echo WEB_cell_perday; ?> / <?php echo WEB_cell_permonth; ?></td>
            </tr>
            <tr>
                <td><?php print_qa_select('f_enabled', $auth_info['enabled'], $is_system_ou); ?></td>
                <td><?php print_qa_select('f_blocked', $auth_info['blocked'], $is_system_ou); ?></td>
                <td><?php print_filter_group_select($db_link, 'f_group_id', $auth_info['filter_group_id'], $is_system_ou); ?></td>
                <td><?php print_queue_select($db_link, 'f_queue_id', $auth_info['queue_id'],$is_system_ou); ?></td>
                <td>
                    <input type="text" name="f_day_q" value="<?php echo $auth_info['day_quota']; ?>" size="5" <?php print $disabled_attr; ?> > /
                    <input type="text" name="f_month_q" value="<?php echo $auth_info['month_quota']; ?>" size="5" <?php print $disabled_attr; ?> >
                </td>
            </tr>

            <!-- 3. DHCP -->
            <tr style="height: 10px; background: #f5f5f5;"><td colspan="5"></td></tr>
            <tr>
                <td><?php echo WEB_cell_dns_name; ?> &nbsp;|&nbsp; 
                <?php 
                if (!empty($alias_link)) {
                    print_url(WEB_cell_aliases, $alias_link, 'linkButton');
                    }
                ?>
                </td>
                <td><?php echo WEB_cell_dhcp; ?></td>
                <td><?php echo WEB_cell_acl; ?></td>
                <td><?php echo WEB_cell_option_set; ?></td>
                <td></td>
            </tr>
            <tr>
                <td style="white-space: nowrap;">
                    <input type="text" name="f_dns_name" size="14" value="<?php echo htmlspecialchars($auth_info['dns_name']); ?>" pattern="^([a-zA-Z0-9-]{1,63})(\.[a-zA-Z0-9-]{1,63})*\.?$"  <?php echo $disabled_attr; ?> class='full-width'>
                    <br>
                    <label>
                        <input type="checkbox" id="f_dns_ptr" name="f_dns_ptr" value="1" <?php echo $f_dns_ptr; echo " {$disabled_attr}"; ?>>
                        <?php echo WEB_cell_ptr_only; ?>
                    </label>
                </td>
                <td><?php print_qa_select('f_dhcp', $auth_info['dhcp'], $is_system_ou); ?></td>
                <td><?php print_dhcp_acl_list($db_link, "f_acl", $auth_info['dhcp_acl'], $is_system_ou); ?></td>
                <td><?php print_dhcp_option_set_list($db_link, "f_dhcp_option_set", $auth_info['dhcp_option_set'],$is_system_ou); ?></td>
                <td></td>
            </tr>

            <!-- 4. МОНИТОРИНГ -->
            <tr style="height: 10px; background: #f5f5f5;"><td colspan="5"></td></tr>
            <tr>
                <td width="200">
                    <?php
                    if (!empty($auth_info['wikiname'])) {
                        $wiki_url = rtrim(get_option($db_link, 60), '/');
                        if (preg_match('/127.0.0.1/', $wiki_url)) {
                            print WEB_cell_wikiname;
                        } else {
                            $wiki_web = rtrim(get_option($db_link, 63), '/');
                            $wiki_web = ltrim($wiki_web, '/');
                            $wiki_link = $wiki_url . '/' . $wiki_web . '/' . $auth_info['wikiname'];
                            print_url(WEB_cell_wikiname, $wiki_link, 'linkButton');
                        }
                    } else {
                        print WEB_cell_wikiname;
                    }
                    $dev_id = get_device_by_auth($db_link, $auth_info['user_id']);
                    if (isset($dev_id)) {
                        print "&nbsp|&nbsp";
                        print_url('Device', '/admin/devices/editdevice.php?id=' . $dev_id, 'linkButton');
                    }
                    ?>
                </td>
                <td><?php if (empty($device) || (!empty($device) && $device['device_type'] > 2)) echo WEB_cell_nagios; ?></td>
                <td><?php if (empty($device) || (!empty($device) && $device['device_type'] > 2)) echo WEB_cell_link; ?></td>
                <td><?php echo WEB_cell_nagios_handler; ?></td>
                <td></td>
            </tr>
            <tr>
                <td><input type="text" name="f_wiki" value="<?php echo htmlspecialchars($auth_info['wikiname']); ?>" <?php print $disabled_attr; ?> class='full-width'></td>
                <td><?php if (empty($device) || (!empty($device) && $device['device_type'] > 2)) print_qa_select('f_nagios', $auth_info['nagios'],$is_system_ou); ?></td>
                <td><?php if (empty($device) || (!empty($device) && $device['device_type'] > 2)) print_qa_select('f_link', $auth_info['link_check'],$is_system_ou); ?></td>
                <td colspan=2><input type="text" name="f_handler" value="<?php echo htmlspecialchars($auth_info['nagios_handler']); ?>" <?php print $disabled_attr; ?> class='full-width'></td>
            </tr>

            <!-- 5. ТИП ЗАПИСИ -->
            <tr style="height: 10px; background: #f5f5f5;"><td colspan="5"></td></tr>
            <tr>
                <td><?php echo WEB_cell_temporary; ?></td>
                <td colspan="4">
                    <?php print_qa_select('f_dynamic', $auth_info['dynamic']); ?>
                    <span id="end-life-container" style="<?php echo !$auth_info['dynamic'] ? 'display:none;' : ''; ?>">
                        &nbsp;—&nbsp;
                        <input type="datetime-local"
                               id="f_end_life"
                               name="f_end_life"
                               min="<?php echo $created->format('Y-m-d\TH:i:s'); ?>"
                               value="<?php echo htmlspecialchars($auth_info['end_life'] ?? ''); ?>"
                               step="1">
                    </span>
                </td>
            </tr>

            <!-- КНОПКИ -->
            <tr style="height: 10px; background: #f5f5f5;"><td colspan="5"></td></tr>
            <tr>
                <td colspan="3">
                    <input type="submit" name="moveauth" value="<?php echo WEB_btn_move; ?>">
                    <?php print_login_select($db_link, 'f_new_parent', $auth_info['user_id']); ?>
                </td>
                <?php if ($auth_info['deleted']): ?>
                    <td><font color="red"><?php echo WEB_deleted . ": " . $auth_info['changed_time']; ?></font></td>
                    <td align="right"><input type="submit" name="recovery" value="<?php echo WEB_btn_recover; ?>"></td>
                <?php else: ?>
                    <td></td>
                    <td align="right"><input type="submit" name="editauth" value="<?php echo WEB_btn_save; ?>"></td>
                <?php endif; ?>
            </tr>
        </table>

        <!-- ИНФОРМАЦИЯ О ЗАПИСИ -->
        <table class="data" style="margin-top: 20px;">
            <caption style="caption-side: top; font-weight: bold; margin-bottom: 5px;"><?php echo WEB_status; ?></caption>
            <tr>
                <td width="150"><?php echo WEB_cell_created_by; ?>:</td>
                <td width="200"><?php echo $auth_info['created_by']; ?></td>
                <td align="right"><a href="/admin/logs/authlog.php?auth_id=<?php echo $id; ?>"><?php echo WEB_log; ?></a></td>
                <td align="right"><?php print_url(WEB_report_by_day, "/admin/reports/authday.php?id=$id"); ?></td>
            </tr>
            <tr>
                <td><?php echo WEB_cell_created; ?>:</td>
                <td><?php echo $auth_info['ts']; ?></td>
                <td><?php echo WEB_cell_connection; ?>:</td>
                <td><?php echo get_connection($db_link, $id); ?></td>
            </tr>
            <tr>
                <td><?php echo WEB_cell_dhcp_hostname; ?>:</td>
                <td><?php echo $auth_info['dhcp_hostname']; ?></td>
                <td>Dhcp event:</td>
                <td><?php echo $dhcp_str; ?></td>
            </tr>
            <tr>
                <td><?php echo WEB_cell_arp_found; ?>:</td>
                <td><?php echo $auth_info['arp_found']; ?></td>
                <td><?php echo WEB_cell_mac_found; ?>:</td>
                <td><?php echo $auth_info['mac_found']; ?></td>
            </tr>
            <tr>
                <td><?php echo WEB_changed_time; ?>:</td>
                <td><?php echo $auth_info['changed_time']; ?></td>
                <td></td>
                <td></td>
            </tr>
        </table>

        <?php if ($msg_error): ?>
            <div id="msg"><b><?php echo $msg_error; ?></b></div>
        <?php endif; ?>
    </form>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const dynamicField = document.querySelector('[name="f_dynamic"]');
    const endLifeContainer = document.getElementById('end-life-container');
    if (dynamicField && endLifeContainer) {
        function toggleEndLife() {
            const isActive = dynamicField.type === 'checkbox' 
                ? dynamicField.checked 
                : dynamicField.value == '1';
            endLifeContainer.style.display = isActive ? '' : 'none';
        }
        dynamicField.addEventListener('change', toggleEndLife);
    }
});
</script>

<?php require_once($_SERVER['DOCUMENT_ROOT'] . "/inc/footer.php"); ?>
