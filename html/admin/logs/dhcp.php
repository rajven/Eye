<?php
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/auth.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/languages/" . $language . ".php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/header.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/datefilter.php");

if (isset($_POST['dhcp_show'])) { $f_dhcp = $_POST['dhcp_show']; }
    else {
    if (isset($_SESSION[$page_url]['f_dhcp'])) { $f_dhcp=$_SESSION[$page_url]['f_dhcp']; } else { $f_dhcp = 'all'; }
    }

$_SESSION[$page_url]['f_dhcp']=$f_dhcp;

$dhcp_where = '';
if ($f_dhcp != 'all') { $dhcp_where = " and action='$f_dhcp' "; }
print_log_submenu($page_url);
?>

<div id="cont">
<br>
<form action="<?=$_SERVER['PHP_SELF']?>" method="post">
  Начало:&nbsp<input type="date" name="date_start" value="<?php echo $date1; ?>" />
  Конец:&nbsp<input type="date" name="date_stop" value="<?php echo $date2; ?>" />
  Тип события:&nbsp<?php print_dhcp_select('dhcp_show', $f_dhcp); ?>
  Отображать:<?php print_row_at_pages('rows',$displayed); ?>
  <input type="submit" value="OK">
</form>

<?php
$countSQL="SELECT Count(*) FROM dhcp_log WHERE date(timestamp)>='$date1' AND date(timestamp)<'$date2' $dhcp_where";
$res = mysqli_query($db_link, $countSQL);
$count_records = mysqli_fetch_array($res);
$total=ceil($count_records[0]/$displayed);
if ($page>$total) { $page=$total; }
if ($page<1) { $page=1; }
$start = ($page * $displayed) - $displayed; 
print_navigation($page_url,$page,$displayed,$count_records[0],$total);
?>
<br>
<table class="data" width="900">
<tr align="center">
	<td class="data" width=150><b>Время</b></td>
	<td class="data"><b>Тип</b></td>
	<td class="data"><b>Mac</b></td>
	<td class="data"><b>IP</b></td>
</tr>

<?php
#speedup dhcp log paging
$sSQL = "SELECT timestamp,mac,ip,action,auth_id FROM dhcp_log as D JOIN (SELECT id FROM dhcp_log WHERE date(timestamp)>='$date1' and date(timestamp)<'$date2' $dhcp_where ORDER BY timestamp DESC LIMIT $start,$displayed) AS I ON D.id = I.id";
$userlog = get_records_sql($db_link, $sSQL);

foreach ($userlog as $row) {
    if ($row['action'] == "old") { $row['action'] = "Обновление аренды: "; }
    if ($row['action'] == "add") { $row['action'] = "Аренда адреса: "; }
    if ($row['action'] == "del") { $row['action'] = "Освобождение адреса: "; }
    $l_msg = $row['action'] . " " . $row['mac'] . " " . $row['ip'];
    print "<tr align=center align=center class=\"tr1\" onmouseover=\"className='tr2'\" onmouseout=\"className='tr1'\">\n";
    print "<td class=\"data\">" . $row['timestamp'] . "</td>\n";
    print "<td class=\"data\">" . $row['action'] . "</td>\n";
    print "<td class=\"data\">" . $row['mac'] . "</td>\n";
    if (isset($row['auth_id']) and $row['auth_id'] > 0) {
	print "<td class=\"data\"><a href=/admin/users/editauth.php?id=".$row['auth_id'].">" . $row['ip'] . "</a></td>\n"; 
	} else {
	print "<td class=\"data\">" . $row['ip'] . "</td>\n"; 
	}
    print "</tr>\n";
    }
print "</table>\n";
print_navigation($page_url,$page,$displayed,$count_records[0],$total);
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/footer.php");
?>
