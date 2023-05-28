<?php
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/auth.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/languages/" . HTML_LANG . ".php");

if (! defined("CONFIG")) die("Not defined");

if (isset($_POST["ApplyForAll"])) {

    $auth_id = $_POST["fid"];

    if (empty($_POST["a_enabled"])) { $_POST["a_enabled"]=0; }
    if (empty($_POST["a_dhcp"])) { $_POST["a_dhcp"]=0; }
    if (empty($_POST["a_queue_id"])) { $_POST["a_queue_id"]=0; }
    if (empty($_POST["a_group_id"])) { $_POST["a_group_id"]=0; }
    if (empty($_POST["a_traf"])) { $_POST["a_traf"]=0; }

    if (empty($_POST["n_enabled"])) { $_POST["n_enabled"]=0; }
    if (empty($_POST["n_link"])) { $_POST["n_link"]=0; }

    $a_enabled  = $_POST["a_enabled"] * 1;
    $a_dhcp     = $_POST["a_dhcp"] * 1;
    $a_dhcp_acl = $_POST["a_dhcp_acl"];
    $a_queue    = $_POST["a_queue_id"] * 1;
    $a_group    = $_POST["a_group_id"] * 1;
    $a_traf     = $_POST["a_traf"] * 1;

    $n_enabled = $_POST["n_enabled"] * 1;
    $n_link    = $_POST["n_link"] * 1;
    $n_handler = $_POST["n_handler"];

    $msg="Massive User change!";
    LOG_WARNING($db_link,$msg);

    $all_ok=1;
    foreach ($auth_id as $key => $val) {
        if ($val) {
            unset($auth);
	    if (isset($_POST["e_enabled"]))    { $auth['enabled'] = $a_enabled; }
	    if (isset($_POST["e_group_id"]))   { $auth['filter_group_id'] = $a_group; }
	    if (isset($_POST["e_queue_id"]))   { $auth['queue_id'] = $a_queue; }
	    if (isset($_POST["e_dhcp"]))       { $auth['dhcp'] = $a_dhcp; }
	    if (isset($_POST["e_dhcp_acl"]))   { $auth['dhcp_acl'] = $a_dhcp_acl; }
	    if (isset($_POST["e_traf"]))       { $auth['save_traf'] = $a_traf; }
//nagios
	    if (isset($_POST["e_nag_enabled"])){ $auth['nagios'] = $n_enabled; }
	    if (isset($_POST["e_nag_link"]))   { $auth['link_check'] = $n_link; }
	    if (isset($_POST["e_nag_handler"])){ $auth['nagios_handler'] = $n_handler; }

	    if (!empty($auth)) {
        	$ret = update_record($db_link, "User_auth", "id='" . $val . "'", $auth);
		if (!$ret) { $all_ok = 0; }
		}
            }
        }
    if ($all_ok) { print "Success!"; } else { print "Fail!"; }
    }
