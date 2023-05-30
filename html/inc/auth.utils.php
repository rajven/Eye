<?php

define("CONFIG", 1);
define("SQL", 1);
require_once($_SERVER['DOCUMENT_ROOT'] . "/cfg/config.php");
require_once($_SERVER['DOCUMENT_ROOT'] . "/inc/sql.php");
require_once($_SERVER['DOCUMENT_ROOT'] . "/inc/common.php");

ini_set('session.use_trans_sid',true);
ini_set('session.use_only_cookies',false);

function logout()
{
    if (!session_id()) {
        session_start();
    }
    if (session_id()) {
        // Если есть активная сессия, удаляем куки сессии
        setcookie(session_name(), session_id(), time() - 60 * 60 * 24);
        session_unset();
        session_destroy();
    }
    header("Location: /login.php");
}

function qlogout()
{
    if (!session_id()) {
        session_start();
    }
    if (session_id()) {
        // Если есть активная сессия, удаляем куки сессии
        setcookie(session_name(), session_id(), time() - 60 * 60 * 24);
        session_unset();
        session_destroy();
    }
    exit;
}

//login by password
function login($db)
{
    if (!session_id()) {
        if (!session_start()) {
            logout();
            exit();
        }
    }
    if (!IsAuthenticated($db)) {
        logout();
        exit();
    }
    return true;
}

//login by api_key
function Silentlogin($db)
{
    if (!session_id()) {
        if (!session_start()) {
            logout();
            exit();
        }
    }
    if (!IsSilentAuthenticated($db)) {
        logout();
        exit();
    }
    return true;
}

function IsAuthenticated($db)
{
    if (!empty($_SESSION['user_id'])) {
        return true;
    }

    if (empty($auth_ip)) {
        $auth_ip = get_user_ip();
        $_SESSION['IP'] = $auth_ip;
    }

    if (!empty($_POST['login'])) {
        $login = trim($_POST['login']);
    }
    if (!empty($_POST['password'])) {
        $pass = trim($_POST['password']);
    }


    if (empty($login) or empty($pass)) {
        LOG_INFO($db, "login [$login] or password [$pass] undefined from $auth_ip: fail!");
        logout();
        return false;
    }

    $login = htmlspecialchars(stripslashes($login));
    if (empty($login) or empty($pass)) {
        LOG_INFO($db, "login [$login] or password [$pass] undefined from $auth_ip: fail!");
        logout();
        return false;
    }

    $query = "SELECT * FROM `Customers` WHERE Login='{$login}'";
    $auth_record = get_record_sql($db, $query);
    if (!empty($auth_record)) {
        if (password_verify($pass, $auth_record['password'])) {
            if (empty($_SESSION['session_id'])) {
                session_regenerate_id();
                $_SESSION['session_id'] = session_id();
            }
            if (empty($_SESSION['user_id'])) {
                LOG_INFO($db, "login user [$login] from " . $_SESSION['IP'] . ": success.");
            }
            $_SESSION['user_id'] = $auth_record['id'];
            $_SESSION['login'] = $login;
            return true;
        } else {
            LOG_INFO($db, "login user [$login] from " . $_SESSION['IP'] . ": fail!");
            logout();
            return false;
        }
    }
    LOG_INFO($db, "login user [$login] from " . $_SESSION['IP'] . ": fail!");
    logout();
    return false;
}

function IsSilentAuthenticated($db)
{
    if (!empty($_SESSION['user_id'])) {
        return true;
    }

    if (empty($auth_ip)) {
        $auth_ip = get_user_ip();
        $_SESSION['IP'] = $auth_ip;
    }

    if (!empty($_GET['login'])) {
        $login = trim($_GET['login']);
    }
    if (!empty($_POST['login'])) {
        $login = trim($_POST['login']);
    }

    if (!empty($_GET['password'])) {
        $pass = trim($_GET['password']);
    }
    if (!empty($_POST['password'])) {
        $pass = trim($_POST['password']);
    }
    if (!empty($_GET['api_key'])) {
        $pass = trim($_GET['api_key']);
    }
    if (!empty($_POST['api_key'])) {
        $pass = trim($_POST['api_key']);
    }

    if (empty($login) or empty($pass)) {
        LOG_INFO($db, "login or password undefined from $auth_ip: fail!");
        logout();
        return false;
    }

    $login = htmlspecialchars(stripslashes($login));

    if ($login == '' or $pass == '') {
        LOG_INFO($db, "login or password undefined from $auth_ip: fail!");
        logout();
        return false;
    }

    $query = "SELECT id FROM `Customers` WHERE Login='{$login}' AND `api_key`='{$pass}'";
    $auth_record = get_record_sql($db, $query);
    if (!empty($auth_record)) {
        if (empty($_SESSION['session_id'])) {
            session_regenerate_id();
            $_SESSION['session_id'] = session_id();
        }
        if (empty($_SESSION['user_id'])) {
            LOG_INFO($db, "Silent login user [$login] from " . $_SESSION['IP'] . ": success.");
        }
        $_SESSION['user_id'] = $auth_record['id'];
        $_SESSION['login'] = $login;
        return true;
    }

    LOG_INFO($db, "Silent login user $login from " . $_SESSION['IP'] . ": fail!");
    logout();
    return false;
}
