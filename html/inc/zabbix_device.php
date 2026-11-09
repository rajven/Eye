<?php
// Macros and tags of the zabbix host of one eye network device. The names live in
// the global lists of the settings, the device carries the values of them.
if (!defined("CONFIG")) {
    die("Not defined");
}

// A macro of the secret type lives in the base ciphered, as the passwords of the
// equipment do, and its value never comes back to the interface: the form shows the
// mask instead. The mask is the same line which zabbix itself answers for a secret
// macro, so a form which brings the mask back changes nothing, in eye and in zabbix
// allthe same
if (!defined('ZABBIX_MACRO_MASK')) {
    define('ZABBIX_MACRO_MASK', '********');
}

// 1 when the name of the global list asks for a secret value
function zabbix_macro_is_secret($type)
{
    return (int)$type === 1;
}

// 1 when the value which the form brought is the mask of a secret which nobody touched
function zabbix_macro_is_mask($value)
{
    return preg_match('/^\*+$/', trim((string)$value)) === 1;
}

// the value of the binding before it goes into the base: a secret is ciphered, the rest
// stays as it is, and an empty value stays empty (the cipher of nothing is nothing)
function zabbix_macro_store($value, $secret)
{
    $value = (string)$value;
    if ($value === '' || !$secret) {
        return $value;
    }
    return crypt_string($value);
}

// the value of the binding on its way out, to the host of zabbix. a base which was not
// upgraded yet keeps a secret in the plain text: such a row has no cipher inside, so
// what can not be opened is given away as it stands, and the update patch of the
// version 3-2-8 cipheres it later
function zabbix_macro_open($value, $secret)
{
    $value = trim((string)$value);
    if ($value === '' || !$secret) {
        return $value;
    }

    $plain = decrypt_string($value);
    if (!is_string($plain) || $plain === '') {
        return $value;
    }
    // a plain text which is not a cipher of anything can not hold the signs no text of
    // a human being holds
    if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $plain)) {
        return $value;
    }

    return $plain;
}

// the type of a name of the global list is a property of the list, so the values of the
// devices follow it: the map spares a query for every binding of a block
function zabbix_macro_types($db)
{
    $types = [];
    foreach (get_records_sql($db, "SELECT id, macro_type FROM zabbix_macros_list", []) as $row) {
        $types[(int)$row['id']] = (int)$row['macro_type'];
    }
    return $types;
}

