<?php

require_once($_SERVER['DOCUMENT_ROOT'] . "/inc/auth.php");
require_once($_SERVER['DOCUMENT_ROOT'] . "/inc/languages/" . HTML_LANG . ".php");
require_once($_SERVER['DOCUMENT_ROOT'] . "/inc/idfilter.php");

if (isset($_POST['s_save'])) {

        $new['subnet'] = trim($_POST['s_subnet']);
        $new['office'] = $_POST['s_office'] * 1;
        $new['hotspot'] = $_POST['s_hotspot'] * 1;
        $new['vpn'] = $_POST['s_vpn'] * 1;
        $new['free'] = $_POST['s_free'] * 1;
        $new['dhcp'] = $_POST['s_dhcp'] * 1;
        $new['dhcp_lease_time'] = $_POST['s_lease_time'] * 1;
        $new['static'] = $_POST['s_static'] * 1;
        $new['discovery'] = $_POST['s_discovery'] * 1;
        $new['dhcp_update_hostname'] = $_POST['s_dhcp_update'] * 1;
        $new['comment'] = trim($_POST['s_comment']);

        $range = cidrToRange($new['subnet']);
        $first_user_ip = $range[0];
        $last_user_ip = $range[1];
        $cidr = $range[2][1];
        if (isset($cidr) and $cidr <= 32) { $new['subnet'] = $first_user_ip . '/' . $cidr; } else { $new['subnet'] = ''; }
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
        header("Location: " . $_SERVER["REQUEST_URI"]);
        exit;
    }

unset($_POST);

require_once($_SERVER['DOCUMENT_ROOT'] . "/inc/header.php");

print_control_submenu($page_url);

$sSQL = "SELECT * FROM subnets WHERE id=$id";
$subnet_info = get_record_sql($db_link, $sSQL);

?>
<div id="cont">
    <?php
    if (!empty($_SESSION[$page_url]['msg'])) {
        print '<div id="msg">' . $_SESSION[$page_url]['msg'] . '</div>';
        unset($_SESSION[$page_url]['msg']);
    }
    ?>
<form name="def" action="editsubnet.php?id=<?php echo $id; ?>" method="post">
<input type="hidden" name="id" value=<?php echo $id; ?>>
<table class="data">
<td><td><b><?php echo WEB_network_subnet; ?></b></td>
<?php 
$cl = "data";
print "<td class=\"$cl\"><input type=\"text\" name='s_subnet' value='".$subnet_info['subnet']."' size='18'></td>\n";
?>
<td><button name='s_save' value='save'><?php print WEB_btn_save; ?></button></td></tr>

<td><td><b><?php echo WEB_cell_comment; ?></b></td>
<?php print "<td colspan=2 class=\"data\"><input type=\"text\" name='s_comment[" . $subnet_info['id'] . "]' value='".$subnet_info['comment']."'></td>\n"; ?></tr>

<td><td><b><?php echo WEB_network_gateway; ?></b></td>
<?php 
$cell_disabled = '';
if ($subnet_info['office'] and !$subnet_info['vpn']) {
        $default_range = cidrToRange($subnet_info['subnet']);
        if (!isset($subnet_info['dhcp_start']) or !($subnet_info['dhcp_start'] > 0)) {
            $subnet_info['dhcp_start'] = ip2long($default_range[3]);
            }
        if (!isset($subnet_info['dhcp_stop']) or !($subnet_info['dhcp_stop'] > 0)) {
            $subnet_info['dhcp_stop'] = ip2long($default_range[4]);
            }
    } else {
        $cell_disabled = 'readonly=true';
        $cl = 'down';
    }
