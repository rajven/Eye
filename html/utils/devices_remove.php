<?php
require_once($_SERVER['DOCUMENT_ROOT'] . "/inc/auth.php");
require_once($_SERVER['DOCUMENT_ROOT'] . "/inc/languages/" . HTML_LANG . ".php");

if (!defined("CONFIG")) die("Not defined");

$page_url = null;

$remove_action = getPOST('RemoveDevice', null, null);
$f_deleted     = getPOST('f_deleted', null, null);

if ($remove_action !== null && $f_deleted !== '') {
    $dev_ids = getPOST('fid', null, []);
    $all_ok = true;

    foreach ($dev_ids as $val) {
        $id = (int)$val;
        if ($id > 0) {
            $changes = delete_device($db_link, $id);
            if (empty($changes)) {
                $all_ok = false;
            }
        }
    }

    echo $all_ok ? 'Success!' : 'Fail!';
}

?>
