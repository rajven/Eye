<?php
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/auth.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/languages/" . HTML_LANG . ".php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/header.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/idfilter.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/datetimefilter.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/gatefilter.php");
$default_sort='id';
$sort_table = 'A';
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/sortfilter.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/search.php");
$rdns = 0;
if (isset($_POST['dns'])) { $rdns=$_POST['dns']*1; }
$_SESSION[$page_url]['dns']=$rdns;
$dns_checked='';
if ($rdns) { $dns_checked='checked="checked"'; }

$dns_cache=NULL;

$usersip = get_record_sql($db_link, "SELECT ip,user_id,description FROM user_auth WHERE user_auth.id=$id");
if (empty($usersip)) {
    header("location: /admin/reports/index-full.php");
    exit;
}

$fip = $usersip['ip'];
$parent = $usersip['user_id'];
$fcomm = $usersip['description'];

print_trafdetail_submenu($page_url,"id=$id&date_start='$date1'&date_stop='$date2'","<b>".WEB_log_detail_for."&nbsp<a href=/admin/users/editauth.php?id=$id>$fip</a></b> ::&nbsp");
?>

<div id="contsubmenu">

<form action="<?php print $page_url; ?>" method="post">
<input type="hidden" name="id" value=<?php echo $id; ?>>
<?php print_date_fields($date1,$date2,$date_shift); ?>
<?php echo WEB_cell_gateway; ?>:&nbsp <?php print_gateway_select($db_link, 'gateway', $rgateway); ?>
DNS:&nbsp <input type=checkbox name=dns value="1" <?php print $dns_checked; ?>>
<?php echo WEB_search; ?>:&nbsp<input type="text" minlength="7" maxlength="15" size="15" pattern="^(?>(\d|[1-9]\d{2}|1\d\d|2[0-4]\d|25[0-5])\.){3}(?1)$"  name="search" value="<?php echo $search; ?>" />
<?php print WEB_rows_at_page."&nbsp"; print_row_at_pages('rows',$displayed); ?>
<input type="submit" value="<?php echo WEB_btn_show; ?>">
</form>

<b><?php echo WEB_log_full; ?></b>

<?php
$sort_url = "<a href='userdaydetaillog.php?id=".$id.'&date_start="'.$date1.'"&date_stop="'.$date2.'"';

$gateway_filter='';
if (!empty($rgateway) and $rgateway>0) { $gateway_filter="(router_id=$rgateway) AND"; }
if (!empty($search)) { $gateway_filter.=' (src_ip='.ip2long($search).' OR dst_ip='.ip2long($search).') AND'; }

$countSQL="SELECT Count(*) FROM traffic_detail as A WHERE $gateway_filter (auth_id='$id') and ts>='$date1' and ts<'$date2'";
$count_records = get_single_field($db_link,$countSQL);
$total=ceil($count_records/$displayed);
if ($page>$total) { $page=$total; }
if ($page<1) { $page=1; }
$start = ($page * $displayed) - $displayed;
print_navigation($page_url,$page,$displayed,$count_records,$total);
$gateway_list = get_gateways($db_link);
?>

<br>
<table class="data">
<tr align="center">
<td class="data" width=150><b><?php $url = $sort_url.'&sort=ts&order='.$new_order."'>".WEB_date."</a>"; print $url; ?></b></td>
<td class="data" width=30><b><?php echo WEB_cell_gateway; ?></b></td>
<td class="data" width=30><b><?php echo WEB_traffic_proto; ?></b></td>
<td class="data" width=150><b><?php $url = $sort_url.'&sort=src_ip&order='.$new_order."'>".WEB_traffic_source_address."</a>"; print $url; ?></b></td>
<td class="data"><b>DNS</b></td>
<td class="data" width=50><b><?php echo WEB_traffic_src_port; ?></b></td>
<td class="data" width=150><b><?php $url = $sort_url.'&sort=dst_ip&order='.$new_order."'>".WEB_traffic_dest_address."</a>"; print $url; ?></b></td>
<td class="data"><b>DNS</b></td>
<td class="data" width=50><b><?php echo WEB_traffic_dst_port; ?></b></td>
<td class="data" width=80><b><?php $url = $sort_url.'&sort=bytes&order='.$new_order."'>".WEB_bytes."</a>"; print $url; ?></b></td>
<td class="data" width=80><b><?php $url = $sort_url.'&sort=pkt&order='.$new_order."'>".WEB_pkts."</a>"; print $url; ?></b></td>
</tr>
<?php
$fsql = "SELECT A.id, A.ts, A.router_id, A.proto, A.src_ip, A.src_port, A.dst_ip, A.dst_port, A.bytes, A.pkt FROM traffic_detail as A JOIN (SELECT id FROM traffic_detail 
        WHERE $gateway_filter (auth_id='$id') and  ts>='$date1' and ts<'$date2'
        ORDER BY ts ASC LIMIT $start,$displayed) as T ON A.id = T.id ORDER BY $sort_table.$sort_field $order";
$userdata = get_records_sql($db_link, $fsql);
foreach ($userdata as $row) {
    print "<tr align=center class=\"tr1\" onmouseover=\"className='tr2'\" onmouseout=\"className='tr1'\">\n";
    print "<td class=\"data\">" . $row['ts'] . "</td>\n";
    print "<td class=\"data\">" . $gateway_list[$row['router_id']] . "</td>\n";
    $proto_name = getprotobynumber($row['proto']);
    if (!$proto_name) { $proto_name = $row['proto']; }
    print "<td class=\"data\">" . $proto_name . "</td>\n";
    print "<td class=\"data\" align=left>" . long2ip($row['src_ip']) . "</td>\n";
    $ip_name = '-';
    if ($rdns) { $ip_name = ResolveIP($db_link, $row['src_ip']); }
    print "<td class=\"data\" align=left>" . $ip_name . "</td>\n";
    print "<td class=\"data\">" . $row['src_port'] . "</td>\n";
    print "<td class=\"data\" align=left>" . long2ip($row['dst_ip']) . "</td>\n";
    $ip_name = '-';
    if ($rdns) { $ip_name = ResolveIP($db_link, $row['dst_ip']); }
    print "<td class=\"data\" align=left>" . $ip_name . "</td>\n";
    print "<td class=\"data\">" . $row['dst_port'] . "</td>\n";
    print "<td class=\"data\" align=right>" . fbytes($row['bytes']) . "</td>\n";
    print "<td class=\"data\" align=right>" . $row['pkt'] . "</td>\n";
    print "</tr>\n";
}
?>
</table>
<?php print_navigation($page_url,$page,$displayed,$count_records[0],$total); ?>
<br>
<?php
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/footer.php");
?>
