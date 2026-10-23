<?php
/* Закрываем <div id="cont">, который страницы открывают и не закрывают;
   служебные строки идут уже в #eye-main — во всю ширину полости. */
?>
</div><!--/#cont-->

<br style="clear: both">

<div id="copyright">Copyright &copy; 2008-2026 Eye v<?php print $config["version"]; ?> &nbsp;<a href="https://github.com/rajven/Eye">rnd@rajven.ru</a></div>
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
    <span><?php echo WEB_perf_page; ?> <strong><?php printf("%.4f %s", $time, WEB_perf_sec); ?></strong></span>
    <span><?php echo WEB_perf_memory; ?> <?php echo fbytes($peak_memory); ?></span>
    <span><?php echo WEB_perf_used; ?> <?php echo fbytes($end_memory - $start_memory); ?></span>
</div>

</div><!--/#eye-main-->
</div><!--/#eye-shell-->

<script src="/js/eye.js<?php echo !empty($eye_js_mtime) ? '?v=' . $eye_js_mtime : ''; ?>"></script>

</body>
</html>
