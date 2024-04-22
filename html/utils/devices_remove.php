<?php
require_once($_SERVER['DOCUMENT_ROOT'] . "/inc/auth.php");
require_once($_SERVER['DOCUMENT_ROOT'] . "/inc/languages/" . HTML_LANG . ".php");

if (!defined("CONFIG")) die("Not defined");

if (isset($_POST["RemoveDevice"]) and (isset($_POST["f_deleted"]))) {
	if ($_POST["f_deleted"] * 1) {
		$all_ok = 1;
		$dev_ids = $_POST["fid"];
		foreach ($dev_ids as $key => $val) {
			if ($val) {
					unbind_ports($db_link, $val);
					run_sql($db_link, "DELETE FROM connections WHERE device_id=".$val);
					run_sql($db_link, "DELETE FROM device_l3_interfaces WHERE device_id=".$val);
					run_sql($db_link, "DELETE FROM device_ports WHERE device_id=".$val);
					run_sql($db_link, "DELETE FROM gateway_subnets WHERE device_id=".$val);
					delete_record($db_link, "devices", "id=".$val);
					}
				}
		if ($all_ok) {
			print "Success!";
		} else {
			print "Fail!";
		}
	}
}
