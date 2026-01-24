<?php
if (!defined("CONFIG")) die("Not defined");

$search = getParam('search', $page_url, '');
$search = trim($search);
$_SESSION[$page_url]['search'] = $search;
?>
