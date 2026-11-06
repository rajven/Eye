<?php

require_once ($_SERVER['DOCUMENT_ROOT']."/inc/auth.utils.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/languages/" . HTML_LANG . ".php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/csrf.php");

if (session_status() !== PHP_SESSION_ACTIVE) { init_db_sessions($db_link); session_start(); }
csrf_token($db_link);

$error = '';

// Использование
$redirect_url = getSafeRedirectUrl(DEFAULT_PAGE);

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit'])) {
    csrf_verify_post($db_link);

    $login = trim($_POST['login']);
    $password = trim($_POST['password']);

    // validate if login is empty
    if (empty($login)) {
        $error .= '<p class="error">'.WEB_msg_login_hint.'.</p>';
	}

    // validate if password is empty
    if (empty($password)) {
        $error .= '<p class="error">'.WEB_msg_password_hint.'.</p>';
	}

    if (empty($error)) {
	if (login($db_link)) { 
	    csrf_reset();
            $redirect_url = urldecode($redirect_url);
            header("Location: $redirect_url");
            }
	}

    }
?>

<!DOCTYPE html>
<html>
    <head>
    <title><?php echo WEB_site_title; ?> login</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta charset="UTF-8" />
    <link rel="stylesheet" type="text/css" href="/css/<?php echo HTML_STYLE.'.css'; ?>">
    </head>
    <body class="eye-login">
	<div class="login">
	    <h1><?php echo WEB_msg_login; ?></h1>
	    <?php /* $error was built at the top of the file but never rendered before */ echo $error; ?>
	     <form action="login.php" method="post">
		<input type="hidden" name="csrf_token" value="<?php print csrf_token($db_link); ?>">
		<label for="username">
		    <span aria-hidden="true">&#128100;</span><span class="vh">Логин</span>
		</label>
		<input type="text" name="login" placeholder="<?php echo WEB_msg_username; ?>" id="login" required autofocus>
		<label for="password">
		    <span aria-hidden="true">&#128274;</span><span class="vh">Пароль</span>
		</label>
		<input type="password" name="password" placeholder="<?php echo WEB_msg_password; ?>" id="password" required>
                <input type="hidden" name="redirect_url" value="<?php print htmlspecialchars($redirect_url); ?>">
		<input type="submit" name="submit" value="<?php echo WEB_btn_login; ?>">
	    </form>
	</div>
    </body>
</html>
