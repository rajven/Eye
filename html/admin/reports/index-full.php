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
$gateway_list = get_gateways($db_link);

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

// === 1. Выбор таблицы статистики ===
$traffic_stat_table = ($days_shift >= ($config["traffic_ipstat_history"] ?? 30)) 
    ? 'user_stats' 
    : 'user_stats_full';

// === 2. Безопасная сортировка ===
$allowed_sort_fields = ['tin', 'tout', 'pin', 'pout', 'id', 'router_id'];
$allowed_orders = ['ASC', 'DESC'];

$sort_field = in_array($sort_field, $allowed_sort_fields, true) ? $sort_field : 'tin';
$order = in_array(strtoupper($order), $allowed_orders, true) ? strtoupper($order) : 'DESC';

$sort_sql = " ORDER BY $sort_field $order";

// === 3. Базовые параметры ===
$sql_params = [$date1, $date2];

// === 4. Формируем запрос ===
$trafSQL = "
    SELECT 
        user_auth.id,
        {$traffic_stat_table}.router_id,
        SUM(byte_in) AS tin,
        SUM(byte_out) AS tout,
        MAX(ROUND(pkt_in / step)) AS pin,
        MAX(ROUND(pkt_out / step)) AS pout
    FROM {$traffic_stat_table}
    JOIN user_auth ON {$traffic_stat_table}.auth_id = user_auth.id
    JOIN user_list ON user_list.id = user_auth.user_id
    WHERE {$traffic_stat_table}.ts >= ?
      AND {$traffic_stat_table}.ts < ?
";

// === 5. Дополнительные условия ===
if ($rou !== 0) {
    $trafSQL .= " AND user_list.ou_id = ?";
    $sql_params[] = (int)$rou;
}
if ($rgateway > 0) {
    $trafSQL .= " AND {$traffic_stat_table}.router_id = ?";
    $sql_params[] = (int)$rgateway;
}

// === 6. GROUP BY (корректный для текущего SELECT) ===
$trafSQL .= " GROUP BY user_auth.id, {$traffic_stat_table}.router_id";

// === 7. Подсчёт записей ===
$countSQL = "SELECT COUNT(*) FROM ($trafSQL) AS subquery";
$count_records = (int)get_single_field($db_link, $countSQL, $sql_params);

// === 8. Пагинация ===
$total = ceil($count_records / $displayed);
$page = max(1, min($page, $total));
$start = ($page - 1) * $displayed; // исправлено

print_navigation($page_url, $page, $displayed, $count_records, $total);

// === 9. Добавляем сортировку, LIMIT, OFFSET ===
$trafSQL .= $sort_sql . " LIMIT ? OFFSET ?";
$sql_params[] = (int)$displayed;
$sql_params[] = (int)$start;

// === 10. Выполняем запрос ===
$traf = get_records_sql($db_link, $trafSQL, $sql_params);

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

foreach ($traf as $row) {
    if ($row['tin'] + $row['tout'] == 0) { continue; }
    $total_in += $row['tin'];
    $total_out += $row['tout'];
    $s_router = !empty($gateway_list[$row['router_id']]) ? $gateway_list[$row['router_id']] : '';
    $cl = $row['tout'] > 2 * $row['tin'] ? "nb" : "data";
    $a_SQL='SELECT ip,U.login FROM user_auth, user_list as U where user_auth.user_id=U.id and user_auth.id=?';
    $auth_record = get_record_sql($db_link,$a_SQL,[$row['id']]);
    print "<tr align=center class=\"tr1\" onmouseover=\"className='tr2'\" onmouseout=\"className='tr1'\">\n";
    print "<td align=left class=\"$cl\">" . $auth_record['login'] . "</td>\n";
    print "<td align=left class=\"$cl\"><a href=authday.php?id=" . $row['id'] . "&date_start=$date1&date_stop=$date2>" . $auth_record['ip'] . "</a></td>\n";
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
