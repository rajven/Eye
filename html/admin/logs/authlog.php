<?php
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/auth.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/languages/" . $language . ".php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/header.php");
$default_date_shift='m';
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/datefilter.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/loglevelfilter.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/logfilter.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/authidfilter.php");
if (!isset($auth_id)) { header('Location: /admin/logs/index.php', true, 301); exit; }
?>
<div id="cont">
<br>
<form action="<?=$_SERVER['PHP_SELF']?>" method="post">
<input name="auth_id" value="<?php print $auth_id; ?>" hidden=true>
Начало:<input type="date" name="date_start" value="<?php echo $date1; ?>" />
Конец:<input type="date" name="date_stop" value="<?php echo $date2; ?>" />
Отображать:<?php print_row_at_pages('rows',$displayed); ?>
Уровень логов:<?php print_loglevel_select('log_level',$log_level); ?>
<input type="submit" value="OK"><br><br>
Фильтр Источник:<input name="customer" value="<?php echo $fcustomer; ?>" />
Сообщение:<input name="message" value="<?php echo $fmessage; ?>" />
</form>

<?php
$log_filter ='';

global $L_INFO;
global $L_ERROR;
global $L_VERBOSE;
global $L_DEBUG;

if ($log_level == $L_INFO) { $log_filter = " and `level`=$L_INFO "; }
if ($log_level == $L_ERROR) { $log_filter = " and (`level`=$L_INFO or `level`=$L_ERROR) "; }
if ($log_level == $L_VERBOSE) { $log_filter = " and (`level`=$L_INFO or `level`=$L_ERROR or `level`=$L_VERBOSE) "; }
if ($log_level == $L_DEBUG) { $log_filter = ""; }

if (isset($log_filter)) { $log_filter = $log_filter." and auth_id=".$auth_id; } else { $log_filter = "auth_id=".$auth_id; }
if (isset($fcustomer)) { $log_filter = $log_filter." and customer LIKE '%".$fcustomer."%'"; }
if (isset($fmessage)) { $log_filter = $log_filter." and message LIKE '%".$fmessage."%'"; }

$countSQL="SELECT Count(*) FROM syslog WHERE `timestamp`>='$date1' AND `timestamp`<'$date2' $log_filter";
$res = mysqli_query($db_link, $countSQL);
$count_records = mysqli_fetch_array($res);

$total=ceil($count_records[0]/$displayed);
if ($page>$total) { $page=$total; }
if ($page<1) { $page=1; }
$start = ($page * $displayed) - $displayed; 
print_navigation($page_url,$page,$displayed,$count_records[0],$total);
?>
<br>
<table class="data" width="90%">
	<tr align="center">
		<td class="data" width=150><b>Время</b></td>
		<td class="data"><b>Менеджер</b></td>
		<td class="data"><b>Level</b></td>
		<td class="data"><b>Лог</b></td>
	</tr>
<?php
#speedup paging
$sSQL = "SELECT timestamp,customer,message,level FROM syslog as S JOIN (SELECT id FROM syslog WHERE `timestamp`>='$date1' AND `timestamp`<'$date2' $log_filter ORDER BY id DESC LIMIT $start,$displayed) AS I ON S.id = I.id";
$userlog = get_records_sql($db_link, $sSQL);
foreach ($userlog as $row) {
    print "<tr align=center align=center class=\"tr1\" onmouseover=\"className='tr2'\" onmouseout=\"className='tr1'\">\n";
    print "<td class=\"data\">" . $row['timestamp'] . "</td>\n";
    print "<td class=\"data\">" . $row['customer'] . "</td>\n";
    $msg_level = 'INFO';
    if ($row['level'] == $L_ERROR) { $msg_level='ERROR'; }
    if ($row['level'] == $L_DEBUG) { $msg_level='DEBUG'; }
    if ($row['level'] == $L_VERBOSE) { $msg_level='VERBOSE'; }
    print "<td class=\"data\">" . $msg_level . "</td>\n";
    $print_msg = expand_log_str($db_link, $row['message']);
    print "<td class=\"data\" align=left>" . $print_msg . "</td>\n";
    print "</tr>\n";
}
print "</table>\n";
print_navigation($page_url,$page,$displayed,$count_records[0],$total);
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/footer.php");
?>
