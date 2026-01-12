<?php
$default_displayed=50;
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/auth.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/languages/" . HTML_LANG . ".php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/header.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/rulesfilter.php");

if (getPOST("removeRule") !== null) {
    $r_id = getPOST("f_id", null, []);
    if (!empty($r_id) && is_array($r_id)) {
        foreach ($r_id as $val) {
            $val = trim($val);
            if ($val !== '') {
                delete_record($db_link, "auth_rules", "id = ?", [(int)$val]);
            }
        }
    }
    
    header("Location: " . $_SERVER["REQUEST_URI"]);
    exit;
}

print_ip_submenu($page_url);
?>
<div id="cont">
<br>
<form name="def" action="auto_rules.php" method="post">

<table>
<tr>
        <td>
        <b><?php echo WEB_rules_search_target; ?> - </b><?php print_rule_target_select('rule_target', $rule_target); ?>
        </td>
        <td>
        <b><?php echo WEB_rules_search_type; ?> - </b><?php print_rule_type_select('rule_type', $rule_type); ?>
        </td>
        <td></td>
</tr>
<tr>
        <td colspan=2>
        <?php echo WEB_ips_search; ?>:&nbsp<input type="text" name="f_rule" value="<?php echo $f_rule; ?>"/>
        </td>
        <td>
        <?php print WEB_rows_at_page."&nbsp"; print_row_at_pages('rows',$displayed); ?>
        <input id="btn_filter" name="btn_filter" type="submit" value="<?php echo WEB_btn_show; ?>">
        </td>
</tr>
</table>

<?php

$target_filter='';
$params=[];
if ($rule_target>0) {
    if ($rule_target==1) { $target_filter = ' AND user_id>0'; }
    if ($rule_target==2) { $target_filter = ' AND ou_id>0'; }
    }

$type_filter='';
if ($rule_type>0) { $type_filter = ' AND type=?'; $params[]=$rule_type; }

$rule_filter='';
if (!empty($f_rule)) { $rule_filter = ' AND rule LIKE ?'; $params[]=$f_rule.'%'; }

$rule_filters = '';
if (!empty($target_filter) or !empty($type_filter) or !empty($rule_filter)) {
    $rule_filters='WHERE id '.$target_filter.$type_filter.$rule_filter;
    }

fix_auth_rules($db_link);
$countSQL="SELECT Count(*) FROM auth_rules $rule_filters";
$count_records = get_single_field($db_link,$countSQL, $params);
$total=ceil($count_records/$displayed);
if ($page>$total) { $page=$total; }
if ($page<1) { $page=1; }
$start = ($page * $displayed) - $displayed;
print_navigation($page_url,$page,$displayed,$count_records,$total);
?>

<table class="data">
<tr align="center">
<td><input type="checkbox" onClick="checkAll(this.checked);"></td>
<td><b><?php echo WEB_cell_type; ?></b></td>
<td><b><?php echo WEB_ou_rule; ?></b></td>
<td colspan=2><b><?php echo WEB_rules_target; ?></b></td>
<td><b><?php echo WEB_cell_description; ?></b></td>
<td align=right><input type="submit" onclick="return confirm('<?php echo WEB_msg_delete; ?>?')" name="removeRule" value="<?php echo WEB_btn_delete; ?>"></td>
</tr>
<?php
$rulesSQL = "SELECT * FROM auth_rules $rule_filters ORDER BY id LIMIT ? OFFSET ?";
$params[]=$displayed;
$params[]=$start;

$t_auth_rules = get_records_sql($db_link,$rulesSQL, $params);
foreach ( $t_auth_rules as $row ) {
    print "<tr align=center>\n";
    print "<td class=\"data\" style='padding:0'><input type=checkbox name=f_id[] value=".$row["id"]." ></td>\n";
    print "<td class=\"data\">";
    if ($row['type'] == 1) { print "Subnet"; }
    if ($row['type'] == 2) { print "Mac"; }
    if ($row['type'] == 3) { print "Hostname"; }
    print "</td>\n";
    print "<td class=\"data\">".$row['rule']."</td>\n";
    print "<td colspan=2 class=\"data\" align=left>";
    if (!empty($row['user_id'])) {
	$user_info=get_record_sql($db_link,"SELECT * FROM user_list WHERE id=?", [$row['user_id']]);
	if (!empty($user_info)) { print "User: &nbsp"; print_url($user_info['login'],'/admin/users/edituser.php?id='.$user_info['id']); }
	}
    if (!empty($row['ou_id'])) {
	$ou_info=get_record_sql($db_link,"SELECT * FROM ou WHERE id=?", [ $row['ou_id'] ]);
	if (!empty($ou_info)) { print "Group: &nbsp"; print_url($ou_info['ou_name'],'/admin/groups/edit_group.php?id='.$ou_info['id']); }
	}
    print "</td>";
    print "<td class=\"data\" colspan=2>".$row['description']."</td>\n";
    print "</tr>\n";
}
?>
</table>
</form>

<script>
document.getElementById('rule_target').addEventListener('change', function(event) {
  const buttonApply = document.getElementById('btn_filter');
  buttonApply.click();
});
document.getElementById('rule_type').addEventListener('change', function(event) {
  const buttonApply = document.getElementById('btn_filter');
  buttonApply.click();
});
document.getElementById('rows').addEventListener('change', function(event) {
  const buttonApply = document.getElementById('btn_filter');
  buttonApply.click();
});

</script>

<?php
print_navigation($page_url,$page,$displayed,$count_records,$total);
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/footer.php"); 
?>
