<?php

require_once($_SERVER['DOCUMENT_ROOT'] . "/inc/auth.php");
require_once($_SERVER['DOCUMENT_ROOT'] . "/inc/languages/" . HTML_LANG . ".php");

if (isset($_POST["s_remove"])) {
    if (!empty($_POST["s_id"])) {
        $s_id = $_POST["s_id"];
        foreach ($s_id as $key => $net_id) {
            if (isset($net_id)) {
                LOG_INFO($db_link, "Remove subnet id: $net_id");
                delete_record($db_link, "subnets", "id=" . $net_id);
            }
        }
    }
    header("Location: " . $_SERVER["REQUEST_URI"]);
    exit;
}

if (isset($_POST['s_save'])) {
    if (!empty($_POST["s_id"])) {
        $s_id = $_POST["s_id"];
        foreach ($s_id as $key => $net_id) {
            if (isset($net_id)) {
                $new['subnet'] = trim($_POST['s_subnet'][$net_id]);
                $new['office'] = $_POST['s_office'][$net_id] * 1;
                $new['hotspot'] = $_POST['s_hotspot'][$net_id] * 1;
                $new['vpn'] = $_POST['s_vpn'][$net_id] * 1;
                $new['free'] = $_POST['s_free'][$net_id] * 1;
                $new['dhcp'] = $_POST['s_dhcp'][$net_id] * 1;
                $new['dhcp_lease_time'] = $_POST['s_lease_time'][$net_id] * 1;
                $new['static'] = $_POST['s_static'][$net_id] * 1;
                $new['discovery'] = $_POST['s_discovery'][$net_id] * 1;
                $new['dhcp_update_hostname'] = $_POST['s_dhcp_update'][$net_id] * 1;
                $new['comment'] = trim($_POST['s_comment'][$net_id]);
                $range = cidrToRange($new['subnet']);
                $first_user_ip = $range[0];
                $last_user_ip = $range[1];
                $cidr = $range[2][1];
                if (isset($cidr) and $cidr <= 32) {
                    $new['subnet'] = $first_user_ip . '/' . $cidr;
                } else {
                    $new['subnet'] = '';
                }
                $new['ip_int_start'] = ip2long($first_user_ip);
                $new['ip_int_stop'] = ip2long($last_user_ip);
                $new['dhcp_start'] = ip2long(trim($_POST['s_dhcp_start'][$net_id]));
                $new['dhcp_stop'] = ip2long(trim($_POST['s_dhcp_stop'][$net_id]));
                $dhcp_fail = 0;
                if (!isset($new['dhcp_start']) or $new['dhcp_start'] == 0) {
                    $dhcp_fail = 1;
                }
                if (!isset($new['dhcp_stop']) or $new['dhcp_stop'] == 0) {
                    $dhcp_fail = 1;
                }
                if (!$dhcp_fail and ($new['dhcp_start'] - $new['ip_int_stop'] >= 0)) {
                    $dhcp_fail = 1;
                }
                if (!$dhcp_fail and ($new['dhcp_start'] - $new['ip_int_start'] <= 0)) {
                    $dhcp_fail = 1;
                }
                if (!$dhcp_fail and ($new['dhcp_stop'] - $new['ip_int_stop'] >= 0)) {
                    $dhcp_fail = 1;
                }
                if (!$dhcp_fail and ($new['dhcp_stop'] - $new['ip_int_start'] <= 0)) {
                    $dhcp_fail = 1;
                }
                if (!$dhcp_fail and ($new['dhcp_start'] - $new['dhcp_stop'] >= 0)) {
                    $dhcp_fail = 1;
                }
                if ($dhcp_fail) {
                    $new['dhcp_start'] = ip2long($range[3]);
                    $new['dhcp_stop'] = ip2long($range[4]);
                }
                $gateway = ip2long(trim($_POST['s_gateway'][$net_id]));
                if (!isset($gateway)) {
                    $gateway = $range[5];
                }
                $new['gateway'] = $gateway;
                if ($new['hotspot']) {
                    $new['dhcp_update_hostname'] = 0;
                    $new['discovery'] = 0;
                    $new['vpn'] = 0;
                }
                if ($new['vpn']) {
                    $new['discovery'] = 0;
                    $new['dhcp'] = 0;
                }
                if ($new['office']) {
                    $new['free'] = 0;
                }
                if (!$new['office']) {
                    $new['discovery'] = 0;
                    $new['dhcp'] = 0;
                    $new['static'] = 0;
                    $new['dhcp_update_hostname'] = 0;
                    $new['gateway'] = 0;
                    $new['dhcp_start'] = 0;
                    $new['dhcp_stop'] = 0;
                }
                update_record($db_link, "subnets", "id='$net_id'", $new);
            }
        }
    }
    header("Location: " . $_SERVER["REQUEST_URI"]);
    exit;
}

