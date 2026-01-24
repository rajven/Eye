<?php
require_once($_SERVER['DOCUMENT_ROOT'] . "/inc/auth.php");
require_once($_SERVER['DOCUMENT_ROOT'] . "/inc/languages/" . HTML_LANG . ".php");

if (!defined("CONFIG")) die("Not defined");

$page_url = null;

$remove_action = getPOST('RemoveAuth', $page_url, null);
$f_deleted     = getPOST('f_deleted', $page_url, null);

if ($remove_action !== null && $f_deleted !== '') {
    $auth_id = getPOST('fid', $page_url, []);
    $all_ok = true;

    foreach ($auth_id as $val) {
        $id = (int)$val;
        if ($id > 0) { // только положительные ID
            $changes = delete_user_auth($db_link, $id);
            if (empty($changes)) {
                $all_ok = false;
            }
        }
    }

    echo $all_ok ? 'Success!' : 'Fail!';
}

?>
