<?php
require_once($_SERVER['DOCUMENT_ROOT'] . "/inc/auth.php");
require_once($_SERVER['DOCUMENT_ROOT'] . "/inc/languages/" . HTML_LANG . ".php");

if (!defined("CONFIG")) die("Not defined");

$page_url = null;

if (isset($_POST["RemoveDevice"]) && !empty($_POST["f_deleted"])) {
    $dev_ids = $_POST["fid"] ?? [];
    $all_ok = true;

    foreach ($dev_ids as $val) {
        if ($val = (int)$val) { // Приводим к целому числу и проверяем, что не 0
            $changes = delete_device($db_link, $val);
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
