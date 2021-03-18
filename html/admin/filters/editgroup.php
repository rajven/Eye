<?php
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/auth.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/languages/" . $language . ".php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/idfilter.php");

if (isset($_POST["editgroup"])) {
    $new['group_name'] = substr($_POST["f_group_name"], 0, 30);
    update_record($db_link, "Group_list", "id='$id'", $new);
    header("location: index.php");
}

if (isset($_POST["addfilter"])) {
    $filter_id = $_POST["newfilter"] * 1;
    list ($forder) = mysqli_fetch_array(mysqli_query($db_link, "SELECT MAX(GF.order) FROM Group_filters GF where group_id='$id'"));
    $forder ++;
    $new['group_id'] = $id;
    $new['filter_id'] = $filter_id;
    $new['order'] = $forder;
    insert_record($db_link, "Group_filters", $new);
    header("Location: " . $_SERVER["REQUEST_URI"]);
}

if (isset($_POST["removefilter"])) {
    $fgid = $_POST["fgid"];
    foreach ($fgid as $key => $val) {
        if ($val) {
            delete_record($db_link, "Group_filters", "id=" . $val * 1);
        }
    }
    header("Location: " . $_SERVER["REQUEST_URI"]);
}
if (isset($_POST["saveorder"])) {
    if ((isset($_POST["fgid"])) and (isset($_POST["ford"]))) {
        $fgid = $_POST["fgid"];
        $ford = $_POST["ford"];
        LOG_DEBUG($db_link, "Resort filter rules for group id: $id");
        foreach ($ford as $key => $val) {
            $gid = $fgid[$key];
            $new['order'] = $val;
            update_record($db_link, "Group_filters", "id=" . $gid, $new);
        }
    }
    header("Location: " . $_SERVER["REQUEST_URI"]);
}

unset($_POST);
$group_name = get_group($db_link, $id);

require_once ($_SERVER['DOCUMENT_ROOT']."/inc/header.php");
?>
<div id="cont">
<form name="def" action="editgroup.php?id=<? echo $id; ?>" method="post">
<input type="hidden" name="id" value=<? echo $id; ?>>
<table class="data">
<tr>
<td>Название</td>
<td><input type="text" name="f_group_name" value="<? echo $group_name; ?>" size=25></td>
</tr>
<tr>
<td colspan=2><input type="submit" name="editgroup"	value="Сохранить"></td>
</tr>
</table>
<br> <b>Список фильтров группы</b><br>
<table class="data">
<tr>
<td><input type="checkbox" onClick="checkAll(this.checked);"></td>
<td>Order</td>
<td>Название фильтра</td>
<td align="right"><input type="submit" name="removefilter" value="Удалить Фильтр"></td>
</tr>

<?php
$sSQL = "SELECT G.id, G.filter_id, F.name, G.order FROM Group_filters G, Filter_list F WHERE F.id=G.filter_id and group_id=$id Order by G.order";
$flist = get_records_sql($db_link,$sSQL);
foreach ($flist as $row) {
    print "<tr align=center>\n";
    print "<td class=\"data\" style='padding:0'><input type=checkbox name=fgid[] value=".$row['id']."></td>\n";
    print "<td class=\"data\" align=left><input type=text name=ford[] value=".$row['order']." size=4 ></td>\n";
    print "<td class=\"data\" align=left><a href=editfilter.php?id=".$row['filter_id'].">" . $row['name'] . "</a></td>\n";
    print "<td class=\"data\"></td>\n";
    print "</tr>";
}
?>
</table>
<table>
<tr>
<td><input type="submit" name="addfilter" value="Добавить фильтр"> <?php print_filter_select($db_link, 'newfilter', $id); ?> </td>
<td align="right"><input type="submit" name="saveorder" value="Применить порядок"></td>
</tr>
</table>
</form>
<?
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/footer.php");
?>
