<?php
require_once($_SERVER['DOCUMENT_ROOT'] . "/inc/auth.php");
require_once($_SERVER['DOCUMENT_ROOT'] . "/inc/languages/" . HTML_LANG . ".php");

if (!defined("CONFIG")) die("Not defined");

if (isset($_POST["RemoveUser"]) and (isset($_POST["f_deleted"]))) {
	if ($_POST["f_deleted"] * 1) {
		$fid = $_POST["fid"];
		$all_ok = 1;
		foreach ($fid as $key => $val) {
			if ($val) {
				$login = get_record($db_link, "User_list", "id='$val'");
				$device = get_record($db_link, "devices", "user_id='$val'");
				if (!empty($device)) {
					LOG_INFO($db_link, "Delete device for user id: $val");
					unbind_ports($db_link, $device['id']);
					run_sql($db_link, "DELETE FROM connections WHERE device_id=" . $device['id']);
					run_sql($db_link, "DELETE FROM device_l3_interfaces WHERE device_id=" . $device['id']);
					run_sql($db_link, "DELETE FROM device_ports WHERE device_id=" . $device['id']);
					delete_record($db_link, "devices", "id=" . $device['id']);
				}
				run_sql($db_link, "DELETE FROM auth_rules WHERE user_id=$val");
				run_sql($db_link, "UPDATE User_auth SET deleted=1, changed=1, dhcp_changed=1 WHERE user_id=$val");
				delete_record($db_link, "User_list", "id=$val");
				LOG_WARNING($db_link, "Deleted user id: $val login: " . $login['login'] . "\r\n");
			}
		}
		if ($all_ok) {
			print "Success!";
		} else {
			print "Fail!";
		}
	}
}
