<?php
if (!defined("CONFIG")) {
    die("Not defined");
}

$default_ou = isset($default_ou) ? (int)$default_ou : 0;

$rou = getParam('ou', $page_url, $default_ou, FILTER_VALIDATE_INT);
$_SESSION[$page_url]['ou'] = (int)$rou;
?>
