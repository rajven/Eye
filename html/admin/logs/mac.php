<?php
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/auth.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/languages/" . HTML_LANG . ".php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/header.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/cidrfilter.php");
$default_date_shift='m';
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/datefilter.php");

if (isset($_POST['mac'])) { $f_mac = mac_simplify($_POST['mac']); }
if (isset($_GET['mac'])) { $f_mac = mac_simplify($_GET['mac']); }
if (!isset($f_mac) and isset($_SESSION[$page_url]['mac'])) { $f_mac=$_SESSION[$page_url]['mac']; }
if (!isset($f_mac)) { $f_mac=''; }

$_SESSION[$page_url]['mac']=$f_mac;

$mac_where = '';
if (!empty($f_mac)) { $mac_where = " and mac='$f_mac' "; }

print_log_submenu($page_url);
?>
<div id="cont">
<br>
<form action="<?=$_SERVER['PHP_SELF']?>" method="post">
<?php echo WEB_log_start_date; ?>:&nbsp<input type="date" name="date_start" value="<?php echo $date1; ?>" />
<?php echo WEB_log_stop_date; ?>:&nbsp<input type="date"	name="date_stop" value="<?php echo $date2; ?>" />
<?php echo WEB_cell_mac; ?>:&nbsp<input type="text" name="mac" value="<?php echo mac_dotted($f_mac); ?>" pattern="^([0-9A-Fa-f]{2}[:-]){5}[0-9A-Fa-f]{2}|([0-9a-fA-F]{4}[\\.-][0-9a-fA-F]{4}[\\.-][0-9a-fA-F]{4})|[0-9A-Fa-f]{12}$" />
<?php print WEB_rows_at_page."&nbsp"; print_row_at_pages('rows',$displayed); ?>
<input type="submit" value="<?php echo WEB_btn_show; ?>">
</form>

<?php
$countSQL="SELECT Count(*) FROM mac_history WHERE `timestamp`>='$date1' AND `timestamp`<'$date2' $mac_where ORDER BY id DESC";
$res = mysqli_query($db_link, $countSQL);
$count_records = mysqli_fetch_array($res);

$total=ceil($count_records[0]/$displayed);
if ($page>$total) { $page=$total; }
if ($page<1) { $page=1; }
$start = ($page * $displayed) - $displayed; 
print_navigation($page_url,$page,$displayed,$count_records[0],$total);
?>
<br>
<table class="data" width="850">
<tr align="center">
	<td class="data" width=150><b><?php echo WEB_log_time; ?></b></td>
	<td class="data"><b><?php echo WEB_cell_mac; ?></b></td>
	<td class="data"><b><?php echo WEB_cell_connection; ?></b></td>
	<td class="data"><b><?php echo WEB_cell_ip; ?></b></td>
</tr>

<?php

$sSQL = "SELECT * FROM mac_history WHERE `timestamp`>='$date1' AND `timestamp`<'$date2' $mac_where ORDER BY `timestamp` DESC LIMIT $start,$displayed";
$maclog = get_records_sql($db_link, $sSQL);

foreach ($maclog as $row) {
    print "<tr align=center class=\"tr1\" onmouseover=\"className='tr2'\" onmouseout=\"className='tr1'\">\n";
    print "<td class=\"data\">" . $row['timestamp'] . "</td>\n";
    print "<td class=\"data\">" . expand_mac($db_link,mac_dotted($row['mac'])) . "</td>\n";
    print "<td class=\"data\">" . get_port($db_link, $row['port_id']) . "</td>\n";
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
