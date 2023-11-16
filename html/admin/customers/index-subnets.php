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
        <input class="button_right" type="submit" onclick="return confirm('<?php print WEB_btn_delete; ?>?')" name="s_remove" value="<?php print WEB_btn_remove; ?>">
    </div>
        <b><?php echo WEB_network_org_title; ?></b> <br>
        <table class="data">
            <tr align="center">
                <td></td>
                <td><b><?php echo WEB_network_subnet; ?></b></td>
                <td><b><?php echo WEB_network_use_dhcp; ?></b></td>
                <td><b><?php echo WEB_network_static; ?></b></td>
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
                print "<td class=\"data\" style='padding:0'><input type=checkbox name=s_id[] value='" . $row['id'] . "'></td>\n";
                print "<td class=\"data\" align=left><a href=editsubnet.php?id=".$row["id"].">" . $row["subnet"] . "</a></td>\n";
                print_td_yes_no($row['dhcp']);
                print_td_yes_no($row['static']);
                print_td_yes_no($row['office']);
                print_td_yes_no($row['hotspot']);
                print_td_yes_no($row['vpn']);
                print_td_yes_no($row['free']);
                print_td_yes_no($row['dhcp_update_hostname']);
                print_td_yes_no($row['discovery']);
                print "<td class=\"data\">" . $row['comment'] . " </td>\n";
                print "</tr>\n";
                }
            ?>
        </table>
    </form>
    <?php require_once($_SERVER['DOCUMENT_ROOT'] . "/inc/footer.php"); ?>