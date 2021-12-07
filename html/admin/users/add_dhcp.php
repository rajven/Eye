<?php
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/qauth.php");

global $default_user_ou_id;
global $default_hotspot_user_id;

if (!empty($_GET["ip"]) and !empty($_GET["mac"])) {
    $ip = $_GET["ip"];
    $mac = mac_dotted(trim($_GET["mac"]));
    $dhcp_hostname = NULL;
    if (!empty($_GET["hostname"])) { $dhcp_hostname = trim($_GET["hostname"]); }
    $faction = $_GET["action"] * 1;
    $action = 'add';
    if ($faction == 1) { $action = 'add'; }
    if ($faction == 0) { $action = 'del'; }

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

	$auth = get_record_sql($db_link,"SELECT * FROM User_auth WHERE ip_int=" . $ip_aton . " AND deleted=0");

	$aid = NULL;
	if (!empty($auth)) {
	    $aid = $auth['id'];
	    LOG_VERBOSE($db_link,"Found auth for dhcp id: $aid with ip: $ip mac: $mac");
	    }

	if ($action ==='add' and empty($auth)) {
	    LOG_VERBOSE($db_link,"Add user by dhcp request ip: $ip mac: $mac");
	    $aid = resurrection_auth($db_link, $ip, $mac, $action, $dhcp_hostname);
            }

	if ($action ==='del' and !empty($auth)) {
            $last_time = strtotime($auth['dhcp_time']);
            LOG_VERBOSE($db_link,"Delete action found for ip $ip (id: $aid, userid: ".$auth['user_id']."). Last timestamp = ".strftime('%Y-%m-%d %H-%M-%S',$last_time)." Now = ".strftime('%Y-%m-%d %H-%M-%S',time()));
	    if ((time() - $last_time>60) and ($auth['ou_id'] == $default_user_ou_id or $auth['ou_id'] == $default_hotspot_ou_id)) {
                LOG_VERBOSE($db_link,"Remove dynamic user ip (id: $aid) by dhcp request for ip: $ip mac: $mac");
	        delete_record($db_link,"User_auth","id=".$aid);
	        $u_count=get_count_records($db_link,'User_auth','deleted=0 and user_id='.$auth['user_id']);
	        if ($u_count == 0) {
	    	    delete_record($db_link,"User_list","id=".$auth['user_id']);
                    LOG_VERBOSE($db_link,"Remove dynamic user id: ".$auth['user_id']." by dhcp request");
	    	    }
	        }
	    }
	
	if ($log_dhcp) {
    	    $dhcp_log['auth_id'] = $aid;
            $dhcp_log['ip'] = $ip;
	    $dhcp_log['ip_int'] = $ip_aton;
            $dhcp_log['mac'] = $mac;
	    $dhcp_log['action'] = $action;
    	    insert_record($db_link, "dhcp_log", $dhcp_log); 
    	    }
        } else {
        LOG_ERROR($db_link, "$ip - wrong network!");
        }
}
unset($_GET);
?>