// A name of the global list moved from the plain text to the secret type or back: the
// values which the devices keep for it must follow the type at once, while a later
// export reads them by the new rule
function zabbix_macro_rettype($db, $list_id, $from_secret, $to_secret)
{
    $list_id = (int)$list_id;
    if ($list_id <= 0 || $from_secret === $to_secret) {
        return 0;
    }

    // the export of any device may read the values right after the move
    zabbix_record_device_rows_clear(null, 'macros');

    $moved = 0;
    foreach (get_records_sql($db, "SELECT id, macro_value FROM zabbix_macros WHERE macros_list_id = ?",
                             [$list_id]) as $row) {
        $value = zabbix_macro_open($row['macro_value'], $from_secret);
        update_record($db, 'zabbix_macros', 'id = ?',
            ['macro_value' => zabbix_macro_store($value, $to_secret)], [(int)$row['id']]);
        $moved++;
    }

    return $moved;
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
    // the types of the names of the global list: one map for the whole block instead of
    // a question for every row of it
    $types = $with_type ? zabbix_macro_types($db) : [];

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
        $secret = $with_type && zabbix_macro_is_secret($types[(int)$row[$conf['list_id_field']]] ?? 0);
        if ($with_type) {
            print "<td class=\"data\">"
                . ($secret ? WEB_zabbix_macro_type_secret : WEB_zabbix_macro_type_text)
                . "</td>\n";
        }
        // the value of a secret macro never comes back to the browser: the field holds
        // the mask, and the save which brings that mask back leaves the stored cipher as
        // it is - the same rule which zabbix keeps for its own secret macros
        $shown = $secret ? ZABBIX_MACRO_MASK : (string)($row[$conf['value_field']] ?? '');
        print "<td class=\"data\" width=140><input type=\"text\" name=\"{$pre}_value[" . $bind_id . "]\" value=\""
            . htmlspecialchars($shown, ENT_QUOTES, 'UTF-8') . '"'
            . ($secret ? ' title="' . htmlspecialchars(WEB_zabbix_macro_secret_hint, ENT_QUOTES, 'UTF-8') . '"' : '')
            . "></td>\n";
        print "<td class=\"data\">";
        print_qa_select("{$pre}_used[" . $bind_id . "]", $row['is_used']);
        print "</td>\n";
        print "<td class=\"data\" style='padding:0'><input type=\"checkbox\" name=\"{$pre}_del[" . $bind_id . "]\" value=\"1\"></td>\n";
        print "</tr>\n";
    }

    // the name of the global list is chosen in the searchable list, its value for the
    // device is typed beside it, so a new binding is made by one save of the form
    print "<tr class=\"zabbix-add\">\n";
    print "<td class=\"data\" colspan=\"" . ($with_type ? '2' : '1') . "\">";
    print "<b>" . $conf['add_label'] . ":&nbsp;</b>";
    if (!empty($free)) {
        if ($with_type) {
            print_zabbix_macro_id_select("{$pre}_add", $free, $conf['empty_label']);
        } else {
            print_zabbix_tag_id_select("{$pre}_add", $free, $conf['empty_label']);
        }
    } else {
        print WEB_zabbix_list_is_empty;
    }
    print "</td>\n";
    print "<td class=\"data\" width=140><input type=\"text\" name=\"{$pre}_add_value\" value=\"\"></td>\n";
    print "<td class=\"data\" colspan=\"2\"></td>\n";
    print "</tr>\n";

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

    // the type of a name of the global list decides whether its value is ciphered
    $types = $conf['type_field'] !== '' ? zabbix_macro_types($db) : [];

    // the rows of the bindings are read by the export of the same request: the cache of
    // them must not keep what this save is about to change
    zabbix_record_device_rows_clear($device_id, $kind);

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

            $secret = $conf['type_field'] !== ''
                && zabbix_macro_is_secret($types[(int)$row[$conf['list_id_field']]] ?? 0);
            $came = substr(trim((string)($u_values[$bind_id] ?? '')), 0, 250);

            $stored = [];
            // the mask of a secret which nobody touched keeps the cipher of the base
            if (!$secret || !zabbix_macro_is_mask($came)) {
                $stored[$conf['value_field']] = zabbix_macro_store($came, $secret);
            }
            $stored['is_used'] = (int)($u_used[$bind_id] ?? 0);

            update_record($db, $conf['bind_table'], "id = ?", $stored, [$bind_id]);
        }
    }

    // a name of the global list becomes a binding of the device with the value which
    // was typed in the same row of the form
    $add_list_id = (int)getPOST("{$pre}_add", null, 0);
    if ($add_list_id > 0) {
        $add_value = substr(trim((string)getPOST("{$pre}_add_value", null, '')), 0, 250);
        $list_row = get_record($db, $conf['list_table'], "id = ?", [$add_list_id]);
        $exists = get_record_sql($db,
            "SELECT id FROM " . $conf['bind_table'] . " WHERE " . $conf['list_id_field'] . " = ? AND device_id = ?",
            [$add_list_id, $device_id]);
        if (!empty($list_row) && empty($exists)) {
            // a new binding of a secret name is ciphered at once: its value never lies
            // in the base in the plain text, not even between two saves
            $add_secret = $conf['type_field'] !== ''
                && zabbix_macro_is_secret($list_row[$conf['type_field']] ?? 0);
            insert_record($db, $conf['bind_table'], [
                $conf['list_id_field'] => $add_list_id,
                'device_id' => $device_id,
                $conf['value_field'] => zabbix_macro_store($add_value, $add_secret),
                'is_used' => 1,
            ]);
        }
    }
}

// Names of a global list, they are offered in the mass operations of the monitoring
// page exactly as in the form of the device
function zabbix_global_names($db, $kind)
{
    $conf = zabbix_device_kind_conf($kind);
    $rows = [];
    foreach (get_records_sql($db, "SELECT * FROM " . $conf['list_table'] . " ORDER BY " . $conf['name_field'], []) as $row) {
        $rows[] = ['id' => $row['id'], 'macro' => $row[$conf['name_field']], 'description' => $row['description']];
    }

    return $rows;
}

