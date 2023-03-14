<?php
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/auth.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/languages/" . HTML_LANG . ".php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/header.php");
$default_date_shift='h';
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/datetimefilter.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/gatefilter.php");
$default_sort='id';
$sort_table = 'A';
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/sortfilter.php");

if (isset($_POST['ip'])) { $f_ip = $_POST['ip']; }
if (isset($_GET['ip'])) { $f_ip = $_GET['ip']; }
if (!isset($f_ip) and isset($_SESSION[$page_url]['ip'])) { $f_ip=$_SESSION[$page_url]['ip']; }
if (empty($f_ip)) { $f_ip = '127.0.0.1'; }

$_SESSION[$page_url]['ip']=$f_ip;

$ip_where = '';

if (!empty($f_ip)) {
    if (checkValidIp($f_ip)) {
        $ip_where = " (src_ip=inet_aton('" . $f_ip . "') or dst_ip=inet_aton('" . $f_ip . "')) AND "; 
        }
    }

$rdns = 0;
if (isset($_POST['dns'])) { $rdns=$_POST['dns']*1; }
$_SESSION[$page_url]['dns']=$rdns;
$dns_checked='';
if ($rdns) { $dns_checked='checked="checked"'; }

$dns_cache=NULL;

print_log_submenu($page_url);
print_trafdetail_submenu($page_url,"id=$id&date_start=$date1&date_stop=$date2","<b>".WEB_log_detail_for."<a href=/admin/users/editauth.php?id=$id>$f_ip</a></b> ::&nbsp");
?>

<div id="contsubmenu">

<form action="<?php print $page_url; ?>" method="post">
<input type="hidden" name="id" value=<?php echo $id; ?>>
<?php echo WEB_cell_ip; ?>:&nbsp<input type="text" name="ip" value="<?php echo $f_ip; ?>" pattern="^(([0-9]|[1-9][0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5])\.){3}([0-9]|[1-9][0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5])$"
<?php echo WEB_log_start_date; ?>:&nbsp<input type="datetime-local" name="date_start" value="<?php echo $date1; ?>" />
<?php echo WEB_log_stop_date; ?>:&nbsp<input type="datetime-local" name="date_stop" value="<?php echo $date2; ?>" />
<?php echo WEB_cell_gateway; ?>:&nbsp <?php print_gateway_select($db_link, 'gateway', $rgateway); ?>
DNS:&nbsp <input type=checkbox name=dns value="1" <?php print $dns_checked; ?>>
<?php print WEB_rows_at_page."&nbsp"; print_row_at_pages('rows',$displayed); ?>
<input type="submit" value="<?php echo WEB_btn_show; ?>">
</form>

<b><?php echo WEB_log_full; ?></b>

<?php
$sort_url = "<a href='detaillog.php?date_start=\"".$date1.'"&date_stop="'.$date2.'"';
if (!empty($f_ip)) { $sort_url .='&f_ip="'.$f_ip.'"'; }

$gateway_filter='';
if (!empty($rgateway) and $rgateway>0) { $gateway_filter="(router_id=$rgateway) AND"; }

$countSQL="SELECT Count(*) FROM Traffic_detail as A WHERE $gateway_filter $ip_where `timestamp`>='$date1' AND `timestamp`<'$date2'";
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
<td class="data" width=20><b><?php echo WEB_cell_login; ?></b></td>
<td class="data" width=150><b><?php $url = $sort_url.'&sort=timestamp&order='.$new_order."'>".WEB_date."</a>"; print $url; ?></b></td>
<td class="data" width=30><b><?php echo WEB_cell_gateway; ?></b></td>
<td class="data" width=30><b><?php echo WEB_traffic_proto; ?></b></td>
<td class="data" width=150><b><?php $url = $sort_url.'&sort=src_ip&order='.$new_order."'>".WEB_traffic_source_address."</a>"; print $url; ?></b></td>
<td class="data"><b>DNS</b></td>
<td class="data" width=50><b><?php echo WEB_traffic_src_port; ?></b></td>
<td class="data" width=150><b><?php $url = $sort_url.'&sort=dst_ip&order='.$new_order."'>".WEB_traffic_dest_address."</a>"; print $url; ?></b></td>
<td class="data"><b>DNS</b></td>
<td class="data" width=50><b><?php echo WEB_traffic_dst_port; ?></b></td>
<td class="data" width=80><b><?php $url = $sort_url.'&sort=bytes&order='.$new_order."'>".WEB_bytes."</a>"; print $url; ?></b></td>
<td class="data" width=80><b><?php $url = $sort_url.'&sort=pkt&order='.$new_order."'>Pkt</a>"; print $url; ?></b></td>
</tr>
<?php
$fsql = "SELECT A.id, A.auth_id, A.`timestamp`, A.router_id, A.proto, A.src_ip, A.src_port, A.dst_ip, A.dst_port, A.bytes, A.pkt FROM Traffic_detail as A JOIN (SELECT id FROM Traffic_detail 
        WHERE $gateway_filter $ip_where `timestamp`>='$date1' AND `timestamp`<'$date2'
        ORDER BY `timestamp` ASC LIMIT $start,$displayed) as T ON A.id = T.id ORDER BY $sort_table.$sort_field $order";
$userdata = mysqli_query($db_link, $fsql);
while (list ($uid, $auth_id, $udata, $urouter, $uproto, $sip, $sport,$dip, $dport, $ubytes, $upkt) = mysqli_fetch_array($userdata)) {
    print "<tr align=center align=center class=\"tr1\" onmouseover=\"className='tr2'\" onmouseout=\"className='tr1'\">\n";
    print "<td class=\"data\">$uid</td>\n";
    print "<td class=\"data\">"; print_auth_simple($db_link,$auth_id); print "</td>\n";
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
<?php
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/footer.php");
?>
