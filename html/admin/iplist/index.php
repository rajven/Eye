<?php
$default_displayed=500;
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/auth.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/languages/" . HTML_LANG . ".php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/header.php");
$default_sort='ip_int';
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/oufilter.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/subnetfilter.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/sortfilter.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/gatefilter.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/enabledfilter.php");

$sort_table = 'User_auth';
if ($sort_field == 'login') { $sort_table = 'User_list'; }
if ($sort_field == 'fio') { $sort_table = 'User_list'; }

$sort_url = "<a href=index.php?ou=" . $rou; 

if (isset($_POST["removeauth"])) {
    $auth_id = $_POST["fid"];
    foreach ($auth_id as $key => $val) {
        if ($val) {
                run_sql($db_link, 'DELETE FROM connections WHERE auth_id='.$val);
                run_sql($db_link, 'DELETE FROM User_auth_alias WHERE auth_id='.$val);
                $auth["deleted"] = 1;
                $changes = get_diff_rec($db_link,"User_auth","id='$val'", '', 0);
                if (!empty($changes)) { LOG_WARNING($db_link,"Удалён адрес доступа: \r\n $changes"); }
                update_record($db_link, "User_auth", "id=" . $val, $auth);
                }
            }
    header("Location: " . $_SERVER["REQUEST_URI"]);
    exit;
    }

if (isset($_POST["ApplyForAll"])) {
    $auth_id = $_POST["fid"];
    $a_enabled = $_POST["a_enabled"] * 1;
    $a_dhcp = $_POST["a_dhcp"] * 1;
    $a_dhcp_acl = $_POST["a_dhcp_acl"];
    $a_queue = $_POST["a_queue_id"] * 1;
    $a_group = $_POST["a_group_id"] * 1;
    $a_traf = $_POST["a_traf"] * 1;
    $msg="Массовое изменение пользователей!";
    foreach ($auth_id as $key => $val) {
        if ($val) {
            unset($auth);
            $auth['enabled'] = $a_enabled;
            $auth['filter_group_id'] = $a_group;
            $auth['queue_id'] = $a_queue;
            $auth['dhcp'] = $a_dhcp;
            $auth['dhcp_acl'] = $a_dhcp_acl;
            $auth['save_traf'] = $a_traf;
            update_record($db_link, "User_auth", "id='" . $val . "'", $auth);
            }
        }
    LOG_WARNING($db_link,$msg);
    header("Location: " . $_SERVER["REQUEST_URI"]);
    exit;
    }

if ($rou == 0) { $ou_filter = ''; } else { $ou_filter = " and User_list.ou_id=$rou "; }

if ($rsubnet == 0) { $subnet_filter = ''; } else {
    $subnet_range = get_subnet_range($db_link,$rsubnet);
    if (!empty($subnet_range)) { $subnet_filter = " and User_auth.ip_int>=".$subnet_range['start']." and User_auth.ip_int<=".$subnet_range['stop']; }
    }

$enabled_filter='';
if ($enabled>0) {
    if ($enabled===2) { $enabled_filter = ' and User_auth.enabled=1'; }
    if ($enabled===1) { $enabled_filter = ' and User_auth.enabled=0'; }
    }


if (isset($_POST['ip'])) { $f_ip = $_POST['ip']; }
if (!isset($f_ip) and isset($_SESSION[$page_url]['ip'])) { $f_ip=$_SESSION[$page_url]['ip']; }
if (!isset($f_ip)) { $f_ip=''; }
$_SESSION[$page_url]['ip']=$f_ip;

$ip_where = '';
if (!empty($f_ip)) {
    if (checkValidIp($f_ip)) { $ip_where = " and ip_int=inet_aton('" . $f_ip . "') "; }
    if (checkValidMac($f_ip)) { $ip_where = " and mac='" . mac_dotted($f_ip) . "'  "; }
    $ip_list_filter = $ip_where;
    } else {
    $ip_list_filter = $ou_filter.$subnet_filter.$enabled_filter;
    }

print_ip_submenu($page_url);

?>
<div id="cont">
<form name="def" action="index.php" method="post">
<table class="data">
	<tr>
        <td>
        <b><?php print $list_ou; ?> - </b><?php print_ou_select($db_link, 'ou', $rou); ?>
        <b>Отображать:<?php print_row_at_pages('rows',$displayed); ?>
        <b><?php print $list_subnet; ?> - </b><?php print_subnet_select_office($db_link, 'subnet', $rsubnet); ?>
        <b>По активности - </b><?php print_enabled_select('enabled', $enabled); ?>
        <b>Поиск ip or mac:&nbsp<input type="text" name="ip" value="<?php echo $f_ip; ?>" pattern="^((([0-9]|[1-9][0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5])\.){3}([0-9]|[1-9][0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5])|([0-9A-Fa-f]{2}[:-]){5}[0-9A-Fa-f]{2}|([0-9a-fA-F]{4}[\\.-][0-9a-fA-F]{4}[\\.-][0-9a-fA-F]{4})|[0-9A-Fa-f]{12})$"/>
        <input type="submit" value="Показать">
        </td>
	</tr>
</table>

