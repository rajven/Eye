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
print "<br><br>\n";
print "<table class=\"data\">\n";
print "<tr class=\"info\">\n";
print "<td ><b><a href=index.php?sort=login&order=$new_order>".WEB_cell_login."</a></b></td>\n";
print "<td ><b>".WEB_cell_gateway."</b></td>\n";
print "<td ><b><a href=index.php?sort=tin&order=$new_order>".WEB_title_input."</a></b></td>\n";
print "<td ><b><a href=index.php?sort=tout&order=$new_order>".WEB_title_output."<a></b></td>\n";
print "</tr>\n";

$sort_sql=" ORDER BY tin DESC";

if (!empty($sort_field) and !empty($order)) { $sort_sql = " ORDER BY $sort_field $order"; }

$gateway_list = get_gateways($db_link);

$trafSQL = "SELECT 
User_list.login,User_list.ou_id,User_auth.user_id, User_stats.auth_id, 
User_stats.router_id, SUM( byte_in ) AS tin, SUM( byte_out ) AS tout 
FROM User_stats,User_auth,User_list WHERE User_list.id=User_auth.user_id 
AND User_stats.auth_id = User_auth.id 
AND User_stats.timestamp>='$date1' 
AND User_stats.timestamp<'$date2' 
";

if ($rou !== 0) { $trafSQL = $trafSQL . " AND User_list.ou_id=$rou"; }

if ($rgateway == 0) {
    $trafSQL = $trafSQL . " GROUP by User_auth.user_id,User_stats.router_id";
    } else {
    $trafSQL = $trafSQL . " AND User_stats.router_id=$rgateway GROUP by User_auth.user_id,User_stats.router_id";
    }

#set sort
$trafSQL=$trafSQL ." $sort_sql";

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
    print "<td align=left class=\"$cl\"><a href=userday.php?id=" . $row['user_id'] . "&date_start=$date1&date_stop=$date2>" . $row['login'] . "</a></td>\n";
    print "<td align=left class=\"$cl\">$s_router</td>\n";
    print "<td class=\"$cl\">" . fbytes($row['tin']) . "</td>\n";
    print "<td class=\"$cl\">" . fbytes($row['tout']) . "</td>\n";
    print "</tr>\n";
}

print "<tr align=center class=\"tr1\" onmouseover=\"className='tr2'\" onmouseout=\"className='tr1'\">\n";
print "<td class=\"data\" colspan=2><b>".WEB_title_itog."</b></td>\n";
print "<td class=\"data\"><b>" . fbytes($total_in) . "</b></td>\n";
print "<td class=\"data\"><b>" . fbytes($total_out) . "</b></td>\n";
print "</tr>\n";
?>
  </table>
<?php
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/footer.php");
?>
