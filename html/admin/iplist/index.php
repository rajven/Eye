<?php
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/auth.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/languages/" . $language . ".php");
$default_displayed = 500;
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/header.php");
$default_sort='ip_int';
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/oufilter.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/subnetfilter.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/sortfilter.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/gatefilter.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/enabledfilter.php");

$sort_table = 'User_auth';
if ($sort_field == 'login') { $sort_table = 'User_list'; }
if ($sort_field == 'fio') { $sort_table = 'User_list'; }

$sort_url = "<a href=index.php?ou=" . $rou; 
global $default_user_id;

if ($rou == 0) { $ou_filter = ''; } else { $ou_filter = " and User_list.ou_id=$rou "; }

if ($rsubnet == 0) { $subnet_filter = ''; } else {
    $subnet_range = get_subnet_range($db_link,$rsubnet);
    $subnet_filter = " and User_auth.ip_int>=$subnet_range[start] and User_auth.ip_int<=$subnet_range[stop] ";
    }

$enabled_filter='';
if ($enabled>0) {
    if ($enabled===2) { $enabled_filter = ' and User_auth.enabled=1'; }
    if ($enabled===1) { $enabled_filter = ' and User_auth.enabled=0'; }
    }

$ip_list_filter = $ou_filter.$subnet_filter.$enabled_filter;

print_ip_submenu($page_url);

?>
<div id="cont">
<form name="def" action="index.php" method="post">
<table class="data">
	<tr>
        <td>
        <b><?php print $list_ou; ?> - </b><?php print_ou_select($db_link, 'ou', $rou); ?>
        Отображать:<?php print_row_at_pages('rows',$displayed); ?>
        <b><?php print $list_subnet; ?> - </b><?php print_subnet_select_office($db_link, 'subnet', $rsubnet); ?>
        <b>По активности - </b><?php print_enabled_select('enabled', $enabled); ?>
        <input type="submit" value="Показать">
        </td>
	</tr>
</table>

<?php
$countSQL="SELECT Count(*) FROM User_auth, User_list WHERE User_auth.user_id = User_list.id AND User_auth.deleted =0 $ip_list_filter";
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
		<td align=Center><?php print $cell_comment; ?></td>
		<td align=Center><?php print $cell_dns_name; ?></td>
		<td align=Center><?php print $sort_url . "&sort=nagios&order=$new_order>" . $cell_nagios; ?></td>
		<td align=Center><?php print $sort_url . "&sort=link_check&order=$new_order>" . $cell_link; ?></td>
		<td align=Center><?php print $cell_enabled; ?></td>
		<td align=Center><?php print $cell_filter; ?></td>
		<td align=Center><?php print $cell_shaper; ?></td>
		<td align=Center><?php print $cell_connection; ?></td>
		<td align=Center><?php print $sort_url . "&sort=dhcp_time&order=$new_order>DHCP</a>"; ?></td>
		<td align=Center><?php print $sort_url . "&sort=last_found&order=$new_order>Last</a>"; ?></td>
	</tr>
<?php

$sSQL = "SELECT User_auth.id, User_auth.ip, User_auth.mac, User_auth.user_id, User_list.login, User_auth.nagios, User_auth.link_check, 
User_auth.comments, User_auth.dns_name, User_auth.dhcp_hostname, User_auth.enabled, User_auth.filter_group_id, User_auth.queue_id, 
User_auth.blocked, User_auth.dhcp_action, User_auth.dhcp_time, User_auth.last_found, User_auth.nagios_status
FROM User_auth, User_list
WHERE User_auth.user_id = User_list.id
AND User_auth.deleted =0 $ip_list_filter
ORDER BY $sort_table.$sort_field $order LIMIT $start,$displayed";
$users = get_records_sql($db_link,$sSQL);
foreach ($users as $user) {
    if ($user['dhcp_time'] == '0000-00-00 00:00:00') {
        $dhcp_str = '';
    } else {
        $dhcp_str = $user['dhcp_time'] . " (" . $user['dhcp_action'] . ")";
    }
    if ($user['last_found'] == '0000-00-00 00:00:00') { $user['last_found'] = ''; }
    print "<tr align=center>\n";
    $cl = "data";
    if (! $user['enabled']) { $cl = "warn"; }
    if ($user['blocked']) { $cl = "error"; }
    print "<td class=\"$cl\" ><a href=/admin/users/edituser.php?id=".$user['user_id'].">" . $user['login'] . "</a></td>\n";
    print "<td class=\"$cl\" ><a href=/admin/users/editauth.php?id=".$user['id'].">" . $user['ip'] . "</a></td>\n";
    print "<td class=\"$cl\" >" . expand_mac($db_link,$user['mac']) . "</td>\n";
    if (isset($user['dhcp_hostname']) and strlen($user['dhcp_hostname']) > 0) {
        print "<td class=\"$cl\" >".$user['comments']." [" . $user['dhcp_hostname'] . "]</td>\n";
    } else {
        print "<td class=\"$cl\" >".$user['comments']."</td>\n";
    }
    print "<td class=\"$cl\" >".$user['dns_name']."</td>\n";
    print "<td class=\"$cl\" >" . get_qa($user['nagios']) . "</td>\n";
    print "<td class=\"$cl\" >" . get_qa($user['link_check']) . "</td>\n";
    print "<td class=\"$cl\" >" . get_qa($user['enabled']) . "</td>\n";
    print "<td class=\"$cl\" >" . get_group($db_link, $user['filter_group_id']) . "</td>\n";
    print "<td class=\"$cl\" >" . get_queue($db_link, $user['queue_id']) . "</td>\n";
    print "<td class=\"data\" >" . get_connection($db_link, $user['id']) . "</td>\n";
    print "<td class=\"data\" >".$dhcp_str."</td>\n";
    if ($user['nagios_status'] == "UP") { $cl = "up"; }
    if ($user['nagios_status'] == "DOWN") { $cl = "down"; }
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
