<?php

require_once ($_SERVER['DOCUMENT_ROOT']."/inc/auth.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/languages/" . HTML_LANG . ".php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/idfilter.php");

$msg_error = "";

$sSQL = "SELECT * FROM user_list WHERE id=?";
$auth_info = get_record_sql($db_link, $sSQL, [ $id ]);

if (isset($_POST["s_remove"])) {
    $s_id = $_POST["s_id"];
    foreach ($s_id as $key => $val) {
        if (isset($val)) {
            LOG_INFO($db_link, "Remove rule id: $val ".dump_record($db_link,'auth_rules','id=?', [ $val ]));
            delete_record($db_link, "auth_rules", "id=?" , [ $val ]);
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
            $new['rule_type'] = $_POST['s_type'][$j]*1;
            $new['rule'] = trim($_POST['s_rule'][$j]);
            $new['description'] = trim($_POST['s_description'][$j]);
            if ($new['rule_type'] ==2) {
                $new['rule'] = mac_dotted($new['rule']);
                }
	    update_auth_rule($db_link,$new,$save_id);
        }
    }
    header("Location: " . $_SERVER["REQUEST_URI"]);
    exit;
}

if (isset($_POST["s_create"])) {
    $new_rule = $_POST["s_new_rule"];
    if (isset($new_rule)) {
	add_auth_rule($db_link,$new_rule,$_POST["s_new_type"],$id);
	}
    header("Location: " . $_SERVER["REQUEST_URI"]);
    exit;
}

unset($_POST);

fix_auth_rules($db_link);

require_once ($_SERVER['DOCUMENT_ROOT']."/inc/header.php");

?>
<div id="cont">
<br>
<form name="def" action="edit_rules.php?id=<?php echo $id; ?>" method="post">
<b><?php print WEB_ou_rules_for_autoassigning."&nbsp"; print_url($auth_info['login'],"/admin/users/edituser.php?id=$id"); ?></b>
<br>
<?php echo WEB_ou_rules_order; ?>:  hotspot => subnet => mac => hostname => default user
<br><br>
<table class="data">
<tr align="center">
    <td></td>
    <td width=30><b>id</b></td>
    <td><b><?php echo WEB_cell_type; ?></b></td>
    <td><b><?php echo WEB_ou_rule; ?></b></td>
    <td><b><?php echo WEB_cell_description; ?></b></td>
    <td><input type="submit" onclick="return confirm('<?php echo WEB_msg_delete; ?>?')" name="s_remove" value="<?php echo WEB_btn_delete; ?>"></td>
</tr>
<?php
$t_auth_rules = get_records_sql($db_link,"SELECT * FROM auth_rules WHERE user_id=? ORDER BY id", [ $id ]);
foreach ( $t_auth_rules as $row ) {
    print "<tr align=center>\n";
    print "<td class=\"data\" style='padding:0'><input type=checkbox name=s_id[] value='{$row['id']}'></td>\n";
    print "<td class=\"data\"><input type=\"hidden\" name='n_id[]' value='{$row['id']}'>{$row['id']}</td>\n";
    print "<td class=\"data\">"; print_qa_rule_select("s_type[]","{$row['rule_type']}"); print "</td>\n";
    print "<td class=\"data\"><input type=\"text\" name='s_rule[]' value='{$row['rule']}'></td>\n";
    print "<td class=\"data\"><input type=\"text\" name='s_description[]' value='{$row['description']}'></td>\n";
    print "<td class=\"data\"><button name='s_save[]' value='{$row['id']}'>".WEB_btn_save."</button></td>\n";
    print "</tr>\n";
}
?>
</table>
<div>
<?php print WEB_ou_new_rule."&nbsp"; print_qa_rule_select("s_new_type","1");  
print "<input type=\"text\" name='s_new_rule' value=''>"; ?>
<input type="submit" name="s_create" value="<?php echo WEB_btn_add; ?>">
</div>
</form>
<?php
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/footer.php");
?>
