<?php
require_once($_SERVER['DOCUMENT_ROOT'] . "/inc/auth.php");
require_once($_SERVER['DOCUMENT_ROOT'] . "/inc/languages/" . HTML_LANG . ".php");

if (isset($_POST["create"])) {
    $fname = $_POST["newfilter"];
    $ftype = 0;
    if (isset($_POST['filter_type'])) {
        $ftype = $_POST["filter_type"] * 1;
    }
    if (isset($fname)) {
        $new['name'] = $fname;
        $new['filter_type'] = $ftype;
        $new_id = insert_record($db_link, "filter_list", $new);
        header("Location: editfilter.php?id=$new_id");
        exit;
    }
}

if (isset($_POST["remove"])) {
    $fid = $_POST["fid"];
    foreach ($fid as $key => $val) {
        if ($val) {
            run_sql($db_link, "DELETE FROM group_filters WHERE filter_id=" . $val);
            delete_record($db_link, "filter_list", "id=$val");
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
    <form name="def" action="index.php" method="post">
        <table class="data">
            <tr align="center">
                <td><input type="checkbox" onClick="checkAll(this.checked);"></td>
                <td><b>id</b></td>
                <td><b><?php echo WEB_cell_forename; ?></b></td>
                <td><b><?php echo WEB_cell_type; ?></b></td>
                <td><b><?php echo WEB_traffic_proto; ?></b></td>
                <td><b><?php echo WEB_traffic_dest_address; ?></b></td>
                <td><b><?php echo WEB_traffic_dst_port; ?></b></td>
                <td><b><?php echo WEB_traffic_src_port; ?></b></td>
                <td><b><?php echo WEB_cell_description; ?></b></td>
                <td><input type="submit" onclick="return confirm('<?php echo WEB_msg_delete; ?>?')" name="remove" value="<?php echo WEB_btn_delete; ?>"></td>
            </tr>
            <?php
            $filters = get_records_sql($db_link, 'SELECT * FROM filter_list ORDER BY name');
            foreach ($filters as $row) {
                print "<tr align=center>\n";
                print "<td class=\"data\" style='padding:0'><input type=checkbox name=fid[] value=" . $row['id'] . "></td>\n";
                print "<td class=\"data\" ><input type=hidden name=\"id\" value=" . $row['id'] . ">" . $row['id'] . "</td>\n";
                print "<td class=\"data\" align=left><a href=editfilter.php?id=" . $row['id'] . ">" . $row['name'] . "</a></td>\n";
                if (empty($row['description'])) {
                    $row['description'] = '';
                }
                if (empty($row['proto'])) {
                    $row['proto'] = '';
                }
                if (empty($row['dst'])) {
                    $row['dst'] = '';
                }
                if (empty($row['dstport'])) {
                    $row['dstport'] = '';
                }
                if (empty($row['srcport'])) {
                    $row['srcport'] = '';
                }
                if ($row['filter_type'] == 0) {
                    print "<td class=\"data\">IP фильтр</td>\n";
                    print "<td class=\"data\">" . $row['proto'] . "</td>\n";
                    print "<td class=\"data\">" . $row['dst'] . "</td>\n";
                    print "<td class=\"data\">" . $row['dstport'] . "</td>\n";
                    print "<td class=\"data\">" . $row['srcport'] . "</td>\n";
                    print "<td class=\"data\">" . $row['description'] . "</td>\n";
                } else {
                    print "<td class=\"data\">Name фильтр</td>\n";
                    print "<td class=\"data\"></td>\n";
                    print "<td class=\"data\">" . $row['dst'] . "</td>\n";
                    print "<td class=\"data\"></td>\n";
                    print "<td class=\"data\"></td>\n";
                    print "<td class=\"data\">" . $row['description'] . "</td>\n";
                }
                print "<td></td></tr>";
            }
            ?>
        </table>
        <div>
            <?php echo WEB_cell_name; ?>
            <input type=text name=newfilter value="Unknown">
            <?php echo Web_filter_type; ?>
            <select name="filter_type" disabled=true>
                <option value=0 selected>IP фильтр</option>
                <option value=1>Name фильтр</option>
            </select>
            <input type="submit" name="create" value="<?php echo WEB_btn_add; ?>">
    </form>
    <?php
    require_once($_SERVER['DOCUMENT_ROOT'] . "/inc/footer.php");
    ?>