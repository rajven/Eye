<?php
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/auth.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/languages/" . HTML_LANG . ".php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/idfilter.php");

$msg_error = "";

if (isset($_POST["edituser"])) {
    $new['Login'] = substr(trim($_POST["login"]), 0, 20);
    if (isset($_POST["pass"]) and (strlen(trim($_POST["pass"])) > 0)) {
        $new['Pwd'] = md5($_POST["pass"]);
    }
    $new['readonly'] = $_POST["f_ro"] * 1;
    update_record($db_link, "Customers", "id='$id'", $new);
    unset($_POST["pass"]);
    header("Location: " . $_SERVER["REQUEST_URI"]);
    exit;
}

unset($_POST);

print_control_submenu($page_url);

require_once ($_SERVER['DOCUMENT_ROOT']."/inc/header.php");
$customer=get_record($db_link,'Customers',"id=".$id);
?>
<div id="cont">
<br><b><?php echo WEB_custom_titles; ?></b><br>
	<form name="def" action="editcustom.php?id=<?php echo $id; ?>" method="post">
		<input type="hidden" name="id" value=<?php echo $id; ?>>
		<table class="data">
			<tr>
				<td><?php echo WEB_custom_login; ?></td>
				<td><?php echo WEB_custom_password; ?></td>
				<td><?php echo WEB_custom_mode; ?></td>
			</tr>
			<tr>
				<td><input type="text" name="login" value="<?php print $customer['Login']; ?>" size=20></td>
				<td><input type="text" name="pass" value="" size=20></td>
				<td><?php print_qa_select('f_ro',$customer['readonly']); ?></td>
			</tr>
			<td colspan=2><input type="submit" name="edituser" value="<?php echo WEB_btn_save; ?>"></td>
		</table>
	</form>
<?php require_once ($_SERVER['DOCUMENT_ROOT']."/inc/footer.php"); ?>
