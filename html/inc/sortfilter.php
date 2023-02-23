<?php
if (! defined("CONFIG")) die("Not defined");

if (!isset($default_sort)) { $default_sort=''; }
if (!isset($default_order)) { $default_order='ASC'; }

if (isset($_GET['sort'])) { $sort_field = $_GET["sort"]; } else {
    if (isset($_SESSION[$page_url]['sort_field'])) { $sort_field=$_SESSION[$page_url]['sort_field']; } else { $sort_field = $default_sort; }
    }

if (isset($_GET['order'])) { $order = strtoupper($_GET["order"]); } else {
    if (isset($_SESSION[$page_url]['order'])) { $order=strtoupper($_SESSION[$page_url]['order']); } else { $order = $default_order; }
    }

if (strtoupper($order) === 'ASC') { $new_order = 'DESC'; } else { $new_order = 'ASC'; }

$_SESSION[$page_url]['order']=$order;
$_SESSION[$page_url]['sort_field']=$sort_field;
?>
