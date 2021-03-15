<?php
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/auth.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/languages/" . $language . ".php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/header.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/idfilter.php");
$default_date_shift='d';
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/datefilter.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/oufilter.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/gatefilter.php");

$user=get_record_sql($db_link,'SELECT * FROM User_list WHERE id='.$id);

?>

<div id="cont">
<b><?php print "Трафик пользователя <a href=../users/edituser.php?id=$id>" . $user['login'] . "</a>"; ?></b>
<br>
<form action="<?php print $_SERVER['PHP_SELF']; ?>" method="post">
<input type="hidden" name="id" value=<?php echo $id; ?>>
Начало:&nbsp<input type="date" name="date_start" value="<?php print $date1; ?>" />
Конец:&nbsp<input type="date" name="date_stop" value="<?php print $date2; ?>" />
Шлюз:&nbsp <?php print_gateway_select($db_link, 'gateway', $rgateway); ?>
<input type="submit" value="OK">
</form>

<br>
<table class="data" width='100%'>
<tr align="center">
<td class="data"><b><?php echo userinfo2;  ?></b></td>
<td class="data"><b> Gateway </b></td>
<td class="data"><b><?php echo userinfo10; ?></b></td>
<td class="data"><b><?php echo userinfo7; ?></b></td>
<td class="data"><b><?php echo userinfo8; ?></b></td>
</tr>

<?php
$gateway_list = get_gateways($db_link);

$gateway_filter='';
if (!empty($rgateway) and $rgateway>0) { $gateway_filter="(User_stats.router_id=$rgateway) AND"; }

$sSQL = "SELECT id,ip,comments FROM User_auth WHERE (User_auth.user_id=$id) Order by IP";
$usersip = mysqli_query($db_link, $sSQL);

$ipcount = 0;
$itog_in = 0;
$itog_out = 0;

while ($row = mysqli_fetch_array($usersip)) {

    $fid = $row["id"];
    $fip = $row["ip"];
    $fcomm = $row["comments"];

    $sSQL = "SELECT SUM(byte_in)+SUM(byte_out) as t_sum FROM User_stats 
    WHERE $gateway_filter User_stats.timestamp>='$date1' AND User_stats.timestamp<'$date2'AND auth_id=$fid";

    $day_summary = get_record_sql($db_link, $sSQL);
    if (!empty($day_summary)) { $summ = $day_summary['t_sum']; } else { $summ = 0; }

    if ($summ > 0) {
        $ipcount ++;
        print "<tr align=center align=center class=\"tr1\" onmouseover=\"className='tr2'\" onmouseout=\"className='tr1'\">\n";
        print "<td class=\"data\" ><b><a href=/admin/users/editauth.php?id=$fid>$fip</a></b></td>\n";
        print "<td class=\"data\" colspan=2>$fcomm</td>\n";
        print "<td class=\"data\" ><a href=/admin/reports/userdaydetail.php?id=$fid&date_start=$date1&date_stop=$date2>TOP 10</a></td>\n";
        print "<td class=\"data\" ><a href=/admin/reports/userdaydetaillog.php?id=$fid&date_start=$date1&date_stop=$date2>Детализация</a></td>\n";
        print "</tr>\n";

	$display_date_format='%Y-%m-%d %H';
	if ($days_shift <=1) { $display_date_format='%Y-%m-%d %H'; }
	if ($days_shift >1 and $days_shift <=30) { $display_date_format='%Y-%m-%d'; }
	if ($days_shift >30 and $days_shift <=730) { $display_date_format='%Y-%m'; }
	if ($days_shift >730) { $display_date_format='%Y'; }

        $sSQL = "SELECT User_stats.router_id,DATE_FORMAT(User_stats.timestamp,'$display_date_format') as tHour,SUM(byte_in),SUM(byte_out) 
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

        $userdata = mysqli_query($db_link, $sSQL);

        $sum_in = 0;
        $sum_out = 0;

        while (list ($u_router_id, $udata, $uin, $uout) = mysqli_fetch_array($userdata)) {
            print "<tr align=center align=center class=\"tr1\" onmouseover=\"className='tr2'\" onmouseout=\"className='tr1'\">\n";
            print "<td class=\"data\"> </td>\n";
            print "<td class=\"data\">$gateway_list[$u_router_id]</td>\n";
            print "<td class=\"data\">" . $udata . "</td>\n";
            print "<td class=\"data\">" . fbytes($uin) . "</td>\n";
            print "<td class=\"data\">" . fbytes($uout) . "</td>\n";
            print "</tr>\n";
            $sum_in += $uin;
            $sum_out += $uout;
        }
        print "<tr align=center align=center class=\"tr1\" onmouseover=\"className='tr2'\" onmouseout=\"className='tr1'\">\n";
        print "<td class=\"data\"><b>" . userinfo9 . "</b></td>\n";
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
    print "<tr align=center align=center class=\"tr1\" onmouseover=\"className='tr2'\" onmouseout=\"className='tr1'\">\n";
    print "<td class=\"data\"><b> Итого </b></td>\n";
    print "<td class=\"data\"><b> </b></td>\n";
    print "<td class=\"data\"><b>" . fbytes($itog_in) . "</b></td>\n";
    print "<td class=\"data\"><b>" . fbytes($itog_out) . "</b></td>\n";
    print "</tr>\n";
}
?>
</table>
<?
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/footer.php");
?>