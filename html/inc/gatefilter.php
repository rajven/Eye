<?php
if (!defined("CONFIG")) die("Not defined");

$rgateway = getPOST('gateway', $page_url, 0, FILTER_VALIDATE_INT);
$_SESSION[$page_url]['gateway'] = (int)$rgateway;
?>
