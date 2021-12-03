<?php
$default_displayed=500;
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/auth.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/languages/" . $language . ".php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/header.php");
$default_sort='ip_int';
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/oufilter.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/subnetfilter.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/sortfilter.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/gatefilter.php");

$enabled=2;
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/enabledfilter.php");

if (isset($_POST["ApplyForAll"])) {
    $auth_id = $_POST["fid"];
    $n_enabled = $_POST["n_enabled"] * 1;
    $n_link = $_POST["n_enabled"] * 1;
    $n_handler = $_POST["n_handler"];
    $msg="Массовое изменение пользователей!";
    foreach ($auth_id as $key => $val) {
        if ($val) {
            unset($auth);
            $auth['nagios'] = $n_enabled;
            $auth['link_check'] = $n_link;
            $auth['nagios_handler'] = $n_handler;
            update_record($db_link, "User_auth", "id='" . $val . "'", $auth);
            }
        }
    LOG_WARNING($db_link,$msg);
    header("Location: " . $_SERVER["REQUEST_URI"]);
    exit;
    }

$sort_table = 'User_auth';
if ($sort_field == 'login') { $sort_table = 'User_list'; }
if ($sort_field == 'fio') { $sort_table = 'User_list'; }

$sort_url = "<a href=nagios.php?ou=" . $rou; 

if ($rou == 0) { $ou_filter = ''; } else { $ou_filter = " and User_list.ou_id=$rou "; }

if ($rsubnet == 0) { $subnet_filter = ''; } else {
    $subnet_range = get_subnet_range($db_link,$rsubnet);
    if (!empty($subnet_range)) { $subnet_filter = " and User_auth.ip_int>=".$subnet_range['start']." and User_auth.ip_int<=".$subnet_range['stop']; }
    }

$enabled_filter='';
if ($enabled>0) {
    if ($enabled===2) { $enabled_filter = ' and User_auth.nagios=1'; }
    if ($enabled===1) { $enabled_filter = ' and User_auth.nagios=0'; }
    }

$ip_list_filter = $ou_filter.$subnet_filter.$enabled_filter;

print_ip_submenu($page_url);

?>
<div id="cont">
<form name="def" action="nagios.php" method="post">
<table class="data">
	<tr>
        <td>
        <b><?php print $list_ou; ?> - </b><?php print_ou_select($db_link, 'ou', $rou); ?>
        <b>Отображать:<?php print_row_at_pages('rows',$displayed); ?>
        <b><?php print $list_subnet; ?> - </b><?php print_subnet_select_office($db_link, 'subnet', $rsubnet); ?>
        <b>Мониторится - </b><?php print_enabled_select('enabled', $enabled); ?>
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
<td>Для выделенных установить: Nagios&nbsp<?php print_qa_select('n_enabled', 1); ?></td>
<td>Lnk&nbsp<?php print_qa_select('n_link', 0); ?></td>
<td>Event-handler&nbsp<?php print_nagios_handler_select('n_handler', ''); ?></td>
<td>&nbsp<input type="submit" onclick="return confirm('Применить для выделенных?')" name="ApplyForAll" value="Применить"></td>
</tr>
</table>

<table class="data">
	<tr>
        	<td align=Center><input type="checkbox" onClick="checkAll(this.checked);"></td>
		<td align=Center><?php print $sort_url . "&sort=login&order=$new_order>" . $cell_login . "</a>"; ?></td>
		<td align=Center><?php print $sort_url . "&sort=ip_int&order=$new_order>" . $cell_ip . "</a>"; ?></td>
		<td align=Center><?php print $sort_url . "&sort=mac&order=$new_order>" . $cell_mac . "</a>"; ?></td>
		<td align=Center><?php print $cell_comment; ?></td>
		<td align=Center><?php print $cell_wikiname; ?></td>
		<td align=Center><?php print $sort_url . "&sort=nagios&order=$new_order>" . $cell_nagios; ?></td>
		<td align=Center><?php print $sort_url . "&sort=link_check&order=$new_order>" . $cell_link; ?></td>
		<td align=Center><?php print $cell_nagios_handler; ?></td>
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
    if ($user['nagios_status'] == "UP") { $cl = "up"; }
    if ($user['nagios_status'] == "DOWN") { $cl = "down"; }
    if (!$user['nagios']) { $cl = "data"; }

    print "<td class=\"$cl\" style='padding:0'><input type=checkbox name=fid[] value=".$user['id']."></td>\n";
    print "<td class=\"$cl\" ><a href=/admin/users/edituser.php?id=".$user['user_id'].">" . $user['login'] . "</a></td>\n";
    print "<td class=\"$cl\" ><a href=/admin/users/editauth.php?id=".$user['id'].">" . $user['ip'] . "</a></td>\n";
    print "<td class=\"$cl\" >" . expand_mac($db_link,$user['mac']) . "</td>\n";
    if (isset($user['dhcp_hostname']) and strlen($user['dhcp_hostname']) > 0) {
        print "<td class=\"$cl\" width=200>".$user['comments']." [" . $user['dhcp_hostname'] . "]</td>\n";
    } else {
        print "<td class=\"$cl\" width=200>".$user['comments']."</td>\n";
    }
    if (!empty($user['WikiName'])) {
        $wiki_url = rtrim(get_option($db_link, 60),'/');
        if (preg_match('/127.0.0.1/', $wiki_url)) { print "<td class=\"$cl\" ></td>\n"; } else {
            $wiki_web = rtrim(get_option($db_link, 63),'/');
            $wiki_web = ltrim($wiki_web,'/');
            $wiki_link = $wiki_url.'/'.$wiki_web.'/'.$user['WikiName'];
            print "<td class=\"$cl\" >"; print_url($user['WikiName'],$wiki_link); print "</td>\n";
            }
        } else {
        print "<td class=\"$cl\" ></td>\n";
        }

    if (!empty($user['nagios']) and $user['nagios']) {
        $nagios_url = rtrim(get_option($db_link, 57),'/');
        if (preg_match('/127.0.0.1/', $nagios_url)) { print "<td class=\"$cl\" >". get_qa($user['nagios']) ."</td>\n"; } else {
            $nagios_link = $nagios_url.'/cgi-bin/status.cgi?host='.get_nagios_name($user);
            print "<td class=\"$cl\" >"; print_url(get_qa($user['nagios']),$nagios_link); print "</td>\n";
            }
        } else {
        print "<td class=\"$cl\" >" . get_qa($user['nagios']) . "</td>\n";
        }

    print "<td class=\"$cl\" >" . get_qa($user['link_check']) . "</td>\n";
    print "<td class=\"$cl\" >".$user['nagios_handler']."</td>\n";
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
<td class="up">Хост включен</td>
<td class="down">Хост недоступен</td>
<td class="data">Неизвестно</td>
</table>
<?php
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/footer.php");
?>
