<?php
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/auth.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/languages/" . HTML_LANG . ".php");

$msg_error = "";

if (isset($_POST["create"])) {
    $login = $_POST["newlogin"];
    if ($login) {
	$customer = get_record_sql($db_link,"Select * from customers WHERE LCase(login)=LCase('$login')");
        if (!empty($customer)) {
            $msg_error = "Login $login already exists!";
            LOG_ERROR($db_link, $msg_error);
            unset($_POST);
        } else {
            $new['login'] = $login;
	    $new['api_key'] = randomPassword(20);
            $new['rights'] = 3;
            LOG_INFO($db_link, "Create new login: $login");
            $id = insert_record($db_link, "customers", $new);
	    if (!empty($id)) { header("Location: editcustom.php?id=$id"); exit; }
        }
    }
    header("Location: " . $_SERVER["REQUEST_URI"]);
    exit;
}

if (isset($_POST["remove"])) {
    $fid = $_POST["fid"];
    foreach ($fid as $key => $val) {
        if ($val) {
            LOG_INFO($db_link, "Remove login with id: $val ". dump_record($db_link,'customers','id='.$val));
            delete_record($db_link, "customers", "id=" . $val);
        }
    }
    header("Location: " . $_SERVER["REQUEST_URI"]);
    exit;
}
unset($_POST);
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/header.php");

print_control_submenu($page_url);

?>
<div id="cont">
<br>
<form name="def" action="index.php" method="post">
<b><?php echo WEB_submenu_customers; ?></b>
<table class="data">
<tr align="center">
<td width="30"><input type="checkbox" onClick="checkAll(this.checked);"></td>
<td><b>Login</b></td>
<td><b><?php echo WEB_cell_description; ?></b></td>
<td><b><?php echo WEB_customer_mode;?></b></td>
</tr>
<?php
$users = get_records($db_link,'customers','True ORDER BY login');
foreach ($users as $row) {
    $cl = "data";
    $acl = get_record_sql($db_link,'SELECT * FROM acl WHERE id='.$row['rights']);
    print "<tr align=center>\n";
    print "<td class=\"$cl\" style='padding:0'><input type=checkbox name=fid[] value=".$row['id']."></td>\n";
    print "<td class=\"$cl\" align=left width=200><a href=editcustom.php?id=".$row['id'].">" . $row['login'] . "</a></td>\n";
    print "<td class=\"$cl\" >". $row['description']. "</a></td>\n";
    print "<td class=\"$cl\" >". $acl['name']. "</a></td>\n";
}
?>
</table>
<table class="data">
	<tr>
		<td><input type=text name=newlogin value="Unknown"></td>
		<td><input type="submit" name="create" value="<?php echo WEB_btn_add; ?>"></td>
		<td align="right"><input type="submit" onclick="return confirm('<?php print WEB_btn_delete; ?>?')" name="remove" value="<?php print WEB_btn_remove; ?>"></td>
		</tr>
	</table>
</form>
<?php
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/footer.php");
?>