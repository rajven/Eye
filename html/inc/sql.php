<?php
if (! defined("SQL")) { die("Not defined"); }

$db_link = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);

if (! $db_link) {
    echo "Ошибка: Невозможно установить соединение с MySQL." . PHP_EOL;
    echo "Код ошибки errno: " . mysqli_connect_errno() . PHP_EOL;
    echo "Текст ошибки error: " . mysqli_connect_error() . PHP_EOL;
    exit();
    }

/* изменение набора символов на utf8 */
if (!mysqli_set_charset($db_link,'utf8mb4')) {
    printf("Ошибка при загрузке набора символов utf8: %s\n", mysqli_error($db_link));
    exit();
    }

?>
