<?php
$default_displayed=500;
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/auth.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/languages/" . $language . ".php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/header.php");
$default_sort='ip_int';
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/oufilter.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/subnetfilter.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/sortfilter.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/vendorfilter.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/devtypesfilter.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/buildingfilter.php");

if (isset($_POST["removeauth"])) {
    $dev_ids = $_POST["fid"];
    foreach ($dev_ids as $key => $val) {
        if ($val) {
                $device= get_record($db_link,"devices","id='$val'");
                if (!empty($device)) {
                    unbind_ports($db_link, $val);
	            run_sql($db_link, 'DELETE FROM connections WHERE device_id='.$val);
	            run_sql($db_link, 'DELETE FROM device_l3_interfaces WHERE device_id='.$val);
	            run_sql($db_link, 'DELETE FROM device_ports WHERE device_id='.$val);
        	    delete_record($db_link, "devices", "id=".$val);
        	    LOG_WARNING($db_link,"Удалено устройство ".$device['device_name']." id: ".$val);
        	    }
                }
            }
    header("Location: " . $_SERVER["REQUEST_URI"]);
    exit;
    }

$unknown=1;
if (!isset($_POST['f_unknown']) and isset($_POST['OK'])) { $unknown=0; }
if (isset($_POST['f_unknown'])) { $unknown=$_POST['f_unknown']*1; }

$unknown_checked='';
if ($unknown) { $unknown_checked='checked="checked"'; }

$sort_table = 'A';
if ($sort_field == 'login') { $sort_table = 'L'; }
if ($sort_field == 'fio') { $sort_table = 'L'; }
if ($sort_field == 'model_name') { $sort_table = 'M'; }

$sort_url = "<a href=index-passive.php?ou=" . $rou; 

if ($rou == 0) { $ou_filter = ''; } else { $ou_filter = " and L.ou_id=$rou "; }

if ($rsubnet == 0) { $subnet_filter = ''; } else {
    $subnet_range = get_subnet_range($db_link,$rsubnet);
    if (!empty($subnet_range)) { $subnet_filter = " and A.ip_int>=".$subnet_range['start']." and A.ip_int<=".$subnet_range['stop']; }
    }

$d_filter='';
if ($f_building_id > 0) { $d_filter .= ' and D.building_id=' . $f_building_id; }
if ($f_devtype_id > 0) { $d_filter .= ' and D.device_type=' . $f_devtype_id; } else { $d_filter .= ' and D.device_type>2'; }

$ip_list_filter = $ou_filter.$subnet_filter;

unset($_POST);
print_device_submenu($page_url);

?>
<div id="cont">
<form name="def" action="index-passive.php" method="post">
<table class="data">
<tr>
<td class="info"> Тип оборудования: </td>
<td class="info"> <?php  print_devtypes_select($db_link, "devtypes", $f_devtype_id, "id>2"); ?>
<td class="info">Показать оборудование из</td>
<td class="info"> <?php  print_building_select($db_link, "building_id", $f_building_id); ?></td>
<td class="info" colspan=2 align=right><input name="OK" type="submit" value="Показать"></td>
<td align=right><input type="submit" onclick="return confirm('Удалить выделенных?')" name="removeauth" value="Удалить"></td>
</tr>
<tr>
<td class="info"><?php print $list_ou; ?> </td>
<td class="info"><?php print_ou_select($db_link, 'ou', $rou); ?></td>
<td class="info">Отображать:<?php print_row_at_pages('rows',$displayed); ?></td>
<td class="info"><?php print $list_subnet; ?> </td>
<td class="info"><?php print_subnet_select_office($db_link, 'subnet', $rsubnet); ?></td>
<td class="info">Hide unknown:&nbsp <input type=checkbox name=f_unknown value="1" <?php print $unknown_checked; ?>> </td>
<td class="info">Vendor: <?php print_vendor_select($db_link,"vendor_select",$f_vendor_select); ?></td>
</td>
</tr>
</table>

