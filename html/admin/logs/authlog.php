<?php
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/auth.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/languages/" . HTML_LANG . ".php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/header.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/datetimefilter.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/loglevelfilter.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/logfilter.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/authidfilter.php");
if (!isset($auth_id)) { header('Location: /admin/logs/index.php', true, 301); exit; }
?>
<div id="cont">
<br>
<form action="<?=$_SERVER['PHP_SELF']?>" method="post">
<input name="auth_id" value="<?php print $auth_id; ?>" hidden=true>
<?php print_date_fields($date1,$date2,$date_shift); ?>
<?php echo WEB_log_level_display; ?>:<?php print_loglevel_select('display_log_level',$display_log_level); ?>
<?php print WEB_rows_at_page."&nbsp"; print_row_at_pages('rows',$displayed); ?>
<input type="submit" value="<?php echo WEB_btn_show; ?>"><br><br>
<?php echo WEB_log_filter_source; ?>:<input name="customer" value="<?php echo $fcustomer; ?>" />
<?php echo WEB_log_message; ?>:<input name="message" value="<?php echo $fmessage; ?>" />
</form>

<?php
$params = [$date1, $date2];
$log_filter_parts = [];
// Уровень логирования
if ($display_log_level == L_ERROR) {
    $log_filter_parts[] = "level = ?";
    $params[] = L_ERROR;
} elseif ($display_log_level == L_WARNING) {
    $log_filter_parts[] = "level <= ?";
    $params[] = L_WARNING;
} elseif ($display_log_level == L_INFO) {
    $log_filter_parts[] = "level <= ?";
    $params[] = L_INFO;
} elseif ($display_log_level == L_VERBOSE) {
    $log_filter_parts[] = "level <= ?";
    $params[] = L_VERBOSE;
}
// L_DEBUG — ничего не добавляем (все уровни)

$log_filter_parts[] = "auth_id = ?";
$params[] = $auth_id;

if (!empty($fcustomer)) {
    $log_filter_parts[] = "customer LIKE ?";
    $params[] = '%' . $fcustomer . '%';
}
if (!empty($fmessage)) {
    $log_filter_parts[] = "message LIKE ?";
    $params[] = '%' . $fmessage . '%';
}

// Собираем фильтр
$log_filter = !empty($log_filter_parts) ? ' AND ' . implode(' AND ', $log_filter_parts) : '';

$countSQL = "SELECT COUNT(*) FROM worklog WHERE ts >= ? AND ts < ?" . $log_filter;
$count_records = get_single_field($db_link, $countSQL, $params);

$total=ceil($count_records/$displayed);
if ($page>$total) { $page=$total; }
if ($page<1) { $page=1; }
$start = ($page * $displayed) - $displayed; 
print_navigation($page_url,$page,$displayed,$count_records,$total);
#speedup paging
$sSQL = "SELECT ts,customer,message,level FROM worklog as S JOIN (SELECT id FROM worklog WHERE ts>=? AND ts<? $log_filter 
ORDER BY id DESC 
LIMIT $displayed OFFSET $start) AS I ON S.id = I.id";

$params[]=$displayed;
$params[]=$start;

$userlog = get_records_sql($db_link, $sSQL, $params);

?>
<br>
<table class="data" width="90%">
	<tr align="center">
		<td class="data" width=150><b><?php echo WEB_log_time; ?></b></td>
		<td class="data"><b><?php echo WEB_log_manager; ?></b></td>
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
    if ($row['level'] == L_DEBUG) { $msg_level='DEBUG'; }
    if ($row['level'] == L_VERBOSE) { $msg_level='VERBOSE'; }
    print "<td class=\"data\">" . $msg_level . "</td>\n";
    $print_msg = expand_log_str($db_link, $row['message']);
    print "<td class=\"data\" align=left>" . $print_msg . "</td>\n";
    print "</tr>\n";
}
print "</table>\n";
print_navigation($page_url,$page,$displayed,$count_records,$total);
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/footer.php");
?>
