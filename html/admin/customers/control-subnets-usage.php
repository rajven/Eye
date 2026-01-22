<?php

require_once ($_SERVER['DOCUMENT_ROOT']."/inc/auth.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/languages/" . HTML_LANG . ".php");
unset($_POST);
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/header.php");
print_control_submenu($page_url);
$zombi_days = get_option($db_link, 35);
// Создаём временную метку: сейчас минус $zombi_days дней
$zombi_threshold = date('Y-m-d H:i:s', strtotime("-$zombi_days days"));
?>
<div id="cont">
<br>
<b><?php echo WEB_network_usage_title; ?></b> <br>
<table class="data">
<tr align="center">
	<td><b><?php echo WEB_network_subnet; ?></b></td>
	<td><b><?php echo WEB_network_all_ip; ?></b></td>
	<td><b><?php echo WEB_network_used_ip; ?></b></td>
	<td><b><?php echo WEB_network_free_ip; ?></b></td>
	<td><b><?php echo WEB_network_dhcp_size; ?></b></td>
	<td><b><?php echo WEB_network_dhcp_used; ?></b></td>
	<td><b><?php echo WEB_network_dhcp_free; ?></b></td>
	<td><b><?php echo WEB_network_static_free; ?></b></td>
	<td><b><?php echo WEB_network_zombi; ?><br><?php print "(> ".$zombi_days." ".WEB_days.")"; ?></b></td>
	<td><b><?php echo WEB_network_zombi_dhcp; ?><br><?php print "(> ".$zombi_days." ".WEB_days.")"; ?></b></td>
</tr>
<?php
$t_subnets = get_records_SQL($db_link, 'SELECT * FROM subnets WHERE office=1 ORDER BY ip_int_start');
if (!empty($t_subnets)) {
    foreach ($t_subnets as $row) {
        print "<tr align=center>\n";
        $cl = "data";

        // Subnet
        print "<td class=\"$cl\"><a href=/admin/iplist/index.php?ou=0&cidr=" . htmlspecialchars($row['subnet']) . ">" . htmlspecialchars($row['subnet']) . "</a></td>\n";

        // Total IPs
        $subnet_ips = max(0, $row['ip_int_stop'] - $row['ip_int_start'] + 1);
        if ($subnet_ips > 4 ) { $all_ips = $subnet_ips - 3; } else { $all_ips = $subnet_ips; }
        print "<td class=\"$cl\">" . $all_ips . "</td>\n";

        // Used (total)
        $used_all = get_count_records($db_link, 'user_auth', 'deleted = 0 AND ip_int >= ? AND ip_int <= ?', [$row['ip_int_start'], $row['ip_int_stop']]);
        $total_used_percent = ($all_ips > 0) ? ($used_all / $all_ips) * 100 : 0;
        $total_class = '';
        if ($total_used_percent >= 95) {
            $total_class = 'error';
        } elseif ($total_used_percent >= 85) {
            $total_class = 'warn';
        }
        print "<td class=\"" . ($total_class ?: $cl) . "\">" . $used_all . "</td>\n";

        // Free (total)
        $free_all = max(0, $all_ips - $used_all);

        $free_total_class = '';
        if ($all_ips > 0) {
            $free_total_percent = ($free_all / $all_ips) * 100;
            if ($free_total_percent <= 5) {
                $free_total_class = 'error';
            } elseif ($free_total_percent <= 15) {
                $free_total_class = 'warn';
            }
        }
        print "<td class=\"" . ($free_total_class ?: $cl) . "\">" . $free_all . "</td>\n";

        // DHCP pool size
        $dhcp_pool = max(0, $row['dhcp_stop'] - $row['dhcp_start'] + 1);
        print "<td class=\"$cl\">" . $dhcp_pool . "</td>\n";

        // Used (DHCP)
        $used_dhcp = 0;
        if ($dhcp_pool > 0) {
            $used_dhcp = get_count_records($db_link, 'user_auth', 'deleted = 0 AND ip_int >= ? AND ip_int <= ?', [$row['dhcp_start'], $row['dhcp_stop']]);
        }
        $dhcp_used_percent = ($dhcp_pool > 0) ? ($used_dhcp / $dhcp_pool) * 100 : 0;
        $dhcp_used_class = '';
        if ($dhcp_used_percent >= 95) {
            $dhcp_used_class = 'error';
        } elseif ($dhcp_used_percent >= 85) {
            $dhcp_used_class = 'warn';
        }
        print "<td class=\"" . ($dhcp_used_class ?: $cl) . "\">" . $used_dhcp . "</td>\n";

        // Free (DHCP)
        $free_dhcp = max(0, $dhcp_pool - $used_dhcp);
        $free_dhcp_class = '';

        if ($dhcp_pool > 0) {
            $free_dhcp_percent = ($free_dhcp / $dhcp_pool) * 100;
            if ($free_dhcp_percent <= 5) {
                $free_dhcp_class = 'error';
            } elseif ($free_dhcp_percent <= 15) {
                $free_dhcp_class = 'warn';
            }
        }
        print "<td class=\"" . ($free_dhcp_class ?: $cl) . "\">" . $free_dhcp . "</td>\n";

        // Free static
        $free_static = max ( 0, $free_all - $free_dhcp);
        print "<td class=\"$cl\">" . $free_static . "</td>\n";

        // Zombie (total) — от общего размера подсети
        $zombi_total = get_count_records($db_link, 'user_auth', 'deleted = 0 AND ip_int >= ? AND ip_int <= ? AND last_found <= ?', [$row['ip_int_start'], $row['ip_int_stop'], $zombi_threshold]);
        $zombi_total_class = '';
        if ($all_ips > 0) {
            $zombi_total_ratio = ($zombi_total / $all_ips) * 100;
            if ($zombi_total_ratio >= 30) {
                $zombi_total_class = 'error';
            } elseif ($zombi_total_ratio >= 20) {
                $zombi_total_class = 'warn';
            }
        }
        print "<td class=\"" . ($zombi_total_class ?: $cl) . "\">" . $zombi_total . "</td>\n";

        // Zombie (DHCP) — от размера DHCP-пула
        $zombi_dhcp = get_count_records($db_link, 'user_auth', 'deleted = 0 AND ip_int >= ? AND ip_int <= ? AND last_found <= ?', [$row['dhcp_start'], $row['dhcp_stop'], $zombi_threshold]);
        $zombi_dhcp_class = '';
        if ($dhcp_pool > 0) {
            $zombi_dhcp_ratio = ($zombi_dhcp / $dhcp_pool) * 100;
            if ($zombi_dhcp_ratio >= 30) {
                $zombi_dhcp_class = 'error';
            } elseif ($zombi_dhcp_ratio >= 20) {
                $zombi_dhcp_class = 'warn';
            }
        }
        print "<td class=\"" . ($zombi_dhcp_class ?: $cl) . "\">" . $zombi_dhcp . "</td>\n";

        print "</tr>\n";
    }
}
?>
</table>
<?php require_once ($_SERVER['DOCUMENT_ROOT']."/inc/footer.php"); ?>
