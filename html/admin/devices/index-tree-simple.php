<?php
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/auth.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/languages/" . HTML_LANG . ".php");
unset($_POST);
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/header.php");
print_device_submenu($page_url);
?>

<div id="cont">
<br>
<?php

function print_child($device_id, $hash, $processed_ids = array()) 
{
    // Защита от циклических ссылок - проверяем, не обрабатывали ли уже это устройство
    if (in_array((int)$device_id, array_map('intval', $processed_ids), true)) {
        return '';
    }
    // Добавляем текущее устройство в список обработанных
    $processed_ids[] = (int)$device_id;
    foreach ($hash as $device) {
        if (!isset($device['parent_id'])) { continue; }
        if ((int)$device['parent_id'] !== (int)$device_id) { continue; }
        print '<ul><li>';
        // порты аплинка, имя устройства и его модель: текст записи экранирован, оформление
        // самого eye остаётся разметкой, ссылка узла делает её print_url_html()
        print_url_html(esc($device['parent_port']) . '->' . esc($device['uplink']) . '&nbsp;'
            . esc($device['name']) . '&nbsp;::&nbsp;<small><i>[' . esc($device['model_name']) . ']</i></small>',
            '/admin/devices/editdevice.php?id=' . (int)$device['id']);
        print_child($device['id'], $hash, $processed_ids);
        print '</li></ul>';
    }
}

$dSQL = 'SELECT D.*,DM.model_name FROM devices as D,device_models as DM WHERE D.device_model_id=DM.id AND (deleted=0 and device_type<=2)';
$switches = get_records_sql($db_link,$dSQL);
$dev_hash = NULL;

// Сначала соберем все устройства
foreach ($switches as $row) {
    $dev_id = $row['id'];
    $dev_hash[$dev_id]['id'] = $dev_id;
    $dev_hash[$dev_id]['name'] = $row['device_name'];
    $dev_hash[$dev_id]['type'] = $row['device_type'];
    $dev_hash[$dev_id]['model_name'] = $row['model_name'];
    $dev_hash[$dev_id]['parent_id'] = null; // инициализируем
    $pSQL = 'SELECT * FROM device_ports WHERE uplink = 1 and device_id=?';
    $uplink = get_record_sql($db_link,$pSQL, [ $dev_id ]);
    if (empty($uplink)) { continue; }
    if (empty($uplink['target_port_id'])) { continue; }
    $dev_hash[$dev_id]['uplink'] = $uplink['port_name'];
    $parentSQL = 'SELECT * FROM device_ports WHERE device_ports.id=?';
    $parent = get_record_sql($db_link,$parentSQL, [$uplink['target_port_id']]);
    // Защита от ссылки на самого себя и на несуществующий порт
    if (empty($parent) || $parent['device_id'] == $dev_id) {
        // Устройство ссылается само на себя - пропускаем эту связь
        $dev_hash[$dev_id]['parent_id'] = null;
        continue;
    }
    $dev_hash[$dev_id]['parent_id'] = $parent['device_id'];
    $dev_hash[$dev_id]['parent_port'] = $parent['port_name'];
}

print '<div id="html">';

foreach ($dev_hash as $device) {
    // Пропускаем устройства, которые имеют родителя (они будут отображены как дети)
    if (isset($device['parent_id']) && $device['parent_id'] !== null) { 
        continue; 
    }
    print '<ul><li>';
    print_url((string)$device['name'], '/admin/devices/editdevice.php?id=' . (int)$device['id']);
    print print_child($device['id'], $dev_hash, array()); 
    print '</li>';
    print '</ul>';
}
print '</div>';

require_once ($_SERVER['DOCUMENT_ROOT']."/inc/footer.php");
?>
