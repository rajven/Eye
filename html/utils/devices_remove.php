<?php
require_once($_SERVER['DOCUMENT_ROOT'] . "/inc/auth.php");
require_once($_SERVER['DOCUMENT_ROOT'] . "/inc/languages/" . HTML_LANG . ".php");

if (!defined("CONFIG")) die("Not defined");

$page_url = null;

$remove_action = getPOST('RemoveDevice', null, null);
$f_deleted     = getPOST('f_deleted', null, null);
$dev_ids       = (array) getPOST('fid', null, []);

$ids = [];
foreach ($dev_ids as $val) {
    $id = (int)$val;
    if ($id > 0) {
        $ids[] = $id;
    }
}

if ($remove_action === null || $f_deleted === null || $f_deleted === '') {
    print '<div class="result err">' . WEB_mass_no_action . '</div>';
    exit;
}

if (empty($ids)) {
    print '<div class="result err">' . WEB_mass_nothing_selected . '</div>';
    exit;
}

// the removal of a device is a mass action of the list, and the right for it is looked up before the first
// record is touched: the token of the csrf proves who sent the request, it says nothing
// about what that reader may do
if (!acl_is_admin()) {
    acl_denied($db_link, WEB_title_devices);
}

$all_ok = true;

foreach ($ids as $id) {
    // the host of the device is switched off and marked as the host of a deleted
    // device before the record disappears: no script would find the device afterwards
    $zbx_state = is_zabbix_enabled() ? zabbix_record_state($db_link, 'netdev', $id) : null;
    if ($zbx_state !== null && ((int)$zbx_state['monitoring'] === 1 || (int)$zbx_state['monitoring_old'] === 1)) {
        zabbix_record_mark_deleted($db_link, $zbx_state);
    }

    if (empty(delete_device($db_link, $id))) {
        $all_ok = false;
    }
}

$message = $all_ok ? WEB_mass_success : WEB_mass_fail;
/* a class instead of inline styles: the block used to stay green even on Fail! */
print '<div class="result ' . ($all_ok ? 'ok' : 'err') . '">' . $message . '</div>';

?>
