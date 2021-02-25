<?php
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/auth.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/languages/" . $language . ".php");

if (isset($_POST["create"])) {
    $fname = $_POST["newgroup"];
    if ($fname) {
        $new[group_name] = $fname;
        insert_record($db_link, "Group_list", $new);
        list ($new_id) = mysqli_fetch_array(mysqli_query($db_link, "Select id from Group_list where group_name='$fname' order by id DESC"));
        header("location: editgroup.php?id=$new_id[0]");
    }
    header("Location: " . $_SERVER["REQUEST_URI"]);
}

unset($_POST);
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/header.php");

print_filters_submenu($page_url);
?>
<div id="cont">
<br> <b>Список групп</b> <br>
<form name="def" action="groups.php" method="post">
<table class="data">
<tr align="center">
	<td><input type="checkbox" onClick="checkAll(this.checked);"></td>
	<td><b>Id</b></td>
	<td width=200><b>Название</b></td>
</tr>
<?
$users = mysqli_query($db_link, "select * from Group_list order by id");
while (list ($id, $grpname) = mysqli_fetch_array($users)) {
    print "<tr align=center>\n";
    print "<td class=\"data\" style='padding:0'><input type=checkbox name=fid[] value=$id></td>\n";
    print "<td class=\"data\" ><input type=\"hidden\" name=\"id\" value=$id>$id</td>\n";
    print "<td class=\"data\"><a href=editgroup.php?id=$id>" . $grpname . "</a></td>\n";
}
?>
</table>
<table class="data">
	<tr align=left>
		<td>Название <input type=text name=newgroup value="Unknown"></td>
		<td><input type="submit" name="create" value="Добавить"></td>
		<td align="right"><input type="submit" name="remove" value="Удалить"></td>
		</tr>
	</table>
</form>
<?
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/footer.php");
?>