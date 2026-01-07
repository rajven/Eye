<?php
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/auth.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/languages/" . HTML_LANG . ".php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/header.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/datetimefilter.php");
$default_sort='tin';
$default_order='DESC';
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/oufilter.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/sortfilter.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/gatefilter.php");

print_reports_submenu($page_url);

?>
<div id="cont">

<form action="index.php" method="post">
<?php echo WEB_cell_ou; ?>:&nbsp<?php print_ou_select($db_link,'ou',$rou); ?>
<?php print_date_fields($date1,$date2,$date_shift); ?>
<?php echo WEB_cell_gateway; ?>:&nbsp<?php print_gateway_select($db_link, 'gateway', $rgateway); ?>
<?php print WEB_rows_at_page."&nbsp"; print_row_at_pages('rows',$displayed); ?>
<input type="submit" value="<?php echo WEB_btn_show; ?>">
</form>

<?php

$traffic_stat_table = 'user_stats_full';
if ($days_shift >= $config["traffic_ipstat_history"]) { $traffic_stat_table = 'user_stats'; }

$sort_sql=" ORDER BY tin DESC";

if (!empty($sort_field) and !empty($order)) { $sort_sql = " ORDER BY $sort_field $order"; }

$gateway_list = get_gateways($db_link);

$sql_params=[];

$trafSQL = "SELECT user_auth.user_id,".$traffic_stat_table.".router_id,
SUM( byte_in ) AS tin, SUM( byte_out ) AS tout, MAX(ROUND(pkt_in/step)) as pin, MAX(ROUND(pkt_out/step)) as pout 
FROM ".$traffic_stat_table.",user_auth,user_list WHERE user_list.id=user_auth.user_id 
AND ".$traffic_stat_table.".auth_id = user_auth.id 
AND ".$traffic_stat_table.".ts>= ? AND ".$traffic_stat_table.".ts< ?";

array_push($sql_params,$date1);
array_push($sql_params,$date2);
if ($rou !== 0) {
    $trafSQL = $trafSQL . " AND user_list.ou_id=?";
    array_push($sql_params,$rou);
}

if ($rgateway >0) {
    $trafSQL = $trafSQL . " AND ".$traffic_stat_table.".router_id= ?";
    array_push($sql_params,$rgateway);
}

$trafSQL = $trafSQL . "  GROUP by user_auth.user_id,".$traffic_stat_table.".router_id";

$countSQL = "SELECT Count(*) FROM ($trafSQL) A";
$count_records = get_single_field($db_link,$countSQL,$sql_params);

$total=ceil($count_records/$displayed);
if ($page>$total) { $page=$total; }
if ($page<1) { $page=1; }
$start = ($page * $displayed) - $displayed;

#set sort
$trafSQL=$trafSQL ." $sort_sql LIMIT ? OFFSET ?";
array_push($sql_params,$displayed);
array_push($sql_params,$start);

print_navigation($page_url,$page,$displayed,$count_records,$total);

print "<br><br>\n";
print "<table class=\"data\">\n";
print "<tr class=\"info\">\n";
print "<td ><b><a href=index.php?sort=login&order=$new_order>".WEB_cell_login."</a></b></td>\n";
print "<td ><b>".WEB_cell_gateway."</b></td>\n";
print "<td ><b><a href=index.php?sort=tin&order=$new_order>".WEB_title_input."</a></b></td>\n";
print "<td ><b><a href=index.php?sort=tout&order=$new_order>".WEB_title_output."<a></b></td>\n";
print "<td ><b><a href=index.php?sort=pin&order=$new_order>".WEB_title_maxpktin."</a></b></td>\n";
print "<td ><b><a href=index.php?sort=pout&order=$new_order>".WEB_title_maxpktout."<a></b></td>\n";
print "</tr>\n";

$total_in = 0;
$total_out = 0;

$traf = get_records_sql($db_link, $trafSQL,$sql_params);

foreach ($traf as $row) {
    if ($row['tin'] + $row['tout'] == 0) { continue; }
    $total_in += $row['tin'];
    $total_out += $row['tout'];
    $s_router = !empty($gateway_list[$row['router_id']]) ? $gateway_list[$row['router_id']] : '';
    $cl = $row['tout'] > 2 * $row['tin'] ? "nb" : "data";

    $u_SQL='SELECT * FROM user_list WHERE id=?';
    $user_record = get_record_sql($db_link,$u_SQL,[$row['user_id']]);
    print "<tr align=center class=\"tr1\" onmouseover=\"className='tr2'\" onmouseout=\"className='tr1'\">\n";
    print "<td align=left class=\"$cl\"><a href=userday.php?id=" . $row['user_id'] . "&date_start=$date1&date_stop=$date2>" . $user_record['login'] . "</a></td>\n";
    print "<td align=left class=\"$cl\">$s_router</td>\n";
    print "<td class=\"$cl\">" . fbytes($row['tin']) . "</td>\n";
    print "<td class=\"$cl\">" . fbytes($row['tout']) . "</td>\n";
    print "<td class=\"$cl\">" . fpkts($row['pin']) . "</td>\n";
    print "<td class=\"$cl\">" . fpkts($row['pout']) . "</td>\n";
    print "</tr>\n";
}

print "<tr align=center class=\"tr1\" onmouseover=\"className='tr2'\" onmouseout=\"className='tr1'\">\n";
print "<td class=\"data\" colspan=2><b>".WEB_title_itog."</b></td>\n";
print "<td class=\"data\"><b>" . fbytes($total_in) . "</b></td>\n";
print "<td class=\"data\"><b>" . fbytes($total_out) . "</b></td>\n";
print "<td class=\"data\"><b></b></td>\n";
print "<td class=\"data\"><b></b></td>\n";
print "</tr>\n";
?>
</table>

<?php
print_navigation($page_url,$page,$displayed,$count_records,$total);
?>

<script>
document.getElementById('ou').addEventListener('change', function(event) {
  const buttonApply = document.getElementById('btn_filter');
  buttonApply.click();
});

document.getElementById('rows').addEventListener('change', function(event) {
  const buttonApply = document.getElementById('btn_filter');
  buttonApply.click();
});

document.getElementById('gateway').addEventListener('change', function(event) {
  const buttonApply = document.getElementById('btn_filter');
  buttonApply.click();
});

</script>

<?php
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/footer.php");
?>
