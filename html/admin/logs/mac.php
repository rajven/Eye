<?php
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/auth.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/languages/" . HTML_LANG . ".php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/header.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/cidrfilter.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/datetimefilter.php");

$f_mac = mac_dotted(getParam('mac', $page_url, ''));
$_SESSION[$page_url]['mac'] = $f_mac;

print_log_submenu($page_url);
?>
<div id="cont">
<br>
<form action="<?=$_SERVER['PHP_SELF']?>" method="post">
<?php print_date_fields($date1,$date2,$date_shift); ?>
<?php echo WEB_cell_mac; ?>:&nbsp<input type="text" name="mac" value="<?php echo mac_dotted($f_mac); ?>" pattern="^([0-9A-Fa-f]{2}[:-]){5}[0-9A-Fa-f]{2}|([0-9a-fA-F]{4}[\\.-][0-9a-fA-F]{4}[\\.-][0-9a-fA-F]{4})|[0-9A-Fa-f]{12}$" />
<?php print WEB_rows_at_page."&nbsp"; print_row_at_pages('rows',$displayed); ?>
<input type="submit" value="<?php echo WEB_btn_show; ?>">
</form>

<?php
$params = [$date1, $date2];
$conditions = [];
if (!empty($f_mac)) {
    $conditions[] = "mac = ?";
    $params[] = $f_mac;
}
$whereClause = !empty($conditions) ? ' AND ' . implode(' AND ', $conditions) : '';
$countSQL = "SELECT COUNT(*) FROM mac_history WHERE ts >= ? AND ts < ?" . $whereClause;
$count_records = (int)get_single_field($db_link, $countSQL, $params);
$total = ceil($count_records / $displayed);
$page = max(1, min($page, $total));
$start = ($page - 1) * $displayed;
print_navigation($page_url, $page, $displayed, $count_records, $total);

$dataParams = array_merge($params, [$displayed, $start]);

$sSQL = "
    SELECT * FROM mac_history 
    WHERE ts >= ? AND ts < ?" . $whereClause . "
    ORDER BY ts DESC 
    LIMIT ? OFFSET ?
";

$maclog = get_records_sql($db_link, $sSQL, $dataParams);
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

foreach ($maclog as $row) {
    print "<tr align=center class=\"tr1\" onmouseover=\"className='tr2'\" onmouseout=\"className='tr1'\">\n";
    print "<td class=\"data\">" . get_datetime_display($row['ts']) . "</td>\n";
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
print_navigation($page_url,$page,$displayed,$count_records,$total);
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/footer.php");
?>
