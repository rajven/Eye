<?php
if (!defined("CONFIG")) die("Not defined");

$f_devmodel_id = getParam('devmodels', $page_url, -1, FILTER_VALIDATE_INT);
$_SESSION[$page_url]['devmodels'] = (int)$f_devmodel_id;
?>
