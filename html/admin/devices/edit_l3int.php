<?php
require_once ($_SERVER['DOCUMENT_ROOT'] . "/inc/auth.php");
require_once ($_SERVER['DOCUMENT_ROOT'] . "/inc/languages/" . HTML_LANG . ".php");
require_once ($_SERVER['DOCUMENT_ROOT'] . "/inc/idfilter.php");

$device = get_record($db_link, 'devices', "id = ?", [$id]);
$snmp = getSnmpAccess($device);
$user_info = get_record_sql($db_link, "SELECT * FROM user_list WHERE id = ?", [$device['user_id']]);
$int_list = getIpAdEntIfIndex($db_link, $device['ip'], $snmp);

// Удаление L3-интерфейсов
if (getPOST("s_remove") !== null) {
    $s_id = getPOST("s_id", null, []);
    
    if (!empty($s_id) && is_array($s_id)) {
        foreach ($s_id as $val) {
            $val = trim($val);
            if ($val === '') continue;
            
            LOG_INFO($db_link, "Remove l3_interface id: $val " . dump_record($db_link, 'device_l3_interfaces', 'id = ?', [$val]));
            delete_record($db_link, "device_l3_interfaces", "id = ?", [(int)$val]);
        }
    }
    
    header("Location: " . $_SERVER["REQUEST_URI"]);
    exit;
}

// Сохранение ОТМЕЧЕННЫХ L3-интерфейсов
if (getPOST("s_save") !== null) {
    $selected_ids = getPOST("s_id", null, []);      // отмеченные чекбоксы
    $all_ids      = getPOST("n_id", null, []);      // все ID
    $types        = getPOST("s_type", null, []);
    
    if (!empty($selected_ids) && is_array($selected_ids)) {
        $selected_ids = array_map('intval', $selected_ids);
        $selected_set = array_flip($selected_ids);
        
        foreach ($all_ids as $i => $id) {
            $id = (int)$id;
            if ($id <= 0 || !isset($selected_set[$id])) continue;
            
            $new = [
                'interface_type' => (int)($types[$i] ?? 0)
            ];
            
            update_record($db_link, "device_l3_interfaces", "id = ?", $new, [$id]);
        }
    }
    
    header("Location: " . $_SERVER["REQUEST_URI"]);
    exit;
}

// Создание нового L3-интерфейса
if (getPOST("s_create") !== null) {
    $create_name = trim(getPOST("s_create_name", null, ''));
    
    if ($create_name !== '') {
        $parts = explode(";", $create_name);
        if (count($parts) >= 3) {
            $new = [
                'name'           => preg_replace('/"/', '', trim($parts[0])),
                'snmpin'         => trim($parts[1]),
                'interface_type' => (int)trim($parts[2]),
                'device_id'      => $id
            ];
            
            LOG_INFO($db_link, "Create new l3_interface " . $new['name']);
            insert_record($db_link, "device_l3_interfaces", $new);
        }
    }
    
    header("Location: " . $_SERVER["REQUEST_URI"]);
    exit;
}

unset($_POST);

// Автоисправление интерфейсов
$t_l3_interface = get_records_sql($db_link, "SELECT * FROM device_l3_interfaces WHERE device_id = ? ORDER BY name", [$id]);

$int_by_name = [];
foreach ($int_list as $row) {
    $row['name'] = preg_replace('/"/', '', $row['name']);
    $int_by_name[$row['name']] = $row;
}
$fixed = 0;

// Исправление snmpin по имени
foreach ($t_l3_interface as $row) {
    if (empty($row['snmpin']) && !empty($int_by_name[$row['name']])) {
        update_record($db_link, 'device_l3_interfaces', 'id = ?', ['snmpin' => $int_by_name[$row['name']]['index']], [$row['id']]);
        $fixed = 1;
    }
}

// Обновление имени по snmpin
foreach ($t_l3_interface as $row) {
    if (!empty($int_list[$row['snmpin']]) && $int_list[$row['snmpin']]['name'] !== $row['name']) {
        update_record($db_link, 'device_l3_interfaces', 'id = ?', ['name' => $int_list[$row['snmpin']]['name']], [$row['id']]);
        $fixed = 1;
    }
}

if ($fixed) {
    $t_l3_interface = get_records_sql($db_link, "SELECT * FROM device_l3_interfaces WHERE device_id = ? ORDER BY name", [$id]);
}

require_once ($_SERVER['DOCUMENT_ROOT'] . "/inc/header.php");

print_device_submenu($page_url);
print_editdevice_submenu($page_url, $id, $device['device_type'], $user_info['login']);
?>
<div id="contsubmenu">
<br>
<form name="def" action="edit_l3int.php?id=<?php echo $id; ?>" method="post">
<?php echo WEB_list_l3_interfaces . "<b>"; print_url($device['device_name'], "/admin/devices/editdevice.php?id=$id"); ?></b> <br>
<table class="data">
<tr align="center">
    <td><input type="checkbox" onClick="checkAll(this.checked);"></td>
    <td width=30><b>id</b></td>
    <td><b><?php echo WEB_cell_name; ?></b></td>
    <td><b><?php echo WEB_cell_type; ?></b></td>
    <td>
        <!-- Кнопки управления справа -->
        <div style="text-align: right; white-space: nowrap;">
            <input type="submit" name="s_save" value="<?php echo WEB_btn_save; ?>">
            <input type="submit" 
                   onclick="return confirm('<?php echo WEB_msg_delete; ?>?')" 
                   name="s_remove" 
                   value="<?php echo WEB_btn_remove; ?>"
                   style="margin-left: 8px;">
        </div>
    </td>
</tr>
<?php
foreach ($t_l3_interface as $row) {
    print "<tr align=center>\n";
    print "<td class=\"data\" style='padding:0'><input type=\"checkbox\" name=\"s_id[]\" value=\"{$row['id']}\"></td>\n";
    print "<td class=\"data\"><input type=\"hidden\" name=\"n_id[]\" value=\"{$row['id']}\">{$row['snmpin']}</td>\n";
    print "<td class=\"data\">" . htmlspecialchars($row['name']) . '/' . htmlspecialchars($int_list[$row['snmpin']]['ip'] ?? '') . "</td>\n";
    print "<td class=\"data\">";
    print_qa_l3int_select('s_type[]', $row['interface_type']);
    print "</td>\n";
    print "<td class=\"data\"></td>\n";
    print "</tr>\n";
}
?>
<tr>
    <td colspan=4>
        <?php 
        echo WEB_l3_interface_add; 
        print_add_dev_interface($db_link, $id, $int_list, 's_create_name');
        ?>
    </td>
    <td>
        <input type="submit" name="s_create" value="<?php echo WEB_btn_add; ?>">
    </td>
</tr>
</table>
</form>
<?php
require_once ($_SERVER['DOCUMENT_ROOT'] . "/inc/footer.php");
?>
