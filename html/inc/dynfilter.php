<?php
if (!defined("CONFIG")) die("Not defined");

$dynamic_enabled = getParam('dynamic_enabled', $page_url, 0, FILTER_VALIDATE_INT);
$_SESSION[$page_url]['dynamic_enabled'] = (int)$dynamic_enabled;
?>
