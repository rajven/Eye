<?php
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/auth.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/languages/" . $language . ".php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/idfilter.php");

if (isset($_POST["editport"])) {
    $new['snmp_index'] = $_POST["f_snmp"] * 1;
    $new['uplink'] = $_POST["f_uplink"] * 1;
    $new['nagios'] = $_POST["f_nagios"] * 1;
    $new['skip'] = $_POST["f_skip"] * 1;
    $new['comment'] = $_POST["f_comment"];
    update_record($db_link, "device_ports", "id='$id'", $new);

    $target_id = $_POST["f_target_port"];
    bind_ports($db_link, $id, $target_id);

    // redirect to device
    $device_id = get_record_field($db_link,'device_ports','device_id',"id=".$id);
    header("location: editswitches.php?id=$device_id");
}

unset($_POST);

$device_id = get_record_field($db_link,'device_ports','device_id',"id=".$id);

$port = get_record($db_link, 'device_ports',"id=".$id);

require_once ($_SERVER['DOCUMENT_ROOT']."/inc/header.php");
?>
<div id="cont">

<form name="def" action="editport.php?id=<? echo $id; ?>" method="post">
<table class="data">
<tr>
<td>id</td>
<td>Порт</td>
<td>snmp</td>
<td>Комментарий</td>
<td>Device</td>
<td>Uplink</td>
<td>Nagios</td>
<td>Не проверять</td>
</tr>
<?php
print "<td class=\"data\"><input type=hidden name=\"id\" value=".$id.">".$id."</td>\n";
print "<td class=\"data\"><input type=\"text\" name='f_port' value='".$port['port']."'></td>\n";
print "<td class=\"data\"><input type=\"text\" name='f_snmp' value='".$port['snmp_index']."'></td>\n";
print "<td class=\"data\"><input type=\"text\" name='f_comment' value='".$port['comment']."'></td>\n";
print "<td class=\"data\">";
print_device_port_select($db_link, 'f_target_port', $device_id, $port['target_port_id']);
print "</td>\n";
print "<td class=\"data\">";
print_qa_select('f_uplink', $port['uplink']);
print "</td>\n";
print "<td class=\"data\">";
print_qa_select('f_nagios', $port['nagios']);
print "</td>\n";
print "<td class=\"data\">";
print_qa_select('f_skip', $port['skip']);
print "</td>\n";
?>
</tr>
<tr><td colspan=2><input type="submit" name="editport" value="Сохранить"></td></tr>
</table>
</form>
<?
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/footer.php");
?>
