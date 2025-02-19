<?php
if (! defined("CONFIG")) die("Not defined");

if (isset($_GET['dhcp_enabled'])) { $dhcp_enabled = $_GET["dhcp_enabled"] * 1; }
if (isset($_POST['dhcp_enabled'])) { $dhcp_enabled = $_POST["dhcp_enabled"] * 1; }
if (!isset($dhcp_enabled)) {
    if (isset($_SESSION[$page_url]['dhcp_enabled'])) { $dhcp_enabled = $_SESSION[$page_url]['dhcp_enabled']*1; }
    }
if (!isset($dhcp_enabled)) { $dhcp_enabled = 0; }
$_SESSION[$page_url]['dhcp_enabled']=$dhcp_enabled;
?>
