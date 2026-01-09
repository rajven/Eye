<?php
if (!defined("CONFIG")) die("Not defined");

$display_log_level = getParam('display_log_level', $page_url, 1, FILTER_VALIDATE_INT);
$_SESSION[$page_url]['display_log_level'] = (int)$display_log_level;
?>
