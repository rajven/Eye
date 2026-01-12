<?php
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/auth.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/languages/" . HTML_LANG . ".php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/header.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/idfilter.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/datetimefilter.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/oufilter.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/gatefilter.php");
$auth=get_record_sql($db_link,'SELECT * FROM user_auth WHERE id=?', [$id]);
$user=get_record_sql($db_link,'SELECT * FROM user_list WHERE id=?', [ $auth['user_id']]);
$gateway_list = get_gateways($db_link);

?>
<div id="cont">
<b>
<?php
print WEB_report_user_traffic."&nbsp<a href=../users/edituser.php?id=".$auth['user_id'].">" . $user['login'] . "</a>&nbsp"; 
print WEB_report_traffic_for_ip."&nbsp<a href=../users/editauth.php?id=$id>".$auth['ip']."</a>";
?>
</b>
<br>
<form action="<?php print $_SERVER['PHP_SELF']; ?>" method="post">
<input type="hidden" name="id" value=<?php echo $id; ?>>
<?php print_date_fields($date1,$date2,$date_shift); ?>
<?php echo WEB_cell_gateway; ?>:&nbsp <?php print_gateway_select($db_link, 'gateway', $rgateway); ?>
<input type="submit" value="<?php echo WEB_btn_show; ?>">
</form>
<br>
<table class="data" width=700>
<tr align="center">
<td class="data"><b><?php echo WEB_cell_gateway; ?></b></td>
<td class="data"><b><?php print WEB_title_date; ?></b></td>
<td class="data"><b><?php print WEB_title_input; ?></b></td>
<td class="data"><b><?php print WEB_title_output; ?></b></td>
<td class="data"><b><?php print WEB_title_maxpktin; ?></b></td>
<td class="data"><b><?php print WEB_title_maxpktout; ?></b></td>
</tr>
<?php

// === 1. Определяем тип СУБД ===
$db_type = $db_link->getAttribute(PDO::ATTR_DRIVER_NAME);

// === 2. Выбираем формат даты для каждой СУБД ===
if ($days_shift <= 1) {
    $mysql_format = '%Y-%m-%d %H';
    $pg_format    = 'YYYY-MM-DD HH24';
} elseif ($days_shift <= 30) {
    $mysql_format = '%Y-%m-%d';
    $pg_format    = 'YYYY-MM-DD';
} elseif ($days_shift <= 730) {
    $mysql_format = '%Y-%m';
    $pg_format    = 'YYYY-MM';
} else {
    $mysql_format = '%Y';
    $pg_format    = 'YYYY';
}

// === 3. Базовые параметры (все значения — через параметры!) ===
$params = [$date1, $date2, (int)$id];

// === 4. Дополнительное условие по router_id (если нужно) ===
$router_condition = '';
if (!empty($rgateway) && $rgateway > 0) {
    $router_condition = ' AND router_id = ?';
    $params[] = (int)$rgateway;
}

// === 5. Формируем запрос в зависимости от СУБД ===
if ($db_type === 'mysql') {
    $sSQL = "
        SELECT 
            router_id,
            DATE_FORMAT(ts, '$mysql_format') AS tHour,
            SUM(byte_in) AS byte_in_sum,
            SUM(byte_out) AS byte_out_sum,
            MAX(ROUND(pkt_in / step)) AS pkt_in_max,
            MAX(ROUND(pkt_out / step)) AS pkt_out_max
        FROM user_stats_full
        WHERE ts >= ? AND ts < ? AND auth_id = ?$router_condition
        GROUP BY DATE_FORMAT(ts, '$mysql_format'), router_id
        ORDER BY tHour" . ($rgateway > 0 ? '' : ', router_id');

} elseif ($db_type === 'pgsql') {
    $sSQL = "
        SELECT 
            router_id,
            TO_CHAR(ts, '$pg_format') AS tHour,
            SUM(byte_in) AS byte_in_sum,
            SUM(byte_out) AS byte_out_sum,
            MAX(ROUND(pkt_in / step)) AS pkt_in_max,
            MAX(ROUND(pkt_out / step)) AS pkt_out_max
        FROM user_stats_full
        WHERE ts >= ? AND ts < ? AND auth_id = ?$router_condition
        GROUP BY TO_CHAR(ts, '$pg_format'), router_id
        ORDER BY tHour" . ($rgateway > 0 ? '' : ', router_id');

} else {
    throw new RuntimeException("Unsupported database driver: $db_type");
}

// === 6. Выполняем запрос ===
$userdata = get_records_sql($db_link, $sSQL, $params);

$sum_in = 0;
$sum_out = 0;

print "<tr align=center class=\"tr1\" onmouseover=\"className='tr2'\" onmouseout=\"className='tr1'\">\n";
print "<td class=\"data\" colspan=2>".$auth['description']."</td>\n";
print "<td class=\"data\" colspan=2><a href=/admin/reports/userdaydetail.php?id=$id&date_start=$date1&date_stop=$date2>TOP 10</a></td>\n";
print "<td class=\"data\" colspan=2><a href=/admin/reports/userdaydetaillog.php?id=$id&date_start=$date1&date_stop=$date2>".WEB_report_detail."</a></td>\n";
print "</tr>\n";

foreach ($userdata as $row) {
    print "<tr align=center class=\"tr1\" onmouseover=\"className='tr2'\" onmouseout=\"className='tr1'\">\n";
    print "<td class=\"data\">" . $gateway_list[$row['router_id']] . "</td>\n";
    print "<td class=\"data\">" . $row['tHour'] . "</td>\n";
    print "<td class=\"data\">" . fbytes($row['byte_in_sum']) . "</td>\n";
    print "<td class=\"data\">" . fbytes($row['byte_out_sum']) . "</td>\n";
    print "<td class=\"data\">" . fpkts($row['pkt_in_max']) . "</td>\n";
    print "<td class=\"data\">" . fpkts($row['pkt_out_max']) . "</td>\n";
    print "</tr>\n";
    $sum_in += $row['byte_in_sum'];
    $sum_out += $row['byte_out_sum'];
}

print "<tr align=center class=\"tr1\" onmouseover=\"className='tr2'\" onmouseout=\"className='tr1'\">\n";
print "<td class=\"data\"><b>" . WEB_title_itog . "</b></td>\n";
print "<td class=\"data\"><b> </b></td>\n";
print "<td class=\"data\"><b>" . fbytes($sum_in) . "</b></td>\n";
print "<td class=\"data\"><b>" . fbytes($sum_out) . "</b></td>\n";
print "<td class=\"data\"><b></b></td>\n";
print "<td class=\"data\"><b></b></td>\n";
print "</tr>\n";
?>
</table>
<?php
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/footer.php");
?>
