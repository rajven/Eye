<?php
if (! defined("CONFIG")) die("Not defined");
if (isset($_POST['vendor_select']) or isset($_GET['vendor_select'])) {
    if (isset($_GET['vendor_select'])) { $f_vendor_select = $_GET['vendor_select']*1; }
    if (isset($_POST['vendor_select'])) { $f_vendor_select = $_POST['vendor_select']*1; }
    } else {
    if (isset($_SESSION[$page_url]['vendor_select'])) { $f_vendor_select=$_SESSION[$page_url]['vendor_select']; } else { $f_vendor_select = 0; }
    }
$_SESSION[$page_url]['vendor_select']=$f_vendor_select;
?>
