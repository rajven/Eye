<?php
if (! defined("CONFIG")) die("Not defined");

if (isset($_GET['enabled'])) { $enabled = $_GET["enabled"] * 1; }
if (isset($_POST['enabled'])) { $enabled = $_POST["enabled"] * 1; }
if (!isset($enabled)) {
    if (isset($_SESSION[$page_url]['enabled'])) { $enabled = $_SESSION[$page_url]['enabled']*1; }
    }
if (!isset($enabled)) { $enabled = 0; }
$_SESSION[$page_url]['enabled']=$enabled;
?>
