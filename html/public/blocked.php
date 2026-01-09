<?php
define("CONFIG", 1);
define("SQL", 1);
require_once ($_SERVER['DOCUMENT_ROOT']."/cfg/config.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/sql.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/common.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/languages/" . HTML_LANG . ".php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/header_public.php");

// === 1. Безопасное получение IP ===
$auth_ip = get_user_ip();
if (!$auth_ip || !filter_var($auth_ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
    die("<font color=red><b>Invalid IP detected!</b></font>");
}

// === 2. Преобразуем IP в BIGINT (беззнаковый) ===
$ip_long = sprintf('%u', ip2long($auth_ip));

// === 3. Находим авторизацию и пользователя за один JOIN ===
$sql = "
    SELECT 
        ua.*, ul.*
    FROM user_auth ua
    JOIN user_list ul ON ua.user_id = ul.id
    WHERE ua.ip_int = ? AND ua.deleted = 0 AND ul.deleted = 0
";
$record = get_record_sql($db_link, $sql, [$ip_long]);

if (!$record) {
    die("<font color=red><b>" . WEB_cell_ip . "&nbsp;" . htmlspecialchars($auth_ip, ENT_QUOTES) . "&nbsp; - " . WEB_unknown . "!</b></font>");
}

// === 4. Подготавливаем данные ===
$id = $record['id'];
$user_id = $record['user_id'];

$KB = get_const('KB') ? 1024 : 1000;

// Квоты пользователя
$user_month_quota = ($record['month_quota'] ?? 0) * $KB * $KB;
$user_day_quota   = ($record['day_quota']   ?? 0) * $KB * $KB;

// Квоты IP (auth)
$auth_month_quota = ($record['auth_month_quota'] ?? $record['month_quota'] ?? 0) * $KB * $KB;
$auth_day_quota   = ($record['auth_day_quota']   ?? $record['day_quota']   ?? 0) * $KB * $KB;

// === 5. Получаем трафик за день и месяц за 2 запроса (без циклов!) ===
$params_day = [$date1, $date2, $user_id];
$params_month = [$date1m, $date2m, $user_id];

// Трафик по всем auth этого пользователя
$day_traffic = get_record_sql($db_link, "
    SELECT 
        SUM(CASE WHEN ua.id = ? THEN us.byte_in ELSE 0 END) AS auth_in,
        SUM(CASE WHEN ua.id = ? THEN us.byte_out ELSE 0 END) AS auth_out,
        SUM(us.byte_in) AS user_in,
        SUM(us.byte_out) AS user_out
    FROM user_stats us
    JOIN user_auth ua ON us.auth_id = ua.id
    WHERE us.ts >= ? AND us.ts < ? AND ua.user_id = ? AND ua.deleted = 0
", [$id, $id, $date1, $date2, $user_id]);

$month_traffic = get_record_sql($db_link, "
    SELECT 
        SUM(CASE WHEN ua.id = ? THEN us.byte_in ELSE 0 END) AS auth_in,
        SUM(CASE WHEN ua.id = ? THEN us.byte_out ELSE 0 END) AS auth_out,
        SUM(us.byte_in) AS user_in,
        SUM(us.byte_out) AS user_out
    FROM user_stats us
    JOIN user_auth ua ON us.auth_id = ua.id
    WHERE us.ts >= ? AND us.ts < ? AND ua.user_id = ? AND ua.deleted = 0
", [$id, $id, $date1m, $date2m, $user_id]);

$day_auth_sum_in   = $day_traffic['auth_in']   ?? 0;
$day_auth_sum_out  = $day_traffic['auth_out']  ?? 0;
$day_user_sum_in   = $day_traffic['user_in']   ?? 0;
$day_user_sum_out  = $day_traffic['user_out']  ?? 0;

$month_auth_sum_in  = $month_traffic['auth_in']  ?? 0;
$month_auth_sum_out = $month_traffic['auth_out'] ?? 0;
$month_user_sum_in  = $month_traffic['user_in']  ?? 0;
$month_user_sum_out = $month_traffic['user_out'] ?? 0;

?>

<div id="cont">
<table>
<tr>
    <td><b><?php echo WEB_msg_now; ?></b></td>
    <td><?php print GetNowTimeString(); ?></td>
</tr>
<tr>
    <td><b><?php echo WEB_cell_login; ?></b></td>
    <td><?php print htmlspecialchars($record['login'], ENT_QUOTES); ?></td>
</tr>
<tr>
    <td><b><?php echo WEB_cell_fio; ?></b></td>
    <td><?php print htmlspecialchars($record['fio'], ENT_QUOTES); ?></td>
</tr>
<tr>
    <td><?php echo WEB_msg_access_login; ?></td>
    <td><b>
    <?php if ($record['enabled'] && !$record['blocked']): ?>
        <?php echo WEB_msg_enabled; ?>
    <?php else: ?>
        <?php if (!$record['enabled']): ?>
            <font color="red"><?php echo WEB_msg_disabled; ?></font>&nbsp;
        <?php endif; ?>
        <?php if ($record['blocked']): ?>
            <font color="red"><?php echo WEB_msg_traffic_blocked; ?></font>
        <?php endif; ?>
    <?php endif; ?>
    </b></td>
</tr>
<!-- Аналогично для IP-статуса -->
<tr>
    <td><?php echo WEB_msg_access_ip; ?></td>
    <td><b>
    <?php if ($record['enabled'] && !$record['blocked'] && $record['auth_enabled'] /*?*/): ?>
        <?php echo WEB_msg_enabled; ?>
    <?php else: ?>
        <?php if (!$record['enabled'] /* или auth_enabled */): ?>
            <font color="red"><?php echo WEB_msg_disabled; ?></font>&nbsp;
        <?php endif; ?>
        <?php if ($record['auth_blocked'] /*?*/): ?>
            <font color="red"><?php echo WEB_msg_traffic_blocked; ?></font>
        <?php endif; ?>
    <?php endif; ?>
    </b></td>
</tr>
<tr><td><?php echo WEB_cell_filter; ?></td><td><?php print get_group($db_link, $record["filter_group_id"]); ?> </td></tr>
<tr><td><?php echo WEB_cell_shaper; ?></td><td><?php print get_queue($db_link, $record["queue_id"]); ?></td></tr>
<tr><td><?php echo WEB_cell_login_quote_month; ?> </td><td><?php print fbytes($user_month_quota); ?> </td></tr>
<tr><td><?php echo WEB_cell_login_quote_day; ?> </td><td><?php print fbytes($user_day_quota); ?> </td></tr>
<tr><td><?php echo WEB_cell_ip_quote_month; ?> </td><td><?php print fbytes($auth_month_quota); ?> </td></tr>
<tr><td><?php echo WEB_cell_ip_quote_day; ?> </td><td><?php print fbytes($auth_day_quota); ?> </td></tr>

<!-- Трафик -->
<tr class='data'><td><b><?php echo WEB_traffic_stats . " " . WEB_cell_ip; ?></b></td><td><?php echo htmlspecialchars($auth_ip, ENT_QUOTES); ?></td></tr>
<tr class='data'><td><?php echo WEB_public_day_traffic; ?></td><td><?php echo fbytes($day_auth_sum_in) . " / " . fbytes($day_auth_sum_out); ?></td></tr>
<tr class='data'><td><?php echo WEB_public_month_traffic; ?></td><td><?php echo fbytes($month_auth_sum_in) . " / " . fbytes($month_auth_sum_out); ?></td></tr>
<tr class='data'><td><b><?php echo WEB_traffic_stats . " " . WEB_cell_login; ?></b></td><td><?php echo htmlspecialchars($record['login'], ENT_QUOTES); ?></td></tr>
<tr class='data'><td><?php echo WEB_public_day_traffic; ?></td><td><?php echo fbytes($day_user_sum_in) . " / " . fbytes($day_user_sum_out); ?></td></tr>
<tr class='data'><td><?php echo WEB_public_month_traffic; ?></td><td><?php echo fbytes($month_user_sum_in) . " / " . fbytes($month_user_sum_out); ?></td></tr>
</table>

<?php
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/footer.php");
?>
