<?php

require_once ($_SERVER['DOCUMENT_ROOT']."/inc/auth.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/languages/" . $language . ".php");
unset($_POST);
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/header.php");
print_control_submenu($page_url);
$zombi_days = get_option($db_link, 35);

?>
<div id="cont">
<br>
<form name="def" action="control-subnets.php" method="post">
<b>Статистика использования сетей организации</b> <br>
<table class="data">
<tr align="center">
	<td><b>Сеть</b></td>
	<td><b>Всего<br>адресов</b></td>
	<td><b>Занято</b></td>
	<td><b>Свободно<br>(всего)</b></td>
	<td><b>Размер<br>dhcp пула</b></td>
	<td><b>Занято<br>в пуле</b></td>
	<td><b>Свободно<br>в пуле</b></td>
	<td><b>Свободно<br>(static)</b></td>
<?php
print "<td><b>Зомби, всего <br>(> $zombi_days дней)</b></td>";
print "<td><b>Зомби dhcp<br>(> $zombi_days дней)</b></td>";
?>
</tr>
<?
$t_subnets = get_records($db_link,'subnets','office=1 ORDER BY ip_int_start');
if (!empty($t_subnets)) {
foreach ( $t_subnets as $row ) {
    print "<tr align=center>\n";
    $cl="data";
    print "<td class=\"$cl\"><a href=/admin/iplist/index.php?ou=0&subnet=".$row['id'].">".$row['subnet']."</a></td>\n";
    $all_ips = $row['ip_int_stop']-$row['ip_int_start']-3;
    print "<td class=\"$cl\">".$all_ips."</td>\n";
#used
    $used_all = get_count_records($db_link,'User_auth','deleted=0 and ip_int>='.$row['ip_int_start'].' and ip_int<='.$row['ip_int_stop']);
    print "<td class=\"$cl\">".$used_all."</td>\n";
    $free_all = $all_ips - $used_all;
    print "<td class=\"$cl\">".$free_all."</td>\n";
    $dhcp_pool = $row['dhcp_stop']-$row['dhcp_start']+1;
    print "<td class=\"$cl\">".$dhcp_pool."</td>\n";
#used pool
    $used_dhcp = get_count_records($db_link,'User_auth','deleted=0 and ip_int>='.$row['dhcp_start'].' and ip_int<='.$row['dhcp_stop']);
    print "<td class=\"$cl\">".$used_dhcp."</td>\n";
    $free_dhcp = $dhcp_pool - $used_dhcp;
    print "<td class=\"$cl\">".$free_dhcp."</td>\n";
    $free_static = $free_all -  $free_dhcp;
    print "<td class=\"$cl\">".$free_static."</td>\n";
    $zombi = get_count_records($db_link,'User_auth','deleted=0 and ip_int>='.$row['ip_int_start'].' and ip_int<='.$row['ip_int_stop'].' and last_found<=(NOW() - INTERVAL '.$zombi_days.' DAY)');
    print "<td class=\"$cl\">".$zombi."</td>\n";
    $zombi = get_count_records($db_link,'User_auth','deleted=0 and ip_int>='.$row['dhcp_start'].' and ip_int<='.$row['dhcp_stop'].' and last_found<=(NOW() - INTERVAL '.$zombi_days.' DAY)');
    print "<td class=\"$cl\">".$zombi."</td>\n";
    print "</tr>\n";
    }
}
?>
</table>
</form>
<?php
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/footer.php");
?>
