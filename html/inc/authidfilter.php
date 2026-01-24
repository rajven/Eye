<?php
if (!defined("CONFIG")) die("Not defined");

// Получаем auth_id из GET, POST или сессии, с валидацией как целого числа
$auth_id = getParam('auth_id', $page_url, null, FILTER_VALIDATE_INT);

// Если не получили из запроса/сессии, пробуем использовать значение по умолчанию
if ($auth_id === null && isset($default_auth_id)) {
    $auth_id = (int)$default_auth_id;
}

// Если всё ещё нет auth_id - редирект
if ($auth_id === null || $auth_id <= 0) {
    header("Location: /admin/index.php");
    exit;
}

// Сохраняем в сессии
$_SESSION[$page_url]['auth_id'] = $auth_id;
?>
