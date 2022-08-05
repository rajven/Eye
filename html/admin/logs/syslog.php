<?php
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/auth.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/languages/" . $language . ".php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/header.php");
$default_date_shift='d';
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/datefilter.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/logfilter.php");

if (isset($_POST['device_show'])) { $f_id = $_POST['device_show']*1; }
if (isset($_GET['device_show'])) { $f_id = $_GET['device_show']*1; }

if (!isset($f_id) and isset($_SESSION[$page_url]['device_show'])) { $f_id=$_SESSION[$page_url]['device_show']*1; }
if (!isset($f_id)) { $f_id=0; }

$_SESSION[$page_url]['device_show']=$f_id;

print_log_submenu($page_url);

$log_filter = "";

if ($f_id>0) {
    $dev_ips=get_device_ips($db_link,$f_id);
    $log_filter=' and `ip` IN (';
    foreach ($dev_ips as $index => $ip) {
	$log_filter=$log_filter."'".$ip."',";
        }
    $log_filter = preg_replace('/\,$/', '',$log_filter);
    $log_filter = $log_filter .")";
    }

?>
<div id="cont">
<br>
<form action="<?=$_SERVER['PHP_SELF']?>" method="post">
Начало:&nbsp<input type="date" name="date_start" value="<?php echo $date1; ?>" />
Конец:&nbsp<input type="date" name="date_stop" value="<?php echo $date2; ?>" />
Отчёт по устройству&nbsp <?php print_device_select($db_link, "device_show", $f_id); ?>
Отображать:<?php print_row_at_pages('rows',$displayed); ?>
<input type="submit" value="OK"><br><br>
Фильтр - сообщение:<input name="message" value="<?php echo $fmessage; ?>" />
</form>

<?php

if (isset($fmessage)) {
    if (isset($log_filter)) { $log_filter = $log_filter." and message LIKE '%".$fmessage."%'"; } else { $log_filter = " message LIKE '%".$fmessage."%'"; }
    }

$countSQL="SELECT Count(*) FROM `remote_syslog` WHERE `date`>='$date1' AND `date`<'$date2' $log_filter";
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
			<td class="data" width=150><b>Дата</b></td>
			<td class="data"><b>IP </b></td>
			<td class="data"><b>Сообщение</b></td>
		</tr>

<?php

#speedup pageing
$sSQL = "SELECT 
`date`, `ip`, `message` 
FROM `remote_syslog` as R 
JOIN 
(SELECT id FROM `remote_syslog` WHERE `date`>='$date1' AND `date`<'$date2' $log_filter ORDER BY `id` DESC LIMIT $start,$displayed) as I 
ON R.id = I.id";
$syslog = get_records_sql($db_link, $sSQL);
if (!empty($syslog)) {
    foreach ($syslog as $row) {
        print "<tr align=center align=center class=\"tr1\" onmouseover=\"className='tr2'\" onmouseout=\"className='tr1'\">\n";
        print "<td class=\"data\">" . $row['date'] . "</td>\n";
        print "<td class=\"data\">" . $row['ip'] . "</td>\n";
        print "<td class=\"data\">" . $row['message'] . "</td>\n";
        print "</tr>\n";
        }
    }
print "</table>\n";
print_navigation($page_url,$page,$displayed,$count_records[0],$total);
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/footer.php");
?>
