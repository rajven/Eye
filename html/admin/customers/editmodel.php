<?php

$default_id = null;
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/auth.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/languages/" . HTML_LANG . ".php");
require_once ($_SERVER["DOCUMENT_ROOT"]."/inc/idfilter.php");

// the parameters of a device model and its templates of zabbix are edited here, the
// list of the models only shows them and creates the new ones
$model = get_record($db_link, "device_models", "id = ?", [$id]);
if (empty($model)) {
    header("Location: /admin/customers/devmodels.php");
    exit;
}

$model_id = (int)$model['id'];
// the models below 10000 belong to the installation, their vendor and name are fixed
$is_system = $model_id < 10000;
$disabled_attr = $is_system ? 'disabled' : '';
$zabbix_enabled = is_zabbix_enabled();

// Saving of the model and of the templates bound to it
if (getPOST("editmodel") !== null) {
    $new = [
        'poe_in'          => (int)getPOST("f_poe_in", null, 0),
        'poe_out'         => (int)getPOST("f_poe_out", null, 0),
        'nagios_template' => substr(trim((string)getPOST("f_nagios", null, '')), 0, 200),
    ];

    if (!$is_system) {
        $new['vendor_id']  = (int)getPOST("f_vendor", null, $model['vendor_id']);
        $new['model_name'] = substr(trim((string)getPOST("f_name", null, '')), 0, 200);
        if ($new['model_name'] === '') {
            $_SESSION[$page_url]['msg'] = WEB_model_name_empty;
            header("Location: " . $_SERVER["REQUEST_URI"]);
            exit;
        }
    }

    update_record($db_link, "device_models", "id = ?", $new, [$model_id]);

    header("Location: " . $_SERVER["REQUEST_URI"]);
    exit;
}

