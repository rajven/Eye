<?php
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/auth.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/languages/" . HTML_LANG . ".php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/header.php");
$default_date_shift='d';
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/datefilter.php");

if (isset($_POST['dhcp_show'])) { $f_dhcp = $_POST['dhcp_show']; }
    else {
    if (isset($_SESSION[$page_url]['f_dhcp'])) { $f_dhcp=$_SESSION[$page_url]['f_dhcp']; } else { $f_dhcp = 'all'; }
    }

if (isset($_POST['ip'])) { $f_ip = $_POST['ip']; }
if (!isset($f_ip) and isset($_SESSION[$page_url]['ip'])) { $f_ip=$_SESSION[$page_url]['ip']; }
if (!isset($f_ip)) { $f_ip=''; }

$_SESSION[$page_url]['f_dhcp']=$f_dhcp;
$_SESSION[$page_url]['ip']=$f_ip;

$dhcp_where = '';
if ($f_dhcp != 'all') { $dhcp_where = " and action='$f_dhcp' "; }

if (!empty($f_ip)) {
    if (checkValidIp($f_ip)) { 
        $dhcp_where = " and ip_int=inet_aton('" . $f_ip . "') "; 
        } else {
        if (checkValidMac($f_ip)) { 
            $dhcp_where = " and mac='" . mac_dotted($f_ip) . "'  "; 
            } else { $dhcp_where = " and dhcp_hostname like '".$f_ip."%'"; }
        }
    }

print_log_submenu($page_url);

?>

<div id="cont">
<br>
<form action="<?=$_SERVER['PHP_SELF']?>" method="post">
  <?php echo WEB_log_start_date; ?>:&nbsp<input type="date" name="date_start" value="<?php echo $date1; ?>" />
  <?php echo WEB_log_stop_date; ?>:&nbsp<input type="date" name="date_stop" value="<?php echo $date2; ?>" />
  <?php echo WEB_log_event_type; ?>:&nbsp<?php print_dhcp_select('dhcp_show', $f_dhcp); ?>
  <?php echo WEB_log_select_ip_mac; ?>:&nbsp<input type="text" name="ip" value="<?php echo $f_ip; ?>" />
  <?php print WEB_rows_at_page."&nbsp"; print_row_at_pages('rows',$displayed); ?>
  <input type="submit" value="<?php echo WEB_btn_show; ?>">
</form>

<?php
$countSQL="SELECT Count(*) FROM dhcp_log WHERE `timestamp`>='$date1' AND `timestamp`<'$date2' $dhcp_where";
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
	<td class="data" width=150><b><?php echo WEB_log_time; ?></b></td>
	<td class="data"><b><?php echo WEB_cell_type; ?></b></td>
	<td class="data"><b><?php echo WEB_cell_mac; ?></b></td>
	<td class="data"><b><?php echo WEB_cell_ip; ?></b></td>
	<td class="data"><b><?php echo WEB_cell_dhcp_hostname; ?></b></td>
</tr>

<?php

#speedup dhcp log paging
$sSQL = "SELECT * FROM dhcp_log as D JOIN (SELECT id FROM dhcp_log WHERE `timestamp`>='$date1' and `timestamp`<'$date2' $dhcp_where ORDER BY `id` DESC LIMIT $start,$displayed) AS I ON D.id = I.id";
$userlog = get_records_sql($db_link, $sSQL);

foreach ($userlog as $row) {
    if ($row['action'] == "old") { $row['action'] = WEB_log_dhcp_old.": "; }
    if ($row['action'] == "add") { $row['action'] = WEB_log_dhcp_add.": "; }
    if ($row['action'] == "del") { $row['action'] = WEB_log_dhcp_del.": "; }
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
	print "<td class=\"data\">" . $row['dhcp_hostname'] . "</td>\n"; 
    print "</tr>\n";
    }
print "</table>\n";
print_navigation($page_url,$page,$displayed,$count_records[0],$total);
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/footer.php");
?>
