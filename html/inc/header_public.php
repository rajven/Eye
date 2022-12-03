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
<link rel="stylesheet" type="text/css" href="/<?php echo HTML_STYLE.".css"; ?>">
<meta http-equiv="content-type" content="text/html; charset=utf-8">
</head>
<body>
<div id="title"><?php print get_const('org_name'); ?></div>

