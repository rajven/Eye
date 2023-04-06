<?php

require_once ($_SERVER['DOCUMENT_ROOT']."/inc/auth.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/languages/" . HTML_LANG . ".php");

if (isset($_POST["recheck_ip"]) and is_option($db_link, 37)) {
    $run_cmd = get_option($db_link, 37);
    $result = shell_exec("/usr/bin/sudo ".escapeshellcmd($run_cmd)." >/dev/null 2>/dev/null &");
    LOG_INFO($db_link, "Run command: $run_cmd ");
    header("Location: " . $_SERVER["REQUEST_URI"]);
    exit;
}

if (isset($_POST["refresh_dhcp"]) and is_option($db_link, 38)) {
    $run_cmd = get_option($db_link, 38);
    $result = shell_exec("/usr/bin/sudo ".escapeshellcmd($run_cmd)." >/dev/null 2>/dev/null &");
    LOG_INFO($db_link, "Run command: $run_cmd ");
    header("Location: " . $_SERVER["REQUEST_URI"]);
    exit;
}

if (isset($_POST["refresh_nagios"]) and is_option($db_link, 40)) {
    $run_cmd = get_option($db_link, 40);
    $result = shell_exec("/usr/bin/sudo ".escapeshellcmd($run_cmd)." >/dev/null 2>/dev/null &");
    LOG_INFO($db_link, "Run command: $run_cmd ");
    header("Location: " . $_SERVER["REQUEST_URI"]);
    exit;
}

if (isset($_POST["up_nagios"])) {
    run_sql($db_link,"UPDATE User_auth SET nagios_status='UP'");
    run_sql($db_link,"UPDATE devices SET nagios_status='UP'");
    header("Location: " . $_SERVER["REQUEST_URI"]);
    exit;
}

if (isset($_POST["refresh_dns"]) and is_option($db_link, 39)) {
    $run_cmd = get_option($db_link, 39);
    $result = shell_exec("/usr/bin/sudo ".escapeshellcmd($run_cmd)." >/dev/null 2>/dev/null &");
    LOG_INFO($db_link, "Run command: $run_cmd ");
    header("Location: " . $_SERVER["REQUEST_URI"]);
    exit;
}

if (isset($_POST["discovery"]) and is_option($db_link, 41)) {
    $run_cmd = get_option($db_link, 41);
    $result = shell_exec("/usr/bin/sudo ".escapeshellcmd($run_cmd)." >/dev/null 2>/dev/null &");
    LOG_INFO($db_link, "Run command: $run_cmd ");
    header("Location: " . $_SERVER["REQUEST_URI"]);
    exit;
}

if (isset($_POST["discovery2"]) and is_option($db_link, 41)) {
    $run_cmd = get_option($db_link, 41);
    $result = shell_exec("/usr/bin/sudo ".escapeshellcmd($run_cmd)." force >/dev/null 2>/dev/null &");
    LOG_INFO($db_link, "Run command: $run_cmd force");
    header("Location: " . $_SERVER["REQUEST_URI"]);
    exit;
}

if (!empty($_POST["save_traf_all"]) and $_POST["save_traf_all"]) {
    run_sql($db_link, 'Update User_auth SET save_traf=1');
    LOG_INFO($db_link, "Enable save traffic for all!");
    header("Location: " . $_SERVER["REQUEST_URI"]);
    exit;
}

if (!empty($_POST["not_save_traf_all"]) and $_POST["not_save_traf_all"]) {
    run_sql($db_link, 'Update User_auth SET save_traf=0');
    LOG_INFO($db_link, "Disable save traffic for all!");
    header("Location: " . $_SERVER["REQUEST_URI"]);
    exit;
}

if (isset($_POST["s_remove"])) {
    $s_id = $_POST["s_id"];
    foreach ($s_id as $key => $val) {
        if (isset($val)) {
            LOG_INFO($db_link, "Remove subnet id: $val");
            delete_record($db_link, "subnets", "id=" . $val);
        }
    }
    header("Location: " . $_SERVER["REQUEST_URI"]);
    exit;
}

if (isset($_POST["clean_cache"])) {
    LOG_INFO($db_link, "Clean dns cache");
    run_sql($db_link,"DELETE FROM dns_cache");
    header("Location: " . $_SERVER["REQUEST_URI"]);
    exit;
}

unset($_POST);

require_once ($_SERVER['DOCUMENT_ROOT']."/inc/header.php");

print_control_submenu($page_url);

?>
<div id="cont">
<br>
<form name="def" action="control.php" method="post">
<table class="data">
<?php
        if (is_option($db_link, 37)) {
            print "<tr><td align=right>".WEB_control_access."&nbsp<input type=submit name='recheck_ip' value='".WEB_btn_refresh."'></td></tr>";
        }
        if (is_option($db_link, 38)) {
            print "<tr><td align=right>".WEB_control_dhcp."&nbsp<input type=submit name='refresh_dhcp' value='".WEB_btn_refresh."' ></td></tr>";
        }
        if (is_option($db_link, 39)) {
            print "<tr><td align=right>".WEB_control_dns."&nbsp<input type=submit name='refresh_dns' value='".WEB_btn_refresh."'  ></td></tr>";
        }
        if (is_option($db_link, 40)) {
            print "<tr><td align=right>".WEB_control_nagios."&nbsp<input type=submit name='refresh_nagios' value='".WEB_btn_refresh."'></td></tr>";
            print "<tr><td align=right>".WEB_control_nagios_clear_alarm."&nbsp<input type=submit name='up_nagios' value='".WEB_btn_run."'></td></tr>";
        }
        if (is_option($db_link, 41)) {
            print "<tr><td align=right>".WEB_control_scan_network."&nbsp<input type=submit name='discovery' value='".WEB_btn_run."'></td></tr>";
        }
        if (is_option($db_link, 41)) {
            print "<tr><td  align=right>".WEB_control_fping_scan_network."&nbsp<input type=submit name='discovery2' value='".WEB_btn_run."'></td></tr>";
        }
        if (get_option($db_link, 23)) {
            print "<tr><td  align=right>".WEB_control_log_traffic_on."&nbsp<input type=submit name='save_traf_all' value='".WEB_btn_run."'></td></tr>";
            print "<tr><td  align=right>".WEB_control_log_traffic_off."&nbsp<input type=submit name='not_save_traf_all' value='".WEB_btn_run."'></td></tr>";
        }
        print "<tr><td  align=right>".WEB_control_clear_dns_cache."&nbsp<input type=submit name='clean_cache' value='".WEB_btn_run."'></td></tr>";
?>
<tr>
<td align=right><a href="ipcam.php"><?php echo WEB_control_port_off; ?></a></td>
</tr>
</table>
</form>
<?php
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/footer.php");
?>