<?php

$u_filter='';
if ($unknown and $f_vendor_select==0) { $u_filter=' AND V.id<>1 '; } else {
    if (!empty($f_vendor_select) and $f_vendor_select>=1) {
        $u_filter = " AND V.id=".$f_vendor_select." ";
        }
    }

$countSQL="SELECT Count(*) FROM User_auth A, User_list L, devices D, device_models M, vendors V.
WHERE D.user_id=L.id AND A.ip = D.ip AND D.device_model_id=M.id AND M.vendor_id=V.id AND A.deleted =0
$u_filter $ip_list_filter $d_filter";

$res = mysqli_query($db_link, $countSQL);
$count_records = mysqli_fetch_array($res);
$total=ceil($count_records[0]/$displayed);
if ($page>$total) { $page=$total; }
if ($page<1) { $page=1; }
$start = ($page * $displayed) - $displayed; 
print_navigation($page_url,$page,$displayed,$count_records[0],$total);
?>
<br>

<table class="data">
	<tr>
		<td align=Center><input type="checkbox" onClick="checkAll(this.checked);"></td>
		<td align=Center><?php print $sort_url . "&sort=login&order=$new_order>" . $cell_login . "</a>"; ?></td>
		<td align=Center><?php print $sort_url . "&sort=ip_int&order=$new_order>" . $cell_ip . "</a>"; ?></td>
		<td align=Center><?php print $sort_url . "&sort=mac&order=$new_order>" . $cell_mac . "</a>"; ?></td>
		<td align=Center><?php print $sort_url . "&sort=model_name&order=$new_order>".$cell_host_model; ?></td>
		<td align=Center><?php print $cell_comment; ?></td>
		<td align=Center><?php print $cell_connection; ?></td>
		<td align=Center><?php print $sort_url . "&sort=last_found&order=$new_order>Last</a>"; ?></td>
	</tr>
<?php

$sSQL = "SELECT A.id, D.id as dev_id, D.device_type, A.ip, A.mac, A.user_id, L.login, D.comment, A.last_found, V.name, M.model_name 
FROM User_auth A, User_list L, devices D, device_models M, vendors V 
WHERE D.user_id=L.id AND A.ip = D.ip AND D.device_model_id=M.id AND M.vendor_id=V.id AND A.deleted =0
$u_filter $ip_list_filter $d_filter
ORDER BY $sort_table.$sort_field $order LIMIT $start,$displayed";

$users = get_records_sql($db_link,$sSQL);
foreach ($users as $user) {
    if ($user['last_found'] == '0000-00-00 00:00:00') { $user['last_found'] = ''; }
    print "<tr align=center>\n";
    $cl = "data";
    print "<td class=\"$cl\" style='padding:0'><input type=checkbox name=fid[] value=".$user['dev_id']."></td>\n";
    print "<td class=\"$cl\" ><a href=/admin/devices/editdevice.php?id=".$user['dev_id'].">" . $user['login'] . "</a></td>\n";
    print "<td class=\"$cl\" ><a href=/admin/users/edituser.php?id=".$user['user_id'].">" . $user['ip'] . "</a></td>\n";
    print "<td class=\"$cl\" >" . expand_mac($db_link,$user['mac']) . "</td>\n";
    print "<td class=\"$cl\" >".$user['name'].' '.$user['model_name']."</td>\n";
    print "<td class=\"$cl\" >".$user['comment']."</td>\n";
    print "<td class=\"data\" >" . get_connection($db_link, $user['id']) . "</td>\n";
    print "<td class=\"$cl\" >".$user['last_found']."</td>\n";
    print "</tr>\n";
}
print "</table>\n";
print_navigation($page_url,$page,$displayed,$count_records[0],$total);
?>
<br>
<table class="data">
<tr><td>Цветовая маркировка</td></tr>
<tr>
<td class="warn">Пользователь выключен</td>
<td class="error">Блокировка по трафику</td>
</table>
<?php
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/footer.php");
?>
