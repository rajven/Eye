<?php
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/auth.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/languages/" . HTML_LANG . ".php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/header.php");
$default_date_shift='d';
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/datefilter.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/loglevelfilter.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/logfilter.php");
print_log_submenu($page_url);
?>
<div id="cont">
<br>
<form action="<?=$_SERVER['PHP_SELF']?>" method="post">
Начало:<input type="date" name="date_start" value="<?php echo $date1; ?>" />
Конец:<input type="date" name="date_stop" value="<?php echo $date2; ?>" />
Отображать:<?php print_row_at_pages('rows',$displayed); ?>
Уровень логов:<?php print_loglevel_select('display_log_level',$display_log_level); ?>
<input type="submit" value="OK"><br><br>
Фильтр Источник:<input name="customer" value="<?php echo $fcustomer; ?>" />
Сообщение:<input name="message" value="<?php echo $fmessage; ?>" />
</form>

<?php
$log_filter ='';

if ($display_log_level == L_ERROR) { $log_filter = " and `level`=". L_ERROR." "; }
if ($display_log_level == L_WARNING) { $log_filter = " and `level`<=".L_WARNING." "; }
if ($display_log_level == L_INFO) { $log_filter = " and `level`<=".L_INFO." "; }
if ($display_log_level == L_VERBOSE) { $log_filter = " and `level`<=".L_VERBOSE." "; }
if ($display_log_level == L_DEBUG) { $log_filter = ""; }

if (!empty($fcustomer)) { $log_filter = $log_filter." and customer LIKE '%".$fcustomer."%'"; }
if (!empty($fmessage)) { $log_filter = $log_filter." and message LIKE '%".$fmessage."%'"; }

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

<table class="data">
<tr align="center">
	<td class="data" width=150><b>Время</b></td>
	<td class="data"><b>Менеджер/Источник</b></td>
	<td class="data"><b>Level</b></td>
	<td class="data"><b>Лог</b></td>
</tr>

<?php
#speedup paging
$sSQL = "SELECT `timestamp`,customer,message,level FROM syslog as S JOIN (SELECT id FROM syslog WHERE `timestamp`>='$date1' AND `timestamp`<'$date2' $log_filter ORDER BY id DESC LIMIT $start,$displayed) AS I ON S.id = I.id";

$userlog = get_records_sql($db_link, $sSQL);
foreach ($userlog as $row) {
    print "<tr align=center align=center class=\"tr1\" onmouseover=\"className='tr2'\" onmouseout=\"className='tr1'\">\n";
    print "<td class=\"data\">" . $row['timestamp'] . "</td>\n";
    print "<td class=\"data\">" . $row['customer'] . "</td>\n";
    $msg_level = 'INFO';
    if ($row['level'] == L_ERROR) { $msg_level='ERROR'; }
    if ($row['level'] == L_WARNING) { $msg_level='WARNING'; }
    if ($row['level'] == L_DEBUG) { $msg_level='DEBUG'; }
    if ($row['level'] == L_VERBOSE) { $msg_level='VERBOSE'; }
    print "<td class=\"data\">" . $msg_level . "</td>\n";
    $print_msg = expand_log_str($db_link, $row['message']);
    print "<td class=\"data\" align=left>" . $print_msg . "</td>\n";
    print "</tr>\n";
}
print "</table>\n";
print_navigation($page_url,$page,$displayed,$count_records[0],$total);
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/footer.php");
?>
