<?php
require_once($_SERVER['DOCUMENT_ROOT'] . "/inc/auth.php");
require_once($_SERVER['DOCUMENT_ROOT'] . "/inc/languages/" . HTML_LANG . ".php");

if (!defined("CONFIG")) die("Not defined");

$page_url = null;

if (getPOST("ApplyForAll", $page_url) !== null) {

    $dev_id = getPOST("fid", $page_url, []);

    $a_dev_type = (int)getPOST("a_dev_type", $page_url, 0);
    $a_device_model_id = (int)getPOST("a_device_model_id", $page_url, 0);
    $a_building_id = (int)getPOST("a_building_id", $page_url, 0);
    $a_snmp_version = (int)getPOST("a_snmp_version", $page_url, 0);
    $a_ro_community = trim(getPOST("a_ro_community", $page_url, 'public'));
    $a_rw_community = trim(getPOST("a_rw_community", $page_url, 'private'));

    $all_ok = true;

    foreach ($dev_id as $val) {
        $id = (int)$val;
        if ($id <= 0) {
            continue;
        }

        $device = [];

        if (getPOST("e_set_type", $page_url) !== null) {
            $device['device_type'] = $a_dev_type;
        }
        if (getPOST("e_set_model", $page_url) !== null) {
            $device['device_model_id'] = $a_device_model_id;
            $device['vendor_id'] = get_device_model_vendor($db_link, $a_device_model_id);
        }
        if (getPOST("e_set_snmp_version", $page_url) !== null) {
            $device['snmp_version'] = $a_snmp_version;
        }
        if (getPOST("e_set_ro_community", $page_url) !== null) {
            $device['community'] = $a_ro_community;
        }
        if (getPOST("e_set_rw_community", $page_url) !== null) {
            $device['rw_community'] = $a_rw_community;
        }
        if (getPOST("e_set_building", $page_url) !== null) {
            $device['building_id'] = $a_building_id;
        }

        if (!empty($device)) {
            $ret = update_record($db_link, "devices", "id = ?", $device, [$id]);
            if (!$ret) {
                $all_ok = false;
            }
        }
    }

    echo $all_ok ? "Success!" : "Fail!";
}

?>
