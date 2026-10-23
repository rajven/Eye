<?php
/**
 * Page header of the Eye admin.
 *
 * Shell: <div id="eye-shell"> = rail (#eye-rail with #title and #navi) plus the
 * content column (#eye-main; #submenu/#cont are printed by the page itself and
 * closed by footer.php). The existing id and class names are kept, so the 60+
 * page files do not have to change.
 *
 * Removed from the header: Remodal (dialogs are native <dialog>) and Select2
 * (plain <select>, styled by css/eye.css). jQuery is loaded only on the pages
 * that need jsTree (admin/devices/index-tree.php).
 *
 * Theme: define("HTML_STYLE","eye")      light, follows the OS in dark mode
 *        define("HTML_STYLE","eye-dark") always dark
 */

$eye_style = defined('HTML_STYLE') ? HTML_STYLE : 'eye';
/* ?v= stops the browser from serving a cached eye.css/eye.js after a theme
   update: Cache-Control is not configured anywhere for this admin app.
   the file mtime is used, so replacing a file invalidates its cache. */
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

/* active section: /admin/<section>/... taken from $page_url (set in inc/auth.php) */
$eye_path = '';
if (isset($page_url)) {
    $eye_path = (string) parse_url($page_url, PHP_URL_PATH);
}
$eye_sec = preg_match('#^/admin/([a-z0-9_-]+)#', $eye_path, $eye_m) ? $eye_m[1] : '';

/* [label, href, path section] as a list, not as a map: two identical
   translations must not swallow one menu entry */
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
/* theme and density are applied before the first paint and do not depend
   on footer.php loading /js/eye.js; otherwise the page shows the light theme
   for a moment, or forever when eye.js is never loaded. */
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
