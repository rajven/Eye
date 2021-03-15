<?php
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/auth.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/languages/" . $language . ".php");
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
}

unset($_POST);

require_once ($_SERVER['DOCUMENT_ROOT']."/inc/header.php");
$customer=get_record($db_link,'Customers',"id=".$id);
?>
<div id="cont">

	<form name="def" action="editcustom.php?id=<? echo $id; ?>" method="post">
		<input type="hidden" name="id" value=<? echo $id; ?>>
		<table class="data">
			<tr>
				<td>Login</td>
				<td>Password</td>
				<td>RO</td>
			</tr>
			<tr>
				<td><input type="text" name="login" value="<?php print $customer['Login']; ?>" size=20></td>
				<td><input type="text" name="pass" value="" size=20></td>
				<td><?php print_qa_select('f_ro',$customer['readonly']); ?></td>
			</tr>
			<td colspan=2><input type="submit" name="edituser" value="Save"></td>
		</table>
	</form>
<?
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/footer.php");
?>