<?php
if (! defined("CONFIG")) die("Not defined");

if (isset($_GET['search'])) { $search = trim($_GET["search"]); }
if (isset($_POST['search'])) { $search = trim($_POST["search"]); }
if (!isset($search)) {
    if (isset($_SESSION[$page_url]['search'])) { $search = $_SESSION[$page_url]['search']; }
    }
if (!isset($search)) { $search = ''; }
$_SESSION[$page_url]['search']=$search;
?>
