<?php
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/auth.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/languages/" . HTML_LANG . ".php");
require_once ($_SERVER["DOCUMENT_ROOT"]."/inc/idfilter.php");

$ou_info = get_record_sql($db_link,'SELECT * FROM ou WHERE id=?', [$id]);

// Сохранение настроек OU
if (getPOST("save") !== null) {
    $new = [
        'ou_name'                => trim(getPOST("f_group_name", null, $ou_info['ou_name'])),
        'default_users'          => (int)getPOST("f_default", null, 0),
        'default_hotspot'        => (int)getPOST("f_default_hotspot", null, 0),
        'nagios_dir'             => trim(getPOST("f_nagios", null, '')),
        'nagios_host_use'        => trim(getPOST("f_nagios_host", null, '')),
        'nagios_ping'            => trim(getPOST("f_nagios_ping", null, 0)),
        'nagios_default_service' => trim(getPOST("f_nagios_service", null, '')),
        'queue_id'               => (int)getPOST("f_queue_id", null, 0),
        'filter_group_id'        => (int)getPOST("f_filter_group_id", null, 0),
        'enabled'                => (int)getPOST("f_enabled", null, 0),
        'dynamic'                => (int)getPOST("f_dynamic", null, 0)
    ];

    // Обработка life_duration
    if ($new['dynamic']) {
        $tmp_life_duration = str_replace(',', '.', getPOST("f_life_duration", null, 0));
        $new['life_duration'] = (!empty($tmp_life_duration) && is_numeric($tmp_life_duration)) 
            ? (float)$tmp_life_duration 
            : 0;
    } else {
        $new['life_duration'] = 0;
    }

    // Сброс флагов по умолчанию
    if ($new['default_users']) {
        update_records($db_link, "ou", "id != ?", ['default_users' => 0], [$id]);
    }
    if ($new['default_hotspot']) {
        update_records($db_link, "ou", "id != ?", ['default_hotspot' => 0], [$id]);
    }

    update_record($db_link, "ou", "id = ?", $new, [$id]);
    header("Location: " . $_SERVER["REQUEST_URI"]);
    exit;
}

// Удаление правил авторизации
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

// Сохранение изменений в правилах
if (getPOST("s_save") !== null) {
    $s_ids = getPOST("s_id", null, []);
    $n_ids = getPOST("n_id", null, []);
    $s_types = getPOST("s_type", null, []);
    $s_rules = getPOST("s_rule", null, []);
    $s_descriptions = getPOST("s_description", null, []);

    if (is_array($s_ids) && is_array($n_ids)) {
        // Преобразуем ID в целые числа
        $n_ids = array_map('intval', $n_ids);
        $s_ids = array_map('intval', $s_ids);
        foreach ($s_ids as $save_id) {
            if ($save_id <= 0) continue;
            $idx = array_search($save_id, $n_ids, true);
            if ($idx === false) continue;
            // Получаем тип правила
            $rule_type = (int)($s_types[$idx] ?? 3);
            // Получаем и очищаем правило
            $raw_rule = trim($s_rules[$idx] ?? '');
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
            // Для других типов (3 и т.д.) — без валидации
            $new = [
                'rule_type'   => $rule_type,
                'rule'        => $new_rule,
                'description' => trim($s_descriptions[$idx] ?? '')
            ];
            update_record($db_link, "auth_rules", "id = ?", $new, [$save_id]);
        }
    }
    header("Location: " . $_SERVER["REQUEST_URI"]);
    exit;
}
// Создание нового правила
if (getPOST("s_create") !== null) {
    $new_rule = trim(getPOST("s_new_rule", null, ''));
    if ($new_rule !== '') {
        $rule_type  = (int)getPOST("s_new_type", null, 3);
        if ($rule_type == 1 and !checkValidIp($new_rule)) {
                header("Location: " . $_SERVER["REQUEST_URI"]);
                exit;
                }
        if ($rule_type == 2 and MayBeMac($new_rule)==null) {
                header("Location: " . $_SERVER["REQUEST_URI"]);
                exit;
                }
        if ($rule_type == 2) { $new_rule = MayBeMac($new_rule); }
        $new = [
            'rule_type'    => $rule_type,
            'rule'    => $new_rule,
            'ou_id'   => $id
        ];
        insert_record($db_link, "auth_rules", $new);
    }
    header("Location: " . $_SERVER["REQUEST_URI"]);
    exit;
}

require_once ($_SERVER['DOCUMENT_ROOT']."/inc/header.php");

fix_auth_rules($db_link);

?>
<div id="cont">
<form name="def" action="edit_group.php?id=<?php echo $id; ?>" method="post">
<input type="hidden" name="id" value=<?php echo $id; ?>>
<table class="data">
<tr align="center">
<td colspan=2><b><?php echo WEB_cell_name; ?></b></td>
<td><b>Default</b></td>
<td width=100><b>Hotspot</b></td>
<td><b><?php print WEB_cell_dynamic; ?></b></td>
</tr>
<?php

