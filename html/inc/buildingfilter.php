<?php
if (!defined("CONFIG")) die("Not defined");

// Получаем building_id с валидацией как целого числа
$f_building_id = getParam('building_id', $page_url, 0, FILTER_VALIDATE_INT);

// Гарантируем, что значение неотрицательное
if ($f_building_id < 0) {
    $f_building_id = 0;
}

// Сохраняем в сессии
$_SESSION[$page_url]['building_id'] = $f_building_id;
?>
