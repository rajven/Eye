<?php
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/auth.utils.php");
if (isset($_SESSION['session_id'])) {
    run_sql($db_link, "DELETE FROM sessions WHERE session_id='".$_SESSION['session_id']."'");
    }
if (session_id()) {
    run_sql($db_link, "DELETE FROM sessions WHERE session_id='".session_id()."'");
    }
if (isset($_COOKIE["Auth"])) { 
    $data_array = explode(":", $_COOKIE["Auth"]);
    run_sql($db_link, "DELETE FROM sessions WHERE session_id='".$data_array[1]."'");
    }
logout();
?>
