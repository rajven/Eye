<?php
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/auth.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/languages/" . $language . ".php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/idfilter.php");

$switch=get_record($db_link,'devices',"id=".$id);

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
    }

if (isset($_POST['poe_on']) and $switch['snmp_version']>0) {
    $len = is_array($_POST['poe_on']) ? count($_POST['poe_on']) : 0;
    for ($i = 0; $i < $len; $i ++) {
        $port_index = intval($_POST['poe_on'][$i]);
        LOG_DEBUG($db_link, "Device id: $id enable poe at port snmp index $port_index");
        set_port_poe_state($switch['vendor_id'], $port_index, $switch['ip'], $switch['rw_community'], $switch['snmp_version'], 1);
    }
    header("Location: " . $_SERVER["REQUEST_URI"]);
}

if (isset($_POST['poe_off']) and $switch['snmp_version']>0) {
    $len = is_array($_POST['poe_off']) ? count($_POST['poe_off']) : 0;
    for ($i = 0; $i < $len; $i ++) {
        $port_index = intval($_POST['poe_off'][$i]);
        LOG_DEBUG($db_link, "Device id: $id disable poe at port snmp index $port_index");
        set_port_poe_state($switch['vendor_id'], $port_index, $switch['ip'], $switch['rw_community'], $switch['snmp_version'], 0);
    }
    header("Location: " . $_SERVER["REQUEST_URI"]);
}

if (isset($_POST['port_on']) and $switch['snmp_version']>0) {
    $len = is_array($_POST['port_on']) ? count($_POST['port_on']) : 0;
    for ($i = 0; $i < $len; $i ++) {
        $port_index = intval($_POST['port_on'][$i]);
        LOG_DEBUG($db_link, "Device id: $id enable port with snmp index $port_index");
        set_port_state($switch['vendor_id'], $port_index, $switch['ip'], $switch['rw_community'], $switch['snmp_version'], 1);
    }
    header("Location: " . $_SERVER["REQUEST_URI"]);
}

if (isset($_POST['port_off']) and $switch['snmp_version']>0) {
    $len = is_array($_POST['port_off']) ? count($_POST['port_off']) : 0;
    for ($i = 0; $i < $len; $i ++) {
        $port_index = intval($_POST['port_off'][$i]);
        LOG_DEBUG($db_link, "Device id: $id disable port with snmp index $port_index");
        set_port_state($switch['vendor_id'], $port_index, $switch['ip'], $switch['rw_community'], $switch['snmp_version'], 0);
    }
    header("Location: " . $_SERVER["REQUEST_URI"]);
}

unset($_POST);

require_once ($_SERVER['DOCUMENT_ROOT']."/inc/header.php");
print_editdevice_submenu($page_url,$id);

?>
<div id="cont">
<form name="def" action="switchstatus.php?id=<? echo $id; ?>" method="post">

<?php
print "<br>\n";
print "<b>Состояние портов ".$switch['device_name']." - ".$switch['ip']."</b><br>\n";

