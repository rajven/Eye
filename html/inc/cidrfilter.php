<?php
if (!defined("CONFIG")) die("Not defined");

$default_cidr = $default_cidr ?? '';

// Получаем raw-значение без фильтрации
$rcidr_raw = getParam('cidr', $page_url, $default_cidr);

// Заменяем русскую "ю" и "Ю" на точку (на случай переключённой раскладки)
$rcidr_normalized = str_replace(['ю', 'Ю'], '.', $rcidr_raw);

// Обрезаем пробелы
$rcidr = trim($rcidr_normalized);

$_SESSION[$page_url]['cidr'] = $rcidr;
?>
