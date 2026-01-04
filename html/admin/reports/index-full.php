<?php
$default_displayed=100;
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
<form action="index-full.php" method="post">
<?php echo WEB_cell_ou; ?>:&nbsp<?php print_ou_select($db_link,'ou',$rou); ?>
<?php print_date_fields($date1,$date2,$date_shift); ?>
<?php echo WEB_cell_gateway; ?>:&nbsp<?php print_gateway_select($db_link, 'gateway', $rgateway); ?>
<?php print WEB_rows_at_page."&nbsp"; print_row_at_pages('rows',$displayed); ?>
<input id='btn_filter' name='btn_filter' type="submit" value="<?php echo WEB_btn_show; ?>">
</form>

<?php

$sort_sql=" ORDER BY tin DESC";

if (!empty($sort_field) and !empty($order)) { $sort_sql = " ORDER BY $sort_field $order"; }

$gateway_list = get_gateways($db_link);

$trafSQL = "SELECT 
user_list.login,user_list.ou_id,user_auth.user_id, user_auth.ip, user_stats_full.auth_id, 
user_stats_full.router_id, SUM( byte_in ) AS tin, SUM( byte_out ) AS tout, MAX(ROUND(`pkt_in`/`step`)) as pin, MAX(ROUND(`pkt_out`/`step`)) as pout 
FROM user_stats_full,user_auth,user_list WHERE user_list.id=user_auth.user_id 
AND user_stats_full.auth_id = user_auth.id 
AND user_stats_full.timestamp>='$date1' 
AND user_stats_full.timestamp<'$date2' 
";

if ($rou !== 0) {
    $trafSQL = $trafSQL . " AND user_list.ou_id=$rou";
}

if ($rgateway == 0) {
    $trafSQL = $trafSQL . " GROUP by user_auth.id,user_stats_full.router_id";
} else {
    $trafSQL = $trafSQL . " AND user_stats_full.router_id=$rgateway GROUP by user_auth.id,user_stats_full.router_id";
}

$countSQL = "SELECT Count(*) FROM ($trafSQL) A";
$count_records = get_single_field($db_link,$countSQL);
$total=ceil($count_records/$displayed);
if ($page>$total) { $page=$total; }
if ($page<1) { $page=1; }
$start = ($page * $displayed) - $displayed;

#set sort
$trafSQL=$trafSQL ." $sort_sql LIMIT $start,$displayed";

print_navigation($page_url,$page,$displayed,$count_records,$total);

print "<br><br>\n";
print "<table class=\"data\" cellspacing=\"1\" cellpadding=\"4\">\n";
print "<tr align=\"center\">\n";
print "<td ><b><a href=index-full.php?sort=login&order=$new_order>".WEB_cell_login."</a></b></td>\n";
print "<td ><b><a href=index-full.php?sort=ip&order=$new_order>".WEB_cell_ip."</a></b></td>\n";
print "<td ><b>".WEB_cell_gateway."</b></td>\n";
print "<td ><b><a href=index-full.php?sort=tin&order=$new_order>".WEB_title_input."</a></b></td>\n";
print "<td ><b><a href=index-full.php?sort=tout&order=$new_order>".WEB_title_output."<a></b></td>\n";
print "<td ><b><a href=index-full.php?sort=pin&order=$new_order>".WEB_title_maxpktin."</a></b></td>\n";
print "<td ><b><a href=index-full.php?sort=pout&order=$new_order>".WEB_title_maxpktout."<a></b></td>\n";
print "</tr>\n";

$total_in = 0;
$total_out = 0;

$traf = get_records_sql($db_link, $trafSQL);

foreach ($traf as $row) {
    if ($row['tin'] + $row['tout'] == 0) { continue; }
    $total_in += $row['tin'];
    $total_out += $row['tout'];
    $s_router = !empty($gateway_list[$row['router_id']]) ? $gateway_list[$row['router_id']] : '';
    $cl = $row['tout'] > 2 * $row['tin'] ? "nb" : "data";
    
    print "<tr align=center class=\"tr1\" onmouseover=\"className='tr2'\" onmouseout=\"className='tr1'\">\n";
    print "<td align=left class=\"$cl\">" . $row['login'] . "</td>\n";
    print "<td align=left class=\"$cl\"><a href=authday.php?id=" . $row['auth_id'] . "&date_start=$date1&date_stop=$date2>" . $row['ip'] . "</a></td>\n";
    print "<td align=left class=\"$cl\">$s_router</td>\n";
    print "<td class=\"$cl\">" . fbytes($row['tin']) . "</td>\n";
    print "<td class=\"$cl\">" . fbytes($row['tout']) . "</td>\n";
    print "<td class=\"$cl\">" . fpkts($row['pin']) . "</td>\n";
    print "<td class=\"$cl\">" . fpkts($row['pout']) . "</td>\n";
    print "</tr>\n";
}

print "<tr align=center class=\"tr1\" onmouseover=\"className='tr2'\" onmouseout=\"className='tr1'\">\n";
print "<td class=\"data\" colspan=2><b>".WEB_title_itog."</b></td>\n";
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
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/footer.simple.php");
?>
