<?php
require_once($_SERVER['DOCUMENT_ROOT'] . "/inc/auth.php");
require_once($_SERVER['DOCUMENT_ROOT'] . "/inc/languages/" . HTML_LANG . ".php");

if (!defined("CONFIG")) die("Not defined");

if (isset($_POST["RemoveUser"]) and (isset($_POST["f_deleted"]))) {
	if ($_POST["f_deleted"] * 1) {
		$fid = $_POST["fid"];
		$all_ok = 1;
		foreach ($fid as $key => $val) {
			if ($val) { delete_user($db_link,$val); }
		}
		if ($all_ok) {
			print "Success!";
		} else {
			print "Fail!";
		}
	}
}
