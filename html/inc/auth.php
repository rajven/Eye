<?php
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/login.php");
login($db_link);
// считываем текущее время
$start_time = microtime();
// разделяем секунды и миллисекунды (становятся значениями начальных ключей массива-списка)
$start_array = explode(" ",$start_time);
// это и есть стартовое время
$start_time = $start_array[1] + $start_array[0]; 

$page_full_url=$_SERVER['PHP_SELF'];
$page_url_array = explode('?', $page_full_url);
$page_url = $page_url_array[0];
$page_url_args = $page_url_array[1];
if (!empty($_GET['id'])) { $page_url = $page_url.'=id='.$_GET["id"]; }
if (empty($_GET['id']) and !empty($_POST['id'])) { $page_url = $page_url.'=id='.$_POST["id"]; }

if (isset($_GET['logout'])) { session_destroy(); header("Location: /logout.php"); }

if (isset($_GET['page'])){ $page = $_GET['page']*1; }
if (isset($_POST['page'])){ $page = $_POST['page']*1; }
if (!isset($page) and isset($_SESSION[$page_url]['page'])) { $page=$_SESSION[$page_url]['page']*1; }
if (!isset($page)) { $page=1; }

if (!isset($default_displayed)) { $default_displayed=50; }

if (isset($_POST['rows'])) { $displayed=$_POST['rows']*1; }
if (!isset($displayed) and isset($_SESSION[$page_url]['rows'])) { $displayed=$_SESSION[$page_url]['rows']*1; }
if (!isset($displayed)) { $displayed=$default_displayed; }

$_SESSION[$page_url]['page']=$page;
$_SESSION[$page_url]['rows']=$displayed;

?>
