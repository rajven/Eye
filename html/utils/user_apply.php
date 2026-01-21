<?php
require_once($_SERVER['DOCUMENT_ROOT'] . "/inc/auth.php");
require_once($_SERVER['DOCUMENT_ROOT'] . "/inc/languages/" . HTML_LANG . ".php");

if (!defined("CONFIG")) die("Not defined");

$page_url = null;

if (getPOST("ApplyForAll", $page_url)) {

    // === Безопасное получение и приведение параметров через getPOST ===
    $auth_id = getPOST("fid", $page_url, []);

    $a_enabled = (int)getPOST("a_enabled", $page_url, 0);
    $a_dhcp = (int)getPOST("a_dhcp", $page_url, 0);
    $a_queue = (int)getPOST("a_queue_id", $page_url, 0);
    $a_group = (int)getPOST("a_group_id", $page_url, 0);
    $a_traf = (int)getPOST("a_traf", $page_url, 0);
    $a_day = (int)getPOST("a_day_q", $page_url, 0);
    $a_month = (int)getPOST("a_month_q", $page_url, 0);
    $a_ou_id = (int)getPOST("a_new_ou", $page_url, 0);
    $a_permanent = (int)getPOST("a_permanent", $page_url, 0);
    $a_bind_mac = (int)getPOST("a_bind_mac", $page_url, 0);
    $a_bind_ip = (int)getPOST("a_bind_ip", $page_url, 0);
    $a_create_netdev = (int)getPOST("a_create_netdev", $page_url, 0);
    $a_dhcp_acl = trim(getPOST("a_dhcp_acl", $page_url, ''));
    $a_dhcp_option_set = trim(getPOST("a_dhcp_option_set", $page_url, ''));

    $all_ok = true;

    foreach ($auth_id as $user_id_raw) {
        $user_id = (int)$user_id_raw;
        if (!$user_id) continue;

        $auth_updates = [];
        $user_updates = [];

        if (getPOST("e_enabled", $page_url) !== null) {
            $auth_updates['enabled'] = $a_enabled;
            $user_updates['enabled'] = $a_enabled;
        }
        if (getPOST("e_group_id", $page_url) !== null) {
            $auth_updates['filter_group_id'] = $a_group;
        }
        if (getPOST("e_queue_id", $page_url) !== null) {
            $auth_updates['queue_id'] = $a_queue;
        }
        if (getPOST("e_dhcp", $page_url) !== null) {
            $auth_updates['dhcp'] = $a_dhcp;
        }
        if (getPOST("e_dhcp_acl", $page_url) !== null) {
            $auth_updates['dhcp_acl'] = $a_dhcp_acl;
        }
        if (getPOST("e_dhcp_option_set", $page_url) !== null) {
            $auth_updates['dhcp_option_set'] = $a_dhcp_option_set;
        }
        if (getPOST("e_traf", $page_url) !== null) {
            $auth_updates['save_traf'] = $a_traf;
        }
        if (getPOST("e_day_q", $page_url) !== null) {
            $user_updates['day_quota'] = $a_day;
        }
        if (getPOST("e_month_q", $page_url) !== null) {
            $user_updates['month_quota'] = $a_month;
        }
        if (getPOST("e_new_ou", $page_url) !== null) {
            $user_updates['ou_id'] = $a_ou_id;
            $auth_updates['ou_id'] = $a_ou_id;
        }
        if (getPOST("e_permanent", $page_url) !== null) {
            $user_updates['permanent'] = $a_permanent;
        }

        // === Обновление user_list ===
        if (!empty($user_updates)) {
            $login_record = get_record($db_link, "user_list", "id = ?", [$user_id]);
            if ($login_record) {
                $msg .= " For all ip user id: " . $user_id . " login: " . ($login_record['login'] ?? '') . " set: ";
                $msg .= get_diff_rec($db_link, "user_list", "id = ?", $user_updates, 1, [$user_id]);
                $ret = update_record($db_link, "user_list", "id = ?", $user_updates, [$user_id]);
                if (!$ret) $all_ok = false;
            }
        }

        // === Получаем все активные auth записи пользователя ===
        $auth_list = get_records_sql($db_link,
            "SELECT id, mac, ip FROM user_auth WHERE deleted = 0 AND user_id = ?",
            [$user_id]
        );

        $b_mac = '';
        $b_ip = '';

        // === Обновляем каждую auth запись ===
        if (!empty($auth_list)) {
            foreach ($auth_list as $row) {
                if (empty($row['id'])) continue;
                
                if (empty($b_mac) && !empty($row['mac'])) $b_mac = $row['mac'];
                if (empty($b_ip) && !empty($row['ip'])) $b_ip = $row['ip'];

                if (!empty($auth_updates)) {
                    $ret = update_record($db_link, "user_auth", "id = ?", $auth_updates, [(int)$row['id']]);
                    if (!$ret) $all_ok = false;
                }
            }
        }

        // === Правило привязки MAC ===
        if (getPOST("e_bind_mac", $page_url) !== null) {
            if ($a_bind_mac && $b_mac) {
                $user_rule = get_record_sql($db_link,
                    "SELECT * FROM auth_rules WHERE user_id = ? AND rule_type = 2",
                    [$user_id]
                );
                $mac_rule = get_record_sql($db_link,
                    "SELECT * FROM auth_rules WHERE rule = ? AND rule_type = 2",
                    [$b_mac]
                );

                if (!$user_rule && !$mac_rule) {
                    insert_record($db_link, "auth_rules", [
                        'user_id' => $user_id,
                        'rule_type' => 2,
                        'rule' => $b_mac
                    ]);
                } else {
                    LOG_INFO($db_link, "Auto rule for user_id: $user_id and mac $b_mac already exists");
                }
            } else {
                delete_records($db_link, "auth_rules","user_id = ? AND rule_type = 2", [$user_id]);
            }
        }

        // === Правило привязки IP ===
        if (getPOST("e_bind_ip", $page_url) !== null) {
            if ($a_bind_ip && $b_ip) {
                $user_rule = get_record_sql($db_link,
                    "SELECT * FROM auth_rules WHERE user_id = ? AND rule_type = 1",
                    [$user_id]
                );
                $ip_rule = get_record_sql($db_link,
                    "SELECT * FROM auth_rules WHERE rule = ? AND rule_type = 1",
                    [$b_ip]
                );

                if (!$user_rule && !$ip_rule) {
                    insert_record($db_link, "auth_rules", [
                        'user_id' => $user_id,
                        'rule_type' => 1,
                        'rule' => $b_ip
                    ]);
                } else {
                    LOG_INFO($db_link, "Auto rule for user_id: $user_id and ip $b_ip already exists");
                }
            } else {
                delete_records($db_link, "auth_rules","user_id = ? AND rule_type = 1", [$user_id]);
            }
        }

        // === Создание сетевого устройства ===
        if (getPOST("e_create_netdev", $page_url) !== null && $a_create_netdev && $b_ip) {
            $existing_device = get_record_sql($db_link,
                "SELECT * FROM devices WHERE user_id = ?",
                [$user_id]
            );
            
            if (!$existing_device) {
                $latest_auth = get_record_sql($db_link,
                    "SELECT * FROM user_auth WHERE user_id = ? ORDER BY last_found DESC",
                    [$user_id]
                );
                
                if ($latest_auth) {
                    $new_device = [
                        'user_id' => $user_id,
                        'device_name' => $login_record['login'] ?? 'user_' . $user_id,
                        'device_type' => 5,
                        'ip' => $latest_auth['ip'],
                        'community' => get_const('snmp_default_community'),
                        'snmp_version' => get_const('snmp_default_version'),
                        'login' => get_option($db_link, 28),
                        'password' => get_option($db_link, 29),
                        'protocol' => 0,
                        'control_port' => get_option($db_link, 30)
                    ];
                    
                    $new_id = insert_record($db_link, "devices", $new_device);
                }
            }
        }
    }

    if ($all_ok) {
        print "Success!";
    } else {
        print "Fail!";
    }
}
?>
