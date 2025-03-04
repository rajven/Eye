<?php
require_once($_SERVER['DOCUMENT_ROOT'] . "/inc/auth.php");
require_once($_SERVER['DOCUMENT_ROOT'] . "/inc/languages/" . HTML_LANG . ".php");

if (isset($_POST["create"])) {
    $fname = $_POST["newgroup"];
    if ($fname) {
        $new['group_name'] = $fname;
        $new_id = insert_record($db_link, "Group_list", $new);
        header("location: editgroup.php?id=$new_id");
        exit;
    }
    header("Location: " . $_SERVER["REQUEST_URI"]);
    exit;
}

if (isset($_POST["remove"])) {
    $fgid = $_POST["fid"];
    foreach ($fgid as $key => $val) {
        if (!empty($val)) {
            run_sql($db_link, "UPDATE User_auth SET filter_group_id=0, changed = 1 WHERE deleted=0 AND filter_group_id=" . $val * 1);
            run_sql($db_link, "DELETE FROM Group_filters WHERE group_id=" . $val * 1);
            delete_record($db_link, "Group_list", "id=" . $val * 1);
        }
    }
    header("Location: " . $_SERVER["REQUEST_URI"]);
    exit;
}

unset($_POST);
require_once($_SERVER['DOCUMENT_ROOT'] . "/inc/header.php");

print_filters_submenu($page_url);
?>
<div id="cont">
    <form name="def" action="groups.php" method="post">
        <table class="data">
            <tr align="center">
                <td><input type="checkbox" onClick="checkAll(this.checked);"></td>
                <td><b>Id</b></td>
                <td width=200><b><?php echo WEB_cell_name; ?></b></td>
                <td ><b><?php echo WEB_submenu_filter_instance; ?></b></td>
                <td width=200><b><?php echo WEB_cell_comment; ?></b></td>
                <td><input type="submit" onclick="return confirm('<?php echo WEB_msg_delete; ?>?')" name="remove" value="<?php echo WEB_btn_delete; ?>"></td>
            </tr>
            <?php
            $groups = get_records_sql($db_link, 'SELECT * FROM Group_list ORDER BY id');
            foreach ($groups as $row) {
		$filter_instance = get_record_sql($db_link,'SELECT * FROM filter_instances WHERE id='.$row["instance_id"]);
                print "<tr align=center>\n";
                print "<td class=\"data\" style='padding:0'><input type=checkbox name=fid[] value=" . $row["id"] . "></td>\n";
                print "<td class=\"data\" ><input type=\"hidden\" name=\"" . $row["id"] . "\" value=" . $row["id"] . ">" . $row["id"] . "</td>\n";
                print "<td class=\"data\"><a href=editgroup.php?id=" . $row["id"] . ">" . $row["group_name"] . "</a></td>\n";
                print "<td class=\"data\">". $filter_instance["name"]."</td>\n";
                print "<td class=\"data\">" . $row["comment"] . "</td>\n";
                print "<td></td></tr>";
            }
            ?>
        </table>
        <div>
            <?php echo WEB_cell_name; ?><input type=text name=newgroup value="Unknown">
            <input type="submit" name="create" value="<?php echo WEB_btn_add; ?>">
        </div>
    </form>
    <?php
    require_once($_SERVER['DOCUMENT_ROOT'] . "/inc/footer.php");
    ?>