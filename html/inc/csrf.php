<?php
// CSRF protection of the admin interface. A synchronizer token lives in the
// session. The output filter adds it as a hidden field to every form of every
// page, and every POST which passes through inc/auth.php must carry it back.
// The api is not covered: its requests are authorized by the api key, not by
// the cookies of the browser, so they are not subject to csrf at all.

if (!defined("CONFIG")) die("Not defined");

// the token of the session, created on the first call
function csrf_token($db = null)
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        init_db_sessions($db);
        session_start();
    }
    if (empty($_SESSION['csrf_token'])) {
        // 32 random bytes, hex on the wire; the value is safe in html as it is
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// drop the token when the authentication state changes (a successful login),
// so a token known before the login does not survive into the session after it
function csrf_reset()
{
    unset($_SESSION['csrf_token']);
}

// the output filter of the pages: puts the hidden field right after the open
// tag of every <form>, except the ones which explicitly go by GET — the filters
// of the lists must not carry the token in the url
function csrf_inject($html)
{
    if (empty($_SESSION['csrf_token']) || stripos($html, '<form') === false) {
        return $html;
    }
    $field = '<input type="hidden" name="csrf_token" value="' . $_SESSION['csrf_token'] . '">';
    return preg_replace_callback('/<form\b[^>]*>/i', function ($m) use ($field) {
        if (preg_match('/method\s*=\s*(["\']?)get\1/i', $m[0])) {
            return $m[0];                  // a GET filter: no token into the url
        }
        return $m[0] . $field;
    }, $html);
}

// every POST which passed through inc/auth.php must bring the token back
function csrf_verify_post($db = null)
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        return true;
    }
    $sent  = (string)($_POST['csrf_token'] ?? '');
    $known = csrf_token($db);
    if ($sent !== '' && hash_equals($known, $sent)) {
        return true;
    }
    LOG_WARNING($db, "CSRF: POST from " . ($_SERVER['REMOTE_ADDR'] ?? '?')
        . " to " . ($_SERVER['REQUEST_URI'] ?? '?') . " carries no valid token");
    http_response_code(403);
    print "<p class=\"error\">The session has expired or the request is forged. "
        . "Open the page again and repeat the action.</p>";
    exit;
}
