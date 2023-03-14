<?php
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/auth.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/languages/" . HTML_LANG . ".php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/idfilter.php");

if (isset($_POST["editgroup"])) {
    $new['group_name'] = $_POST["f_group_name"];
    $new['comment'] = $_POST["f_group_comment"];
    update_record($db_link, "Group_list", "id='$id'", $new);
    header("Location: " . $_SERVER["REQUEST_URI"]);
    exit;
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
    exit;
}

if (isset($_POST["removefilter"])) {
    $fgid = $_POST["fgid"];
    foreach ($fgid as $key => $val) {
        if (!empty($val)) { delete_record($db_link, "Group_filters", "id=" . $val * 1); }
    }
    header("Location: " . $_SERVER["REQUEST_URI"]);
    exit;
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
    exit;
}

unset($_POST);

$group = get_record_sql($db_link, "SELECT * FROM Group_list WHERE id=".$id);

print_filters_submenu($page_url);

require_once ($_SERVER['DOCUMENT_ROOT']."/inc/header.php");
?>
<div id="cont">
<br> <b><?php echo WEB_title_group; ?></b> <br>

<form name="def" action="editgroup.php?id=<?php echo $id; ?>" method="post">
<input type="hidden" name="id" value=<?php echo $id; ?>>
<table class="data">
<tr>
<td><?php echo WEB_cell_name; ?></td>
<td><input type="text" name="f_group_name" value="<?php echo $group['group_name']; ?>"></td>
<td><?php echo WEB_cell_comment; ?></td>
<td><input type="text" name="f_group_comment" value="<?php echo $group['comment']; ?>"></td>
</tr>
<tr>
<td colspan=2><input type="submit" name="editgroup"	value="<?php echo WEB_btn_save; ?>"></td>
</tr>
</table>
<br> <b><?php echo WEB_groups_filter_list; ?></b><br>
<table class="data">
<tr>
<td><input type="checkbox" onClick="checkAll(this.checked);"></td>
<td><?php echo WEB_group_filter_order; ?></td>
<td><?php echo WEB_group_filter_name; ?></td>
<td align="right"><input type="submit" onclick="return confirm('<?php echo WEB_msg_delete_filter; ?>?')" name="removefilter" value="<?php echo WEB_btn_delete; ?>"></td>
</tr>

<?php
$sSQL = "SELECT G.id, G.filter_id, F.name, G.order, F.comment FROM Group_filters G, Filter_list F WHERE F.id=G.filter_id and group_id=$id Order by G.order";
$flist = get_records_sql($db_link,$sSQL);
foreach ($flist as $row) {
    print "<tr align=center>\n";
    print "<td class=\"data\" style='padding:0'><input type=checkbox name=fgid[] value=".$row['id']."></td>\n";
    print "<td class=\"data\" align=left><input type=text name=ford[] value=".$row['order']." size=4 ></td>\n";
    print "<td class=\"data\" align=left><a href=editfilter.php?id=".$row['filter_id'].">" . $row['name'] . "</a></td>\n";
    print "<td class=\"data\" align=left>" . $row['comment'] . "</a></td>\n";
    print "</tr>";
}
?>
</table>
<div>
<input type="submit" name="addfilter" value="<?php echo WEB_msg_add_filter; ?>"> <?php print_filter_select($db_link, 'newfilter', $id); ?>
<input type="submit" name="saveorder" value="<?php echo WEB_btn_reorder; ?>">
</div>
</form>
<?php
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/footer.php");
?>
