<?php

define("CONFIG", 1);
define("SQL", 1);
require_once($_SERVER['DOCUMENT_ROOT'] . "/cfg/config.php");
require_once($_SERVER['DOCUMENT_ROOT'] . "/inc/sql.php");
require_once($_SERVER['DOCUMENT_ROOT'] . "/inc/common.php");

ini_set('session.use_trans_sid', true);
ini_set('session.use_only_cookies', false);

define('SESSION_TABLE', 'sessions');
define('USER_SESSIONS_TABLE', 'user_sessions');

//set default const values
if (!defined('SESSION_LIFETIME') || SESSION_LIFETIME < 60) { define('SESSION_LIFETIME', 86400); }
if (!defined("HTML_LANG")) { define("HTML_LANG","english"); }
if (!defined("HTML_STYLE")) { define("HTML_STYLE","white"); }
if (!defined("IPCAM_GROUP_ID")) { define("IPCAM_GROUP_ID","5"); }
if (!defined("SNMP_timeout")) { define("SNMP_timeout","500000"); }
if (!defined("SNMP_retry")) { define("SNMP_retry","1"); }

// Инициализация сессий в БД
function init_db_sessions($db) {
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
function sess_open($savePath, $sessionName) { return true; }
function sess_close() { return true; }

function sess_read($sessionId) {
    global $db_link;
    $sessionId = mysqli_real_escape_string($db_link, $sessionId);
    $result = mysqli_query($db_link, "SELECT data FROM ".SESSION_TABLE." WHERE id = '$sessionId'");
    if (!$result) {
        error_log("Session read failed: " . mysqli_error($db_link));
        return '';
    }
    return mysqli_num_rows($result) ? mysqli_fetch_assoc($result)['data'] : '';
}

function sess_write($sessionId, $data) {
    global $db_link;
    $sessionId = mysqli_real_escape_string($db_link, $sessionId);
    $data = mysqli_real_escape_string($db_link, $data);
    $time = time();
    $query = "INSERT INTO ".SESSION_TABLE." (id, data, last_accessed) 
              VALUES ('$sessionId', '$data', $time)
              ON DUPLICATE KEY UPDATE data = '$data', last_accessed = $time";

    if (!mysqli_query($db_link, $query)) {
        error_log("Session write failed: " . mysqli_error($db_link));
        return false;
    }
    return true;
}

function sess_destroy($sessionId) {
    global $db_link;
    $sessionId = mysqli_real_escape_string($db_link, $sessionId);
    if (!mysqli_query($db_link, "DELETE FROM ".SESSION_TABLE." WHERE id = '$sessionId'")) {
        error_log("Session destroy failed: " . mysqli_error($db_link));
        return false;
    }
    return true;
}

function sess_gc($maxLifetime) {
    global $db_link;
    $old = time() - $maxLifetime;
    if (!mysqli_query($db_link, "DELETE FROM ".SESSION_TABLE." WHERE last_accessed < $old")) {
        error_log("Session GC failed: " . mysqli_error($db_link));
        return false;
    }
    return true;
}


function login($db) {

    // 1. Проверка активной сессии
    if (!empty($_SESSION['user_id']) && validate_session($db)) {
        // Дополнительная валидация сессии
        if ($_SESSION['ip'] === get_user_ip() && 
            $_SESSION['user_agent'] === ($_SERVER['HTTP_USER_AGENT'] ?? '')) {
            return true;
        }
        // Несоответствие параметров - разрушаем сессию
        logout($db);
    }

    // 2. Проверка API-авторизации (для API-запросов)
    if (strpos($_SERVER['REQUEST_URI'], '/api.php') === 0) {
        return IsSilentAuthenticated($db);
    }

    // 4. Проверка логина/пароля из POST-данных (обычная форма входа)
    if (!empty($_POST['login']) && !empty($_POST['password'])) {
        if (authenticate_by_credentials($db, $_POST['login'], $_POST['password'])) {
            LOG_INFO($db, "Logged in customer id: ".$_SESSION['user_id']." name: ".$_SESSION['login']." from ".$_SESSION['ip']." with acl: ".$_SESSION['acl']);
            return true;
        }
        // Неудачная попытка входа
        sleep(1); // Защита от брутфорса
    }

    // 5. Если ни один метод не сработал - требовать авторизацию
    logout($db);
    exit;
}

function authenticate_by_credentials($db,$login,$password) {

    $login = mysqli_real_escape_string($db, trim($login));
    $query = "SELECT * FROM `Customers` WHERE Login='{$login}'";
    $user = get_record_sql($db, $query);

    if (empty($user)) {
        sleep(1);
        return false;
    }

    if (!password_verify($password, $user['password'])) {
        sleep(1);
        return false;
    }

    // Создание сессии
    session_regenerate_id(true);

    $_SESSION = [
        'user_id'    => $user['id'],
        'login'      => $user['Login'],
        'acl'        => $user['rights'],
        'ip'         => get_client_ip(),
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
        'created'    => time()
    ];

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
    if (!mysqli_query($db, $query)) {
        error_log("Session DB error: ".mysqli_error($db));
        return false;
    }

    return true;
}

function validate_session($db) {

    // Проверка IP и User-Agent
    if ($_SESSION['ip'] !== get_client_ip() || 
        $_SESSION['user_agent'] !== ($_SERVER['HTTP_USER_AGENT'] ?? '')) {
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

    if (!$result || mysqli_num_rows($result) === 0) {
        logout($db);
        return false;
    }

    // Обновление времени активности
    mysqli_query($db, 
        "UPDATE ".USER_SESSIONS_TABLE." 
         SET last_activity = ".time()." 
         WHERE session_id = '$sessionId'");

    return true;
}

function get_client_ip() {
    foreach (['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $key) {
        if (!empty($_SERVER[$key])) {
            $ip = trim(current(explode(',', $_SERVER[$key])));
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
    }
    return '127.0.0.1';
}

// Авторизация по API-ключу (без пароля)
function IsSilentAuthenticated($db) {
    if (!empty($_SESSION['user_id'])) {
        return true;
    }

    $auth_ip = get_user_ip();
    $api_key = '';

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

    if (empty($login) || empty($api_key) || strlen($api_key) < 20) {
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
        error_log("API auth failed for: $login");
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

    LOG_INFO($db, "Logged in to api customer id: ".$_SESSION['user_id']." name: ".$_SESSION['login']." from ".$_SESSION['ip']." with acl: ".$_SESSION['acl']);
    return true;
}

// Выход из системы (полная версия)
function logout($db, $silent = FALSE) {
    if (session_status() === PHP_SESSION_ACTIVE) {
        LOG_INFO($db, "Logout customer id: ".$_SESSION['user_id']." name: ".$_SESSION['login']." from ".$_SESSION['ip']." with acl: ".$_SESSION['acl']);
        // Деактивация сессии в БД
        $sessionId = mysqli_real_escape_string($db, session_id());
        mysqli_query($db, 
            "UPDATE ".USER_SESSIONS_TABLE." 
             SET is_active = 0 
             WHERE session_id = '$sessionId'");
        // Очистка данных
        $_SESSION = [];
        session_destroy();
        setcookie(session_name(), '', time() - SESSION_LIFETIME, '/');
        // Удаление авторизационной куки (если есть)
        if (isset($_COOKIE['Auth'])) {
            setcookie('Auth', '', time() - SESSION_LIFETIME, '/');
        }
    }
    if (!$silent) {
        header('Location: /login.php');
        //?redirect=' . urlencode($_SERVER['REQUEST_URI']));
        }
}

// Инициализация системы сессий
init_db_sessions($db_link);

// Инициализация сессии
if (session_status() !== PHP_SESSION_ACTIVE) {
        // Старт сессии с безопасными настройками
        session_start([
            'cookie_lifetime' => SESSION_LIFETIME ,
//          'cookie_secure'   => true,
//          'cookie_httponly' => true,
            'cookie_samesite' => 'Strict',
            'gc_maxlifetime' => SESSION_LIFETIME
        ]);
    }
