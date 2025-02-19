<?php
if (! defined("CONFIG")) die("Not defined");

if (isset($_GET['dynamic_enabled'])) { $dynamic_enabled = $_GET["dynamic_enabled"] * 1; }
if (isset($_POST['dynamic_enabled'])) { $dynamic_enabled = $_POST["dynamic_enabled"] * 1; }
if (!isset($dynamic_enabled)) {
    if (isset($_SESSION[$page_url]['dynamic_enabled'])) { $dynamic_enabled = $_SESSION[$page_url]['dynamic_enabled']*1; }
    }
if (!isset($dynamic_enabled)) { $dynamic_enabled = 0; }
$_SESSION[$page_url]['dynamic_enabled']=$dynamic_enabled;
?>
