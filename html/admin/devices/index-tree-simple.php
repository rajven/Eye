<?php
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/auth.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/languages/" . $language . ".php");
unset($_POST);
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/header.php");
print_device_submenu($page_url);
?>
<div id="cont">
<br>
<?php

function print_child($device_id,$hash) 
{
foreach ($hash as $device) {
    if (!isset($device['parent_id'])) { continue; }
    if ($device['parent_id'] !== $device_id) { continue; }
    print '<ul><li>';
    print_url($device['name'],'/admin/devices/editdevice.php?id='.$device['id']);
    print_child($device['id'],$hash);
    print '</li></ul>';
    }
}

$dSQL = 'SELECT * FROM devices WHERE deleted=0 '.$filter.' '.$sort_sql;
$switches = get_records_sql($db_link,$dSQL);
$dev_hash = NULL;
foreach ($switches as $row) {
$dev_id=$row['id'];
$dev_hash[$dev_id]['id']=$dev_id;
$dev_hash[$dev_id]['name']=$row['device_name'];
$pSQL = 'SELECT * FROM device_ports WHERE uplink = 1 and device_id='.$dev_id;
$uplink = get_record_sql($db_link,$pSQL);
if (empty($uplink)) { continue; }
if (empty($uplink['target_port_id'])) { continue; }
$dev_hash[$dev_id]['uplink']=$uplink['port_name'];
$parentSQL='SELECT * FROM device_ports WHERE device_ports.id='.$uplink['target_port_id'];
$parent=get_record_sql($db_link,$parentSQL);
$dev_hash[$dev_id]['parent_id']=$parent['device_id'];
$dev_hash[$dev_id]['parent_port']=$parent['port_name'];
}

print '<div id="html">';
foreach ($dev_hash as $device) {
if (isset($device['parent_id'])) { continue; }
print '<ul><li>';
print_url($device['name'],'/admin/devices/editdevice.php?id='.$device['id']);
print_child($device['id'],$dev_hash);
print '</li>';
print '</ul>';
}
print '</div>';

require_once ($_SERVER['DOCUMENT_ROOT']."/inc/footer.php");
?>
