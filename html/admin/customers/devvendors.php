<?php

$default_displayed=25;
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/auth.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/languages/" . HTML_LANG . ".php");

// Сохранение изменений
if (getPOST("save") !== null) {
    $selected_ids = getPOST("f_id", null, []);
    
    if (!empty($selected_ids) && is_array($selected_ids)) {
        // Преобразуем в целые числа и оставляем только >= 10000
        $selected_ids = array_filter(array_map('intval', $selected_ids), fn($id) => $id >= 10000);
        
        if (!empty($selected_ids)) {
            $r_ids   = array_map('intval', getPOST("r_id",   null, []));
            $f_names = getPOST("f_name", null, []);
            
            foreach ($selected_ids as $vendor_id) {
                $idx = array_search($vendor_id, $r_ids, true);
                if ($idx === false) continue;
                
                $name = trim($f_names[$idx] ?? '');
                if ($name === '') continue;
                
                update_record($db_link, "vendors", "id = ?", ['name' => $name], [$vendor_id]);
            }
        }
    }
    
    header("Location: " . $_SERVER["REQUEST_URI"]);
    exit;
}

// Создание нового производителя
if (getPOST("create") !== null) {
    $vendor_name = trim(getPOST("new_vendor", null, ''));
    
    if ($vendor_name !== '') {
        $max_record = get_record_sql($db_link, "SELECT MAX(id) AS max_id FROM vendors");
        $next_id = (isset($max_record['max_id']) && $max_record['max_id'] >= 10000)
            ? (int)$max_record['max_id'] + 1
            : 10000;
        insert_record($db_link, "vendors", [
            'id'   => $next_id,
            'name' => $vendor_name
        ]);
    }
    header("Location: " . $_SERVER["REQUEST_URI"]);
    exit;
}

// Удаление отмеченных
if (getPOST("remove") !== null) {
    $selected_ids = getPOST("f_id", null, []);
    if (!empty($selected_ids) && is_array($selected_ids)) {
        // Преобразуем в целые числа и оставляем только >= 10000
        $selected_ids = array_filter(array_map('intval', $selected_ids), fn($id) => $id >= 10000);
        if (!empty($selected_ids)) {
            $r_ids   = array_map('intval', getPOST("r_id",   null, []));
            foreach ($selected_ids as $vendor_id) {
                $idx = array_search($vendor_id, $r_ids, true);
                if ($idx === false) continue;
                update_records($db_link, "device_models", "vendor_id =?", [ 'id'=> 1 ], [ $vendor_id ]);
                delete_record($db_link, "vendors", "id = ?", [$vendor_id]);
            }
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
    <form name="def" action="devvendors.php" method="post">

        <table class="data">
            <tr>
                <td><b><?php echo WEB_list_vendors; ?></b></td>
                <td><?php echo WEB_rows_at_page . "&nbsp:"; print_row_at_pages('rows', $displayed); ?></td>
                <td><input type="submit" name="OK" value="<?php echo WEB_btn_show; ?>"></td>
            </tr>
        </table>

        <?php
        $countSQL = "SELECT COUNT(*) FROM vendors";
        $count_records = get_single_field($db_link, $countSQL);
        $total = ceil($count_records / $displayed);
        if ($page > $total) { $page = $total; }
        if ($page < 1) { $page = 1; }
        $start = ($page * $displayed) - $displayed;
        print_navigation($page_url, $page, $displayed, $count_records, $total);
        ?>

        <table class="data">
            <tr align="center">
                <td><input type="checkbox" onClick="checkAll(this.checked);"></td>
                <td><b>Id</b></td>
                <td><b><?php echo WEB_model_vendor; ?></b></td>
                <td style="width: 120px;">
                    <input type="submit" name="save" value="<?php echo WEB_btn_save; ?>">
                </td>
            </tr>
            <?php
            $params = [$displayed, $start];
            $t_vendors = get_records_sql($db_link, "SELECT * FROM vendors ORDER BY name LIMIT ? OFFSET ?", $params);
            foreach ($t_vendors as $row) {
                $is_system = ($row['id'] < 10000);
                $disabled_attr = $is_system ? 'disabled' : '';
                $checkbox_attr = $is_system ? 'disabled title="System vendor — cannot be edited or deleted"' : '';

                echo "<tr align=center>\n";
                echo "<td class=\"data\" style='padding:0'>";
                echo "<input type=\"checkbox\" name=\"f_id[]\" value=\"{$row['id']}\" $checkbox_attr>";
                echo "</td>\n";
                echo "<td class=\"data\"><input type=\"hidden\" name=\"r_id[]\" value=\"{$row['id']}\">{$row['id']}</td>\n";
                echo "<td colspan=2 class=\"data\">";
                echo "<input type=\"text\" name=\"f_name[]\" value=\"" . htmlspecialchars($row['name']) . "\" class=\"full-width\" $disabled_attr>";
                echo "</td>\n";
                echo "</tr>\n";
            }
            ?>
        </table>

        <!-- Кнопки под таблицей -->
        <div style="margin-top: 15px; display: flex; justify-content: space-between; align-items: center;">
            <!-- Массовые действия -->
            <div>
                <input type="submit"
                       name="remove"
                       value="<?php echo WEB_btn_delete; ?>"
                       onclick="return confirm('<?php echo WEB_msg_delete; ?>?')"
                       <?php if (empty($t_vendors) || !array_filter($t_vendors, fn($r) => $r['id'] >= 10000)) echo 'disabled'; ?>>
            </div>

            <!-- Создание новой записи -->
            <div style="display: flex; gap: 8px; align-items: center;">
                <input type="text" name="new_vendor" value="Unknown" style="width: 120px;">
                <input type="submit" name="create" value="<?php echo WEB_btn_add; ?>">
            </div>
        </div>

    </form>

<?php
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/footer.php");
?>
