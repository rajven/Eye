<?php
if (!defined("CONFIG")) die("Not defined");

$default_sort  = $default_sort  ?? '';
$default_order = $default_order ?? 'ASC';

// Получаем параметры
$sort_field = getParam('sort', $page_url, $default_sort);
$order      = strtoupper(getParam('order', $page_url, $default_order));

// Валидация: sort_field должно быть одним словом (буквы, цифры, подчёркивания, дефисы)
if (!preg_match('/^[a-zA-Z0-9_-]+$/', $sort_field)) {
    $sort_field = $default_sort;
}

// Валидация: order только ASC или DESC
if ($order !== 'ASC' && $order !== 'DESC') {
    $order = $default_order;
}

$new_order = ($order === 'ASC') ? 'DESC' : 'ASC';

$_SESSION[$page_url]['sort_field'] = $sort_field;
$_SESSION[$page_url]['order']      = $order;
?>
