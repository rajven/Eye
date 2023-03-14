<?php
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/auth.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/languages/" . HTML_LANG . ".php");

if (isset($_POST["remove"])) {
    $fid = $_POST["f_id"];
    foreach ($fid as $key => $val) {
        if (isset($val) and $val > 0) {
            run_sql($db_link, "UPDATE User_list SET ou_id=0 WHERE ou_id=$val");
            run_sql($db_link, "UPDATE User_auth SET ou_id=0 WHERE ou_id=$val");
            delete_record($db_link, "OU", "id=" . $val);
            }
        }
    header("Location: " . $_SERVER["REQUEST_URI"]);
    exit;
    }

if (isset($_POST["create"])) {
    $ou_name = $_POST["new_ou"];
    if (isset($ou_name)) {
        $new['ou_name'] = $ou_name;
        insert_record($db_link, "OU", $new);
        }
    header("Location: " . $_SERVER["REQUEST_URI"]);
    exit;
    }

unset($_POST);
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/header.php");
?>
<div id="cont">
<table>
<tr>
<td><b><?php echo WEB_list_ou; ?></b><br>
<form name="def" action="index.php" method="post">
<table class="data">
<tr align="center">
<td><input type="checkbox" onClick="checkAll(this.checked);"></td>
<td><b>Id</b></td>
<td><b><?php echo WEB_cell_flags; ?></b></td>
<td><b><?php echo WEB_cell_name; ?></b></td>
<td>
<input type="submit" onclick="return confirm('<?php echo WEB_msg_delete; ?>?')" name="remove" value="<?php echo WEB_btn_delete; ?>">
</td>
</tr>
<?php
$t_ou = get_records($db_link,'OU','TRUE ORDER BY ou_name');
foreach ($t_ou as $row) {
    print "<tr align=center>\n";
    print "<td class=\"data\" style='padding:0'><input type=checkbox name=f_id[] value='{$row['id']}'></td>\n";
    print "<td class=\"data\"><input type=\"hidden\" name='r_id[]' value='{$row['id']}'>{$row['id']}</td>\n";
    $flag='';
    if ($row['default_users'] == 1) { $flag='D'; }
    if ($row['default_hotspot'] == 1) { $flag='H'; }
    print "<td class=\"data\">$flag</td>\n";
    print "<td class=\"data\">"; print_url($row['ou_name'],"/admin/groups/edit_group.php?id=".$row['id']); print "</td>\n";
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
