<?php
if (! defined("CONFIG")) die("Not defined");

if (!isset($default_cidr)) { $default_cidr = ''; }
if (isset($_GET['cidr'])) { $rcidr = $_GET["cidr"]; }
if (isset($_POST['cidr'])) { $rcidr = $_POST["cidr"]; }
if (! isset($rcidr)) {
    if (isset($_SESSION[$page_url]['cidr'])) { $rcidr=$_SESSION[$page_url]['cidr']; } else { $rcidr = $default_cidr; }
    }
$_SESSION[$page_url]['cidr']=$rcidr;
?>
