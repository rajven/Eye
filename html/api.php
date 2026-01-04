<?php
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/auth.php");

$action='';
$ip='';
$mac='';
$rec_id='';
$ip_aton=NULL;
$f_subnet='';

//MODE
if (!empty($_GET['get'])) { $action = 'get_'.$_GET['get']; }
if (!empty($_GET['send'])) { $action = 'send_'.$_GET['send']; }
if (!empty($_POST['get'])) { $action = 'get_'.$_POST['get']; }
if (!empty($_POST['send'])) { $action = 'send_'.$_POST['send']; }

//GET
if (!empty($_GET['ip'])) { $ip = $_GET['ip']; }
if (!empty($_GET['mac'])) { $mac = mac_dotted(trim($_GET['mac'])); }
if (!empty($_GET['id'])) { $rec_id = $_GET['id']; }
if (!empty($_GET['subnet'])) { $f_subnet = $_GET['subnet']; }

//POST
if (!empty($_POST['ip'])) { $ip = $_POST['ip']; }
if (!empty($_POST['mac'])) { $mac = mac_dotted($_POST['mac']); }
if (!empty($_POST['id'])) { $rec_id = $_POST['id']; }
if (!empty($_POST['subnet'])) { $f_subnet = $_POST['subnet']; }

if (!empty($action)) {

      if (!empty($ip) and checkValidIp($ip))  { $ip_aton=ip2long($ip); }

      //return user auth record
      //api.php?login=<LOGIN>&api_key=<API_KEY>&get=user_auth&{mac=<MAC>|ip=<IP>}
      if ($action ==='get_user_auth') {
          $result=[];
          $sql='';
          LOG_VERBOSE($db_link,"API: Get User Auth record with ip: $ip mac: $mac id: $rec_id");
          if (!empty($mac) and !empty($ip_aton)) { 
                $sql="SELECT * FROM user_auth WHERE `ip_int`=".$ip_aton." AND `mac`='".$mac."' AND deleted=0"; 
              } else {
              if (!empty($ip_aton)) { $sql = "SELECT * FROM user_auth WHERE `ip_int`=".$ip_aton." AND deleted=0"; }
              if (!empty($mac)) { $sql="SELECT * FROM user_auth WHERE `mac`='".$mac."' AND deleted=0"; }
              }
          if (!empty($rec_id)) { $sql="SELECT * FROM user_auth WHERE id=".$rec_id; }
          if (!empty($sql)) {
              $result=get_record_sql($db_link,$sql);
              if (!empty($result)) {
                  LOG_VERBOSE($db_link,"API: Record found.");
                  try {
                    $json = json_encode($result, JSON_THROW_ON_ERROR);
                    header('Content-Type: application/json');
                    echo $json;
                    }
                  catch (JsonException $exception) {
                    LOG_ERROR($db_link,"API: Error decoding JSON. Error: ".$exception->getMessage());
                    exit($exception->getMessage());
                  }
                } else {
                  LOG_VERBOSE($db_link,"API: Not found.");
                }
             } else {
              LOG_VERBOSE($db_link,"API: not enough parameters");
             }
          }

      //return user records
      //api.php?login=<LOGIN>&api_key=<API_KEY>&get=user&id=<ID>
      if ($action ==='get_user') {
          $result=[];
          $sql='';
          LOG_VERBOSE($db_link,"API: Get User record with id: $rec_id");
          if (!empty($rec_id)) {
                $sql="SELECT * FROM user_list WHERE id=$rec_id";
                $result=get_record_sql($db_link,$sql);
                if (!empty($result)) {
                    LOG_VERBOSE($db_link,"API: User record found.");
                    $sql="SELECT * FROM user_auth WHERE deleted=0 AND user_id=".$rec_id;
                    $result_auth=get_records_sql($db_link,$sql);
                    try {
                        if (!empty($result_auth)) { $result["auth"]=$result_auth; } else { $result["auth"]=''; }
                        $json_user = json_encode($result, JSON_THROW_ON_ERROR);
                        header('Content-Type: application/json');
                        echo $json_user;
                        }
                    catch (JsonException $exception) {
                        LOG_ERROR($db_link,"API: Error decoding JSON. Error: ".$exception->getMessage());
                        exit($exception->getMessage());
                      }
                } else {
                  LOG_VERBOSE($db_link,"API: User not found.");
                }
             } else {
              LOG_VERBOSE($db_link,"API: not enough parameters");
             }
          }

      //return all records for dhcp server
      //api.php?login=<LOGIN>&api_key=<API_KEY>&get=dhcp_all
      if ($action ==='get_dhcp_all') {
            $result=[];
            LOG_VERBOSE($db_link,"API: Get all dhcp records");
            $sql = "SELECT ua.id, ua.ip, ua.ip_int, ua.mac, ua.comments, ua.dns_name, ua.dhcp_option_set, ua.dhcp_acl, ua.ou_id, SUBSTRING_INDEX(s.subnet, '/', 1) AS subnet_base 
                FROM  user_auth ua JOIN subnets s ON ua.ip_int BETWEEN s.ip_int_start AND s.ip_int_stop
                WHERE ua.dhcp = 1 AND ua.deleted = 0 AND s.dhcp = 1 ORDER BY ua.ip_int";
            $result = get_records_sql($db_link, $sql);
            if (!empty($result)) {
                    LOG_VERBOSE($db_link, "API: " . count($result) . " records found.");
                    try {
                        header('Content-Type: application/json');
                        echo json_encode($result, JSON_THROW_ON_ERROR);
                    } catch (JsonException $exception) {
                        LOG_ERROR($db_link, "API: JSON encoding error: " . $exception->getMessage());
                        exit("JSON error");
                        }
                    } else {
                        LOG_VERBOSE($db_link, "API: No records found.");
                        header('Content-Type: application/json');
                        echo json_encode([]);
                    }
            }

      //return all record in subnet for dhcp-server
      //api.php?login=<LOGIN>&api_key=<API_KEY>&get=dhcp_subnet&subnet=<SUBNET>
      if ($action ==='get_dhcp_subnet' and !empty($f_subnet)) {
            $result=[];
            $f_subnet = trim($f_subnet, "'");
            LOG_VERBOSE($db_link,"API: Get dhcp records for subnet ".$f_subnet);
            $sql = "SELECT ua.id, ua.ip, ua.ip_int, ua.mac, ua.comments, ua.dns_name, ua.dhcp_option_set, ua.dhcp_acl, ua.ou_id, SUBSTRING_INDEX(s.subnet, '/', 1) AS subnet_base 
                FROM  user_auth ua JOIN subnets s ON ua.ip_int BETWEEN s.ip_int_start AND s.ip_int_stop
                WHERE ua.dhcp = 1 AND ua.deleted = 0 AND s.dhcp = 1 AND SUBSTRING_INDEX(s.subnet, '/', 1) = '".$f_subnet."' ORDER BY ua.ip_int";
            $result = get_records_sql($db_link, $sql);
            if (!empty($result)) {
                    LOG_VERBOSE($db_link, "API: " . count($result) . " records found.");
                    try {
                        header('Content-Type: application/json');
                        echo json_encode($result, JSON_THROW_ON_ERROR);
                    } catch (JsonException $exception) {
                        LOG_ERROR($db_link, "API: JSON encoding error: " . $exception->getMessage());
                        exit("JSON error");
                        }
                    } else {
                        LOG_VERBOSE($db_link, "API: No records found.");
                        header('Content-Type: application/json');
                        echo json_encode([]);
                    }
            }

      //add dhcp log record
      //api.php?login=<LOGIN>&api_key=<API_KEY>&send=dhcp&mac=<MAC>&ip=<IP>&action=<0|1>[&hostname=<HOSTNAME>]
      if ($action ==='send_dhcp') {
          if (!empty($ip) and !empty($mac)) {
              $dhcp_hostname = '';
              if (!empty($_GET["hostname"])) { $dhcp_hostname = trim($_GET["hostname"]); }
              if (!empty($_POST["hostname"])) { $dhcp_hostname = trim($_POST["hostname"]); }
              $faction = $_GET["action"] * 1;
              $dhcp_action = 'add';
              if ($faction == 1) { $dhcp_action = 'add'; }
              if ($faction == 0) { $dhcp_action = 'del'; }
              LOG_VERBOSE($db_link, "API: external dhcp request for $ip [$mac] $dhcp_action");
              if (checkValidIp($ip) and is_our_network($db_link, $ip)) {
                    $new['action']=$dhcp_action;
                    $new['mac']=$mac;
                    $new['ip']=$ip;
                    $new['dhcp_hostname']=$dhcp_hostname;
                    insert_record($db_link,"dhcp_queue",$new);
                    } else { LOG_ERROR($db_link, "$ip - wrong network!"); }
              }
          }
      } else {
        LOG_WARNING($db_link,"API: Unknown request");
      }

ob_end_flush();

// Легкая очистка сессии без установки кук
if (session_status() === PHP_SESSION_ACTIVE) {
    $_SESSION = [];
    session_destroy();
}

unset($_GET);
unset($_POST);
?>
