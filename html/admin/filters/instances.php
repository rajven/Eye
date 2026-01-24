<?php
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/auth.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/languages/" . HTML_LANG . ".php");

// Сохранение изменений
if (getPOST("save") !== null) {
    $f_ids = getPOST("f_id", null, []);
    $r_ids = getPOST("r_id", null, []);
    $f_names = getPOST("f_name", null, []);
    $f_descriptions = getPOST("f_description", null, []);
    if (is_array($f_ids) && is_array($r_ids)) {
        foreach ($f_ids as $save_id) {
            $save_id = (int)$save_id;
            if ($save_id <= 0) continue;

            $idx = array_search($save_id, $r_ids, true);
            if ($idx === false) continue;

            $new = [
                'name'        => trim($f_names[$idx] ?? ''),
                'description' => trim($f_descriptions[$idx] ?? '')
            ];

            update_record($db_link, "filter_instances", "id = ?", $new, [$save_id]);
        }
    }

    header("Location: " . $_SERVER["REQUEST_URI"]);
    exit;
}

// Создание нового экземпляра
if (getPOST("create") !== null) {
    $instance_name = trim(getPOST("new_instance", null, ''));
    
    if ($instance_name !== '') {
        insert_record($db_link, "filter_instances", ['name' => $instance_name]);
    }
    
    header("Location: " . $_SERVER["REQUEST_URI"]);
    exit;
}

// Удаление экземпляров
if (getPOST("remove") !== null) {
    $r_ids = getPOST("r_id", null, []);
    
    if (is_array($r_ids)) {
        foreach ($r_ids as $id) {
            $id = (int)$id;
            if ($id <= 1) continue; // защищаем ID <= 1
            
            // Находим все группы, использующие этот instance_id
            $deleted_groups = get_records_sql($db_link, 
                "SELECT id FROM group_list WHERE instance_id > 1 AND instance_id = ?", 
                [$id]
            );
            
            if (!empty($deleted_groups)) {
                foreach ($deleted_groups as $d_group) {
                    $group_id = (int)($d_group['id'] ?? 0);
                    if ($group_id <= 0) continue;
                    
                    // Сбрасываем привязку в user_auth
                    update_records($db_link, "user_auth", 
                        "deleted = 0 AND filter_group_id = ?", 
                        ['filter_group_id' => 0, 'changed' => 1], 
                        [$group_id]
                    );

                    // Удаление связей
                    delete_records($db_link, "group_filters", "group_id = ?", [$group_id]);

                    // Удаляем группу
                    delete_record($db_link, "group_list", "id = ?", [$group_id]);
                }
            }
            
            // Удаляем сам экземпляр
            delete_record($db_link, "filter_instances", "id = ?", [$id]);
        }
    }
    
    header("Location: " . $_SERVER["REQUEST_URI"]);
    exit;
}

unset($_POST);
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/header.php");
print_filters_submenu($page_url);
?>
<div id="cont">
<form name="def" action="instances.php" method="post">
<table class="data">
<tr align="center">
	<td><input type="checkbox" onClick="checkAll(this.checked);"></td>
	<td><b>Id</b></td>
	<td><b><?php echo WEB_group_instance_name; ?></b></td>
	<td><b><?php echo WEB_cell_description; ?></b></td>
	<td><input type="submit" onclick="return confirm('<?php echo WEB_msg_delete; ?>?')" name="remove" value="<?php echo WEB_btn_delete; ?>"></td>
        <?php print "<td><input type=\"submit\" name=\"save\" value='".WEB_btn_save."'></td>"; ?>
</tr>
<?php
$t_instance=get_records_sql($db_link, "SELECT * FROM filter_instances");
foreach ($t_instance as $row) {
    print "<tr align=center>";
    print "<td class=\"data\" style='padding:0'><input type=checkbox name=f_id[] value='".$row['id']."'></td>";
    print "<td class=\"data\"><input type=\"hidden\" name='r_id[]' value='".$row['id']."'>".$row['id']."</td>";
    print "<td class=\"data\"><input type=\"text\" name='f_name[]' value='".$row['name']."'></td>";
    print "<td class=\"data\"><input type=\"text\" name='f_description[]' value='".$row['description']."'></td>";
    print "<td colspan=2 class=\"data\"></td>";
    print "</tr>";
}
?>
</table>
<div>
<input type=text name=new_instance value="New_instance">
<input type="submit" name="create" value="<?php echo WEB_btn_add; ?>">
</div>
</form>
<?php
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/footer.php");
?>