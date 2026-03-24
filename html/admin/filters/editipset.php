<?php
require_once($_SERVER['DOCUMENT_ROOT'] . "/inc/auth.php");
require_once($_SERVER['DOCUMENT_ROOT'] . "/inc/languages/" . HTML_LANG . ".php");
require_once($_SERVER['DOCUMENT_ROOT'] . "/inc/idfilter.php");

// Загружаем данные ipset
$ipset = get_record_sql($db_link, "SELECT * FROM ipset_list WHERE id=?", [ $id ]);
if (!$ipset) {
    die("IPSet not found");
}

// ==================== СОХРАНЕНИЕ НАСТРОЕК IPSET ====================
if (getPOST("save_ipset") !== null) {
    $new = [
        'name'        => trim(getPOST("f_name", null, $ipset['name'])),
        'description' => trim(getPOST("f_description", null, $ipset['description']))
    ];
    // Валидация имени (только латиница, цифры, подчёркивание, дефис)
    if (!preg_match('/^[a-zA-Z0-9_-]{1,64}$/', $new['name'])) {
        $error = WEB_error_ipset_name;
    } else {
        update_record($db_link, "ipset_list", "id = ?", $new, [$id]);
        header("Location: " . $_SERVER["REQUEST_URI"]);
        exit;
    }
}

// ==================== ДОБАВЛЕНИЕ ОДНОГО ЭЛЕМЕНТА ====================
if (getPOST("add_member") !== null) {
    $ip          = trim(getPOST("f_ip", null, ''));
    $description = trim(getPOST("f_member_desc", null, ''));
    
    if ($ip !== '') {
        // Валидация IP (IPv4 или IPv6)
        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            $new = [
                'ipset_id'    => $id,
                'ip'          => $ip,
                'description' => $description
            ];
            // insert_record автоматически обработает дубликат благодаря UNIQUE ключу
            @insert_record($db_link, "ipset_members", $new);
        } else {
            $error = WEB_error_ip_address . htmlspecialchars($ip);
        }
    }
    header("Location: " . $_SERVER["REQUEST_URI"]);
    exit;
}

// ==================== МАССОВОЕ ДОБАВЛЕНИЕ ====================
if (getPOST("add_members_bulk") !== null) {
    $bulk = trim(getPOST("f_bulk_ips", null, ''));
    if ($bulk !== '') {
        $lines = preg_split('/[\r\n]+/', $bulk);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || strpos($line, '#') === 0) continue;
            
            // Парсинг: "ip [description]"
            $parts = preg_split('/\s{2,}|\t/', $line, 2);
            $ip = trim($parts[0]);
            $desc = isset($parts[1]) ? trim($parts[1]) : '';
            
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                @insert_record($db_link, "ipset_members", [
                    'ipset_id'    => $id,
                    'ip'          => $ip,
                    'description' => $desc
                ]);
            }
        }
    }
    header("Location: " . $_SERVER["REQUEST_URI"]);
    exit;
}

// ==================== ОБНОВЛЕНИЕ ЭЛЕМЕНТОВ ====================
if (getPOST("update_members") !== null) {
    $member_ids = getPOST("f_member_id", null, []);
    if (!empty($member_ids) && is_array($member_ids)) {
        $f_desc = getPOST("f_member_desc_edit", null, []);
        
        foreach ($member_ids as $mid) {
            $mid = (int)$mid;
            if ($mid <= 0) continue;
            
            $new = [
                'description' => isset($f_desc[$mid]) ? trim($f_desc[$mid]) : ''
            ];
            update_record($db_link, "ipset_members", "id = ?", $new, [$mid]);
        }
    }
    header("Location: " . $_SERVER["REQUEST_URI"]);
    exit;
}

// ==================== УДАЛЕНИЕ ЭЛЕМЕНТОВ ====================
if (getPOST("remove_members") !== null) {
    $member_ids = getPOST("f_member_id", null, []);
    if (!empty($member_ids) && is_array($member_ids)) {
        foreach ($member_ids as $val) {
            if ($val !== '' && (int)$val > 0) {
                delete_record($db_link, "ipset_members", "id = ?", [(int)$val]);
            }
        }
    }
    header("Location: " . $_SERVER["REQUEST_URI"]);
    exit;
}

// ==================== ОЧИСТКА ВСЕГО IPSET ====================
if (getPOST("clear_all") !== null) {
    if (getPOST("confirm_clear") === 'yes') {
        delete_record($db_link, "ipset_members", "ipset_id = ?", [$id]);
    }
    header("Location: " . $_SERVER["REQUEST_URI"]);
    exit;
}

unset($_POST);

print_filters_submenu($page_url);
require_once($_SERVER['DOCUMENT_ROOT'] . "/inc/header.php");
?>

