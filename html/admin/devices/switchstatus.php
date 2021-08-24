<?php
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/auth.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/languages/" . $language . ".php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/idfilter.php");

$switch=get_record($db_link,'devices',"id=".$id);

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
    print "<td>Порт</td>\n";
    print "<td>Snmp</td>\n";
    print "<td>Mac count</td>\n";
    print "<td>Uplink</td>\n";
    print "<td>Nagios</td>\n";
    print "<td>Skip</td>\n";
    print "<td>Юзер|Device</td>\n";
    print "<td>Комментарий</td>\n";
    print "<td>Vlan</td>\n";
    if ($snmp_ok) {
        print "<td>Speed</td>\n";
        print "<td>Errors</td>\n";
        print "<td>IfName</td>\n";
        print "<td>Additional</td>\n";
        print "<td>POE Control</td>\n";
        print "<td>Port Control</td>\n";
    }
    print "</tr>\n";
    $sSQL = "SELECT id,snmp_index,port,comment,target_port_id,last_mac_count,uplink,nagios,skip,vlan from device_ports WHERE device_ports.device_id=$id Order by port";
    $flist = mysqli_query($db_link, $sSQL);
    while (list ($d_id, $d_snmp, $d_port, $d_comment, $d_target_id, $d_mac_count, $d_uplink, $d_nagios, $d_skip, $d_vlan) = mysqli_fetch_array($flist)) {
        print "<tr align=center>\n";
        $cl = "up";
        if (isset($switch['ip']) and ($switch['ip'] != '') and $snmp_ok) {
            $port_state_detail = get_port_state_detail($d_snmp, $switch['ip'], $switch['community'], $switch['snmp_version']);
            list ($poper, $padmin, $pspeed, $perrors) = explode(';', $port_state_detail);
            if (preg_match('/up/i', $poper)) {
                $cl = "up";
            }
            if (preg_match('/down/i', $poper)) {
                if (preg_match('/down/i', $padmin)) {
                    $cl = "shutdown";
                } else {
                    $cl = "down";
                }
            }
        }
	print "<td class=\"$cl\" style='padding:0'><input type=checkbox name=d_port_index[] value=".$d_snmp." ></td>\n";
        print "<td class=\"$cl\"><a href=\"editport.php?id=$d_id\">" . $d_port . "</a></td>\n";
        print "<td class=\"$cl\" >" . $d_snmp . "</td>\n";
        print "<td class=\"$cl\" ><button name=\"write\" class=\"j-submit-report\" onclick=\"window.open('portmactable.php?id=" . $d_id . "')\">" . $d_mac_count . "</button></td>\n";
        print "<td class=\"$cl\" >" . get_qa($d_uplink) . "</td>\n";
        print "<td class=\"$cl\" >" . get_qa($d_nagios) . "</td>\n";
        print "<td class=\"$cl\" >" . get_qa($d_skip) . "</td>\n";
        print "<td class=\"$cl\">";
        if (isset($d_target_id) and $d_target_id > 0) {
            print_device_port($db_link, $d_target_id);
        } else {
            print_auth_port($db_link, $d_id);
        }
        print "</td>\n";
        print "<td class=\"$cl\">" . $d_comment . "</td>\n";
        if ($snmp_ok) {
            $vlan = get_port_vlan($d_snmp, $switch['ip'], $switch['community'], $switch['snmp_version']);
            $ifname = get_snmp_ifname($switch['ip'], $switch['community'], $switch['snmp_version'], $d_snmp);
            $sfp_status = get_sfp_status($switch['vendor_id'], $d_snmp, $switch['ip'], $switch['community'], $switch['snmp_version'], $modules_oids);
            $poe_status = get_port_poe_state($switch['vendor_id'], $d_snmp, $switch['ip'], $switch['community'], $switch['snmp_version']);
            if (!isset($vlan)) { $vlan = $d_vlan; }
            print "<td class=\"$cl\">" . $vlan . "</td>\n";
            $speed = "0";
            $cl_speed = $cl;
            if ($pspeed == 0) {
                $speed = "";
            }
            if ($pspeed == 10000000) {
                $speed = "10M";
                $cl_speed = "speed10M";
            }
            if ($pspeed == 100000000) {
                $speed = "100M";
                $cl_speed = "speed100M";
            }
            if ($pspeed == 1000000000) {
                $speed = "1G";
                $cl_speed = "speed1G";
            }
            if ($pspeed == 10000000000) {
                $speed = "10G";
                $cl_speed = "speed10G";
            }
            if ($pspeed == 4294967295) {
                $speed = "10G";
                $cl_speed = "speed10G";
            }
            if ($pspeed == 10) {
                $speed = "10G";
                $cl_speed = "speed10G";
            }
            print "<td class=\"$cl_speed\">" . $speed . "</td>\n";
            $cl_error = $cl;
            if ($perrors > 0) {
                $cl_error = "crc";
            }
            print "<td class=\"$cl_error\">" . $perrors . "</td>\n";
            global $torrus_url;
            $f_cacti_url = get_cacti_graph($switch['ip'], $d_snmp);
            if (! isset($torrus_url) and (! isset($f_cacti_url))) {
                print "<td class=\"$cl\">" . $ifname . "</td>\n";
            } else {
                if (isset($f_cacti_url)) {
                    $snmp_url = "<a href=\"$f_cacti_url\">" . $ifname . "</a>";
                }
                if (isset($torrus_url)) {
                    $normed_ifname = trim(str_replace("/", "_", $ifname));
                    $normed_ifname = trim(str_replace(".", "_", $normed_ifname));
                    $normed_ifname = trim(str_replace(" ", "_", $normed_ifname));
                    $pattern = '/cisco/i';
                    preg_match($pattern, $switch['device_model'], $matches);
                    if (isset($matches[0])) {
                        $normed_ifname = trim(str_replace("Gi", "GigabitEthernet", $normed_ifname));
                    }
                    $t_url = str_replace("HOST_IP", $switch['ip'], $torrus_url);
                    $t_url = str_replace("IF_NAME", $normed_ifname, $t_url);
                    $snmp_url = "<a href=\"$t_url\">" . $ifname . "</a>";
                }
                print "<td class=\"$cl\">" . $snmp_url . "</td>\n";
            }
            print "<td class=\"$cl\">" . $sfp_status;
            if (isset($poe_status)) {
                if ($poe_status == 1) {
                    $port_poe_detail = get_port_poe_detail($switch['vendor_id'], $d_snmp, $switch['ip'], $switch['community'], $switch['snmp_version']);
                    print "POE:On " . $port_poe_detail;
                }
                if ($poe_status == 2) {
                    print "POE:Off";
                }
            }
            print "</td>\n";
            if (isset($poe_status) and ! $d_skip and ! $switch['is_router']) {
                print "<td class=\"data\">";
                if ($switch['vendor_id'] != 9) {
                    if ($poe_status == 2) {
                        print "<button name='poe_on[]' value='{$d_snmp}'>POE On</button>";
                	}
                    if ($poe_status == 1) {
                        print "<button name='poe_off[]' value='{$d_snmp}'>POE Off</button>";
                	}
            	    } else {
                    print "Not supported";
            	    }
                print "</td>\n";
        	} else {
        	print "<td>Not supported</td>\n";
        	}
            if (isset($padmin) and ! $d_uplink and ! $d_skip and ! $switch['is_router']) {
                print "<td class=\"data\">";
                if ($switch['vendor_id'] != 9) {
                    if (preg_match('/down/i', $padmin)) {
                        print "<button name='port_on[]' value='{$d_snmp}'>Enable port</button>";
                	}
                    if (preg_match('/up/i', $padmin)) {
                        print "<button name='port_off[]' value='{$d_snmp}'>Shutdown port</button>";
                	}
            	    } else {
                    print "Not supported";
            	    }
                print "</td>\n";
        	}
    	    } else {
    	    print "<td class=\"$cl\">" . $d_vlan . "</td>\n";
    	    }
        print "</tr>";
    }
    print "<tr>\n";
    print "<td colspan=6>snmp start</td>\n";
    print "<td class=\"data\"><input type=\"text\" name='f_snmp_start' value=1></td>\n";
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