// A name of the global list is assigned to every device of the mass operation with the
// same value. An existing binding of the device gets the new value and is used again
function mass_zabbix_device_binding($db, $device_ids, $kind, $list_id, $value)
{
    $conf = zabbix_device_kind_conf($kind);
    $list_id = (int)$list_id;
    if ($list_id <= 0 || empty($device_ids)) {
        return 0;
    }

    $list_row = get_record($db, $conf['list_table'], "id = ?", [$list_id]);
    if (empty($list_row)) {
        LOG_WARNING($db, "Zabbix: the name $list_id of the " . $kind . " list is not found, the mass operation is skipped");
        return 0;
    }

    // update_record() answers success even when the access level refused the change,
    // so the level is asked here directly: a mass operation which wrote nothing must
    // not be reported as done - that is how the names of the tags and of the macros
    // appeared to be added and removed in silence
    if (!allow_update($db, $conf['bind_table'], 'add')
        && !allow_update($db, $conf['bind_table'], 'update')) {
        LOG_WARNING($db, "Zabbix: the access level of the user does not allow changing " . $conf['caption']
            . " (table " . $conf['bind_table'] . "), the mass operation is skipped");
        return 0;
    }

    $value = substr(trim((string)$value), 0, 250);
    $changed = 0;

    // the export of every device of the operation reads its bindings after this write
    zabbix_record_device_rows_clear(null, $kind);

    foreach ($device_ids as $device_id) {
        $device_id = (int)$device_id;
        if ($device_id <= 0) {
            continue;
        }

        $row = get_record_sql($db,
            "SELECT * FROM " . $conf['bind_table'] . " WHERE " . $conf['list_id_field'] . " = ? AND device_id = ?",
            [$list_id, $device_id]);
        // the same rule as in the form of the device: the value of a secret name is
        // ciphered before it goes into the base
        $secret = $conf['type_field'] !== ''
            && zabbix_macro_is_secret($list_row[$conf['type_field']] ?? 0);
        $stored = zabbix_macro_store($value, $secret);

        if (!empty($row)) {
            update_record($db, $conf['bind_table'], "id = ?",
                [$conf['value_field'] => $stored, 'is_used' => 1], [(int)$row['id']]);
            $changed++;
        } elseif (insert_record($db, $conf['bind_table'], [
                $conf['list_id_field'] => $list_id,
                'device_id' => $device_id,
                $conf['value_field'] => $stored,
                'is_used' => 1,
            ])) {
            $changed++;
        }
    }

    return $changed;
}

// A name of the global list is taken away from every device of the mass operation.
// The binding is removed, so the host of the device loses the macro or the tag too
function mass_zabbix_device_unbind($db, $device_ids, $kind, $list_id)
{
    $conf = zabbix_device_kind_conf($kind);
    $list_id = (int)$list_id;
    if ($list_id <= 0 || empty($device_ids)) {
        return 0;
    }

    // the same rule as in the binding: the export which follows must see the removal
    zabbix_record_device_rows_clear(null, $kind);

    if (!allow_update($db, $conf['bind_table'], 'del')) {
        LOG_WARNING($db, "Zabbix: the access level of the user does not allow removing " . $conf['caption']
            . " (table " . $conf['bind_table'] . "), the mass operation is skipped");
        return 0;
    }

    $changed = 0;
    foreach ($device_ids as $device_id) {
        $device_id = (int)$device_id;
        if ($device_id <= 0) {
            continue;
        }

        $row = get_record_sql($db,
            "SELECT * FROM " . $conf['bind_table'] . " WHERE " . $conf['list_id_field'] . " = ? AND device_id = ?",
            [$list_id, $device_id]);
        if (!empty($row)) {
            delete_record($db, $conf['bind_table'], "id = ?", [(int)$row['id']]);
            $changed++;
        }
    }

    return $changed;
}

// Number of the used macros and of the used tags of every device of the list, the
// monitoring page shows them beside the host of the device
function zabbix_device_binding_counts($db, $device_ids)
{
    $counts = [];
    $ids = array_values(array_unique(array_map('intval', (array)$device_ids)));
    $ids = array_values(array_filter($ids, function ($id) {
        return $id > 0;
    }));
    if (empty($ids)) {
        return $counts;
    }

    foreach (['macros' => 'zabbix_macros', 'tags' => 'zabbix_tags'] as $kind => $table) {
        $bind_id = ($kind === 'macros') ? 'macros_list_id' : 'tags_list_id';
        $rows = get_records_sql($db,
            "SELECT device_id, COUNT(*) AS cnt FROM " . $table . " WHERE is_used = 1 AND device_id IN ("
            . implode(',', array_fill(0, count($ids), '?')) . ") GROUP BY device_id", $ids);
        foreach ($rows as $row) {
            $counts[(int)$row['device_id']][$kind] = (int)$row['cnt'];
        }
    }

    return $counts;
}
