<?php
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/auth.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/languages/" . $language . ".php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/header.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/idfilter.php");
$default_date_shift='d';
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/datefilter.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/oufilter.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/gatefilter.php");
$auth=get_record_sql($db_link,'SELECT * FROM User_auth WHERE id='.$id);
$user=get_record_sql($db_link,'SELECT * FROM User_list WHERE id='.$auth['user_id']);

?>
<div id="cont">
<b>
<?php
print "Трафик пользователя <a href=../users/edituser.php?id=".$auth['user_id'].">" . $user['login'] . "</a>"; 
print " для адреса <a href=../users/editauth.php?id=$id>".$auth['ip']."</a>";
?>
</b>
<br>
<form action="<?php print $_SERVER['PHP_SELF']; ?>" method="post">
<input type="hidden" name="id" value=<?php echo $id; ?>>
Начало:&nbsp<input type="date" name="date_start" value="<?php print $date1; ?>" />
Конец:&nbsp<input type="date" name="date_stop" value="<?php print $date2; ?>" />
Шлюз:&nbsp <?php print_gateway_select($db_link, 'gateway', $rgateway); ?>
<input type="submit" value="OK">
</form>
<br>
<table class="data" width=700>
<tr align="center">
<td class="data"><b> Gateway </b></td>
<td class="data"><b><?php print $title_date; ?></b></td>
<td class="data"><b><?php print $title_input; ?></b></td>
<td class="data"><b><?php print $title_output; ?></b></td>
<td class="data"><b><?php print $title_maxpktin; ?></b></td>
<td class="data"><b><?php print $title_maxpktout; ?></b></td>
</tr>
<?php

$gateway_list = get_gateways($db_link);
$gateway_filter='';
if (!empty($rgateway) and $rgateway>0) { $gateway_filter="(router_id=$rgateway) AND"; }

print "<tr align=center align=center class=\"tr1\" onmouseover=\"className='tr2'\" onmouseout=\"className='tr1'\">\n";
print "<td class=\"data\" colspan=2>".$auth['comments']."</td>\n";
print "<td class=\"data\" colspan=2><a href=/admin/reports/userdaydetail.php?id=$id&date_start=$date1&date_stop=$date2>TOP 10</a></td>\n";
print "<td class=\"data\" colspan=2><a href=/admin/reports/userdaydetaillog.php?id=$id&date_start=$date1&date_stop=$date2>Детализация</a></td>\n";
print "</tr>\n";

$display_date_format='%Y-%m-%d %H';

if ($days_shift <=1) { $display_date_format='%Y-%m-%d %H'; }
if ($days_shift >1 and $days_shift <=30) { $display_date_format='%Y-%m-%d'; }
if ($days_shift >30 and $days_shift <=730) { $display_date_format='%Y-%m'; }
if ($days_shift >730) { $display_date_format='%Y'; }

$sSQL = "SELECT router_id,DATE_FORMAT(`timestamp`,'$display_date_format') as tHour,SUM(`byte_in`),SUM(`byte_out`),MAX(ROUND(`pkt_in`/`step`)),MAX(ROUND(`pkt_out`/`step`))
FROM User_stats_full WHERE `timestamp`>='$date1' AND `timestamp`<'$date2' AND auth_id=$id";
if ($rgateway == 0) {
        $sSQL = $sSQL . " GROUP BY DATE_FORMAT(`timestamp`,'$display_date_format'),router_id ORDER BY tHour,router_id";
        } else {
        $sSQL = $sSQL . " AND router_id=$rgateway GROUP BY DATE_FORMAT(`timestamp`,'$display_date_format'),router_id ORDER BY tHour";
        }

$userdata = mysqli_query($db_link, $sSQL);
$sum_in = 0;
$sum_out = 0;
while (list ($u_router_id, $udata, $uin, $uout, $pin, $pout) = mysqli_fetch_array($userdata)) {
    print "<tr align=center align=center class=\"tr1\" onmouseover=\"className='tr2'\" onmouseout=\"className='tr1'\">\n";
    print "<td class=\"data\">$gateway_list[$u_router_id]</td>\n";
    print "<td class=\"data\">" . $udata . "</td>\n";
    print "<td class=\"data\">" . fbytes($uin) . "</td>\n";
    print "<td class=\"data\">" . fbytes($uout) . "</td>\n";
    print "<td class=\"data\">" . fpkts($pin) . "</td>\n";
    print "<td class=\"data\">" . fpkts($pout) . "</td>\n";
    print "</tr>\n";
    $sum_in += $uin;
    $sum_out += $uout;
}
print "<tr align=center align=center class=\"tr1\" onmouseover=\"className='tr2'\" onmouseout=\"className='tr1'\">\n";
print "<td class=\"data\"><b>" . userinfo9 . "</b></td>\n";
print "<td class=\"data\"><b> </b></td>\n";
print "<td class=\"data\"><b>" . fbytes($sum_in) . "</b></td>\n";
print "<td class=\"data\"><b>" . fbytes($sum_out) . "</b></td>\n";
print "<td class=\"data\"><b></b></td>\n";
print "<td class=\"data\"><b></b></td>\n";
print "</tr>\n";
?>
</table>
<?
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/footer.php");
?>
