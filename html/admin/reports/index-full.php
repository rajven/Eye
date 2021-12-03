<?php
$default_displayed=100;
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/auth.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/languages/" . $language . ".php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/header.php");
$default_date_shift='d';
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/datefilter.php");
$default_sort='tin';
$default_order='DESC';
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/oufilter.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/sortfilter.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/gatefilter.php");

print_reports_submenu($page_url);

?>
<div id="cont">
<form action="index-full.php" method="post">
Группа:&nbsp<?php print_ou_select($db_link,'ou',$rou); ?>
Начало:&nbsp<input type="date" name="date_start" value="<?php echo $date1; ?>" />
Конец:&nbsp<input type="date" name="date_stop" value="<?php echo $date2; ?>" />
Шлюз:&nbsp<?php print_gateway_select($db_link, 'gateway', $rgateway); ?>
Отображать:<?php print_row_at_pages('rows',$displayed); ?>
<input type="submit" value="OK">
</form>

<?php

$sort_sql=" ORDER BY tin DESC";

if (!empty($sort_field) and !empty($order)) { $sort_sql = " ORDER BY $sort_field $order"; }

$gateway_list = get_gateways($db_link);

$trafSQL = "SELECT 
User_list.login,User_list.ou_id,User_auth.user_id, User_auth.ip, User_stats_full.auth_id, 
User_stats_full.router_id, SUM( byte_in ) AS tin, SUM( byte_out ) AS tout, MAX(ROUND(`pkt_in`/`step`)) as pin, MAX(ROUND(`pkt_out`/`step`)) as pout 
FROM User_stats_full,User_auth,User_list WHERE User_list.id=User_auth.user_id 
AND User_stats_full.auth_id = User_auth.id 
AND User_stats_full.timestamp>='$date1' 
AND User_stats_full.timestamp<'$date2' 
";

if ($rou !== 0) {
    $trafSQL = $trafSQL . " AND User_list.ou_id=$rou";
}

if ($rgateway == 0) {
    $trafSQL = $trafSQL . " GROUP by User_auth.id,User_stats_full.router_id";
} else {
    $trafSQL = $trafSQL . " AND User_stats_full.router_id=$rgateway GROUP by User_auth.id,User_stats_full.router_id";
}

$countSQL = "SELECT Count(*) FROM ($trafSQL) A";

$res = mysqli_query($db_link, $countSQL);
$count_records = mysqli_fetch_array($res);
$total=ceil($count_records[0]/$displayed);
if ($page>$total) { $page=$total; }
if ($page<1) { $page=1; }
$start = ($page * $displayed) - $displayed;

#set sort
$trafSQL=$trafSQL ." $sort_sql LIMIT $start,$displayed";

print_navigation($page_url,$page,$displayed,$count_records[0],$total);

print "<br><br>\n";
print "<table class=\"data\" width=\"850\" cellspacing=\"1\" cellpadding=\"4\">\n";
print "<tr align=\"center\">\n";
print "<td ><b><a href=index-full.php?sort=login&order=$new_order>Логин</a></b></td>\n";
print "<td ><b><a href=index-full.php?sort=ip&order=$new_order>IP</a></b></td>\n";
print "<td ><b>Gate</b></td>\n";
print "<td ><b><a href=index-full.php?sort=tin&order=$new_order>Входящий</a></b></td>\n";
print "<td ><b><a href=index-full.php?sort=tout&order=$new_order>Исходящий<a></b></td>\n";
print "<td ><b><a href=index-full.php?sort=pin&order=$new_order>Max in, pkt/s</a></b></td>\n";
print "<td ><b><a href=index-full.php?sort=pout&order=$new_order>Max out, pkt/s<a></b></td>\n";
print "</tr>\n";

$total_in = 0;
$total_out = 0;

$traf = mysqli_query($db_link, $trafSQL);

while (list ($s_login,$s_ou_id,$u_id,$s_ip,$s_auth_id, $s_router_id, $traf_day_in, $traf_day_out, $p_in, $p_out) = mysqli_fetch_array($traf)) {
    if ($traf_day_in + $traf_day_out ==0) { continue; }
    $total_in += $traf_day_in;
    $total_out += $traf_day_out;
    $s_router = $gateway_list[$s_router_id];
    $cl = "data";
    if ($traf_day_out > 2 * $traf_day_in) { $cl = "nb"; }
    print "<tr align=center class=\"tr1\" onmouseover=\"className='tr2'\" onmouseout=\"className='tr1'\">\n";
    print "<td align=left class=\"$cl\">$s_login</td>\n";
    print "<td align=left class=\"$cl\"><a href=authday.php?id=$s_auth_id&date_start=$date1&date_stop=$date2>$s_ip</a></td>\n";
    print "<td align=left class=\"$cl\">$s_router</td>\n";
    print "<td class=\"$cl\">" . fbytes($traf_day_in) . "</td>\n";
    print "<td class=\"$cl\">" . fbytes($traf_day_out) . "</td>\n";
    print "<td class=\"$cl\">" . fpkts($p_in) . "</td>\n";
    print "<td class=\"$cl\">" . fpkts($p_out) . "</td>\n";
    print "</tr>\n";
}
print "<tr align=center align=center class=\"tr1\" onmouseover=\"className='tr2'\" onmouseout=\"className='tr1'\">\n";
print "<td class=\"data\" colspan=2><b>Итого</b></td>\n";
print "<td class=\"data\"><b></b></td>\n";
print "<td class=\"data\"><b>" . fbytes($total_in) . "</b></td>\n";
print "<td class=\"data\"><b>" . fbytes($total_out) . "</b></td>\n";
print "<td class=\"data\"><b></b></td>\n";
print "<td class=\"data\"><b></b></td>\n";
print "</tr>\n";
?>
</table>

<?php
print_navigation($page_url,$page,$displayed,$count_records[0],$total);
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/footer.php");
?>
