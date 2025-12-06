<?php
if (!defined("CONFIG")) {
    die("Not defined");
}

// значение по умолчанию
$default_ou = isset($default_ou) ? (int)$default_ou : 0;

// Проверяем источники в порядке приоритета
$rou = null;

// 1. GET (самый высокий приоритет)
if (!empty($_GET['ou'])) {
    $rou = (int)$_GET['ou'];
}
// 2. POST (ниже приоритетом)
elseif (!empty($_POST['ou'])) {
    $rou = (int)$_POST['ou'];
}
// 3. SESSION (если есть)
elseif (!empty($_SESSION[$page_url]['ou'])) {
    $rou = (int)$_SESSION[$page_url]['ou'];
}
// 4. Значение по умолчанию
else {
    $rou = $default_ou;
}

// Сохраняем в сессию
$_SESSION[$page_url]['ou'] = $rou;
?>