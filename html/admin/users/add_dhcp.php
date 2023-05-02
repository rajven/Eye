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

	$dhcp_record['ip']=$ip;
	$dhcp_record['mac']=$mac;
	$dhcp_record['type']=$action;
	$dhcp_record['hostname']=$dhcp_hostname;
	$dhcp_record['hotspot']=is_hotspot($db_link,$ip);
	$dhcp_record['ip_aton']=ip2long($ip);

    LOG_VERBOSE($db_link, "external dhcp request for $ip [$mac] $action");
    if (checkValidIp($ip) and is_our_network($db_link, $ip)) {
		$log_dhcp = 1;
		//check hotspot
		if ($dhcp_record['hotspot']) {
			LOG_DEBUG($db_link,"Hotspot user found!");
			$log_dhcp_hotspot = get_option($db_link,44);
			if (!isset($log_dhcp_hotspot)) { $log_dhcp_hotspot = 0; }
			$log_dhcp = !$log_dhcp_hotspot;
			}
		$auth = get_record_sql($db_link,"SELECT * FROM User_auth WHERE ip_int=" . $dhcp_record['ip_aton'] . " AND deleted=0");
		$aid = NULL;
		if (!empty($auth)) {
	    	$aid = $auth['id'];
	    	LOG_VERBOSE($db_link,"Found auth for dhcp id: $aid with ip: $ip mac: $mac",$aid);
            } else {
	    	LOG_VERBOSE($db_link,"User ip record not found for ip: $ip mac: $mac action: $action. Create it!",0);
	    	$aid = resurrection_auth($db_link, $dhcp_record);
	    	if (empty($aid)) {
                LOG_ERROR($db_link,"Failed create new user record for ip: $ip mac: $mac",0);
                exit;
                }
	    	LOG_VERBOSE($db_link,"Add user by dhcp request ip: $ip mac: $mac action: $action",$aid);
            $auth = get_record_sql($db_link,"SELECT * FROM User_auth WHERE id=" . $aid);
            }
       		if ($action ==='del' and !empty($auth['dhcp_time'])) {
                $last_time = strtotime($auth['dhcp_time']);
                LOG_VERBOSE($db_link,"Delete action found for ip $ip (id: $aid, userid: ".$auth['user_id']."). Last timestamp = ".strftime('%Y-%m-%d %H-%M-%S',$last_time)." Now = ".strftime('%Y-%m-%d %H-%M-%S',time()),$aid);
	        	if ((time() - $last_time>60) and ($auth['ou_id'] == get_const('default_user_ou_id') or $auth['ou_id'] == get_const('default_hotspot_ou_id'))) {
                    LOG_VERBOSE($db_link,"Remove dynamic user ip (id: $aid) by dhcp request for ip: $ip mac: $mac",$aid);
	            	delete_record($db_link,"User_auth","id=".$aid);
	            	$u_count=get_count_records($db_link,'User_auth','deleted=0 and user_id='.$auth['user_id']);
	            	if ($u_count == 0) {
	    	       		delete_record($db_link,"User_list","id=".$auth['user_id']);
                        LOG_VERBOSE($db_link,"Remove dynamic user id: ".$auth['user_id']." by dhcp request",$aid);
	    	    		}
	        		}
	    	}
        	if ($log_dhcp) {
    	        $dhcp_log['auth_id'] = $aid;
	        	$dhcp_log['dhcp_hostname'] = $dhcp_hostname;
				$dhcp_log['ip']=$dhcp_record['ip'];
				$dhcp_log['mac']=$dhcp_record['mac'];
				$dhcp_log['action']=$dhcp_record['type'];
				$dhcp_log['ip_int']=$dhcp_record['ip_aton'];
    	        insert_record($db_link, "dhcp_log", $dhcp_log); 
    	        }
        } else { LOG_ERROR($db_link, "$ip - wrong network!"); }
	}
unset($_GET);
logout();
?>
