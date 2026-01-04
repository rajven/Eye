<?php
require_once($_SERVER['DOCUMENT_ROOT'] . "/inc/auth.php");
require_once($_SERVER['DOCUMENT_ROOT'] . "/inc/languages/" . HTML_LANG . ".php");
require_once($_SERVER['DOCUMENT_ROOT'] . "/inc/idfilter.php");

if (isset($_POST["editgroup"])) {
    $new['group_name'] = $_POST["f_group_name"];
    $new['instance_id'] = $_POST["f_instance_id"]*1;
    $new['comment'] = $_POST["f_group_comment"];
    update_record($db_link, "group_list", "id='$id'", $new);
    header("Location: " . $_SERVER["REQUEST_URI"]);
    exit;
}

if (isset($_POST["addfilter"])) {
    $filter_id = $_POST["newfilter"] * 1;
    $max_record = get_record_sql($db_link, "SELECT MAX(G.order) as morder FROM group_filters as G where G.group_id='$id'");
    if (empty($max_record)) {
        $forder = 1;
    } else {
        $forder = $max_record["morder"] * 1 + 1;
    }
    $new['group_id'] = $id;
    $new['filter_id'] = $filter_id;
    $new['order'] = $forder;
    $new['action'] = 1;
    insert_record($db_link, "group_filters", $new);
    header("Location: " . $_SERVER["REQUEST_URI"]);
    exit;
}

if (isset($_POST["removefilter"])) {
    $f_group_filter = $_POST["f_group_filter"];
    foreach ($f_group_filter as $key => $val) {
        if (!empty($val)) {
            delete_record($db_link, "group_filters", "id=" . $val * 1);
        }
    }
    header("Location: " . $_SERVER["REQUEST_URI"]);
    exit;
}

if (isset($_POST["updateFilters"])) {
    if (!empty($_POST["f_group_filter"])) {
        $f_group_filter = $_POST["f_group_filter"];
        LOG_DEBUG($db_link, "Update filters for group id: " . $id);
        for ($i = 0; $i < count($f_group_filter); ++$i) {
            $group_filter_id = $f_group_filter[$i];
            if (empty($_POST["f_ord"][$group_filter_id])) {
                $new['order'] = $i;
            } else {
                $new['order'] = $_POST["f_ord"][$group_filter_id] * 1;
            }
            if (empty($_POST["f_action"][$group_filter_id])) {
                $new['action'] = 0;
            } else {
                $new['action'] = $_POST["f_action"][$group_filter_id] * 1;
            }
            if (!empty($new)) {
                update_record($db_link, "group_filters", "id=" . $group_filter_id, $new);
            }
        }
    }
    header("Location: " . $_SERVER["REQUEST_URI"]);
    exit;
}

unset($_POST);

$group = get_record_sql($db_link, "SELECT * FROM group_list WHERE id=" . $id);

print_filters_submenu($page_url);

require_once($_SERVER['DOCUMENT_ROOT'] . "/inc/header.php");
?>
<div id="cont">
    <br> <b><?php echo WEB_title_group; ?></b> <br>

    <form name="def" action="editgroup.php?id=<?php echo $id; ?>" method="post">
        <input type="hidden" name="id" value=<?php echo $id; ?>>
        <table class="data">
            <tr>
                <td><?php echo WEB_cell_name; ?></td>
                <td class='data'><input type="text" name="f_group_name" value="<?php echo $group['group_name']; ?>"></td>
                <td class='data' align=right><input type="submit" name="editgroup" value="<?php echo WEB_btn_save; ?>"></td>
            </tr>
            <tr>
                <td><?php echo WEB_cell_comment; ?></td>
                <td class='data'><input type="text" name="f_group_comment" value="<?php echo $group['comment']; ?>"></td>
                <td class='data'></td>
            </tr>
            <tr>
                <td><?php echo WEB_submenu_filter_instance; ?></td>
                <td class='data'><?php print_instance_select($db_link,'f_instance_id',$group['instance_id']); ?></td>
                <td class='data'></td>
            </tr>
        </table>
        <br> <b><?php echo WEB_groups_filter_list; ?></b><br>
        <table class="data">
            <tr>
                <td><input type="checkbox" onClick="checkAll(this.checked);"></td>
                <td><?php echo WEB_group_filter_order; ?></td>
                <td><?php echo WEB_group_filter_name; ?></td>
                <td><?php echo WEB_traffic_action; ?></td>
                <td class='up'><input type="submit" name="updateFilters" value="<?php echo WEB_btn_save_filters; ?>"></td>
                <td class='warn'><input type="submit" onclick="return confirm('<?php echo WEB_msg_delete_filter; ?>?')" name="removefilter" value="<?php echo WEB_btn_delete; ?>"></td>
            </tr>

            <?php
            $sSQL = "SELECT G.id, G.filter_id, F.name, G.order, G.action, F.comment FROM group_filters G, filter_list F WHERE F.id=G.filter_id and group_id=$id Order by G.order";
            $flist = get_records_sql($db_link, $sSQL);
            foreach ($flist as $row) {
                print "<tr align=center>\n";
                print "<td class=\"data\" style='padding:0'><input type=checkbox name=f_group_filter[] value=" . $row['id'] . "></td>\n";
                print "<td class=\"data\" align=left><input type=text name=f_ord[" . $row['id'] . "] value=" . $row['order'] . " size=4 ></td>\n";
                print "<td class=\"data\" align=left><a href=editfilter.php?id=" . $row['filter_id'] . ">" . $row['name'] . "</a></td>\n";
                $cl = "data";
                if ($row['action']) {
                    $cl = "up";
                } else {
                    $cl = "warn";
                }
                print "<td class=" . $cl . ">";
                print_action_select('f_action[' . $row['id'] . ']', $row['action']);
                print "</td>";
                print "<td colspan=2 class=\"data\" align=left>" . $row['comment'] . "</a></td>\n";
                print "</tr>";
            }
            ?>
        </table>
        <div>
            <input type="submit" name="addfilter" value="<?php echo WEB_msg_add_filter; ?>"> <?php print_filter_select($db_link, 'newfilter', $id); ?>
        </div>
    </form>
    <?php
    require_once($_SERVER['DOCUMENT_ROOT'] . "/inc/footer.php");
    ?>