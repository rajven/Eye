<?php
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/auth.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/languages/" . HTML_LANG . ".php");

// if(isset($_POST['f_ou_id'])){ $f_ou_id=$_POST['f_ou_id']*1; } else { $f_ou_id=1; }

$f_ou_id = IPCAM_GROUP_ID;

if (isset($_POST['port_on'])) {
    $len = is_array($_POST['port_on']) ? count($_POST['port_on']) : 0;
    for ($i = 0; $i < $len; $i ++) {
        $building_id = intval($_POST['port_on'][$i]);
        set_port_for_group($db_link, $f_ou_id, $building_id, 1);
    }
    header("Location: " . $_SERVER["REQUEST_URI"]);
    exit;
}

if (isset($_POST['port_off'])) {
    $len = is_array($_POST['port_off']) ? count($_POST['port_off']) : 0;
    for ($i = 0; $i < $len; $i ++) {
        $building_id = intval($_POST['port_off'][$i]);
        set_port_for_group($db_link, $f_ou_id, $building_id, 0);
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
$t_config = get_records_sql($db_link, "select id,name from building order by name");
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
