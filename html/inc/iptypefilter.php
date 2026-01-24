<?php
if (!defined("CONFIG")) die("Not defined");

$ip_type = getParam('ip_type', $page_url, 0, FILTER_VALIDATE_INT);
$_SESSION[$page_url]['ip_type'] = (int)$ip_type;
?>
