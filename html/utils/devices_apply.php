<?php
require_once($_SERVER['DOCUMENT_ROOT'] . "/inc/auth.php");
require_once($_SERVER['DOCUMENT_ROOT'] . "/inc/languages/" . HTML_LANG . ".php");

if (!defined("CONFIG")) die("Not defined");

$page_url = null;

$all_ok = true;

// the request of a mass operation must carry both its mark and the selection: a
// dialog which did not meet the script of the interface brought nothing at all, and the
// answer came back green with 'Success!' while not a single record was touched
if (getPOST("ApplyForAll", $page_url) === null) {
    print '<div class="result err">' . WEB_mass_no_action . '</div>';
    exit;
}

if (empty((array) getPOST('fid', $page_url, []))) {
    print '<div class="result err">' . WEB_mass_nothing_selected . '</div>';
    exit;
}

if (getPOST("ApplyForAll", $page_url) !== null) {

    $dev_id = getPOST("fid", $page_url, []);

    $a_dev_type = (int)getPOST("a_dev_type", $page_url, 0);
    $a_device_model_id = (int)getPOST("a_device_model_id", $page_url, 0);
    $a_building_id = (int)getPOST("a_building_id", $page_url, 0);
    $a_snmp_version = (int)getPOST("a_snmp_version", $page_url, 0);
    $a_ro_community = trim(getPOST("a_ro_community", $page_url, 'public'));
    $a_rw_community = trim(getPOST("a_rw_community", $page_url, 'private'));

    // the parameters of the monitoring system and of the zabbix host of the device
    $a_monitoring = (int)getPOST("a_monitoring", $page_url, 0);
    $a_link = (int)getPOST("a_link", $page_url, 0);
    $a_handler = substr(trim((string)getPOST("a_handler", $page_url, '')), 0, 50);
    $a_tag_add = (int)getPOST("a_tag_add", $page_url, 0);
    $a_tag_value = trim((string)getPOST("a_tag_value", $page_url, ''));
    $a_tag_del = (int)getPOST("a_tag_del", $page_url, 0);
    $a_macro_add = (int)getPOST("a_macro_add", $page_url, 0);
    $a_macro_value = trim((string)getPOST("a_macro_value", $page_url, ''));
    $a_macro_del = (int)getPOST("a_macro_del", $page_url, 0);

    $tag_changed = 0;
    $macro_changed = 0;


    foreach ($dev_id as $val) {
        $id = (int)$val;
        if ($id <= 0) {
            continue;
        }

        // the device of eye is the host of the monitoring system, its parameters live
        // in the row of the device
        $cur_device = get_record_sql($db_link, "SELECT * FROM devices WHERE id = ? AND deleted = 0", [$id]);
        if (!$cur_device) {
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
        if (getPOST("e_monitoring", $page_url) !== null) {
            // monitoring_old keeps the value which the monitoring system knows, the
            // host of a switched off device is disabled there by the export
            set_monitoring($device, (int)$cur_device['monitoring'], $a_monitoring);
            if ($device['monitoring'] === 0) {
                $device['monitoring_status'] = 'UP';
            }
        }
        if (getPOST("e_link", $page_url) !== null) {
            $device['link_check'] = $a_link;
        }
        if (getPOST("e_handler", $page_url) !== null) {
            $device['nagios_handler'] = $a_handler;
        }

        if (!empty($device)) {
            $ret = update_record($db_link, "devices", "id = ?", $device, [$id]);
            if (!$ret) {
                $all_ok = false;
            }
        }

        // the names of the global zabbix lists are assigned to the device itself, the
        // value of a tag or of a macro is the same for every device of the operation
        $binding_changed = 0;
        if (getPOST("e_tag_add", $page_url) !== null) {
            $binding_changed += mass_zabbix_device_binding($db_link, [$id], 'tags', $a_tag_add, $a_tag_value);
        }
        if (getPOST("e_tag_del", $page_url) !== null) {
            $binding_changed += mass_zabbix_device_unbind($db_link, [$id], 'tags', $a_tag_del);
        }
        if (getPOST("e_macro_add", $page_url) !== null) {
            $binding_changed += mass_zabbix_device_binding($db_link, [$id], 'macros', $a_macro_add, $a_macro_value);
        }
        if (getPOST("e_macro_del", $page_url) !== null) {
            $binding_changed += mass_zabbix_device_unbind($db_link, [$id], 'macros', $a_macro_del);
        }
        $tag_changed += $binding_changed;

        // the zabbix host of the device follows the changes of its monitoring state and
        // of its macros and tags
        $refresh = isset($device['monitoring']) || isset($device['link_check'])
            || isset($device['nagios_handler']) || $binding_changed > 0;
        if ($refresh) {
            $zbx_message = zabbix_record_sync_report($db_link, 'netdev', $id);
            if ($zbx_message !== '') {
                LOG_INFO($db_link, $cur_device['device_name'] . ': ' . $zbx_message);
            }
        }
    }

    if ($tag_changed > 0) {
        LOG_INFO($db_link, WEB_zabbix_mass_tags . ': ' . $tag_changed);
    }
    if ($macro_changed > 0) {
        LOG_INFO($db_link, WEB_zabbix_mass_macros . ': ' . $macro_changed);
    }
}

$message = $all_ok ? WEB_mass_success : WEB_mass_fail;
/* a class instead of inline styles: the block used to stay green even on Fail! */
print '<div class="result ' . ($all_ok ? 'ok' : 'err') . '">' . $message . '</div>';

?>
