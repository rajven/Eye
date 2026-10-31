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

// Bind the selected zabbix template to the device model
if (getPOST("save") !== null && !isset($page_error)) {
    $template_name = trim((string)getPOST("f_template", null, ''));
    if ($template_name !== '') {
        // the template is searched in zabbix by its technical name
        $template = zabbix_template_by_name($template_name);
        if ($template === null) {
            LOG_WARNING($db_link, "Zabbix template $template_name not found in zabbix! Skipped.");
        } else {
            $exists = get_record_sql($db_link,
                "SELECT id FROM zabbix_templates WHERE device_model_id = ? AND (template_id = ? OR template_name = ?)",
                [$model_id, (int)$template['id'], $template['host']]);
            if (empty($exists)) {
                insert_record($db_link, "zabbix_templates", [
                    'device_model_id' => $model_id,
                    'template_id' => (int)$template['id'],
                    'template_name' => $template['host'],
                ]);
            }
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

    // macros used by the model, the names are taken from the macros list of the settings
    $u_ids = getPOST("u_id", null, []);
    $u_values = getPOST("u_value", null, []);
    $u_used = getPOST("u_used", null, []);
    $u_del = getPOST("u_del", null, []);
    if (!empty($u_ids) && is_array($u_ids)) {
        foreach ($u_ids as $used_id) {
            $used_id = (int)$used_id;
            if ($used_id <= 0) {
                continue;
            }

            $used_row = get_record_sql($db_link, "SELECT * FROM zabbix_macros WHERE id = ? AND device_model_id = ?",
                [$used_id, $model_id]);
            if (empty($used_row)) {
                continue;
            }

            if (isset($u_del[$used_id])) {
                delete_record($db_link, "zabbix_macros", "id = ?", [$used_id]);
                continue;
            }

            update_record($db_link, "zabbix_macros", "id = ?", [
                'macro_value' => substr(trim((string)($u_values[$used_id] ?? '')), 0, 250),
                'is_used' => (int)($u_used[$used_id] ?? 0),
            ], [$used_id]);
        }
    }

    // a macro of the global list becomes a used macro of the model
    $add_list_id = (int)getPOST("f_add_macro", null, 0);
    if ($add_list_id > 0) {
        $list_row = get_record($db_link, "zabbix_macros_list", "id = ?", [$add_list_id]);
        $used_exists = get_record_sql($db_link,
            "SELECT id FROM zabbix_macros WHERE macros_list_id = ? AND device_model_id = ?",
            [$add_list_id, $model_id]);
        if (!empty($list_row) && empty($used_exists)) {
            insert_record($db_link, "zabbix_macros", [
                'macros_list_id' => $add_list_id,
                'device_model_id' => $model_id,
                // an empty value of the model takes the default value of the macros list
                'macro_value' => '',
                'is_used' => 1,
            ]);
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

// only the templates which are not bound to the model yet are offered
$free_templates = [];
foreach (zabbix_template_list() as $template) {
    if (isset($bound_names[$template['host']])) {
        continue;
    }
    $free_templates[] = $template;
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
            <?php print_zabbix_template_select('f_template', $free_templates); ?>
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
// macros used by the model, their names and default values come from the global macros list
$t_used = get_records_sql($db_link,
    "SELECT m.*, l.macro_name, l.macro_type, l.default_value, l.description
     FROM zabbix_macros m LEFT JOIN zabbix_macros_list l ON m.macros_list_id = l.id
     WHERE m.device_model_id = ? ORDER BY l.macro_name", [$model_id]);

$used_list_ids = [];
foreach ($t_used as $row) {
    $used_list_ids[(int)$row['macros_list_id']] = 1;
}

// the macros of the global list which are not used by the model yet
$free_macros = [];
foreach (get_records_sql($db_link, "SELECT * FROM zabbix_macros_list ORDER BY macro_name", []) as $row) {
    if (isset($used_list_ids[(int)$row['id']])) {
        continue;
    }
    $free_macros[] = ['id' => $row['id'], 'macro' => $row['macro_name'], 'description' => $row['default_value']];
}
?>
<table class="data">
    <tr align="center">
        <td colspan="5"><b><?php echo WEB_zabbix_macros_used; ?></b></td>
    </tr>
    <tr align="center">
        <td><b><?php echo WEB_zabbix_macro; ?></b></td>
        <td><b><?php echo WEB_zabbix_default_value; ?></b></td>
        <td><b><?php echo WEB_zabbix_value; ?></b></td>
        <td><b><?php echo WEB_zabbix_used; ?></b></td>
        <td><b><?php echo WEB_zabbix_remove; ?></b></td>
    </tr>
<?php
if (empty($t_used)) {
    print "<tr><td class=\"data\" colspan=\"5\">" . WEB_ipset_empty . "</td></tr>\n";
}
foreach ($t_used as $row) {
    print "<tr align=center>\n";
    print "<td class=\"data\" width=170><input type=\"hidden\" name=\"u_id[]\" value=\"" . $row['id'] . "\">"
        . htmlspecialchars($row['macro_name'] ?? '') . "</td>\n";
    print "<td class=\"data\">" . htmlspecialchars($row['default_value'] ?? '') . "</td>\n";
    print "<td class=\"data\" width=120><input type=\"text\" name=\"u_value[" . $row['id'] . "]\" value=\""
        . htmlspecialchars($row['macro_value']) . "\" placeholder=\"" . htmlspecialchars($row['default_value'] ?? '') . "\"></td>\n";
    print "<td class=\"data\">";
    print_qa_select("u_used[" . $row['id'] . "]", $row['is_used']);
    print "</td>\n";
    print "<td class=\"data\" style='padding:0'><input type=checkbox name=u_del[" . $row['id'] . "] value='1'></td>\n";
    print "</tr>\n";
}
?>
    <tr>
        <td colspan="5" class="data">
            <b><?php echo WEB_zabbix_add_macro . ":&nbsp"; ?></b>
            <?php print_zabbix_macro_id_select('f_add_macro', $free_macros, WEB_zabbix_macro_select_model_empty); ?>
        </td>
    </tr>
    <tr>
        <td colspan="5" class="data" align="right">
            <button name="save" value="save"><?php print WEB_btn_save; ?></button>
        </td>
    </tr>
</table>
<br>
<?php echo WEB_zabbix_macros_hint; ?>
<br><br>
<b><?php echo WEB_zabbix_macros_global . "&nbsp;"; ?></b>
<a href="/admin/customers/zabbixmacros.php"><?php echo WEB_submenu_zabbix_macros; ?></a>
<br><br>
<?php echo WEB_zabbix_macro_hint; ?>
</form>
<br>
<a href="/admin/customers/devmodels.php"><?php echo WEB_submenu_device_models; ?></a>
</div>
<?php
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/footer.php");
?>
