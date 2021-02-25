<?php
if (isset($_GET['auth_id'])) { $auth_id = $_GET["auth_id"] * 1; }
if (isset($_POST['auth_id'])) { $auth_id = $_POST["auth_id"] * 1; }
if (!isset($auth_id)) {
    if (isset($_SESSION[$page_url]['auth_id'])) { $auth_id = $_SESSION[$page_url]['auth_id']*1; }
    }
if (!isset($auth_id) and isset($default_auth_id)) { $auth_id=$default_auth_id; }
if (!isset($auth_id)) { header("Location: /admin/index.php"); }
$_SESSION[$page_url]['auth_id']=$auth_id;
?>
