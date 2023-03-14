<?php
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/auth.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/languages/" . HTML_LANG . ".php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/idfilter.php");

$device=get_record($db_link,'devices',"id=".$id);

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

if (isset($_POST['poe_on']) and $device['snmp_version']>0) {
    $len = is_array($_POST['poe_on']) ? count($_POST['poe_on']) : 0;
    for ($i = 0; $i < $len; $i ++) {
        $port_index = intval($_POST['poe_on'][$i]);
        LOG_DEBUG($db_link, "Device id: $id enable poe at port snmp index $port_index");
        set_port_poe_state($device['vendor_id'], $port_index, $device['ip'], $device['rw_community'], $device['snmp_version'], 1);
    }
    header("Location: " . $_SERVER["REQUEST_URI"]);
    exit;
}

if (isset($_POST['poe_off']) and $device['snmp_version']>0) {
    $len = is_array($_POST['poe_off']) ? count($_POST['poe_off']) : 0;
    for ($i = 0; $i < $len; $i ++) {
        $port_index = intval($_POST['poe_off'][$i]);
        LOG_DEBUG($db_link, "Device id: $id disable poe at port snmp index $port_index");
        set_port_poe_state($device['vendor_id'], $port_index, $device['ip'], $device['rw_community'], $device['snmp_version'], 0);
    }
    header("Location: " . $_SERVER["REQUEST_URI"]);
    exit;
}

if (isset($_POST['port_on']) and $device['snmp_version']>0) {
    $len = is_array($_POST['port_on']) ? count($_POST['port_on']) : 0;
    for ($i = 0; $i < $len; $i ++) {
        $port_index = intval($_POST['port_on'][$i]);
        LOG_DEBUG($db_link, "Device id: $id enable port with snmp index $port_index");
        set_port_state($device['vendor_id'], $port_index, $device['ip'], $device['rw_community'], $device['snmp_version'], 1);
    }
    header("Location: " . $_SERVER["REQUEST_URI"]);
    exit;
}

if (isset($_POST['port_off']) and $device['snmp_version']>0) {
    $len = is_array($_POST['port_off']) ? count($_POST['port_off']) : 0;
    for ($i = 0; $i < $len; $i ++) {
        $port_index = intval($_POST['port_off'][$i]);
        LOG_DEBUG($db_link, "Device id: $id disable port with snmp index $port_index");
        set_port_state($device['vendor_id'], $port_index, $device['ip'], $device['rw_community'], $device['snmp_version'], 0);
    }
    header("Location: " . $_SERVER["REQUEST_URI"]);
    exit;
}

unset($_POST);

$user_info = get_record_sql($db_link,"SELECT * FROM User_list WHERE id=".$device['user_id']);

require_once ($_SERVER['DOCUMENT_ROOT']."/inc/header.php");

print_device_submenu($page_url);
print_editdevice_submenu($page_url,$id,$device['device_type'],$user_info['login']);

?>

<div id="contsubmenu">

<form name="def" action="switchstatus.php?id=<?php echo $id; ?>" method="post">

<?php
print "<br>\n";
print "<b>".WEB_device_port_state_list."&nbsp".$device['device_name']." - ".$device['ip']."</b><br>\n";

