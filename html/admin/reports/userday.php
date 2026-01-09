<?php
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/auth.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/languages/" . HTML_LANG . ".php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/header.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/idfilter.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/datetimefilter.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/oufilter.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/gatefilter.php");

$user=get_record_sql($db_link,'SELECT * FROM user_list WHERE id=?', [ $id ]);

?>

<div id="cont">
<b><?php print "Трафик пользователя <a href=../users/edituser.php?id=$id>" . $user['login'] . "</a>"; ?></b>
<br>
<form action="<?php print $_SERVER['PHP_SELF']; ?>" method="post">
<input type="hidden" name="id" value=<?php echo $id; ?>>
<?php print_date_fields($date1,$date2,$date_shift); ?>
<?php echo WEB_cell_gateway; ?>:&nbsp<?php print_gateway_select($db_link, 'gateway', $rgateway); ?>
<input type="submit" value="<?php echo WEB_btn_show; ?>">
</form>

<br>
<table class="data" width='100%'>
<tr align="center">
<td class="data"><b><?php print WEB_title_ip; ?></b></td>
<td class="data"><b><?php print WEB_cell_gateway; ?></b></td>
<td class="data"><b><?php print WEB_title_date; ?></b></td>
<td class="data"><b><?php print WEB_title_input; ?></b></td>
<td class="data"><b><?php print WEB_title_output; ?></b></td>
</tr>

<?php

$gateway_list = get_gateways($db_link);

$sSQL = "SELECT id,ip,description FROM user_auth WHERE (user_auth.user_id=?) ORDER BY IP";
$usersip = get_records_sql($db_link, $sSQL, [ $id ]);

$ipcount = 0;
$itog_in = 0;
$itog_out = 0;

// Определяем тип СУБД один раз (лучше вынести выше, но для примера — здесь)
$db_type = $db_link->getAttribute(PDO::ATTR_DRIVER_NAME);

