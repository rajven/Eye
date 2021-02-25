<?php
if (isset($default_id)) { $id=$default_id; }
if (isset($_GET['id'])) { $id = $_GET["id"] * 1; }
if (isset($_POST['id'])) { $id = $_POST["id"] * 1; }
if (!isset($id)) {
    if (isset($_SESSION[$page_url]['id'])) { $id = $_SESSION[$page_url]['id']*1; }
    }
if (!isset($id) and isset($default_id)) { $id=$default_id; }
if (!isset($id)) { header("Location: /admin/index.php"); }
$_SESSION[$page_url]['id']=$id;
?>
