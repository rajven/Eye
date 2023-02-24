<?php
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/auth.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/languages/" . HTML_LANG . ".php");

if (isset($_POST["create"])) {
    $fname = $_POST["newgroup"];
    if ($fname) {
        $new['group_name'] = $fname;
        $new_id=insert_record($db_link, "Group_list", $new);
        header("location: editgroup.php?id=$new_id");
        exit;
    }
    header("Location: " . $_SERVER["REQUEST_URI"]);
    exit;
}

if (isset($_POST["remove"])) {
    $fgid = $_POST["fid"];
    foreach ($fgid as $key => $val) {
        if (!empty($val)) { delete_record($db_link, "Group_list", "id=" . $val * 1); }
        }
    header("Location: " . $_SERVER["REQUEST_URI"]);
    exit;
    }

unset($_POST);
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/header.php");

print_filters_submenu($page_url);
?>
<div id="cont">
<form name="def" action="groups.php" method="post">
<table class="data">
<tr align="center">
	<td><input type="checkbox" onClick="checkAll(this.checked);"></td>
	<td><b>Id</b></td>
	<td width=200><b>Название</b></td>
	<td width=200><b>Комментарий</b></td>
</tr>
<?php
$groups = get_records_sql($db_link,'SELECT * FROM Group_list ORDER BY id');
foreach ($groups as $row) {
    print "<tr align=center>\n";
    print "<td class=\"data\" style='padding:0'><input type=checkbox name=fid[] value=".$row["id"]."></td>\n";
    print "<td class=\"data\" ><input type=\"hidden\" name=\"".$row["id"]."\" value=".$row["id"].">".$row["id"]."</td>\n";
    print "<td class=\"data\"><a href=editgroup.php?id=".$row["id"].">" . $row["group_name"] . "</a></td>\n";
    print "<td class=\"data\">" . $row["comment"] . "</td>\n";
}
?>
</table>
<table class="data">
	<tr align=left>
		<td>Название <input type=text name=newgroup value="Unknown"></td>
		<td><input type="submit" name="create" value="Добавить"></td>
		<td align="right"><input type="submit" onclick="return confirm('Удалить?')" name="remove" value="Удалить"></td>
		</tr>
	</table>
</form>
<?php
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/footer.php");
?>