<?php
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/auth.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/languages/" . HTML_LANG . ".php");

// Удаление OU
if (getPOST("remove") !== null) {
    $fid = getPOST("f_id", null, []);
    
    if (!empty($fid) && is_array($fid)) {
        foreach ($fid as $val) {
            $val = (int)$val;
            if ($val <= 0) continue;
            // Обнуляем привязки в user_list
            update_records($db_link, "user_list", "ou_id = ?", ['ou_id' => 0], [$val]);
            // Обнуляем привязки в user_auth
            update_records($db_link, "user_auth", "ou_id = ?", ['ou_id' => 0], [$val]);
            // Удаляем правила авторизации
            delete_records($db_link, "auth_rules", "ou_id = ?", [$val]);
            // Удаляем сам OU
            delete_record($db_link, "ou", "id = ?", [$val]);
        }
    }
    header("Location: " . $_SERVER["REQUEST_URI"]);
    exit;
}

// Создание нового OU
if (getPOST("create") !== null) {
    $ou_name = trim(getPOST("new_ou", null, ''));
    if ($ou_name !== '') {
        insert_record($db_link, "ou", ['ou_name' => $ou_name]);
    }
    header("Location: " . $_SERVER["REQUEST_URI"]);
    exit;
}

unset($_POST);
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/header.php");
?>
<div id="cont">
<b><?php echo WEB_list_ou; ?></b><br>
<form name="def" action="index.php" method="post">
<table class="data">
<tr align="center">
<td><input type="checkbox" onClick="checkAll(this.checked);"></td>
<td><b>Id</b></td>
<td><b><?php echo WEB_cell_flags; ?></b></td>
<td><b><?php echo WEB_cell_name; ?></b></td>
<td><b><?php echo WEB_cell_dynamic; ?></b></td>
<td>
<input type="submit" onclick="return confirm('<?php echo WEB_msg_delete; ?>?')" name="remove" value="<?php echo WEB_btn_delete; ?>">
</td>
</tr>
<?php
$t_ou = get_records_sql($db_link,'SELECT * FROM ou ORDER BY ou_name');
foreach ($t_ou as $row) {
    print "<tr align=center>\n";
    print "<td class=\"data\" style='padding:0'><input type=checkbox name=f_id[] value='{$row['id']}'></td>\n";
    print "<td class=\"data\"><input type=\"hidden\" name='r_id[]' value='{$row['id']}'>{$row['id']}</td>\n";
    $flag='';
    if ($row['default_users'] == 1) { $flag='D'; }
    if ($row['default_hotspot'] == 1) { $flag='H'; }
    print "<td class=\"data\">$flag</td>\n";
    print "<td class=\"data\">"; print_url($row['ou_name'],"/admin/groups/edit_group.php?id=".$row['id']); print "</td>\n";
    print_yes_no($row['dynamic'],'custom','data');
    print "<td class=\"data\"></td>\n";
    print "</tr>\n";
}
?>
</table>
<div>
    <input type=text name=new_ou value="Unknown"></td>
    <input type="submit" name="create" value="<?php echo WEB_btn_add; ?>">
</div>
</form>
<?php
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/footer.php");
?>
