<?php
if (! defined("CONFIG")) die("Not defined");
if (isset($_POST['devmodels']) or isset($_GET['devmodels'])) {
    if (isset($_GET['devmodels'])) { $f_devmodel_id = $_GET['devmodels']*1; }
    if (isset($_POST['devmodels'])) { $f_devmodel_id = $_POST['devmodels']*1; }
    } else {
    if (isset($_SESSION[$page_url]['devmodels'])) { $f_devmodel_id=$_SESSION[$page_url]['devmodels']; } else { $f_devmodel_id = -1; }
    }
$_SESSION[$page_url]['devmodels']=$f_devmodel_id;
?>
