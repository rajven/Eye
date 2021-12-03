<?php

require_once ($_SERVER['DOCUMENT_ROOT']."/inc/auth.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/languages/" . $language . ".php");

if (isset($_POST["remove"])) {
    $fid = $_POST["f_id"];
    if (!empty($fid)) {
        foreach ($fid as $key => $val) {
            if (isset($val) and $val != 1) {
                LOG_INFO($db_link, "Remove config option id: $val");
                delete_record($db_link, "config", "id=" . $val);
                }
            }
        }
    header("Location: " . $_SERVER["REQUEST_URI"]);
    exit;
}

if (isset($_POST['save'])) {
    $len = is_array($_POST['save']) ? count($_POST['save']) : 0;
    for ($i = 0; $i < $len; $i ++) {
        $save_id = intval($_POST['save'][$i]);
        $len_all = is_array($_POST['id']) ? count($_POST['id']) : 0;
        for ($j = 0; $j < $len_all; $j ++) {
            if (intval($_POST['id'][$j]) != $save_id) { continue; }
            $value = $_POST['f_config_value'][$j];
            if (isset($value) and $value!=='********') {
                $new['value'] = $value;
                update_record($db_link, "config", "id='{$save_id}'", $new);
            }
        }
    }
    header("Location: " . $_SERVER["REQUEST_URI"]);
    exit;
}

if (isset($_POST["create"])) {
    $new_option = $_POST["f_new_option"];
    if (isset($new_option)) {
        $new['option_id'] = $new_option;
        $new['value'] = get_option($db_link,$new_option);
        LOG_INFO($db_link, "Add config option $new_option");
        insert_record($db_link, "config", $new);
        }
    header("Location: " . $_SERVER["REQUEST_URI"]);
    exit;
}

unset($_POST);

fix_auth_rules($db_link);

require_once ($_SERVER['DOCUMENT_ROOT']."/inc/header.php");

print_control_submenu($page_url);

?>
<div id="cont">
<br>
<form name="def" action="control-options.php" method="post">
<br><b>Настройки</b><br>
<table class="data" width=800>
<tr align="center">
<td width=20><input type="checkbox" onClick="checkAll(this.checked);"></td>
<td width=30><b>Id</b></td>
<td width=150><b>Параметр</b></td>
<td width=150><b>Значение</b></td>
<td width=350><b>Комментарий</b></td>
<td width=100><input type="submit" onclick="return confirm('Удалить?')" name="remove" value="Удалить"></td>
</tr>

<?php
$t_config = mysqli_query($db_link, "select config.id,option_id,option_name,value,type,description,min_value,max_value from config,config_options where config.option_id=config_options.id order by option_name");
while ($row = mysqli_fetch_array($t_config)) {
    print "<tr align=center>\n";
    print "<td class=\"data\" style='padding:0'><input type=checkbox name=f_id[] value='{$row['id']}'></td>\n";
    print "<td class=\"data\"><input type=\"hidden\" name='id[]' value='{$row['id']}'>{$row['id']}</td>\n";
    print "<td class=\"data\"><input type=\"text\" name='f_config_option[]' value='{$row['option_name']}' disabled=true readonly=true></td>\n";
    $type = $row['type'];
    print "<td class=\"data\">";
    $option_value = $row['value'];
    if ($row['option_id']==29) { $option_value='********'; }
    if (!isset($option_value)) {
        $option_value = get_option($db_link, $row['option_id']);
        set_option($db_link, $row['option_id'], $option_value);
    }
    if ($type == 'int') {
        $min = '';
        $max = '';
        if (!empty($row['min_value']) or $row['min_value']==0) { $min="min=".$row['min_value']; }
        if (!empty($row['max_value'])) { $max="max=".$row['max_value']; }
        print "<input type=\"number\" name='f_config_value[]' value='$option_value' $min $max>";
    }
    if ($type == 'text') {
        print "<input type=\"text\" name='f_config_value[]' value='$option_value' size=30>";
    }
    if ($type == 'bool') {
        print_qa_select("f_config_value[]", $option_value);
    }
    print "</td>\n";
    print "<td class=\"data\">".$row['description']."</td>\n";
    print "<td class=\"data\"><button name='save[]' value='{$row['id']}'>Сохранить</button></td>\n";
    print "</tr>\n";
}
?>
<tr>
<td colspan=5 class="data">Добавить параметр :<?php print_option_select($db_link, "f_new_option"); ?></td>
<td><input type="submit" name="create" value="Добавить"></td>
</tr>
</table>
</form>
<?php require_once ($_SERVER['DOCUMENT_ROOT']."/inc/footer.php"); ?>
