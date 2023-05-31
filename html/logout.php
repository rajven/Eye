<?php
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/auth.utils.php");
if (isset($_SESSION['session_id'])) {
    run_sql($db_link, "DELETE FROM sessions WHERE session_id='".$_SESSION['session_id']."'");
    }
logout();
?>
