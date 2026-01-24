<?php
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/auth.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/languages/" . HTML_LANG . ".php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/header.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/datetimefilter.php");

$f_id = getParam('device_show', $page_url, 0, FILTER_VALIDATE_INT);
$_SESSION[$page_url]['device_show'] = $f_id;

$params = [$date1, $date2];
$conditions = [];

// === 2. Условие по устройству ===
if ($f_id > 0) {
    $conditions[] = "D.id = ?";
    $params[] = (int)$f_id; // приведение к int для безопасности
}

$whereClause = !empty($conditions) ? ' AND ' . implode(' AND ', $conditions) : '';

$countSQL = "
    SELECT COUNT(*)
    FROM unknown_mac AS U
    JOIN devices AS D ON U.device_id = D.id
    JOIN device_ports AS DP ON U.port_id = DP.id
    WHERE D.device_type <= 2
      AND U.ts >= ?
      AND U.ts < ?
      $whereClause
";

$count_records = (int)get_single_field($db_link, $countSQL, $params);
$total = ceil($count_records / $displayed);
$page = max(1, min($page, $total));
$start = ($page - 1) * $displayed;


$limit = (int)$displayed;
$offset = (int)$start;

$dataParams = array_merge($params, [$limit, $offset]);

$sSQL = "
    SELECT U.mac, U.ts, DP.port, D.device_name
    FROM unknown_mac AS U
    JOIN devices AS D ON U.device_id = D.id
    JOIN device_ports AS DP ON U.port_id = DP.id
    WHERE D.device_type <= 2
      AND U.ts >= ?
      AND U.ts < ?
      $whereClause
    ORDER BY U.mac
    LIMIT ? OFFSET ?
";

$maclog = get_records_sql($db_link, $sSQL, $dataParams);

print_log_submenu($page_url);

?>

<div id="cont">
<br>
<form action="<?=$_SERVER['PHP_SELF']?>" method="post">
<?php echo WEB_log_report_by_device; print "&nbsp"; 
print_netdevice_select($db_link, "device_show", $f_id);
print_date_fields($date1,$date2,$date_shift);
print WEB_rows_at_page."&nbsp"; print_row_at_pages('rows',$displayed); 
?>
<input type="submit" value="<?php echo WEB_btn_show; ?>">
</form>

<?php print_navigation($page_url, $page, $displayed, $count_records, $total); ?>

<br>
<table class="data" width="750">
<tr align="center">
	<td class="data" width=110><b><?php echo WEB_cell_connection; ?></b></td>
	<td class="data"><b><?php echo WEB_device_port_name; ?></b></td>
	<td class="data"><b><?php echo WEB_cell_mac; ?></b></td>
	<td class="data"><b><?php echo WEB_cell_last_found; ?></b></td>
</tr>
<?php
foreach ($maclog as $row) {
    print "<tr align=center class=\"tr1\" onmouseover=\"className='tr2'\" onmouseout=\"className='tr1'\">\n";
    print "<td class=\"data\">" . $row['device_name'] . "</td>\n";
    print "<td class=\"data\">" . $row['port'] . "</td>\n";
    print "<td class=\"data\"><a href=/admin/logs/mac.php?mac=" . mac_dotted($row['mac']) . ">" . mac_dotted($row['mac']) . "</a></td>\n";
    print "<td class=\"data\">" . get_datetime_display($row['ts']) . "</td>\n";
    print "</tr>\n";
}
print "</table>\n";
print_navigation($page_url,$page,$displayed,$count_records,$total);
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/footer.php");
?>
