<?php

require_once ($_SERVER['DOCUMENT_ROOT']."/inc/auth.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/languages/" . $language . ".php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/idfilter.php");

global $default_user_id;
global $hotspot_user_id;

$msg_error = "";

$sSQL = "SELECT * FROM User_list WHERE id=$id";
$auth_info = get_record_sql($db_link, $sSQL);

if (isset($_POST["s_remove"])) {
    $s_id = $_POST["s_id"];
    foreach ($s_id as $key => $val) {
        if (isset($val)) {
            LOG_INFO($db_link, "Remove rule id: $val");
            delete_record($db_link, "auth_rules", "id=" . $val);
        }
    }
    header("Location: " . $_SERVER["REQUEST_URI"]);
}

if (isset($_POST['s_save'])) {
    $len = is_array($_POST['s_save']) ? count($_POST['s_save']) : 0;
    for ($i = 0; $i < $len; $i ++) {
        $save_id = intval($_POST['s_save'][$i]);
        $len_all = is_array($_POST['n_id']) ? count($_POST['n_id']) : 0;
        for ($j = 0; $j < $len_all; $j ++) {
            if (intval($_POST['n_id'][$j]) != $save_id) { continue; }
            $new['type'] = $_POST['s_type'][$j];
            $new['rule'] = trim($_POST['s_rule'][$j]);
            update_record($db_link, "auth_rules", "id='{$save_id}'", $new);
        }
    }
    header("Location: " . $_SERVER["REQUEST_URI"]);
}

if (isset($_POST["s_create"])) {
    $new_rule = $_POST["s_new_rule"];
    if (isset($new_rule)) {
        $new['type'] = $_POST["s_new_type"];
        $new['rule'] = $new_rule;
        $new['user_id'] = $id;
        LOG_INFO($db_link, "Create new rule $new_rule");
        insert_record($db_link, "auth_rules", $new);
    }
    header("Location: " . $_SERVER["REQUEST_URI"]);
}

unset($_POST);

global $default_user_id;
global $hotspot_user_id;

//cleanup hotspot subnet rules
delete_record($db_link,"auth_rules","user_id=".$default_user_id);
delete_record($db_link,"auth_rules","user_id=".$hotspot_user_id);
$t_hotspot = get_records_sql($db_link,"subnets","hotspot=1");
foreach ($t_hotspot as $row) { delete_record($db_link,"auth_rules","rule='".$row['subnet']."'"); }

require_once ($_SERVER['DOCUMENT_ROOT']."/inc/header.php");

?>
<div id="cont">
<br>
<form name="def" action="edit_rules.php" method="post">
<b>Правила автоназначения адресов в <?php print_url($auth_info['login'],"/admin/users/edituser.php?id=$id"); ?></b>
<br>
Порядок применения: hotspot => subnet => mac => hostname => default user
<br><br>
<table class="data">
<tr align="center">
	<td></td>
	<td width=30><b>id</b></td>
	<td><b>Тип</b></td>
	<td><b>Правило</b></td>
	<td><input type="submit" name="s_remove" value="Удалить"></td>
</tr>
<?
$t_auth_rules = get_records($db_link,'auth_rules',"user_id=$id ORDER BY id");
foreach ( $t_auth_rules as $row ) {
    print "<tr align=center>\n";
    print "<td class=\"data\" style='padding:0'><input type=checkbox name=s_id[] value='{$row['id']}'></td>\n";
    print "<td class=\"data\"><input type=\"hidden\" name='n_id[]' value='{$row['id']}'>{$row['id']}</td>\n";
    print "<td class=\"data\">"; print_qa_rule_select("s_type[]","{$row['type']}"); print "</td>\n";
    print "<td class=\"data\"><input type=\"text\" name='s_rule[]' value='{$row['rule']}'></td>\n";
    print "<td class=\"data\"><button name='s_save[]' value='{$row['id']}'>Сохранить</button></td>\n";
    print "</tr>\n";
}
?>
<tr>
<td colspan=6>Новое правило :<?php print_qa_rule_select("s_new_type","1");  print "<input type=\"text\" name='s_new_rule' value=''>"; ?></td>
<td><input type="submit" name="s_create" value="Добавить"></td>
</tr>
</table>
</form>
<?php
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/footer.php");
?>
