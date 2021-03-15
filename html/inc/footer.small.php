<div id="copyright">Copyright &copy; 2008-2021 Stat v2.0<a href="mailto:rnd@rajven.ru">Техническая поддержка</a></div>
<?php
$end_time = microtime();
$end_array = explode(" ",$end_time);
$end_time = $end_array[1] + $end_array[0];
// вычитаем из конечного времени начальное
$time = $end_time - $start_time;
// выводим в выходной поток (броузер) время генерации страницы
printf("Страница сгенерирована за %f секунд",$time);
?>
</div>
</body>
</html>
