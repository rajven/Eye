<?php
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/auth.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/languages/" . $language . ".php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/idfilter.php");

if (isset($_POST["regensnmp"])) {
    $snmp_index = $_POST["f_snmp_start"] * 1;
    $sSQL = "SELECT id,port from device_ports WHERE device_ports.device_id=$id order by id";
    $flist = mysqli_query($db_link, $sSQL);
    LOG_DEBUG($db_link, "Recalc snmp_index for device id: $id with start $snmp_index");
    while (list ($port_id, $port) = mysqli_fetch_array($flist)) {
        $snmp = $port + $snmp_index - 1;
        $new[snmp_index] = $snmp;
        update_record($db_link, "device_ports", "id='$port_id'", $new);
    }
    header("Location: " . $_SERVER["REQUEST_URI"]);
}

$switch=get_record($db_link,'devices',"id=".$id);

unset($_POST);

require_once ($_SERVER['DOCUMENT_ROOT']."/inc/header.php");
print_editdevice_submenu($page_url,$id);

?>
<div id="cont">
<form name="def" action="switchport.php?id=<? echo $id; ?>" method="post">

<?php
print "<br>\n";
print "<b>Список портов $switch[device_name] - $switch[ip]</b><br>\n";

print "<table class=\"data\" cellspacing=\"1\" cellpadding=\"4\">\n";
print "<tr>\n";
    print "<td>Порт</td>\n";
    print "<td>Snmp</td>\n";
    print "<td>Mac count</td>\n";
    print "<td>Uplink</td>\n";
    print "<td>Nagios</td>\n";
    print "<td>Skip</td>\n";
    print "<td>Юзер|Device</td>\n";
    print "<td>Комментарий</td>\n";
    print "<td>Vlan</td>\n";
    print "<td>График</td>\n";
print "</tr>\n";

$sSQL = "SELECT id,snmp_index,port,comment,target_port_id,last_mac_count,uplink,nagios,skip,vlan from device_ports WHERE device_ports.device_id=$id Order by port";
$flist = mysqli_query($db_link, $sSQL);
while (list ($d_id, $d_snmp, $d_port, $d_comment, $d_target_id, $d_mac_count, $d_uplink, $d_nagios, $d_skip, $d_vlan) = mysqli_fetch_array($flist)) {
print "<tr align=center>\n";
$cl="data";
if ($d_uplink) { $cl="info"; }
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
        print "<td class=\"$cl\">" . $d_vlan . "</td>\n";

        global $torrus_url;
        $cacti_url = get_cacti_graph($switch[ip], $d_snmp);
        if (! isset($torrus_url) and (! isset($cacti_url))) {
                print "<td class=\"$cl\"></td>\n";
        	} else {
                if (isset($cacti_url)) {
                    $snmp_url = "<a href=\"$cacti_url\">Статистика</a>";
        	    }
		if (isset($torrus_url)) {
                    $normed_ifname = trim(str_replace("/", "_", $ifname));
	            $normed_ifname = trim(str_replace(".", "_", $normed_ifname));
	            $normed_ifname = trim(str_replace(" ", "_", $normed_ifname));
        	    $pattern = '/cisco/i';
        	    preg_match($pattern, $switch[device_model], $matches);
        	    if (isset($matches[0])) {
                	$normed_ifname = trim(str_replace("Gi", "GigabitEthernet", $normed_ifname));
        		}
                    $t_url = str_replace("HOST_IP", $switch[ip], $torrus_url);
	            $t_url = str_replace("IF_NAME", $normed_ifname, $t_url);
	            $snmp_url = "<a href=\"$t_url\">Статистика</a>";
        	    }
		print "<td class=\"$cl\">" . $snmp_url . "</td>\n";
		}
        print "</td>\n";
print "</tr>";
}
print "<tr>\n";
    print "<td colspan=6>snmp start</td>\n";
    print "<td class=\"data\"><input type=\"text\" name='f_snmp_start' value=1></td>\n";
    print "<td><input type=\"submit\" name=\"regensnmp\" value=\"Обновить snmp\"></td>\n";
print "</tr>\n";
print "</table>\n";
?>
</form>

<?
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/footer.small.php");
?>
