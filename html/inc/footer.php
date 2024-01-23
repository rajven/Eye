<div id="copyright">Copyright &copy; 2008-2024 Eye v2.4.14 &nbsp<a href="https://github.com/rajven/Eye">rnd@rajven.ru</a></div>

<?php
$end_time = microtime();
$end_array = explode(" ",$end_time);
$end_time = $end_array[1] + $end_array[0];
$time = $end_time - $start_time;
printf(WEB_page_speed."%f ".WEB_sec,$time);
?>

</div>

<script src="/js/select-auto.js"></script>

</body>
</html>
