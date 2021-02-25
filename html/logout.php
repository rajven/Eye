<?php
require_once ("inc/auth.php");

$logout_url = '/admin/logout.html';
LOG_DEBUG($db_link, "logout user " . $_SESSION['login'] . " from " . $_SESSION['IP']);

unset($_COOKIE[session_name()]);
$_SESSION = array();
session_destroy();

header('Location: ' . $logout_url, true, 301);

?>
