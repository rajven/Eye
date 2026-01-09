<?php
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/auth.php");

// Определяем page_url для сессии (можно использовать константу или путь)
$page_url = 'api';

// Получаем параметры через безопасные функции
$action_get  = getParam('get',    $page_url);
$action_send = getParam('send',   $page_url);
$ip          = getParam('ip',     $page_url, '', FILTER_VALIDATE_IP, ['flags' => FILTER_FLAG_IPV4]);
$mac_raw     = getParam('mac',    $page_url, '');
$rec_id      = getParam('id',     $page_url, null, FILTER_VALIDATE_INT);
$f_subnet    = getParam('subnet', $page_url, '');

// Обработка MAC-адреса
$mac = !empty($mac_raw) ? mac_dotted(trim($mac_raw)) : '';

// Определяем действие
$action = '';
if (!empty($action_get))  { $action = 'get_' . $action_get; }
if (!empty($action_send)) { $action = 'send_' . $action_send; }

// Дополнительные параметры для send_dhcp
$dhcp_hostname = getParam('hostname', $page_url, '');
$faction_raw   = getParam('action', $page_url, 1, FILTER_VALIDATE_INT);

if (!empty($action)) {

    // Преобразуем IP в BIGINT (если валиден)
    $ip_aton = null;
    if ($ip) {
        $ip_aton = sprintf('%u', ip2long($ip));
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
    }

    // === get_user ===
    if ($action === 'get_user') {
        LOG_VERBOSE($db_link, "API: Get User record with id: $rec_id");
        
        if ($rec_id > 0) {
            $user = get_record_sql($db_link, "SELECT * FROM user_list WHERE id = ?", [$rec_id]);
            if ($user) {
                $auth_records = get_records_sql($db_link, 
                    "SELECT * FROM user_auth WHERE deleted = 0 AND user_id = ?", 
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
        ");
        
        LOG_VERBOSE($db_link, "API: " . count($result) . " records found.");
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($result ?: [], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    // === get_dhcp_subnet ===
    if ($action === 'get_dhcp_subnet' && !empty($f_subnet)) {
        // Валидация подсети как IPv4-адреса
        if (!filter_var($f_subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid subnet format']);
            exit;
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
        ", [$f_subnet]);

        LOG_VERBOSE($db_link, "API: " . count($result) . " records found.");
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($result ?: [], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    // === send_dhcp ===
    if ($action === 'send_dhcp') {
        if ($ip && $mac) {
            $faction = $faction_raw !== null ? (int)$faction_raw : 1;
            $dhcp_action = ($faction === 0) ? 'del' : 'add';

            LOG_VERBOSE($db_link, "API: external dhcp request for $ip [$mac] $dhcp_action");
            
            if (is_our_network($db_link, $ip)) {
                insert_record($db_link, "dhcp_queue", [
                    'action' => $dhcp_action,
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
    }
} else {
    LOG_WARNING($db_link, "API: Unknown request");
    http_response_code(400);
    echo json_encode(['error' => 'Unknown action']);
}

ob_end_flush();

// Очистка сессии
if (session_status() === PHP_SESSION_ACTIVE) {
    $_SESSION = [];
    session_destroy();
}
?>
