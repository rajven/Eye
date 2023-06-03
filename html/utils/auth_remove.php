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
                run_sql($db_link, 'DELETE FROM connections WHERE auth_id=' . $val);
                run_sql($db_link, 'DELETE FROM User_auth_alias WHERE auth_id=' . $val);
                $changes = delete_record($db_link, "User_auth", "id=" . $val);
                if (!empty($changes)) {
                    LOG_WARNING($db_link, "Remove user ip: $changes");
                } else {
                    $all_ok = 1;
                }
            }
        }
        if ($all_ok) {
            print "Success!";
        } else {
            print "Fail!";
        }
    }
}