if ($device['snmp_version']>0) {
    $snmp_ok = check_snmp_access($device['ip'], $device['community'], $device['snmp_version']);
    $modules_oids = NULL;
    if ($snmp_ok) {
            if ($device['snmp_version'] == 2) {
	        $modules_oids = snmp2_real_walk($device['ip'], $device['community'], CISCO_MODULES);
	        }
            if ($device['snmp_version'] == 1) {
	        $modules_oids = snmprealwalk($device['ip'], $device['community'], CISCO_MODULES);
	        }
	    }
    } else { $snmp_ok = 0; }

    print "<table class=\"data\" cellspacing=\"1\" cellpadding=\"4\">\n";
    print "<tr>\n";
    print "<td>id</td>\n";
    print "<td>".WEB_device_port_number."</td>\n";
    print "<td>".WEB_device_port_name."</td>\n";
    print "<td>".WEB_device_port_snmp_index."</td>\n";
    print "<td>".WEB_device_connected_endpoint."</td>\n";
    print "<td>".WEB_cell_comment."</td>\n";
    print "<td>".WEB_device_port_uplink."</td>\n";
    print "<td>".WEB_nagios."</td>\n";
    print "<td>".WEB_cell_skip."</td>\n";
    print "<td>".WEB_cell_vlan."</td>\n";
    print "<td>".WEB_device_snmp_port_oid_name."</td>\n";
    print "<td>".WEB_device_port_speed."</td>\n";
    print "<td>".WEB_device_port_errors."</td>\n";
    print "<td>".WEB_cell_mac_count."</td>\n";
    print "<td>".WEB_msg_additional."</td>\n";
    print "<td>".WEB_device_poe_control."</td>\n";
    print "<td>".WEB_device_port_control."</td>\n";
    print "</tr>\n";
    $sSQL = "SELECT * FROM device_ports WHERE device_ports.device_id=$id ORDER BY port";
    $ports=get_records_sql($db_link,$sSQL);
    foreach ($ports as $row) {
        print "<tr align=center>\n";
        $cl = "down";
        $new_info = NULL;
        //fix empty port names
        if (empty($row['port_name'])) { $row['port_name']=$row['port']; $new_info['port_name']=$row['port']; }
        if (isset($device['ip']) and ($device['ip'] != '') and $snmp_ok) {
            $port_state_detail = get_port_state_detail($row['snmp_index'], $device['ip'], $device['community'], $device['snmp_version'], $device['fdb_snmp_index']);
            list ($poper, $padmin, $pspeed, $perrors) = explode(';', $port_state_detail);
            if ($poper == 1 ) { $cl = "up";  }
            if ($poper >= 2 ) {
                if ($padmin >=2) { $cl = "shutdown"; } else { $cl = "down"; }
                }
            }
	print "<td class='".$cl."' style='padding:0'><input type=checkbox name=d_port_index[] value=".$row['snmp_index']." ></td>\n";
        print "<td class='".$cl."'><a href=\"editport.php?id=".$row['id']."\">" . $row['port'] . "</a></td>\n";
        print "<td class='".$cl."' >" . $row['port_name'] . "</td>\n";
        print "<td class='".$cl."' >" . $row['snmp_index'] . "</td>\n";
        print "<td class='".$cl."'>";
        if (isset($row['target_port_id']) and $row['target_port_id'] > 0) {
            print_device_port($db_link, $row['target_port_id']);
        } else {
            print_auth_port($db_link, $row['id']);
        }
        print "</td>\n";
        print "<td class='".$cl."'>" . $row['comment'] . "</td>\n";
        print "<td class='".$cl."' >" . get_qa($row['uplink']) . "</td>\n";
        print "<td class='".$cl."' >" . get_qa($row['nagios']) . "</td>\n";
        print "<td class='".$cl."' >" . get_qa($row['skip']) . "</td>\n";
        $poe_info="POE:None";

        $vlan = $row['vlan'];
        $ifname= $row['ifName'];

        if ($snmp_ok) {
            $vlan = get_port_vlan($row['port'], $row['snmp_index'], $device['ip'], $device['community'], $device['snmp_version'], $device['fdb_snmp_index']);
            $ifname = get_snmp_ifname1($device['ip'], $device['community'], $device['snmp_version'], $row['snmp_index']);
            if (empty($ifname)) { $ifname = get_snmp_ifname2($device['ip'], $device['community'], $device['snmp_version'], $row['snmp_index']); }
            $sfp_status = get_sfp_status($device['vendor_id'], $row['snmp_index'], $device['ip'], $device['community'], $device['snmp_version'], $modules_oids);
            $poe_status = get_port_poe_state($device['vendor_id'], $row['snmp_index'], $device['ip'], $device['community'], $device['snmp_version']);
            if (isset($poe_status)) {
                if ($poe_status == 1) {
                    $port_poe_detail = get_port_poe_detail($device['vendor_id'], $row['snmp_index'], $device['ip'], $device['community'], $device['snmp_version']);
                    $poe_info="POE:On " . $port_poe_detail;
                    }
                if ($poe_status == 2) { $poe_info="POE:Off"; }
                }
            if (!isset($vlan)) { $vlan = $row['vlan']; } else {
                if ($row['vlan']!==$vlan) { $new_info['vlan']=$vlan; }
                }
            if (!isset($row['ifName']) or $row['ifName'] !== $ifname) { $new_info['ifName']=$ifname; }
            }

        //fix port information
        if (!empty($new_info)) { update_record($db_link, "device_ports", "id=".$row['id'], $new_info); }

        $ifname=compact_port_name($ifname);
        $f_cacti_url = get_cacti_graph($device['ip'], $row['snmp_index']);
        if (empty(get_const('torrus_url')) and (empty($f_cacti_url))) {  $snmp_url=$ifname; } 
                else {
                if (isset($f_cacti_url)) { $snmp_url = "<a href=\"$f_cacti_url\">" . $ifname . "</a>"; }
                if (!empty(get_const('torrus_url'))) {
                    $normed_ifname = str_replace("/", "_", $ifname);
                    $normed_ifname = str_replace(".", "_", $normed_ifname);
                    $normed_ifname = trim(str_replace(" ", "_", $normed_ifname));
                    $t_url = str_replace("HOST_IP", $device['ip'], get_const('torrus_url'));
                    $t_url = str_replace("IF_NAME", $normed_ifname, $t_url);
                    $snmp_url = "<a href=\"$t_url\">" . $ifname . "</a>";
                    }
                }

        print "<td class='".$cl."'>" . $vlan . "</td>\n";
        print "<td class='".$cl."'>" . $snmp_url . "</td>\n";

        $speed = "0";
        $cl_speed = $cl;
        if ($pspeed == 0) { $speed = ""; }
        if ($pspeed == 10000000) { $speed = "10M"; $cl_speed = "speed10M"; }
        if ($pspeed == 100000000) { $speed = "100M"; $cl_speed = "speed100M"; }
        if ($pspeed == 1000000000) { $speed = "1G"; $cl_speed = "speed1G"; }
        if ($pspeed == 10000000000) { $speed = "10G"; $cl_speed = "speed10G"; }
        if ($pspeed == 4294967295) { $speed = "10G"; $cl_speed = "speed10G"; }
        if ($pspeed == 10) { $speed = "10G"; $cl_speed = "speed10G"; }
        print "<td class=\"$cl_speed\">" . $speed . "</td>\n";
        $cl_error = $cl;
        if ($perrors > 0) { $cl_error = "crc"; }
        print "<td class=\"$cl_error\">" . $perrors . "</td>\n";
        print "<td class='".$cl."' ><button name=\"write\" class=\"j-submit-report\" onclick=\"window.open('portmactable.php?id=" . $row['id'] . "')\">" . $row['last_mac_count'] . "</button></td>\n";
        print "<td class='".$cl."'>" . $sfp_status. " ". $poe_info."</td>\n";
        if (isset($poe_status) and ! $row['skip'] and ! $device['is_router']) {
                print "<td class=\"data\">";
                if ($device['vendor_id'] != 9) {
                    if ($poe_status == 2) {
                        print "<button name='poe_on[]' value='{$row['snmp_index']}'>".WEB_device_poe_on."</button>";
                	}
                    if ($poe_status == 1) {
                        print "<button name='poe_off[]' value='{$row['snmp_index']}'>".WEB_device_poe_off."</button>";
                	}
            	    } else {
                    print WEB_msg_unsupported;
            	    }
                print "</td>\n";
        	} else {
        	print "<td>".WEB_msg_unsupported."</td>\n";
        	}
        if (isset($padmin) and ! $row['uplink'] and ! $row['skip'] and ! $device['is_router']) {
                print "<td class=\"data\">";
                if ($device['vendor_id'] != 9) {
                    if ($padmin >=2) {
                        print "<button name='port_on[]' value='{$row['snmp_index']}'>".WEB_device_port_on."</button>";
                	}
                    if ($padmin ==1) {
                        print "<button name='port_off[]' value='{$row['snmp_index']}'>".WEB_device_port_off."</button>";
                	}
            	    } else {
                    print WEB_msg_unsupported;
            	    }
                print "</td>\n";
        	}
        print "</tr>";
    }
    print "<tr>\n";
    print "</table>\n";
?>

<div>
    <?php echo WEB_device_first_port_snmp_value; ?>
    &nbsp
    <input type='text' name='f_snmp_start' value=1>
    <input type='submit' name='regensnmp' value='<?php echo WEB_device_recalc_snmp_port ?>'>
</div>

<?php
    print "<table class=\"data\" cellspacing=\"1\" cellpadding=\"4\">\n";
    print "<tr><td>".WEB_port_status."</td></tr>\n";
    print "<tr><td class=\"down\">".WEB_port_oper_down."</td>";
    print "<td class=\"up\">".WEB_port_oper_up."</td>";
    print "<td class=\"shutdown\">".WEB_port_admin_shutdown."</td><tr>\n";
    print "</table>\n";
    print "<table class=\"data\" cellspacing=\"1\" cellpadding=\"4\">\n";
    print "<tr><td>".WEB_port_speed."</td></tr>\n";
    print "<tr><td class=\"speed10M\">".WEB_port_speed_10."</td><td class=\"speed100M\">".WEB_port_speed_100."</td><td class=\"speed1G\">".WEB_port_speed_1G."</td><td class=\"speed10G\">".WEB_port_speed_10G."</td><tr>\n";
    print "</table>\n";
print "</form>";
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/footer.small.php");
?>
