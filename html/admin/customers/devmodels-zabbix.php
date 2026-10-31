<?php

$default_id = null;
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/auth.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/languages/" . HTML_LANG . ".php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/idfilter.php");

// device model id
$model_id = (int)$id;
$model = get_record($db_link, "device_models", "id = ?", [$model_id]);
if (empty($model)) {
    header("Location: /admin/customers/devmodels.php");
    exit;
}

// the form is available only when zabbix is selected and configured
if (!is_zabbix_enabled()) {
    $page_error = WEB_zabbix_disabled;
}

// Bind the selected zabbix templates to the device model
if (getPOST("save") !== null && !isset($page_error)) {
    $add_templates = getPOST("f_templates", null, []);
    if (!empty($add_templates) && is_array($add_templates)) {
        foreach ($add_templates as $template_name) {
            $template_name = trim((string)$template_name);
            if ($template_name === '') {
                continue;
            }
            // the template is searched in zabbix by its technical name
            $template = zabbix_template_by_name($db_link, $template_name);
            if ($template === null) {
                LOG_WARNING($db_link, "Zabbix template $template_name not found in zabbix! Skipped.");
                continue;
            }
            $exists = get_record_sql($db_link,
                "SELECT id FROM zabbix_templates WHERE device_model_id = ? AND (template_id = ? OR template_name = ?)",
                [$model_id, (int)$template['id'], $template['host']]);
            if (!empty($exists)) {
                continue;
            }
            insert_record($db_link, "zabbix_templates", [
                'device_model_id' => $model_id,
                'template_id' => (int)$template['id'],
                'template_name' => $template['host'],
            ]);
        }
    }

    // unlink the checked templates
    $unlink = getPOST("f_unlink", null, []);
    if (!empty($unlink) && is_array($unlink)) {
        foreach ($unlink as $link_id => $value) {
            delete_record($db_link, "zabbix_templates", "id = ? AND device_model_id = ?",
                [(int)$link_id, $model_id]);
        }
    }

    // macros available for the model, the used macros are stored with their values
    $f_macro_ids = getPOST("m_id", null, []);
    $m_names = getPOST("m_name", null, []);
    $m_values = getPOST("m_value", null, []);
    $m_types = getPOST("m_type", null, []);
    $m_descriptions = getPOST("m_description", null, []);
    $m_used = getPOST("m_used", null, []);
    if (!empty($f_macro_ids) && is_array($f_macro_ids)) {
        foreach ($f_macro_ids as $macro_list_id) {
            $macro_list_id = (int)$macro_list_id;
            if ($macro_list_id <= 0) {
                continue;
            }

            $row = get_record_sql($db_link,
                "SELECT * FROM zabbix_macros_list WHERE id = ? AND device_model_id = ?",
                [$macro_list_id, $model_id]);
            if (empty($row)) {
                continue;
            }

            $name = normalizer_zabbix_macro_name($m_names[$macro_list_id] ?? '');
            $value = trim((string)($m_values[$macro_list_id] ?? ''));
            $type = (int)($m_types[$macro_list_id] ?? 0);
            $description = trim((string)($m_descriptions[$macro_list_id] ?? ''));
            $used = (int)($m_used[$macro_list_id] ?? 0);

            // an empty macro name removes the macro from the model
            if ($name === '') {
                delete_record($db_link, "zabbix_macros", "macros_list_id = ?", [$macro_list_id]);
                delete_record($db_link, "zabbix_macros_list", "id = ?", [$macro_list_id]);
                continue;
            }

            update_record($db_link, "zabbix_macros_list", "id = ?", [
                'macro_name' => $name,
                'macro_type' => $type,
                'default_value' => $value,
                'description' => $description,
            ], [$macro_list_id]);

            $used_row = get_record_sql($db_link, "SELECT id FROM zabbix_macros WHERE macros_list_id = ?", [$macro_list_id]);
            if (empty($used_row)) {
                insert_record($db_link, "zabbix_macros", [
                    'macros_list_id' => $macro_list_id,
                    'device_model_id' => $model_id,
                    'macro_name' => $name,
                    'macro_value' => $value,
                    'is_used' => $used,
                ]);
            } else {
                update_record($db_link, "zabbix_macros", "id = ?", [
                    'macro_name' => $name,
                    'macro_value' => $value,
                    'is_used' => $used,
                ], [(int)$used_row['id']]);
            }
        }
    }

    // new macros of the model
    $new_names = getPOST("new_name", null, []);
    $new_values = getPOST("new_value", null, []);
    $new_types = getPOST("new_type", null, []);
    $new_descriptions = getPOST("new_description", null, []);
    if (!empty($new_names) && is_array($new_names)) {
        for ($i = 0; $i < count($new_names); $i++) {
            $name = normalizer_zabbix_macro_name($new_names[$i]);
            if ($name === '') {
                continue;
            }
            $exists = get_record_sql($db_link,
                "SELECT id FROM zabbix_macros_list WHERE device_model_id = ? AND macro_name = ?",
                [$model_id, $name]);
            if (!empty($exists)) {
                continue;
            }
            $value = trim((string)($new_values[$i] ?? ''));
            $new_list_id = insert_record($db_link, "zabbix_macros_list", [
                'device_model_id' => $model_id,
                'macro_name' => $name,
                'macro_type' => (int)($new_types[$i] ?? 0),
                'default_value' => $value,
                'description' => trim((string)($new_descriptions[$i] ?? '')),
            ]);
            if ($new_list_id) {
                insert_record($db_link, "zabbix_macros", [
                    'macros_list_id' => (int)$new_list_id,
                    'device_model_id' => $model_id,
                    'macro_name' => $name,
                    'macro_value' => $value,
                    'is_used' => 1,
                ]);
            }
        }
    }

    header("Location: " . $_SERVER["REQUEST_URI"]);
    exit;
}

