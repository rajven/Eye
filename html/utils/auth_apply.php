<?php
require_once($_SERVER['DOCUMENT_ROOT'] . "/inc/auth.php");
require_once($_SERVER['DOCUMENT_ROOT'] . "/inc/languages/" . HTML_LANG . ".php");

if (!defined("CONFIG")) die("Not defined");

$page_url = null;

if (getPOST("ApplyForAll", $page_url)) {

    // Получаем массив ID авторизаций
    $auth_id = getPOST("fid", $page_url, []);

    // Получаем и валидируем все параметры через getPOST
    $a_ou_id = (int)getPOST("a_new_ou", $page_url, 0);
    $a_enabled = (int)getPOST("a_enabled", $page_url, 0);
    $a_dhcp = (int)getPOST("a_dhcp", $page_url, 0);
    $a_dhcp_acl = trim(getPOST("a_dhcp_acl", $page_url, ''));
    $a_dhcp_option_set = trim(getPOST("a_dhcp_option_set", $page_url, ''));
    $a_queue = (int)getPOST("a_queue_id", $page_url, 0);
    $a_group = (int)getPOST("a_group_id", $page_url, 0);
    $a_traf = (int)getPOST("a_traf", $page_url, 0);
    $a_bind_mac = (int)getPOST("a_bind_mac", $page_url, 0);
    $a_bind_ip = (int)getPOST("a_bind_ip", $page_url, 0);
    $n_enabled = (int)getPOST("n_enabled", $page_url, 0);
    $n_link = (int)getPOST("n_link", $page_url, 0);
    $n_handler = getPOST("n_handler", $page_url, '');

    $msg = "Massive User change!";
    LOG_WARNING($db_link, $msg);

    $all_ok = true;

    foreach ($auth_id as $val) {
        $id = (int)$val;
        if ($id <= 0) continue;

        // Получаем текущую авторизацию и пользователя
        $cur_auth = get_record_sql($db_link, "SELECT * FROM user_auth WHERE id = ?", [$id]);
        if (!$cur_auth) continue;

        $user_info = get_record_sql($db_link, "SELECT * FROM user_list WHERE id = ?", [(int)$cur_auth["user_id"]]);
        if (!$user_info) continue;

        // Формируем данные для обновления auth
        $auth_updates = [];

        if (getPOST("e_enabled", $page_url) !== null) {
            $auth_updates['enabled'] = (int)($user_info["enabled"] * $a_enabled);
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
        if (getPOST("e_nag_enabled", $page_url) !== null) {
            $auth_updates['nagios'] = $n_enabled;
        }
        if (getPOST("e_nag_link", $page_url) !== null) {
            $auth_updates['link_check'] = $n_link;
        }
        if (getPOST("e_nag_handler", $page_url) !== null) {
            $auth_updates['nagios_handler'] = $n_handler;
        }

        // Обновляем запись в user_auth
        if (!empty($auth_updates)) {
            $ret = update_record($db_link, "user_auth", "id = ?", $auth_updates, [$id]);
            if (!$ret) $all_ok = false;
        }

        // Изменение группы пользователя
        if (getPOST("e_new_ou", $page_url) !== null && $a_ou_id >0) {
            $user_updates = ['ou_id' => $a_ou_id];
            $auth_updates_for_all = ['ou_id' => $a_ou_id];

            $log_msg = "For user id: " . $cur_auth['user_id'] . " login: " . ($user_info['login'] ?? '') . " set: ou_id = " . $a_ou_id;
            LOG_INFO($db_link, $log_msg);

            // Обновляем user_list
            $ret = update_record($db_link, "user_list", "id = ?", $user_updates, [(int)$cur_auth['user_id']]);
            if (!$ret) $all_ok = false;

            // Обновляем все записи user_auth для этого пользователя
            $ret = update_records($db_link, "user_auth", "user_id = ?", $auth_updates_for_all, [(int)$cur_auth['user_id']]);
            if (!$ret) $all_ok = false;
        }

        // Правило привязки MAC
        if (getPOST("e_bind_mac", $page_url) !== null) {
            if ($cur_auth && !empty($cur_auth['mac'])) {
                if ($a_bind_mac) {
                    $user_rule = get_record_sql($db_link, "SELECT * FROM auth_rules WHERE user_id = ? AND rule_type = 2", [(int)$cur_auth['user_id']]);
                    $mac_rule = get_record_sql($db_link, "SELECT * FROM auth_rules WHERE rule = ? AND rule_type = 2", [$cur_auth['mac']]);
                    
                    if (!$user_rule && !$mac_rule) {
                        $new_rule = [
                            'user_id' => (int)$cur_auth['user_id'],
                            'rule_type' => 2,
                            'rule' => $cur_auth['mac']
                        ];
                        insert_record($db_link, "auth_rules", $new_rule);
                        LOG_INFO($db_link, "Created auto rule for user_id: " . $cur_auth['user_id'] . " and mac " . $cur_auth['mac']);
                    } else {
                        LOG_INFO($db_link, "Auto rule for user_id: " . $cur_auth['user_id'] . " and mac " . $cur_auth['mac'] . " already exists");
                    }
                } else {
                    run_sql($db_link, "DELETE FROM auth_rules WHERE user_id = ? AND rule_type = 2", [(int)$cur_auth['user_id']]);
                    LOG_INFO($db_link, "Remove auto rule for user_id: " . $cur_auth['user_id'] . " and mac " . $cur_auth['mac']);
                }
            } else {
                LOG_ERROR($db_link, "Auto rule for user_id: " . ($cur_auth['user_id'] ?? 'N/A') . " not created. Record not found or empty mac.");
            }
        }

        // Правило привязки IP
        if (getPOST("e_bind_ip", $page_url) !== null) {
            if ($cur_auth && !empty($cur_auth['ip'])) {
                if ($a_bind_ip) {
                    $user_rule = get_record_sql($db_link, "SELECT * FROM auth_rules WHERE user_id = ? AND rule_type = 1", [(int)$cur_auth['user_id']]);
                    $ip_rule = get_record_sql($db_link, "SELECT * FROM auth_rules WHERE rule = ? AND rule_type = 1", [$cur_auth['ip']]);
                    
                    if (!$user_rule && !$ip_rule) {
                        $new_rule = [
                            'user_id' => (int)$cur_auth['user_id'],
                            'rule_type' => 1,
                            'rule' => $cur_auth['ip']
                        ];
                        insert_record($db_link, "auth_rules", $new_rule);
                        LOG_INFO($db_link, "Created auto rule for user_id: " . $cur_auth['user_id'] . " and ip " . $cur_auth['ip']);
                    } else {
                        LOG_INFO($db_link, "Auto rule for user_id: " . $cur_auth['user_id'] . " and ip " . $cur_auth['ip'] . " already exists");
                    }
                } else {
                    run_sql($db_link, "DELETE FROM auth_rules WHERE user_id = ? AND rule_type = 1", [(int)$cur_auth['user_id']]);
                    LOG_INFO($db_link, "Remove auto rule for user_id: " . $cur_auth['user_id'] . " and ip " . $cur_auth['ip']);
                }
            } else {
                LOG_ERROR($db_link, "Auto rule for user_id: " . ($cur_auth['user_id'] ?? 'N/A') . " not created. Record not found or empty ip.");
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