print "<td colspan=2 class=\"$cl\"><input type=\"text\" name='s_gateway' value='" . long2ip($subnet_info['gateway']) . "'  size='15' $cell_disabled></td>\n";
?>
</tr>
<td><td><b><?php echo WEB_network_use_dhcp; ?></b></td>
<?php
if ($subnet_info['dhcp']) { $cl = 'up'; } else { $cl = 'data'; }
print "<td colspan=2 class=\"$cl\">";
print_qa_select("s_dhcp", $subnet_info['dhcp']);
print "</td>\n";
?>
</tr>
<td><td><b><?php echo WEB_network_static; ?></b></td>
<?php
if ($subnet_info['static']) { $cl = 'up'; } else { $cl = 'data'; }
print "<td colspan=2 class=\"$cl\">";
print_qa_select("s_static", $subnet_info['static']);
print "</td>\n";
$cl = 'data';
?>
</tr>
<td><td><b><?php echo WEB_network_dhcp_first; ?></b></td>
<?php print "<td colspan=2 class=\"$cl\"><input type=\"text\" name='s_dhcp_start' value='" . long2ip($subnet_info['dhcp_start']) . "' size='15' $cell_disabled></td>\n"; ?></tr>
<td><td><b><?php echo WEB_network_dhcp_last; ?></b></td>
<?php print "<td colspan=2 class=\"$cl\"><input type=\"text\" name='s_dhcp_stop' value='" . long2ip($subnet_info['dhcp_stop']) . "' size='15' $cell_disabled></td>\n"; ?></tr>
<td><td><b><?php echo WEB_network_dhcp_leasetime; ?></b></td>
<?php print "<td colspan=2 class=\"$cl\"><input type=\"text\" name='s_lease_time' value='" . $subnet_info['dhcp_lease_time'] . "'size='3' $cell_disabled></td>\n"; ?></tr>
<td><td><b><?php echo WEB_network_office_subnet; ?></b></td>
<?php
$row_cl = 'data';
if (!$subnet_info['office']) { $row_cl = 'down'; }
if ($subnet_info['office']) { $cl = 'up'; } else { $cl = 'data'; }
print "<td colspan=2 class=\"$cl\">";
print_qa_select("s_office", $subnet_info['office']);
print "</td>\n";
?>
</tr>
<td><td><b><?php echo WEB_network_hotspot; ?></b></td>
<?php
if ($row_cl === 'data' and $subnet_info['hotspot']) { $cl = 'up'; } else { $cl = $row_cl; }
print "<td colspan=2 class=\"$cl\">";
print_qa_select_ext("s_hotspot", $subnet_info['hotspot'], !$subnet_info['office']);
print "</td>\n";
?>
</tr>
<td><td><b><?php echo WEB_network_vpn; ?></b></td>
<?php
if ($row_cl === 'data' and $subnet_info['vpn']) { $cl = 'up'; } else { $cl = $row_cl; }
print "<td colspan=2 class=\"$cl\">";
print_qa_select_ext("s_vpn", $subnet_info['vpn'], !$subnet_info['office']);
print "</td>\n";
?>
</tr>
<td><td><b><?php echo WEB_network_free; ?></b></td>
<?php
if ($subnet_info['free']) { $cl = 'up'; } else { $cl = $row_cl; }
print "<td colspan=2 class=\"$cl\">";
print_qa_select("s_free", $subnet_info['free']);
print "</td>\n";
?>
</tr>
<td><td><b><?php echo WEB_network_dyndns; ?></b></td>
<?php
if ($row_cl === 'data' and $subnet_info['dhcp_update_hostname']) { $cl = 'up'; } else { $cl = $row_cl; }
print "<td colspan=2 class=\"$cl\">";
print_qa_select_ext("s_dhcp_update", $subnet_info['dhcp_update_hostname'], !$subnet_info['office']);
print "</td>\n";
?>
</tr>
<td><td><b><?php echo WEB_network_discovery; ?></b></td>
<?php
if ($row_cl === 'data' and $subnet_info['discovery']) { $cl = 'up'; } else { $cl = $row_cl; }
print "<td colspan=2 class=\"$cl\">";
print_qa_select_ext("s_discovery", $subnet_info['discovery'], !$subnet_info['office']);
print "</td>\n";
?>
</tr>
</table>
<?php
    if ($msg_error) {
        print "<div id='msg'><b>$msg_error</b></div><br>\n";
    }
?>
</form>
<?php require_once($_SERVER['DOCUMENT_ROOT'] . "/inc/footer.php"); ?>
