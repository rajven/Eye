<?php
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/auth.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/sqlt.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/languages/" . $language . ".php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/header.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/idfilter.php");
$default_date_shift='d';
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/datefilter.php");

$usersip = mysqli_query($db_link, "SELECT ip,user_id,comments FROM User_auth WHERE User_auth.id=$id");
list ($fip, $parent, $fcomm) = mysqli_fetch_array($usersip);
?>

<div id="cont">
<?php print "<b>Детализация для <a href=../users/editauth.php?id=$id>$fip</a></b><br>\n"; ?>

<form action="<?=$_SERVER['PHP_SELF']?>" method="post">
<input type="hidden" name="id" value=<? echo $id; ?>>
  Начало:&nbsp<input type="date" name="date_start" value="<?php echo $date1; ?>" />
  Конец:&nbsp<input type="date" name="date_stop" value="<?php echo $date2; ?>" />
  Отображать:<?php print_row_at_pages('rows',$displayed); ?>
  <input type="submit" value="OK">
</form>

<br>
<b>Топ 10 по входящему трафику</b>
<table class="data">
<tr align="center">
<td class="data" width=30><b>Протокол</b></td>
<td class="data" width=150><b>Откуда</b></td>
<td class="data"><b>DNS</b></td>
<td class="data" width=50><b>Порт</b></td>
<td class="data" width=80><b>Байт</b></td>
</tr>
<?php
$ip_aton = ip2long($fip);
$fsql = "SELECT A.proto, A.src_ip, A.src_port, SUM(A.bytes) as tin FROM Traffic_detail A
            WHERE (auth_id='$id') and  (date(`timestamp`)>='$date1' and date(`timestamp`)<'$date2') and (A.dst_ip='$ip_aton')
            GROUP BY A.src_ip, A.src_port, A.proto ORDER BY tin DESC LIMIT 0,10";
$userdata = mysqli_query($dbt_link, $fsql);
while (list ($uproto, $uip, $uport, $ubytes) = mysqli_fetch_array($userdata)) {
    print "<tr align=center align=center class=\"tr1\" onmouseover=\"className='tr2'\" onmouseout=\"className='tr1'\">\n";
    print "<td class=\"data\">" . $uproto . "</td>\n";
    print "<td class=\"data\" align=left>" . long2ip($uip) . "</td>\n";
    $ip_name = gethostbyaddr(long2ip($uip));
    if (! isset($ip_name)) { $ip_name = '-'; }
    print "<td class=\"data\" align=left>" . $ip_name . "</td>\n";
    print "<td class=\"data\">" . $uport . "</td>\n";
    print "<td class=\"data\" align=right>" . fbytes($ubytes) . "</td>\n";
    print "</tr>\n";
}
?>
</table>
<b>Топ 10 по исходящему трафику</b>
<table class="data">
<tr align="center">
<td class="data" width=30><b>Протокол</b></td>
<td class="data" width=150><b>Куда</b></td>
<td class="data"><b>DNS</b></td>
<td class="data" width=50><b>Порт</b></td>
<td class="data" width=80><b>Байт</b></td>
</tr>
<?php
$fsql = "SELECT A.proto, A.dst_ip, A.dst_port, SUM(A.bytes) as tout FROM Traffic_detail A
        WHERE (auth_id='$id') and  (date(`timestamp`)>='$date1' and date(`timestamp`)<'$date2') and (A.src_ip='$ip_aton')
        GROUP BY A.dst_ip, A.dst_port, A.proto ORDER BY tout DESC LIMIT 0,10";
$userdata = mysqli_query($dbt_link, $fsql);
while (list ($uproto, $uip, $uport, $ubytes) = mysqli_fetch_array($userdata)) {
    print "<tr align=center align=center class=\"tr1\" onmouseover=\"className='tr2'\" onmouseout=\"className='tr1'\">\n";
    print "<td class=\"data\">" . $uproto . "</td>\n";
    print "<td class=\"data\" align=left>" . long2ip($uip) . "</td>\n";
    $ip_name = gethostbyaddr(long2ip($uip));
    if (! isset($ip_name)) { $ip_name = '-'; }
    print "<td class=\"data\" align=left>" . $ip_name . "</td>\n";
    print "<td class=\"data\">" . $uport . "</td>\n";
    print "<td class=\"data\" align=right>" . fbytes($ubytes) . "</td>\n";
    print "</tr>\n";
}
?>
</table>
<b>Полный лог</b>

<?php
$countSQL="SELECT Count(*) FROM Traffic_detail as A WHERE (auth_id='$id') and (date(`timestamp`)>='$date1' and date(`timestamp`)<'$date2')";
$res = mysqli_query($dbt_link, $countSQL);
$count_records = mysqli_fetch_array($res);
$total=ceil($count_records[0]/$displayed);
if ($page>$total) { $page=$total; }
if ($page<1) { $page=1; }
$start = ($page * $displayed) - $displayed;
print_navigation($page_url,$page,$displayed,$count_records[0],$total);
$gateway_list = get_gateways($db_link);
?>

<br>
<table class="data">
<tr align="center">
<td class="data" width=150><b>Дата</b></td>
<td class="data" width=30><b>Роутер</b></td>
<td class="data" width=30><b>Протокол</b></td>
<td class="data" width=150><b>Откуда</b></td>
<td class="data"><b>DNS</b></td>
<td class="data" width=50><b>Порт</b></td>
<td class="data" width=150><b>Куда</b></td>
<td class="data"><b>DNS</b></td>
<td class="data" width=50><b>Порт</b></td>
<td class="data" width=80><b>Байт</b></td>
</tr>
<?php
$fsql = "SELECT A.`timestamp`, A.router_id, A.proto, A.src_ip, A.src_port, A.dst_ip, A.dst_port, A.bytes FROM Traffic_detail as A JOIN (SELECT id FROM Traffic_detail 
        WHERE (auth_id='$id') and  (date(`timestamp`)>='$date1' and date(`timestamp`)<'$date2')
        ORDER BY `timestamp` ASC LIMIT $start,$displayed) as T ON A.id = T.id";
$userdata = mysqli_query($dbt_link, $fsql);
while (list ($udata, $urouter, $uproto, $sip, $sport,$dip, $dport, $ubytes) = mysqli_fetch_array($userdata)) {
    print "<tr align=center align=center class=\"tr1\" onmouseover=\"className='tr2'\" onmouseout=\"className='tr1'\">\n";
    print "<td class=\"data\">$udata</td>\n";
    print "<td class=\"data\">$gateway_list[$urouter]</td>\n";
    print "<td class=\"data\">" . $uproto . "</td>\n";
    print "<td class=\"data\" align=left>" . long2ip($sip) . "</td>\n";
    $ip_name = gethostbyaddr(long2ip($sip));
    if (! isset($ip_name)) { $ip_name = '-'; }
    print "<td class=\"data\" align=left>" . $ip_name . "</td>\n";
    print "<td class=\"data\">" .$sport . "</td>\n";
    print "<td class=\"data\" align=left>" . long2ip($dip) . "</td>\n";
    $ip_name = gethostbyaddr(long2ip($dip));
    if (! isset($ip_name)) { $ip_name = '-'; }
    print "<td class=\"data\" align=left>" . $ip_name . "</td>\n";
    print "<td class=\"data\">" . $dport . "</td>\n";
    print "<td class=\"data\" align=right>" . fbytes($ubytes) . "</td>\n";
    print "</tr>\n";
}
?>
</table>
<?php print_navigation($page_url,$page,$displayed,$count_records[0],$total); ?>
<br>
<?php
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/footer.php");
?>
