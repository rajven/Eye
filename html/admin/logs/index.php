<?php
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/auth.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/languages/" . HTML_LANG . ".php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/header.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/datetimefilter.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/loglevelfilter.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/logfilter.php");

if (isset($_POST['user_ip'])) { $fuser_ip = $_POST['user_ip']; }
if (isset($_GET['user_ip'])) { $fuser_ip = $_GET['user_ip']; }
if (!isset($fuser_ip) and isset($_SESSION[$page_url]['user_ip'])) { $fuser_ip=$_SESSION[$page_url]['user_ip']; }
if (!isset($fuser_ip)) { $fuser_ip=''; }
$_SESSION[$page_url]['user_ip']=$fuser_ip;

print_log_submenu($page_url);
?>
<div id="cont">
<br>
<form action="<?=$_SERVER['PHP_SELF']?>" method="post">
<?php print_date_fields($date1,$date2,$date_shift); ?>
<?php echo WEB_log_level_display; ?>:<?php print_loglevel_select('display_log_level',$display_log_level); ?>
<?php print WEB_rows_at_page."&nbsp"; print_row_at_pages('rows',$displayed); ?>
<input type="submit" value="<?php echo WEB_btn_show; ?>"><br><br>
<?php echo WEB_log_filter_source; ?>:&nbsp<input name="customer" value="<?php echo $fcustomer; ?>" /> &nbsp
<?php echo WEB_log_event; ?>:&nbsp<input name="message" value="<?php echo $fmessage; ?>" /> &nbsp
<?php echo WEB_msg_IP; ?>:&nbsp<input name="user_ip" value="<?php echo $fuser_ip; ?>" /><br>
</form>

<?php
$log_filter ='';

if ($display_log_level == L_ERROR) { $log_filter = " and level=". L_ERROR." "; }
if ($display_log_level == L_WARNING) { $log_filter = " and level<=".L_WARNING." "; }
if ($display_log_level == L_INFO) { $log_filter = " and level<=".L_INFO." "; }
if ($display_log_level == L_VERBOSE) { $log_filter = " and level<=".L_VERBOSE." "; }
if ($display_log_level == L_DEBUG) { $log_filter = ""; }

if (!empty($fcustomer)) { $log_filter = $log_filter." and customer LIKE '".$fcustomer."'"; }
if (!empty($fmessage)) { $log_filter = $log_filter." and message LIKE '".$fmessage."'"; }
if (!empty($fuser_ip)) { $log_filter = $log_filter." and ip LIKE '".$fuser_ip."'"; }

$countSQL="SELECT Count(*) FROM worklog WHERE ts>='$date1' AND ts<'$date2' $log_filter";
$count_records = get_single_field($db_link,$countSQL);
$total=ceil($count_records/$displayed);
if ($page>$total) { $page=$total; }
if ($page<1) { $page=1; }
$start = ($page * $displayed) - $displayed; 
print_navigation($page_url,$page,$displayed,$count_records,$total);

#speedup paging
$sSQL = "SELECT * FROM (SELECT * FROM worklog WHERE ts>='$date1' AND ts<'$date2' $log_filter ) AS W ORDER BY ts DESC LIMIT $displayed OFFSET $start";

?>
<br>

<table class="data">
<tr align="center">
	<td class="data" width=150><b><?php echo WEB_log_time; ?></b></td>
	<td class="data"><b><?php echo WEB_log_filter_source; ?></b></td>
	<td class="data"><b><?php echo WEB_msg_IP; ?></b></td>
	<td class="data"><b><?php echo WEB_log_level; ?></b></td>
	<td class="data"><b><?php echo WEB_log_event; ?></b></td>
</tr>

<?php
$userlog = get_records_sql($db_link, $sSQL);

foreach ($userlog as $row) {
    print "<tr align=center class=\"tr1\" onmouseover=\"className='tr2'\" onmouseout=\"className='tr1'\">\n";
    print "<td class=\"data\">" . $row['ts'] . "</td>\n";
    print "<td class=\"data\">" . $row['customer'] . "</td>\n";
    $msg_level = 'INFO';
    if ($row['level'] == L_ERROR) { $msg_level='ERROR'; }
    if ($row['level'] == L_WARNING) { $msg_level='WARNING'; }
    if ($row['level'] == L_DEBUG) { $msg_level='DEBUG'; }
    if ($row['level'] == L_VERBOSE) { $msg_level='VERBOSE'; }
    print "<td class=\"data\">" . $row['ip'] . "</td>\n";
    print "<td class=\"data\">" . $msg_level . "</td>\n";
    $print_msg = expand_log_str($db_link, $row['message']);
    print "<td class=\"data\" align=left>" . $print_msg . "</td>\n";
    print "</tr>\n";
}
print "</table>\n";
print_navigation($page_url,$page,$displayed,$count_records,$total);
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/footer.php");
?>
