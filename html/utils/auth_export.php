<?php
require_once($_SERVER['DOCUMENT_ROOT'] . "/inc/auth.php");
require_once($_SERVER['DOCUMENT_ROOT'] . "/inc/languages/" . HTML_LANG . ".php");

if (!defined("CONFIG")) die("Not defined");

$page_url = null;

if (getPOST("ExportAuth", $page_url) !== null) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="auth_export.csv"');

    $out = fopen('php://output', 'w');
    fputcsv($out, ['login', 'ip', 'mac', 'description', 'dns name', 'last_found', 'connected'], ';');

    $a_selected = getPOST("a_selected", $page_url, null);
    
    if ($a_selected !== null && (int)$a_selected) {
        // Export selected
        $auth_ids = getPOST("fid", $page_url, []);
        $valid_ids = [];

        foreach ($auth_ids as $id) {
            $id = (int)$id;
            if ($id > 0) {
                $valid_ids[] = $id;
            }
        }

        if (!empty($valid_ids)) {
            $chunk_size = 500;
            foreach (array_chunk($valid_ids, $chunk_size) as $chunk) {
                $placeholders = str_repeat('?,', count($chunk) - 1) . '?';
                $sql = "SELECT ul.login, ua.ip, ua.mac, ua.description, ua.dns_name, ua.last_found, ua.id
                    FROM user_auth ua
                    JOIN user_list ul ON ua.user_id = ul.id
                    WHERE ua.id IN ($placeholders)
                ";
                $records = get_records_sql($db_link, $sql, $chunk);
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
        }
    } else {
        // Export all
        $allowed_sort_fields = [
            'ua.ip_int',
            'ua.mac',
            'ul.login',
            'ua.last_found'
        ];

        $sort_field = 'ua.ip_int';
        $ip_sort = getPOST("ip-sort", $page_url, '');
        if ($ip_sort !== '' && in_array($ip_sort, $allowed_sort_fields, true)) {
            $sort_field = $ip_sort;
        }

        $sql = "
            SELECT  ul.login, ua.ip, ua.mac, ua.description, ua.dns_name, ua.last_found, ua.id
            FROM user_auth ua
            JOIN user_list ul ON ua.user_id = ul.id
            WHERE ua.deleted = 0
            ORDER BY $sort_field
        ";
        
        $records = get_records_sql($db_link, $sql, []);
        
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
