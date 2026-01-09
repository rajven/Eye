<?php
require_once($_SERVER['DOCUMENT_ROOT'] . "/inc/auth.php");
require_once($_SERVER['DOCUMENT_ROOT'] . "/inc/languages/" . HTML_LANG . ".php");

if (!defined("CONFIG")) die("Not defined");

$page_url = null;

if (isset($_POST["RemoveUser"]) && !empty($_POST["f_deleted"])) {
    $fid = $_POST["fid"] ?? [];
    $all_ok = true;

    foreach ($fid as $val) {
        if ($val = (int)$val) {
            $result = delete_user($db_link, $val);
            if (!$result) {
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