<div id="cont">
    <br><b>✏️ <?php echo WEB_ipset_edit.":&nbsp".htmlspecialchars($ipset['name']); ?></b><br>
    <?php if (!empty($error)): ?>
        <div style="color:red; margin:10px 0; padding:8px; background:#ffe0e0; border:1px solid #f99;">
            ⚠️ <?php echo $error; ?>
        </div>
    <?php endif; ?>

    <form method="post" action="?id=<?php echo $id; ?>" style="margin:15px 0">
        <table class="data">
            <tr>
                <td width="120"><b>Имя:</b></td>
                <td>
                    <input type="text" name="f_name" 
                           value="<?php echo htmlspecialchars($ipset['name']); ?>" 
                           size="40" maxlength="64" required 
                           pattern="[a-zA-Z0-9_-]+" 
                           title=<?php echo WEB_ipset_name_hint; ?>>
                </td>
            </tr>
            <tr>
                <td><b><?php echo WEB_cell_description; ?>:</b></td>
                <td>
                    <input type="text" name="f_description" 
                           value="<?php echo htmlspecialchars($ipset['description']); ?>" 
                           size="70" maxlength="255">
                </td>
            </tr>
            <tr>
                <td><b><?php echo WEB_cell_created; ?>:</b></td>
                <td><?php echo $ipset['created_at']; ?></td>
            </tr>
            <tr>
                <td><b><?php echo WEB_cell_update; ?>:</b></td>
                <td><?php echo $ipset['updated_at']; ?></td>
            </tr>
            <tr>
                <td colspan="2" align="right">
                    <input type="submit" name="save_ipset" value="💾<?php echo WEB_btn_save; ?>">
                </td>
            </tr>
        </table>
    </form>

    <hr>

    <!-- ========== ДОБАВЛЕНИЕ ЭЛЕМЕНТОВ (ipset_members) ========== -->
    <b>➕ Добавить IP-адрес</b>
    <form method="post" action="?id=<?php echo $id; ?>" style="margin:10px 0">
        <table class="data">
            <tr>
                <td><?php echo WEB_msg_IP; ?>:</td>
                <td>
                    <input type="text" name="f_ip" size="35" 
                           placeholder="192.168.1.1 or 2001:db8::1" 
                           pattern="^((25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)$|^([0-9a-fA-F]{0,4}:){2,7}[0-9a-fA-F]{0,4}$"
                           required>
                </td>
                <td><?php echo WEB_cell_description; ?>:</td>
                <td><input type="text" name="f_member_desc" size="30" maxlength="255"></td>
                <td><input type="submit" name="add_member" value="<?php echo WEB_btn_add; ?>"></td>
            </tr>
        </table>
    </form>

    <details style="margin:10px 0">
        <summary><b>📋 <?php echo WEB_ipset_massadd; ?></b></summary>
        <form method="post" action="?id=<?php echo $id; ?>" style="margin:10px 0">
            <textarea name="f_bulk_ips" rows="8" cols="80" 
                      placeholder="192.168.1.1&#10;10.0.0.0/24&#10;2001:db8::1&#9;<?php echo WEB_cell_description; ?>&#10;"></textarea><br>
            <small><?php echo WEB_ipset_massadd_hint; ?></small><br><br>
            <input type="submit" name="add_members_bulk" value="📥 <?php echo WEB_btn_add; ?>">
        </form>
    </details>

    <hr>

    <!-- ========== СПИСОК ЭЛЕМЕНТОВ (ipset_members) ========== -->
    <b>📦 <?php echo WEB_record_count; ?>
        <?php 
        $count = get_record_sql($db_link, "SELECT COUNT(*) as c FROM ipset_members WHERE ipset_id=?", [$id]);
        echo $count['c'] ?? 0; 
        ?>)</b>
    
    <form method="post" action="?id=<?php echo $id; ?>" style="margin:10px 0">
        <table class="data">
            <thead>
                <tr>
                    <td><input type="checkbox" id="chk_all" onclick="toggleAll(this)"></td>
                    <td><b>ID</b></td>
                    <td><b><?php echo WEB_msg_IP; ?></b></td>
                    <td><b><?php echo WEB_cell_description; ?></b></td>
                    <td><b><?php echo WEB_cell_created; ?></b></td>
                    <td class="up"><input type="submit" name="update_members" value="✏️ <?php echo WEB_btn_save; ?>"></td>
                    <td class="warn"><input type="submit" name="remove_members" value="🗑️ <?php echo WEB_btn_delete; ?>" 
                           onclick="return confirm('<?php echo WEB_msg_delete_selected; ?>')"></td>
                </tr>
            </thead>
            <tbody>
                <?php
                $members = get_records_sql($db_link, 
                    "SELECT id, ip, description, created_at FROM ipset_members WHERE ipset_id=? ORDER BY ip", 
                    [$id]);
                
                if (!empty($members)):
                    foreach ($members as $m):
                ?>
                <tr>
                    <td><input type="checkbox" name="f_member_id[]" value="<?php echo $m['id']; ?>"></td>
                    <td><?php echo $m['id']; ?></td>
                    <td><code><?php echo htmlspecialchars($m['ip']); ?></code></td>
                    <td>
                        <input type="text" name="f_member_desc_edit[<?php echo $m['id']; ?>]" 
                               value="<?php echo htmlspecialchars($m['description']); ?>" size="35" maxlength="255">
                    </td>
                    <td><small><?php echo $m['created_at']; ?></small></td>
                    <td colspan="2"></td>
                </tr>
                <?php 
                    endforeach;
                else:
                ?>
                <tr>
                    <td colspan="7" align="center" style="color:#666; padding:20px;">
                        ⚪ <?php echo WEB_ipset_empty; ?>
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </form>

    <?php if (!empty($members)): ?>
    <form method="post" action="?id=<?php echo $id; ?>" onsubmit="return confirm('⚠️<?php echo WEB_ipset_clear_qa; ?>')">
        <input type="hidden" name="confirm_clear" value="yes">
        <input type="submit" name="clear_all" value="<?php echo WEB_ipset_clear; ?>"
               style="background:#c33; color:white; border:none; padding:8px 16px; cursor:pointer;">
    </form>
    <?php endif; ?>

<script>
function toggleAll(source) {
    const checkboxes = document.querySelectorAll('input[name="f_member_id[]"]');
    checkboxes.forEach(cb => cb.checked = source.checked);
}
</script>

<?php
require_once($_SERVER['DOCUMENT_ROOT'] . "/inc/footer.php");
?>
