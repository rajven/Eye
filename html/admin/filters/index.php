<?php
require_once($_SERVER['DOCUMENT_ROOT'] . "/inc/auth.php");
require_once($_SERVER['DOCUMENT_ROOT'] . "/inc/languages/" . HTML_LANG . ".php");

// Создание нового фильтра
if (getPOST("create") !== null) {
    $fname = trim(getPOST("newfilter", null, ''));
    $ftype = (int)getPOST("filter_type", null, 0);

    if ($fname !== '') {
        $new_id = insert_record($db_link, "filter_list", [
            'name'         => $fname,
            'filter_type'  => $ftype
        ]);

        if ($new_id) {
            header("Location: editfilter.php?id=$new_id");
            exit;
        }
    }

    header("Location: " . $_SERVER["REQUEST_URI"]);
    exit;
}

// Удаление фильтров
if (getPOST("remove") !== null) {
    $fid = getPOST("fid", null, []);

    if (!empty($fid) && is_array($fid)) {
        foreach ($fid as $val) {
            $val = trim($val);
            if ($val === '') continue;

            // Удаляем из связей
            delete_records($db_link, "group_filters", "filter_id = ?", [(int)$val]);

            // Удаляем сам фильтр
            delete_record($db_link, "filter_list", "id = ?", [(int)$val]);
        }
    }

    header("Location: " . $_SERVER["REQUEST_URI"]);
    exit;
}

unset($_POST);
require_once($_SERVER['DOCUMENT_ROOT'] . "/inc/header.php");
print_filters_submenu($page_url);
?>
<div id="cont">
    <form name="def" action="index.php" method="post">
        <table class="data">
            <tr align="center">
                <td><input type="checkbox" onClick="checkAll(this.checked);"></td>
                <td><b>id</b></td>
                <td><b><?php echo WEB_cell_forename; ?></b></td>
                <td><b><?php echo WEB_cell_type; ?></b></td>
                <td><b><?php echo WEB_traffic_proto; ?></b></td>
                <td><b><?php echo WEB_traffic_dest_address; ?></b></td>
                <td><b><?php echo WEB_traffic_dst_port; ?></b></td>
                <td><b><?php echo WEB_traffic_src_port; ?></b></td>
                <td><b><?php echo WEB_cell_description; ?></b></td>
                <td><input type="submit" onclick="return confirm('<?php echo WEB_msg_delete; ?>?')" name="remove" value="<?php echo WEB_btn_delete; ?>"></td>
            </tr>
            <?php
            // Запрос с подгрузкой имени IPSet через LEFT JOIN
            $filters = get_records_sql($db_link, 
                'SELECT f.*, i.name AS ipset_name 
                 FROM filter_list f 
                 LEFT JOIN ipset_list i ON f.ipset_id = i.id 
                 ORDER BY f.name');
            
            foreach ($filters as $row) {
                print "<tr align=center>\n";
                print "<td class=\"data\" style='padding:0'><input type=checkbox name=fid[] value=" . $row['id'] . "></td>\n";
                print "<td class=\"data\" ><input type=hidden name=\"id\" value=" . $row['id'] . ">" . $row['id'] . "</td>\n";
                print "<td class=\"data\" align=left><a href=editfilter.php?id=" . $row['id'] . ">" . htmlspecialchars($row['name']) . "</a></td>\n";
                
                // Инициализация пустых полей
                $row['description'] = $row['description'] ?? '';
                $row['proto']       = $row['proto'] ?? '';
                $row['dst']         = $row['dst'] ?? '';
                $row['dstport']     = $row['dstport'] ?? '';
                $row['srcport']     = $row['srcport'] ?? '';
                
                if ($row['filter_type'] == 0) {
                    // === Формирование отображения dst / ipset ===
                    $dst_display = '';
                    if (!empty($row['ipset_id']) && !empty($row['ipset_name'])) {
                        // Режим IPSet: показываем имя с ссылкой на редактирование
                        $dst_display = '<span title="IPSet">' . 
                            '<a href="editipset.php?id=' . (int)$row['ipset_id'] . '" ' .
                            'class="ref">' .
                            '📦 ' . htmlspecialchars($row['ipset_name']) . '</a></span>';
                    } elseif (!empty($row['dst'])) {
                        // Режим прямого IP: показываем dst
                        $dst_display = htmlspecialchars($row['dst']);
                    } else {
                        // Пустое значение
                        $dst_display = '<span class="none">0/0</span>';
                    }
                    
                    print "<td class=\"data\">IP фильтр</td>\n";
                    print "<td class=\"data\">" . htmlspecialchars($row['proto']) . "</td>\n";
                    print "<td class=\"data\" align=left>" . $dst_display . "</td>\n";
                    print "<td class=\"data\">" . htmlspecialchars($row['dstport']) . "</td>\n";
                    print "<td class=\"data\">" . htmlspecialchars($row['srcport']) . "</td>\n";
                    print "<td class=\"data\">" . htmlspecialchars($row['description']) . "</td>\n";
                } else {
                    // Name фильтр (упрощённый режим)
                    $dst_display = '';
                    if (!empty($row['ipset_id']) && !empty($row['ipset_name'])) {
                        $dst_display = '<span title="IPSet">' . 
                            '<a href="editipset.php?id=' . (int)$row['ipset_id'] . '" ' .
                            'class="ref">' .
                            '📦 ' . htmlspecialchars($row['ipset_name']) . '</a></span>';
                    } elseif (!empty($row['dst'])) {
                        $dst_display = htmlspecialchars($row['dst']);
                    } else {
                        $dst_display = '<span class="none">—</span>';
                    }
                    
                    print "<td class=\"data\">Name фильтр</td>\n";
                    print "<td class=\"data\"></td>\n";
                    print "<td class=\"data\" align=left>" . $dst_display . "</td>\n";
                    print "<td class=\"data\"></td>\n";
                    print "<td class=\"data\"></td>\n";
                    print "<td class=\"data\">" . htmlspecialchars($row['description']) . "</td>\n";
                }
                print "<td></td></tr>";
            }
            ?>
        </table>
        <div>
            <?php echo WEB_cell_name; ?>
            <input type=text name=newfilter value="Unknown">
            <?php echo WEB_filter_type; ?>
            <select name="filter_type" disabled=true>
                <option value=0 selected>IP фильтр</option>
                <option value=1>Name фильтр</option>
            </select>
            <input type="submit" name="create" value="<?php echo WEB_btn_add; ?>">
    </form>
    <?php
    require_once($_SERVER['DOCUMENT_ROOT'] . "/inc/footer.php");
    ?>
