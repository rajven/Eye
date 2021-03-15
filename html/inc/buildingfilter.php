<?php
if (! defined("CONFIG")) die("Not defined");
if (isset($_POST['building_id']) or isset($_GET['building_id'])) {
    if (isset($_GET['building_id'])) { $f_building_id = $_GET['building_id']*1; }
    if (isset($_POST['building_id'])) { $f_building_id = $_POST['building_id']*1; }
    } else {
    if (isset($_SESSION[$page_url]['building_id'])) { $f_building_id=$_SESSION[$page_url]['building_id']; } else { $f_building_id = 0; }
    }
$_SESSION[$page_url]['building_id']=$f_building_id;
?>
