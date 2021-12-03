<?php
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/auth.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/languages/" . $language . ".php");
require_once ($_SERVER["DOCUMENT_ROOT"]."/inc/idfilter.php");
global $default_user_ou_id;
global $default_hotspot_ou_id;

if (isset($_POST['save'])) {
        $new['ou_name'] = $_POST['f_group_name'];
        $new['default_users'] = $_POST['f_default']*1;
        $new['default_hotspot'] = $_POST['f_default_hotspot']*1;
        $new['nagios_dir'] = $_POST['f_nagios'];
        $new['nagios_host_use'] = $_POST['f_nagios_host'];
        $new['nagios_ping'] = $_POST['f_nagios_ping'];
        $new['nagios_default_service'] = $_POST['f_nagios_service'];
        if ($new['default_users'] == TRUE) { run_sql($db_link,"UPDATE OU set default_users=0 WHERE id!='{$id}'"); }
        if ($new['default_hotspot'] == TRUE) { run_sql($db_link,"UPDATE OU set default_hotspot=0 WHERE id!='{$id}'"); }
        update_record($db_link, "OU", "id='{$id}'", $new);
        header("Location: " . $_SERVER["REQUEST_URI"]);
	exit;
	}

if (isset($_POST["s_remove"])) {
    $s_id = $_POST["s_id"];
    foreach ($s_id as $key => $val) {
        if (isset($val)) {
            LOG_INFO($db_link, "Remove rule id: $val");
            delete_record($db_link, "auth_rules", "id=" . $val);
        }
    }
    header("Location: " . $_SERVER["REQUEST_URI"]);
    exit;
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
    exit;
}

if (isset($_POST["s_create"])) {
    $new_rule = $_POST["s_new_rule"];
    if (!empty($new_rule)) {
        $new['type'] = $_POST["s_new_type"];
        $new['rule'] = $new_rule;
        $new['ou_id'] = $id;
        LOG_INFO($db_link, "Create new rule $new_rule for ou_id: $id");
        insert_record($db_link, "auth_rules", $new);
    }
    header("Location: " . $_SERVER["REQUEST_URI"]);
    exit;
}

unset($_POST);
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/header.php");

fix_auth_rules($db_link);

?>
<div id="cont">
<form name="def" action="edit_group.php" method="post">
<table class="data">
<tr align="center">
<td><b>Название</b></td>
<td><b>Default</b></td>
<td><b>Hotspot</b></td>
<td><b>Nagios directory</b></td>
<td><b>Host template</b></td>
<td><b>Ping</b></td>
<td><b>Host service</b></td>
</tr>
<?php
$ou_info = get_record_sql($db_link,'SELECT * FROM OU WHERE id='.$id);
print "<tr align=center>\n";
print "<td class=\"data\"><input type=\"text\" name='f_group_name' value='{$ou_info['ou_name']}'></td>\n";
if ($ou_info['default_users']) { $cl = "up"; } else { $cl="data"; }
print "<td class=\"$cl\">";  print_qa_select("f_default",$ou_info['default_users']); print "</td>\n";
if ($ou_info['default_hotspot']) { $cl = "up"; } else { $cl="data"; }
print "<td class=\"$cl\">";  print_qa_select("f_default_hotspot",$ou_info['default_hotspot']); print "</td>\n";
print "<td class=\"data\"><input type=\"text\" name='f_nagios' value='{$ou_info['nagios_dir']}'></td>\n";
print "<td class=\"data\"><input type=\"text\" name='f_nagios_host' value='{$ou_info['nagios_host_use']}'></td>\n";
print "<td class=\"data\">"; print_qa_select("f_nagios_ping",$ou_info['nagios_ping']); print "</td>\n";
print "<td class=\"data\"><input type=\"text\" name='f_nagios_service' value='{$ou_info['nagios_default_service']}'></td>\n";
print "<td class=\"data\"><button name='save' value='{$ou_info['id']}'>Сохранить</button></td>\n";
print "</tr>\n";
?>
</table>
<br>
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
    <td><input type="submit" onclick="return confirm('Удалить?')" name="s_remove" value="Удалить"></td>
</tr>
<?php
$t_auth_rules = get_records($db_link,'auth_rules',"ou_id=$id ORDER BY id");
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
<?php require_once ($_SERVER['DOCUMENT_ROOT']."/inc/footer.php"); ?>
