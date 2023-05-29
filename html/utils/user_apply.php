<?php
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/auth.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/languages/" . HTML_LANG . ".php");

if (! defined("CONFIG")) die("Not defined");

if (isset($_POST["ApplyForAll"])) {

    $auth_id = $_POST["fid"];

    if (empty($_POST["a_enabled"]))  { $_POST["a_enabled"]=0; }
    if (empty($_POST["a_dhcp"]))     { $_POST["a_dhcp"]=0; }
    if (empty($_POST["a_queue_id"])) { $_POST["a_queue_id"]=0; }
    if (empty($_POST["a_group_id"])) { $_POST["a_group_id"]=0; }
    if (empty($_POST["a_traf"]))     { $_POST["a_traf"]=0; }

    if (empty($_POST["a_day_q"]))    { $_POST["a_day_q"]=0; }
    if (empty($_POST["a_month_q"]))  { $_POST["a_month_q"]=0; }
    if (empty($_POST["a_new_ou"]))   { $_POST["a_new_ou"]=0; }


    $a_enabled  = $_POST["a_enabled"] * 1;
    $a_dhcp     = $_POST["a_dhcp"] * 1;
    $a_dhcp_acl = $_POST["a_dhcp_acl"];
    $a_queue    = $_POST["a_queue_id"] * 1;
    $a_group    = $_POST["a_group_id"] * 1;
    $a_traf     = $_POST["a_traf"] * 1;
    $a_day      = $_POST["a_day_q"] * 1;
    $a_month    = $_POST["a_month_q"] * 1;
    $a_ou_id    = $_POST["a_new_ou"] * 1;

    $msg="Massive User change!";
    LOG_WARNING($db_link,$msg);

    $all_ok=1;
    foreach ($auth_id as $key => $val) {
        if ($val) {
            unset($auth);
            unset($user);
	    if (isset($_POST["e_enabled"]))    { $auth['enabled'] = $a_enabled; $user['enabled'] = $a_enabled; }
	    if (isset($_POST["e_group_id"]))   { $auth['filter_group_id'] = $a_group; }
	    if (isset($_POST["e_queue_id"]))   { $auth['queue_id'] = $a_queue; }
	    if (isset($_POST["e_dhcp"]))       { $auth['dhcp'] = $a_dhcp; }
	    if (isset($_POST["e_dhcp_acl"]))   { $auth['dhcp_acl'] = $a_dhcp_acl; }
	    if (isset($_POST["e_traf"]))       { $auth['save_traf'] = $a_traf; }
	    if (isset($_POST["e_day_q"]))      { $user['day_quota'] = $a_day; }
	    if (isset($_POST["e_month_q"]))    { $user['month_quota'] = $a_month; }
	    if (isset($_POST["e_new_ou"]))     { $user['ou_id'] = $a_ou_id; $auth['ou_id'] = $a_ou_id; }

            $login = get_record($db_link,"User_list","id='$val'");
            $msg.=" For all ip user id: ".$val." login: ".$login['login']." set: ";
            $msg.= get_diff_rec($db_link,"User_list","id='$val'", $user, 1);
            $ret = update_record($db_link, "User_list", "id='" . $val . "'", $user);
	    if (!$ret) { $all_ok = 0; }

	    $auth_list = get_records_sql($db_link,"SELECT id FROM User_auth WHERE deleted=0 AND user_id=".$val);
	    if (!empty($auth)) {
		foreach ($auth_list as $row) {
		    if (empty($row)) { continue; }
        	    $ret = update_record($db_link, "User_auth", "id='" . $row["id"] . "'", $auth);
		    if (!$ret) { $all_ok = 0; }
		    }
		}
            }
        }
    if ($all_ok) { print "Success!"; } else { print "Fail!"; }
    }