foreach ($usersip as $row) {
    $fid = (int)$row["id"];
    $fip = $row["ip"];
    $fcomm = $row["description"];

    $params = [$date1, $date2];
    $conditions = ["user_stats.ts >= ?", "user_stats.ts < ?"];
    
    if (!empty($rgateway) && $rgateway > 0) {
        $conditions[] = "user_stats.router_id = ?";
        $params[] = (int)$rgateway;
    }
    $conditions[] = "auth_id = ?";
    $params[] = $fid;

    $whereClause = implode(' AND ', $conditions);
    $sSQL = "SELECT SUM(byte_in) + SUM(byte_out) AS t_sum FROM user_stats WHERE $whereClause";
    $day_summary = get_record_sql($db_link, $sSQL, $params);
    $summ = !empty($day_summary) ? (float)($day_summary['t_sum'] ?? 0) : 0;

    if ($summ > 0) {
        $ipcount++;
        print "<tr align=center class=\"tr1\" onmouseover=\"className='tr2'\" onmouseout=\"className='tr1'\">\n";
        print "<td class=\"data\" ><b><a href=/admin/users/editauth.php?id=$fid>$fip</a></b></td>\n";
        print "<td class=\"data\" colspan=2>$fcomm</td>\n";
        print "<td class=\"data\" ><a href=/admin/reports/userdaydetail.php?id=$fid&date_start=$date1&date_stop=$date2>TOP 10</a></td>\n";
        print "<td class=\"data\" ><a href=/admin/reports/userdaydetaillog.php?id=$fid&date_start=$date1&date_stop=$date2>".WEB_report_detail."</a></td>\n";
        print "</tr>\n";

        // === 2. Формат даты в зависимости от СУБД ===
        if ($days_shift <= 1) {
            $mysql_format = '%Y-%m-%d %H';
            $pg_format    = 'YYYY-MM-DD HH24';
        } elseif ($days_shift <= 30) {
            $mysql_format = '%Y-%m-%d';
            $pg_format    = 'YYYY-MM-DD';
        } elseif ($days_shift <= 730) {
            $mysql_format = '%Y-%m';
            $pg_format    = 'YYYY-MM';
        } else {
            $mysql_format = '%Y';
            $pg_format    = 'YYYY';
        }

        // === 3. Параметры для детального запроса ===
        $detail_params = [$date1, $date2, $fid];
        $detail_conditions = "user_stats.ts >= ? AND user_stats.ts < ? AND auth_id = ?";

        if ($rgateway > 0) {
            $detail_conditions .= " AND user_stats.router_id = ?";
            $detail_params[] = (int)$rgateway;
        }

        // === 4. Запрос в зависимости от СУБД ===
        if ($db_type === 'mysql') {
            $date_expr = "DATE_FORMAT(user_stats.ts, '$mysql_format')";
            $sSQL = "
                SELECT 
                    user_stats.router_id,
                    $date_expr AS tHour,
                    SUM(byte_in) AS byte_in_sum,
                    SUM(byte_out) AS byte_out_sum
                FROM user_stats
                WHERE $detail_conditions
                GROUP BY $date_expr, user_stats.router_id
                ORDER BY tHour" . ($rgateway > 0 ? '' : ', user_stats.router_id');
        } elseif ($db_type === 'pgsql') {
            $date_expr = "TO_CHAR(user_stats.ts, '$pg_format')";
            $sSQL = "
                SELECT 
                    user_stats.router_id,
                    $date_expr AS tHour,
                    SUM(byte_in) AS byte_in_sum,
                    SUM(byte_out) AS byte_out_sum
                FROM user_stats
                WHERE $detail_conditions
                GROUP BY $date_expr, user_stats.router_id
                ORDER BY tHour" . ($rgateway > 0 ? '' : ', user_stats.router_id');
        } else {
            throw new Exception("Unsupported DB: $db_type");
        }

        $userdata = get_records_sql($db_link, $sSQL, $detail_params);

        $sum_in = 0;
        $sum_out = 0;
        foreach ($userdata as $userrow) {
            print "<tr align=center class=\"tr1\" onmouseover=\"className='tr2'\" onmouseout=\"className='tr1'\">\n";
            print "<td class=\"data\"> </td>\n";
            print "<td class=\"data\">" . $gateway_list[$userrow['router_id']] . "</td>\n";
            print "<td class=\"data\">" . $userrow['tHour'] . "</td>\n";
            print "<td class=\"data\">" . fbytes($userrow['byte_in_sum']) . "</td>\n";
            print "<td class=\"data\">" . fbytes($userrow['byte_out_sum']) . "</td>\n";
            print "</tr>\n";
            $sum_in += $userrow['byte_in_sum'];
            $sum_out += $userrow['byte_out_sum'];
        }
        print "<tr align=center class=\"tr1\" onmouseover=\"className='tr2'\" onmouseout=\"className='tr1'\">\n";
        print "<td class=\"data\"><b>" . WEB_title_sum . "</b></td>\n";
        print "<td class=\"data\"><b> </b></td>\n";
        print "<td class=\"data\"><b> </b></td>\n";
        print "<td class=\"data\"><b>" . fbytes($sum_in) . "</b></td>\n";
        print "<td class=\"data\"><b>" . fbytes($sum_out) . "</b></td>\n";
        print "</tr>\n";
        $itog_in += $sum_in;
        $itog_out += $sum_out;
    }
}
if ($ipcount > 1) {
    print "<tr align=center class=\"tr1\" onmouseover=\"className='tr2'\" onmouseout=\"className='tr1'\">\n";
    print "<td class=\"data\"><b>".WEB_title_itog."</b></td>\n";
    print "<td class=\"data\"><b> </b></td>\n";
    print "<td class=\"data\"><b> </b></td>\n";
    print "<td class=\"data\"><b>" . fbytes($itog_in) . "</b></td>\n";
    print "<td class=\"data\"><b>" . fbytes($itog_out) . "</b></td>\n";
    print "</tr>\n";
}
?>
</table>
<?php
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/footer.php");
?>