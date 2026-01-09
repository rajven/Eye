<?php
if (!defined("CONFIG")) die("Not defined");

$f_vendor_select = getParam('vendor_select', $page_url, 0, FILTER_VALIDATE_INT);
$_SESSION[$page_url]['vendor_select'] = (int)$f_vendor_select;
?>
