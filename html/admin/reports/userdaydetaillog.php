<?php
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/auth.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/languages/" . HTML_LANG . ".php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/header.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/idfilter.php");
$default_date_shift='h';
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/datetimefilter.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/gatefilter.php");
$default_sort='id';
$sort_table = 'A';
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/sortfilter.php");
$rdns = 0;
if (isset($_POST['dns'])) { $rdns=$_POST['dns']*1; }
$_SESSION[$page_url]['dns']=$rdns;
$dns_checked='';
if ($rdns) { $dns_checked='checked="checked"'; }

$dns_cache=NULL;

$usersip = mysqli_query($db_link, "SELECT ip,user_id,comments FROM User_auth WHERE User_auth.id=$id");
list ($fip, $parent, $fcomm) = mysqli_fetch_array($usersip);

print_trafdetail_submenu($page_url,"id=$id&date_start=$date1&date_stop=$date2","<b>Детализация для <a href=/admin/users/editauth.php?id=$id>$fip</a></b> ::&nbsp");
?>

<div id="cont">

<form action="<?php print $page_url; ?>" method="post">
<input type="hidden" name="id" value=<?php echo $id; ?>>
Начало:&nbsp<input type="datetime-local" name="date_start" value="<?php echo $date1; ?>" />
Конец:&nbsp<input type="datetime-local" name="date_stop" value="<?php echo $date2; ?>" />
Шлюз:&nbsp <?php print_gateway_select($db_link, 'gateway', $rgateway); ?>
DNS:&nbsp <input type=checkbox name=dns value="1" <?php print $dns_checked; ?>>
Отображать:<?php print_row_at_pages('rows',$displayed); ?>
<input type="submit" value="OK">
</form>

<b>Полный лог</b>

<?php
$sort_url = "<a href='userdaydetaillog.php?id=".$id.'&date_start="'.$date1.'"&date_stop="'.$date2.'"';

$gateway_filter='';
if (!empty($rgateway) and $rgateway>0) { $gateway_filter="(router_id=$rgateway) AND"; }

$countSQL="SELECT Count(*) FROM Traffic_detail as A WHERE $gateway_filter (auth_id='$id') and `timestamp`>='$date1' and `timestamp`<'$date2'";
$res = mysqli_query($db_link, $countSQL);
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
<td class="data" width=20><b><?php $url = $sort_url.'&sort=id&order='.$new_order."'>id</a>"; print $url; ?></b></td>
<td class="data" width=150><b><?php $url = $sort_url.'&sort=timestamp&order='.$new_order."'>Дата</a>"; print $url; ?></b></td>
<td class="data" width=30><b>Роутер</b></td>
<td class="data" width=30><b>Протокол</b></td>
<td class="data" width=150><b><?php $url = $sort_url.'&sort=src_ip&order='.$new_order."'>Откуда</a>"; print $url; ?></b></td>
<td class="data"><b>DNS</b></td>
<td class="data" width=50><b>Порт</b></td>
<td class="data" width=150><b><?php $url = $sort_url.'&sort=dst_ip&order='.$new_order."'>Куда</a>"; print $url; ?></b></td>
<td class="data"><b>DNS</b></td>
<td class="data" width=50><b>Порт</b></td>
<td class="data" width=80><b><?php $url = $sort_url.'&sort=bytes&order='.$new_order."'>Байт</a>"; print $url; ?></b></td>
<td class="data" width=80><b><?php $url = $sort_url.'&sort=pkt&order='.$new_order."'>Pkt</a>"; print $url; ?></b></td>
</tr>
<?php
$fsql = "SELECT A.id, A.`timestamp`, A.router_id, A.proto, A.src_ip, A.src_port, A.dst_ip, A.dst_port, A.bytes, A.pkt FROM Traffic_detail as A JOIN (SELECT id FROM Traffic_detail 
        WHERE $gateway_filter (auth_id='$id') and  `timestamp`>='$date1' and `timestamp`<'$date2'
        ORDER BY `timestamp` ASC LIMIT $start,$displayed) as T ON A.id = T.id ORDER BY $sort_table.$sort_field $order";
$userdata = mysqli_query($db_link, $fsql);
while (list ($uid,$udata, $urouter, $uproto, $sip, $sport,$dip, $dport, $ubytes, $upkt) = mysqli_fetch_array($userdata)) {
    print "<tr align=center align=center class=\"tr1\" onmouseover=\"className='tr2'\" onmouseout=\"className='tr1'\">\n";
    print "<td class=\"data\">$uid</td>\n";
    print "<td class=\"data\">$udata</td>\n";
    print "<td class=\"data\">$gateway_list[$urouter]</td>\n";
    if ($uproto==='6') { $uproto = 'tcp'; }
    if ($uproto==='17') { $uproto = 'udp'; }
    print "<td class=\"data\">" . $uproto . "</td>\n";
    print "<td class=\"data\" align=left>" . long2ip($sip) . "</td>\n";
    $ip_name = '-';
    if ($rdns) { $ip_name = ResolveIP($db_link,$sip); }
    print "<td class=\"data\" align=left>" . $ip_name . "</td>\n";
    print "<td class=\"data\">" .$sport . "</td>\n";
    print "<td class=\"data\" align=left>" . long2ip($dip) . "</td>\n";
    $ip_name = '-';
    if ($rdns) { $ip_name = ResolveIP($db_link,$dip); }
    print "<td class=\"data\" align=left>" . $ip_name . "</td>\n";
    print "<td class=\"data\">" . $dport . "</td>\n";
    print "<td class=\"data\" align=right>" . fbytes($ubytes) . "</td>\n";
    print "<td class=\"data\" align=right>" . $upkt . "</td>\n";
    print "</tr>\n";
}
?>
</table>
<?php print_navigation($page_url,$page,$displayed,$count_records[0],$total); ?>
<br>
<?php
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/footer.php");
?>
