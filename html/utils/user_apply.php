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

    $a_enabled  = $_POST["a_enabled"] * 1;
    $a_dhcp     = $_POST["a_dhcp"] * 1;
    $a_dhcp_acl = $_POST["a_dhcp_acl"];
    $a_queue    = $_POST["a_queue_id"] * 1;
    $a_group    = $_POST["a_group_id"] * 1;
    $a_traf     = $_POST["a_traf"] * 1;
    $a_day      = $_POST["a_day_q"] * 1;
    $a_month    = $_POST["a_month_q"] * 1;
    $a_ou_id    = $_POST["a_new_ou"] * 1;

    $a_bind_mac = $_POST["a_bind_mac"] * 1;

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

            $login = get_record($db_link, "User_list", "id='$val'");
            $msg .= " For all ip user id: " . $val . " login: " . $login['login'] . " set: ";
            $msg .= get_diff_rec($db_link, "User_list", "id='$val'", $user, 1);
            $ret = update_record($db_link, "User_list", "id='" . $val . "'", $user);
            if (!$ret) {
                $all_ok = 0;
            }

            $auth_list = get_records_sql($db_link, "SELECT id, mac FROM User_auth WHERE deleted=0 AND user_id=" . $val);
            $b_mac = '';
            if (!empty($auth)) {
                foreach ($auth_list as $row) {
                    if (empty($row)) {
                        continue;
                    }
                    if (empty($b_mac) and !empty($row["mac"])) {
                        $b_mac = $row["mac"];
                    }
                    $ret = update_record($db_link, "User_auth", "id='" . $row["id"] . "'", $auth);
                    if (!$ret) {
                        $all_ok = 0;
                    }
                }
            }

            //bind mac rule
            if (isset($_POST["e_bind_mac"]) and !empty($b_mac)) {
                if ($a_bind_mac) {
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
                } else {
                    run_sql($db_link, "DELETE FROM auth_rules WHERE user_id=" . $val . " AND type=2");
                    LOG_INFO($db_link, "Remove auto rule for user_id: " . $val . " and mac " . $b_mac);
                }
            } else {
                LOG_ERROR($db_link, "Auto rule for user_id: " . $first_auth['user_id'] . " not created. Record not found or empty mac.");
            }
        }
    }
    if ($all_ok) {
        print "Success!";
    } else {
        print "Fail!";
    }
}
