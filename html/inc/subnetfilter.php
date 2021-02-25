<?php
if (!isset($default_subnet)) { $default_subnet = 0; }
if (isset($_GET['subnet'])) { $rsubnet = $_GET["subnet"] * 1; }
if (isset($_POST['subnet'])) { $rsubnet = $_POST["subnet"] * 1; }
if (! isset($rsubnet)) {
    if (isset($_SESSION[$page_url]['subnet'])) { $rsubnet=$_SESSION[$page_url]['subnet']; } else { $rsubnet = $default_subnet; }
    }
$_SESSION[$page_url]['subnet']=$rsubnet;
?>
