<?php
require_once($_SERVER['DOCUMENT_ROOT'] . "/inc/auth.php");
require_once($_SERVER['DOCUMENT_ROOT'] . "/inc/languages/" . HTML_LANG . ".php");
require_once($_SERVER['DOCUMENT_ROOT'] . "/inc/mass.php");

if (!defined("CONFIG")) die("Not defined");

$page_url = null;

// the parameters of the addresses is a mass action of the list, and the right for it is looked up before the first
// record is touched: the token of the csrf proves who sent the request, it says nothing
// about what that reader may do
if (!acl_can_edit()) {
    acl_denied($db_link, WEB_title_users_ips);
}

$all_ok = true;

// the request of a mass operation must carry both its mark and the selection: a
// dialog which did not meet the script of the interface brought nothing at all, and the
// answer came back green with 'Success!' while not a single record was touched
if (getPOST("ApplyForAll", $page_url) === null) {
    print '<div class="result err">' . WEB_mass_no_action . '</div>';
    exit;
}

if (empty((array) getPOST('fid', $page_url, []))) {
    print '<div class="result err">' . WEB_mass_nothing_selected . '</div>';
    exit;
}

// the same rule as the endpoint of the devices: an operation of the dialog which marked
// nothing changed nothing, and the answer must not be green: the screen would say Done
$marks = ['e_new_ou', 'e_enabled', 'e_group_id', 'e_queue_id', 'e_dhcp', 'e_dhcp_acl',
          'e_dhcp_option_set', 'e_traf', 'e_bind_mac', 'e_bind_ip', 'e_create_netdev'];
