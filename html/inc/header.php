<?php
/**
 * Шапка страницы Eye.
 *
 * Каркас: <div id="eye-shell"> = рельса (#eye-rail: #title + #navi) и полость
 * (#eye-main: #submenu/#cont печатает сама страница, закрывает footer.php).
 * Имена id/классов сохранены прежними, поэтому 60+ страниц менять не нужно.
 *
 * Из шапки убраны: Remodal (диалоги — нативный <dialog>) и Select2
 * (обычные <select>, стилизованы в css/eye.css). jQuery остаётся только
 * на страницах, где нужен jsTree (admin/devices/index-tree.php).
 *
 * Тема: define("HTML_STYLE","eye")      — светлая, тёмная включается сама,
 *                                        если система в тёмном режиме
 *       define("HTML_STYLE","eye-dark") — тёмная всегда
 */

$eye_style = defined('HTML_STYLE') ? HTML_STYLE : 'white';
/* ?v= нужен, чтобы после обновления темы браузер не показывал старый eye.css/eye.js
   из кеша (Cache-Control для этой админки нигде не выставляется).
   Берём mtime файла: кэш сбрасывается сам в момент замены файла. */
$eye_ver = '';
$eye_css_path = (isset($_SERVER['DOCUMENT_ROOT']) ? $_SERVER['DOCUMENT_ROOT'] : '') . '/css/' . $eye_style . '.css';
$eye_js_path  = (isset($_SERVER['DOCUMENT_ROOT']) ? $_SERVER['DOCUMENT_ROOT'] : '') . '/js/eye.js';
$eye_css_mtime = ($eye_css_path && is_file($eye_css_path)) ? (string) filemtime($eye_css_path) : '';
$eye_js_mtime  = ($eye_js_path  && is_file($eye_js_path))  ? (string) filemtime($eye_js_path)  : '';
if ($eye_css_mtime === '' && isset($config['version'])) {
    $eye_css_mtime = preg_replace('/[^\w.\-]/', '', (string) $config['version']);
}
if ($eye_js_mtime === '' && isset($config['version'])) {
    $eye_js_mtime = preg_replace('/[^\w.\-]/', '', (string) $config['version']);
}
$eye_dark  = (substr($eye_style, -5) === '-dark') ? ' data-eye="dark"' : '';
$eye_lang  = (defined('HTML_LANG') && HTML_LANG === 'english') ? 'en' : 'ru';

/* активный раздел: /admin/<раздел>/... из $page_url (считается в inc/auth.php) */
$eye_path = '';
if (isset($page_url)) {
    $eye_path = (string) parse_url($page_url, PHP_URL_PATH);
}
$eye_sec = preg_match('#^/admin/([a-z0-9_-]+)#', $eye_path, $eye_m) ? $eye_m[1] : '';

/* [подпись, ссылка, раздел пути] — списком, а не словарём: два одинаковых
   перевода не должны съедать пункт меню */
$eye_nav = [
    [WEB_title_reports,   '/admin/reports/index-full.php', 'reports'],
    [WEB_title_groups,    '/admin/groups/',                'groups'],
    [WEB_title_users,     '/admin/users/',                 'users'],
    [WEB_title_users_ips, '/admin/iplist/',                'iplist'],
    [WEB_title_filters,   '/admin/filters/',               'filters'],
    [WEB_title_shapers,   '/admin/queues/',                'queues'],
    [WEB_title_devices,   '/admin/devices/',               'devices'],
    [WEB_title_control,   '/admin/customers/control.php',  'customers'],
    [WEB_title_logs,      '/admin/logs/',                  'logs'],
];
?>
<!DOCTYPE html>
<html lang="<?php echo $eye_lang; ?>"<?php echo $eye_dark; ?>>
<head>
<title><?php echo WEB_site_title; ?></title>

<link rel="apple-touch-icon" sizes="180x180" href="/img/apple-touch-icon.png">
<link rel="icon" type="image/png" sizes="32x32" href="/img/favicon-32x32.png">
<link rel="icon" type="image/png" sizes="16x16" href="/img/favicon-16x16.png">
<link rel="manifest" href="/site.webmanifest">

<meta name="viewport" content="width=device-width, initial-scale=1">
<meta charset="UTF-8">

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
<link rel="stylesheet" type="text/css" href="/css/<?php echo $eye_style . '.css' . ($eye_css_mtime ? '?v=' . $eye_css_mtime : ''); ?>">
</head>
<body data-eye-i18n="<?php echo htmlspecialchars(json_encode([
    'dense'   => WEB_lbl_dense,
    'normal'  => WEB_lbl_normal,
    'roomy'   => WEB_lbl_roomy,
    'theme'   => WEB_lbl_theme,
    'themeL'  => WEB_lbl_theme_light,
    'themeD'  => WEB_lbl_theme_dark,
    'sel'     => WEB_lbl_selected,
    'clear'   => WEB_lbl_clear,
    'failed'  => WEB_lbl_failed,
    'busy'    => WEB_msg_processing,
    'typeahead' => WEB_lbl_typeahead,
    'nomatch' => WEB_lbl_nomatch,
]), ENT_QUOTES); ?>">
<div id="eye-shell">

<aside id="eye-rail">
  <div id="title"><?php print get_const('org_name') ?></div>
  <nav id="navi" aria-label="<?php echo WEB_site_title; ?>">
<?php foreach ($eye_nav as $item):
    $label = $item[0];
    $href = $item[1];
    $here = ($item[2] === $eye_sec);
    $url = htmlspecialchars($href, ENT_QUOTES);
?>
    <a href="<?php echo $url; ?>"<?php if ($here) echo ' class="here" aria-current="page"'; ?>><?php echo $label; ?></a>
<?php endforeach; ?>
    <a href="/logout.php"><?php echo WEB_title_exit; ?></a>
  </nav>
  <div class="rail-foot">
    <button type="button" class="btn sm" data-eye-dens-btn><?php echo WEB_lbl_dense; ?></button>
  </div>
</aside>

<div id="eye-main">
