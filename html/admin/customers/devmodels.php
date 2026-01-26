<?php

$default_displayed=25;
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/auth.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/languages/" . HTML_LANG . ".php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/vendorfilter.php");

if (getPOST("save")) {
    $f_ids = getPOST("f_id", null, []);
    if (!empty($f_ids) && is_array($f_ids)) {
        // Преобразуем в целые числа
        $f_ids = array_map('intval', array_filter($f_ids, fn($id) => $id > 0));
        // Получаем все данные
        $r_ids       = array_map('intval', getPOST("r_id",       null, []));
        $f_vendors   = getPOST("f_vendor",   null, []);
        $f_names     = getPOST("f_name",     null, []);
        $f_poe_ins   = getPOST("f_poe_in",   null, []);
        $f_poe_outs  = getPOST("f_poe_out",  null, []);
        $f_nagios    = getPOST("f_nagios",   null, []);
        foreach ($f_ids as $save_id) {
            $idx = array_search($save_id, $r_ids, true);
            if ($idx === false) continue;
            $new = [
                'poe_in'  => !empty($f_poe_ins[$idx])  ? (int)$f_poe_ins[$idx]  : 0,
                'poe_out' => !empty($f_poe_outs[$idx]) ? (int)$f_poe_outs[$idx] : 0,
                'nagios_template' => trim($f_nagios[$idx] ?? '')
            ];
            // Для кастомных моделей (ID >= 10000)
            if ($save_id >= 10000) {
                $new['vendor_id']  = !empty($f_vendors[$idx]) ? (int)$f_vendors[$idx] : 1;
                $new['model_name'] = trim($f_names[$idx] ?? '');
            }

            update_record($db_link, "device_models", "id = ?", $new, [$save_id]);
        }
    }
    header("Location: " . $_SERVER["REQUEST_URI"]);
    exit;
}

if (getPOST("remove")) {
    $f_ids = getPOST("f_id", null, []);
    if (!empty($f_ids) && is_array($f_ids)) {
        $f_ids = array_map('intval', array_filter($f_ids, fn($id) => $id >= 10000));
        foreach ($f_ids as $id) {
            // set default - Unknown computer
            update_records($db_link, "devices", "device_model_id = ?", ['device_model_id' => 87], [$id]);
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
            'vendor_id'  => (int)($f_vendor_id ?? 1),
            'model_name' => $model_name
        ];
        insert_record($db_link, "device_models", $new);
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
        ?>

        <br>
        <table class="data">
            <tr align="center">
                <td><input type="checkbox" onClick="checkAll(this.checked);"></td>
                <td><b>Id</b></td>
                <td><b><?php echo WEB_model_vendor; ?></b></td>
                <td><b><?php echo WEB_cell_name; ?></b></td>
                <td><b><?php echo WEB_cell_poe_in; ?></b></td>
                <td><b><?php echo WEB_cell_poe_out; ?></b></td>
                <td><b><?php echo WEB_nagios_template; ?></b></td>
                <td></td>
            </tr>
            <?php
            foreach ($t_models as $row) {
                $is_system = ($row['id'] < 10000);
                $disabled_attr = $is_system ? 'disabled' : '';
                $checkbox_attr = $is_system ? 'disabled title="System model — cannot be edited or deleted"' : '';
                echo "<tr align=center>\n";
                echo "<td class=\"data\" style='padding:0'>";
                echo "<input type=\"checkbox\" name=\"f_id[]\" value=\"{$row['id']}\">";
                echo "</td>\n";
                echo "<td class=\"data\"><input type=\"hidden\" name=\"r_id[]\" value=\"{$row['id']}\">{$row['id']}</td>\n";
                echo "<td class=\"data\" width=150>";
                print_vendor_set($db_link, 'f_vendor[]', $row['vendor_id'], $is_system);
                echo "</td>\n";
                echo "<td class=\"data\">";
                echo "<input type=\"text\" name=\"f_name[]\" value=\"" . htmlspecialchars($row['model_name']) . "\" $disabled_attr>";
                echo "</td>\n";
                echo "<td class=\"data\">";
                print_qa_select("f_poe_in[]", $row['poe_in']);
                echo "</td>\n";
                echo "<td class=\"data\">";
                print_qa_select("f_poe_out[]", $row['poe_out']);
                echo "</td>\n";
                echo "<td class=\"data\">";
                echo "<input type=\"text\" name=\"f_nagios[]\" value=\"" . htmlspecialchars($row['nagios_template']) . "\">";
                echo "</td>\n";
                echo "<td class=\"data\"></td>\n";
                echo "</tr>\n";
            }
            ?>
        </table>

        <!-- Кнопки под таблицей -->
        <div style="margin-top: 15px; display: flex; justify-content: space-between; align-items: center;">
            <!-- Массовые действия -->
            <div>
                <input type="submit"
                       name="save"
                       value="<?php echo WEB_btn_save; ?>"
                       <?php if (empty($t_models)) echo 'disabled'; ?>>
                <input type="submit"
                       name="remove"
                       value="<?php echo WEB_btn_delete; ?>"
                       onclick="return confirm('<?php echo WEB_msg_delete; ?>?')"
                       <?php if (empty($t_models) || !array_filter($t_models, fn($r) => $r['id'] >= 10000)) echo 'disabled'; ?>>
            </div>

            <!-- Создание новой записи -->
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
