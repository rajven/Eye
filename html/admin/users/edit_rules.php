<?php
require_once ($_SERVER['DOCUMENT_ROOT'] . "/inc/auth.php");
require_once ($_SERVER['DOCUMENT_ROOT'] . "/inc/languages/" . HTML_LANG . ".php");
require_once ($_SERVER['DOCUMENT_ROOT'] . "/inc/idfilter.php");

$msg_error = "";

$sSQL = "SELECT * FROM user_list WHERE id = ?";
$auth_info = get_record_sql($db_link, $sSQL, [$id]);

// Удаление правил
if (getPOST("s_remove") !== null) {
    $s_id = getPOST("s_id", null, []);
    if (!empty($s_id) && is_array($s_id)) {
        foreach ($s_id as $val) {
            $val = trim($val);
            if ($val === '') continue;
            delete_record($db_link, "auth_rules", "id = ?", [(int)$val]);
        }
    }
    header("Location: " . $_SERVER["REQUEST_URI"]);
    exit;
}

// Сохранение ОТМЕЧЕННЫХ правил
if (getPOST("s_save") !== null) {
    $selected_ids = getPOST("s_id", null, []);      // отмеченные чекбоксы
    $all_ids      = getPOST("n_id", null, []);      // все ID
    $types        = getPOST("s_type", null, []);
    $rules        = getPOST("s_rule", null, []);
    $descriptions = getPOST("s_description", null, []);

    if (!empty($selected_ids) && is_array($selected_ids)) {
        $selected_ids = array_map('intval', $selected_ids);
        $selected_set = array_flip($selected_ids);

        foreach ($all_ids as $i => $id) {
            $id = (int)$id;
            if ($id <= 0 || !isset($selected_set[$id])) continue;

            // Получаем тип правила
            $rule_type = (int)($types[$i] ?? 3);
            $raw_rule  = trim($rules[$i] ?? '');
            $desc      = trim($descriptions[$i] ?? '');

            if ($raw_rule === '') continue;

            $new_rule = $raw_rule;
            // Валидация в зависимости от типа
            if ($rule_type == 1) {
                // IP-адрес
                if (!checkValidIp($new_rule)) {
                    continue; // пропускаем невалидный IP
                }
            } elseif ($rule_type == 2) {
                // MAC-адрес
                $normalized_mac = MayBeMac($new_rule);
                if ($normalized_mac === null) {
                    continue; // пропускаем невалидный MAC
                }
                $new_rule = $normalized_mac;
            }

            $new = [
                'rule_type'   => $rule_type,
                'rule'        => $new_rule,
                'description' => $desc
            ];

            update_auth_rule($db_link, $new, $id);
        }
    }
    header("Location: " . $_SERVER["REQUEST_URI"]);
    exit;
}

// Создание нового правила
if (getPOST("s_create") !== null) {
    $new_rule = trim(getPOST("s_new_rule", null, ''));
    $rule_type = (int)getPOST("s_new_type", null, 3);
    if ($new_rule !== '') {
        if ($rule_type == 1 and !checkValidIp($new_rule)) {
                header("Location: " . $_SERVER["REQUEST_URI"]);
                exit;
                }
        if ($rule_type == 2 and MayBeMac($new_rule)==null) {
                header("Location: " . $_SERVER["REQUEST_URI"]);
                exit;
                }
        if ($rule_type == 2) { $new_rule = MayBeMac($new_rule); }
        add_auth_rule($db_link, $new_rule, $rule_type, $id);
    }
    header("Location: " . $_SERVER["REQUEST_URI"]);
    exit;
}

unset($_POST);
fix_auth_rules($db_link);

require_once ($_SERVER['DOCUMENT_ROOT'] . "/inc/header.php");
?>
<div id="cont">
<br>
<form name="def" action="edit_rules.php?id=<?php echo $id; ?>" method="post">
<b><?php print WEB_ou_rules_for_autoassigning . "&nbsp;"; print_url($auth_info['login'], "/admin/users/edituser.php?id=$id"); ?></b>
<br>
<?php echo WEB_ou_rules_order; ?>: hotspot => subnet => mac => hostname => default user
<br><br>
<table class="data">
<tr align="center">
    <td><input type="checkbox" onClick="checkAll(this.checked);"></td>
    <td width=30><b>id</b></td>
    <td><b><?php echo WEB_cell_type; ?></b></td>
    <td><b><?php echo WEB_ou_rule; ?></b></td>
    <td><b><?php echo WEB_cell_description; ?></b></td>
    <td>
        <!-- Кнопки управления справа -->
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
$t_auth_rules = get_records_sql($db_link, "SELECT * FROM auth_rules WHERE user_id = ? ORDER BY id", [$id]);
foreach ($t_auth_rules as $row) {
    print "<tr align=center>\n";
    print "<td class=\"data\" style='padding:0'><input type=\"checkbox\" name=\"s_id[]\" value=\"{$row['id']}\"></td>\n";
    print "<td class=\"data\"><input type=\"hidden\" name=\"n_id[]\" value=\"{$row['id']}\">{$row['id']}</td>\n";
    print "<td class=\"data\">";
    print_qa_rule_select("s_type[]", "{$row['rule_type']}");
    print "</td>\n";
    print "<td class=\"data\"><input type=\"text\" name=\"s_rule[]\" value=\"" . htmlspecialchars($row['rule']) . "\"></td>\n";
    print "<td class=\"data\"><input type=\"text\" name=\"s_description[]\" value=\"" . htmlspecialchars($row['description']) . "\"></td>\n";
    print "<td class=\"data\"></td>\n";
    print "</tr>\n";
}
?>
</table>

<div style="margin-top: 15px;">
    <?php 
    print WEB_ou_new_rule . "&nbsp;";
    print_qa_rule_select("s_new_type", "1");
    print '<input type="text" name="s_new_rule" value="">'; 
    ?>
    <input type="submit" name="s_create" value="<?php echo WEB_btn_add; ?>">
</div>

</form>
<?php
require_once ($_SERVER['DOCUMENT_ROOT'] . "/inc/footer.php");
?>
