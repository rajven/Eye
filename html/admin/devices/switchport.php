<?php
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/auth.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/languages/" . HTML_LANG . ".php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/idfilter.php");

// Перегенерация SNMP-индексов
if (getPOST("regensnmp") !== null) {
    $snmp_index = (int)getPOST("f_snmp_start", null, 1);
    $sSQL = "SELECT id, port FROM device_ports WHERE device_ports.device_id = ? ORDER BY id";
    $flist = get_records_sql($db_link, $sSQL, [$id]);
    LOG_DEBUG($db_link, "Recalc snmp_index for device id: $id with start $snmp_index");
    foreach ($flist as $row) {
        $snmp = $row['port'] + $snmp_index - 1;
        update_record($db_link, "device_ports", "id = ?", ['snmp_index' => $snmp], [$row['id']]);
    }
    header("Location: " . $_SERVER["REQUEST_URI"]);
    exit;
}

// Сохранение ОТМЕЧЕННЫХ портов
if (getPOST("save") !== null) {
    $selected_ids = getPOST("f_id", null, []);      // отмеченные чекбоксы
    $all_ids      = getPOST("p_id", null, []);      // все ID
    $port_names   = getPOST("f_name", null, []);
    $snmp_indices = getPOST("f_snmp_index", null, []);
    
    if (!empty($selected_ids) && is_array($selected_ids)) {
        $selected_ids = array_map('intval', $selected_ids);
        $selected_set = array_flip($selected_ids);
        
        foreach ($all_ids as $i => $id) {
            $id = (int)$id;
            if ($id <= 0 || !isset($selected_set[$id])) continue;
            
            $new = [
                'port_name'   => trim($port_names[$i] ?? ''),
                'snmp_index'  => (int)($snmp_indices[$i] ?? 0)
            ];
            
            update_record($db_link, "device_ports", "id = ?", $new, [$id]);
        }
    }
    
    header("Location: " . $_SERVER["REQUEST_URI"]);
    exit;
}

unset($_POST);

$device=get_record($db_link,'devices',"id=?", [$id]);
$user_info = get_record_sql($db_link,"SELECT * FROM user_list WHERE id=?", [ $device['user_id'] ]);

require_once ($_SERVER['DOCUMENT_ROOT']."/inc/header.php");

print_device_submenu($page_url);
print_editdevice_submenu($page_url,$id,$device['device_type'],$user_info['login']);

?>
<div id="contsubmenu">
<form name="def" action="switchport.php?id=<?php echo $id; ?>" method="post">
<br>

<?php print "<b>".WEB_device_port_list."&nbsp".$device['device_name']." - ".$device['ip']."</b><br>\n"; ?>

<table class="data" cellspacing="1" cellpadding="4">
<tr>
<td><input type="checkbox" onClick="checkAll(this.checked);"></td>
<td>id</td>
<td>N</td>
<td><?php echo WEB_device_port_name; ?></td>
<td><?php echo WEB_device_port_snmp_index; ?></td>
<td><?php echo WEB_device_connected_endpoint; ?></td>
<td><?php echo WEB_cell_description; ?></td>
<td><?php echo WEB_device_port_uplink; ?></td>
<td><?php echo WEB_nagios; ?></td>
<td><?php echo WEB_cell_skip; ?></td>
<td><?php echo WEB_cell_vlan; ?></td>
<td><?php echo WEB_device_snmp_port_oid_name; ?></td>
<td><?php echo WEB_cell_mac_count; ?></td>
</tr>
<?php
$sSQL = "SELECT * FROM device_ports WHERE device_ports.device_id=? ORDER BY port";
$ports=get_records_sql($db_link,$sSQL, [ $id ]);
foreach ($ports as $row) {
        print "<tr align=center>\n";
        $cl = "data";
        print "<td class='".$cl."' style='padding:0'><input type=checkbox name=f_id[] value='{$row['id']}'></td>\n";
        print "<td class='data'><input type='hidden' name='p_id[]' value='{$row['id']}'><a href='editport.php?id=".$row['id']."'>{$row['id']}</a></td>\n";
        print "<td class='".$cl."' >".$row['port']."</td>\n";
        print "<td class='".$cl."' ><input type='text' name='f_name[]' value='{$row['port_name']}' size=5></td>\n";
        print "<td class='".$cl."' ><input type='text' name='f_snmp_index[]' value='{$row['snmp_index']}' size=10></td>\n";
        print "<td class='".$cl."'>";
        if (isset($row['target_port_id']) and $row['target_port_id'] > 0) {
            print_device_port($db_link, $row['target_port_id']);
        } else {
            print_auth_port($db_link, $row['id'],FALSE);
        }
        print "</td>\n";
        print "<td class='".$cl."'>" . get_port_description($db_link, $row['id'],$row['description']) . "</td>\n";
        print_td_yes($row['uplink'],FALSE,$cl);
        print_td_yes($row['nagios'],FALSE,$cl);
        print_td_yes($row['skip'],FALSE,$cl);
        $display_vlan= $row['vlan'];
        if (!empty($row['untagged_vlan'])) { 
            if ($row['untagged_vlan'] != $row['vlan']) { 
                $pattern = '/(\d+),(\d+),(\d+),(\d+),(\d+),/';
                $replacement = '${1},${2},${3},${4},${5}<br>U:';
                $display_untagged = preg_replace($pattern, $replacement, $row['untagged_vlan']);
                $display_vlan.=";U:".$display_untagged; 
                }
            }
        if (!empty($row['tagged_vlan'])) { 
            $pattern = '/(\d+),(\d+),(\d+),(\d+),(\d+),/';
            $replacement = '${1},${2},${3},${4},${5}<br>T:';
            $display_tagged = preg_replace($pattern, $replacement, $row['tagged_vlan']);
            $display_vlan.=";T:".$display_tagged; 
            }
        $ifname= compact_port_name($row['ifname']);
        $f_cacti_url = get_cacti_graph($device['ip'], $row['snmp_index']);
        if (empty(get_const('torrus_url')) and (empty($f_cacti_url))) {  $snmp_url=$ifname; }
                else {
                if (isset($f_cacti_url)) { $snmp_url = "<a href='".$f_cacti_url."'>" . $ifname . "</a>"; }
                if (!empty(get_const('torrus_url'))) {
                    $normed_ifname = str_replace("/", "_", $ifname);
                    $normed_ifname = str_replace(".", "_", $normed_ifname);
                    $normed_ifname = trim(str_replace(" ", "_", $normed_ifname));
                    $t_url = str_replace("HOST_IP", $device['ip'], get_const('torrus_url'));
                    $t_url = str_replace("IF_NAME", $normed_ifname, $t_url);
                    $snmp_url = "<a href='".$t_url."'>" . $ifname . "</a>";
                    }
                }
        print "<td class='".$cl."'>" . $display_vlan . "</td>\n";
        print "<td class='".$cl."'>" . $snmp_url . "</td>\n";
        print "<td class='".$cl."' ><button onclick=\"". open_window_url('portmactable.php?id='.$row['id'])." return false;\">" . $row['last_mac_count'] . "</button></td>\n";
print "</tr>";
}
print "<tr>\n";
print "<td colspan=12 align=right><input type='submit' name='save' value='".WEB_btn_save."'></td>\n";
print "</tr>\n";
print "</table>\n";
?>
<div>
    <?php echo WEB_device_first_port_snmp_value; ?>
    &nbsp
    <input type='text' name='f_snmp_start' value=1>
    <input type='submit' name='regensnmp' value='<?php echo WEB_device_recalc_snmp_port ?>'>
</div>
</form>
<?php
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/footer.php");
?>
