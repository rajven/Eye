<?php
// Macros and tags of the zabbix host of one eye network device. The names live in
// the global lists of the settings, the device carries the values of them.
if (!defined("CONFIG")) {
    die("Not defined");
}

// Both kinds of the bindings are stored and edited the same way, only the names of
// the database and of the form elements differ. The prefixes of the form elements
// keep the blocks of the device form apart
function zabbix_device_kind_conf($kind)
{
    if ($kind === 'tags') {
        return [
            'caption' => WEB_zabbix_tags_device,
            'list_table' => 'zabbix_tags_list',
            'list_id_field' => 'tags_list_id',
            'bind_table' => 'zabbix_tags',
            'name_field' => 'tag_name',
            'value_field' => 'tag_value',
            'type_field' => '',
            'add_label' => WEB_zabbix_add_tag,
            'empty_label' => WEB_zabbix_tag_select_model_empty,
            'hint' => WEB_zabbix_tags_hint,
            'list_page' => '/admin/customers/zabbixtags.php',
            'list_menu' => WEB_submenu_zabbix_tags,
            'pre' => 't',
            'name_label' => WEB_zabbix_tag,
        ];
    }

    return [
        'caption' => WEB_zabbix_macros_device,
        'list_table' => 'zabbix_macros_list',
        'list_id_field' => 'macros_list_id',
        'bind_table' => 'zabbix_macros',
        'name_field' => 'macro_name',
        'value_field' => 'macro_value',
        'type_field' => 'macro_type',
        'add_label' => WEB_zabbix_add_macro,
        'empty_label' => WEB_zabbix_macro_select_model_empty,
        'hint' => WEB_zabbix_macros_hint,
        'list_page' => '/admin/customers/zabbixmacros.php',
        'list_menu' => WEB_submenu_zabbix_macros,
        'pre' => 'm',
        'name_label' => WEB_zabbix_macro,
    ];
}

