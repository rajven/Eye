<br style="clear: both">

<div id="copyright">Copyright &copy; 2008-2026 Eye v<?php print $config["version"]; ?> &nbsp<a href="https://github.com/rajven/Eye">rnd@rajven.ru</a></div>
<hr>
<div>
<?php
$end_time = microtime();
$end_array = explode(" ",$end_time);
$end_time = $end_array[1] + $end_array[0];
$time = $end_time - $start_time;
$end_memory = memory_get_usage();
$peak_memory = memory_get_peak_usage();
ob_end_flush();
?>
</div>

<div class="performance-info">
    ⚡ Страница сгенерирована за <strong><?php printf("%.4f сек", $time); ?></strong>
    &nbsp;|&nbsp;
    🧠 Память: <?php echo fbytes($peak_memory); ?> (пик)
    &nbsp;|&nbsp;
    💾 Использовано: <?php echo fbytes($end_memory - $start_memory); ?>
</div>

</div>

<script src="/js/select-auto.js"></script>

</body>
</html>
