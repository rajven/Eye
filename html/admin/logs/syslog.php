<?php
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/auth.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/languages/" . HTML_LANG . ".php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/header.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/datetimefilter.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/logfilter.php");

$f_id = getParam('device_show', $page_url, 0, FILTER_VALIDATE_INT);
$_SESSION[$page_url]['device_show'] = $f_id;

$params = [$date1, $date2];
$conditions = [];

// === Фильтр по IP (через IN с параметрами) ===
if ($f_id > 0) {
    $dev_ips = get_device_ips($db_link, $f_id);
    if (!empty($dev_ips)) {
        // Создаём плейсхолдеры: ?, ?, ?
        $placeholders = str_repeat('?,', count($dev_ips) - 1) . '?';
        $conditions[] = "ip IN ($placeholders)";
        $params = array_merge($params, $dev_ips);
    }
}

if (!empty($fmessage)) {
    $conditions[] = "message LIKE ?";
    $params[] = '%' . $fmessage . '%';
}

$whereClause = !empty($conditions) ? ' AND ' . implode(' AND ', $conditions) : '';

$countSQL = "SELECT COUNT(*) FROM remote_syslog WHERE ts >= ? AND ts < ?" . $whereClause;
$count_records = (int)get_single_field($db_link, $countSQL, $params);
$total = ceil($count_records / $displayed);
$page = max(1, min($page, $total));
$start = ($page - 1) * $displayed;
$limit = (int)$displayed;
$offset = (int)$start;

$dataParams = array_merge($params, [$limit, $offset]);

$sSQL = "
    SELECT * FROM remote_syslog 
    WHERE ts >= ? AND ts < ?" . $whereClause . "
    ORDER BY ts DESC 
    LIMIT ? OFFSET ?
";

$syslog = get_records_sql($db_link, $sSQL, $dataParams);

print_log_submenu($page_url);

?>

<div id="cont">
<br>
<form action="<?=$_SERVER['PHP_SELF']?>" method="post">
    <?php
    print_date_fields($date1, $date2, $date_shift);
    echo WEB_log_report_by_device, "&nbsp;";
    print_device_select($db_link, "device_show", $f_id);
    echo WEB_rows_at_page, "&nbsp;";
    print_row_at_pages('rows', $displayed);
    ?>
    <input type="submit" value="<?=WEB_btn_show?>"><br><br>
    <?php echo WEB_log_filter_event; ?>:<input name="message" value="<?php echo $fmessage; ?>" />
</form>

<?php print_navigation($page_url, $page, $displayed, $count_records, $total); ?>

<br>
<table class="data" width="90%">
		<tr align="center">
			<td class="data" width=150><b><?php echo WEB_date; ?></b></td>
			<td class="data"><b><?php echo WEB_cell_ip; ?></b></td>
			<td class="data"><b><?php echo WEB_log_event; ?></b></td>
		</tr>

<?php

if (!empty($syslog)) {
    foreach ($syslog as $row) {
        print "<tr align=center class=\"tr1\" onmouseover=\"className='tr2'\" onmouseout=\"className='tr1'\">\n";
        print "<td class=\"data\">" . get_datetime_display($row['ts']) . "</td>\n";
        print "<td class=\"data\">" . $row['ip'] . "</td>\n";
        print "<td class=\"data\">" . $row['message'] . "</td>\n";
        print "</tr>\n";
        }
    }
print "</table>\n";
print_navigation($page_url,$page,$displayed,$count_records,$total);
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/footer.php");
?>
