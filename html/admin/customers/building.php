<?php
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/auth.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/languages/" . HTML_LANG . ".php");

if (getPOST("remove")) {
    $fid = getPOST("f_id", null, []);
    $valid_ids = array_filter(array_map('intval', $fid), fn($id) => $id > 1);

    foreach ($valid_ids as $val) {
        LOG_INFO($db_link, 'Remove building id: ' . $val . ' ' . dump_record($db_link, 'building', 'id=?', [$val]));
        delete_record($db_link, "building", "id=?", [$val]);
    }

    header("Location: " . $_SERVER["REQUEST_URI"]);
    exit;
}

if (getPOST("save")) {
    $r_id = getPOST("r_id",null, []);
    $f_building_name = getPOST("f_building_name", null, []);
    $f_building_description = getPOST("f_building_description", null, []);
    $save_flags = getPOST("save", null, []);

    $r_id = array_map('intval', $r_id);
    $save_flags = array_map('intval', $save_flags);

    foreach ($save_flags as $i => $save_id) {
        if ($save_id <= 0) continue;

        $found_index = array_search($save_id, $r_id, true);
        if ($found_index === false) continue;

        $name = trim($f_building_name[$found_index] ?? '');
        $description = trim($f_building_description[$found_index] ?? '');

        if ($name !== '') {
            $new = ['name' => $name, 'description' => $description];
            LOG_INFO($db_link, "Change building id='{$save_id}': name=" . $name . " description=" . $description);
            update_record($db_link, "building", "id=?", $new, [$save_id]);
        }
    }

    header("Location: " . $_SERVER["REQUEST_URI"]);
    exit;
}

if (getPOST("create")) {
    $building_name = trim(getPOST("new_building", null, ''));
    
    if ($building_name !== '') {
        $new = ['name' => $building_name];
        LOG_INFO($db_link, "Add building: " . $building_name);
        insert_record($db_link, "building", $new);
    }
    
    header("Location: " . $_SERVER["REQUEST_URI"]);
    exit;
}

unset($_POST);
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/header.php");
print_control_submenu($page_url);
?>
<div id="cont">
<form name="def" action="building.php" method="post">
<table class="data">
<tr align="center">
<td><input type="checkbox" onClick="checkAll(this.checked);"></td>
<td><b>id</b></td>
<td><b><?php echo WEB_cell_name; ?></b></td>
<td><b><?php echo WEB_cell_description; ?></b></td>
<td>
<input type="submit" onclick="return confirm('<?php print WEB_btn_delete; ?>?')" name="remove" value="<?php print WEB_btn_remove; ?>">
</td>
</tr>
<?php
$t_building = get_records_sql($db_link,'SELECT * FROM building ORDER BY name');
foreach ($t_building as $row) {
    print "<tr align=center>\n";
    print "<td class=\"data\" style='padding:0'><input type=checkbox name=f_id[] value='{$row['id']}'></td>\n";
    print "<td class=\"data\"><input type=\"hidden\" name='r_id[]' value='{$row['id']}'>{$row['id']}</td>\n";
    print "<td class=\"data\"><input type=\"text\" name='f_building_name[]' value='{$row['name']}'></td>\n";
    print "<td class=\"data\"><input type=\"text\" name='f_building_description[]' value='{$row['description']}'></td>\n";
    print "<td class=\"data\"><button name='save[]' value='{$row['id']}'>".WEB_btn_save."</button></td>\n";
    print "</tr>\n";
}
?>
</table>
<table>
<tr>
<td><input type=text name=new_building value="Unknown"></td>
<td>
<input type="submit" name="create" value="<?php print WEB_btn_add; ?>">
</td>
<td align="right"></td>
</tr>
</table>
</form>
<?php
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/footer.php");
?>
