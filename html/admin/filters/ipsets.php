<?php
require_once($_SERVER['DOCUMENT_ROOT'] . "/inc/auth.php");
require_once($_SERVER['DOCUMENT_ROOT'] . "/inc/languages/" . HTML_LANG . ".php");

if (getPOST("create") !== null) {
    $fname = trim(getPOST("newipset", null, ''));
    if ($fname !== '') {
        $new_id = insert_record($db_link, "ipset_list", ['name' => $fname]);
        if ($new_id) {
            header("Location: editipset.php?id=$new_id");
            exit;
        }
    }
    header("Location: " . $_SERVER["REQUEST_URI"]);
    exit;
}

if (getPOST("remove") !== null) {
    $fgid = getPOST("fid", null, []);
    if (!empty($fgid) && is_array($fgid)) {
        foreach ($fgid as $val) {
            $val = trim($val);
            if ($val === '') continue;
            delete_records($db_link, "ipset_members", "ipset_id = ?", [$val]);
            update_records($db_link, "filter_list", "ipset_id = ?", [ 'ipset_id'=>0, 'dst'=>'' ], [$val]);
            delete_records($db_link, "ipset_list", "id = ?", [$val]);
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
    <form name="def" action="ipsets.php" method="post">
        <table class="data">
            <tr align="center">
                <td><input type="checkbox" onClick="checkAll(this.checked);"></td>
                <td><b>Id</b></td>
                <td width=200><b><?php echo WEB_cell_name; ?></b></td>
                <td width=200><b><?php echo WEB_cell_description; ?></b></td>
                <td><input type="submit" onclick="return confirm('<?php echo WEB_msg_delete; ?>?')" name="remove" value="<?php echo WEB_btn_delete; ?>"></td>
            </tr>
            <?php
            $ipsets = get_records_sql($db_link, 'SELECT * FROM ipset_list ORDER BY id');
            foreach ($ipsets as $row) {
                print "<tr align=center>\n";
                print "<td class=\"data\" style='padding:0'><input type=checkbox name=fid[] value=" . $row["id"] . "></td>\n";
                print "<td class=\"data\" ><input type=\"hidden\" name=\"" . $row["id"] . "\" value=" . $row["id"] . ">" . $row["id"] . "</td>\n";
                print "<td class=\"data\"><a href=editipset.php?id=" . $row["id"] . ">" . $row["name"] . "</a></td>\n";
                print "<td class=\"data\">" . $row["description"] . "</td>\n";
                print "<td></td></tr>";
            }
            ?>
        </table>
        <div>
            <?php echo WEB_cell_name; ?><input type=text name=newipset value="Unknown">
            <input type="submit" name="create" value="<?php echo WEB_btn_add; ?>">
        </div>
    </form>
    <?php
    require_once($_SERVER['DOCUMENT_ROOT'] . "/inc/footer.php");
    ?>
