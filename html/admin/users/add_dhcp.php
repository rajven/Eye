<?php
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/qauth.php");

if (isset($_GET["ip"]) and isset($_GET["mac"])) {
    $ip = $_GET["ip"];
    $mac = mac_dotted(trim($_GET["mac"]));
    if (isset($_GET["host"])) {
        $dhcp_hostname = trim($_GET["host"]);
    }
    $faction = $_GET["action"] * 1;
    if ($faction === 1) {
        $action = 'add';
    }
    if ($faction === 0) {
        $action = 'del';
    }
    if (! isset($action)) {
        $action = 'add';
    }
    LOG_VERBOSE($db_link, "external dhcp request for $ip [$mac] $action");
    if (checkValidIp($ip) and is_our_network($db_link, $ip)) {
	$log_dhcp = 1;
        $ip_aton = ip2long($ip);
	//check hotspot
	$hotspot_user = is_hotspot($db_link,$ip);
	if ($hotspot_user) {
		LOG_DEBUG($db_link,"Hotspot user found!");
		$log_dhcp_hotspot = get_option($db_link,44);
		if (!isset($log_dhcp_hotspot)) { $log_dhcp_hotspot = 0; }
		$log_dhcp = !$log_dhcp_hotspot;
		}
	if ($faction ===0 and get_count_records($db_link, 'User_auth', "ip_int=" . $ip_aton . " and enabled=1")===0) {
    	    LOG_VERBOSE($db_link, "dhcp action delete for unknown ip: $ip. Skip add record.");
	    } else {
            $aid = resurrection_auth($db_link, $ip, $mac, $action, $dhcp_hostname);
		if ($log_dhcp) {
    		    $dhcp_log[auth_id] = $aid;
        	    $dhcp_log[ip] = $ip;
		    $dhcp_log[ip_int] = $ip_aton;
        	    $dhcp_log[mac] = $mac;
		    $dhcp_log[action] = $action;
    	        insert_record($db_link, "dhcp_log", $dhcp_log); 
    		}
    	    }
    } else {
        LOG_ERROR($db_link, "$ip - wrong network!");
    }
}
unset($_GET);
?>