if ($switch['snmp_version']>0) {
        $snmp_ok = check_snmp_access($switch['ip'], $switch['community'], $switch['snmp_version']);
	if ($snmp_ok) {
	    global $cisco_modules;
            if ($switch['snmp_version'] == 2) {
	        $modules_oids = snmp2_real_walk($switch['ip'], $switch['community'], $cisco_modules);
	    }
            if ($switch['snmp_version'] == 1) {
	        $modules_oids = snmprealwalk($switch['ip'], $switch['community'], $cisco_modules);
	    }
	}
    } else { $snmp_ok = 0; }

    print "<table class=\"data\" cellspacing=\"1\" cellpadding=\"4\">\n";
    print "<tr>\n";
    print "<td>id</td>\n";
    print "<td>N</td>\n";
    print "<td>Порт</td>\n";
    print "<td>snmp</td>\n";
    print "<td>Юзер|Device</td>\n";
    print "<td>Комментарий</td>\n";
    print "<td>Uplink</td>\n";
    print "<td>Nagios</td>\n";
    print "<td>Skip</td>\n";
    print "<td>Vlan</td>\n";
    print "<td>IfName</td>\n";
    print "<td>Speed</td>\n";
    print "<td>Errors</td>\n";
    print "<td>Mac count</td>\n";
    print "<td>Additional</td>\n";
    print "<td>POE Control</td>\n";
    print "<td>Port Control</td>\n";
    print "</tr>\n";
    $sSQL = "SELECT * FROM device_ports WHERE device_ports.device_id=$id ORDER BY port";
    $ports=get_records_sql($db_link,$sSQL);
    foreach ($ports as $row) {
        print "<tr align=center>\n";
        $cl = "down";
        $new_info = NULL;
        //fix empty port names
        if (!isset($row['port_name'])) { $row['port_name']=$row['port']; $new_info['port_name']=$row['port']; }
        if (isset($switch['ip']) and ($switch['ip'] != '') and $snmp_ok) {
            $port_state_detail = get_port_state_detail($row['snmp_index'], $switch['ip'], $switch['community'], $switch['snmp_version'], $switch['fdb_snmp_index']);
            list ($poper, $padmin, $pspeed, $perrors) = explode(';', $port_state_detail);
            if (preg_match('/up/i', $poper)) { $cl = "up";  }
            if (preg_match('/down/i', $poper)) {
                if (preg_match('/down/i', $padmin)) { $cl = "shutdown"; } else { $cl = "down"; }
                }
            }
	print "<td class=\"$cl\" style='padding:0'><input type=checkbox name=d_port_index[] value=".$row['snmp_index']." ></td>\n";
        print "<td class=\"$cl\"><a href=\"editport.php?id=".$row['id']."\">" . $row['port'] . "</a></td>\n";
        print "<td class=\"$cl\" >" . $row['port_name'] . "</td>\n";
        print "<td class=\"$cl\" >" . $row['snmp_index'] . "</td>\n";
        print "<td class=\"$cl\">";
        if (isset($row['target_port_id']) and $row['target_port_id'] > 0) {
            print_device_port($db_link, $row['target_port_id']);
        } else {
            print_auth_port($db_link, $row['id']);
        }
        print "</td>\n";
        print "<td class=\"$cl\">" . $row['comment'] . "</td>\n";
        print "<td class=\"$cl\" >" . get_qa($row['uplink']) . "</td>\n";
        print "<td class=\"$cl\" >" . get_qa($row['nagios']) . "</td>\n";
        print "<td class=\"$cl\" >" . get_qa($row['skip']) . "</td>\n";
        $poe_info="POE:None";

        $vlan = $row['vlan'];
        $ifname= $row['ifName'];

        if ($snmp_ok) {
            $vlan = get_port_vlan($row['port'], $row['snmp_index'], $switch['ip'], $switch['community'], $switch['snmp_version'], $switch['fdb_snmp_index']);
            $ifname = get_snmp_ifname($switch['ip'], $switch['community'], $switch['snmp_version'], $row['snmp_index']);
            $sfp_status = get_sfp_status($switch['vendor_id'], $row['snmp_index'], $switch['ip'], $switch['community'], $switch['snmp_version'], $modules_oids);
            $poe_status = get_port_poe_state($switch['vendor_id'], $row['snmp_index'], $switch['ip'], $switch['community'], $switch['snmp_version']);
            if (isset($poe_status)) {
                if ($poe_status == 1) {
                    $port_poe_detail = get_port_poe_detail($switch['vendor_id'], $row['snmp_index'], $switch['ip'], $switch['community'], $switch['snmp_version']);
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
        global $torrus_url;
        $f_cacti_url = get_cacti_graph($switch['ip'], $row['snmp_index']);
        if (! isset($torrus_url) and (! isset($f_cacti_url))) {  $snmp_url=$ifname; } 
                else {
                if (isset($f_cacti_url)) { $snmp_url = "<a href=\"$f_cacti_url\">" . $ifname . "</a>"; }
                if (isset($torrus_url)) {
                    $normed_ifname = str_replace("/", "_", $ifname);
                    $normed_ifname = str_replace(".", "_", $normed_ifname);
                    $normed_ifname = trim(str_replace(" ", "_", $normed_ifname));
                    $t_url = str_replace("HOST_IP", $switch['ip'], $torrus_url);
                    $t_url = str_replace("IF_NAME", $normed_ifname, $t_url);
                    $snmp_url = "<a href=\"$t_url\">" . $ifname . "</a>";
                    }
                }

        print "<td class=\"$cl\">" . $vlan . "</td>\n";
        print "<td class=\"$cl\">" . $snmp_url . "</td>\n";

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
        print "<td class=\"$cl\" ><button name=\"write\" class=\"j-submit-report\" onclick=\"window.open('portmactable.php?id=" . $row['id'] . "')\">" . $row['last_mac_count'] . "</button></td>\n";
        print "<td class=\"$cl\">" . $sfp_status. " ". $poe_info."</td>\n";
        if (isset($poe_status) and ! $row['skip'] and ! $switch['is_router']) {
                print "<td class=\"data\">";
                if ($switch['vendor_id'] != 9) {
                    if ($poe_status == 2) {
                        print "<button name='poe_on[]' value='{$row['snmp_index']}'>POE On</button>";
                	}
                    if ($poe_status == 1) {
                        print "<button name='poe_off[]' value='{$row['snmp_index']}'>POE Off</button>";
                	}
            	    } else {
                    print "Not supported";
            	    }
                print "</td>\n";
        	} else {
        	print "<td>Not supported</td>\n";
        	}
        if (isset($padmin) and ! $row['uplink'] and ! $row['skip'] and ! $switch['is_router']) {
                print "<td class=\"data\">";
                if ($switch['vendor_id'] != 9) {
                    if (preg_match('/down/i', $padmin)) {
                        print "<button name='port_on[]' value='{$row['snmp_index']}'>Enable port</button>";
                	}
                    if (preg_match('/up/i', $padmin)) {
                        print "<button name='port_off[]' value='{$row['snmp_index']}'>Shutdown port</button>";
                	}
            	    } else {
                    print "Not supported";
            	    }
                print "</td>\n";
        	}
        print "</tr>";
    }
    print "<tr>\n";
    print "<td colspan=10>snmp start &nbsp<input type=\"text\" name='f_snmp_start' value=1></td>\n";
    print "<td><input type=\"submit\" name=\"regensnmp\" value=\"Обновить snmp\"></td>\n";
    print "</tr>\n";
    print "</table>\n";
    print "<table class=\"data\" cellspacing=\"1\" cellpadding=\"4\">\n";
    print "<tr><td>Port status</td></tr>\n";
    print "<tr><td class=\"down\">Oper down</td><td class=\"up\">Oper up</td><td class=\"shutdown\">Admin shutdown</td><tr>\n";
    print "</table>\n";
    print "<table class=\"data\" cellspacing=\"1\" cellpadding=\"4\">\n";
    print "<tr><td>Port speed</td></tr>\n";
    print "<tr><td class=\"speed10M\">10M</td><td class=\"speed100M\">100M</td><td class=\"speed1G\">1G</td><td class=\"speed10G\">10G</td><tr>\n";
    print "</table>\n";

print "</form>";

require_once ($_SERVER['DOCUMENT_ROOT']."/inc/footer.small.php");
?>
