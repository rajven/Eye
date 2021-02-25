<?php
if (! defined("CONFIG")) die("Not defined");
if (isset($_POST['customer']) or isset($_GET['customer'])) {
    if (isset($_GET['customer'])) { $fcustomer = $_GET['customer']; }
    if (isset($_POST['customer'])) { $fcustomer = $_POST['customer']; }
    } else {
    if (isset($_SESSION[$page_url]['customer'])) { $fcustomer=$_SESSION[$page_url]['customer']; } else { $fcustomer = ''; }
    }
if (isset($_POST['customer']) or isset($_GET['customer'])) {
    if (isset($_GET['customer'])) { $fcustomer = $_GET['customer']; }
    if (isset($_POST['customer'])) { $fcustomer = $_POST['customer']; }
    } else {
    if (isset($_SESSION[$page_url]['customer'])) { $fcustomer=$_SESSION[$page_url]['customer']; } else { $fcustomer = ''; }
    }

if (isset($_POST['message']) or isset($_GET['message'])) {
    if (isset($_GET['message'])) { $fmessage = $_GET['message']; }
    if (isset($_POST['message'])) { $fmessage = $_POST['message']; }
    } else {
    if (isset($_SESSION[$page_url]['message'])) { $fmessage=$_SESSION[$page_url]['message']; } else { $fmessage = ''; }
    }
if (isset($_POST['message']) or isset($_GET['message'])) {
    if (isset($_GET['message'])) { $fmessage = $_GET['message']; }
    if (isset($_POST['message'])) { $fmessage = $_POST['message']; }
    } else {
    if (isset($_SESSION[$page_url]['message'])) { $fmessage=$_SESSION[$page_url]['message']; } else { $fmessage = ''; }
    }

$_SESSION[$page_url]['customer']=$fcustomer;
$_SESSION[$page_url]['message']=$fmessage;
?>
