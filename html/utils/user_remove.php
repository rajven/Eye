<?php
require_once($_SERVER['DOCUMENT_ROOT'] . "/inc/auth.php");
require_once($_SERVER['DOCUMENT_ROOT'] . "/inc/languages/" . HTML_LANG . ".php");

if (!defined("CONFIG")) die("Not defined");

$page_url = null;

// Получаем массив fid только из POST (т.к. это форма удаления)
$fid = getPOST('fid', null, []); // возвращаем пустой массив по умолчанию

// Проверяем наличие действия "RemoveUser" и флага f_deleted
$remove_action = getPOST('RemoveUser', null, null);
$f_deleted    = getPOST('f_deleted', null, null);

if ($remove_action !== null && $f_deleted !== null && $f_deleted !== '') {
    $all_ok = true;

    foreach ($fid as $val) {
        $id = (int)$val;
        if ($id > 0) {
            $result = delete_user($db_link, $id);
            if (!$result) {
                $all_ok = false;
            }
        }
    }

    echo $all_ok ? "Success!" : "Fail!";
}

?>
