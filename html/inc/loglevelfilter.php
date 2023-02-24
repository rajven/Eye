<?php
if (! defined("CONFIG")) die("Not defined");

if (isset($_POST['display_log_level']) or isset($_GET['display_log_level'])) {
    if (isset($_GET['display_log_level'])) { $display_log_level = $_GET['display_log_level']*1; }
    if (isset($_POST['display_log_level'])) { $display_log_level = $_POST['display_log_level']*1; }
    } else {
    if (isset($_SESSION[$page_url]['display_log_level'])) { $display_log_level=$_SESSION[$page_url]['display_log_level']; } else { $display_log_level = 1; }
    }

if (empty($display_log_level)) { $display_log_level=1; }

$_SESSION[$page_url]['display_log_level']=$display_log_level;
?>
