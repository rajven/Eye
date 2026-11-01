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

<?php echo WEB_zabbix_model_templates_hint; ?>
<br><br>
<b><?php echo WEB_zabbix_macros_global . "&nbsp;"; ?></b>
<a href="/admin/customers/zabbixmacros.php"><?php echo WEB_submenu_zabbix_macros; ?></a>
<br>
<b><?php echo WEB_zabbix_tags_global . "&nbsp;"; ?></b>
<a href="/admin/customers/zabbixtags.php"><?php echo WEB_submenu_zabbix_tags; ?></a>
<br><br>
</form>
<br>
<a href="/admin/customers/devmodels.php"><?php echo WEB_submenu_device_models; ?></a>
</div>
<?php
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/footer.php");
?>
