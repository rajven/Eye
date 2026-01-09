<?php
require_once($_SERVER['DOCUMENT_ROOT'] . "/inc/auth.php");
require_once($_SERVER['DOCUMENT_ROOT'] . "/inc/languages/" . HTML_LANG . ".php");

if (!defined("CONFIG")) die("Not defined");

if (isset($_POST["ExportAuth"])) {
    // Устанавливаем правильный Content-Type для CSV
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="auth_export.csv"');

    $out = fopen('php://output', 'w');
    fputcsv($out, ['login', 'ip', 'mac', 'description', 'dns name', 'last_found', 'connected'], ';');

    if (!empty($_POST["a_selected"]) && (int)$_POST["a_selected"]) {
        // Export selected only
        $auth_ids = $_POST["fid"] ?? [];
        $valid_ids = [];

        // Фильтруем и приводим к целым числам
        foreach ($auth_ids as $id) {
            if ($id = (int)$id) {
                $valid_ids[] = $id;
            }
        }

        if (!empty($valid_ids)) {
            // Создаем плейсхолдеры для IN
            $placeholders = str_repeat('?,', count($valid_ids) - 1) . '?';
            $sql = "
                SELECT 
                    ul.login, 
                    ua.ip, 
                    ua.mac, 
                    ua.description, 
                    ua.dns_name, 
                    ua.last_found,
                    ua.id
                FROM user_auth ua
                JOIN user_list ul ON ua.user_id = ul.id
                WHERE ua.id IN ($placeholders)
            ";
            $records = get_records_sql($db_link, $sql, $valid_ids);
            
            foreach ($records as $record) {
                fputcsv($out, [
                    $record['login'],
                    $record['ip'],
                    $record['mac'],
                    $record['description'],
                    $record['dns_name'],
                    $record['last_found'],
                    get_connection_string($db_link, $record['id'])
                ], ';');
            }
        }
    } else {
        // Export all
        $conditions = ["ua.deleted = 0"];
        $params = [];
        
        // Фильтр по IP (если передан как часть WHERE условия)
        // Безопасная сортировка - белый список разрешенных полей
        $allowed_sort_fields = [
            'user_auth.ip_int', 'ua.ip_int',
            'user_auth.ip', 'ua.ip',
            'user_auth.mac', 'ua.mac',
            'user_list.login', 'ul.login',
            'ua.last_found'
        ];
        
        $sort_field = 'ua.ip_int';
        if (!empty($_POST["ip-sort"]) && in_array($_POST["ip-sort"], $allowed_sort_fields, true)) {
            $sort_field = $_POST["ip-sort"];
        }

        $sql = "
            SELECT 
                ua.*, 
                ul.login, 
                ul.enabled as UEnabled, 
                ul.blocked as UBlocked,
                ua.id
            FROM user_auth ua
            JOIN user_list ul ON ua.user_id = ul.id
            WHERE " . implode(' AND ', $conditions) . "
            ORDER BY $sort_field
        ";
        
        $records = get_records_sql($db_link, $sql, $params);
        
        foreach ($records as $record) {
            fputcsv($out, [
                $record['login'],
                $record['ip'],
                $record['mac'],
                $record['description'],
                $record['dns_name'],
                $record['last_found'],
                get_connection_string($db_link, $record['id'])
            ], ';');
        }
    }
    
    fclose($out);
    exit;
}
?>
