<?php
if (! defined("CONFIG")) die("Not defined");
if (isset($_POST['log_level']) or isset($_GET['log_level'])) {
    if (isset($_GET['log_level'])) { get_const('log_level') = $_GET['log_level']*1; }
    if (isset($_POST['log_level'])) { get_const('log_level') = $_POST['log_level']*1; }
    } else {
    if (isset($_SESSION[$page_url]['log_level'])) { get_const('log_level')=$_SESSION[$page_url]['log_level']; } else { get_const('log_level') = 1; }
    }
$_SESSION[$page_url]['log_level']=get_const('log_level');
?>
