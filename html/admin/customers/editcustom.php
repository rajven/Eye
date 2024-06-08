<?php
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/auth.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/languages/" . HTML_LANG . ".php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/idfilter.php");

$msg_error = "";

if (isset($_POST["edituser"])) {
    global $salt;
    $new['Login'] = substr(trim($_POST["login"]), 0, 20);
    $new['comment'] = substr(trim($_POST["comment"]), 0, 100);
    if (isset($_POST["pass"]) and (strlen(trim($_POST["pass"])) > 0)) {
        $new['password'] = password_hash($_POST["pass"], PASSWORD_BCRYPT);
	}
    if (isset($_POST["api_key"]) and (strlen(trim($_POST["api_key"])) > 20)) {
        $new['api_key'] = $_POST["api_key"];
	}
    $new['rights'] = $_POST["f_acl"] * 1;
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
<br><b><?php echo WEB_customer_titles; ?></b><br>
	<form name="def" action="editcustom.php?id=<?php echo $id; ?>" method="post">
		<input type="hidden" name="id" value=<?php echo $id; ?>>
		<table class="data">
			<tr>
				<td><?php echo WEB_customer_login; ?></td>
				<td><input type="text" name="login" value="<?php print $customer['Login']; ?>" size=20></td>
			</tr>
			<tr>
				<td><?php echo WEB_cell_comment; ?></td>
				<td><input type="text" name="comment" value="<?php print $customer['comment']; ?>" size=50></td>
			</tr>
			<tr>
				<td><?php echo WEB_customer_password; ?></td>
				<td><input type="password" name="pass" value="" size=20></td>
			</tr>
			<tr>
				<td><?php echo WEB_customer_api_key; ?></td>
				<td><input type="text" name="api_key" value="<?php print $customer['api_key']; ?>" size=50></td>
			</tr>
			<tr>
				<td><?php echo WEB_customer_mode; ?></td>
				<td><?php print_acl_select($db_link,'f_acl',$customer['rights']); ?></td>
			</tr>
                        <tr>
        			<td colspan=2><input type="submit" name="edituser" value="<?php echo WEB_btn_save; ?>"></td>
                        </tr>
		</table>
	</form>
<?php require_once ($_SERVER['DOCUMENT_ROOT']."/inc/footer.php"); ?>
