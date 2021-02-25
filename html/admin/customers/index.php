<?php
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/auth.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/languages/" . $language . ".php");

$msg_error = "";

if (isset($_POST["create"])) {
    $login = $_POST["newlogin"];
    if ($login) {
        list ($lcount) = mysqli_fetch_array(mysqli_query($db_link, "Select count(id) from Customers where LCase(Login)=LCase('$login')"));
        if ($lcount > 0) {
            $msg_error = "Ошибка создания менеджера. Логин $login в базе уже есть!";
            LOG_INFO($db_link, $msg_error);
            unset($_POST);
        } else {
            $new[login] = $login;
            insert_record($db_link, "Customers", $new);
            list ($id) = mysqli_fetch_array(mysqli_query($db_link, "Select id from Customers where Login='$login' order by id DESC"));
            LOG_INFO($db_link, "Создание нового менеджера login: $login");
            header("location: editcustom.php?id=$id");
        }
    }
    header("Location: " . $_SERVER["REQUEST_URI"]);
}

if (isset($_POST["remove"])) {
    $fid = $_POST["fid"];
    while (list ($key, $val) = @each($fid)) {
        if ($val) {
            LOG_INFO($db_link, "Удаляем менеджера с id: $val");
            delete_record($db_link, "Customers", "id=" . $val);
        }
    }
    header("Location: " . $_SERVER["REQUEST_URI"]);
}
unset($_POST);
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/header.php");

print_control_submenu($page_url);

?>
<div id="cont">
<br>
<form name="def" action="index.php" method="post">
<b>Список администраторов.</b>
<table class="data">
<tr align="center">
<td width="30"><input type="checkbox" onClick="checkAll(this.checked);"></td>
<td><b>Login</b></td>
</tr>
<?
$users = get_records($db_link,'Customers','True ORDER BY Login');
foreach ($users as $row) {
    $cl = "data";
    print "<tr align=center>\n";
    print "<td class=\"$cl\" style='padding:0'><input type=checkbox name=fid[] value=".$row['id']."></td>\n";
    print "<td class=\"$cl\" align=left width=200><a href=editcustom.php?id=".$row['id'].">" . $row['Login'] . "</a></td>\n";
}
?>
</table>
<table class="data">
	<tr>
		<td><input type=text name=newlogin value="Unknown"></td>
		<td><input type="submit" name="create" value="Добавить логин"></td>
		<td align="right"><input type="submit" name="remove" value="Удалить"></td>
		</tr>
	</table>
</form>
<?php
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/footer.php");
?>