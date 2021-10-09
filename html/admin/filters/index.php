<?php
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/auth.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/languages/" . $language . ".php");

if (isset($_POST["create"])) {
    $fname = $_POST["newfilter"];
    $ftype = 0;
    if (isset($_POST['filter_type'])) {
        $ftype = $_POST["filter_type"] * 1;
	}
    if (isset($fname)) {
        $new['name'] = $fname;
        $new['type'] = $ftype;
        $new_id=insert_record($db_link, "Filter_list", $new);
        header("Location: editfilter.php?id=$new_id");
	}
    }

if (isset($_POST["remove"])) {
    $fid = $_POST["fid"];
    foreach ($fid as $key => $val) {
        if ($val) {
            delete_record($db_link, "Group_filters", "filter_id=$val");
            delete_record($db_link, "Filter_list", "id=$val");
        }
    }
    header("Location: " . $_SERVER["REQUEST_URI"]);
}
unset($_POST);
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/header.php");
print_filters_submenu($page_url);
?>
<div id="cont">
<form name="def" action="index.php" method="post">
	<table class="data">
	<tr align="center">
		<td><input type="checkbox" onClick="checkAll(this.checked);"></td>
		<td><b>id</b></td>
		<td><b>Имя</b></td>
		<td><b>Тип</b></td>
		<td><b>Протокол</b></td>
		<td><b>Адрес назначения</b></td>
		<td><b>Порт</b></td>
		<td><b>Действие</b></td>
	</tr>
<?
$filters = get_records($db_link,'Filter_list','TRUE ORDER BY name');
foreach ($filters as $row) {
    print "<tr align=center>\n";
    print "<td class=\"data\" style='padding:0'><input type=checkbox name=fid[] value=".$row['id']."></td>\n";
    print "<td class=\"data\" ><input type=hidden name=\"id\" value=".$row['id'].">".$row['id']."</td>\n";
    print "<td class=\"data\" align=left><a href=editfilter.php?id=".$row['id'].">" . $row['name'] . "</a></td>\n";
    if ($row['type'] == 0) {
        print "<td class=\"data\">IP фильтр</td>\n";
        print "<td class=\"data\">".$row['proto']."</td>\n";
        print "<td class=\"data\">".$row['dst']."</td>\n";
        print "<td class=\"data\">".$row['dstport']."</td>\n";
        print "<td class=\"data\">" . get_action($row['action']) . "</td>\n<tr>";
    } else {
        print "<td class=\"data\">Name фильтр</td>\n";
        print "<td class=\"data\"></td>\n";
        print "<td class=\"data\">".$row['dst']."</td>\n";
        print "<td class=\"data\"></td>\n";
        print "<td class=\"data\">" . get_action($row['action']) . "</td>\n<tr>";
    }
}
?>
</table>
<table class="data">
	<tr align=left>
	<td>Название <input type=text name=newfilter value="Unknown"></td>
	<td>Тип фильтра <select name="filter_type" disabled=true>
	<option value=0 selected>IP фильтр</option>
	<option value=1>Name фильтр</option>
	</select>
	</td>
	<td><input type="submit" name="create" value="Добавить"></td>
	<td align="right"><input type="submit" onclick="return confirm('Удалить?')" name="remove" value="Удалить"></td>
	</tr>
	</table>
</form>
<?

require_once ($_SERVER['DOCUMENT_ROOT']."/inc/footer.php");
?>
