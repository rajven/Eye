<?php

$start_memory = memory_get_usage();
$start_time = microtime();

ob_start();

$session_init=1;

require_once ($_SERVER['DOCUMENT_ROOT']."/inc/auth.utils.php");

login($db_link);

$start_array = explode(" ", $start_time);
$start_time = $start_array[1] + $start_array[0];

$page_full_url = $_SERVER['PHP_SELF'];
$page_url_array = explode('?', $page_full_url);

// Определяем базовый URL страницы
$page_url = !empty($page_url_array[0]) ? $page_url_array[0] : $_SERVER["REQUEST_URI"];
$page_url_args = !empty($page_url_array[1]) ? $page_url_array[1] : '';

// Получаем параметры через безопасные функции
$id = getParam('id', $page_url);

if (!empty($id) && !empty($page_url)) {
    $page_url = $page_url . '?id=' . urlencode($id);
}

if (empty($page_url)) {
    header("Location: " . DEFAULT_PAGE);
    exit;
}

// Получаем номер страницы
$page = getParam('page', $page_url, 1, FILTER_VALIDATE_INT);
if ($page < 1) $page = 1;

// Получаем количество строк на странице
$default_displayed = 50;
$displayed = getPOST('rows', $page_url, null, FILTER_VALIDATE_INT);
if ($displayed === null) {
    $displayed = $_SESSION[$page_url]['rows'] ?? $default_displayed;
}
if ($displayed < 1) $displayed = $default_displayed;

// Сохраняем в сессии
$_SESSION[$page_url]['page'] = $page;
$_SESSION[$page_url]['rows'] = $displayed;
?>
