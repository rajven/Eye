<?php
require_once($_SERVER['DOCUMENT_ROOT'] . "/inc/auth.php");
require_once($_SERVER['DOCUMENT_ROOT'] . "/inc/languages/" . HTML_LANG . ".php");

if (!defined("CONFIG")) die("Not defined");

$page_url = null;

// the parameters of the devices is a mass action of the list, and the right for it is looked up before the first
// record is touched: the token of the csrf proves who sent the request, it says nothing
// about what that reader may do
if (!acl_can_edit()) {
    acl_denied($db_link, WEB_title_devices);
}

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
    $zbx_failed = [];
    $zbx_occupied = [];
    $zbx_unwritten = [];

    // what every mark of the dialog writes into: the table of it and its caption. the
    // mass operation of a list touches the record of the device, the bindings of its
    // zabbix names, and nothing else
    $asks = [
        ['devices', WEB_devices, ['e_set_type', 'e_set_model', 'e_set_building', 'e_set_snmp_version',
                                  'e_set_ro_community', 'e_set_rw_community', 'e_monitoring', 'e_link',
                                  'e_handler'], []],
        ['zabbix_tags', WEB_zabbix_tags_device, ['e_tag_add', 'e_tag_del'], [$a_tag_add, $a_tag_del]],
        ['zabbix_macros', WEB_zabbix_macros_device, ['e_macro_add', 'e_macro_del'], [$a_macro_add, $a_macro_del]],
    ];

    foreach ($asks as $ask) {
        list($table, $caption, $marks, $names) = $ask;

        $wanted = [];
        foreach ($marks as $mark) {
            if (getPOST($mark, $page_url) !== null) {
                $wanted[] = $mark;
            }
        }
        if (empty($wanted)) {
            continue;
        }

        // a row of the zabbix names which is marked but still shows the placeholder asks
        // for nothing: the dialog must say so instead of passing in silence
        foreach ($names as $num => $name) {
            if ((int)$name <= 0 && getPOST($marks[$num], $page_url) !== null) {
                print '<div class="result err">' . sprintf(WEB_mass_no_name, $caption) . '</div>';
                exit;
            }
        }

        // update_record() and delete_record() answer success even when the access level
        // refused the change, so an operator without the right to a table used to get a
        // green 'Done' above a base where nothing had moved. the level is asked here
        if (!allow_update($db_link, $table, 'add') && !allow_update($db_link, $table, 'update')
            && !allow_update($db_link, $table, 'del')) {
            LOG_WARNING($db_link, "Mass operation: the access level of the user does not allow changing $table");
            print '<div class="result err">' . sprintf(WEB_mass_no_rights, $caption) . '</div>';
            exit;
        }
    }

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
        // a mark of an add with the name of the list chosen and with the devices in the
        // selection can not produce zero records: that is a broken or a missing table of
        // the bindings, and the answer must not be green. a removal of a name which is
        // not bound is the usual case, so zero changes there is not a failure
        if (getPOST("e_tag_add", $page_url) !== null) {
            $done = mass_zabbix_device_binding($db_link, [$id], 'tags', $a_tag_add, $a_tag_value);
            $tag_changed += $done;
            $binding_changed += $done;
            if ($done == 0) {
                $all_ok = false;
                $zbx_unwritten[] = $cur_device['device_name'] . ' (' . WEB_zabbix_tags_device . ')';
            }
        }
        if (getPOST("e_tag_del", $page_url) !== null) {
            $binding_changed += mass_zabbix_device_unbind($db_link, [$id], 'tags', $a_tag_del);
        }
        if (getPOST("e_macro_add", $page_url) !== null) {
            $done = mass_zabbix_device_binding($db_link, [$id], 'macros', $a_macro_add, $a_macro_value);
            $macro_changed += $done;
            $binding_changed += $done;
            if ($done == 0) {
                $all_ok = false;
                $zbx_unwritten[] = $cur_device['device_name'] . ' (' . WEB_zabbix_macros_device . ')';
            }
        }
        if (getPOST("e_macro_del", $page_url) !== null) {
            $binding_changed += mass_zabbix_device_unbind($db_link, [$id], 'macros', $a_macro_del);
        }

        // the zabbix host of the device follows the changes of its monitoring state and
        // of its macros and tags
        $refresh = isset($device['monitoring']) || isset($device['link_check'])
            || isset($device['nagios_handler']) || $binding_changed > 0;
        if ($refresh) {
            // the export of the host is a part of the operation: while zabbix refused it
            // the operation is not finished, and the refusal must be read on the screen
            // and not only in the work log - the names of the tags and of the macros stay
            // written in eye and invisible in the monitoring system
            $zbx_result = zabbix_record_sync($db_link, 'netdev', $id);
            $zbx_action = $zbx_result['action'] ?? '';
            if (!in_array($zbx_action, ['unsupported', 'skipped'], true)) {
                $zbx_message = zabbix_record_sync_message($db_link, $zbx_result);
                if ($zbx_message !== '') {
                    LOG_INFO($db_link, $cur_device['device_name'] . ': ' . $zbx_message);
                }
            }
            if ($zbx_action === 'failed') {
                $all_ok = false;
                $zbx_failed[] = $cur_device['device_name'];
            }
            // a host which belongs to another record of the same address is left alone:
            // the operation really did nothing to that device, and the screen must say so
            if ($zbx_action === 'occupied') {
                $zbx_occupied[] = $cur_device['device_name'];
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
if (!empty($zbx_unwritten)) {
    $message = sprintf(WEB_mass_not_written, implode(', ', array_slice($zbx_unwritten, 0, 3)));
} elseif (!empty($zbx_failed)) {
    // the names of the first few devices are enough to understand where to look
    $message = sprintf(WEB_mass_zabbix_failed, implode(', ', array_slice($zbx_failed, 0, 3)));
} elseif (!empty($zbx_occupied)) {
    $message = sprintf(WEB_mass_zabbix_occupied, implode(', ', array_slice($zbx_occupied, 0, 3)));
    $all_ok = false;
}
/* a class instead of inline styles: the block used to stay green even on Fail! */
print '<div class="result ' . ($all_ok ? 'ok' : 'err') . '">' . $message . '</div>';

?>
