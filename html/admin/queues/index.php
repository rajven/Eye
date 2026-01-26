<?php
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/auth.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/languages/" . HTML_LANG . ".php");

// Сохранение ОТМЕЧЕННЫХ записей
if (getPOST("save") !== null) {
    $selected_ids = getPOST("f_id", null, []);        // отмеченные чекбоксы
    $all_ids      = getPOST("r_id", null, []);        // все ID
    $names        = getPOST("f_queue_name", null, []);
    $downs        = getPOST("f_down", null, []);
    $ups          = getPOST("f_up", null, []);

    if (!empty($selected_ids) && is_array($selected_ids)) {
        $selected_ids = array_map('intval', $selected_ids);
        $selected_set = array_flip($selected_ids);

        foreach ($all_ids as $i => $id) {
            $id = (int)$id;
            if ($id <= 0 || !isset($selected_set[$id])) continue;

            $name = trim($names[$i] ?? '');
            if ($name === '') continue;

            update_record($db_link, "queue_list", "id = ?", [
                'queue_name' => $name,
                'download'   => (int)($downs[$i] ?? 0),
                'upload'     => (int)($ups[$i] ?? 0)
            ], [$id]);
        }
    }

    header("Location: " . $_SERVER["REQUEST_URI"]);
    exit;
}

// Удаление отмеченных
if (getPOST("remove") !== null) {
    $f_id = getPOST("f_id", null, []);
    if (!empty($f_id) && is_array($f_id)) {
        foreach ($f_id as $id) {
            $id = (int)$id;
            if ($id > 0) {
                delete_record($db_link, "queue_list", "id = ?", [$id]);
            }
        }
    }
    header("Location: " . $_SERVER["REQUEST_URI"]);
    exit;
}

// Создание новой очереди
if (getPOST("create") !== null) {
    $queue_name = trim(getPOST("new_queue", null, ''));
    if ($queue_name !== '') {
        insert_record($db_link, "queue_list", ['queue_name' => $queue_name]);
    }
    header("Location: " . $_SERVER["REQUEST_URI"]);
    exit;
}

unset($_POST);
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/header.php");
?>
<div id="cont">
    <b><?php echo WEB_list_queues; ?></b> <br>

    <form name="def" action="index.php" method="post">
        <table class="data">
            <tr align="center">
                <td>
                    <input type="checkbox" onClick="checkAll(this.checked);">
                </td>
                <td><b>Id</b></td>
                <td><b><?php echo WEB_cell_name; ?></b></td>
                <td><b>Download</b></td>
                <td><b>Upload</b></td>
            </tr>
            <?php
            $t_queue = get_records_sql($db_link, "SELECT * FROM queue_list ORDER BY id");
            foreach ($t_queue as $row) {
                print "<tr align=center>\n";
                print "<td class=\"data\" style='padding:0'><input type=\"checkbox\" name=\"f_id[]\" value=\"{$row['id']}\"></td>\n";
                print "<td class=\"data\"><input type=\"hidden\" name=\"r_id[]\" value=\"{$row['id']}\">{$row['id']}</td>\n";
                print "<td class=\"data\"><input type=\"text\" class=\"full-width\" name=\"f_queue_name[]\" value=\"" . htmlspecialchars($row['queue_name']) . "\"></td>\n";
                print "<td class=\"data\"><input type=\"text\" name=\"f_down[]\" value=\"{$row['download']}\"></td>\n";
                print "<td class=\"data\"><input type=\"text\" name=\"f_up[]\" value=\"{$row['upload']}\"></td>\n";
                print "</tr>\n";
            }
            ?>
        </table>

        <div style="margin-top: 15px; display: flex; justify-content: space-between; align-items: center;">
            <div>
                <input type="submit" name="save" value="<?php echo WEB_btn_save; ?>">
                <input type="submit"
                       onclick="return confirm('<?php echo WEB_msg_delete; ?>?')"
                       name="remove"
                       value="<?php echo WEB_btn_delete; ?>">
            </div>
            <div style="display: flex; gap: 8px; align-items: center;">
                <input type="text" name="new_queue" value="New_queue" style="width: 120px;">
                <input type="submit" name="create" value="<?php echo WEB_btn_add; ?>">
            </div>
        </div>
    </form>

<?php
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/footer.php");
?>