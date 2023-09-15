<?php
if (! defined("CONFIG")) die("Not defined");
if (isset($_POST['devtypes']) or isset($_GET['devtypes'])) {
    if (isset($_GET['devtypes'])) { $f_devtype_id = $_GET['devtypes']*1; }
    if (isset($_POST['devtypes'])) { $f_devtype_id = $_POST['devtypes']*1; }
    } else {
    if (isset($_SESSION[$page_url]['devtypes'])) { $f_devtype_id=$_SESSION[$page_url]['devtypes']; } else { $f_devtype_id = -1; }
    }
$_SESSION[$page_url]['devtypes']=$f_devtype_id;
?>
