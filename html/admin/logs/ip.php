<?php
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/auth.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/languages/" . HTML_LANG . ".php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/header.php");
$default_date_shift='m';
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/datefilter.php");

if (isset($_POST['ip'])) { $f_ip = $_POST['ip']; }
if (isset($_GET['ip'])) { $f_ip = $_GET['ip']; }
if (!isset($f_ip) and isset($_SESSION[$page_url]['ip'])) { $f_ip=$_SESSION[$page_url]['ip']; }
if (!isset($f_ip)) { $f_ip=''; }

$_SESSION[$page_url]['ip']=$f_ip;

print_log_submenu($page_url);

$ip_where = '';
if (!empty($f_ip)) {
    if (checkValidIp($f_ip)) { $ip_where = " and ip_int=inet_aton('" . $f_ip . "') "; }
    if (checkValidMac($f_ip)) { $ip_where = " and mac='" . mac_dotted($f_ip) . "'  "; }
    }
?>

<div id="cont">
<br>
<?php echo WEB_log_mac_history_hint; ?>
<form action="<?=$_SERVER['PHP_SELF']?>" method="post">
<?php echo WEB_log_start_date; ?>:&nbsp<input type="date" name="date_start" value="<?php echo $date1; ?>" />
<?php echo WEB_log_stop_date; ?>:&nbsp<input type="date"	name="date_stop" value="<?php echo $date2; ?>" />
<?php echo WEB_log_select_ip_mac; ?>:&nbsp<input type="text" name="ip" value="<?php echo $f_ip; ?>" pattern="^((([0-9]|[1-9][0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5])\.){3}([0-9]|[1-9][0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5])|([0-9A-Fa-f]{2}[:-]){5}[0-9A-Fa-f]{2}|([0-9a-fA-F]{4}[\\.-][0-9a-fA-F]{4}[\\.-][0-9a-fA-F]{4})|[0-9A-Fa-f]{12})$"/>
<?php print WEB_rows_at_page."&nbsp"; print_row_at_pages('rows',$displayed); ?>
<input type="submit" value="<?php echo WEB_btn_show; ?>">
</form>

<?php
$countSQL="SELECT Count(*) FROM User_auth WHERE `timestamp`>='$date1' AND `timestamp`<'$date2' $ip_where";
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
				<td class="data"><b>id</b></td>
				<td class="data" width=150><b><?php echo WEB_cell_created; ?></b></td>
				<td class="data" width=150><b><?php echo WEB_cell_last_found; ?></b></td>
				<td class="data"><b><?php echo WEB_cell_ip; ?></b></td>
				<td class="data"><b><?php echo WEB_cell_mac; ?></b></td>
				<td class="data"><b><?php echo WEB_cell_dhcp_hostname; ?></b></td>
				<td class="data"><b><?php echo WEB_cell_dns_name; ?></b></td>
		</tr>

<?php

$sSQL = "SELECT * FROM User_auth WHERE `timestamp`>='$date1' AND `timestamp`<'$date2' $ip_where ORDER BY id DESC LIMIT $start,$displayed";

$iplog = get_records_sql($db_link, $sSQL);
foreach ($iplog as $row) {
    print "<tr align=center align=center class=\"tr1\" onmouseover=\"className='tr2'\" onmouseout=\"className='tr1'\">\n";
    print "<td class=\"data\">" . $row['id'] . "</td>\n";
    print "<td class=\"data\">" . $row['timestamp'] . "</td>\n";
    print "<td class=\"data\">" . $row['last_found'] . "</td>\n";
    if (isset($row['id']) and $row['id'] > 0) {
        print "<td class=\"data\"><a href=/admin/users/editauth.php?id=".$row['id'].">" . $row['ip'] . "</a></td>\n";
    } else {
        print "<td class=\"data\">" . $row['ip'] . "</td>\n";
    }
    print "<td class=\"data\">" . expand_mac($db_link,mac_dotted($row['mac'])) . "</td>\n";
    print "<td class=\"data\">" . $row['dhcp_hostname'] . "</td>\n";
    print "<td class=\"data\">" . $row['dns_name'] . "</td>\n";
    print "</tr>\n";
}
print "</table>\n";
print_navigation($page_url,$page,$displayed,$count_records[0],$total);
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/footer.php");
?>
