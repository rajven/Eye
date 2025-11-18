<?php

define("CONFIG", 1);
define("SQL", 1);
require_once($_SERVER['DOCUMENT_ROOT'] . "/cfg/config.php");
require_once($_SERVER['DOCUMENT_ROOT'] . "/inc/sql.php");
require_once($_SERVER['DOCUMENT_ROOT'] . "/inc/common.php");

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
    // Настройка обработчиков сессий
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
    
    $sessionId = mysqli_real_escape_string($db_link, $sessionId);
    $result = mysqli_query($db_link, "SELECT data FROM ".SESSION_TABLE." WHERE id = '$sessionId'");
    
    if (!$result) {
        $error = mysqli_error($db_link);
        LOG_DEBUG($db_link, "Session read failed: " . $error);
        log_session_debug($db_link, "Session read query failed", $error);
        return '';
    }
    
    $data = mysqli_num_rows($result) ? mysqli_fetch_assoc($result)['data'] : '';
    log_session_debug($db_link, "Session data retrieved", ['length' => strlen($data), 'exists' => !empty($data)]);
    
    return $data;
}

function sess_write($sessionId, $data) {
    global $db_link;
    log_session_debug($db_link, "Writing session", ['sessionId' => $sessionId, 'data_length' => strlen($data)]);
    
    $sessionId = mysqli_real_escape_string($db_link, $sessionId);
    $data = mysqli_real_escape_string($db_link, $data);
    $time = time();
    $query = "INSERT INTO ".SESSION_TABLE." (id, data, last_accessed) 
              VALUES ('$sessionId', '$data', $time)
              ON DUPLICATE KEY UPDATE data = '$data', last_accessed = $time";

    if (!mysqli_query($db_link, $query)) {
        $error = mysqli_error($db_link);
        LOG_DEBUG($db_link, "Session write failed: " . $error);
        log_session_debug($db_link, "Session write query failed", $error);
        return false;
    }
    
    log_session_debug($db_link, "Session write successful");
    return true;
}