// the templates bound to the model are a separate form of the same page
if (getPOST("savetpl") !== null && $zabbix_enabled) {
    $template_name = trim((string)getPOST("f_template", null, ''));
    if ($template_name !== '') {
        // the template is looked up in zabbix by its technical name
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

    $unlink = getPOST("f_unlink", null, []);
    if (is_array($unlink)) {
        foreach (array_keys($unlink) as $link_id) {
            delete_record($db_link, "zabbix_templates", "id = ? AND device_model_id = ?",
                [(int)$link_id, $model_id]);
        }
    }

    header("Location: " . $_SERVER["REQUEST_URI"]);
    exit;
}

// Check the templates of the model against zabbix
if (getPOST("check") !== null && $zabbix_enabled) {
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

$model_title = get_device_model_name($db_link, $model_id);

// the devices of the model, they are the reason the templates matter
$devices_count = (int)get_single_field($db_link,
    "SELECT COUNT(id) FROM devices WHERE device_model_id = ? AND deleted = 0", [$model_id]);

$t_bound = [];
$free_templates = [];
if ($zabbix_enabled) {
    $t_bound = get_records_sql($db_link,
        "SELECT * FROM zabbix_templates WHERE device_model_id = ? ORDER BY template_name", [$model_id]);
    $bound_names = [];
    foreach ($t_bound as $row) {
        $bound_names[$row['template_name']] = 1;
    }
    // only the templates which the model does not use yet are offered
    foreach (zabbix_template_list() as $template) {
        if (isset($bound_names[$template['host']])) {
            continue;
        }
        $free_templates[] = $template;
    }
}
?>
<div id="cont">
<br>
<b><?php echo $model_title . " [" . $model_id . "]"; ?></b>
&nbsp|&nbsp
<?php
print_url(WEB_list_models, '/admin/customers/devmodels.php', 'linkButton');
print "&nbsp|&nbsp" . WEB_devices . ": " . $devices_count;
?>
<br><br>

<?php
if (!empty($_SESSION[$page_url]['msg'])) {
    print "<div id='msg'>" . $_SESSION[$page_url]['msg'] . "</div>\n";
    unset($_SESSION[$page_url]['msg']);
}
if ($is_system) {
    print "<div class='warn'>" . WEB_model_system . "</div>\n";
}
?>

<form name="def" action="editmodel.php?id=<?php echo $model_id; ?>" method="post">
<table class="data">
    <tr align="center">
        <td colspan="4"><b><?php echo WEB_model_parameters; ?></b></td>
    </tr>
    <tr align="center">
        <td><b><?php echo WEB_model_vendor; ?></b></td>
        <td><b><?php echo WEB_cell_name; ?></b></td>
        <td><b><?php echo WEB_cell_poe_in; ?></b></td>
        <td><b><?php echo WEB_cell_poe_out; ?></b></td>
    </tr>
    <tr align="center">
        <td class="data"><?php print_vendor_set($db_link, 'f_vendor', $model['vendor_id'], $is_system); ?></td>
        <td class="data"><input type="text" name="f_name" value="<?php echo htmlspecialchars($model['model_name'], ENT_QUOTES, 'UTF-8'); ?>" <?php echo $disabled_attr; ?> class="full-width"></td>
        <td class="data"><?php print_qa_select('f_poe_in', $model['poe_in']); ?></td>
        <td class="data"><?php print_qa_select('f_poe_out', $model['poe_out']); ?></td>
    </tr>
    <tr align="center">
        <td><b><?php echo WEB_nagios_template; ?></b></td>
        <td colspan="3"></td>
    </tr>
    <tr align="center">
        <td class="data"><input type="text" name="f_nagios" value="<?php echo htmlspecialchars($model['nagios_template'], ENT_QUOTES, 'UTF-8'); ?>" class="full-width"></td>
        <td class="data" colspan="3" align="right">
            <input type="submit" name="editmodel" value="<?php echo WEB_btn_save; ?>">
        </td>
    </tr>
</table>
</form>

<?php
if (!$zabbix_enabled) {
    // without zabbix the form of the parameters is the whole page
    print "<br>" . WEB_zabbix_disabled . "<br>\n";
    require_once ($_SERVER['DOCUMENT_ROOT']."/inc/footer.php");
    exit;
}
?>

<form name="zabbix" action="editmodel.php?id=<?php echo $model_id; ?>" method="post">
<table class="data">
    <tr align="center">
        <td colspan="4"><b><?php echo WEB_zabbix_templates; ?></b></td>
    </tr>
    <tr align="center">
        <td><b><?php echo WEB_zabbix_unlink; ?></b></td>
        <td><b><?php echo WEB_zabbix_template; ?></b></td>
        <td><b>Zabbix id</b></td>
        <td></td>
    </tr>
<?php
if (empty($t_bound)) {
    print "<tr><td class=\"data\" colspan=\"4\" align=\"center\">" . WEB_ipset_empty . "</td></tr>\n";
}
foreach ($t_bound as $row) {
    print "<tr align=center>\n";
    print "<td class=\"data\" style='padding:0'><input type=checkbox name=f_unlink[" . $row['id'] . "] value='1'></td>\n";
    print "<td class=\"data\">" . htmlspecialchars($row['template_name'], ENT_QUOTES, 'UTF-8') . "</td>\n";
    print "<td class=\"data\">" . (int)$row['template_id'] . "</td>\n";
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
            <input type="submit" name="savetpl" value="<?php echo WEB_btn_save; ?>">
        </td>
        <td colspan="2" class="data" align="right">
            <input type="submit" name="check" value="<?php echo WEB_zabbix_templates_check; ?>">
        </td>
    </tr>
</table>
</form>
<br>

<?php echo WEB_zabbix_model_templates_hint; ?>
<br><br>
<b><?php echo WEB_zabbix_macros_global . "&nbsp;"; ?></b>
<a href="/admin/customers/zabbixmacros.php"><?php echo WEB_submenu_zabbix_macros; ?></a>
<br>
<b><?php echo WEB_zabbix_tags_global . "&nbsp;"; ?></b>
<a href="/admin/customers/zabbixtags.php"><?php echo WEB_submenu_zabbix_tags; ?></a>
<br><br>
<?php
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/footer.php");
?>
