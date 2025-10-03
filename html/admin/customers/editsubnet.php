<?php
require_once($_SERVER['DOCUMENT_ROOT'] . "/inc/auth.php");
require_once($_SERVER['DOCUMENT_ROOT'] . "/inc/languages/" . HTML_LANG . ".php");
require_once($_SERVER['DOCUMENT_ROOT'] . "/inc/idfilter.php");

if (isset($_POST['s_save'])) {
    $new['subnet'] = trim($_POST['s_subnet']);
    $new['vlan_tag'] = trim($_POST['s_vlan']) * 1;
    if (empty($new['vlan_tag']) or ($new['vlan_tag'] < 1 or $new['vlan_tag'] > 4096)) { 
        $new['vlan_tag'] = 1; 
    }
    $new['office'] = $_POST['s_office'] * 1;
    $new['hotspot'] = $_POST['s_hotspot'] * 1;
    $new['vpn'] = $_POST['s_vpn'] * 1;
    $new['free'] = $_POST['s_free'] * 1;
    $new['dhcp'] = $_POST['s_dhcp'] * 1;
    $new['dhcp_lease_time'] = $_POST['s_lease_time'] * 1;
    $new['static'] = $_POST['s_static'] * 1;
    $new['discovery'] = $_POST['s_discovery'] * 1;
    $new['notify'] = $_POST['s_notify'] * 1;
    $new['dhcp_update_hostname'] = $_POST['s_dhcp_update'] * 1;
    $new['comment'] = trim($_POST['s_comment']);

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
    $new['dhcp_start'] = ip2long(trim($_POST['s_dhcp_start']));
    $new['dhcp_stop'] = ip2long(trim($_POST['s_dhcp_stop']));

    $dhcp_fail = 0;
    if (!isset($new['dhcp_start']) or $new['dhcp_start'] == 0) { $dhcp_fail = 1; }
    if (!isset($new['dhcp_stop']) or $new['dhcp_stop'] == 0) { $dhcp_fail = 1; }
    if (!$dhcp_fail and ($new['dhcp_start'] - $new['ip_int_stop'] >= 0)) { $dhcp_fail = 1; }
    if (!$dhcp_fail and ($new['dhcp_start'] - $new['ip_int_start'] <= 0)) { $dhcp_fail = 1; }
    if (!$dhcp_fail and ($new['dhcp_stop'] - $new['ip_int_stop'] >= 0)) { $dhcp_fail = 1; }
    if (!$dhcp_fail and ($new['dhcp_stop'] - $new['ip_int_start'] <= 0)) { $dhcp_fail = 1; }
    if (!$dhcp_fail and ($new['dhcp_start'] - $new['dhcp_stop'] >= 0)) { $dhcp_fail = 1; }

    if ($dhcp_fail) {
        $new['dhcp_start'] = ip2long($range[3]);
        $new['dhcp_stop'] = ip2long($range[4]);
    }

    $gateway = ip2long(trim($_POST['s_gateway']));
    if (!isset($gateway)) { $gateway = $range[5]; }
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

    if ($new['office']) { $new['free'] = 0; }
    
    if (!$new['office']) {
        $new['discovery'] = 0;
        $new['dhcp'] = 0;
        $new['static'] = 0;
        $new['dhcp_update_hostname'] = 0;
        $new['gateway'] = 0;
        $new['dhcp_start'] = 0;
        $new['dhcp_stop'] = 0;
    }

    update_record($db_link, "subnets", "id='$id'", $new);
    header("Location: /admin/customers/index-subnets.php");
    exit;
}

unset($_POST);
require_once($_SERVER['DOCUMENT_ROOT'] . "/inc/header.php");
print_control_submenu($page_url);

$sSQL = "SELECT * FROM subnets WHERE id=$id";
$subnet_info = get_record_sql($db_link, $sSQL);
?>