<?php
$countSQL="SELECT Count(*) FROM User_auth, User_list WHERE User_auth.user_id = User_list.id AND User_auth.deleted =0 $ip_list_filter";
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
<td>Для выделенных установить: Включен&nbsp<?php print_qa_select('a_enabled', 1); ?></td>
<td>Фильтр&nbsp<?php print_group_select($db_link, 'a_group_id', 0); ?></td>
<td>Шейпер&nbsp<?php print_queue_select($db_link, 'a_queue_id', 0); ?></td>
<td>Dhcp&nbsp<?php print_qa_select('a_dhcp', 1); ?></td>
<td>Dhcp-acl&nbsp<?php print_dhcp_acl_select('a_dhcp_acl',''); ?></td>
<td>Save traffic&nbsp<?php print_qa_select('a_traf',1); ?></td>
<td>&nbsp<input type="submit" onclick="return confirm('Применить для выделенных?')" name="ApplyForAll" value="Применить"></td>
<td align=right><input type="submit" onclick="return confirm('Удалить выделенных?')" name="removeauth" value="Удалить"></td>
</tr>
</table>

<table class="data">
	<tr>
        	<td align=Center><input type="checkbox" onClick="checkAll(this.checked);"></td>
		<td align=Center><?php print $sort_url . "&sort=login&order=$new_order>" . $cell_login . "</a>"; ?></td>
		<td align=Center><?php print $sort_url . "&sort=ip_int&order=$new_order>" . $cell_ip . "</a>"; ?></td>
		<td align=Center><?php print $sort_url . "&sort=mac&order=$new_order>" . $cell_mac . "</a>"; ?></td>
		<td align=Center><?php print $cell_comment; ?></td>
		<td align=Center><?php print $cell_dns_name; ?></td>
		<td align=Center><?php print $cell_enabled; ?></td>
		<td align=Center><?php print $cell_filter; ?></td>
		<td align=Center><?php print $cell_shaper; ?></td>
		<td align=Center><?php print $cell_traf; ?></td>
		<td align=Center><?php print $cell_dhcp; ?></td>
		<td align=Center><?php print $cell_acl; ?></td>
		<td align=Center><?php print $sort_url . "&sort=dhcp_time&order=$new_order>DHCP event</a>"; ?></td>
		<td align=Center><?php print $sort_url . "&sort=last_found&order=$new_order>Last</a>"; ?></td>
		<td align=Center><?php print $cell_connection; ?></td>
	</tr>
<?php

$sSQL = "SELECT User_auth.*, User_list.login FROM User_auth, User_list
WHERE User_auth.user_id = User_list.id AND User_auth.deleted =0 $ip_list_filter
ORDER BY $sort_table.$sort_field $order LIMIT $start,$displayed";

$users = get_records_sql($db_link,$sSQL);
foreach ($users as $user) {
    if ($user['dhcp_time'] == '0000-00-00 00:00:00') {
        $dhcp_str = '';
    } else {
        $dhcp_str = $user['dhcp_time'] . " (" . $user['dhcp_action'] . ")";
    }
    if ($user['last_found'] == '0000-00-00 00:00:00') { $user['last_found'] = ''; }
    print "<tr align=center>\n";
    $cl = "data";
    if (!$user['enabled']) { $cl = "warn"; }
    if ($user['blocked']) { $cl = "error"; }
    print "<td class=\"$cl\" style='padding:0'><input type=checkbox name=fid[] value=".$user['id']."></td>\n";
    print "<td class=\"$cl\" ><a href=/admin/users/edituser.php?id=".$user['user_id'].">" . $user['login'] . "</a></td>\n";
    print "<td class=\"$cl\" ><a href=/admin/users/editauth.php?id=".$user['id'].">" . $user['ip'] . "</a></td>\n";
    print "<td class=\"$cl\" >" . expand_mac($db_link,$user['mac']) . "</td>\n";
    if (isset($user['dhcp_hostname']) and strlen($user['dhcp_hostname']) > 0) {
        print "<td class=\"$cl\" width=200 >".$user['comments']." [" . $user['dhcp_hostname'] . "]</td>\n";
    } else {
        print "<td class=\"$cl\" width=200 >".$user['comments']."</td>\n";
    }
    print "<td class=\"$cl\" >".$user['dns_name']."</td>\n";
    print "<td class=\"$cl\" >" . get_qa($user['enabled']) . "</td>\n";
    print "<td class=\"$cl\" >" . get_group($db_link, $user['filter_group_id']) . "</td>\n";
    print "<td class=\"$cl\" >" . get_queue($db_link, $user['queue_id']) . "</td>\n";
    print "<td class=\"$cl\" >" . get_qa($user['save_traf']) . "</td>\n";
    print "<td class=\"$cl\" >" . get_qa($user['dhcp']) . "</td>\n";
    print "<td class=\"$cl\" >".$user['dhcp_acl']."</td>\n";
    print "<td class=\"$cl\" >".$dhcp_str."</td>\n";
    print "<td class=\"$cl\" >".$user['last_found']."</td>\n";
    print "<td class=\"$cl\" >" . get_connection($db_link, $user['id']) . "</td>\n";
    print "</tr>\n";
}
print "</table>\n";
print_navigation($page_url,$page,$displayed,$count_records[0],$total);
?>
<br>
<table class="data">
<tr><td>Цветовая маркировка</td></tr>
<tr>
<td class="warn">Пользователь выключен</td>
<td class="error">Блокировка по трафику</td>
</table>
<?php
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/footer.php");
?>
