<?php
require_once($_SERVER['DOCUMENT_ROOT'] . "/inc/auth.php");
require_once($_SERVER['DOCUMENT_ROOT'] . "/inc/languages/" . HTML_LANG . ".php");

if (!defined("CONFIG")) die("Not defined");

if (isset($_POST["RemoveAuth"]) && !empty($_POST["f_deleted"])) {
    $auth_id = $_POST["fid"] ?? [];
    $all_ok = true;

    foreach ($auth_id as $val) {
        if ($val = (int)$val) { // Приводим к int и проверяем, что не 0
            $changes = delete_user_auth($db_link, $val);
            if (empty($changes)) {
                $all_ok = false;
            }
        }
    }

    if ($all_ok) {
        print "Success!";
    } else {
        print "Fail!";
    }
}
?>
