<?php
if (!defined("CONFIG")) die("Not defined");

$search = getParam('search', $page_url, '', FILTER_VALIDATE_IP);
$search = trim((string)$search);

$_SESSION[$page_url]['search'] = $search;
?>
