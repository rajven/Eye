$debug_export = var_export($_POST, true);
ob_start();
var_dump($_POST);
$debug_dump = ob_get_clean();
LOG_DEBUG($db_link,$debug_dump);
