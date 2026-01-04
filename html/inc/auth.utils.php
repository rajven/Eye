<?php

define("CONFIG", 1);
define("SQL", 1);
require_once($_SERVER['DOCUMENT_ROOT'] . "/cfg/config.php");
require_once($_SERVER['DOCUMENT_ROOT'] . "/inc/sql.php");
require_once($_SERVER['DOCUMENT_ROOT'] . "/inc/common.php");

// исправление дублирующихся PHPSESSID <<<
if (isset($_SERVER['HTTP_COOKIE'])) {
    preg_match_all('/PHPSESSID=([^;\s]+)/', $_SERVER['HTTP_COOKIE'], $matches);
    if (!empty($matches[1])) {
        $real_session_id = end($matches[1]);
        session_id($real_session_id);
        $_COOKIE['PHPSESSID'] = $real_session_id;
    }
}

// Включим подробное логирование сессий
LOG_DEBUG($db_link, "=== SESSION DEBUG START ===");
LOG_DEBUG($db_link, "Session status: " . session_status());
LOG_DEBUG($db_link, "PHP_SESSION_ACTIVE: " . PHP_SESSION_ACTIVE);
LOG_DEBUG($db_link, "DOCUMENT_ROOT: " . $_SERVER['DOCUMENT_ROOT']);
LOG_DEBUG($db_link, "REQUEST_URI: " . ($_SERVER['REQUEST_URI'] ?? 'unknown'));
LOG_DEBUG($db_link, "HTTP_COOKIE: " . ($_SERVER['HTTP_COOKIE'] ?? 'no cookies'));

// Удаляем порт из домена для корректной работы кук
$domain_parts = explode(':', $_SERVER['HTTP_HOST']);
$clean_domain = $domain_parts[0];

// Если прокси передаёт HTTPS
if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
    $_SERVER['HTTPS'] = 'on';
}

ini_set('session.cookie_lifetime', SESSION_LIFETIME);
ini_set('session.cookie_path', '/');
ini_set('session.cookie_domain', $clean_domain);
ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));
ini_set('session.cookie_httponly', true);
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.gc_maxlifetime', SESSION_LIFETIME);
//ini_set('session.use_trans_sid', true);
//ini_set('session.use_only_cookies', false);

define('SESSION_TABLE', 'sessions');
define('USER_SESSIONS_TABLE', 'user_sessions');

//set default const values
if (!defined('SESSION_LIFETIME') || SESSION_LIFETIME < 60) { define('SESSION_LIFETIME', 86400); }
if (!defined("HTML_LANG")) { define("HTML_LANG","english"); }
if (!defined("HTML_STYLE")) { define("HTML_STYLE","white"); }
if (!defined("IPCAM_GROUP_ID")) { define("IPCAM_GROUP_ID","5"); }
if (!defined("SNMP_timeout")) { define("SNMP_timeout","500000"); }
if (!defined("SNMP_retry")) { define("SNMP_retry","1"); }

// Функция для логирования отладки сессий
function log_session_debug($db, $message, $data = null) {
    $log_message = "SESSION_DEBUG: " . $message;
    if ($data !== null) {
        $log_message .= " | Data: " . (is_array($data) ? json_encode($data) : $data);
    }
    $log_message .= " | SID: " . (session_id() ?: 'no-session-id');
    $log_message .= " | Cookies: " . ($_SERVER['HTTP_COOKIE'] ?? 'none');
    LOG_DEBUG($db, $log_message);
}

// Инициализация сессий в БД
function init_db_sessions($db) {
    log_session_debug($db, "Initializing database sessions");
    session_set_save_handler(
        'sess_open',
        'sess_close',
        'sess_read',
        'sess_write',
        'sess_destroy',
        'sess_gc'
    );
    register_shutdown_function('session_write_close');
}

// Обработчики сессий
function sess_open($savePath, $sessionName) {
    global $db_link;
    log_session_debug($db_link, "Session opened", ['savePath' => $savePath, 'sessionName' => $sessionName]);
    return true;
}

function sess_close() {
    global $db_link;
    log_session_debug($db_link, "Session closed");
    return true;
}

