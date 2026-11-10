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
   the version is the hash of the content, not the modification time: a deploy which
   copies the times over (rsync -a, an archive, an image layer) leaves the mtime of a
   changed file old, the address of the script does not change, and the browser keeps
   working with the copy it took before the update. */
$eye_ver = '';
$eye_css_path = (isset($_SERVER['DOCUMENT_ROOT']) ? $_SERVER['DOCUMENT_ROOT'] : '') . '/css/' . $eye_style . '.css';
$eye_js_path  = (isset($_SERVER['DOCUMENT_ROOT']) ? $_SERVER['DOCUMENT_ROOT'] : '') . '/js/eye.js';

function eye_asset_version($path)
{
    if (!$path or !is_file($path)) {
        return '';
    }
    $hash = md5_file($path);
    if ($hash !== false and $hash !== '') {
        return substr($hash, 0, 12);
    }
    return (string) filemtime($path);
}

$eye_css_mtime = eye_asset_version($eye_css_path);
$eye_js_mtime  = eye_asset_version($eye_js_path);
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
        else { d.setAttribute('data-eye-dens', 'compact'); }
        /* the attribute always names the mode, as js/eye.js does it: the compact look is
           the default of the interface, so a page which remembers nothing paints dense and
           the rows do not jump when eye.js arrives */
    } catch (e) {}
})();
</script>
<script>
/* Safety net of the mass operations. A dialog of a list carries no action and no
   method: the work is done by /js/eye.js, which posts the fields of the dialog together
   with the selection of the list. While that script is missing - a browser keeps an
   older copy of it, a proxy refuses it, an extension blocks it, or its own
   initialization stops on the way - the dialog is sent as a plain request to the list
   page: the page reloads, the selection is lost, the button says 'processing' over and
   over, and nothing reaches the screen. So the endpoints are listed here, in the text
   of the page, where they can not grow old beside the base, and this handler steps
   aside as soon as the script of the interface marks a form as its own. */
window.EYE_MASS = {
    formAuthApply:   { url: '/utils/auth_apply.php' },
    formAuthDel:     { url: '/utils/auth_remove.php' },
    formAuthExport:  { url: '/utils/auth_export.php', download: 'all-ips.csv' },
    formUserApply:   { url: '/utils/user_apply.php' },
    formUserDel:     { url: '/utils/user_remove.php' },
    formNetDevApply: { url: '/utils/devices_apply.php' },
    formNetDevDel:   { url: '/utils/devices_remove.php' }
};

(function () {
    // marks the buttons of the dialog as busy; the pages call it from their own onsubmit
    function block(formId) {
        var f = document.getElementById(formId);
        if (!f) { return; }
        marks(f, true, words().busy || '');
    }

    function release(formId) {
        var f = document.getElementById(formId);
        if (!f) { return; }
        marks(f, false);
    }

    window.EYE_MASS_BLOCK = block;
    window.EYE_MASS_RELEASE = release;

    function words() {
        var out = {};
        try { out = JSON.parse(document.body.getAttribute('data-eye-i18n') || '{}'); } catch (e) {}
        return out;
    }

    function note(host, html, isErr) {
        var box = host.querySelector('.dlg-error');
        if (!box) {
            box = document.createElement('p');
            box.className = 'note dlg-error' + (isErr ? ' err' : '');
            host.appendChild(box);
        }
        box.textContent = (html || '').replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
    }

    function marks(form, on, busy) {
        var buttons = form.querySelectorAll('input[type=submit], button[type=submit]');
        for (var i = 0; i < buttons.length; i++) {
            var b = buttons[i];
            var isInput = (b.tagName === 'INPUT');
            if (on) {
                if (b.dataset.eyeOldLabel !== undefined) { continue; }   // busy allready
                b.dataset.eyeOldLabel = '1';
                b.dataset.eyeOld = isInput ? b.value : b.textContent;
                b.disabled = true;
                if (isInput) { b.value = busy; } else { b.textContent = busy; }
            } else {
                if (b.dataset.eyeOldLabel === undefined) { continue; }
                delete b.dataset.eyeOldLabel;
                b.disabled = false;
                if (b.dataset.eyeOld) {
                    if (isInput) { b.value = b.dataset.eyeOld; } else { b.textContent = b.dataset.eyeOld; }
                }
                delete b.dataset.eyeOld;
            }
        }
    }

    document.addEventListener('submit', function (ev) {
        var form = ev.target;
        var cfg = window.EYE_MASS[form.id];
        if (!cfg || form.dataset.eyeMassWired) {
            return;                       // the script of the interface has taken the form
        }

        ev.preventDefault();

        // the dialog holds the parameters of the operation, the selection lives in the
        // form of the list: both of them go to the endpoint, as the interface script does
        var data = new FormData();
        var list = document.getElementById('def');
        if (list) {
            new FormData(list).forEach(function (v, k) { data.append(k, v); });
        }
        new FormData(form).forEach(function (v, k) { data.append(k, v); });

        var host = form.closest('dialog') || form;
        var l = words();
        marks(form, true, l.busy || '');

        fetch(cfg.url, { method: 'POST', body: data, credentials: 'same-origin' })
            .then(function (r) {
                if (!r.ok) { throw new Error('HTTP ' + r.status); }
                if (cfg.download) { return r.blob(); }
                return r.text();
            })
            .then(function (res) {
                if (cfg.download) {
                    var a = document.createElement('a');
                    a.href = URL.createObjectURL(res);
                    a.download = cfg.download;
                    document.body.appendChild(a);
                    a.click();
                    a.remove();
                    marks(form, false);
                    return;
                }
                if (/class="result err"/.test(res || '')) {
                    note(host, res, true);            // the dialog stays open
                    marks(form, false);
                    return;
                }
                if (host.close) { host.close(); }
                var btn = document.getElementById('btn_filter');
                if (btn) { setTimeout(function () { btn.click(); }, 200); }
                else { setTimeout(function () { location.reload(); }, 200); }
            })
            .catch(function (err) {
                note(host, (l.failed || 'Failed') + ' ' + err.message, true);
                marks(form, false);
            });
    });
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
    'more'    => WEB_lbl_typeahead_more,
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
    <button type="button" class="btn sm" data-eye-dens-btn title="<?php echo htmlspecialchars(WEB_lbl_dense, ENT_QUOTES); ?>"
      aria-label="<?php echo htmlspecialchars(WEB_lbl_dense, ENT_QUOTES); ?>"><?php echo WEB_lbl_dense; ?></button>
  </div>
</aside>

<div id="eye-main">
