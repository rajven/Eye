<?php
require_once($_SERVER['DOCUMENT_ROOT'] . "/inc/auth.php");
require_once($_SERVER['DOCUMENT_ROOT'] . "/inc/languages/" . HTML_LANG . ".php");

if (!defined("CONFIG")) die("Not defined");

if (isset($_POST["RemoveAuth"]) and (isset($_POST["f_deleted"]))) {
    if ($_POST["f_deleted"] * 1) {
        $auth_id = $_POST["fid"];
        $all_ok = 1;
        foreach ($auth_id as $key => $val) {
            if ($val) {
                $changes = delete_user_auth($db_link,$val);
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
