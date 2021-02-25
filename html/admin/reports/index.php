<?php
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/auth.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/sqlt.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/languages/" . $language . ".php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/header.php");
$default_date_shift='d';
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/datefilter.php");
$default_sort='in';
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/oufilter.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/sortfilter.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/gatefilter.php");
?>
<div id="cont">
<b>Трафик пользователей</b>
<form action="index.php" method="post">
Группа:&nbsp<?php print_ou_select($db_link,'ou',$rou); ?>
Начало:&nbsp<input type="date" name="date_start" value="<?php echo $date1; ?>" />
Конец:&nbsp<input type="date" name="date_stop" value="<?php echo $date2; ?>" />
Шлюз:&nbsp<?php print_gateway_select($db_link, 'gateway', $rgateway); ?>
<input type="submit" value="OK">
</form>

<?php
print "<br><br>\n";
print "<table class=\"data\" width=\"650\" cellspacing=\"1\" cellpadding=\"4\">\n";
print "<tr align=\"center\">\n";
print "<td ><b><a href=index.php?sort=login&order=$new_order>Логин</a></b></td>\n";
print "<td ><b>Gate</b></td>\n";
print "<td ><b><a href=index.php?sort=in&order=$new_order>Входящий</a></b></td>\n";
print "<td ><b><a href=index.php?sort=out&order=$new_order>Исходящий<a></b></td>\n";
print "</tr>\n";

$gateway_list = get_gateways($db_link);

$trafSQL = "SELECT User_stats.auth_id, User_stats.router_id, SUM( byte_in ) AS tin, SUM( byte_out ) AS tout 
FROM User_stats WHERE (date(User_stats.timestamp)>='$date1') AND (date(User_stats.timestamp)<'$date2') AND (byte_in>0 or byte_out>0)";

if ($rgateway == 0) {
    $trafSQL = $trafSQL . " GROUP by User_stats.auth_id,User_stats.router_id";
} else {
    $trafSQL = $trafSQL . " AND User_stats.router_id=$rgateway GROUP by User_stats.auth_id,User_stats.router_id";
}

$userSQL = "SELECT User_list.login as login, User_auth.user_id as user_id, User_auth.id as auth_id FROM User_list,User_auth WHERE User_list.id=User_auth.user_id";
if ($rou == 0) {
    $userSQL = $userSQL . " ORDER BY User_auth.id";
} else {
    $userSQL = $userSQL . " AND User_list.ou_id=$rou ORDER BY User_auth.id";
}

$users = mysqli_query($db_link, $userSQL);

while (list ($f_login, $f_user_id, $f_auth_id) = mysqli_fetch_array($users)) {
    $user_ref[$f_auth_id][id] = $f_user_id;
    $user_ref[$f_auth_id][login] = $f_login;
}

$total_in = 0;
$total_out = 0;

unset($user_traf);
$traf = mysqli_query($dbt_link, $trafSQL);

while (list ($s_auth_id, $s_router_id, $traf_day_in, $traf_day_out) = mysqli_fetch_array($traf)) {
    if (! isset($user_ref{$s_auth_id}{id})) { continue; }
    if ($traf_day_in + $traf_day_out > 0) {
        $u_id = $user_ref[$s_auth_id][id];
        if (! isset($user_traf[$u_id][$s_router_id][in])) {
            $user_traf[$u_id][$s_router_id][in] = 0;
        }
        if (! isset($user_traf[$u_id][$s_router_id][out])) {
            $user_traf[$u_id][$s_router_id][out] = 0;
        }
        if (! isset($user_traf[$u_id][$s_router_id][login])) {
            $user_traf[$u_id][$s_router_id][login] = $user_ref[$s_auth_id][login];
        }
        $user_traf[$u_id][$s_router_id][in] += $traf_day_in;
        $user_traf[$u_id][$s_router_id][out] += $traf_day_out;
        $total_in += $traf_day_in;
        $total_out += $traf_day_out;
    }
}

$tmp_table = "month_stats_" . $_SESSION['session_id'];
$tSQL = "CREATE TEMPORARY TABLE $tmp_table (`id` int(11) unsigned NOT NULL,`login` varchar(50) not null,`router` varchar(50) not null, `in` bigint, `out` bigint) DEFAULT CHARSET=utf8";
mysqli_query($db_link, $tSQL);
foreach ($user_traf as $u_id => $user_stats) {
    foreach ($user_stats as $s_router_id => $user_info) {
        $tSQL = "insert into $tmp_table (`id`,`login`,`router`,`in`,`out`) values('$u_id','$user_info[login]','$gateway_list[$s_router_id]','$user_info[in]','$user_info[out]')";
        $result = mysqli_query($db_link, $tSQL);
    }
}

$tSQL = "Select `id`,`login`,`router`,`in`,`out` from `$tmp_table` order by `$sort_field` $order";
$user_stats = mysqli_query($db_link, $tSQL);

while (list ($s_id, $s_login, $s_router, $s_in, $s_out) = mysqli_fetch_array($user_stats)) {
    $cl = "data";
    if ($s_out > 2 * $s_in) { $cl = "nb"; }
    print "<tr align=center align=center class=\"tr1\" onmouseover=\"className='tr2'\" onmouseout=\"className='tr1'\">\n";
    print "<td class=\"$cl\"><a href=userday.php?id=$s_id&date_start=$date1&date_stop=$date2>$s_login</a></td>\n";
    print "<td class=\"$cl\">$s_router</td>\n";
    print "<td class=\"$cl\">" . fbytes($s_in) . "</td>\n";
    print "<td class=\"$cl\">" . fbytes($s_out) . "</td>\n";
    print "</tr>\n";
}
print "<tr align=center align=center class=\"tr1\" onmouseover=\"className='tr2'\" onmouseout=\"className='tr1'\">\n";
print "<td class=\"data\" colspan=2><b>Итого</b></td>\n";
print "<td class=\"data\"><b>" . fbytes($total_in) . "</b></td>\n";
print "<td class=\"data\"><b>" . fbytes($total_out) . "</b></td>\n";
print "</tr>\n";
?>
  </table>
<?
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/footer.php");
?>
