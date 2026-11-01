<?php

$default_displayed = 50;
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/auth.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/languages/" . HTML_LANG . ".php");

// the zabbix macros list is a part of the monitoring settings
if (!is_zabbix_enabled()) {
    print WEB_zabbix_disabled;
    exit;
}

// the list is global, only the name of the macro can be filtered
$f_macros_name = trim((string)getParam('macros_name', $page_url, ''));
$_SESSION[$page_url]['macros_name'] = $f_macros_name;

// all macros of the zabbix templates are offered to not type the names by hand
$zbx_macros = zabbix_all_template_macros();

if (getPOST("save") !== null) {
    $f_ids = getPOST("f_id", null, []);
    $m_names = getPOST("m_name", null, []);
    $m_types = getPOST("m_type", null, []);
    $m_descriptions = getPOST("m_description", null, []);

    if (!empty($f_ids) && is_array($f_ids)) {
        foreach ($f_ids as $macro_list_id) {
            $macro_list_id = (int)$macro_list_id;
            if ($macro_list_id <= 0) {
                continue;
            }

            if (empty(get_record($db_link, "zabbix_macros_list", "id = ?", [$macro_list_id]))) {
                continue;
            }

            $name = normalizer_zabbix_macro_name($m_names[$macro_list_id] ?? '');
            // an empty name is not a macro, the record is left as it is
            if ($name === '') {
                LOG_WARNING($db_link, "Zabbix macro id $macro_list_id has an empty name! Skipped.");
                continue;
            }

            // the macro name is unique in the global list
            $duplicate = get_record_sql($db_link, "SELECT id FROM zabbix_macros_list WHERE macro_name = ? AND id <> ?",
                [$name, $macro_list_id]);
            if (!empty($duplicate)) {
                LOG_WARNING($db_link, "Zabbix macro $name already exists with id " . $duplicate['id'] . "! Skipped.");
                continue;
            }

            update_record($db_link, "zabbix_macros_list", "id = ?", [
                'macro_name' => $name,
                'macro_type' => (int)($m_types[$macro_list_id] ?? 0),
                'description' => substr(trim((string)($m_descriptions[$macro_list_id] ?? '')), 0, 250),
            ], [$macro_list_id]);
        }
    }

    header("Location: " . $_SERVER["REQUEST_URI"]);
    exit;
}

if (getPOST("remove") !== null) {
    $f_ids = getPOST("f_id", null, []);
    if (!empty($f_ids) && is_array($f_ids)) {
        foreach ($f_ids as $macro_list_id) {
            $macro_list_id = (int)$macro_list_id;
            if ($macro_list_id <= 0) {
                continue;
            }
            // the macros of the models are removed together with the macro of the list
            delete_records($db_link, "zabbix_macros", "macros_list_id = ?", [$macro_list_id]);
            delete_record($db_link, "zabbix_macros_list", "id = ?", [$macro_list_id]);
        }
    }
    header("Location: " . $_SERVER["REQUEST_URI"]);
    exit;
}

if (getPOST("create") !== null) {
    // a name typed by hand wins over the name chosen in the list of the zabbix macros
    $new_name = trim((string)getPOST("new_name", null, ''));
    if ($new_name === '') {
        $new_name = trim((string)getPOST("new_name_pick", null, ''));
    }
    $new_name = normalizer_zabbix_macro_name($new_name);
    $new_type = (int)getPOST("new_type", null, 0);
    $new_description = substr(trim((string)getPOST("new_description", null, '')), 0, 250);

    if ($new_name !== '') {
        $exists = get_record_sql($db_link, "SELECT id FROM zabbix_macros_list WHERE macro_name = ?", [$new_name]);
        if (!empty($exists)) {
            LOG_WARNING($db_link, "Zabbix macro $new_name is already in the list!");
        } else {
            insert_record($db_link, "zabbix_macros_list", [
                'macro_name' => $new_name,
                'macro_type' => $new_type,
                'description' => $new_description,
            ]);
        }
    }

    header("Location: " . $_SERVER["REQUEST_URI"]);
    exit;
}

// Add the macros of the zabbix templates which are not in the list yet
if (getPOST("load") !== null) {
    $zabbix_templates = zabbix_template_list();
    $template_ids = [];
    foreach ($zabbix_templates as $template) {
        $template_ids[] = (int)$template['id'];
    }

    foreach (zabbix_template_macros($template_ids) as $macro) {
        $exists = get_record_sql($db_link, "SELECT id FROM zabbix_macros_list WHERE macro_name = ?", [$macro['macro']]);
        if (!empty($exists)) {
            continue;
        }
        insert_record($db_link, "zabbix_macros_list", [
            'macro_name' => $macro['macro'],
            'macro_type' => (int)$macro['type'],
            'description' => substr($macro['description'], 0, 250),
        ]);
    }

    header("Location: " . $_SERVER["REQUEST_URI"]);
    exit;
}

unset($_POST);
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/header.php");
print_control_submenu($page_url);

