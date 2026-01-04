<?php
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/auth.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/languages/" . HTML_LANG . ".php");

if (isset($_POST["remove"])) {
    $fid = $_POST["f_id"];
    foreach ($fid as $key => $val) {
        if (isset($val) and $val > 1) {
            LOG_INFO($db_link,'Remove building id: '. $val .' '. dump_record($db_link,'building','id='.$val));
            delete_record($db_link, "building", "id=" . $val);
        }
    }
    header("Location: " . $_SERVER["REQUEST_URI"]);
    exit;
}

if (isset($_POST['save'])) {
    $len = is_array($_POST['r_id']) ? count($_POST['r_id']) : 0;
    for ($i = 0; $i < $len; $i ++) {
        $save_id = intval($_POST['save'][$i]);
        if ($save_id == 0) {
            continue;
        }
        $len_all = is_array($_POST['r_id']) ? count($_POST['r_id']) : 0;
        for ($j = 0; $j < $len_all; $j ++) {
            if (intval($_POST['r_id'][$j]) != $save_id) {
                continue;
            }
            $value = $_POST['f_building_name'][$j];
            $value_description = $_POST['f_building_description'][$j];
            if (isset($value)) {
                $new['name'] = $value;
                $new['description'] = $value_description;
                LOG_INFO($db_link,"Change building id='{$save_id}': name=".$value." description=".$value_description);
                update_record($db_link, "building", "id='{$save_id}'", $new);
            }
        }
    }
    header("Location: " . $_SERVER["REQUEST_URI"]);
    exit;
}

if (isset($_POST["create"])) {
    $building_name = $_POST["new_building"];
    if (isset($building_name)) {
        $new['name'] = $building_name;
        LOG_INFO($db_link,'Add building $building_name');
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
$t_building = get_records($db_link,'building','TRUE ORDER BY id');
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