<div id="cont">
    <?php if (!empty($_SESSION[$page_url]['msg'])): ?>
        <div id="msg"><?php echo $_SESSION[$page_url]['msg']; ?></div>
        <?php unset($_SESSION[$page_url]['msg']); ?>
    <?php endif; ?>
    
    <br>
    
    <form name="def" action="editsubnet.php?id=<?php echo $id; ?>" method="post">
        <input type="hidden" name="id" value="<?php echo $id; ?>">
        
        <table class="data">
            <tr>
                <td></td>
                <td><b><?php echo WEB_network_subnet; ?></b></td>
                <td class="data">
                    <input type="text" name="s_subnet" value="<?php echo $subnet_info['subnet']; ?>" size="18">
                </td>
                <td>
                    <button name="s_save" value="save"><?php echo WEB_btn_save; ?></button>
                </td>
            </tr>
            
            <tr>
                <td></td>
                <td><b><?php echo WEB_cell_comment; ?></b></td>
                <td colspan="2" class="data">
                    <input type="text" name="s_comment" value="<?php echo $subnet_info['comment']; ?>">
                </td>
            </tr>
            
            <tr>
                <td></td>
                <td><b><?php echo WEB_network_vlan; ?></b></td>
                <td colspan="2" class="data">
                    <input type="text" name="s_vlan" value="<?php echo $subnet_info['vlan_tag']; ?>" pattern="^(409[0-6]|(40[0-8]|[1-3]\d\d|[1-9]\d|[1-9])\d|[1-9])$">
                </td>
            </tr>
            
            <tr>
                <td></td>
                <td><b><?php echo WEB_network_gateway; ?></b></td>
                <?php
                $cell_disabled = '';
                $cl = 'data';
                if ($subnet_info['office'] and !$subnet_info['vpn']) {
                    $default_range = cidrToRange($subnet_info['subnet']);
                    if (!isset($subnet_info['dhcp_start']) or !($subnet_info['dhcp_start'] > 0)) {
                        $subnet_info['dhcp_start'] = ip2long($default_range[3]);
                    }
                    if (!isset($subnet_info['dhcp_stop']) or !($subnet_info['dhcp_stop'] > 0)) {
                        $subnet_info['dhcp_stop'] = ip2long($default_range[4]);
                    }
                } else {
                    $cell_disabled = 'readonly';
                    $cl = 'down';
                }
                ?>
                <td colspan="2" class="<?php echo $cl; ?>">
                    <input type="text" name="s_gateway" value="<?php echo long2ip($subnet_info['gateway']); ?>" size="15" <?php echo $cell_disabled; ?>>
                </td>
            </tr>
            
            <tr>
                <td></td>
                <td><b><?php echo WEB_network_use_dhcp; ?></b></td>
                <td colspan="2" class="<?php echo $subnet_info['dhcp'] ? 'up' : 'data'; ?>">
                    <?php print_qa_select("s_dhcp", $subnet_info['dhcp']); ?>
                </td>
            </tr>
            
            <tr>
                <td></td>
                <td><b><?php echo WEB_network_static; ?></b></td>
                <td colspan="2" class="<?php echo $subnet_info['static'] ? 'up' : 'data'; ?>">
                    <?php print_qa_select("s_static", $subnet_info['static']); ?>
                </td>
            </tr>
            
            <tr>
                <td></td>
                <td><b><?php echo WEB_network_dhcp_first; ?></b></td>
                <td colspan="2" class="data">
                    <input type="text" name="s_dhcp_start" value="<?php echo long2ip($subnet_info['dhcp_start']); ?>" size="15" <?php echo $cell_disabled; ?>>
                </td>
            </tr>
            
            <tr>
                <td></td>
                <td><b><?php echo WEB_network_dhcp_last; ?></b></td>
                <td colspan="2" class="data">
                    <input type="text" name="s_dhcp_stop" value="<?php echo long2ip($subnet_info['dhcp_stop']); ?>" size="15" <?php echo $cell_disabled; ?>>
                </td>
            </tr>
            
            <tr>
                <td></td>
                <td><b><?php echo WEB_network_dhcp_leasetime; ?></b></td>
                <td colspan="2" class="data">
                    <input type="text" name="s_lease_time" value="<?php echo $subnet_info['dhcp_lease_time']; ?>" size="3" <?php echo $cell_disabled; ?>>
                </td>
            </tr>
            
            <tr>
                <td></td>
                <td><b><?php echo WEB_network_office_subnet; ?></b></td>
                <?php
                $row_cl = $subnet_info['office'] ? 'data' : 'down';
                $cl = $subnet_info['office'] ? 'up' : 'data';
                ?>
                <td colspan="2" class="<?php echo $cl; ?>">
                    <?php print_qa_select("s_office", $subnet_info['office']); ?>
                </td>
            </tr>
            
            <tr>
                <td></td>
                <td><b><?php echo WEB_network_hotspot; ?></b></td>
                <?php
                $cl = ($row_cl === 'data' and $subnet_info['hotspot']) ? 'up' : $row_cl;
                ?>
                <td colspan="2" class="<?php echo $cl; ?>">
                    <?php print_qa_select_ext("s_hotspot", $subnet_info['hotspot'], !$subnet_info['office']); ?>
                </td>
            </tr>
            
            <tr>
                <td></td>
                <td><b><?php echo WEB_network_vpn; ?></b></td>
                <?php
                $cl = ($row_cl === 'data' and $subnet_info['vpn']) ? 'up' : $row_cl;
                ?>
                <td colspan="2" class="<?php echo $cl; ?>">
                    <?php print_qa_select_ext("s_vpn", $subnet_info['vpn'], !$subnet_info['office']); ?>
                </td>
            </tr>
            
            <tr>
                <td></td>
                <td><b><?php echo WEB_network_free; ?></b></td>
                <td colspan="2" class="<?php echo $subnet_info['free'] ? 'up' : $row_cl; ?>">
                    <?php print_qa_select("s_free", $subnet_info['free']); ?>
                </td>
            </tr>
            
            <tr>
                <td></td>
                <td><b><?php echo WEB_network_dyndns; ?></b></td>
                <?php
                $cl = ($row_cl === 'data' and $subnet_info['dhcp_update_hostname']) ? 'up' : $row_cl;
                ?>
                <td colspan="2" class="<?php echo $cl; ?>">
                    <?php print_qa_select_ext("s_dhcp_update", $subnet_info['dhcp_update_hostname'], !$subnet_info['office']); ?>
                </td>
            </tr>
            
            <tr>
                <td></td>
                <td><b><?php echo WEB_network_discovery; ?></b></td>
                <?php
                $cl = ($row_cl === 'data' and $subnet_info['discovery']) ? 'up' : $row_cl;
                ?>
                <td colspan="2" class="<?php echo $cl; ?>">
                    <?php print_qa_select_ext("s_discovery", $subnet_info['discovery'], !$subnet_info['office']); ?>
                </td>
            </tr>

            <tr>
                <td></td>
                <td><b><?php echo WEB_network_notify; ?></b></td>
                <td colspan="2" class="data">
                    <?php print renderNotifyCombobox("s_notify", $subnet_info['notify']); ?>
                </td>
            </tr>

        </table>
        
        <?php if (isset($msg_error) && $msg_error): ?>
            <div id="msg"><b><?php echo $msg_error; ?></b></div>
        <?php endif; ?>
    </form>

<?php require_once($_SERVER['DOCUMENT_ROOT'] . "/inc/footer.php"); ?>
