<?php

$default_displayed = 50;
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/auth.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/languages/" . HTML_LANG . ".php");

// the zabbix tags list is a part of the monitoring settings
if (!is_zabbix_enabled()) {
    print WEB_zabbix_disabled;
    exit;
}

// the list is global, only the name of the tag can be filtered
$f_tags_name = trim((string)getParam('tags_name', $page_url, ''));
$_SESSION[$page_url]['tags_name'] = $f_tags_name;

if (getPOST("save") !== null) {
    $f_ids = getPOST("f_id", null, []);
    $t_names = getPOST("t_name", null, []);
    $t_descriptions = getPOST("t_description", null, []);

    if (!empty($f_ids) && is_array($f_ids)) {
        foreach ($f_ids as $tags_list_id) {
            $tags_list_id = (int)$tags_list_id;
            if ($tags_list_id <= 0) {
                continue;
            }

            if (empty(get_record($db_link, "zabbix_tags_list", "id = ?", [$tags_list_id]))) {
                continue;
            }

            $name = normalizer_zabbix_tag_name($t_names[$tags_list_id] ?? '');
            // an empty name is not a tag, the record is left as it is
            if ($name === '' || $name === ZABBIX_TAG_EYE || $name === ZABBIX_TAG_PARENT) {
                LOG_WARNING($db_link, "Zabbix tag id $tags_list_id has an unusable name! Skipped.");
                continue;
            }

            // the tag name is unique in the global list
            $duplicate = get_record_sql($db_link, "SELECT id FROM zabbix_tags_list WHERE tag_name = ? AND id <> ?",
                [$name, $tags_list_id]);
            if (!empty($duplicate)) {
                LOG_WARNING($db_link, "Zabbix tag $name already exists with id " . $duplicate['id'] . "! Skipped.");
                continue;
            }

            update_record($db_link, "zabbix_tags_list", "id = ?", [
                'tag_name' => $name,
                'description' => substr(trim((string)($t_descriptions[$tags_list_id] ?? '')), 0, 250),
            ], [$tags_list_id]);
        }
    }

    header("Location: " . $_SERVER["REQUEST_URI"]);
    exit;
}

if (getPOST("remove") !== null) {
    $f_ids = getPOST("f_id", null, []);
    if (!empty($f_ids) && is_array($f_ids)) {
        foreach ($f_ids as $tags_list_id) {
            $tags_list_id = (int)$tags_list_id;
            if ($tags_list_id <= 0) {
                continue;
            }
            // the tags of the devices are removed together with the tag of the list
            delete_records($db_link, "zabbix_tags", "tags_list_id = ?", [$tags_list_id]);
            delete_record($db_link, "zabbix_tags_list", "id = ?", [$tags_list_id]);
        }
    }

    header("Location: " . $_SERVER["REQUEST_URI"]);
    exit;
}

if (getPOST("create") !== null) {
    $new_name = normalizer_zabbix_tag_name(trim((string)getPOST("new_name", null, '')));
    $new_description = substr(trim((string)getPOST("new_description", null, '')), 0, 250);

    if ($new_name === '' || $new_name === ZABBIX_TAG_EYE || $new_name === ZABBIX_TAG_PARENT) {
        LOG_WARNING($db_link, "Zabbix tag: the name can not be empty or the name of the automatic tag!");
    } else {
        $exists = get_record_sql($db_link, "SELECT id FROM zabbix_tags_list WHERE tag_name = ?", [$new_name]);
        if (!empty($exists)) {
            LOG_WARNING($db_link, "Zabbix tag $new_name is already in the list!");
        } else {
            insert_record($db_link, "zabbix_tags_list", [
                'tag_name' => $new_name,
                'description' => $new_description,
            ]);
        }
    }

    header("Location: " . $_SERVER["REQUEST_URI"]);
    exit;
}

unset($_POST);
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/header.php");
print_control_submenu($page_url);

