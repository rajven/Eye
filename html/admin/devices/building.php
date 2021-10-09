<?php
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/auth.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/languages/" . $language . ".php");

if (isset($_POST["remove"])) {
    $fid = $_POST["f_id"];
    foreach ($fid as $key => $val) {
        if (isset($val) and $val > 1) {
            LOG_INFO($db_link,'Удаляем расположение с id: '.$val);
            delete_record($db_link, "building", "id=" . $val);
        }
    }
    header("Location: " . $_SERVER["REQUEST_URI"]);
}

if (isset($_POST['save'])) {
    $len = is_array($_POST['id']) ? count($_POST['id']) : 0;
    for ($i = 0; $i < $len; $i ++) {
        $save_id = intval($_POST['save'][$i]);
        if ($save_id == 0) {
            continue;
        }
        $len_all = is_array($_POST['id']) ? count($_POST['id']) : 0;
        for ($j = 0; $j < $len_all; $j ++) {
            if (intval($_POST['id'][$j]) != $save_id) {
                continue;
            }
            $value = $_POST['f_building_name'][$j];
            $value_comment = $_POST['f_building_comment'][$j];
            if (isset($value)) {
                $new['name'] = $value;
                $new['comment'] = $value_comment;
                LOG_INFO($db_link,"Изменяем расположение id='{$save_id}': name=".$value." comment=".$value_comment);
                update_record($db_link, "building", "id='{$save_id}'", $new);
            }
        }
    }
    header("Location: " . $_SERVER["REQUEST_URI"]);
}

if (isset($_POST["create"])) {
    $building_name = $_POST["new_building"];
    if (isset($building_name)) {
        $new['name'] = $building_name;
        LOG_INFO($db_link,'Добавляем расположение $building_name');
        insert_record($db_link, "building", $new);
    }
    header("Location: " . $_SERVER["REQUEST_URI"]);
}

unset($_POST);
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/header.php");
print_device_submenu($page_url);
?>
<div id="cont">
<form name="def" action="building.php" method="post">
<table class="data">
<tr align="center">
<td><input type="checkbox" onClick="checkAll(this.checked);"></td>
<td><b>id</b></td>
<td><b>Название</b></td>
<td><b>Комментарий</b></td>
<td><input type="submit" onclick="return confirm('Удалить?')" name="remove" value="Удалить"></td>
</tr>
<?
$t_building = get_records($db_link,'building','TRUE ORDER BY id');
foreach ($t_building as $row) {
    print "<tr align=center>\n";
    print "<td class=\"data\" style='padding:0'><input type=checkbox name=f_id[] value='{$row['id']}'></td>\n";
    print "<td class=\"data\"><input type=\"hidden\" name='id[]' value='{$row['id']}'>{$row['id']}</td>\n";
    print "<td class=\"data\"><input type=\"text\" name='f_building_name[]' value='{$row['name']}'></td>\n";
    print "<td class=\"data\"><input type=\"text\" name='f_building_comment[]' value='{$row['comment']}'></td>\n";
    print "<td class=\"data\"><button name='save[]' value='{$row['id']}'>Сохранить</button></td>\n";
    print "</tr>\n";
}
?>
</table>
<table>
<tr>
<td><input type=text name=new_building value="Unknown"></td>
<td><input type="submit" name="create" value="Добавить"></td>
<td align="right"></td>
</tr>
</table>
</form>
<?
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/footer.php");
?>
