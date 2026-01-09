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

$usersip = get_record_sql($db_link, "SELECT ip,user_id,description FROM user_auth WHERE user_auth.id=?", [ $id ]);
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

// === 1. Валидация и подготовка параметров ===
$params = [$date1, $date2, (int)$id];
$conditions = ["ts >= ?", "ts < ?", "auth_id = ?"];

// Фильтр по gateway
if (!empty($rgateway) && $rgateway > 0) {
    $conditions[] = "router_id = ?";
    $params[] = (int)$rgateway;
}

// Фильтр по IP (если search — валидный IPv4)
if (!empty($search) && filter_var($search, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
    $ip_long = sprintf('%u', ip2long($search)); // беззнаковое число
    $conditions[] = "(src_ip = ? OR dst_ip = ?)";
    $params[] = $ip_long;
    $params[] = $ip_long;
}

$whereClause = implode(' AND ', $conditions);

// === 2. Подсчёт записей ===
$countSQL = "SELECT COUNT(*) FROM traffic_detail WHERE $whereClause";
$count_records = (int)get_single_field($db_link, $countSQL, $params);

// === 3. Пагинация ===
$total = ceil($count_records / $displayed);
$page = max(1, min($page, $total));
$start = ($page - 1) * $displayed;

print_navigation($page_url, $page, $displayed, $count_records, $total);

// === 4. Безопасная сортировка (БЕЛЫЙ СПИСОК!) ===
$allowed_sort_fields = ['ts', 'proto', 'src_ip', 'dst_ip', 'bytes', 'pkt'];
$allowed_order = ['ASC', 'DESC'];

$sort_field = in_array($sort_field, $allowed_sort_fields, true) ? $sort_field : 'ts';
$order = in_array(strtoupper($order), $allowed_order, true) ? strtoupper($order) : 'ASC';

// === 5. Запрос данных с пагинацией ===
$limit = (int)$displayed;
$offset = (int)$start;

$dataParams = array_merge($params, [$limit, $offset]);

// Используем прямой запрос (без подзапроса — он не нужен для пагинации по id)
$fsql = "
    SELECT id, ts, router_id, proto, src_ip, src_port, dst_ip, dst_port, bytes, pkt
    FROM traffic_detail
    WHERE $whereClause
    ORDER BY $sort_field $order
    LIMIT ? OFFSET ?
";

$userdata = get_records_sql($db_link, $fsql, $dataParams);

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
<?php print_navigation($page_url,$page,$displayed,$count_records,$total); ?>
<br>
<?php
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/footer.php");
?>
