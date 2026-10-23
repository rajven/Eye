<?php

// считываем текущее время
$start_time = microtime();
// разделяем секунды и миллисекунды (становятся значениями начальных ключей массива-списка)
$start_array = explode(" ",$start_time);
// это и есть стартовое время
$start_time = $start_array[1] + $start_array[0];

if (!isset($default_displayed)) { $default_displayed=50; }

$page_full_url=$_SERVER['PHP_SELF'];
$page_url_array = explode('?', $page_full_url);
$page_url = $page_url_array[0];

?>

<!DOCTYPE html>
<html>
<head>
<title>Панель статистики</title>
<?php
    /* тот же механизм сброса кеша, что и в inc/header.php */
    $pub_style = HTML_STYLE;
    $pub_css = (isset($_SERVER['DOCUMENT_ROOT']) ? $_SERVER['DOCUMENT_ROOT'] : '') . '/css/' . $pub_style . '.css';
    $pub_ver = is_file($pub_css) ? '?v=' . filemtime($pub_css) : '';
?>
<script>
/* Тема/плотность применяются до первой отрисовки и не зависят от того,
   подключил ли страницу footer.php с /js/eye.js (иначе страница на мгновение
   — а при отсутствии eye.js и вовсе навсегда — остаётся светлой). */
(function () {
    try {
        var d = document.documentElement, t = null, n = null;
        try { t = localStorage.getItem('eye.theme'); n = localStorage.getItem('eye.dens'); } catch (e) {}
        if (t === 'light' || t === 'dark') { d.setAttribute('data-eye', t); }
        if (n === 'normal' || n === 'roomy') { d.setAttribute('data-eye-dens', n); }
    } catch (e) {}
})();
</script>
<link rel="stylesheet" type="text/css" href="/css/<?php echo $pub_style . '.css' . $pub_ver; ?>">
<meta http-equiv="content-type" content="text/html; charset=utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body class="eye-public">
<div id="title"><?php print get_const('org_name'); ?></div>

