<?php

require_once ($_SERVER['DOCUMENT_ROOT']."/inc/auth.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/languages/" . HTML_LANG . ".php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/idfilter.php");

$msg_error = "";

$sSQL = "SELECT * FROM user_auth WHERE id = ?";
$auth_info = get_record_sql($db_link, $sSQL, [$id]);

if (empty($auth_info['dns_name']) || $auth_info['deleted']) {
    header("Location: /admin/users/editauth.php?id=" . $id);
    exit;
}

// Очистка удалённых алиасов
delete_records($db_link, "user_auth_alias", "auth_id IN (SELECT id FROM user_auth WHERE deleted = 1)");

// Удаление алиасов
if (getPOST("s_remove") !== null) {
    $s_id = getPOST("s_id", null, []);
    
    if (!empty($s_id) && is_array($s_id)) {
        foreach ($s_id as $val) {
            $val = trim($val);
            if ($val === '') continue;
            delete_record($db_link, "user_auth_alias", "id = ?", [(int)$val]);
        }
    }
    
    header("Location: " . $page_url);
    exit;
}

// Сохранение изменений в алиасах
if (getPOST("s_save") !== null) {
    $selected_ids = getPOST("s_id", null, []);        // отмеченные чекбоксы
    $all_ids      = getPOST("n_id", null, []);        // все ID
    $s_aliases    = getPOST("s_alias", null, []);
    $s_descriptions = getPOST("s_description", null, []);

    if (!empty($selected_ids) && is_array($selected_ids)) {
        $selected_ids = array_map('intval', $selected_ids);
        $selected_set = array_flip($selected_ids);
        $domain_zone = ltrim(get_option($db_link, 33), '.');

        foreach ($all_ids as $i => $id) {
            $id = (int)$id;
            if ($id <= 0 || !isset($selected_set[$id])) continue;
            $f_dnsname = trim($s_aliases[$i] ?? '');
            if ($f_dnsname !== '') {
                // Удаляем доменную зону из конца
                $escaped_zone = preg_quote($domain_zone, '/');
                $f_dnsname = preg_replace('/\.' . $escaped_zone . '$/i', '', $f_dnsname);
                $f_dnsname = preg_replace('/\s+/', '-', $f_dnsname);
            }
            // Валидация
            if (
                $f_dnsname === '' ||
                !checkValidHostname($f_dnsname) ||
                !checkUniqHostname($db_link, $id, $f_dnsname)
            ) {
                continue;
            }
            $new = [
                'alias'      => $f_dnsname,
                'description' => trim($s_descriptions[$i] ?? '')
            ];
            update_record($db_link, "user_auth_alias", "id = ?", $new, [$id]);
        }
    }
    header("Location: " . $page_url);
    exit;
}

// Создание нового алиаса
if (getPOST("s_create") !== null) {
    $new_alias = trim(getPOST("s_create_alias", null, ''));
    
    if ($new_alias !== '') {
        $domain_zone = ltrim(get_option($db_link, 33), '.');
        $f_dnsname = $new_alias;
        
        if ($f_dnsname !== '') {
            $escaped_zone = preg_quote($domain_zone, '/');
            $f_dnsname = preg_replace('/\.' . $escaped_zone . '$/i', '', $f_dnsname);
            $f_dnsname = preg_replace('/\s+/', '-', $f_dnsname);
        }
        
        if (
            $f_dnsname === '' ||
            !checkValidHostname($f_dnsname) ||
            !checkUniqHostname($db_link, $id, $f_dnsname)
        ) {
            $msg_error = "DNS $f_dnsname already exists at: " . searchHostname($db_link, $id, $f_dnsname) . " Discard changes!";
            $_SESSION[$page_url]['msg'] = $msg_error;
            LOG_ERROR($db_link, $msg_error);
            header("Location: " . $_SERVER["REQUEST_URI"]);
            exit;
        }
        
        $new_rec = [
            'alias'    => $f_dnsname,
            'auth_id'  => $id
        ];
        
        insert_record($db_link, "user_auth_alias", $new_rec);
    }
    
    header("Location: " . $page_url);
    exit;
}

require_once ($_SERVER['DOCUMENT_ROOT']."/inc/header.php");


?>
<div id="cont">

<?php
    if (!empty($_SESSION[$page_url]['msg'])) {
        print '<div id="msg">' . $_SESSION[$page_url]['msg'] . '</div>';
        unset($_SESSION[$page_url]['msg']);
    }
?>

<br>
<form name="def" action="edit_alias.php?id=<?php echo $id; ?>" method="post">
<b><?php print WEB_user_alias_for."&nbsp"; print_url($auth_info['ip'],"/admin/users/editauth.php?id=$id"); ?></b> <br>
<table class="data">
<tr align="center">
    <td><input type="checkbox" onClick="checkAll(this.checked);"></td>
    <td width=30><b>id</b></td>
    <td><b><?php echo WEB_cell_name; ?></b></td>
    <td><b><?php echo WEB_cell_description; ?></b></td>
    <td>
        <!-- Контейнер для кнопок справа -->
        <div style="text-align: right; white-space: nowrap;">
            <input type="submit" name="s_save" value="<?php echo WEB_btn_save; ?>">
            <input type="submit" 
                   onclick="return confirm('<?php echo WEB_msg_delete; ?>?')" 
                   name="s_remove" 
                   value="<?php echo WEB_btn_delete; ?>"
                   style="margin-left: 8px;">
        </div>
    </td>
</tr>
<?php
$t_user_auth_alias = get_records_sql($db_link,"SELECT * FROM user_auth_alias WHERE auth_id=? ORDER BY id", [ $id ]);
if (!empty($t_user_auth_alias)) {
foreach ( $t_user_auth_alias as $row ) {
    print "<tr align=center>\n";
    print "<td class=\"data\" style='padding:0'><input type=checkbox name=s_id[] value='{$row['id']}'></td>\n";
    print "<td class=\"data\"><input type=\"hidden\" name='n_id[]' value='{$row['id']}'>{$row['id']}</td>\n";
    print "<td class=\"data\"><input type=\"text\" name='s_alias[]' value='{$row['alias']}' pattern=\"^(([a-zA-Z0-9]|[a-zA-Z0-9][a-zA-Z0-9\-]*[a-zA-Z0-9])\.)*([A-Za-z0-9]|[A-Za-z0-9][A-Za-z0-9\-]*[A-Za-z0-9])$\"></td>\n";
    print "<td class=\"data\"><input type=\"text\" name='s_description[]' value='{$row['description']}'></td>\n";
    print "<td class=\"data\"></td>\n";
    print "</tr>\n";
}
}
?>
</table>
<div>
<?php echo WEB_user_dns_add_alias; ?>:
<input type="text" name='s_create_alias' value='' pattern="^(([a-zA-Z0-9]|[a-zA-Z0-9][a-zA-Z0-9\-]*[a-zA-Z0-9])\.)*([A-Za-z0-9]|[A-Za-z0-9][A-Za-z0-9\-]*[A-Za-z0-9])$">
<input type="submit" name="s_create" value="<?php echo WEB_btn_add; ?>">
</div>
</form>
<?php
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/footer.php");
?>