function sess_destroy($sessionId) {
    global $db_link;
    log_session_debug($db_link, "Destroying session", ['sessionId' => $sessionId]);
    
    $sessionId = mysqli_real_escape_string($db_link, $sessionId);
    if (!mysqli_query($db_link, "DELETE FROM ".SESSION_TABLE." WHERE id = '$sessionId'")) {
        $error = mysqli_error($db_link);
        LOG_DEBUG($db_link, "Session destroy failed: " . $error);
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
    if (!mysqli_query($db_link, "DELETE FROM ".SESSION_TABLE." WHERE last_accessed < $old")) {
        $error = mysqli_error($db_link);
        LOG_DEBUG($db_link, "Session GC failed: " . $error);
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
        // 1. Сначала получаем путь из оригинального URL
        $current_path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $current_path = $current_path ? rtrim($current_path, '/') : '/';
        // 2. Подготавливаем пути для сравнения
        $login_path = rtrim(LOGIN_PAGE, '/');
        $logout_path = rtrim(LOGOUT_PAGE, '/');
        // 3. Сравниваем пути
        if ($current_path !== $login_path && $current_path !== $logout_path) {
            // 4. Кодируем 
            $redirect_url = safeUrlEncode($_SERVER['REQUEST_URI']);
            }
        }

    log_session_debug($db, "Redirect URL determined", ['redirect_url' => $redirect_url]);

    // 1. Проверка активной сессии
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

    // 2. Проверка API-авторизации (для API-запросов)
    if (strpos($_SERVER['REQUEST_URI'], '/api.php') === 0) {
        log_session_debug($db, "API request detected, attempting silent auth");
        return IsSilentAuthenticated($db);
    }

    // 4. Проверка логина/пароля из POST-данных (обычная форма входа)
    if (!empty($_POST['login']) && !empty($_POST['password'])) {
        log_session_debug($db, "POST login attempt", ['login' => $_POST['login']]);
        if (authenticate_by_credentials($db, $_POST['login'], $_POST['password'])) {
            LOG_INFO($db, "Logged in customer id: ".$_SESSION['user_id']." name: ".$_SESSION['login']." from ".$_SESSION['ip']." with acl: ".$_SESSION['acl']." url: ".$redirect_url);
            log_session_debug($db, "Login successful via credentials");
            
            // Немедленно сохраняем сессию
            session_write_close();
            // И перезапускаем для отправки куки браузеру
            session_start();
            
            return true;
        }
        // Неудачная попытка входа
        log_session_debug($db, "Login failed via credentials");
        sleep(1); // Защита от брутфорса
    } else {
        log_session_debug($db, "No POST credentials provided");
    }

    // 5. Если ни один метод не сработал - требовать авторизацию
    log_session_debug($db, "All auth methods failed, calling logout");
    logout($db,FALSE,$redirect_url);
    exit;
}

function authenticate_by_credentials($db,$login,$password) {
    log_session_debug($db, "Authenticating by credentials", ['login' => $login]);

    $login = mysqli_real_escape_string($db, trim($login));
    $query = "SELECT * FROM `Customers` WHERE Login='{$login}'";
    $user = get_record_sql($db, $query);

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

    // Создание сессии
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

    // Запись сессии в БД
    $sessionId = mysqli_real_escape_string($db, session_id());
    $ip = mysqli_real_escape_string($db, $_SESSION['ip']);
    $userAgent = mysqli_real_escape_string($db, $_SESSION['user_agent']);
    $time = time();

    // Запись в БД
    $sessionId = mysqli_real_escape_string($db, session_id());
    $query = "INSERT INTO ".USER_SESSIONS_TABLE." 
        (session_id, user_id, ip_address, user_agent, created_at, last_activity) 
        VALUES (
            '$sessionId',
            {$user['id']},
            '$ip',
            '$userAgent',
            $time,
            $time
        )";
        
    log_session_debug($db, "Executing user session insert query", ['query' => $query]);
    
    if (!mysqli_query($db, $query)) {
        $error = mysqli_error($db);
        LOG_DEBUG($db, "Session DB error: ".$error);
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

    // Проверка IP и User-Agent
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

    // Проверка активности сессии в БД
    $sessionId = mysqli_real_escape_string($db, session_id());
    $result = mysqli_query($db, 
        "SELECT 1 
         FROM ".USER_SESSIONS_TABLE." 
         WHERE 
            session_id = '$sessionId' AND
            user_id = {$_SESSION['user_id']} AND
            is_active = 1
         LIMIT 1");

    if (!$result) {
        $error = mysqli_error($db);
        log_session_debug($db, "Session validation query failed", $error);
        logout($db);
        return false;
    }

    if (mysqli_num_rows($result) === 0) {
        log_session_debug($db, "Session validation failed - no active session in database");
        logout($db);
        return false;
    }

    // Обновление времени активности
    mysqli_query($db, 
        "UPDATE ".USER_SESSIONS_TABLE." 
         SET last_activity = ".time()." 
         WHERE session_id = '$sessionId'");

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

// Авторизация по API-ключу (без пароля)
function IsSilentAuthenticated($db) {
    log_session_debug($db, "Silent authentication attempt");

    if (!empty($_SESSION['user_id'])) {
        log_session_debug($db, "Silent auth - already has user_id in session");
        return true;
    }

    $auth_ip = get_user_ip();
    $api_key = '';
    $login = '';

    // Получаем ключ из GET или POST
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

    // Экранирование и подготовка
    $login = mysqli_real_escape_string($db, $login);
    $api_key = mysqli_real_escape_string($db, $api_key);

    // Ищем пользователя с таким логином и API-ключом
    $query = "SELECT id, rights FROM Customers 
              WHERE Login = '$login' AND api_key = '$api_key' 
              LIMIT 1";
    $result = mysqli_query($db, $query);

    if (!$result || mysqli_num_rows($result) === 0) {
        LOG_DEBUG($db, "API auth failed for: $login");
        log_session_debug($db, "Silent auth failed - user not found or invalid API key");
        return false;
    }

    $user = mysqli_fetch_assoc($result);

    // Создаем сессию
    $_SESSION = [
        'user_id'    => $user['id'],
        'login'      => $login,
        'acl'        => $user['rights'],
        'ip'         => $auth_ip,
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
        'api_auth'   => true // Метка API-аутентификации
    ];

    log_session_debug($db, "Silent auth successful", ['user_id' => $user['id'], 'login' => $login]);
    LOG_INFO($db, "Logged in to api customer id: ".$_SESSION['user_id']." name: ".$_SESSION['login']." from ".$_SESSION['ip']." with acl: ".$_SESSION['acl']);
    return true;
}

// Выход из системы (полная версия)
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
        
        // Деактивация сессии в БД
        $sessionId = session_id();
        if ($sessionId) {
            $sessionId = mysqli_real_escape_string($db, $sessionId);
            $result = mysqli_query($db, 
                "UPDATE ".USER_SESSIONS_TABLE." 
                 SET is_active = 0 
                 WHERE session_id = '$sessionId'");
            log_session_debug($db, "Session deactivation query executed", ['success' => (bool)$result]);
        }
        
        // Очистка данных
        $_SESSION = [];
        session_destroy();
        
        if (!headers_sent()) {
            setcookie(session_name(), '', time() - SESSION_LIFETIME, '/');
            // Удаление авторизационной куки (если есть)
            if (isset($_COOKIE['Auth'])) {
                setcookie('Auth', '', time() - SESSION_LIFETIME, '/');
            }
            log_session_debug($db, "Session cookies cleared");
        }
    } else {
        log_session_debug($db, "Logout - no active session to destroy");
    }
    
    if (!$silent and !headers_sent()) {
        log_session_debug($db, "Performing redirect after logout");
        if ($redirect_url == DEFAULT_PAGE or empty($redirect_url) or $redirect_url=='/') {
            header('Location: '.LOGIN_PAGE);
            } else {
            header('Location: '.LOGIN_PAGE.'?redirect_url='.$redirect_url);
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
    
    // Исправляем домен - убираем порт
    $domain_parts = explode(':', $_SERVER['HTTP_HOST']);
    $clean_domain = $domain_parts[0];
    
    // Старт сессии с безопасными настройками
    session_start([
        'cookie_lifetime' => SESSION_LIFETIME,
        'cookie_path' => '/',
        'cookie_domain' => $clean_domain, // Без порта!
        'cookie_secure' => isset($_SERVER['HTTPS']),
        'cookie_httponly' => true, // Включаем httponly для безопасности
        'cookie_samesite' => 'Lax',
        'gc_maxlifetime' => SESSION_LIFETIME,
    ]);
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
