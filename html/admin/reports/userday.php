<?php
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/auth.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/languages/" . HTML_LANG . ".php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/header.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/idfilter.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/datetimefilter.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/oufilter.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/gatefilter.php");

$user=get_record_sql($db_link,'SELECT * FROM User_list WHERE id='.$id);

?>

<div id="cont">
<b><?php print "Трафик пользователя <a href=../users/edituser.php?id=$id>" . $user['login'] . "</a>"; ?></b>
<br>
<form action="<?php print $_SERVER['PHP_SELF']; ?>" method="post">
<input type="hidden" name="id" value=<?php echo $id; ?>>
<?php print_date_fields($date1,$date2,$date_shift); ?>
<?php echo WEB_cell_gateway; ?>:&nbsp<?php print_gateway_select($db_link, 'gateway', $rgateway); ?>
<input type="submit" value="<?php echo WEB_btn_show; ?>">
</form>

<br>
<table class="data" width='100%'>
<tr align="center">
<td class="data"><b><?php print WEB_title_ip; ?></b></td>
<td class="data"><b><?php print WEB_cell_gateway; ?></b></td>
<td class="data"><b><?php print WEB_title_date; ?></b></td>
<td class="data"><b><?php print WEB_title_input; ?></b></td>
<td class="data"><b><?php print WEB_title_output; ?></b></td>
</tr>

<?php
$gateway_list = get_gateways($db_link);

$gateway_filter='';
if (!empty($rgateway) and $rgateway>0) { $gateway_filter="(User_stats.router_id=$rgateway) AND"; }

$sSQL = "SELECT id,ip,comments FROM User_auth WHERE (User_auth.user_id=$id) Order by IP";
$usersip = get_records_sql($db_link, $sSQL);

$ipcount = 0;
$itog_in = 0;
$itog_out = 0;

foreach ($usersip as $row) {
    $fid = $row["id"];
    $fip = $row["ip"];
    $fcomm = $row["comments"];

    $sSQL = "SELECT SUM(byte_in)+SUM(byte_out) as t_sum FROM User_stats 
    WHERE $gateway_filter User_stats.timestamp>='$date1' AND User_stats.timestamp<'$date2'AND auth_id=$fid";

    $day_summary = get_record_sql($db_link, $sSQL);
    if (!empty($day_summary)) { $summ = $day_summary['t_sum']; } else { $summ = 0; }

    if ($summ > 0) {
        $ipcount++;
        print "<tr align=center class=\"tr1\" onmouseover=\"className='tr2'\" onmouseout=\"className='tr1'\">\n";
        print "<td class=\"data\" ><b><a href=/admin/users/editauth.php?id=$fid>$fip</a></b></td>\n";
        print "<td class=\"data\" colspan=2>$fcomm</td>\n";
        print "<td class=\"data\" ><a href=/admin/reports/userdaydetail.php?id=$fid&date_start=$date1&date_stop=$date2>TOP 10</a></td>\n";
        print "<td class=\"data\" ><a href=/admin/reports/userdaydetaillog.php?id=$fid&date_start=$date1&date_stop=$date2>".WEB_report_detail."</a></td>\n";
        print "</tr>\n";

        $display_date_format='%Y-%m-%d %H';
        if ($days_shift <=1) { $display_date_format='%Y-%m-%d %H'; }
        if ($days_shift >1 and $days_shift <=30) { $display_date_format='%Y-%m-%d'; }
        if ($days_shift >30 and $days_shift <=730) { $display_date_format='%Y-%m'; }
        if ($days_shift >730) { $display_date_format='%Y'; }

        $sSQL = "SELECT User_stats.router_id, DATE_FORMAT(User_stats.timestamp,'$display_date_format') as tHour,
                SUM(byte_in) as byte_in_sum, SUM(byte_out) as byte_out_sum 
                FROM User_stats 
                WHERE User_stats.timestamp>='$date1' AND User_stats.timestamp<'$date2' and auth_id=$fid";
        if ($rgateway == 0) {
            $sSQL = $sSQL . " GROUP BY DATE_FORMAT(User_stats.timestamp,'$display_date_format'),User_stats.router_id 
            ORDER BY tHour,User_stats.router_id";
        } else {
            $sSQL = $sSQL . " and User_stats.router_id=$rgateway 
            GROUP BY DATE_FORMAT(User_stats.timestamp,'$display_date_format'),User_stats.router_id 
            ORDER BY tHour";
        }

        $userdata = get_records_sql($db_link, $sSQL);

        $sum_in = 0;
        $sum_out = 0;

        foreach ($userdata as $userrow) {
            print "<tr align=center class=\"tr1\" onmouseover=\"className='tr2'\" onmouseout=\"className='tr1'\">\n";
            print "<td class=\"data\"> </td>\n";
            print "<td class=\"data\">" . $gateway_list[$userrow['router_id']] . "</td>\n";
            print "<td class=\"data\">" . $userrow['tHour'] . "</td>\n";
            print "<td class=\"data\">" . fbytes($userrow['byte_in_sum']) . "</td>\n";
            print "<td class=\"data\">" . fbytes($userrow['byte_out_sum']) . "</td>\n";
            print "</tr>\n";
            $sum_in += $userrow['byte_in_sum'];
            $sum_out += $userrow['byte_out_sum'];
        }
        print "<tr align=center class=\"tr1\" onmouseover=\"className='tr2'\" onmouseout=\"className='tr1'\">\n";
        print "<td class=\"data\"><b>" . WEB_title_sum . "</b></td>\n";
        print "<td class=\"data\"><b> </b></td>\n";
        print "<td class=\"data\"><b> </b></td>\n";
        print "<td class=\"data\"><b>" . fbytes($sum_in) . "</b></td>\n";
        print "<td class=\"data\"><b>" . fbytes($sum_out) . "</b></td>\n";
        print "</tr>\n";
        $itog_in += $sum_in;
        $itog_out += $sum_out;
    }
}
if ($ipcount > 1) {
    print "<tr align=center class=\"tr1\" onmouseover=\"className='tr2'\" onmouseout=\"className='tr1'\">\n";
    print "<td class=\"data\"><b>".WEB_title_itog."</b></td>\n";
    print "<td class=\"data\"><b> </b></td>\n";
    print "<td class=\"data\"><b> </b></td>\n";
    print "<td class=\"data\"><b>" . fbytes($itog_in) . "</b></td>\n";
    print "<td class=\"data\"><b>" . fbytes($itog_out) . "</b></td>\n";
    print "</tr>\n";
}
?>
</table>
<?php
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/footer.php");
?>