<?php
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/auth.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/languages/" . HTML_LANG . ".php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/header.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/datetimefilter.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/gatefilter.php");

print_reports_submenu($page_url);

function print_gateway_statistics($db, $device_id, $device_name, $date1, $date2) {
    $start_time = new DateTimeImmutable($date1);
    $stop_time = new DateTimeImmutable($date2);
    $interval = $stop_time->diff($start_time, true);
    $delta = (int)$interval->format("%a");

    // === Определяем СУБД ===
    $db_type = $db->getAttribute(PDO::ATTR_DRIVER_NAME);

    // === Формат даты в зависимости от СУБД и периода ===
    if ($delta == 1) {
        $mysql_format = '%Y-%m-%d %H:00:00';
        $pg_format    = 'YYYY-MM-DD HH24:00:00';
    } elseif ($delta > 1 && $delta <= 31) {
        $mysql_format = '%Y-%m-%d';
        $pg_format    = 'YYYY-MM-DD';
    } else {
        $mysql_format = '%Y-%m';
        $pg_format    = 'YYYY-MM';
    }

    $l3_interfaces = get_wan_interfaces($db, $device_id);

    $global_int_in = $global_int_out = $global_int_f_in = $global_int_f_out = 0;

    echo "<tr><td class=\"info\" colspan=\"5\"><b>" . htmlspecialchars($device_name, ENT_QUOTES, 'UTF-8') . "</b></td></tr>\n";

    foreach ($l3_interfaces as $row) {
        $name = htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8');
        $desc = !empty($row['description']) 
            ? ' (' . htmlspecialchars($row['description'], ENT_QUOTES, 'UTF-8') . ')' 
            : '';

        echo "<tr><td class=\"data\" colspan=\"5\"><b>{$name}{$desc}</b></td></tr>\n";
        echo "<tr class=\"info\">\n";
        echo "<td>" . WEB_date . "</td>\n";
        echo "<td>" . WEB_title_input . "</td>\n";
        echo "<td>" . WEB_title_output . "</td>\n";
        echo "<td>" . WEB_title_forward_input . "</td>\n";
        echo "<td>" . WEB_title_forward_output . "</td>\n";
        echo "</tr>\n";

        // === Параметризованный запрос ===
        $params = [$device_id, $row['snmpin'], $date1, $date2];

        if ($db_type === 'mysql') {
            $date_expr = "DATE_FORMAT(ts, '$mysql_format')";
            $sql = "
                SELECT 
                    $date_expr AS dt,
                    SUM(bytes_in) AS byte_in,
                    SUM(bytes_out) AS byte_out,
                    SUM(forward_in) AS byte_f_in,
                    SUM(forward_out) AS byte_f_out
                FROM wan_stats
                WHERE router_id = ? AND interface_id = ? AND ts >= ? AND ts < ?
                GROUP BY $date_expr
                ORDER BY dt";
        } elseif ($db_type === 'pgsql') {
            $date_expr = "TO_CHAR(ts, '$pg_format')";
            $sql = "
                SELECT 
                    $date_expr AS dt,
                    SUM(bytes_in) AS byte_in,
                    SUM(bytes_out) AS byte_out,
                    SUM(forward_in) AS byte_f_in,
                    SUM(forward_out) AS byte_f_out
                FROM wan_stats
                WHERE router_id = ? AND interface_id = ? AND ts >= ? AND ts < ?
                GROUP BY $date_expr
                ORDER BY dt";
        } else {
            throw new Exception("Unsupported DB: $db_type");
        }

        $int_statistics = get_records_sql($db, $sql, $params);

        $int_in = $int_out = $int_f_in = $int_f_out = 0;

        foreach ($int_statistics as $stat) {
            echo "<tr align=\"center\" class=\"tr1\" onmouseover=\"className='tr2'\" onmouseout=\"className='tr1'\">\n";
            echo "<td class=\"data\">" . htmlspecialchars($stat['dt'], ENT_QUOTES, 'UTF-8') . "</td>\n";
            echo "<td class=\"data\">" . fbytes($stat['byte_in']) . "</td>\n";
            echo "<td class=\"data\">" . fbytes($stat['byte_out']) . "</td>\n";
            echo "<td class=\"data\">" . fbytes($stat['byte_f_in']) . "</td>\n";
            echo "<td class=\"data\">" . fbytes($stat['byte_f_out']) . "</td>\n";
            echo "</tr>\n";

            $int_in += $stat['byte_in'];
            $int_out += $stat['byte_out'];
            $int_f_in += $stat['byte_f_in'];
            $int_f_out += $stat['byte_f_out'];
        }

        echo "<tr align=\"center\" class=\"tr1\" onmouseover=\"className='tr2'\" onmouseout=\"className='tr1'\">\n";
        echo "<td class=\"data\"><b>" . WEB_title_itog . "</b></td>\n";
        echo "<td class=\"data\">" . fbytes($int_in) . "</td>\n";
        echo "<td class=\"data\">" . fbytes($int_out) . "</td>\n";
        echo "<td class=\"data\">" . fbytes($int_f_in) . "</td>\n";
        echo "<td class=\"data\">" . fbytes($int_f_out) . "</td>\n";
        echo "</tr>\n";

        $global_int_in += $int_in;
        $global_int_out += $int_out;
        $global_int_f_in += $int_f_in;
        $global_int_f_out += $int_f_out;
    }

    echo "<tr align=\"center\" class=\"tr1\" onmouseover=\"className='tr2'\" onmouseout=\"className='tr1'\">\n";
    echo "<td class=\"data\" colspan=\"5\"><b>" . WEB_title_itog . "</b></td></tr>\n";
    echo "<tr align=\"center\" class=\"tr1\" onmouseover=\"className='tr2'\" onmouseout=\"className='tr1'\">\n";
    echo "<td class=\"data\"></td>\n";
    echo "<td class=\"data\">" . fbytes($global_int_in) . "</td>\n";
    echo "<td class=\"data\">" . fbytes($global_int_out) . "</td>\n";
    echo "<td class=\"data\">" . fbytes($global_int_f_in) . "</td>\n";
    echo "<td class=\"data\">" . fbytes($global_int_f_out) . "</td>\n";
    echo "</tr>\n";
}

?>
<div id="cont">

<form action="wan.php" method="post">
<?php print_date_fields($date1,$date2,$date_shift); ?>
<?php echo WEB_cell_gateway; ?>:&nbsp<?php print_gateway_select($db_link, 'gateway', $rgateway); ?>
<input type="submit" value="<?php echo WEB_btn_show; ?>">
</form>

<br>
<br>
<table class="data">

<?php

if ($rgateway==0) {
    $gateways = get_gateways($db_link);
    foreach ($gateways as $key => $val) {
        print_gateway_statistics($db_link,$key,$val,$date1,$date2);
        }
    } else {
        $router = get_record_sql($db_link,"SELECT device_name FROM devices WHERE id=?", [ $rgateway ]);
        print_gateway_statistics($db_link,$rgateway,$router['device_name'],$date1,$date2);
    }

?>

</table>

<?php
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/footer.php");
?>
