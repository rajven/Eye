<?php
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/auth.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/languages/" . HTML_LANG . ".php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/header.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/datetimefilter.php");
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
    $log_filter=' and ip IN (';
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
<?php print_date_fields($date1,$date2,$date_shift); ?>
<?php echo WEB_log_report_by_device; ?>&nbsp <?php print_device_select($db_link, "device_show", $f_id); ?>
<?php print WEB_rows_at_page."&nbsp"; print_row_at_pages('rows',$displayed); ?>
<input type="submit" value="<?php echo WEB_btn_show; ?>"><br><br>
<?php echo WEB_log_filter_event; ?>:<input name="message" value="<?php echo $fmessage; ?>" />
</form>

<?php

if (!empty($fmessage)) { $log_filter .= " AND message LIKE '%" . addslashes($fmessage) . "%'"; }

$countSQL="SELECT Count(*) FROM remote_syslog WHERE date>='$date1' AND date<'$date2' $log_filter";
$count_records = get_single_field($db_link,$countSQL);
$total=ceil($count_records/$displayed);
if ($page>$total) { $page=$total; }
if ($page<1) { $page=1; }
$start = ($page * $displayed) - $displayed; 
print_navigation($page_url,$page,$displayed,$count_records,$total);
#speedup pageing
$sSQL = "SELECT * FROM (SELECT * FROM remote_syslog WHERE date>='$date1' AND date<'$date2' $log_filter) as R ORDER BY date DESC LIMIT $start,$displayed";
?>

<br>
<table class="data" width="90%">
		<tr align="center">
			<td class="data" width=150><b><?php echo WEB_date; ?></b></td>
			<td class="data"><b><?php echo WEB_cell_ip; ?></b></td>
			<td class="data"><b><?php echo WEB_log_event; ?></b></td>
		</tr>

<?php


$syslog = get_records_sql($db_link, $sSQL);
if (!empty($syslog)) {
    foreach ($syslog as $row) {
        print "<tr align=center class=\"tr1\" onmouseover=\"className='tr2'\" onmouseout=\"className='tr1'\">\n";
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
