<?php
require_once($_SERVER['DOCUMENT_ROOT'] . "/inc/auth.php");
require_once($_SERVER['DOCUMENT_ROOT'] . "/inc/languages/" . HTML_LANG . ".php");

if (!defined("CONFIG")) die("Not defined");

$page_url = null;

$remove_action = getPOST('RemoveAuth', $page_url, null);
$f_deleted     = getPOST('f_deleted', $page_url, null);

$all_ok = true;

if ($remove_action !== null && $f_deleted !== '') {
    $auth_id = getPOST('fid', $page_url, []);

    foreach ($auth_id as $val) {
        $id = (int)$val;
        if ($id > 0) { // только положительные ID
            $changes = delete_user_auth($db_link, $id);
            if (empty($changes)) {
                $all_ok = false;
            }
        }
    }
}

$message = $all_ok ? "Success!" : "Fail!";
print "<div style='padding:20px; font-size:18px; background:#e9f7ef; border:1px solid #2ecc71;'>$message</div>";

?>
