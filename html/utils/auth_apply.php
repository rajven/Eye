<?php
require_once($_SERVER['DOCUMENT_ROOT'] . "/inc/auth.php");
require_once($_SERVER['DOCUMENT_ROOT'] . "/inc/languages/" . HTML_LANG . ".php");

if (!defined("CONFIG")) die("Not defined");

if (isset($_POST["ApplyForAll"])) {

    $auth_id = $_POST["fid"];

    if (empty($_POST["a_new_ou"])) {
        $_POST["a_new_ou"] = 0;
    }
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

    if (empty($_POST["n_enabled"])) {
        $_POST["n_enabled"] = 0;
    }

    if (empty($_POST["n_link"])) {
        $_POST["n_link"] = 0;
    }

    if (empty($_POST["a_bind_mac"])) {
        $_POST["a_bind_mac"] = 0;
    }

    if (empty($_POST["a_bind_ip"])) {
        $_POST["a_bind_ip"] = 0;
    }

    $a_ou_id    = $_POST["a_new_ou"] * 1;
    $a_enabled  = $_POST["a_enabled"] * 1;
    $a_dhcp     = $_POST["a_dhcp"] * 1;
    $a_dhcp_acl = trim($_POST["a_dhcp_acl"]);
    $a_dhcp_option_set = trim($_POST["a_dhcp_option_set"]);
    $a_queue    = $_POST["a_queue_id"] * 1;
    $a_group    = $_POST["a_group_id"] * 1;
    $a_traf     = $_POST["a_traf"] * 1;

    $a_bind_mac = $_POST["a_bind_mac"]*1;
    $a_bind_ip  = $_POST["a_bind_ip"]*1;

    $n_enabled = $_POST["n_enabled"] * 1;
    $n_link    = $_POST["n_link"] * 1;
    $n_handler = $_POST["n_handler"];

    $msg = "Massive User change!";
    LOG_WARNING($db_link, $msg);

    $all_ok = 1;
    foreach ($auth_id as $key => $val) {
        if ($val) {
            unset($auth);
            //check user state
            $cur_auth = get_record_sql($db_link, "SELECT * FROM User_auth WHERE `id`=" . $val);
            if (!empty($cur_auth)) { $user_info = get_record_sql($db_link, "SELECT * FROM User_list WHERE `id`=" . $cur_auth["user_id"]); }


            if (isset($_POST["e_enabled"])) {
                if (!empty($user_info)) { $a_enabled = $user_info["enabled"] * $a_enabled; }
                $auth['enabled'] = $a_enabled;
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
            //nagios
            if (isset($_POST["e_nag_enabled"])) {
                $auth['nagios'] = $n_enabled;
            }
            if (isset($_POST["e_nag_link"])) {
                $auth['link_check'] = $n_link;
            }
            if (isset($_POST["e_nag_handler"])) {
                $auth['nagios_handler'] = $n_handler;
            }

            if (!empty($auth)) {
                $ret = update_record($db_link, "User_auth", "id='" . $val . "'", $auth);
                if (!$ret) { $all_ok = 0; }
            }

            //change user group
            if (isset($_POST["e_new_ou"]) and !empty($a_ou_id) and !empty($user_info)) {
                $user['ou_id'] = $a_ou_id;
                $u_auth['ou_id'] = $a_ou_id;
                //change user group
                $msg = " For user id: " . $cur_auth['user_id'] . " login: " . $user_info['login'] . " set: ou_id = ".$a_ou_id;
                LOG_INFO($db_link,$msg);
                $ret = update_record($db_link, "User_list", "id='" . $cur_auth['user_id'] . "'", $user);
                if (!$ret) { $all_ok = 0; }
                //change user ip
                $auth_list = get_records_sql($db_link, "SELECT * FROM User_auth WHERE user_id=" . $cur_auth['user_id']);
                if (!empty($auth_list)) {
                        foreach ($auth_list as $row) {
                            if (empty($row)) { continue; }
                            $ret = update_record($db_link, "User_auth", "id='" . $row["id"] . "'", $u_auth);
                            if (!$ret) { $all_ok = 0; }
                            }
                        }
                unset($user);
                }

            //bind mac rule
            if (isset($_POST["e_bind_mac"])) {
                $first_auth = get_record_sql($db_link,"SELECT user_id,mac FROM User_auth WHERE id=".$val);
                if (!empty($first_auth) and !empty($first_auth['mac'])) {
                    if ($a_bind_mac) {
                            $auth_rules_user = get_record_sql($db_link,"SELECT * FROM auth_rules WHERE user_id=".$first_auth['user_id']." AND type=2");
                            $auth_rules_mac = get_record_sql($db_link,"SELECT * FROM auth_rules WHERE rule='".$first_auth['mac']."' AND type=2");
                            if (empty($auth_rules_user) and empty($auth_rules_mac)) {
                                $new['user_id']=$first_auth['user_id'];
                                $new['type']=2;
                                $new['rule']=$first_auth['mac'];
                                insert_record($db_link,"auth_rules",$new);
                                LOG_INFO($db_link,"Created auto rule for user_id: ".$first_auth['user_id']." and mac ".$first_auth['mac']);
                                } else {
                                LOG_INFO($db_link,"Auto rule for user_id: ".$first_auth['user_id']." and mac ".$first_auth['mac']." already exists");
                                }
                            } else {
                                run_sql($db_link,"DELETE FROM auth_rules WHERE user_id=".$first_auth['user_id']." AND type=2");
                                LOG_INFO($db_link,"Remove auto rule for user_id: ".$first_auth['user_id']." and mac ".$first_auth['mac']);
                            }
                    } else {
                        LOG_ERROR($db_link,"Auto rule for user_id: ".$first_auth['user_id']." not created. Record not found or empty mac.");
                    }
            }

            //bind ip rule
            if (isset($_POST["e_bind_ip"])) {
                $first_auth = get_record_sql($db_link,"SELECT user_id,ip FROM User_auth WHERE id=".$val);
                if (!empty($first_auth) and !empty($first_auth['ip'])) {
                    if ($a_bind_ip) {
                            $auth_rules_user = get_record_sql($db_link,"SELECT * FROM auth_rules WHERE user_id=".$first_auth['user_id']." AND type=1");
                            $auth_rules_ip = get_record_sql($db_link,"SELECT * FROM auth_rules WHERE rule='".$first_auth['ip']."' AND type=1");
                            if (empty($auth_rules_user) and empty($auth_rules_ip)) {
                                $new['user_id']=$first_auth['user_id'];
                                $new['type']=1;
                                $new['rule']=$first_auth['ip'];
                                insert_record($db_link,"auth_rules",$new);
                                LOG_INFO($db_link,"Created auto rule for user_id: ".$first_auth['user_id']." and ip ".$first_auth['ip']);
                                } else {
                                LOG_INFO($db_link,"Auto rule for user_id: ".$first_auth['user_id']." and ip ".$first_auth['ip']." already exists");
                                }
                            } else {
                                run_sql($db_link,"DELETE FROM auth_rules WHERE user_id=".$first_auth['user_id']." AND type=1");
                                LOG_INFO($db_link,"Remove auto rule for user_id: ".$first_auth['user_id']." and ip ".$first_auth['ip']);
                            }
                    } else {
                        LOG_ERROR($db_link,"Auto rule for user_id: ".$first_auth['user_id']." not created. Record not found or empty ip.");
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
