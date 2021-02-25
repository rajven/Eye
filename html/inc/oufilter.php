<?php
if (!isset($default_ou)) { $default_ou = 0; }
if (isset($_GET['ou'])) { $rou = $_GET["ou"] * 1; }
if (isset($_POST['ou'])) { $rou = $_POST["ou"] * 1; }
if (! isset($rou)) {
    if (isset($_SESSION[$page_url]['ou'])) { $rou=$_SESSION[$page_url]['ou']; } else { $rou = $default_ou; }
    }
$_SESSION[$page_url]['ou']=$rou;
?>