if (isset($_POST["s_create"])) {
    $new_subnet = $_POST["s_create_subnet"];
    if (isset($new_subnet)) {
        $new['subnet'] = trim($new_subnet);
        $range = cidrToRange($new['subnet']);
        $first_user_ip = $range[0];
        $last_user_ip = $range[1];
        $cidr = $range[2][1];
        if (isset($cidr) and $cidr < 32) {
            $ip = $first_user_ip . '/' . $cidr;
        } else {
            $ip = $first_user_ip;
        }
        $new['ip_int_start'] = ip2long($first_user_ip);
        $new['ip_int_stop'] = ip2long($last_user_ip);
        $new['dhcp_start'] = ip2long($range[3]);
        $new['dhcp_stop'] = ip2long($range[4]);
        $new['gateway'] = ip2long($range[5]);
        LOG_INFO($db_link, "Create new subnet $new_subnet");
        insert_record($db_link, "subnets", $new);
    }
    header("Location: " . $_SERVER["REQUEST_URI"]);
    exit;
}

unset($_POST);

fix_auth_rules($db_link);

require_once($_SERVER['DOCUMENT_ROOT'] . "/inc/header.php");

print_control_submenu($page_url);

?>
<div id="cont">
    <br>
    <form name="def" action="control-subnets.php" method="post">
    <div>
        <?php print WEB_network_create . "&nbsp:<input type=\"text\" name='s_create_subnet' value=''>"; ?>
        <input type="submit" name="s_create" value="<?php echo WEB_btn_add; ?>">
        <button class="button_right" name='s_save' value='save'><?php print WEB_btn_save; ?></button>
        <input type="submit" onclick="return confirm('<?php print WEB_btn_delete; ?>?')" name="s_remove" value="<?php print WEB_btn_remove; ?>">
    </div>
        <b><?php echo WEB_network_org_title; ?></b> <br>
        <table class="data">
            <tr align="center">
                <td></td>
                <td><b><?php echo WEB_network_subnet; ?></b></td>
                <td><b><?php echo WEB_network_gateway; ?></b></td>
                <td><b><?php echo WEB_network_use_dhcp; ?></b></td>
                <td><b><?php echo WEB_network_static; ?></b></td>
                <td><b><?php echo WEB_network_dhcp_first; ?></b></td>
                <td><b><?php echo WEB_network_dhcp_last; ?></b></td>
                <td><b><?php echo WEB_network_dhcp_leasetime; ?></b></td>
                <td><b><?php echo WEB_network_office_subnet; ?></b></td>
                <td><b><?php echo WEB_network_hotspot; ?></b></td>
                <td><b><?php echo WEB_network_vpn; ?></b></td>
                <td><b><?php echo WEB_network_free; ?></b></td>
                <td><b><?php echo WEB_network_dyndns; ?></b></td>
                <td><b><?php echo WEB_network_discovery; ?></b></td>
                <td><b><?php echo WEB_cell_comment; ?></b></td>
            </tr>
            <?php
            $t_subnets = get_records($db_link, 'subnets', 'True ORDER BY ip_int_start');
            foreach ($t_subnets as $row) {
                print "<tr align=center>\n";
                $cl = "data";
                print "<td class=\"$cl\" style='padding:0'><input type=checkbox name=s_id[] value='" . $row['id'] . "'></td>\n";
                print "<td class=\"$cl\"><input type=\"text\" name='s_subnet[" . $row['id'] . "]' value='".$row['subnet']."' size='18'></td>\n";
                $cell_disabled = '';
                if ($row['office'] and !$row['vpn']) {
                    $default_range = cidrToRange($row['subnet']);
                    if (!isset($row['dhcp_start']) or !($row['dhcp_start'] > 0)) {
                        $row['dhcp_start'] = ip2long($default_range[3]);
                    }
                    if (!isset($row['dhcp_stop']) or !($row['dhcp_stop'] > 0)) {
                        $row['dhcp_stop'] = ip2long($default_range[4]);
                    }
                } else {
                    $cell_disabled = 'readonly=true';
                    $cl = 'down';
                }
                print "<td class=\"$cl\"><input type=\"text\" name='s_gateway[" . $row['id'] . "]' value='" . long2ip($row['gateway']) . "'  size='15' $cell_disabled></td>\n";
                if ($row['dhcp']) {
                    $cl = 'up';
                } else {
                    $cl = 'data';
                }
                print "<td class=\"$cl\">";
                print_qa_select("s_dhcp[" . $row['id'] . "]", $row['dhcp']);
                print "</td>\n";
                if ($row['static']) {
                    $cl = 'up';
                } else {
                    $cl = 'data';
                }
                print "<td class=\"$cl\">";
                print_qa_select("s_static[" . $row['id'] . "]", $row['static']);
                print "</td>\n";
                $cl = 'data';
                print "<td class=\"$cl\"><input type=\"text\" name='s_dhcp_start[" . $row['id'] . "]' value='" . long2ip($row['dhcp_start']) . "' size='15' $cell_disabled></td>\n";
                print "<td class=\"$cl\"><input type=\"text\" name='s_dhcp_stop[" . $row['id'] . "]' value='" . long2ip($row['dhcp_stop']) . "' size='15' $cell_disabled></td>\n";
                print "<td class=\"$cl\"><input type=\"text\" name='s_lease_time[" . $row['id'] . "]' value='" . $row['dhcp_lease_time'] . "'size='3' $cell_disabled></td>\n";
                $row_cl = 'data';
                if (!$row['office']) {
                    $row_cl = 'down';
                }
                if ($row['office']) {
                    $cl = 'up';
                } else {
                    $cl = 'data';
                }
                print "<td class=\"$cl\">";
                print_qa_select("s_office[" . $row['id'] . "]", $row['office']);
                print "</td>\n";
                if ($row_cl === 'data' and $row['hotspot']) {
                    $cl = 'up';
                } else {
                    $cl = $row_cl;
                }
                print "<td class=\"$cl\">";
                print_qa_select_ext("s_hotspot[" . $row['id'] . "]", $row['hotspot'], !$row['office']);
                print "</td>\n";
                if ($row_cl === 'data' and $row['vpn']) {
                    $cl = 'up';
                } else {
                    $cl = $row_cl;
                }
                print "<td class=\"$cl\">";
                print_qa_select_ext("s_vpn[" . $row['id'] . "]", $row['vpn'], !$row['office']);
                print "</td>\n";
                if ($row['free']) {
                    $cl = 'up';
                } else {
                    $cl = $row_cl;
                }
                print "<td class=\"$cl\">";
                print_qa_select("s_free[" . $row['id'] . "]", $row['free']);
                print "</td>\n";
                if ($row_cl === 'data' and $row['dhcp_update_hostname']) {
                    $cl = 'up';
                } else {
                    $cl = $row_cl;
                }
                print "<td class=\"$cl\">";
                print_qa_select_ext("s_dhcp_update[" . $row['id'] . "]", $row['dhcp_update_hostname'], !$row['office']);
                print "</td>\n";
                if ($row_cl === 'data' and $row['discovery']) {
                    $cl = 'up';
                } else {
                    $cl = $row_cl;
                }
                print "<td class=\"$cl\">";
                print_qa_select_ext("s_discovery[" . $row['id'] . "]", $row['discovery'], !$row['office']);
                print "</td>\n";
                print "<td class=\"data\"><input type=\"text\" name='s_comment[" . $row['id'] . "]' value='".$row['comment']."'></td>\n";
                print "</tr>\n";
                }
            ?>
        </table>
    </form>
    <?php require_once($_SERVER['DOCUMENT_ROOT'] . "/inc/footer.php"); ?>