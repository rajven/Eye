<?php
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/qauth.php");

if (!empty($_GET["ip"]) and !empty($_GET["mac"])) {
    $ip = $_GET["ip"];
    $mac = mac_dotted(trim($_GET["mac"]));
    $dhcp_hostname = '';
    if (!empty($_GET["hostname"])) { $dhcp_hostname = trim($_GET["hostname"]); }
    $faction = $_GET["action"] * 1;
    $action = 'add';
    if ($faction == 1) { $action = 'add'; }
    if ($faction == 0) { $action = 'del'; }
    LOG_VERBOSE($db_link, "external dhcp request for $ip [$mac] $action");
    if (checkValidIp($ip) and is_our_network($db_link, $ip)) {
		$run_cmd = "/opt/Eye/scripts/dnsmasq-hook.sh '".$action."' '".$mac."' '".$ip."' '".$dhcp_hostname."'";
		$result = shell_exec("/usr/bin/sudo ".escapeshellcmd($run_cmd)." >/dev/null 2>/dev/null &");
		LOG_INFO($db_link, "Run command: $run_cmd ");
        } else { LOG_ERROR($db_link, "$ip - wrong network!"); }
	}
unset($_GET);
logout();
?>
