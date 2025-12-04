<?php
if (!headers_sent()) {
    header("Location: /public/blocked.php", true, 302);
    exit;
}
