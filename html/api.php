<?php
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/auth.php");

$action='';
$ip='';
$mac='';
$rec_id='';
$ip_aton=NULL;

//GET
if (!empty($_GET['get'])) { $action = 'get_'.$_GET['get']; }
if (!empty($_GET['send'])) { $action = 'send_'.$_GET['send']; }
if (!empty($_GET['ip'])) { $ip = $_GET['ip']; }
if (!empty($_GET['mac'])) { $mac = mac_dotted(trim($_GET['mac'])); }
if (!empty($_GET['rec_id'])) { $rec_id = $_GET['id']; }

//POST
if (!empty($_POST['get'])) { $action = 'get_'.$_POST['get']; }
if (!empty($_POST['send'])) { $action = 'send_'.$_POST['send']; }
if (!empty($_POST['ip'])) { $ip = $_POST['ip']; }
if (!empty($_POST['mac'])) { $mac = mac_dotted($_POST['mac']); }
if (!empty($_POST['rec_id'])) { $rec_id = $_POST['id']; }

if (!empty($action)) {

      if (!empty($ip) and checkValidIp($ip))  { $ip_aton=ip2long($ip); }

      //return user auth record
      if ($action ==='get_user_auth') {
          $result=[];
          $sql='';
          LOG_VERBOSE($db_link,"API: Get User Auth record with ip: $ip mac: $mac id: $rec_id");
          if (!empty($mac) and !empty($ip_aton)) { 
                $sql="SELECT * FROM User_auth WHERE `ip_int`=".$ip_aton." AND `mac`='".$mac."' AND deleted=0"; 
              } else {
              if (!empty($ip_aton)) { $sql = "SELECT * FROM User_auth WHERE `ip_int`=".$ip_aton." AND deleted=0"; }
              if (!empty($mac)) { $sql="SELECT * FROM User_auth WHERE `mac`='".$mac."' AND deleted=0"; }
              }
          if (!empty($rec_id)) { $sql="SELECT * FROM User_auth WHERE id=".$rec_id; }
          if (!empty($sql)) {
              $result=get_record_sql($db_link,$sql);
              if (!empty($result)) {
                  LOG_VERBOSE($db_link,"API: Record found.");
                  try {
                    $json = json_encode($result, JSON_THROW_ON_ERROR);
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

      //add dhcp log record
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

unset($_GET);
unset($_POST);
logout($db_link,TRUE);
?>
