<?php
if (! defined("CONFIG")) die("Not defined");

if (empty($id) and !empty($_SESSION[$page_url]['id'])) { $id = $_SESSION[$page_url]['id']; }

if (empty($id) and !empty($default_id)) { $id=$default_id; }

if (empty($id)) {
    header("Location: /admin/index.php");
    exit;
    }

$_SESSION[$page_url]['id']=$id;
?>
