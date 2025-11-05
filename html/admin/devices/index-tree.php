<?php
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/auth.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/languages/" . HTML_LANG . ".php");
unset($_POST);
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/header.php");
print_device_submenu($page_url);
?>

<script>
$.getScript("/js/jstree/jstree.min.js")
.fail(function(jqxhr, settings, exception) {
        window.location.href = '/admin/devices/index-tree-simple.php';
        });
</script>

<div id="cont">
<br>
<?php

function print_child($device_id, $hash, $processed_ids = array()) 
{
    // Защита от циклических ссылок - проверяем, не обрабатывали ли уже это устройство
    if (in_array($device_id, $processed_ids)) {
        return '';
    }
    
    // Добавляем текущее устройство в список обработанных
    $processed_ids[] = $device_id;
    
    $output = '';
    foreach ($hash as $device) {
        if (!isset($device['parent_id'])) { continue; }
        if ($device['parent_id'] !== $device_id) { continue; }
        
        $dev_icon = '/img/server.png';
        if ($device['type'] == 0) { $dev_icon = '/img/router.png'; }
        if ($device['type'] == 1) { $dev_icon = '/img/switch16.png'; }
        if ($device['type'] == 2) { $dev_icon = '/img/gateway.png'; }
        if ($device['type'] == 3) { $dev_icon = '/img/server.png'; }
        if ($device['type'] == 4) { $dev_icon = '/img/ap.png'; }
        
        $output .= '{ "text" : "' . $device['parent_port'].'->'.$device['uplink'].'&nbsp'.$device['name'].'&nbsp::&nbsp<small><i>['.$device['model_name'].']</i></small>' . '", "icon" : "'.$dev_icon.'", "id" : "'.$device['id'].'","state" : { "opened" : true },';
        $output .= '"a_attr" : { "href": "'.reencodeurl('/admin/devices/editdevice.php?id='.$device['id']).'"},';
        $output .= '"children" : [' . print_child($device['id'], $hash, $processed_ids) . ']';
        $output .= "},\n";
    }
    return $output;
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
    
    $pSQL = 'SELECT * FROM device_ports WHERE uplink = 1 and device_id='.$dev_id;
    $uplink = get_record_sql($db_link,$pSQL);
    if (empty($uplink)) { continue; }
    if (empty($uplink['target_port_id'])) { continue; }
    
    $dev_hash[$dev_id]['uplink'] = $uplink['port_name'];
    $parentSQL = 'SELECT * FROM device_ports WHERE device_ports.id='.$uplink['target_port_id'];
    $parent = get_record_sql($db_link,$parentSQL);
    
    // Защита от ссылки на самого себя
    if ($parent['device_id'] == $dev_id) {
        // Устройство ссылается само на себя - пропускаем эту связь
        $dev_hash[$dev_id]['parent_id'] = null;
        continue;
    }
    
    $dev_hash[$dev_id]['parent_id'] = $parent['device_id'];
    $dev_hash[$dev_id]['parent_port'] = $parent['port_name'];
}

print '<div id="frmt" class="tree"></div>';
print "\n";
print '<script>';
print "$('#frmt').jstree({";
print '"themes" : { "theme" : "default", "dots" : false, "icons" : false }, "plugins" : [ "themes", "html_data", "ui", "sort" ],';
print "'core' : { 'data' : [";
print "\n";

foreach ($dev_hash as $device) {
    // Пропускаем устройства, которые имеют родителя (они будут отображены как дети)
    if (isset($device['parent_id']) && $device['parent_id'] !== null) { 
        continue; 
    }
    
    $dev_icon = '/img/server.png';
    if ($device['type'] == 0) { $dev_icon = '/img/router.png'; }
    if ($device['type'] == 1) { $dev_icon = '/img/switch16.png'; }
    if ($device['type'] == 2) { $dev_icon = '/img/gateway.png'; }
    if ($device['type'] == 3) { $dev_icon = '/img/server.png'; }
    if ($device['type'] == 4) { $dev_icon = '/img/ap.png'; }
    
    print '{ "text" : "'; 
    print_url($device['name'],'/admin/devices/editdevice.php?id='.$device['id']); 
    print '","icon" : "'.$dev_icon.'", "id" : "'.$device['id'].'" ,"state" : { "opened" : true },';
    print '"a_attr" : { "href": "'.reencodeurl('/admin/devices/editdevice.php?id='.$device['id']).'"},';
    print '"children" : ['; 
    print print_child($device['id'], $dev_hash, array()); 
    print "]";
    print "},\n";
}
?>
]}
}).on('changed.jstree', function (e, data) {
    if (data == null) return '';
    if (data.node == null) return '';
    var href = data.node.a_attr.href;
    var parentId = data.node.a_attr.parent_id;
    if(href == '#') return '';
    var win = window.open(href, "_blank");
});
</script>

<?php
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/footer.php");
?>

