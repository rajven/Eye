<?php
require_once($_SERVER['DOCUMENT_ROOT'] . "/inc/auth.php");
require_once($_SERVER['DOCUMENT_ROOT'] . "/inc/languages/" . HTML_LANG . ".php");

if (!defined("CONFIG")) die("Not defined");

$page_url = null;

// Получаем массив fid только из POST
$fid = getPOST('fid', null, []); // возвращаем пустой массив по умолчанию

// Проверяем наличие действия "RemoveUser" и флага f_deleted
$remove_action = getPOST('RemoveUser', null, null);
$f_deleted    = getPOST('f_deleted', null, null);

$all_ok = true;

if ($remove_action !== null && $f_deleted !== null && $f_deleted !== '') {
    foreach ($fid as $val) {
        $id = (int)$val;
        if ($id > 0) {
            $result = delete_user($db_link, $id);
            if (!$result) {
                $all_ok = false;
            }
        }
    }
}

$message = $all_ok ? "Success!" : "Fail!";
print "<div style='padding:20px; font-size:18px; background:#e9f7ef; border:1px solid #2ecc71;'>$message</div>";

?>