// Check the templates of the model against zabbix
if (getPOST("check") !== null && !isset($page_error)) {
    $check_result = zabbix_check_model_templates($db_link, $model_id);
    $page_msg = '';
    if (!empty($check_result['renamed'])) {
        $names = [];
        foreach ($check_result['renamed'] as $renamed) {
            $names[] = $renamed['row']['template_name'] . ' => ' . $renamed['name'];
        }
        $page_msg .= WEB_zabbix_template_renamed . " " . implode(', ', $names) . "<br>";
        LOG_INFO($db_link, WEB_zabbix_template_renamed . " " . implode(', ', $names));
    }
    if (!empty($check_result['missing'])) {
        $names = [];
        foreach ($check_result['missing'] as $missing) {
            $names[] = $missing['template_name'];
        }
        $page_msg .= WEB_zabbix_template_missing . " " . implode(', ', $names) . "<br>";
        LOG_WARNING($db_link, WEB_zabbix_template_missing . " " . implode(', ', $names));
    }
    if ($page_msg === '') {
        $page_msg = WEB_zabbix_templates_ok;
    }
    $_SESSION[$page_url]['msg'] = $page_msg;
    header("Location: " . $_SERVER["REQUEST_URI"]);
    exit;
}

// Import the user macros of the bound templates into the macro list of the model
if (getPOST("load") !== null && !isset($page_error)) {
    $template_ids = [];
    $bound = get_records_sql($db_link, "SELECT template_id FROM zabbix_templates WHERE device_model_id = ?", [$model_id]);
    foreach ($bound as $row) {
        if (!empty($row['template_id'])) {
            $template_ids[] = (int)$row['template_id'];
        }
    }

    foreach (zabbix_template_macros($template_ids) as $macro) {
        $exists = get_record_sql($db_link,
            "SELECT id FROM zabbix_macros_list WHERE device_model_id = ? AND macro_name = ?",
            [$model_id, $macro['macro']]);
        if (!empty($exists)) {
            continue;
        }
        $new_list_id = insert_record($db_link, "zabbix_macros_list", [
            'device_model_id' => $model_id,
            'macro_name' => $macro['macro'],
            'macro_type' => (int)$macro['type'],
            'default_value' => $macro['value'],
            'description' => $macro['description'],
        ]);
        if ($new_list_id) {
            insert_record($db_link, "zabbix_macros", [
                'macros_list_id' => (int)$new_list_id,
                'device_model_id' => $model_id,
                'macro_name' => $macro['macro'],
                'macro_value' => $macro['value'],
                'is_used' => 0,
            ]);
        }
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
<b><?php echo WEB_zabbix_model . " " . htmlspecialchars($model['model_name']) . " [" . $model_id . "]"; ?></b>
<br><br>

<?php
if (isset($page_error)) {
    print "<div id='msg'>" . $page_error . "</div>\n";
    require_once($_SERVER['DOCUMENT_ROOT'] . "/inc/footer.php");
    exit;
}

if (!empty($_SESSION[$page_url]['msg'])) {
    print "<div id='msg'>" . $_SESSION[$page_url]['msg'] . "</div>\n";
    unset($_SESSION[$page_url]['msg']);
}

$t_bound = get_records_sql($db_link, "SELECT * FROM zabbix_templates WHERE device_model_id = ? ORDER BY template_name", [$model_id]);
$bound_names = [];
foreach ($t_bound as $row) {
    $bound_names[$row['template_name']] = 1;
}

$free_templates = [];
foreach (zabbix_template_list() as $template) {
    if (isset($bound_names[$template['host']])) {
        continue;
    }
    $free_templates[] = $template['host'];
}
?>

<form name="zabbix" action="devmodels-zabbix.php?id=<?php echo $model_id; ?>" method="post">
<table class="data">
    <tr align="center">
        <td colspan="4"><b><?php echo WEB_zabbix_templates; ?></b></td>
    </tr>
    <tr align="center">
        <td><?php echo WEB_zabbix_unlink; ?></td>
        <td><b><?php echo WEB_zabbix_template; ?></b></td>
        <td><b>Zabbix id</b></td>
        <td></td>
    </tr>
<?php
if (empty($t_bound)) {
    print "<tr><td class=\"data\" colspan=\"4\">" . WEB_ipset_empty . "</td></tr>\n";
}
foreach ($t_bound as $row) {
    print "<tr align=center>\n";
    print "<td class=\"data\" style='padding:0'><input type=checkbox name=f_unlink[" . $row['id'] . "] value='1'></td>\n";
    print "<td class=\"data\">" . htmlspecialchars($row['template_name']) . "</td>\n";
    print "<td class=\"data\">" . $row['template_id'] . "</td>\n";
    print "<td class=\"data\"></td>\n";
    print "</tr>\n";
}
?>
    <tr>
        <td colspan="4" class="data">
            <b><?php echo WEB_zabbix_template_add . ":&nbsp"; ?></b>
            <select name="f_templates[]" id="f_templates" size="5" multiple>
<?php
foreach ($free_templates as $name) {
    print "<option value=\"" . htmlspecialchars($name) . "\">" . htmlspecialchars($name) . "</option>\n";
}
?>
            </select>
        </td>
    </tr>
    <tr>
        <td colspan="2" class="data" align="right">
            <button name="save" value="save"><?php print WEB_btn_save; ?></button>
        </td>
        <td colspan="2" class="data" align="right">
            <button name="check" value="check"><?php print WEB_zabbix_templates_check; ?></button>
        </td>
    </tr>
</table>
<br>

<?php
$t_list = get_records_sql($db_link, "SELECT * FROM zabbix_macros_list WHERE device_model_id = ? ORDER BY macro_name", [$model_id]);
$used_rows = [];
foreach (get_records_sql($db_link, "SELECT * FROM zabbix_macros WHERE device_model_id = ?", [$model_id]) as $row) {
    $used_rows[(int)$row['macros_list_id']] = $row;
}
?>
<table class="data">
    <tr align="center">
        <td colspan="5"><b><?php echo WEB_zabbix_macro_list; ?></b></td>
    </tr>
    <tr align="center">
        <td><b><?php echo WEB_zabbix_macro; ?></b></td>
        <td><b><?php echo WEB_zabbix_value; ?></b></td>
        <td><b><?php echo WEB_zabbix_default_value; ?>&nbsp<?php echo WEB_zabbix_macro_type; ?></b></td>
        <td><b><?php echo WEB_zabbix_used; ?></b></td>
        <td><b><?php echo WEB_cell_description; ?></b></td>
    </tr>
<?php
foreach ($t_list as $row) {
    $used = $used_rows[(int)$row['id']] ?? null;
    print "<tr align=center>\n";
    print "<td class=\"data\" width=170><input type=\"hidden\" name=\"m_id[]\" value=\"" . $row['id'] . "\">";
    print "<input type=\"text\" name=\"m_name[" . $row['id'] . "]\" value=\"" . htmlspecialchars($row['macro_name']) . "\"></td>\n";
    print "<td class=\"data\" width=120><input type=\"text\" name=\"m_value[" . $row['id'] . "]\" value=\"" . htmlspecialchars($used['macro_value'] ?? $row['default_value']) . "\"></td>\n";
    print "<td class=\"data\">" . htmlspecialchars($row['default_value']) . "&nbsp";
    print_zabbix_macro_type_select("m_type[" . $row['id'] . "]", $row['macro_type']);
    print "</td>\n";
    print "<td class=\"data\">";
    print_qa_select("m_used[" . $row['id'] . "]", $used['is_used'] ?? 0);
    print "</td>\n";
    print "<td class=\"data\"><input type=\"text\" name=\"m_description[" . $row['id'] . "]\" value=\"" . htmlspecialchars($row['description']) . "\" size=20></td>\n";
    print "</tr>\n";
}

// empty lines used to add new macros
for ($i = 0; $i < 3; $i++) {
    print "<tr align=center>\n";
    print "<td class=\"data\"><input type=\"text\" name=\"new_name[$i]\" value=\"\" placeholder=\"" . WEB_zabbix_macro . "\"></td>\n";
    print "<td class=\"data\"><input type=\"text\" name=\"new_value[$i]\" value=\"\" placeholder=\"" . WEB_zabbix_value . "\"></td>\n";
    print "<td class=\"data\">";
    print_zabbix_macro_type_select("new_type[$i]", 0);
    print "</td>\n";
    print "<td class=\"data\">" . WEB_btn_add . "</td>\n";
    print "<td class=\"data\"><input type=\"text\" name=\"new_description[$i]\" value=\"\" size=20></td>\n";
    print "</tr>\n";
}
?>
    <tr>
        <td colspan="3" class="data" align="right">
            <button name="save" value="save"><?php print WEB_btn_save; ?></button>
        </td>
        <td colspan="2" class="data" align="right">
            <button name="load" value="load"><?php print WEB_zabbix_macro_load; ?></button>
        </td>
    </tr>
</table>
<br>
<?php echo WEB_zabbix_macro_hint; ?>
</form>
<br>
<a href="/admin/customers/devmodels.php"><?php echo WEB_submenu_device_models; ?></a>
</div>
<?php
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/footer.php");
?>
