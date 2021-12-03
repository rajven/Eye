<?php
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/auth.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/languages/" . $language . ".php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/header.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/idfilter.php");
$default_date_shift='d';
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/datefilter.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/gatefilter.php");

$usersip = mysqli_query($db_link, "SELECT ip,user_id,comments FROM User_auth WHERE User_auth.id=$id");
list ($fip, $parent, $fcomm) = mysqli_fetch_array($usersip);

$rdns = 0;
if (isset($_POST['dns'])) { $rdns=$_POST['dns']*1; }
$_SESSION[$page_url]['dns']=$rdns;
$dns_checked='';
if ($rdns) { $dns_checked='checked="checked"'; }

print_trafdetail_submenu($page_url,"id=$id&date_start='$date1'&date_stop='$date2'","<b>Детализация для <a href=/admin/users/editauth.php?id=$id>$fip</a></b> ::&nbsp");
?>

<div id="cont">

<form action="<?=$_SERVER['PHP_SELF']?>" method="post">
<input type="hidden" name="id" value=<?php echo $id; ?>>
  Начало:&nbsp<input type="date" name="date_start" value="<?php echo $date1; ?>" />
  Конец:&nbsp<input type="date" name="date_stop" value="<?php echo $date2; ?>" />
  Шлюз:&nbsp <?php print_gateway_select($db_link, 'gateway', $rgateway); ?>
  DNS:&nbsp <input type=checkbox name=dns value="1" <?php print $dns_checked; ?>>
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

$gateway_filter='';
if (!empty($rgateway) and $rgateway>0) { $gateway_filter="(router_id=$rgateway) AND"; }

$fsql = "SELECT A.proto, A.src_ip, A.src_port, SUM(A.bytes) as tin FROM Traffic_detail A
            WHERE $gateway_filter (auth_id='$id') and  `timestamp`>='$date1' and `timestamp`<'$date2' and (A.dst_ip='$ip_aton')
            GROUP BY A.src_ip, A.src_port, A.proto ORDER BY tin DESC LIMIT 0,10";
$userdata = mysqli_query($db_link, $fsql);
while (list ($uproto, $uip, $uport, $ubytes) = mysqli_fetch_array($userdata)) {
    print "<tr align=center align=center class=\"tr1\" onmouseover=\"className='tr2'\" onmouseout=\"className='tr1'\">\n";
    print "<td class=\"data\">" . $uproto . "</td>\n";
    print "<td class=\"data\" align=left>" . long2ip($uip) . "</td>\n";
    $ip_name = '-';
    if ($rdns) { $ip_name = ResolveIP($db_link,$uip); }
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
        WHERE $gateway_filter (auth_id='$id') and  `timestamp`>='$date1' and `timestamp`<'$date2' and (A.src_ip='$ip_aton')
        GROUP BY A.dst_ip, A.dst_port, A.proto ORDER BY tout DESC LIMIT 0,10";
$userdata = mysqli_query($db_link, $fsql);
while (list ($uproto, $uip, $uport, $ubytes) = mysqli_fetch_array($userdata)) {
    print "<tr align=center align=center class=\"tr1\" onmouseover=\"className='tr2'\" onmouseout=\"className='tr1'\">\n";
    if ($uproto==='6') { $uproto = 'tcp'; }
    if ($uproto==='17') { $uproto = 'udp'; }
    print "<td class=\"data\">" . $uproto . "</td>\n";
    print "<td class=\"data\" align=left>" . long2ip($uip) . "</td>\n";
    $ip_name = '-';
    if ($rdns) { $ip_name = ResolveIP($db_link,$uip); }
    print "<td class=\"data\" align=left>" . $ip_name . "</td>\n";
    print "<td class=\"data\">" . $uport . "</td>\n";
    print "<td class=\"data\" align=right>" . fbytes($ubytes) . "</td>\n";
    print "</tr>\n";
}
?>
</table>
<?php
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/footer.php");
?>
