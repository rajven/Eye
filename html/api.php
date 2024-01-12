<?php
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/qauth.php");

$action='';
$ip='';
$mac='';
$rec_id='';

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

      //return user auth record
      if ($action ==='get_user_auth') {
          $result=[];
          $sql='';
          if (!empty($ip) and checkValidIp($ip)) {
              $ip_aton=ip2long($ip);
              $sql = "SELECT * FROM User_auth WHERE `ip_int`=".$ip_aton." AND deleted=0";
              }
          if (!empty($mac)) { $sql="SELECT * FROM User_auth WHERE `mac`='".$mac."' AND deleted=0"; }
          if (!empty($rec_id)) { $sql="SELECT * FROM User_auth WHERE id=".$rec_id; }
          if (!empty($sql)) {
              $result=get_record_sql($db_link,$sql);
              if (!empty($result)) {
                  try {
                    $json = json_encode($result, JSON_THROW_ON_ERROR);
                    echo $json;
                    }
                  catch (JsonException $exception) {
                    exit($exception->getMessage());
                  }
                }
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
              LOG_VERBOSE($db_link, "external dhcp request for $ip [$mac] $dhcp_action");
              if (checkValidIp($ip) and is_our_network($db_link, $ip)) {
                    $run_cmd = "/opt/Eye/scripts/dnsmasq-hook.sh '".$dhcp_action."' '".$mac."' '".$ip."' '".$dhcp_hostname."'";
                    $result = shell_exec("/usr/bin/sudo ".escapeshellcmd($run_cmd)." >/dev/null 2>/dev/null &");
                    LOG_INFO($db_link, "Run command: $run_cmd ");
                    } else { LOG_ERROR($db_link, "$ip - wrong network!"); }
              }
          }
      }

unset($_GET);
unset($_POST);
?>
