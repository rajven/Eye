<?php
if (!isset($default_sort)) { $default_sort=''; }
if (isset($_GET['sort'])) { $sort_field = $_GET["sort"]; } else {
    if (isset($_SESSION[$page_url]['sort_field'])) { $sort_field=$_SESSION[$page_url]['sort_field']; } else { $sort_field = $default_sort; }
    }

if (isset($_GET['order'])) { $order = $_GET["order"]; } else {
    if (isset($_SESSION[$page_url]['order'])) { $order=$_SESSION[$page_url]['order']; } else { $order = "asc"; }
    }

if ($order == 'asc') { $new_order = 'desc'; } else { $new_order = 'asc'; }

$_SESSION[$page_url]['order']=$order;
$_SESSION[$page_url]['sort_field']=$sort_field;
?>
