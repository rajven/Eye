<?php
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/auth.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/languages/" . $language . ".php");

// if(isset($_POST['f_ou_id'])){ $f_ou_id=$_POST['f_ou_id']*1; } else { $f_ou_id=1; }

$f_ou_id = $ipcam_group_id;

if (isset($_POST['port_on'])) {
    $len = is_array($_POST['port_on']) ? count($_POST['port_on']) : 0;
    for ($i = 0; $i < $len; $i ++) {
        $building_id = intval($_POST['port_on'][$i]);
        set_port_for_group($db_link, $f_ou_id, $building_id, 1);
    }
    header("Location: " . $_SERVER["REQUEST_URI"]);
}

if (isset($_POST['port_off'])) {
    $len = is_array($_POST['port_off']) ? count($_POST['port_off']) : 0;
    for ($i = 0; $i < $len; $i ++) {
        $building_id = intval($_POST['port_off'][$i]);
        set_port_for_group($db_link, $f_ou_id, $building_id, 0);
    }
    header("Location: " . $_SERVER["REQUEST_URI"]);
}

unset($_POST);

require_once ($_SERVER['DOCUMENT_ROOT']."/inc/header.php");

?>
<div id="cont">
	<form name="def" action="ipcam.php" method="post">
<?
print "<table cellspacing=\"0\" cellpadding=\"0\" width=\"500\">";
print "<tr >\n";
print "<td align=center colspan=2>Для группы</td><td>";
print_ou_select($db_link, 'f_ou_id', $f_ou_id);
print "</td>\n";
print "</tr>\n";
print "<tr><td colspan=3><br></td></tr>\n";
$t_config = get_records_sql($db_link, "select id,name from building order by name");
foreach ($t_config as $row) {
    print "<tr align=center>\n";
    print "<td class=\"data\"><input type=\"text\" name='f_name[]' value='{$row['name']}' disabled=true readonly=true></td>\n";
    print "<td class=\"data\"><button name='port_on[]' value='{$row['id']}'>Включить порты</button></td>\n";
    print "<td class=\"data\"><button name='port_off[]' value='{$row['id']}'>Выключить порты</button></td>\n";
    print "</tr>\n";
}
print "<tr align=center>\n";
print "<td class=\"data\"><input type=\"text\" name='f_name[]' value='ALL' disabled=true readonly=true></td>\n";
print "<td class=\"data\"><button name='port_on[]' value='{0}'>Включить порты</button></td>\n";
print "<td class=\"data\"><button name='port_off[]' value='{0}'>Выключить порты</button></td>\n";
print "</tr>\n";
?>
</table>
</form>