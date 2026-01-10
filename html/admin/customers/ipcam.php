<?php
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/auth.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/languages/" . HTML_LANG . ".php");

// if(isset($_POST['f_ou_id'])){ $f_ou_id=$_POST['f_ou_id']*1; } else { $f_ou_id=1; }

$f_ou_id = IPCAM_GROUP_ID;

// Включение портов
if (getPOST("port_on") !== null) {
    $port_on = getPOST("port_on", null, []);
    
    if (is_array($port_on)) {
        foreach ($port_on as $building_id) {
            $building_id = (int)$building_id;
            if ($building_id > 0) {
                set_port_for_group($db_link, $f_ou_id, $building_id, 1);
            }
        }
    }
    
    header("Location: " . $_SERVER["REQUEST_URI"]);
    exit;
}

// Отключение портов
if (getPOST("port_off") !== null) {
    $port_off = getPOST("port_off", null, []);
    
    if (is_array($port_off)) {
        foreach ($port_off as $building_id) {
            $building_id = (int)$building_id;
            if ($building_id > 0) {
                set_port_for_group($db_link, $f_ou_id, $building_id, 0);
            }
        }
    }
    
    header("Location: " . $_SERVER["REQUEST_URI"]);
    exit;
}

unset($_POST);

require_once ($_SERVER['DOCUMENT_ROOT']."/inc/header.php");

?>
<div id="cont">
<form name="def" action="ipcam.php" method="post">
<?php
print "<table cellspacing=\"0\" cellpadding=\"0\" width=\"500\">";
print "<tr >\n";
print "<td align=center colspan=2>".WEB_control_group."</td><td>";
print_ou_select($db_link, 'f_ou_id', $f_ou_id);
print "</td>\n";
print "</tr>\n";
print "<tr><td colspan=3><br></td></tr>\n";
$t_config = get_records_sql($db_link, "SELECT * FROM building ORDER BY name");
foreach ($t_config as $row) {
    print "<tr align=center>\n";
    print "<td class=\"$cl\" style='padding:0'><input type=checkbox name=fid[] value=".$row['id']."></td>\n";
    print "<td class=\"data\"><input type=\"text\" name='f_name[".$row['id']."]' value='".$row['name']."' disabled=true readonly=true></td>\n";
    print "<td class=\"data\"><button name='port_on[".$row['id']."]' value='".$row['id']."'>".WEB_control_port_poe_on."</button></td>\n";
    print "<td class=\"data\"><button name='port_off[".$row['id']."]' value='".$row['id']."'>".WEB_control_port_poe_off."</button></td>\n";
    print "</tr>\n";
}
?>
</table>
</form>
