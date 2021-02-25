<?php
if (! defined("SQL")) { die("Not defined"); }

$dbt_link = mysqli_connect($traf_host, $dbuser, $dbpass, $dbname);

if (! $dbt_link) {
    echo "Ошибка: Невозможно установить соединение с MySQL." . PHP_EOL;
    echo "Код ошибки errno: " . mysqli_connect_errno() . PHP_EOL;
    echo "Текст ошибки error: " . mysqli_connect_error() . PHP_EOL;
    exit();
}

/* изменение набора символов на utf8 */
mysqli_query($dbt_link, "SET NAMES utf8");
if (! mysqli_set_charset($dbt_link, "utf8")) {
    printf("Ошибка при загрузке набора символов utf8: %s\n", mysqli_error($dbt_link));
    exit();
}

?>