?>
<div id="cont">
    <br>
    <form name="filter" action="zabbixtags.php" method="post">
        <table class="data">
            <tr>
                <td><b><?php echo WEB_zabbix_tags_page; ?></b></td>
                <td><?php echo WEB_zabbix_tag . "&nbsp:"; ?><input type="text" name="tags_name" value="<?php echo htmlspecialchars($f_tags_name); ?>"></td>
                <td><?php echo WEB_rows_at_page . "&nbsp:"; print_row_at_pages('rows', $displayed); ?></td>
                <td><input type="submit" name="OK" value="<?php echo WEB_btn_show; ?>"></td>
            </tr>
        </table>
    </form>

    <?php
    $params = [];
    $filter = '';
    if ($f_tags_name !== '') {
        $filter = "WHERE tag_name LIKE ?";
        $params[] = '%' . $f_tags_name . '%';
    }

    $count_records = (int)get_single_field($db_link, "SELECT COUNT(id) FROM zabbix_tags_list $filter", $params);

    $total = ceil($count_records / $displayed);
    if ($page > $total) { $page = $total; }
    if ($page < 1) { $page = 1; }
    $start = ($page * $displayed) - $displayed;
    print_navigation($page_url, $page, $displayed, $count_records, $total);

    $t_tags = get_records_sql($db_link,
        "SELECT * FROM zabbix_tags_list $filter ORDER BY tag_name LIMIT ? OFFSET ?",
        array_merge($params, [$displayed, $start]));

    // number of the eye network devices which apply every tag of the list
    $devices_count = [];
    foreach (get_records_sql($db_link, "SELECT tags_list_id, COUNT(device_id) AS cnt FROM zabbix_tags WHERE is_used = 1 GROUP BY tags_list_id", []) as $row) {
        $devices_count[(int)$row['tags_list_id']] = (int)$row['cnt'];
    }
    ?>

    <br>
    <form name="tags" action="zabbixtags.php" method="post">
    <table class="data">
        <tr align="center">
            <td><input type="checkbox" onClick="checkAll(this.checked);"></td>
            <td><b>Id</b></td>
            <td><b><?php echo WEB_zabbix_tag; ?></b></td>
            <td><b><?php echo WEB_cell_description; ?></b></td>
            <td><b><?php echo WEB_zabbix_used_by_devices; ?></b></td>
        </tr>
<?php
foreach ($t_tags as $row) {
    print "<tr align=center>\n";
    print "<td class=\"data\" style='padding:0'><input type=\"checkbox\" name=\"f_id[]\" value=\"" . $row['id'] . "\"></td>\n";
    print "<td class=\"data\">" . $row['id'] . "</td>\n";
    print "<td class=\"data\"><input type=\"text\" name=\"t_name[" . $row['id'] . "]\" value=\"" . htmlspecialchars($row['tag_name']) . "\"></td>\n";
    print "<td class=\"data\"><input type=\"text\" name=\"t_description[" . $row['id'] . "]\" value=\"" . htmlspecialchars($row['description']) . "\" size=35></td>\n";
    print "<td class=\"data\">" . ($devices_count[(int)$row['id']] ?? 0) . "</td>\n";
    print "</tr>\n";
}
?>
        <tr>
            <td colspan="5" class="data"><b><?php echo WEB_zabbix_add_tag; ?></b></td>
        </tr>
        <tr>
            <td class="data"></td>
            <td class="data"></td>
            <td class="data"><input type="text" name="new_name" value="" placeholder="<?php echo WEB_zabbix_tag_manual; ?>"></td>
            <td class="data"><input type="text" name="new_description" value="" size=35></td>
            <td class="data"></td>
        </tr>
        <tr>
            <td colspan="2" class="data" align="right">
                <input type="submit" name="save" value="<?php echo WEB_btn_save; ?>" <?php if (empty($t_tags)) echo 'disabled'; ?>>
            </td>
            <td colspan="1" class="data" align="center">
                <input type="submit" name="remove" value="<?php echo WEB_btn_delete; ?>"
                       onclick="return confirm('<?php echo WEB_msg_delete; ?>?')"
                       <?php if (empty($t_tags)) echo 'disabled'; ?>>
            </td>
            <td colspan="2" class="data" align="right">
                <input type="submit" name="create" value="<?php echo WEB_btn_add; ?>">
            </td>
        </tr>
    </table>
    </form>
</div>
<?php
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/footer.php");
?>
