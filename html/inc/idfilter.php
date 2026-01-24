<?php
if (!defined("CONFIG")) die("Not defined");

// Получаем id из GET/POST, если не задан — из сессии, если нет — из $default_id
$id = getParam('id', $page_url, $default_id ?? null);

// Если всё ещё пусто — редирект
if (empty($id)) {
    header("Location: /admin/index.php");
    exit;
}

$_SESSION[$page_url]['id'] = $id;
?>
