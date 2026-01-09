<?php
if (!defined("CONFIG")) die("Not defined");

$dhcp_enabled = getParam('dhcp_enabled', $page_url, 0, FILTER_VALIDATE_INT);
$_SESSION[$page_url]['dhcp_enabled'] = (int)$dhcp_enabled;
?>
