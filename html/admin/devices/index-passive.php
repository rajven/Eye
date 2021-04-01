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
global $default_user_id;

if ($rou == 0) { $ou_filter = ''; } else { $ou_filter = " and L.ou_id=$rou "; }

if ($rsubnet == 0) { $subnet_filter = ''; } else {
    $subnet_range = get_subnet_range($db_link,$rsubnet);
    if (!empty($subnet_range)) { $subnet_filter = " and A.ip_int>=".$subnet_range['start']." and A.ip_int<=".$subnet_range['stop']; }
    }

$ip_list_filter = $ou_filter.$subnet_filter;

print_device_submenu($page_url);

?>
<div id="cont">
<form name="def" action="index-passive.php" method="post">
<table class="data">
	<tr>
        <td>
        <b><?php print $list_ou; ?> - </b><?php print_ou_select($db_link, 'ou', $rou); ?>
        Отображать:<?php print_row_at_pages('rows',$displayed); ?>
        <b><?php print $list_subnet; ?> - </b><?php print_subnet_select_office($db_link, 'subnet', $rsubnet); ?>
        Hide unknown:&nbsp <input type=checkbox name=f_unknown value="1" <?php print $unknown_checked; ?>>
        Vendor: <?php print_vendor_select($db_link,"vendor_select",$f_vendor_select); ?>
        <input name="OK" type="submit" value="Показать">
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

$countSQL="SELECT Count(*) FROM User_auth A, User_list L, device_models M, vendors V 
WHERE A.user_id = L.id AND A.device_model_id=M.id AND M.vendor_id=V.id
AND A.deleted=0 $u_filter $ip_list_filter";

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
		<td align=Center><?php print $sort_url . "&sort=login&order=$new_order>" . $cell_login . "</a>"; ?></td>
		<td align=Center><?php print $sort_url . "&sort=ip_int&order=$new_order>" . $cell_ip . "</a>"; ?></td>
		<td align=Center><?php print $sort_url . "&sort=mac&order=$new_order>" . $cell_mac . "</a>"; ?></td>
		<td align=Center><?php print $sort_url . "&sort=model_name&order=$new_order>".$cell_host_model; ?></td>
		<td align=Center><?php print $cell_comment; ?></td>
		<td align=Center><?php print $cell_dns_name; ?></td>
		<td align=Center><?php print $cell_connection; ?></td>
		<td align=Center><?php print $sort_url . "&sort=last_found&order=$new_order>Last</a>"; ?></td>
	</tr>
<?php

$sSQL = "SELECT A.id, A.ip, A.mac, A.user_id, L.login, A.comments, A.dns_name, A.dhcp_hostname, A.last_found, V.name, M.model_name
FROM User_auth A, User_list L, device_models M, vendors V
WHERE A.user_id = L.id AND A.device_model_id=M.id AND M.vendor_id=V.id
AND A.deleted =0 $u_filter $ip_list_filter
ORDER BY $sort_table.$sort_field $order LIMIT $start,$displayed";

$users = get_records_sql($db_link,$sSQL);
foreach ($users as $user) {
    if ($user['last_found'] == '0000-00-00 00:00:00') { $user['last_found'] = ''; }
    print "<tr align=center>\n";
    $cl = "data";
    print "<td class=\"$cl\" ><a href=/admin/users/edituser.php?id=".$user['user_id'].">" . $user['login'] . "</a></td>\n";
    print "<td class=\"$cl\" ><a href=/admin/users/editauth.php?id=".$user['id'].">" . $user['ip'] . "</a></td>\n";
    print "<td class=\"$cl\" >" . expand_mac($db_link,$user['mac']) . "</td>\n";
    print "<td class=\"$cl\" >".$user['name'].' '.$user['model_name']."</td>\n";
    if (isset($user['dhcp_hostname']) and strlen($user['dhcp_hostname']) > 0) {
        print "<td class=\"$cl\" >".$user['comments']." [" . $user['dhcp_hostname'] . "]</td>\n";
    } else {
        print "<td class=\"$cl\" >".$user['comments']."</td>\n";
    }
    print "<td class=\"$cl\" >".$user['dns_name']."</td>\n";
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
