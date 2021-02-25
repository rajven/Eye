<?php
if (isset($_POST['gateway'])) { $rgateway = $_POST["gateway"] * 1; } else {
    if (isset($_SESSION[$page_url]['gateway'])) { $rgateway = $_SESSION[$page_url]['gateway']; } else { $rgateway = 0; }
    }
$_SESSION[$page_url]['gateway']=$rgateway;
?>
