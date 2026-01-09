<?php
if (!defined("CONFIG")) die("Not defined");

$default_subnet = $default_subnet ?? 0;

$rsubnet = getParam('subnet', $page_url, $default_subnet, FILTER_VALIDATE_INT);
$_SESSION[$page_url]['subnet'] = (int)$rsubnet;
?>
