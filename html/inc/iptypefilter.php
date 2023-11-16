<?php
if (! defined("CONFIG")) die("Not defined");

if (isset($_GET['ip_type'])) { $ip_type = $_GET["ip_type"] * 1; }
if (isset($_POST['ip_type'])) { $ip_type = $_POST["ip_type"] * 1; }
if (!isset($ip_type)) {
    if (isset($_SESSION[$page_url]['ip_type'])) { $ip_type = $_SESSION[$page_url]['ip_type']*1; }
    }
if (!isset($ip_type)) { $ip_type = 0; }
$_SESSION[$page_url]['ip_type']=$ip_type;
?>
