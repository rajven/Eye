<?php
if (!defined("CONFIG")) die("Not defined");

$enabled = getParam('enabled', $page_url, 0, FILTER_VALIDATE_INT);
$_SESSION[$page_url]['enabled'] = (int)$enabled;
?>
