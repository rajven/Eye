<?php
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/auth.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/languages/" . HTML_LANG . ".php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/idfilter.php");

if (isset($_POST["regensnmp"])) {
    $snmp_index = $_POST["f_snmp_start"] * 1;
    $sSQL = "SELECT id,port from device_ports WHERE device_ports.device_id=$id order by id";
    $flist = mysqli_query($db_link, $sSQL);
    LOG_DEBUG($db_link, "Recalc snmp_index for device id: $id with start $snmp_index");
    while (list ($port_id, $port) = mysqli_fetch_array($flist)) {
        $snmp = $port + $snmp_index - 1;
        $new['snmp_index'] = $snmp;
        update_record($db_link, "device_ports", "id='$port_id'", $new);
    }
    header("Location: " . $_SERVER["REQUEST_URI"]);
    exit;
}

if (isset($_POST['save'])) {
    $saved = array();
    //button save
    $len = is_array($_POST['save']) ? count($_POST['save']) : 0;
    for ($i = 0; $i < $len; $i ++) {
        $save_id = intval($_POST['save'][$i]);
        if ($save_id == 0) { continue;  }
        array_push($saved,$save_id);
        }
    //select box
    $len = is_array($_POST['f_id']) ? count($_POST['f_id']) : 0;
    if ($len>0) {
        for ($i = 0; $i < $len; $i ++) {
            $save_id = intval($_POST['f_id'][$i]);
            if ($save_id == 0) { continue; }
            if (!in_array($save_id, $saved)) { array_push($saved,$save_id); }
            }
        }
    //save changes
    $len = is_array($saved) ? count($saved) : 0;
    for ($i = 0; $i < $len; $i ++) {
        $save_id = intval($saved[$i]);
        if ($save_id == 0) { continue;  }
        $len_all = is_array($_POST['p_id']) ? count($_POST['p_id']) : 0;
        for ($j = 0; $j < $len_all; $j ++) {
            if (intval($_POST['p_id'][$j]) != $save_id) { continue; }
            $new['port_name'] = $_POST['f_name'][$j];
            $new['snmp_index'] = $_POST['f_snmp_index'][$j]*1;
            update_record($db_link, "device_ports", "id='{$save_id}'", $new);
            }
        }
    header("Location: " . $_SERVER["REQUEST_URI"]);
    exit;
    }


unset($_POST);

$device=get_record($db_link,'devices',"id=".$id);
$user_info = get_record_sql($db_link,"SELECT * FROM User_list WHERE id=".$device['user_id']);

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
<td><?php echo WEB_cell_comment; ?></td>
<td><?php echo WEB_device_port_uplink; ?></td>
<td><?php echo WEB_nagios; ?></td>
<td><?php echo WEB_cell_skip; ?></td>
<td><?php echo WEB_cell_vlan; ?></td>
<td><?php echo WEB_device_snmp_port_oid_name; ?></td>
<td><?php echo WEB_cell_mac_count; ?></td>
</tr>
<?php
$sSQL = "SELECT * FROM device_ports WHERE device_ports.device_id=$id ORDER BY port";
$ports=get_records_sql($db_link,$sSQL);
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
        print "<td class='".$cl."'>" . $row['comment'] . "</td>\n";
        print "<td class='".$cl."' >" . get_qa($row['uplink']) . "</td>\n";
        print "<td class='".$cl."' >" . get_qa($row['nagios']) . "</td>\n";
        print "<td class='".$cl."' >" . get_qa($row['skip']) . "</td>\n";
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
        $ifname= compact_port_name($row['ifName']);
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
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/footer.small.php");
?>
