<?php

$default_displayed=25;
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/auth.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/languages/" . HTML_LANG . ".php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/vendorfilter.php");

// the list of the models only shows them, creates and removes the new ones: every
// model is edited in its own form, together with the templates of zabbix

if (getPOST("remove")) {
    $f_ids = getPOST("f_id", null, []);
    if (!empty($f_ids) && is_array($f_ids)) {
        $f_ids = array_map('intval', array_filter($f_ids, fn($id) => $id >= 10000));
        foreach ($f_ids as $id) {
            // set default - Unknown computer
            update_records($db_link, "devices", "device_model_id = ?", ['device_model_id' => 87], [$id]);
            delete_records($db_link, "zabbix_templates", "device_model_id = ?", [$id]);
            delete_record($db_link, "device_models", "id = ?", [$id]);
        }
    }
    header("Location: " . $_SERVER["REQUEST_URI"]);
    exit;
}

if (getPOST("create")) {
    $model_name = trim(getPOST("new_model", null, ''));
    if ($model_name !== '') {
        $max_record = get_record_sql($db_link, "SELECT MAX(id) AS max_id FROM device_models");
        $f_vendor_id = (int)getPOST("new_vendor_id", null, 1);
        $next_id = (isset($max_record['max_id']) && $max_record['max_id'] >= 10000)
            ? (int)$max_record['max_id'] + 1
            : 10000;

        $new = [
            'id'         => $next_id,
            'vendor_id'  => $f_vendor_id,
            'model_name' => $model_name
        ];
        $new_id = insert_record($db_link, "device_models", $new);
        if (!empty($new_id)) {
            // a new model is empty, the user is taken to its form at once
            header("Location: editmodel.php?id=" . (int)$new_id);
            exit;
        }
    }
    header("Location: " . $_SERVER["REQUEST_URI"]);
    exit;
}

unset($_POST);

$zabbix_enabled = is_zabbix_enabled();

require_once ($_SERVER['DOCUMENT_ROOT']."/inc/header.php");
print_control_submenu($page_url);

?>

<div id="cont">
    <br>
    <form name="def" action="devmodels.php" method="post">

        <table class="data">
            <tr>
                <td><b><?php echo WEB_list_models; ?></b></td>
                <td><?php print_vendor_select($db_link, 'vendor_select', $f_vendor_select); ?></td>
                <td><?php echo WEB_rows_at_page . "&nbsp:"; print_row_at_pages('rows', $displayed); ?></td>
                <td><input type="submit" name="OK" value="<?php echo WEB_btn_show; ?>"></td>
            </tr>
        </table>

        <?php
        $params = [];
        $filter = '';

        if (!empty($f_vendor_select)) {
            $filter = 'WHERE vendor_id = ?';
            $params[] = $f_vendor_select;
        }

        $countSQL = "SELECT COUNT(*) FROM device_models $filter";
        $count_records = get_single_field($db_link, $countSQL, $params);

        $total = ceil($count_records / $displayed);
        if ($page > $total) { $page = $total; }
        if ($page < 1) { $page = 1; }
        $start = ($page * $displayed) - $displayed;
        print_navigation($page_url, $page, $displayed, $count_records, $total);

        $sql = "SELECT * FROM device_models $filter ORDER BY vendor_id, model_name LIMIT ? OFFSET ?";
        $params[] = $displayed;
        $params[] = $start;
        $t_models = get_records_sql($db_link, $sql, $params);

        // the number of the devices of a model and of its templates of zabbix
        $model_devices = [];
        foreach (get_records_sql($db_link,
            "SELECT device_model_id, COUNT(id) AS cnt FROM devices WHERE deleted = 0 GROUP BY device_model_id", []) as $row) {
            $model_devices[(int)$row['device_model_id']] = (int)$row['cnt'];
        }

        $model_templates = [];
        if ($zabbix_enabled) {
            foreach (get_records_sql($db_link,
                "SELECT device_model_id, COUNT(id) AS cnt FROM zabbix_templates GROUP BY device_model_id", []) as $row) {
                $model_templates[(int)$row['device_model_id']] = (int)$row['cnt'];
            }
        }
        ?>

        <br>
        <table class="data">
            <tr align="center">
                <td><input type="checkbox" onClick="checkAll(this.checked);"></td>
                <td><b>Id</b></td>
                <td><b><?php echo WEB_cell_name; ?></b></td>
                <td><b><?php echo WEB_cell_poe_in; ?></b></td>
                <td><b><?php echo WEB_cell_poe_out; ?></b></td>
                <td><b><?php echo WEB_nagios_template; ?></b></td>
                <td><b><?php echo WEB_devices; ?></b></td>
<?php if ($zabbix_enabled) { ?>
                <td><b><?php echo WEB_zabbix_templates; ?></b></td>
<?php } ?>
            </tr>
            <?php
            foreach ($t_models as $row) {
                $is_system = ($row['id'] < 10000);
                // a system model belongs to the installation, it can not be removed
                $checkbox = $is_system
                    ? "<input type=\"checkbox\" disabled title=\"" . WEB_model_system . "\">"
                    : "<input type=\"checkbox\" name=\"f_id[{$row['id']}]\" value=\"{$row['id']}\">";
                $model_page = get_device_model_page($row['id']);
                $model_link = create_url(get_device_model_name($db_link, $row['id']), $model_page);

                echo "<tr align=center>\n";
                echo "<td class=\"data\" style='padding:0'>$checkbox</td>\n";
                echo "<td class=\"data\">{$row['id']}</td>\n";
                echo "<td class=\"data\">$model_link</td>\n";
                echo "<td class=\"data\">" . get_qa((int)$row['poe_in']) . "</td>\n";
                echo "<td class=\"data\">" . get_qa((int)$row['poe_out']) . "</td>\n";
                echo "<td class=\"data\">" . htmlspecialchars((string)$row['nagios_template'], ENT_QUOTES, 'UTF-8') . "</td>\n";
                echo "<td class=\"data\">" . ($model_devices[(int)$row['id']] ?? 0) . "</td>\n";
                if ($zabbix_enabled) {
                    echo "<td class=\"data\">" . ($model_templates[(int)$row['id']] ?? 0) . "</td>\n";
                }
                echo "</tr>\n";
            }
            ?>
        </table>

        <div style="margin-top: 15px; display: flex; justify-content: space-between; align-items: center;">
            <div>
                <input type="submit"
                       name="remove"
                       value="<?php echo WEB_btn_delete; ?>"
                       onclick="return confirm('<?php echo WEB_msg_delete; ?>?')"
                       <?php if (empty($t_models) || !array_filter($t_models, fn($r) => $r['id'] >= 10000)) echo 'disabled'; ?>>
            </div>

            <!-- a new model is created empty and its form opens at once -->
            <div style="display: flex; gap: 8px; align-items: center;">
                <?php print WEB_model_vendor."&nbsp"; print_vendor_select($db_link, 'new_vendor_id', $f_vendor_select ?: 1); ?>
                <input type="text" name="new_model" value="Unknown" placeholder="Model name" style="width: 120px;">
                <input type="submit" name="create" value="<?php echo WEB_btn_add; ?>">
            </div>
        </div>

    </form>

<?php
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/footer.php");
?>
