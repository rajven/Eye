<?php
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/auth.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/languages/" . $language . ".php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/header.php");
print_device_submenu($page_url);
?>
<div id="cont">
<br>
<table class="data">
	<tr align="center">
		<td><b>id</b></td>
		<td><b>Название</b></td>
		<td><b>IP</b></td>
		<td><b>Модель</b></td>
		<td><b>Расположен</b></td>
	</tr>
<?
$switches = get_records($db_link,'devices','deleted=1 ORDER BY ip');
foreach ($switches as $row) {
    print "<tr align=center>\n";
    $cl = "data";
    if ($fdeleted) { $cl = "shutdown"; } else {
        if (isset($fnagios)) {
    	    if ($fnagios = 'DOWN') { $cl = 'down'; }
            if ($fnagios = 'UP') { $cl = 'up'; }
    	    }
	}
    print "<td class=\"$cl\"><input type=hidden name=\"id\" value=".$row['id'].">".$row['id']."</td>\n";
    print "<td class=\"$cl\" align=left><a href=editswitches.php?id=".$row['id'].">" . $row['device_name'] . "</a></td>\n";
    print "<td class=\"$cl\">".$row['ip']."</td>\n";
    print "<td class=\"$cl\">" . get_vendor_name($db_link, $row['vendor_id']) . " " . $row['device_model'] . "</td>\n";
    print "<td class=\"$cl\">" . get_building($db_link, $row['building_id']) . "(" . $row['comment'] . ")</td>\n";
}
?>
</table>
<?php require_once($_SERVER['DOCUMENT_ROOT']."/inc/footer.php"); ?>
