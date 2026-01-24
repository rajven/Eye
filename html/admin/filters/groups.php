<?php
require_once($_SERVER['DOCUMENT_ROOT'] . "/inc/auth.php");
require_once($_SERVER['DOCUMENT_ROOT'] . "/inc/languages/" . HTML_LANG . ".php");

// Создание новой группы
if (getPOST("create") !== null) {
    $fname = trim(getPOST("newgroup", null, ''));
    
    if ($fname !== '') {
        $new_id = insert_record($db_link, "group_list", ['group_name' => $fname]);
        if ($new_id) {
            header("Location: editgroup.php?id=$new_id");
            exit;
        }
    }
    
    header("Location: " . $_SERVER["REQUEST_URI"]);
    exit;
}

// Удаление групп
if (getPOST("remove") !== null) {
    $fgid = getPOST("fid", null, []);
    
    if (!empty($fgid) && is_array($fgid)) {
        foreach ($fgid as $val) {
            $val = trim($val);
            if ($val === '') continue;
            
            // Сброс привязки в user_auth
            update_records($db_link, "user_auth", 
                "deleted = 0 AND filter_group_id = ?", 
                ['filter_group_id' => 0, 'changed' => 1], 
                [$val]
            );
            
            // Удаление связей
            delete_records($db_link, "group_filters", "group_id = ?", [$val]);
            
            // Удаление самой группы
            delete_records($db_link, "group_list", "id = ?", [$val]);
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
    <form name="def" action="groups.php" method="post">
        <table class="data">
            <tr align="center">
                <td><input type="checkbox" onClick="checkAll(this.checked);"></td>
                <td><b>Id</b></td>
                <td width=200><b><?php echo WEB_cell_name; ?></b></td>
                <td ><b><?php echo WEB_submenu_filter_instance; ?></b></td>
                <td width=200><b><?php echo WEB_cell_description; ?></b></td>
                <td><input type="submit" onclick="return confirm('<?php echo WEB_msg_delete; ?>?')" name="remove" value="<?php echo WEB_btn_delete; ?>"></td>
            </tr>
            <?php
            $groups = get_records_sql($db_link, 'SELECT * FROM group_list ORDER BY id');
            foreach ($groups as $row) {
		$filter_instance = get_record_sql($db_link,'SELECT * FROM filter_instances WHERE id=?', [ $row["instance_id"] ]);
                print "<tr align=center>\n";
                print "<td class=\"data\" style='padding:0'><input type=checkbox name=fid[] value=" . $row["id"] . "></td>\n";
                print "<td class=\"data\" ><input type=\"hidden\" name=\"" . $row["id"] . "\" value=" . $row["id"] . ">" . $row["id"] . "</td>\n";
                print "<td class=\"data\"><a href=editgroup.php?id=" . $row["id"] . ">" . $row["group_name"] . "</a></td>\n";
                print "<td class=\"data\">". $filter_instance["name"]."</td>\n";
                print "<td class=\"data\">" . $row["description"] . "</td>\n";
                print "<td></td></tr>";
            }
            ?>
        </table>
        <div>
            <?php echo WEB_cell_name; ?><input type=text name=newgroup value="Unknown">
            <input type="submit" name="create" value="<?php echo WEB_btn_add; ?>">
        </div>
    </form>
    <?php
    require_once($_SERVER['DOCUMENT_ROOT'] . "/inc/footer.php");
    ?>