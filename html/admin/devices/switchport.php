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
        $new['snmp_index'] = $snmp;
        update_record($db_link, "device_ports", "id='$port_id'", $new);
    }
    header("Location: " . $_SERVER["REQUEST_URI"]);
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
    }


$switch=get_record($db_link,'devices',"id=".$id);

unset($_POST);

require_once ($_SERVER['DOCUMENT_ROOT']."/inc/header.php");
print_editdevice_submenu($page_url,$id);

?>
<div id="cont">
<form name="def" action="switchport.php?id=<? echo $id; ?>" method="post">
<br>

<?php print "<b>Список портов ".$switch['device_name']." - ".$switch['ip']."</b><br>\n"; ?>

<table class="data" cellspacing="1" cellpadding="4">
<tr>
<td><input type="checkbox" onClick="checkAll(this.checked);"></td>
<td>id</td>
<td>N</td>
<td>Порт</td>
<td>snmp index</td>
<td>Юзер|Device</td>
<td>Комментарий</td>
<td>Uplink</td>
<td>Nagios</td>
<td>Skip</td>
<td>Vlan</td>
<td>ifName</td>
<td>Mac count</td>
</tr>
<?php
$sSQL = "SELECT * FROM device_ports WHERE device_ports.device_id=$id ORDER BY port";
$ports=get_records_sql($db_link,$sSQL);
foreach ($ports as $row) {
        print "<tr align=center>\n";
        $cl = "data";
        print "<td class=\"$cl\" style='padding:0'><input type=checkbox name=f_id[] value='{$row['id']}'></td>\n";
        print "<td class=\"data\"><input type=\"hidden\" name='p_id[]' value='{$row['id']}'><a href=\"editport.php?id=".$row['id']."\">{$row['id']}</a></td>\n";
        print "<td class=\"$cl\" >".$row['port']."</td>\n";
        print "<td class=\"$cl\" ><input type=\"text\" name='f_name[]' value='{$row['port_name']}' size=5></td>\n";
        print "<td class=\"$cl\" ><input type=\"text\" name='f_snmp_index[]' value='{$row['snmp_index']}' size=10></td>\n";
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
        $vlan = $row['vlan'];
        $ifname= $row['ifName'];
        global $torrus_url;
        $f_cacti_url = get_cacti_graph($switch['ip'], $row['snmp_index']);
        if (! isset($torrus_url) and (! isset($f_cacti_url))) {  $snmp_url=$ifname; }
                else {
                if (isset($f_cacti_url)) { $snmp_url = "<a href=\"$f_cacti_url\">" . $ifname . "</a>"; }
                if (isset($torrus_url)) {
                    $normed_ifname = trim(str_replace("/", "_", $ifname));
                    $normed_ifname = trim(str_replace(".", "_", $normed_ifname));
                    $normed_ifname = trim(str_replace(" ", "_", $normed_ifname));
                    $pattern = '/cisco/i';
                    preg_match($pattern, $switch['device_model'], $matches);
                    if (isset($matches[0])) { $normed_ifname = trim(str_replace("Gi", "GigabitEthernet", $normed_ifname)); }
                    $t_url = str_replace("HOST_IP", $switch['ip'], $torrus_url);
                    $t_url = str_replace("IF_NAME", $normed_ifname, $t_url);
                    $snmp_url = "<a href=\"$t_url\">" . $ifname . "</a>";
                    }
                }
        print "<td class=\"$cl\">" . $vlan . "</td>\n";
        print "<td class=\"$cl\">" . $snmp_url . "</td>\n";
        print "<td class=\"$cl\" ><button name=\"write\" class=\"j-submit-report\" onclick=\"window.open('portmactable.php?id=" . $row['id'] . "')\">" . $row['last_mac_count'] . "</button></td>\n";
print "</tr>";
}
print "<tr>\n";
print "<td colspan=6>snmp start</td>\n";
print "<td class=\"data\"><input type=\"text\" name='f_snmp_start' value=1></td>\n";
print "<td><input type=\"submit\" name=\"regensnmp\" value=\"Обновить snmp\"></td>\n";
print "<td colspan=5 align=right><input type=\"submit\" name=\"save\" value=\"Сохранить\"></td>\n";
print "</tr>\n";
print "</table>\n";
?>
</form>

<?
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/footer.small.php");
?>
