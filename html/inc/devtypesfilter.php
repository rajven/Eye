<?php
if (!defined("CONFIG")) die("Not defined");

$f_devtype_id = getParam('devtypes', $page_url, -1, FILTER_VALIDATE_INT);
$_SESSION[$page_url]['devtypes'] = (int)$f_devtype_id;
?>
