<?php
require_once($_SERVER['DOCUMENT_ROOT'] . "/inc/auth.php");
require_once($_SERVER['DOCUMENT_ROOT'] . "/inc/languages/" . HTML_LANG . ".php");
require_once($_SERVER['DOCUMENT_ROOT'] . "/inc/idfilter.php");

$group = get_record_sql($db_link, "SELECT * FROM group_list WHERE id=?", [ $id ]);

// Редактирование группы
if (getPOST("editgroup") !== null) {
    $new = [
        'group_name'    => trim(getPOST("f_group_name", null, $group['group_name'])),
        'instance_id'   => (int)getPOST("f_instance_id", null, 1),
        'description'   => trim(getPOST("f_group_description", null, ''))
    ];
    update_record($db_link, "group_list", "id = ?", $new, [$id]);
    header("Location: " . $_SERVER["REQUEST_URI"]);
    exit;
}

// Добавление фильтра в группу
if (getPOST("addfilter") !== null) {
    $filter_id = (int)getPOST("newfilter", null, 0);
    
    if ($filter_id > 0) {
        $max_record = get_record_sql($db_link, "SELECT MAX(G.rule_order) as morder FROM group_filters AS G WHERE G.group_id = ?", [$id]);
        $forder = (!empty($max_record) && isset($max_record['morder'])) 
            ? ((int)$max_record['morder'] + 1) 
            : 1;

        $new = [
            'group_id'     => $id,
            'filter_id'    => $filter_id,
            'rule_order'   => $forder,
            'action'       => 1
        ];
        insert_record($db_link, "group_filters", $new);
    }
    
    header("Location: " . $_SERVER["REQUEST_URI"]);
    exit;
}

// Удаление фильтров из группы
if (getPOST("removefilter") !== null) {
    $f_group_filter = getPOST("f_group_filter", null, []);
    
    if (!empty($f_group_filter) && is_array($f_group_filter)) {
        foreach ($f_group_filter as $val) {
            $val = trim($val);
            if ($val !== '') {
                delete_record($db_link, "group_filters", "id = ?", [(int)$val]);
            }
        }
    }
    
    header("Location: " . $_SERVER["REQUEST_URI"]);
    exit;
}

// Обновление порядка и действий фильтров
if (getPOST("updateFilters") !== null) {
    $f_group_filter = getPOST("f_group_filter", null, []);
    
    if (!empty($f_group_filter) && is_array($f_group_filter)) {
        $f_ord    = getPOST("f_ord",    null, []);
        $f_action = getPOST("f_action", null, []);
        
        LOG_DEBUG($db_link, "Update filters for group id: " . $id);
        
        foreach ($f_group_filter as $i => $group_filter_id) {
            $group_filter_id = (int)$group_filter_id;
            if ($group_filter_id <= 0) continue;
            
            $new = [
                'rule_order' => isset($f_ord[$group_filter_id]) 
                    ? (int)$f_ord[$group_filter_id] 
                    : $i,
                'action'     => isset($f_action[$group_filter_id]) 
                    ? (int)$f_action[$group_filter_id] 
                    : 0
            ];
            
            update_record($db_link, "group_filters", "id = ?", $new, [$group_filter_id]);
        }
    }
    
    header("Location: " . $_SERVER["REQUEST_URI"]);
    exit;
}

unset($_POST);


print_filters_submenu($page_url);

require_once($_SERVER['DOCUMENT_ROOT'] . "/inc/header.php");
?>
<div id="cont">
    <br> <b><?php echo WEB_title_group; ?></b> <br>

    <form name="def" action="editgroup.php?id=<?php echo $id; ?>" method="post">
        <input type="hidden" name="id" value=<?php echo $id; ?>>
        <table class="data">
            <tr>
                <td><?php echo WEB_cell_name; ?></td>
                <td class='data'><input type="text" name="f_group_name" value="<?php echo $group['group_name']; ?>"></td>
                <td class='data' align=right><input type="submit" name="editgroup" value="<?php echo WEB_btn_save; ?>"></td>
            </tr>
            <tr>
                <td><?php echo WEB_cell_description; ?></td>
                <td class='data'><input type="text" name="f_group_description" value="<?php echo $group['description']; ?>"></td>
                <td class='data'></td>
            </tr>
            <tr>
                <td><?php echo WEB_submenu_filter_instance; ?></td>
                <td class='data'><?php print_instance_select($db_link,'f_instance_id',$group['instance_id']); ?></td>
                <td class='data'></td>
            </tr>
        </table>
        <br> <b><?php echo WEB_groups_filter_list; ?></b><br>
        <table class="data">
            <tr>
                <td><input type="checkbox" onClick="checkAll(this.checked);"></td>
                <td><?php echo WEB_group_filter_order; ?></td>
                <td><?php echo WEB_group_filter_name; ?></td>
                <td><?php echo WEB_traffic_action; ?></td>
                <td class='up'><input type="submit" name="updateFilters" value="<?php echo WEB_btn_save_filters; ?>"></td>
                <td class='warn'><input type="submit" onclick="return confirm('<?php echo WEB_msg_delete_filter; ?>?')" name="removefilter" value="<?php echo WEB_btn_delete; ?>"></td>
            </tr>

            <?php
            $sSQL = "SELECT G.id, G.filter_id, F.name, G.rule_order, G.action, F.description FROM group_filters G, filter_list F WHERE F.id=G.filter_id and group_id=? ORDER BY G.rule_order";
            $flist = get_records_sql($db_link, $sSQL, [ $id ]);
            foreach ($flist as $row) {
                print "<tr align=center>\n";
                print "<td class=\"data\" style='padding:0'><input type=checkbox name=f_group_filter[] value=" . $row['id'] . "></td>\n";
                print "<td class=\"data\" align=left><input type=text name=f_ord[" . $row['id'] . "] value=" . $row['rule_order'] . " size=4 ></td>\n";
                print "<td class=\"data\" align=left><a href=editfilter.php?id=" . $row['filter_id'] . ">" . $row['name'] . "</a></td>\n";
                $cl = "data";
                if ($row['action']) {
                    $cl = "up";
                } else {
                    $cl = "warn";
                }
                print "<td class=" . $cl . ">";
                print_action_select('f_action[' . $row['id'] . ']', $row['action']);
                print "</td>";
                print "<td colspan=2 class=\"data\" align=left>" . $row['description'] . "</a></td>\n";
                print "</tr>";
            }
            ?>
        </table>
        <div>
            <input type="submit" name="addfilter" value="<?php echo WEB_msg_add_filter; ?>"> <?php print_filter_select($db_link, 'newfilter', $id); ?>
        </div>
    </form>
    <?php
    require_once($_SERVER['DOCUMENT_ROOT'] . "/inc/footer.php");
    ?>