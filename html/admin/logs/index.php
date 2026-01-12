<?php
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/auth.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/languages/" . HTML_LANG . ".php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/header.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/datetimefilter.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/loglevelfilter.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/logfilter.php");

$fuser_ip = getParam('user_ip', $page_url, '');
$_SESSION[$page_url]['user_ip'] = $fuser_ip;

print_log_submenu($page_url);
?>
<div id="cont">
<br>
<form action="<?=$_SERVER['PHP_SELF']?>" method="post">
<?php print_date_fields($date1,$date2,$date_shift); ?>
<?php echo WEB_log_level_display; ?>:<?php print_loglevel_select('display_log_level',$display_log_level); ?>
<?php print WEB_rows_at_page."&nbsp"; print_row_at_pages('rows',$displayed); ?>
<input type="submit" value="<?php echo WEB_btn_show; ?>"><br><br>
<?php echo WEB_log_filter_source; ?>:&nbsp<input name="customer" value="<?php echo $fcustomer; ?>" /> &nbsp
<?php echo WEB_log_event; ?>:&nbsp<input name="message" value="<?php echo $fmessage; ?>" /> &nbsp
<?php echo WEB_msg_IP; ?>:&nbsp<input name="user_ip" value="<?php echo $fuser_ip; ?>" /><br>
</form>

<?php
// === 1. Формируем базовые параметры и условия ===
$params = [$date1, $date2];
$conditions = [];

// Уровень логирования
if ($display_log_level == L_ERROR) {
    $conditions[] = "level = ?";
    $params[] = L_ERROR;
} elseif ($display_log_level == L_WARNING) {
    $conditions[] = "level <= ?";
    $params[] = L_WARNING;
} elseif ($display_log_level == L_INFO) {
    $conditions[] = "level <= ?";
    $params[] = L_INFO;
} elseif ($display_log_level == L_VERBOSE) {
    $conditions[] = "level <= ?";
    $params[] = L_VERBOSE;
}
// L_DEBUG: не добавляем условие (показываем всё)

// Остальные фильтры — ВСЕ через параметры!
if (!empty($fcustomer)) {
    $conditions[] = "customer LIKE ?";
    $params[] = '%' . $fcustomer . '%';
}
if (!empty($fmessage)) {
    $conditions[] = "message LIKE ?";
    $params[] = '%' . $fmessage . '%';
}
if (!empty($fuser_ip)) {
    $conditions[] = "ip LIKE ?";
    $params[] = '%' . $fuser_ip . '%';
}

// Собираем WHERE-часть
$whereClause = !empty($conditions) ? ' AND ' . implode(' AND ', $conditions) : '';

// === 2. Подсчёт общего количества записей ===
$countSQL = "SELECT COUNT(*) FROM worklog WHERE ts >= ? AND ts < ?" . $whereClause;
$count_records = (int)get_single_field($db_link, $countSQL, $params);

// === 3. Пагинация ===
$total = ceil($count_records / $displayed);
$page = max(1, min($page, $total)); // корректное ограничение страницы
$start = ($page - 1) * $displayed;   // исправлено: OFFSET должен быть (page-1)*limit

print_navigation($page_url, $page, $displayed, $count_records, $total);

// === 4. Запрос данных с пагинацией ===
// Добавляем LIMIT и OFFSET как параметры (приводим к int!)
$limit = (int)$displayed;
$offset = (int)$start;

$dataParams = array_merge($params, [$limit, $offset]);

$sSQL = "
    SELECT * FROM worklog 
    WHERE ts >= ? AND ts < ?" . $whereClause . "
    ORDER BY ts DESC 
    LIMIT ? OFFSET ?
";

$userlog = get_records_sql($db_link, $sSQL, $dataParams);

?>
<br>

<table class="data">
<tr align="center">
	<td class="data" width=150><b><?php echo WEB_log_time; ?></b></td>
	<td class="data"><b><?php echo WEB_log_filter_source; ?></b></td>
	<td class="data"><b><?php echo WEB_msg_IP; ?></b></td>
	<td class="data"><b><?php echo WEB_log_level; ?></b></td>
	<td class="data"><b><?php echo WEB_log_event; ?></b></td>
</tr>

<?php

foreach ($userlog as $row) {
    print "<tr align=center class=\"tr1\" onmouseover=\"className='tr2'\" onmouseout=\"className='tr1'\">\n";
    print "<td class=\"data\">" . $row['ts'] . "</td>\n";
    print "<td class=\"data\">" . $row['customer'] . "</td>\n";
    $msg_level = 'INFO';
    if ($row['level'] == L_ERROR) { $msg_level='ERROR'; }
    if ($row['level'] == L_WARNING) { $msg_level='WARNING'; }
    if ($row['level'] == L_DEBUG) { $msg_level='DEBUG'; }
    if ($row['level'] == L_VERBOSE) { $msg_level='VERBOSE'; }
    print "<td class=\"data\">" . $row['ip'] . "</td>\n";
    print "<td class=\"data\">" . $msg_level . "</td>\n";
    $print_msg = expand_log_str($db_link, $row['message']);
    print "<td class=\"data\" align=left>" . $print_msg . "</td>\n";
    print "</tr>\n";
}
print "</table>\n";
print_navigation($page_url,$page,$displayed,$count_records,$total);
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/footer.php");
?>
