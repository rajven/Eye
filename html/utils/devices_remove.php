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
                            $changes = delete_device($db_link,$val);
                            if (empty($changes)) { $all_ok = 0; }
                        }
                }
		if ($all_ok) {
			print "Success!";
		} else {
			print "Fail!";
		}
	}
}
