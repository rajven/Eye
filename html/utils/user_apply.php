<?php
require_once($_SERVER['DOCUMENT_ROOT'] . "/inc/auth.php");
require_once($_SERVER['DOCUMENT_ROOT'] . "/inc/languages/" . HTML_LANG . ".php");

if (!defined("CONFIG")) die("Not defined");

if (isset($_POST["ApplyForAll"])) {

    $auth_id = $_POST["fid"];

    if (empty($_POST["a_enabled"])) {
        $_POST["a_enabled"] = 0;
    }
    if (empty($_POST["a_dhcp"])) {
        $_POST["a_dhcp"] = 0;
    }
    if (empty($_POST["a_queue_id"])) {
        $_POST["a_queue_id"] = 0;
    }
    if (empty($_POST["a_group_id"])) {
        $_POST["a_group_id"] = 0;
    }
    if (empty($_POST["a_traf"])) {
        $_POST["a_traf"] = 0;
    }

    if (empty($_POST["a_day_q"])) {
        $_POST["a_day_q"] = 0;
    }
    if (empty($_POST["a_month_q"])) {
        $_POST["a_month_q"] = 0;
    }
    if (empty($_POST["a_new_ou"])) {
        $_POST["a_new_ou"] = 0;
    }

    if (empty($_POST["a_bind_mac"])) {
        $_POST["a_bind_mac"] = 0;
    }

    if (empty($_POST["a_bind_ip"])) {
        $_POST["a_bind_ip"] = 0;
    }

    if (empty($_POST["a_create_netdev"])) {
        $_POST["a_create_netdev"] = 0;
    }

    if (empty($_POST["a_permanent"])) {
        $_POST["a_permanent"] = 0;
    }

    if (isset($_POST["a_enabled"]))             {     $a_enabled         = $_POST["a_enabled"] * 1; }
    if (isset($_POST["a_dhcp"]))                {     $a_dhcp            = $_POST["a_dhcp"] * 1; }
    if (isset($_POST["a_dhcp_acl"]))            {     $a_dhcp_acl        = trim($_POST["a_dhcp_acl"]); }
    if (isset($_POST["a_dhcp_option_set"]))     {     $a_dhcp_option_set = trim($_POST["a_dhcp_option_set"]); }
    if (isset($_POST["a_queue_id"]))            {     $a_queue           = $_POST["a_queue_id"] * 1; }
    if (isset($_POST["a_group_id"]))            {     $a_group           = $_POST["a_group_id"] * 1; }
    if (isset($_POST["a_traf"]))                {     $a_traf            = $_POST["a_traf"] * 1; }
    if (isset($_POST["a_day_q"]))               {     $a_day             = $_POST["a_day_q"] * 1; }
    if (isset($_POST["a_month_q"]))             {     $a_month           = $_POST["a_month_q"] * 1; }
    if (isset($_POST["a_new_ou"]))              {     $a_ou_id           = $_POST["a_new_ou"] * 1; }
    if (isset($_POST["a_permanent"]))           {     $a_permanent       = $_POST["a_permanent"] * 1; }

    if (isset($_POST["a_bind_mac"]))            {     $a_bind_mac        = $_POST["a_bind_mac"] * 1; }
    if (isset($_POST["a_bind_ip"]))             {     $a_bind_ip         = $_POST["a_bind_ip"] * 1; }
    if (isset($_POST["a_create_netdev"]))       {     $a_create_netdev   = $_POST["a_create_netdev"] * 1; }

    $msg = "Massive User change!";
    LOG_WARNING($db_link, $msg);

    $all_ok = 1;
    foreach ($auth_id as $key => $val) {
        if ($val) {
            unset($auth);
            unset($user);
            if (isset($_POST["e_enabled"])) {
                $auth['enabled'] = $a_enabled;
                $user['enabled'] = $a_enabled;
            }
            if (isset($_POST["e_group_id"])) {
                $auth['filter_group_id'] = $a_group;
            }
            if (isset($_POST["e_queue_id"])) {
                $auth['queue_id'] = $a_queue;
            }
            if (isset($_POST["e_dhcp"])) {
                $auth['dhcp'] = $a_dhcp;
            }
            if (isset($_POST["e_dhcp_acl"])) {
                $auth['dhcp_acl'] = $a_dhcp_acl;
            }
            if (isset($_POST["e_dhcp_option_set"])) {
                $auth['dhcp_option_set'] = $a_dhcp_option_set;
            }
            if (isset($_POST["e_traf"])) {
                $auth['save_traf'] = $a_traf;
            }
            if (isset($_POST["e_day_q"])) {
                $user['day_quota'] = $a_day;
            }
            if (isset($_POST["e_month_q"])) {
                $user['month_quota'] = $a_month;
            }
            if (isset($_POST["e_new_ou"])) {
                $user['ou_id'] = $a_ou_id;
                $auth['ou_id'] = $a_ou_id;
            }

            if (isset($_POST["e_permanent"])) {
                $user['permanent'] = $a_permanent;
            }

            $login = get_record($db_link, "User_list", "id='$val'");
            $msg .= " For all ip user id: " . $val . " login: " . $login['login'] . " set: ";
            $msg .= get_diff_rec($db_link, "User_list", "id='$val'", $user, 1);

            if (!empty($user)) { 
                $ret = update_record($db_link, "User_list", "id='" . $val . "'", $user);
                if (!$ret) { $all_ok = 0; }
                }

            $auth_list = get_records_sql($db_link, "SELECT id, mac, ip FROM User_auth WHERE deleted=0 AND user_id=" . $val);
            $b_mac = '';
            $b_ip = '';
            if (!empty($auth_list)) {
                foreach ($auth_list as $row) {
                    if (empty($row)) { continue; }
                    if (empty($b_mac) and !empty($row["mac"])) { $b_mac = $row["mac"]; }
                    if (empty($b_ip) and !empty($row["ip"])) { $b_ip = $row["ip"]; }
                    if (!empty($auth)) {
                        $ret = update_record($db_link, "User_auth", "id='" . $row["id"] . "'", $auth);
                        if (!$ret) { $all_ok = 0; }
                    }
                }
            }

            //bind mac rule
            if (isset($_POST["e_bind_mac"])) {
                if ($a_bind_mac) {
                    if (!empty($b_mac)) {
                        $auth_rules_user = get_record_sql($db_link, "SELECT * FROM auth_rules WHERE user_id=" . $val . " AND type=2");
                        $auth_rules_mac = get_record_sql($db_link, "SELECT * FROM auth_rules WHERE rule='" . $b_mac . "' AND type=2");
                        if (empty($auth_rules_user) and empty($auth_rules_mac)) {
                                $new['user_id'] = $val;
                                $new['type'] = 2;
                                $new['rule'] = $b_mac;
                                insert_record($db_link, "auth_rules", $new);
                                LOG_INFO($db_link, "Created auto rule for user_id: " . $val . " and mac " . $b_mac);
                            } else {
                                LOG_INFO($db_link, "Auto rule for user_id: " . $val . " and mac " . $mac . " already exists");
                            }
                        }
                    } else {
                        run_sql($db_link, "DELETE FROM auth_rules WHERE user_id=" . $val . " AND type=2");
                        LOG_INFO($db_link, "Remove auto rule for user_id: " . $val . " and mac " . $b_mac);
                }
            }

            //bind ip rule
            if (isset($_POST["e_bind_ip"])) {
                if ($a_bind_ip) {
                    if (!empty($b_ip)) {
                        $auth_rules_user = get_record_sql($db_link, "SELECT * FROM auth_rules WHERE user_id=" . $val . " AND type=1");
                        $auth_rules_ip = get_record_sql($db_link, "SELECT * FROM auth_rules WHERE rule='" . $b_ip . "' AND type=1");
                        if (empty($auth_rules_user) and empty($auth_rules_ip)) {
                                $new['user_id'] = $val;
                                $new['type'] = 1;
                                $new['rule'] = $b_ip;
                                insert_record($db_link, "auth_rules", $new);
                                LOG_INFO($db_link, "Created auto rule for user_id: " . $val . " and ip " . $b_ip);
                            } else {
                                LOG_INFO($db_link, "Auto rule for user_id: " . $val . " and ip " . $ip . " already exists");
                            }
                        }
                    } else {
                        run_sql($db_link, "DELETE FROM auth_rules WHERE user_id=" . $val . " AND type=1");
                        LOG_INFO($db_link, "Remove auto rule for user_id: " . $val . " and ip " . $b_ip);
                }
            }

            //create network devices
            if (isset($_POST["e_create_netdev"])) {
                if ($a_create_netdev) {
                    if (!empty($b_ip)) {
                        $device = get_record_sql($db_link,"SELECT * FROM devices WHERE user_id=".$val);
                        $auth = get_record_sql($db_link,"SELECT * FROM User_auth WHERE user_id=".$val." ORDER BY last_found DESC");
                        if (empty($device) and !empty($auth)) {
                            $new['user_id']=$val;
                            $new['device_name'] = $login['login'];
                            $new['device_type'] = 5;
                            $new['ip']=$auth['ip'];
                            $new['community'] = get_const('snmp_default_community');
                            $new['snmp_version'] = get_const('snmp_default_version');
                            $new['login'] = get_option($db_link,28);
                            $new['password'] = get_option($db_link,29);
                            //default ssh
                            $new['protocol'] = 0;
                            $new['control_port'] = get_option($db_link,30);
                            $new_id=insert_record($db_link, "devices", $new);
                        }
                    }
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
