<?php

require_once ($_SERVER['DOCUMENT_ROOT']."/inc/auth.utils.php");

login($db_link);

// Получаем параметры через безопасные функции
$action_get  = getParam('get', null, null);
$action_send = getParam('send', null, null);
$ip          = getParam('ip', null, null, FILTER_VALIDATE_IP, ['flags' => FILTER_FLAG_IPV4]);
$mac_raw     = getParam('mac', null, null);
$rec_id      = getParam('id', null, null, FILTER_VALIDATE_INT);
$f_subnet    = getParam('subnet', null, null);

// Преобразуем IP в BIGINT
$ip_aton = null;
if (!empty($ip)) {
        $ip_aton = sprintf('%u', ip2long($ip));
    }

// Новые параметры для универсальных методов
$table       = getParam('table', null, null);
$filter      = getParam('filter', null, null); // JSON-строка для кастомного фильтра

$update_data = null;
$content_type = isset($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : '';
if (stripos($content_type, 'application/json') !== false) {
    $raw_input = file_get_contents('php://input');
    if ($raw_input) {
        $json_data = json_decode($raw_input, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $update_data = $raw_input; // Передаём как строку для дальнейшей обработки
        }
    }
}

// Если не получили из тела запроса, пытаемся получить из параметров
if ($update_data === null) {
    $update_data = getParam('data', null, null);
}

// Параметры пагинации
$limit_param = getParam('limit', null, null, FILTER_VALIDATE_INT);
$offset_param = getParam('offset', null, null, FILTER_VALIDATE_INT);
$limit = ($limit_param !== null && $limit_param > 0) ? min((int)$limit_param, 1000) : 1000;
$offset = ($offset_param !== null && $offset_param >= 0) ? (int)$offset_param : 0;

// Обработка MAC-адреса
$mac = '';
if (!empty($mac_raw) && checkValidMac($mac_raw)) {
    $mac = mac_dotted(trim($mac_raw));
}

// Определяем действие
$action = '';
if (!empty($action_get))  { $action = 'get_' . $action_get; }
if (!empty($action_send)) { $action = 'send_' . $action_send; }

// Дополнительные параметры для send_dhcp
$dhcp_hostname = getParam('hostname', null, '');
$dhcp_action   = getParam('action', null, 1, FILTER_VALIDATE_INT);

// === Список разрешённых таблиц ===
$allowed_tables = [
    'building',
    'devices',
    'device_models',
    'device_ports',
    'connections',
    'ou',
    'queue_list',
    'group_list',
    'subnets',
    'config',
    'user_auth',
    'user_list',
    'vendors'
];

function do_exit() {
    exit;
}

// === Валидация таблицы ===
function validate_table($table_name, $allowed) {
    return in_array($table_name, $allowed) ? $table_name : null;
}

// === Безопасное получение данных из таблицы ===
function safe_get_records($db, $table, $filter = null, $limit = 1000, $offset = 0) {
    global $allowed_tables;
    
    if (!validate_table($table, $allowed_tables)) {
        return ['error' => 'Invalid table name'];
    }
    
    $sql = "SELECT * FROM " . $table;
    $params = [];
    
    if ($filter) {
        // Фильтр в формате: {"field":"value","field2":"value2"}
        $filter_arr = json_decode($filter, true);
        if (is_array($filter_arr) && !empty($filter_arr)) {
            $conditions = [];
            foreach ($filter_arr as $field => $value) {
                // Защита от SQL-инъекции: проверяем имя поля
                if (!preg_match('/^[a-z_][a-z0-9_]*$/i', $field)) {
                    continue;
                }
                $conditions[] = "$field = ?";
                $params[] = $value;
            }
            if (!empty($conditions)) {
                $sql .= " WHERE " . implode(" AND ", $conditions);
            }
        }
    }
    
    $sql .= " LIMIT " . (int)$limit;
    if ($offset > 0) {
        $sql .= " OFFSET " . (int)$offset;
    }
    
    $result = get_records_sql($db, $sql, $params);
    return $result;
}

// === Безопасное получение одной записи ===
function safe_get_record($db, $table, $id) {
    global $allowed_tables;
    
    if (!validate_table($table, $allowed_tables)) {
        return ['error' => 'Invalid table name'];
    }
    
    if (!is_numeric($id) || $id <= 0) {
        return ['error' => 'Invalid ID'];
    }
    
    $pk_field = 'id'; // Все таблицы используют 'id' как первичный ключ
    $result = get_record_sql($db, "SELECT * FROM $table WHERE $pk_field = ?", [(int)$id]);
    error_log("SELECT * FROM $table WHERE $pk_field = $id ::". $result);
    return $result;
}

if (!empty($action)) {


    // === УНИВЕРСАЛЬНЫЙ МЕТОД: get_table_record ===
    if ($action === 'get_table_record' && !empty($table)) {
        if ($rec_id>0) {
            $result = safe_get_record($db_link, $table, $rec_id);
            } elseif (!empty($filter)) {
            $result_arr = safe_get_records($db_link, $table, $filter, 1);
            if (!empty($result_arr)) { $result = $result_arr[0]; }
            } else {
            do_exit();
            }
        if (isset($result['error'])) {
            http_response_code(400);
            echo json_encode($result);
            do_exit();
        }
        if ($result) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Record not found']);
        }
        do_exit();
    }

    // === УНИВЕРСАЛЬНЫЙ МЕТОД: get_table_list ===
    if ($action === 'get_table_list' && !empty($table)) {
        $result = safe_get_records($db_link, $table, $filter, $limit, $offset);
        
        if (isset($result['error'])) {
            http_response_code(400);
            echo json_encode($result);
            do_exit();
        }
        
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($result ?: [], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        do_exit();
    }

    // === ОБНОВЛЕНИЕ USER_LIST ===
    if ($action === 'send_update_user' && $rec_id > 0 && !empty($update_data)) {
        $data = json_decode($update_data, true);
        
        if (!is_array($data)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid data format']);
            do_exit();
        }
        
        // Разрешённые поля для обновления
        $allowed_fields = [
            'login', 'description', 'enabled', 'blocked', 'ou_id', 
            'filter_group_id', 'queue_id', 'day_quota', 'month_quota', 'permanent'
        ];
        
        $update_fields = [];
        foreach ($data as $key => $value) {
            if (in_array($key, $allowed_fields)) {
                $update_fields[$key] = $value;
            }
        }
        
        if (empty($update_fields)) {
            http_response_code(400);
            echo json_encode(['error' => 'No valid fields to update']);
            do_exit();
        }
        
        // Проверяем существование пользователя
        $existing = get_record_sql($db_link, "SELECT id FROM user_list WHERE id = ?", [$rec_id]);
        if (!$existing) {
            http_response_code(404);
            echo json_encode(['error' => 'User not found']);
            do_exit();
        }
        
        // Выполняем обновление
        if (update_record($db_link, 'user_list', 'id = ?', $update_fields, [$rec_id])) {
            LOG_VERBOSE($db_link, "API: User $rec_id updated successfully");
            http_response_code(200);
            echo json_encode(['status' => 'updated', 'id' => $rec_id]);
        } else {
            LOG_ERROR($db_link, "API: Failed to update user $rec_id");
            http_response_code(500);
            echo json_encode(['error' => 'Update failed']);
        }
        do_exit();
    }

    // === ОБНОВЛЕНИЕ USER_AUTH ===
    if ($action === 'send_update_user_auth' && $rec_id > 0 && !empty($update_data)) {
        $data = json_decode($update_data, true);

        if (!is_array($data)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid data format']);
            do_exit();
        }

        // Разрешённые поля для обновления
        $allowed_fields = ['mac', 'ip', 'ip_int', 'wikiname', 'description', 'dns_name'];

        $update_fields = [];
        foreach ($data as $key => $value) {
            $db_key = strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $key)); // WikiName -> wiki_name
            if (in_array($db_key, $allowed_fields)) {
                $update_fields[$db_key] = $value;
            }
        }
        
        if (empty($update_fields)) {
            http_response_code(400);
            echo json_encode(['error' => 'No valid fields to update']);
            do_exit();
        }
        
        if (update_record($db_link, 'user_auth', 'id = ?', $update_fields, [$rec_id])) {
            LOG_VERBOSE($db_link, "API: User_auth $rec_id updated via API", $rec_id);
            http_response_code(200);
            echo json_encode(['status' => 'updated', 'id' => $rec_id]);
        } else {
            LOG_ERROR($db_link, "API: Failed to update user_auth $rec_id", $rec_id);
            http_response_code(500);
            echo json_encode(['error' => 'Update failed']);
        }
        do_exit();
    }

    // === get_user_auth ===
    if ($action === 'get_user_auth') {
        LOG_VERBOSE($db_link, "API: Get User Auth record with ip: $ip mac: $mac id: $rec_id");
        
        $result = null;
        $sql = "";
        $params = [];

        if ($rec_id > 0) {
            $sql = "SELECT * FROM user_auth WHERE id = ?";
            $params = [$rec_id];
        } elseif ($ip_aton !== null && !empty($mac)) {
            $sql = "SELECT * FROM user_auth WHERE ip_int = ? AND mac = ? AND deleted = 0";
            $params = [$ip_aton, $mac];
        } elseif ($ip_aton !== null) {
            $sql = "SELECT * FROM user_auth WHERE ip_int = ? AND deleted = 0";
            $params = [$ip_aton];
        } elseif (!empty($mac)) {
            $sql = "SELECT * FROM user_auth WHERE mac = ? AND deleted = 0";
            $params = [$mac];
        }

        if ($sql) {
            $result = get_record_sql($db_link, $sql, $params);
            if ($result) {
                LOG_VERBOSE($db_link, "API: Record found.");
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            } else {
                LOG_VERBOSE($db_link, "API: Not found.");
                http_response_code(404);
                echo json_encode(['error' => 'Not found']);
            }
        } else {
            LOG_VERBOSE($db_link, "API: not enough parameters");
            http_response_code(400);
            echo json_encode(['error' => 'Missing parameters']);
        }
        do_exit();
    }

    // === get_user ===
    if ($action === 'get_user') {
        LOG_VERBOSE($db_link, "API: Get User record with id: $rec_id");
        
        if ($rec_id > 0) {
            $user = get_record_sql($db_link, "SELECT * FROM user_list WHERE deleted = 0 AND id = ?", [$rec_id]);
            if ($user) {
                $auth_records = get_records_sql($db_link, 
                    "SELECT * FROM user_auth WHERE deleted = 0 AND user_id = ? ORDER BY id LIMIT 100", 
                    [$rec_id]
                );
                $user['auth'] = $auth_records ?: [];
                
                LOG_VERBOSE($db_link, "API: User record found.");
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode($user, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            } else {
                LOG_VERBOSE($db_link, "API: User not found.");
                http_response_code(404);
                echo json_encode(['error' => 'User not found']);
            }
        } else {
            LOG_VERBOSE($db_link, "API: not enough parameters");
            http_response_code(400);
            echo json_encode(['error' => 'Missing user ID']);
        }
        do_exit();
    }

    // === get_dhcp_all ===
    if ($action === 'get_dhcp_all') {
        LOG_VERBOSE($db_link, "API: Get all dhcp records");
        $result = get_records_sql($db_link, "
            SELECT 
                ua.id, ua.ip, ua.ip_int, ua.mac, ua.description, 
                ua.dns_name, ua.dhcp_option_set, ua.dhcp_acl, ua.ou_id,
                SUBSTRING_INDEX(s.subnet, '/', 1) AS subnet_base 
            FROM user_auth ua 
            JOIN subnets s ON ua.ip_int BETWEEN s.ip_int_start AND s.ip_int_stop
            WHERE ua.dhcp = 1 AND ua.deleted = 0 AND s.dhcp = 1 
            ORDER BY ua.ip_int
            LIMIT ? OFFSET ?
        ", [$limit, $offset]);
        
        LOG_VERBOSE($db_link, "API: " . count($result) . " records found.");
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($result ?: [], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        do_exit();
    }

    // === get_dhcp_subnet ===
    if ($action === 'get_dhcp_subnet' && !empty($f_subnet)) {
        // Валидация подсети как IPv4-адреса
        if (!filter_var($f_subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid subnet format']);
            do_exit();
        }
        
        LOG_VERBOSE($db_link, "API: Get dhcp records for subnet " . $f_subnet);
        $result = get_records_sql($db_link, "
            SELECT 
                ua.id, ua.ip, ua.ip_int, ua.mac, ua.description, 
                ua.dns_name, ua.dhcp_option_set, ua.dhcp_acl, ua.ou_id,
                SUBSTRING_INDEX(s.subnet, '/', 1) AS subnet_base 
            FROM user_auth ua 
            JOIN subnets s ON ua.ip_int BETWEEN s.ip_int_start AND s.ip_int_stop
            WHERE ua.dhcp = 1 AND ua.deleted = 0 AND s.dhcp = 1 
              AND SUBSTRING_INDEX(s.subnet, '/', 1) = ?
            ORDER BY ua.ip_int
            LIMIT ? OFFSET ?
        ", [$f_subnet, $limit, $offset]);

        LOG_VERBOSE($db_link, "API: " . count($result) . " records found.");
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($result ?: [], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        do_exit();
    }

    // === send_dhcp ===
    if ($action === 'send_dhcp') {
        if ($ip && $mac) {
            $faction = $dhcp_action !== null ? (int)$dhcp_action : 1;
            $action_str = ($faction === 0) ? 'del' : 'add';

            LOG_VERBOSE($db_link, "API: external dhcp request for $ip [$mac] $action_str");
            
            if (is_our_network($db_link, $ip)) {
                insert_record($db_link, "dhcp_queue", [
                    'action' => $action_str,
                    'mac' => $mac,
                    'ip' => $ip,
                    'dhcp_hostname' => $dhcp_hostname
                ]);
                http_response_code(201);
                echo json_encode(['status' => 'queued']);
            } else {
                LOG_ERROR($db_link, "$ip - wrong network!");
                http_response_code(400);
                echo json_encode(['error' => 'IP not in allowed network']);
            }
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'Missing IP or MAC']);
        }
        do_exit();
    }

} else {
    LOG_WARNING($db_link, "API: Unknown request");
    http_response_code(400);
    echo json_encode(['error' => 'Unknown action']);
}

do_exit();

?>