// Bindings of the device. The name of the global list is taken with a left join, so
// a binding is shown even when its name disappeared from the list
function zabbix_device_bindings($db, $device_id, $kind)
{
    $conf = zabbix_device_kind_conf($kind);
    $device_id = (int)$device_id;
    if ($device_id <= 0) {
        return [];
    }

    // the description of the macro is not a column of the tags list
    $type_column = $conf['type_field'] !== '' ? ', l.' . $conf['type_field'] : '';
    $rows = get_records_sql($db,
        "SELECT b.*, l." . $conf['name_field'] . " AS bound_name$type_column
         FROM " . $conf['bind_table'] . " b
         LEFT JOIN " . $conf['list_table'] . " l ON b." . $conf['list_id_field'] . " = l.id
         WHERE b.device_id = ? ORDER BY bound_name", [$device_id]);

    return empty($rows) ? [] : $rows;
}

// Names of the global list which the device does not use yet
function zabbix_device_free_names($db, $device_id, $kind)
{
    $conf = zabbix_device_kind_conf($kind);

    $taken = [];
    foreach (zabbix_device_bindings($db, $device_id, $kind) as $row) {
        $taken[(int)$row[$conf['list_id_field']]] = 1;
    }

    $free = [];
    foreach (get_records_sql($db,
        "SELECT * FROM " . $conf['list_table'] . " ORDER BY " . $conf['name_field'], []) as $row) {
        if (isset($taken[(int)$row['id']])) {
            continue;
        }
        $item = ['id' => $row['id'], 'macro' => $row[$conf['name_field']]];
        if (!empty($row['description'])) {
            $item['description'] = $row['description'];
        }
        $free[] = $item;
    }

    return $free;
}

// Table of the block: the names the device uses, their values, the assign flag and
// the unlink check box. The table is a part of the form of the device itself
function print_zabbix_device_block($db, $device_id, $kind)
{
    if ((int)$device_id <= 0 || !zabbix_record_supported($db)) {
        return;
    }

    $conf = zabbix_device_kind_conf($kind);
    $pre = $conf['pre'];
    $bindings = zabbix_device_bindings($db, $device_id, $kind);
    $free = zabbix_device_free_names($db, $device_id, $kind);

    print "<table class=\"data\">\n";
    print "<tr align=\"center\"><td colspan=\"5\"><b>" . $conf['caption'] . "</b></td></tr>\n";

    // the type of the macro is a property of the global name, the tags have none
    $with_type = $conf['type_field'] !== '';

    print "<tr align=\"center\">\n";
    print "<td><b>" . $conf['name_label'] . "</b></td>\n";
    if ($with_type) {
        print "<td><b>" . WEB_zabbix_macro_type . "</b></td>\n";
    }
    print "<td><b>" . WEB_zabbix_value . "</b></td>\n";
    print "<td><b>" . WEB_zabbix_used . "</b></td>\n";
    print "<td><b>" . WEB_zabbix_remove . "</b></td>\n";
    print "</tr>\n";

    if (empty($bindings)) {
        print "<tr><td class=\"data\" colspan=\"5\">" . WEB_ipset_empty . "</td></tr>\n";
    }

    foreach ($bindings as $row) {
        $bind_id = (int)$row['id'];
        print "<tr align=center>\n";
        print "<td class=\"data\" width=200><input type=\"hidden\" name=\"{$pre}_id[]\" value=\"$bind_id\">"
            . htmlspecialchars((string)($row['bound_name'] ?? ''), ENT_QUOTES, 'UTF-8') . "</td>\n";
        if ($with_type) {
            print "<td class=\"data\">"
                . ((int)($row[$conf['type_field']] ?? 0) === 1 ? WEB_zabbix_macro_type_secret : WEB_zabbix_macro_type_text)
                . "</td>\n";
        }
        print "<td class=\"data\" width=140><input type=\"text\" name=\"{$pre}_value[" . $bind_id . "]\" value=\""
            . htmlspecialchars((string)($row[$conf['value_field']] ?? ''), ENT_QUOTES, 'UTF-8') . "\"></td>\n";
        print "<td class=\"data\">";
        print_qa_select("{$pre}_used[" . $bind_id . "]", $row['is_used']);
        print "</td>\n";
        print "<td class=\"data\" style='padding:0'><input type=\"checkbox\" name=\"{$pre}_del[" . $bind_id . "]\" value=\"1\"></td>\n";
        print "</tr>\n";
    }

    print "<tr><td colspan=\"5\" class=\"data\">\n";
    if (!empty($free)) {
        print "<b>" . $conf['add_label'] . ":&nbsp;</b>";
        if ($with_type) {
            print_zabbix_macro_id_select("{$pre}_add", $free, $conf['empty_label']);
        } else {
            print_zabbix_tag_id_select("{$pre}_add", $free, $conf['empty_label']);
        }
    } else {
        print $conf['add_label'] . ":&nbsp;" . WEB_zabbix_list_is_empty;
    }
    print "</td></tr>\n";

    // the block is a part of the form of the device, its own save button is saved
    // in the history of eye, the button of the device form stores the bindings too
    print "<tr><td colspan=\"5\">" . $conf['hint']
        . "<br><a href=\"" . $conf['list_page'] . "\">" . $conf['list_menu'] . "</a></td></tr>\n";
    print "</table>\n";
    print "<br>\n";
}

// The values, the assign flags and the unlink checks of the block are stored for the
// device, the names of the global list are not touched
function save_zabbix_device_block($db, $device_id, $kind)
{
    $device_id = (int)$device_id;
    if ($device_id <= 0 || !zabbix_record_supported($db)) {
        return;
    }

    $conf = zabbix_device_kind_conf($kind);
    $pre = $conf['pre'];

    $u_ids = getPOST("{$pre}_id", null, []);
    $u_values = getPOST("{$pre}_value", null, []);
    $u_used = getPOST("{$pre}_used", null, []);
    $u_del = getPOST("{$pre}_del", null, []);

    if (!empty($u_ids) && is_array($u_ids)) {
        foreach ($u_ids as $bind_id) {
            $bind_id = (int)$bind_id;
            if ($bind_id <= 0) {
                continue;
            }

            $row = get_record_sql($db,
                "SELECT * FROM " . $conf['bind_table'] . " WHERE id = ? AND device_id = ?",
                [$bind_id, $device_id]);
            if (empty($row)) {
                continue;
            }

            if (isset($u_del[$bind_id])) {
                delete_record($db, $conf['bind_table'], "id = ?", [$bind_id]);
                continue;
            }

            update_record($db, $conf['bind_table'], "id = ?", [
                $conf['value_field'] => substr(trim((string)($u_values[$bind_id] ?? '')), 0, 250),
                'is_used' => (int)($u_used[$bind_id] ?? 0),
            ], [$bind_id]);
        }
    }

    // a name of the global list becomes a binding of the device
    $add_list_id = (int)getPOST("{$pre}_add", null, 0);
    if ($add_list_id > 0) {
        $list_row = get_record($db, $conf['list_table'], "id = ?", [$add_list_id]);
        $exists = get_record_sql($db,
            "SELECT id FROM " . $conf['bind_table'] . " WHERE " . $conf['list_id_field'] . " = ? AND device_id = ?",
            [$add_list_id, $device_id]);
        if (!empty($list_row) && empty($exists)) {
            insert_record($db, $conf['bind_table'], [
                $conf['list_id_field'] => $add_list_id,
                'device_id' => $device_id,
                $conf['value_field'] => '',
                'is_used' => 1,
            ]);
        }
    }
}
