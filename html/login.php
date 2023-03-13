<?php

require_once ($_SERVER['DOCUMENT_ROOT']."/inc/auth.utils.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/languages/" . HTML_LANG . ".php");

$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit'])) {

    $login = trim($_POST['login']);
    $password = trim($_POST['password']);

    // validate if login is empty
    if (empty($login)) {
        $error .= '<p class="error">Please enter login.</p>';
	}

    // validate if password is empty
    if (empty($password)) {
        $error .= '<p class="error">Please enter your password.</p>';
	}

    if (empty($error)) {
	if (login($db_link)) { header("Location: /admin/index.php"); }
	}

    }
?>

<!DOCTYPE html>
<html>
    <head>
    <title><?php echo WEB_site_title; ?> login</title>
    <link rel="stylesheet" type="text/css" href="/<?php echo HTML_STYLE.'.css'; ?>">
    <link rel="stylesheet" type="text/css" href="/login.css" >
    <meta http-equiv="content-type" content="application/xhtml+xml" />
    <meta charset="UTF-8" />
    </head>
    <body>
	<div class="login">
	    <h1>Login</h1>
	    <form action="" method="post">
		<label for="username">
		    <i class="fas fa-user"></i>
		</label>
		<input type="text" name="login" placeholder="Username" id="login" required>
		<label for="password">
		    <i class="fas fa-lock"></i>
		</label>
		<input type="password" name="password" placeholder="Password" id="password" required>
		<input type="submit" name="submit" value="Login">
	    </form>
	</div>
    </body>
</html>