print "<tr align=center>\n";
print "<td colspan=2 class=\"data\"><input type=\"text\" name='f_group_name' value='{$ou_info['ou_name']}' style=\"width:95%;\"></td>\n";
if ($ou_info['default_users']) { $cl = "up"; } else { $cl="data"; }
print "<td class=\"$cl\">";  print_qa_select("f_default",$ou_info['default_users']); print "</td>\n";
if ($ou_info['default_hotspot']) { $cl = "up"; } else { $cl="data"; }
print "<td class=\"$cl\">";  print_qa_select("f_default_hotspot",$ou_info['default_hotspot']); print "</td>\n";
print "<td class=\"data\">";  print_qa_select("f_dynamic",$ou_info['dynamic']); print "</td>\n";
?>
<tr>
<td><b>Nagios directory</b></td>
<td><b>Host template</b></td>
<td><b>Ping</b></td>
<td><b>Host service</b></td>
<td></td>
</tr>
<?php
print "<td class=\"data\"><input type=\"text\" name='f_nagios' value='{$ou_info['nagios_dir']}'></td>\n";
print "<td class=\"data\"><input type=\"text\" name='f_nagios_host' value='{$ou_info['nagios_host_use']}'></td>\n";
print "<td class=\"data\">"; print_qa_select("f_nagios_ping",$ou_info['nagios_ping']); print "</td>\n";
print "<td class=\"data\"><input type=\"text\" name='f_nagios_service' value='{$ou_info['nagios_default_service']}'></td>\n";
print "<td class=\"data\"></td>\n";
?>
</tr>
<tr><td colspan=4><?php echo WEB_ou_autoclient_rules; ?></td></tr>
<tr>
<td class="data"><?php print WEB_cell_enabled."&nbsp"; print_qa_select('f_enabled', $ou_info['enabled']); ?></td>
<td class="data"><?php print WEB_cell_filter."&nbsp"; print_filter_group_select($db_link, 'f_filter_group_id', $ou_info['filter_group_id']); ?></td>
<td class="data"><?php print WEB_cell_shaper."&nbsp"; print_queue_select($db_link, 'f_queue_id', $ou_info['queue_id']); ?></td>
<td class="data" align=right><?php print WEB_cell_life_hours."&nbsp"; 
print "<input type='number' step='0.01' min='0.01' id='f_life_duration' name='f_life_duration' value='" . htmlspecialchars($ou_info['life_duration'])."'";
if (!$ou_info['dynamic']) { print "disabled"; }; print " style=\"width:35%;\" ></td>\n"; ?>
<?php print "<td align=right class=\"data\"><button id='save' name='save' value='{$ou_info['id']}'>".WEB_btn_save."</button></td>\n"; ?>
</tr>
</table>
<br>
<b><?php echo WEB_ou_rules_for_autoassigning."&nbsp"; print $ou_info['ou_name']; ?></b>
<br>
<?php echo WEB_ou_rules_order; ?>: hotspot => subnet => mac => hostname => default user
<br><br>
<table class="data">
<tr align="center">
    <td></td>
    <td width=30><b>id</b></td>
    <td><b><?php echo WEB_cell_type; ?></b></td>
    <td><b><?php echo WEB_ou_rule; ?></b></td>
    <td><b><?php echo WEB_cell_description; ?></b></td>
    <td><input type="submit" onclick="return confirm('<?php echo WEB_msg_delete; ?>?')" name="s_remove" value="<?php echo WEB_btn_delete; ?>"></td>
    <?php print "<td><button id='s_save' name='s_save' value='s_save'>".WEB_btn_save."</button></td>"; ?>
</tr>
<?php
$t_auth_rules = get_records_sql($db_link,"SELECT * FROM auth_rules WHERE ou_id=? ORDER BY id", [ $id ]);
foreach ( $t_auth_rules as $row ) {
    print "<tr align=center>\n";
    print "<td class=\"data\" style='padding:0'><input type=checkbox name=s_id[] value='{$row['id']}'></td>\n";
    print "<td class=\"data\"><input type=\"hidden\" name='n_id[]' value='{$row['id']}'>{$row['id']}</td>\n";
    print "<td class=\"data\">"; print_qa_rule_select("s_type[]","{$row['rule_type']}"); print "</td>\n";
    print "<td class=\"data\"><input type=\"text\" name='s_rule[]' value='{$row['rule']}'></td>\n";
    print "<td class=\"data\"><input type=\"text\" name='s_description[]' value='{$row['description']}'></td>\n";
    print "<td colspan=2 class=\"data\"></td>\n";
    print "</tr>\n";
}
?>
</table>
<div>
<?php print WEB_ou_new_rule."&nbsp"; print_qa_rule_select("s_new_type","1");  
print "<input type=\"text\" name='s_new_rule' value=''>"; ?>
<input type="submit" name="s_create" value="<?php echo WEB_btn_add; ?>">
</div>
</form>

<script>
document.getElementById('f_dynamic').addEventListener('change', function(event) {
  const selectValue = this.value;
  const inputField = document.getElementById('f_life_duration');
  if (selectValue === '1') {
    inputField.disabled = false;
    inputField.value=24;
  } else {
    inputField.disabled = true;
  }
});

</script>

<?php
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/footer.php");
?>
