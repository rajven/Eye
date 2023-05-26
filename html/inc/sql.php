<?php
if (! defined("CONFIG")) die("Not defined");

if (! defined("SQL")) { die("Not defined"); }

function new_connection ($db_host, $db_user, $db_password, $db_name)
{
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$result = mysqli_connect($db_host,$db_user,$db_password,$db_name);

if (! $result) {
    echo "Error connect to MYSQL " . PHP_EOL;
    echo "Errno: " . mysqli_connect_errno() . PHP_EOL;
    echo "Error message: " . mysqli_connect_error() . PHP_EOL;
    exit();
    }

/* enable utf8 */
if (!mysqli_set_charset($result,'utf8mb4')) {
    printf("Error loading utf8: %s\n", mysqli_error($result));
    exit();
    }

//mysqli_options($result, MYSQLI_OPT_INT_AND_FLOAT_NATIVE, 1);

return $result;
}

$db_link = new_connection(DB_HOST, DB_USER, DB_PASS, DB_NAME);

?>