if (empty(mass_marks_asked($marks, $page_url))) {
    print '<div class="result err">' . WEB_mass_no_marks . '</div>';
    exit;
}

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
    $a_new_dev_type = (int)getPOST("a_new_dev_type", $page_url, 5);
    $a_new_dev_model_id = (int)getPOST("a_new_dev_model_id", $page_url, 0);

    // the creation of a device is asked as a whole: the model is the half of the reason why
    // the device is created at all, and the empty choice of the list - its first item which
    // means "все" and carries a negative id - is not a model. the request is refused before
    // the first record is touched, so the operator does not get a part of his addresses with
    // a device and a part without any
    if (getPOST("e_create_netdev", $page_url) !== null) {
        $chosen_model = get_record_sql($db_link, "SELECT id FROM device_models WHERE id = ?", [$a_new_dev_model_id]);
        if (!$chosen_model) {
            LOG_ERROR($db_link, "Mass creation of the devices refused: the model of id $a_new_dev_model_id is not in the base");
            print '<div class="result err">' . WEB_mass_no_model . '</div>';
            exit;
        }
        // the type of the device is a row of the dictionary, and devices.device_type has no
        // foreign key which would say it: an answer outside the list is asked for here
        $chosen_type = get_record_sql($db_link, "SELECT id FROM device_types WHERE id = ?", [$a_new_dev_type]);
        if (!$chosen_type) {
            LOG_ERROR($db_link, "Mass creation of the devices refused: the type of id $a_new_dev_type is not in the base");
            print '<div class="result err">' . WEB_mass_no_dev_type . '</div>';
            exit;
        }
    }

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
        // Обновляем запись в user_auth
        if (!empty($auth_updates)) {
            $ret = update_record($db_link, "user_auth", "id = ?", $auth_updates, [$id]);
            if (!$ret) $all_ok = false;
        }

        // Изменение группы пользователя
        if (getPOST("e_new_ou", $page_url) !== null && $a_ou_id >0) {
            $user_updates = ['ou_id' => $a_ou_id];
            $auth_updates_for_all = ['ou_id' => $a_ou_id];

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
                    } else {
                        LOG_INFO($db_link, "Auto rule for user_id: " . $cur_auth['user_id'] . " and mac " . $cur_auth['mac'] . " already exists");
                    }
                } else {
                    delete_records($db_link, "auth_rules", "user_id = ? AND rule_type = 2", [(int)$cur_auth['user_id']]);
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
                    } else {
                        LOG_INFO($db_link, "Auto rule for user_id: " . $cur_auth['user_id'] . " and ip " . $cur_auth['ip'] . " already exists");
                    }
                } else {
                    delete_records($db_link, "auth_rules", "user_id = ? AND rule_type = 1", [(int)$cur_auth['user_id']]);
                }
            } else {
                LOG_ERROR($db_link, "Auto rule for user_id: " . ($cur_auth['user_id'] ?? 'N/A') . " not created. Record not found or empty ip.");
            }
        }

        // === Создание устройства по выбранному адресу ===
        // a device of eye is the piece of equipment which answers on this address: eye
        // polls it, draws its ports and knows its model. the type and the model come from
        // the dialog, the vendor is taken from the model and not from the words of the
        // operator, because a model never lies about who made it
        if (getPOST("e_create_netdev", $page_url) !== null) {
            $b_ip = trim((string)($cur_auth['ip'] ?? ''));
            if ($b_ip === '') {
                LOG_ERROR($db_link, "Device for the address id $id not created: the record carries no ip");
                $all_ok = false;
                continue;
            }
            if (!filter_var($b_ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                // the address of a device is kept as text of fifteen signs and as a number
                // made by ip2long, and both of them are the shape of the fourth version:
                // a row of the sixth version is not created, the operator sees why in the log
                LOG_INFO($db_link, "Device for the address $b_ip not created: eye keys a device by an address of the fourth version");
                continue;
            }

            // the address which already owns a device is skipped: the operator asked to
            // create, not to rewrite the equipment of another hand, and a second row on one
            // ip would make eye ask the same device twice
            $device_here = get_record_sql($db_link, "SELECT id, device_name FROM devices WHERE ip = ?", [$b_ip]);
            if ($device_here) {
                LOG_INFO($db_link, "Device for the ip $b_ip already exists (id " . $device_here['id'] . "), skipped");
                continue;
            }

            // the name of a new device is the most living sign of the address which the base
            // knows: the name from the dns, then the name which dhcp heard, then the address
            $device_name = $b_ip;
            foreach (['dns_name', 'dhcp_hostname', 'description'] as $field) {
                if (!empty($cur_auth[$field])) {
                    $device_name = trim((string)$cur_auth[$field]);
                    break;
                }
            }

            $new_device = [
                'user_id'         => (int)$cur_auth['user_id'],
                'device_name'     => substr($device_name, 0, 50),
                'device_type'     => $a_new_dev_type,
                'device_model_id' => $a_new_dev_model_id,
                'ip'              => $b_ip,
                'ip_int'          => ip2long($b_ip),
                'snmp_version'    => get_const('snmp_default_version'),
                'community'       => get_const('snmp_default_community'),
            ];
            // the login, the password and the port of the control are not written here at
            // all: the columns keep their own defaults, and the access of the equipment is
            // set in the form of the device, where its value is asked from the operator and
            // not copied from the options of the routers

            // vendor_id of the table has no default which would suit every model, so an
            // unknown vendor is simply not written: the column keeps its own default of
            // one instead of an empty string which a strict base would refuse
            $model_vendor = (int)get_device_model_vendor($db_link, $a_new_dev_model_id);
            if ($model_vendor > 0) {
                $new_device['vendor_id'] = $model_vendor;
            }

            if (!insert_record($db_link, "devices", $new_device)) {
                LOG_ERROR($db_link, "Device for the ip $b_ip not created");
                $all_ok = false;
                continue;
            }
            LOG_INFO($db_link, "Device for the ip $b_ip created, model id $a_new_dev_model_id, type id $a_new_dev_type");
        }
    }
}

$message = $all_ok ? WEB_mass_success : WEB_mass_fail;
/* a class instead of inline styles: the block used to stay green even on Fail! */
print '<div class="result ' . ($all_ok ? 'ok' : 'err') . '">' . $message . '</div>';

?>
