<?php
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/auth.utils.php");

login($db_link);

if (!empty($_GET['redirect'])) {
    // Очищаем и проверяем URL
    $redirect = trim($_GET['redirect']);
    // Удаляем все слеши в начале, чтобы избежать дублирования
    $redirect = ltrim($redirect, '/');
    // Проверяем, что URL не ведет на другой домен и не содержит опасных символов
    if (preg_match('/^[a-zA-Z0-9\-_\/\.]+$/', $redirect) && 
        strpos($redirect, '..') === false && 
        strpos($redirect, '//') === false) {
        // Проверяем, что текущий URL не совпадает с целевым
        $currentPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $currentPath = ltrim($currentPath, '/');
        if ($currentPath !== $redirect) {
            header('Location: /' . $redirect);
            exit;
        }
    }
    // Если проверки не прошли - игнорируем
}

$start_time = microtime();
$start_array = explode(" ",$start_time);
$start_time = $start_array[1] + $start_array[0];

$page_full_url=$_SERVER['PHP_SELF'];
$page_url_array = explode('?', $page_full_url);

$page_url=$_SERVER["REQUEST_URI"];

if (!empty($page_url_array[0])) { $page_url = $page_url_array[0]; }
if (!empty($page_url_array[1])) { $page_url_args = $page_url_array[1]; } else { $page_url_args=''; }

if (!empty($_GET['id'])) { $id = $_GET["id"]; }
if (!empty($_POST['id'])) { $id = $_POST["id"]; }
if (!empty($id) and !empty($page_url)) { $page_url = $page_url.'?id='.$id; }

if (empty($page_url)) {
    header("Location: /admin/index.php");
    exit;
    }

if (isset($_GET['page'])){ $page = $_GET['page']; }
if (isset($_POST['page'])){ $page = $_POST['page']; }
if (!isset($page) and isset($_SESSION[$page_url]['page'])) { $page=$_SESSION[$page_url]['page']; }
if (!isset($page)) { $page=1; }

if (!isset($default_displayed)) { $default_displayed=50; }

if (isset($_POST['rows'])) { $displayed=$_POST['rows']; }
if (!isset($displayed) and isset($_SESSION[$page_url]['rows'])) { $displayed=$_SESSION[$page_url]['rows']; }
if (!isset($displayed)) { $displayed=$default_displayed; }

$_SESSION[$page_url]['page']=$page;
$_SESSION[$page_url]['rows']=$displayed;

?>