?>
<div id="cont">
    <br>
    <form name="filter" action="zabbixmacros.php" method="post">
        <table class="data">
            <tr>
                <td><b><?php echo WEB_zabbix_macros_page; ?></b></td>
                <td><?php echo WEB_zabbix_macro . "&nbsp:"; ?><input type="text" name="macros_name" value="<?php echo htmlspecialchars($f_macros_name); ?>"></td>
                <td><?php echo WEB_rows_at_page . "&nbsp:"; print_row_at_pages('rows', $displayed); ?></td>
                <td><input type="submit" name="OK" value="<?php echo WEB_btn_show; ?>"></td>
            </tr>
        </table>
    </form>

    <?php
    $params = [];
    $filter = '';
    if ($f_macros_name !== '') {
        $filter = "WHERE macro_name LIKE ?";
        $params[] = '%' . $f_macros_name . '%';
    }

    $count_records = (int)get_single_field($db_link, "SELECT COUNT(id) FROM zabbix_macros_list $filter", $params);

    $total = ceil($count_records / $displayed);
    if ($page > $total) { $page = $total; }
    if ($page < 1) { $page = 1; }
    $start = ($page * $displayed) - $displayed;
    print_navigation($page_url, $page, $displayed, $count_records, $total);

    $t_macros = get_records_sql($db_link,
        "SELECT * FROM zabbix_macros_list $filter ORDER BY macro_name LIMIT ? OFFSET ?",
        array_merge($params, [$displayed, $start]));

    // number of the eye network devices which apply every macro of the list
    $devices_count = [];
    foreach (get_records_sql($db_link, "SELECT macros_list_id, COUNT(device_id) AS cnt FROM zabbix_macros WHERE is_used = 1 GROUP BY macros_list_id", []) as $row) {
        $devices_count[(int)$row['macros_list_id']] = (int)$row['cnt'];
    }
    ?>

    <br>
    <form name="macros" action="zabbixmacros.php" method="post">
    <table class="data">
        <tr align="center">
            <td><input type="checkbox" onClick="checkAll(this.checked);"></td>
            <td><b>Id</b></td>
            <td><b><?php echo WEB_zabbix_macro; ?></b></td>
            <td><b><?php echo WEB_zabbix_macro_type; ?></b></td>
            <td><b><?php echo WEB_cell_description; ?></b></td>
            <td><b><?php echo WEB_zabbix_used_by_devices; ?></b></td>
        </tr>
<?php
foreach ($t_macros as $row) {
    print "<tr align=center>\n";
    print "<td class=\"data\" style='padding:0'><input type=\"checkbox\" name=\"f_id[]\" value=\"" . $row['id'] . "\"></td>\n";
    print "<td class=\"data\">" . $row['id'] . "</td>\n";
    print "<td class=\"data\"><input type=\"text\" name=\"m_name[" . $row['id'] . "]\" value=\"" . htmlspecialchars($row['macro_name']) . "\"></td>\n";
    print "<td class=\"data\">"; print_zabbix_macro_type_select("m_type[" . $row['id'] . "]", $row['macro_type']); print "</td>\n";
    print "<td class=\"data\"><input type=\"text\" name=\"m_description[" . $row['id'] . "]\" value=\"" . htmlspecialchars($row['description']) . "\" size=25></td>\n";
    print "<td class=\"data\">" . ($devices_count[(int)$row['id']] ?? 0) . "</td>\n";
    print "</tr>\n";
}
?>
        <tr>
            <td colspan="6" class="data"><b><?php echo WEB_zabbix_add_macro; ?></b></td>
        </tr>
        <tr>
            <td class="data"></td>
            <td class="data"></td>
            <td class="data" colspan="2">
                <?php print_zabbix_macro_select('new_name_pick', $zbx_macros, '', WEB_zabbix_macro_select_keep_empty); ?>
                <br><input type="text" name="new_name" value="" placeholder="<?php echo WEB_zabbix_macro_manual; ?>">
            </td>
            <td class="data"><?php print_zabbix_macro_type_select('new_type', 0); ?></td>
            <td class="data"><input type="text" name="new_description" value="" size=25></td>
            <td class="data"></td>
        </tr>
        <tr>
            <td colspan="2" class="data" align="right">
                <input type="submit" name="save" value="<?php echo WEB_btn_save; ?>" <?php if (empty($t_macros)) echo 'disabled'; ?>>
            </td>
            <td colspan="2" class="data" align="center">
                <input type="submit" name="remove" value="<?php echo WEB_btn_delete; ?>"
                       onclick="return confirm('<?php echo WEB_msg_delete; ?>?')"
                       <?php if (empty($t_macros)) echo 'disabled'; ?>>
            </td>
            <td colspan="2" class="data" align="right">
                <input type="submit" name="create" value="<?php echo WEB_btn_add; ?>">
            </td>
        </tr>
        <tr>
            <td colspan="6" class="data" align="right">
                <input type="submit" name="load" value="<?php echo WEB_zabbix_macro_load_all; ?>">
            </td>
        </tr>
    </table>
    </form>
</div>
<?php
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/footer.php");
?>
