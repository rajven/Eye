<?php
require_once($_SERVER['DOCUMENT_ROOT'] . "/inc/auth.php");
require_once($_SERVER['DOCUMENT_ROOT'] . "/inc/languages/" . HTML_LANG . ".php");

if (!defined("CONFIG")) die("Not defined");

$page_url = null;

// the marks of the mass operation: the request must carry both the action and the
// reason of the removal, as the dialog of the page does
$remove_action = getPOST('RemoveUser', null, null);
$f_deleted     = getPOST('f_deleted', null, null);
$fid           = (array) getPOST('fid', null, []);

$ids = [];
foreach ($fid as $val) {
    $id = (int)$val;
    if ($id > 0) {
        $ids[] = $id;
    }
}

// an answer of the block which the page reads back: it must say what really happened,
// while a request without a selection used to come back green with 'Success!'
if ($remove_action === null || $f_deleted === null || $f_deleted === '') {
    print '<div class="result err">' . WEB_mass_no_action . '</div>';
    exit;
}

if (empty($ids)) {
    print '<div class="result err">' . WEB_mass_nothing_selected . '</div>';
    exit;
}

$all_ok = true;

foreach ($ids as $id) {
    if (!delete_user($db_link, $id)) {
        $all_ok = false;
    }
}

$message = $all_ok ? WEB_mass_success : WEB_mass_fail;
/* a class instead of inline styles: the block used to stay green even on Fail! */
print '<div class="result ' . ($all_ok ? 'ok' : 'err') . '">' . $message . '</div>';

?>
