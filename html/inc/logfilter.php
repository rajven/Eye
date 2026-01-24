<?php
if (!defined("CONFIG")) die("Not defined");

$fcustomer = getParam('customer', $page_url, '');
$fmessage  = getParam('message',  $page_url, '');

$_SESSION[$page_url]['customer'] = $fcustomer;
$_SESSION[$page_url]['message']  = $fmessage;
?>
