<?php
$default_displayed = 500;
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/auth.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/languages/" . HTML_LANG . ".php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/header.php");
$default_sort='ip_int';
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/oufilter.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/subnetfilter.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/sortfilter.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/gatefilter.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/enabledfilter.php");

if (isset($_POST['searchComment'])) { $f_comment = $_POST['searchComment']; }
if (isset($_GET['searchComment'])) { $f_comment = $_GET['searchComment']; }
if (!isset($f_comment) and isset($_SESSION[$page_url]['comment'])) { $f_comment=$_SESSION[$page_url]['comment']; }
if (!isset($f_comment)) { $f_comment=''; }

$_SESSION[$page_url]['comment']=$f_comment;

$sort_table = 'User_auth';

$sort_url = "<a href=deleted.php?";

if ($rsubnet == 0) { $subnet_filter = ''; } else {
    $subnet_range = get_subnet_range($db_link,$rsubnet);
    $subnet_filter = " and User_auth.ip_int>=".$subnet_range['start']." and User_auth.ip_int<=".$subnet_range['stop'];
    }

$ip_list_filter = $subnet_filter;

$ip_where = '';
if (!empty($f_comment)) {
    if (checkValidIp($f_comment)) { $ip_where = " and ip_int=inet_aton('" . $f_comment . "') "; }
    if (checkValidMac($f_comment)) { $ip_where = " and mac='" . mac_dotted($f_comment) . "'  "; }
    if (empty($ip_where)) { $ip_where=" and (User_auth.comments LIKE '$f_comment' OR User_auth.dhcp_hostname LIKE '$f_comment')"; }
    $ip_list_filter = $ip_where;
    } 

print_ip_submenu($page_url);

?>
<div id="cont">
<form name="def" action="deleted.php" method="post">
<br>
<div>
        <b><?php print WEB_network_subnet; ?> - </b><?php print_subnet_select_office($db_link, 'subnet', $rsubnet); ?>
        <?php echo WEB_ips_search_full; ?>: &nbsp <input type=text name=searchComment value="<?php print $f_comment; ?>">
        <?php print WEB_rows_at_page."&nbsp"; print_row_at_pages('rows',$displayed); ?>
        <input type="submit" value="<?php echo WEB_btn_show; ?>">
</div>

<?php
$countSQL="SELECT Count(*) FROM User_auth WHERE User_auth.deleted = 1 $ip_list_filter";
$res = mysqli_query($db_link, $countSQL);
$count_records = mysqli_fetch_array($res);
$total=ceil($count_records[0]/$displayed);
if ($page>$total) { $page=$total; }
if ($page<1) { $page=1; }
$start = ($page * $displayed) - $displayed; 
print_navigation($page_url,$page,$displayed,$count_records[0],$total);
?>
<br>

<table class="data">
	<tr>
		<td align=Center><?php print $sort_url . "sort=ip_int&order=$new_order>" . WEB_cell_ip . "</a>"; ?></td>
		<td align=Center><?php print $sort_url . "sort=mac&order=$new_order>" . WEB_cell_mac . "</a>"; ?></td>
		<td align=Center><?php print WEB_cell_comment; ?></td>
		<td align=Center><?php print WEB_cell_dns_name; ?></td>
		<td align=Center><?php print $sort_url . "sort=timestamp&order=$new_order>".WEB_cell_created."</a>"; ?></td>
		<td align=Center><?php print $sort_url . "sort=changed_time&order=$new_order>".WEB_cell_deleted."</a>"; ?></td>
		<td align=Center><?php print $sort_url . "sort=last_found&order=$new_order>".WEB_cell_last_found."</a>"; ?></td>
	</tr>
<?php

$sSQL = "SELECT 
User_auth.id, User_auth.ip, User_auth.mac, User_auth.comments, User_auth.dns_name, User_auth.dhcp_hostname, 
User_auth.dhcp_time, User_auth.last_found, User_auth.timestamp, User_auth.changed_time
FROM User_auth WHERE User_auth.deleted = 1 $ip_list_filter
ORDER BY $sort_table.$sort_field $order LIMIT $start,$displayed";
$users = get_records_sql($db_link,$sSQL);
foreach ($users as $user) {
    if (empty($user['last_found']) or $user['last_found'] === '0000-00-00 00:00:00') { $user['last_found'] = ''; }
    if (empty($user['timestamp']) or $user['timestamp'] === '0000-00-00 00:00:00') { $user['timestamp'] = ''; }
    if (empty($user['changed_time']) or $user['changed_time'] === '0000-00-00 00:00:00') { $user['changed_time'] = ''; }
    print "<tr align=center>\n";
    $cl = "data";
    print "<td class=\"$cl\" ><a href=/admin/users/editauth.php?id=".$user['id'].">" . $user['ip'] . "</a></td>\n";
    print "<td class=\"$cl\" >" . expand_mac($db_link,$user['mac']) . "</td>\n";
    if (isset($user['dhcp_hostname']) and strlen($user['dhcp_hostname']) > 0) {
        print "<td class=\"$cl\" >".$user['comments']." [" . $user['dhcp_hostname'] . "]</td>\n";
    } else {
        print "<td class=\"$cl\" >".$user['comments']."</td>\n";
    }
    print "<td class=\"$cl\" >".$user['dns_name']."</td>\n";
    print "<td class=\"$cl\" >".$user['timestamp']."</td>\n";
    print "<td class=\"$cl\" >".$user['changed_time']."</td>\n";
    print "<td class=\"$cl\" >".$user['last_found']."</td>\n";
    print "</tr>\n";
}
print "</table>\n";
print_navigation($page_url,$page,$displayed,$count_records[0],$total);
?>
<?php
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/footer.php");
?>
