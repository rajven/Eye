<?php
define("CONFIG", 1);
define("SQL", 1);
require_once ($_SERVER['DOCUMENT_ROOT']."/cfg/config.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/sql.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/common.php");

function is_session_exists() {
    $sessionName = session_name();
    if (isset($_COOKIE[$sessionName]) || isset($_REQUEST[$sessionName])) {
        session_start();
        return !empty($_SESSION);
    }
    return false;
}

function auth()
{
    header("WWW-Authenticate: Basic realm=\"Administration Panel\"");
    close_access();
    exit();
}

function close_access()
{
    header('HTTP/1.1 401 Unauthorized');
    echo "You must enter a valid login and password to access this resource\n";
    exit();
}

function login($db)
{
    session_start();

//default timeout 8h in seconds
    $inactive = 3600*8;
    if (!isset($_SESSION['timeout'])) { $_SESSION['timeout']=time(); }
    $session_life = time() - $_SESSION['timeout'];
    if($session_life > $inactive) { session_destroy(); header("Location: /logout.php"); }

    if (! isset($_SERVER['PHP_AUTH_USER']) and ! isset($_SERVER['PHP_AUTH_PW'])) {
        auth();
    }

    if (! IsAuthenticated($db)) {
        close_access();
        exit();
    }
}

function Silentlogin($db)
{
    session_start();
    if (! IsSilentAuthenticated($db)) {
        close_access();
        exit();
    }
}

function IsAuthenticated($db)
{
    if (isset($_SESSION['user_id'])) { return 1; }

    if (! isset($auth_ip)) {
        $auth_ip = get_user_ip();
        $_SESSION['IP'] = $auth_ip;
    }

    if (isset($_SERVER['PHP_AUTH_USER'])) {
        $login = trim($_SERVER['PHP_AUTH_USER']);
    }
    if (isset($_SERVER['PHP_AUTH_PW'])) {
        $pass = trim($_SERVER['PHP_AUTH_PW']);
    }

    if (! isset($login) or ! isset($pass)) {
        LOG_DEBUG($db, "login [$login] or password [$pass] undefined from $auth_ip: fail!");
        return false;
    }
    
    $login = htmlspecialchars(stripslashes(substr($login, 0, 20)));
    $pass = md5($pass);
    if ($login == '' or $pass == '') {
        LOG_DEBUG($db, "login [$login] or password [$pass] undefined from $auth_ip: fail!");
        return false;
    }

    // LOG_DEBUG($db,"Try login [$login] with password [$pass] from $auth_ip.");
    $query = "SELECT id FROM `Customers` WHERE Login='{$login}' AND `Pwd`='{$pass}' LIMIT 1";
    $auth_login = mysqli_query($db, $query);
    list ($auth_id) = mysqli_fetch_array($auth_login);
    if (isset($auth_id) and $auth_id > 0) {
        if (! isset($_SESSION['session_id'])) {
            session_regenerate_id();
            $_SESSION['session_id'] = session_id();
        }
        if (! isset($_SESSION['user_id'])) {
            LOG_DEBUG($db, "login user [$login] from " . $_SESSION['IP'] . ": success.");
        }
        $_SESSION['user_id'] = $auth_id;
        $_SESSION['login'] = $login;
        return 1;
    }
    LOG_DEBUG($db, "login user [$login] from " . $_SESSION['IP'] . ": fail!");
}

function IsSilentAuthenticated($db)
{
    if (isset($_SESSION['user_id'])) {
        return 1;
    }

    if (! isset($auth_ip)) {
        $auth_ip = get_user_ip();
        $_SESSION['IP'] = $auth_ip;
    }

    if (isset($_GET[login])) {
        $login = trim($_GET[login]);
    }
    if (isset($_POST[login])) {
        $login = trim($_POST[login]);
    }
    if (isset($_GET[password])) {
        $pass = trim($_GET[password]);
    }
    if (isset($_POST[password])) {
        $pass = trim($_POST[password]);
    }

    if (! isset($login) or ! isset($pass)) {
        LOG_DEBUG($db, "login or password undefined from $auth_ip: fail!");
        return false;
    }

    $login = htmlspecialchars(stripslashes(substr($login, 0, 20)));
    $pass = $pass;

    if ($login == '' or $pass == '') {
        LOG_DEBUG($db, "login or password undefined from $auth_ip: fail!");
        return false;
    }

    // LOG_DEBUG($db,"Try silent login [$login] with password [$pass] from $auth_ip.");
    $query = "SELECT id FROM `Customers` WHERE Login='{$login}' AND `Pwd`='{$pass}' LIMIT 1";

    $auth_login = mysqli_query($db, $query);
    list ($auth_id) = mysqli_fetch_array($auth_login);
    if (isset($auth_id) and $auth_id > 0) {
        if (! isset($_SESSION['session_id'])) {
            session_regenerate_id();
            $_SESSION['session_id'] = session_id();
        }
        if (! isset($_SESSION['user_id'])) {
            LOG_DEBUG($db, "login user [$login] from " . $_SESSION['IP'] . ": success.");
        }
        $_SESSION['user_id'] = $auth_id;
        $_SESSION['login'] = $login;
        return 1;
    }

    LOG_DEBUG($db, "Silent login user $login from " . $_SESSION['IP'] . ": fail!");
}

?>