function sess_read($sessionId) {
    global $db_link;
    log_session_debug($db_link, "Reading session", ['sessionId' => $sessionId]);

    $stmt = $db_link->prepare("SELECT data FROM " . SESSION_TABLE . " WHERE id = ?");
    $stmt->execute([$sessionId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    $data = $row ? $row['data'] : '';
    log_session_debug($db_link, "Session data retrieved", ['length' => strlen($data), 'exists' => !empty($data)]);

    return $data;
}

function sess_write($sessionId, $data) {
    global $db_link;
    log_session_debug($db_link, "Writing session", ['sessionId' => $sessionId, 'data_length' => strlen($data)]);

    $time = time();
    $stmt = $db_link->prepare("INSERT INTO " . SESSION_TABLE . " (id, data, last_accessed)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE data = ?, last_accessed = ?");

    $success = $stmt->execute([$sessionId, $data, $time, $data, $time]);

    if (!$success) {
        $error = $stmt->errorInfo();
        LOG_DEBUG($db_link, "Session write failed: " . print_r($error, true));
        log_session_debug($db_link, "Session write query failed", $error);
        return false;
    }

    log_session_debug($db_link, "Session write successful");
    return true;
}

function sess_destroy($sessionId) {
    global $db_link;
    log_session_debug($db_link, "Destroying session", ['sessionId' => $sessionId]);

    $stmt = $db_link->prepare("DELETE FROM " . SESSION_TABLE . " WHERE id = ?");
    $success = $stmt->execute([$sessionId]);

    if (!$success) {
        $error = $stmt->errorInfo();
        LOG_DEBUG($db_link, "Session destroy failed: " . print_r($error, true));
        log_session_debug($db_link, "Session destroy query failed", $error);
        return false;
    }

    log_session_debug($db_link, "Session destroy successful");
    return true;
}

function sess_gc($maxLifetime) {
    global $db_link;
    log_session_debug($db_link, "Running session GC", ['maxLifetime' => $maxLifetime]);

    $old = time() - $maxLifetime;
    $stmt = $db_link->prepare("DELETE FROM " . SESSION_TABLE . " WHERE last_accessed < ?");
    $success = $stmt->execute([$old]);

    if (!$success) {
        $error = $stmt->errorInfo();
        LOG_DEBUG($db_link, "Session GC failed: " . print_r($error, true));
        log_session_debug($db_link, "Session GC query failed", $error);
        return false;
    }

    log_session_debug($db_link, "Session GC completed");
    return true;
}

function login($db) {
    log_session_debug($db, "Login function started", [
        'session_status' => session_status(),
        'session_id' => session_id(),
        'has_post' => !empty($_POST),
        'post_login' => !empty($_POST['login']),
        'current_cookies' => $_COOKIE
    ]);

    $redirect_url = getSafeRedirectUrl(DEFAULT_PAGE);

    if ($redirect_url == DEFAULT_PAGE) {
        $current_path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $current_path = $current_path ? rtrim($current_path, '/') : '/';
        $login_path = rtrim(LOGIN_PAGE, '/');
        $logout_path = rtrim(LOGOUT_PAGE, '/');
        if ($current_path !== $login_path && $current_path !== $logout_path) {
            $redirect_url = safeUrlEncode($_SERVER['REQUEST_URI']);
        }
    }

    log_session_debug($db, "Redirect URL determined", ['redirect_url' => $redirect_url]);

    if (!empty($_SESSION['user_id'])) {
        log_session_debug($db, "Found user_id in session, validating", ['user_id' => $_SESSION['user_id']]);
        if (validate_session($db)) {
            log_session_debug($db, "Session validation successful");
            return true;
        } else {
            log_session_debug($db, "Session validation failed, continuing to other auth methods");
        }
    } else {
        log_session_debug($db, "No user_id found in session");
    }

    if (strpos($_SERVER['REQUEST_URI'], '/api.php') === 0) {
        log_session_debug($db, "API request detected, attempting silent auth");
        return IsSilentAuthenticated($db);
    }

    if (!empty($_POST['login']) && !empty($_POST['password'])) {
        log_session_debug($db, "POST login attempt", ['login' => $_POST['login']]);
        if (authenticate_by_credentials($db, $_POST['login'], $_POST['password'])) {
            LOG_INFO($db, "Logged in customer id: ".$_SESSION['user_id']." name: ".$_SESSION['login']." from ".$_SESSION['ip']." with acl: ".$_SESSION['acl']." url: ".$redirect_url);
            log_session_debug($db, "Login successful via credentials");

            session_write_close();
            session_start();

            return true;
        }
        log_session_debug($db, "Login failed via credentials");
        sleep(1);
    } else {
        log_session_debug($db, "No POST credentials provided");
    }

    log_session_debug($db, "All auth methods failed, calling logout");
    logout($db, FALSE, $redirect_url);
    exit;
}

function authenticate_by_credentials($db, $login, $password) {
    log_session_debug($db, "Authenticating by credentials", ['login' => $login]);

    $login = trim($login);
    $stmt = $db->prepare("SELECT * FROM `customers` WHERE Login = ?");
    $stmt->execute([$login]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (empty($user)) {
        log_session_debug($db, "User not found in database");
        sleep(1);
        return false;
    }

    log_session_debug($db, "User found", ['user_id' => $user['id']]);

    if (!password_verify($password, $user['password'])) {
        log_session_debug($db, "Password verification failed");
        sleep(1);
        return false;
    }

    log_session_debug($db, "Password verified, creating session");

    $regenerate_result = session_regenerate_id(true);
    log_session_debug($db, "Session regenerate result", ['success' => $regenerate_result, 'new_sid' => session_id()]);

    $_SESSION = [
        'user_id'    => $user['id'],
        'login'      => $user['Login'],
        'acl'        => $user['rights'],
        'ip'         => get_client_ip(),
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
        'created'    => time()
    ];

    log_session_debug($db, "Session data populated", $_SESSION);

    $sessionId = session_id();
    $ip = $_SESSION['ip'];
    $userAgent = $_SESSION['user_agent'];
    $time = time();

    $stmt = $db->prepare("INSERT INTO " . USER_SESSIONS_TABLE . "
        (session_id, user_id, ip_address, user_agent, created_at, last_activity)
        VALUES (?, ?, ?, ?, ?, ?)");

    $success = $stmt->execute([$sessionId, $user['id'], $ip, $userAgent, $time, $time]);

    if (!$success) {
        $error = $stmt->errorInfo();
        LOG_DEBUG($db, "Session DB error: " . print_r($error, true));
        log_session_debug($db, "User session insert failed", $error);
        return false;
    }

    log_session_debug($db, "User session record created successfully");
    return true;
}

function validate_session($db) {
    log_session_debug($db, "Validating session", [
        'session_data' => $_SESSION,
        'current_ip' => get_client_ip(),
        'current_ua' => ($_SERVER['HTTP_USER_AGENT'] ?? '')
    ]);

    if ($_SESSION['ip'] !== get_client_ip() ||
        $_SESSION['user_agent'] !== ($_SERVER['HTTP_USER_AGENT'] ?? '')) {
        log_session_debug($db, "Session validation failed - IP or User-Agent mismatch", [
            'session_ip' => $_SESSION['ip'],
            'current_ip' => get_client_ip(),
            'session_ua' => $_SESSION['user_agent'],
            'current_ua' => ($_SERVER['HTTP_USER_AGENT'] ?? '')
        ]);
        logout($db);
        return false;
    }

    $sessionId = session_id();
    $stmt = $db->prepare("SELECT 1
        FROM " . USER_SESSIONS_TABLE . "
        WHERE session_id = ? AND user_id = ? AND is_active = 1
        LIMIT 1");

    $stmt->execute([$sessionId, $_SESSION['user_id']]);
    if ($stmt->rowCount() === 0) {
        log_session_debug($db, "Session validation failed - no active session in database");
        logout($db);
        return false;
    }

    $stmt = $db->prepare("UPDATE " . USER_SESSIONS_TABLE . " SET last_activity = ? WHERE session_id = ?");
    $stmt->execute([time(), $sessionId]);

    log_session_debug($db, "Session validation successful");
    return true;
}

function get_client_ip() {
    $ip = '127.0.0.1';
    foreach (['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $key) {
        if (!empty($_SERVER[$key])) {
            $ip = trim(current(explode(',', $_SERVER[$key])));
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                break;
            }
        }
    }
    log_session_debug($GLOBALS['db_link'], "Client IP determined", ['ip' => $ip]);
    return $ip;
}

function IsSilentAuthenticated($db) {
    log_session_debug($db, "Silent authentication attempt");

    if (!empty($_SESSION['user_id'])) {
        log_session_debug($db, "Silent auth - already has user_id in session");
        return true;
    }

    $auth_ip = get_client_ip();
    $api_key = '';
    $login = '';

    if (!empty($_GET['api_key'])) {
        $api_key = trim($_GET['api_key']);
    } elseif (!empty($_POST['api_key'])) {
        $api_key = trim($_POST['api_key']);
    }

    if (!empty($_GET['login'])) {
        $login = trim($_GET['login']);
    } elseif (!empty($_POST['login'])) {
        $login = trim($_POST['login']);
    }

    log_session_debug($db, "Silent auth parameters", ['login' => $login, 'has_api_key' => !empty($api_key)]);

    if (empty($login) || empty($api_key) || strlen($api_key) < 20) {
        log_session_debug($db, "Silent auth failed - missing parameters");
        return false;
    }

    $stmt = $db->prepare("SELECT id, rights FROM customers WHERE Login = ? AND api_key = ? LIMIT 1");
    $stmt->execute([$login, $api_key]);

    if ($stmt->rowCount() === 0) {
        LOG_DEBUG($db, "API auth failed for: $login");
        log_session_debug($db, "Silent auth failed - user not found or invalid API key");
        return false;
    }

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    $_SESSION = [
        'user_id'    => $user['id'],
        'login'      => $login,
        'acl'        => $user['rights'],
        'ip'         => $auth_ip,
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
        'api_auth'   => true
    ];

    log_session_debug($db, "Silent auth successful", ['user_id' => $user['id'], 'login' => $login]);
    LOG_INFO($db, "Logged in to api customer id: ".$_SESSION['user_id']." name: ".$_SESSION['login']." from ".$_SESSION['ip']." with acl: ".$_SESSION['acl']);
    return true;
}

function logout($db, $silent = FALSE, $redirect_url = DEFAULT_PAGE) {
    log_session_debug($db, "Logout function called", [
        'silent' => $silent,
        'redirect_url' => $redirect_url,
        'session_status' => session_status(),
        'session_id' => session_id()
    ]);

    if (session_status() === PHP_SESSION_ACTIVE) {
        $user_info = isset($_SESSION['user_id']) ?
            "customer id: ".$_SESSION['user_id']." name: ".$_SESSION['login']." from ".$_SESSION['ip']." with acl: ".$_SESSION['acl'] :
            "no user session data";

        LOG_INFO($db, "Logout " . $user_info);

        $sessionId = session_id();
        if ($sessionId) {
            $stmt = $db->prepare("UPDATE " . USER_SESSIONS_TABLE . " SET is_active = 0 WHERE session_id = ?");
            $result = $stmt->execute([$sessionId]);
            log_session_debug($db, "Session deactivation query executed", ['success' => (bool)$result]);
        }

        $_SESSION = [];
        session_destroy();

        if (!headers_sent()) {
            setcookie(session_name(), '', time() - SESSION_LIFETIME, '/');
            if (isset($_COOKIE['Auth'])) {
                setcookie('Auth', '', time() - SESSION_LIFETIME, '/');
            }
            log_session_debug($db, "Session cookies cleared");
        }
    } else {
        log_session_debug($db, "Logout - no active session to destroy");
    }

    if (!$silent && !headers_sent()) {
        log_session_debug($db, "Performing redirect after logout");
        if ($redirect_url == DEFAULT_PAGE || empty($redirect_url) || $redirect_url == '/') {
            header('Location: ' . LOGIN_PAGE);
        } else {
            header('Location: ' . LOGIN_PAGE . '?redirect_url=' . urlencode($redirect_url));
        }
    }
}

// Инициализация системы сессий
log_session_debug($db_link, "Before init_db_sessions");
init_db_sessions($db_link);

// Инициализация сессии
log_session_debug($db_link, "Before session_start check");
if (session_status() !== PHP_SESSION_ACTIVE) {
    log_session_debug($db_link, "Starting session");

    session_start();

    log_session_debug($db_link, "After session_start", [
        'session_status' => session_status(),
        'session_id' => session_id(),
        'session_cookie_params' => session_get_cookie_params()
    ]);
} else {
    log_session_debug($db_link, "Session already active", [
        'session_id' => session_id(),
        'session_status' => session_status()
    ]);
}

log_session_debug($db_link, "=== SESSION DEBUG END ===");