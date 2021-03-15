<?php
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/auth.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/languages/" . $language . ".php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/header.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/datefilter.php");

if (isset($_POST['device_show'])) { $f_id = $_POST['device_show'] * 1; }
if (isset($_GET['device_show'])) { $f_id = $_GET['device_show'] * 1; }
if (!isset($f_id) and isset($_SESSION[$page_url]['device_show'])) { $f_id=$_SESSION[$page_url]['device_show']; }
if (!isset($f_id)) { $f_id=0; }

$_SESSION[$page_url]['device_show']=$f_id;
print_log_submenu($page_url);
$where_dev = "";
if ($f_id > 0) { $where_dev = " and D.id=$f_id "; }
?>

<div id="cont">
<br>
	<form action="<?=$_SERVER['PHP_SELF']?>" method="post">
  Отчёт по свичу&nbsp<?php print_device_select($db_link, "device_show", $f_id); ?>
  Начало:&nbsp<input type="date" name="date_start" value="<?php echo $date1; ?>" />
  Конец:&nbsp<input type="date" name="date_stop" value="<?php echo $date2; ?>" />
  Отображать:<?php print_row_at_pages('rows',$displayed); ?>
  <input type="submit" value="OK">
</form>

<?php
$countSQL="SELECT Count(*) FROM Unknown_mac AS U, devices AS D, device_ports AS DP  WHERE U.device_id = D.id  AND U.port_id = DP.id AND U.timestamp>='$date1' AND U.timestamp<'$date2' $where_dev";
$res = mysqli_query($db_link, $countSQL);
$count_records = mysqli_fetch_array($res);
$total=ceil($count_records[0]/$displayed);
if ($page>$total) { $page=$total; }
if ($page<1) { $page=1; }
$start = ($page * $displayed) - $displayed; 
print_navigation($page_url,$page,$displayed,$count_records[0],$total);
?>
<br>
<table class="data" width="750">
<tr align="center">
	<td class="data" width=110><b>Switch</b></td>
	<td class="data"><b>Port</b></td>
	<td class="data"><b>Mac</b></td>
	<td class="data"><b>Last found</b></td>
</tr>
<?php

$sSQL = "SELECT U.mac, U.timestamp, DP.port, D.device_name FROM Unknown_mac AS U, devices AS D, device_ports AS DP  WHERE U.device_id = D.id  AND U.port_id = DP.id AND U.timestamp>='$date1' AND U.timestamp<'$date2' $where_dev ORDER BY U.mac LIMIT $start,$displayed";
$maclog = get_records_sql($db_link, $sSQL);
foreach ($maclog as $row) {
    print "<tr align=center align=center class=\"tr1\" onmouseover=\"className='tr2'\" onmouseout=\"className='tr1'\">\n";
    print "<td class=\"data\">" . $row['device_name'] . "</td>\n";
    print "<td class=\"data\">" . $row['port'] . "</td>\n";
    print "<td class=\"data\"><a href=/admin/logs/mac.php?mac=" . mac_simplify($row['mac']) . ">" . mac_dotted($row['mac']) . "</a></td>\n";
    print "<td class=\"data\">" . $row['timestamp'] . "</td>\n";
    print "</tr>\n";
}
print "</table>\n";
print_navigation($page_url,$page,$displayed,$count_records[0],$total);
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/footer.php");
?>
