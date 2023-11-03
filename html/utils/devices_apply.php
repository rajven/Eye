<?php
require_once($_SERVER['DOCUMENT_ROOT'] . "/inc/auth.php");
require_once($_SERVER['DOCUMENT_ROOT'] . "/inc/languages/" . HTML_LANG . ".php");

if (!defined("CONFIG")) die("Not defined");

if (isset($_POST["ApplyForAll"])) {

    $dev_id = $_POST["fid"];

    if (empty($_POST["a_dev_type"])) { $_POST["a_dev_type"] = 0; }
    if (empty($_POST["a_device_model_id"])) { $_POST["a_device_model_id"] = 0; }
    if (empty($_POST["a_building_id"])) { $_POST["a_building_id"] = 0; }
    if (empty($_POST["a_snmp_version"])) { $_POST["a_snmp_version"] = 0; }
    if (empty($_POST["a_ro_community"])) { $_POST["a_ro_community"] = 'public'; }
    if (empty($_POST["a_rw_community"])) { $_POST["a_rw_community"] = 'private'; }

    $a_dev_type = $_POST["a_dev_type"];
    $a_device_model_id = $_POST["a_device_model_id"];
    $a_building_id = $_POST["a_building_id"];
    $a_snmp_version = $_POST["a_snmp_version"];
    $a_ro_community = $_POST["a_ro_community"];
    $a_rw_community = $_POST["a_rw_community"];

    $msg = "Massive change devices!";
    LOG_WARNING($db_link, $msg);

    $all_ok = 1;
    foreach ($dev_id as $key => $val) {
        if (!empty($val)) {
            unset($device);
            if (isset($_POST["e_set_type"])) { $device['device_type'] = $a_dev_type; }
            if (isset($_POST["e_set_model"])) { 
                $device['device_model_id'] = $a_device_model_id;
                $device['vendor_id'] = get_device_model_vendor($db_link,$a_device_model_id);
                }
            //snmp
            if (isset($_POST["e_set_snmp_version"])) { $device['snmp_version'] = $a_snmp_version * 1; }
            if (isset($_POST["e_set_ro_community"])) { $device['community'] = $a_ro_community; }
            if (isset($_POST["e_set_rw_community"])) { $device['rw_community'] = $a_rw_community; }
            //location
            if (isset($_POST["e_set_building"])) { $device['building_id'] = $a_building_id * 1; }
            if (!empty($device)) {
                $ret = update_record($db_link, "devices", "id='" . $val . "'", $device);
                if (!$ret) { $all_ok = 0; }
            }
        }
    }
    if ($all_ok) {
        print "Success!";
    } else {
        print "Fail!";
    }
}
