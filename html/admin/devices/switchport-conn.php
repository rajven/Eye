<?php
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/auth.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/languages/" . $language . ".php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/idfilter.php");

$switch=get_record($db_link,'devices',"id=".$id);

if (isset($_POST["remove"])) {
    $fid = $_POST["f_id"];
    while (list ($key, $val) = @each($fid)) {
        if (isset($val) and $val != 1) {
                LOG_INFO($db_link, "Remove connection id: $val");
                delete_record($db_link, "connections", "id=" . $val);
            }
        }
        header("Location: " . $_SERVER["REQUEST_URI"]);
    }

unset($_POST);

require_once ($_SERVER['DOCUMENT_ROOT']."/inc/header.php");
print_editdevice_submenu($page_url,$id);

?>
<div id="cont">
<form name="def" action="switchport-conn.php?id=<? echo $id; ?>" method="post">
<br>

<?php print "<b>Список соединений на портах $switch[device_name] - $switch[ip]</b><br>\n"; ?>

<table class="data">
<tr>
<td width=20><input type="checkbox" onClick="checkAll(this.checked);"></td>
<td width=40><b>Порт</b></td>
<td ><b>Юзер</b></td>
<td width=100><input type="submit" name="remove" value="Удалить"></td>
</tr>

<?php

$connections = get_records($db_link,"connections","device_id=$id ORDER BY port_id ASC");
foreach ($connections as $key => $value) {
print "<tr align=center>\n";
print "<td class=\"data\" style='padding:0'><input type=checkbox name=f_id[] value='{$value['id']}'></td>\n";
$port = get_record($db_link,"device_ports","id=$value[port_id]");
print "<td class=\"data\">". $port[port] . "</a></td>\n";
print "<td class=\"data\">";
print_auth_detail($db_link, $value[auth_id]);
print "</td>\n";
print "<td class=\"data\"></td>\n";
print "</tr>";
}
print "</table>\n";
?>
</form>

<?
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/footer.small.php");
?>
