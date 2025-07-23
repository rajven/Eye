<?php

require_once ($_SERVER['DOCUMENT_ROOT']."/inc/auth.utils.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/languages/" . HTML_LANG . ".php");

$error = '';

function getSafeRedirectUrl(string $default = '/'): string {
    $url = filter_input(INPUT_GET, 'redirect_url', FILTER_SANITIZE_URL) 
        ?? filter_input(INPUT_POST, 'redirect_url', FILTER_SANITIZE_URL) 
        ?? $default;

    $decodedUrl = urldecode($url);
    // Проверяем:
    // 1. URL начинается с `/` (но не `//` или `http://`)
    // 2. Содержит только разрешённые символы (a-z, 0-9, -, _, /, ?, =, &, ., ~)
    if (!preg_match('/^\/(?!\/)[a-z0-9\-_\/?=&.~]*$/i', $decodedUrl)) {
        return $default;
    }

    // Проверяем:
    // 1. Начинается с /, не содержит //, ~, %00
    // 2. Разрешённые символы: a-z, 0-9, -, _, /, ?, =, &, .
    // 3. Допустимые форматы:
    //    - /path/          (слэш на конце)
    //    - /path           (без слэша)
    //    - /file.html      (только .html)
    //    - /script.php     (только .php)
    //    - Любой вариант с параметрами (?id=1)
    if (!preg_match(
        '/^\/'                      // Начинается с /
        . '(?!\/)'                  // Не //
        . '[a-z0-9\-_\/?=&.]*'      // Разрешённые символы
        . '(?:\/'                   // Варианты окончаний:
          . '|\.(html|php)(?:\?[a-z0-9\-_=&]*)?'  // .html/.php (+ параметры)
          . '|(?:\?[a-z0-9\-_=&]*)?' // Или параметры без расширения
        . ')$/i', 
        $decodedUrl
    )) {
        return $default;
    }

    // Дополнительная защита: явно блокируем /config/, /vendor/ и т.д.
    if (preg_match('/(^|\/)(cfg|inc|log|sessions|tmp)(\/|$)/i', $decodedUrl)) {
        return $default;
    }

    return $url;
}

// Использование
$redirect_url = getSafeRedirectUrl(DEFAULT_PAGE);

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit'])) {

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
    <link rel="stylesheet" type="text/css" href="/css/<?php echo HTML_STYLE.'.css'; ?>">
    <link rel="stylesheet" type="text/css" href="/login.css" >
    <meta http-equiv="content-type" content="application/xhtml+xml" />
    <meta charset="UTF-8" />
    </head>
    <body>
	<div class="login">
	    <h1><?php echo WEB_msg_login; ?></h1>
	    <form action="" method="post">
		<label for="username">
		    <i class="fas fa-user"></i>
		</label>
		<input type="text" name="login" placeholder="<?php echo WEB_msg_username; ?>" id="login" required autofocus>
		<label for="password">
		    <i class="fas fa-lock"></i>
		</label>
		<input type="password" name="password" placeholder="<?php echo WEB_msg_password; ?>" id="password" required>
                <input type="hidden" name="redirect_url" value="<?php print htmlspecialchars($redirect_url); ?>">
		<input type="submit" name="submit" value="<?php echo WEB_btn_login; ?>">
	    </form>
	</div>
    </body>
</html>
