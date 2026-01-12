<?php
if (! defined("CONFIG")) die("Not defined");
if (! defined("SQL")) { die("Not defined"); }

$numericFields = [
    'id',
    'option_id',
    'min_value',
    'max_value',
    'draft',
    'uniq',
    'device_id',
    'port_id',
    'auth_id',
    'rights',
    'device_type',
    'device_model_id',
    'vendor_id',
    'building_id',
    'ip_int',
    'control_port',
    'port_count',
    'snmp_version',
    'fdb_snmp_index',
    'discovery',
    'netflow_save',
    'user_acl',
    'dhcp',
    'nagios',
    'active',
    'queue_enabled',
    'connected_user_only',
    'user_id',
    'deleted',
    'discovery_locked',
    'instance_id',
    'snmpin',
    'interface_type',
    'poe_in',
    'poe_out',
    'snmp_index',
    'port',
    'target_port_id',
    'last_mac_count',
    'uplink',
    'skip',
    'vlan',
    'ip_int_start',
    'ip_int_stop',
    'dhcp_start',
    'dhcp_stop',
    'dhcp_lease_time',
    'gateway',
    'office',
    'hotspot',
    'vpn',
    'free',
    'static',
    'dhcp_update_hostname',
    'notify',
    'router_id',
    'proto',
    'src_ip',
    'dst_ip',
    'src_port',
    'dst_port',
    'bytes',
    'pkt',
    'filter_type',
    'subnet_id',
    'group_id',
    'filter_id',
    'order',
    'action',
    'default_users',
    'default_hotspot',
    'nagios_ping',
    'enabled',
    'filter_group_id',
    'queue_id',
    'dynamic',
    'life_duration',
    'parent_id',
    'Download',
    'Upload',
    'byte_in',
    'byte_out',
    'pkt_in',
    'pkt_out',
    'step',
    'bytes_in',
    'bytes_out',
    'forward_in',
    'forward_out',
    'level',
    'last_activity',
    'is_active',
    'day_quota',
    'month_quota',
    'permanent',
    'blocked',
    'changed',
    'dhcp_changed',
    'link_check'
];

$numericFieldsSet = array_flip($numericFields);

function db_escape($connection, $value) {
    // Обработка специальных значений
    if ($value === null) {
        return '';
    }
    if (is_bool($value)) {
        return $value ? 1 : 0;
    }
    if (is_int($value) || is_float($value)) {
        return $value;
    }
    // Для строковых значений
    $string = (string)$value;
    if ($connection instanceof PDO) {
        // Определяем тип базы данных
        $driver = $connection->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === false) {
            // Не удалось определить драйвер, используем универсальный метод
            return addslashes($string);
        }
        try {
            $quoted = $connection->quote($string);
            if ($quoted === false) {
                return addslashes($string);
            }
            // Убираем внешние кавычки для совместимости
            if (strlen($quoted) >= 2 && $quoted[0] === "'" && $quoted[strlen($quoted)-1] === "'") {
                return substr($quoted, 1, -1);
            }
            return $quoted;
        } catch (Exception $e) {
            return addslashes($string);
        }
    } elseif ($connection instanceof mysqli) {
        return mysqli_real_escape_string($connection, $string);
    } elseif (is_resource($connection) && get_resource_type($connection) === 'mysql link') {
        return mysql_real_escape_string($string, $connection);
    } elseif (is_resource($connection) && get_resource_type($connection) === 'pgsql link') {
        return pg_escape_string($connection, $string);
    } else {
        // Последнее средство
        return addslashes($string);
    }
}


function new_connection ($db_type, $db_host, $db_user, $db_password, $db_name, $db_port = null)
{
    // Создаем временный логгер для отладки до установки соединения
    $temp_debug_message = function($message) {
        error_log("DB_CONNECTION_DEBUG: " . $message);
    };

    $temp_debug_message("Starting new_connection function");
    $temp_debug_message("DB parameters - type: $db_type, host: $db_host, user: $db_user, db: $db_name");

    if (function_exists('filter_var') && defined('FILTER_SANITIZE_FULL_SPECIAL_CHARS')) {
        $db_host = filter_var($db_host, FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    } else {
        // Для PHP < 8.1
        $db_host = htmlspecialchars($db_host, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
    $db_name = preg_replace('/[^a-zA-Z0-9_-]/', '', $db_name);

    try {
        $temp_debug_message("Constructing DSN");
        
        // Определяем DSN в зависимости от типа базы данных
        $dsn = "";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        
        if ($db_type === 'mysql') {
            $dsn = "mysql:host=$db_host;dbname=$db_name;charset=utf8mb4";
            if (!empty($db_port)) { $dsn .= ";port=$db_port"; }
        } elseif ($db_type === 'pgsql' || $db_type === 'postgresql') {
            $dsn = "pgsql:host=$db_host;dbname=$db_name";
            if (!empty($db_port)) { $dsn .= ";port=$db_port"; }
            $options[PDO::PGSQL_ATTR_DISABLE_PREPARES] = false;
        } else {
            throw new Exception("Unsupported database type: $db_type. Supported types: mysql, pgsql");
        }

        $temp_debug_message("DSN: $dsn");
        $temp_debug_message("PDO options: " . json_encode($options));
        $temp_debug_message("Attempting to create PDO connection");

        $result = new PDO($dsn, $db_user, $db_password, $options);
        // Устанавливаем кодировку для PostgreSQL
        if ($db_type === 'pgsql' || $db_type === 'postgresql') {
                $result->exec("SET client_encoding TO 'UTF8'");
            }

        $temp_debug_message("PDO connection created successfully");
        $temp_debug_message("PDO connection info: " . ($result->getAttribute(PDO::ATTR_CONNECTION_STATUS) ?? 'N/A for PostgreSQL'));
        // Проверяем наличие атрибутов перед использованием
        if ($db_type === 'mysql') {
            $temp_debug_message("PDO client version: " . $result->getAttribute(PDO::ATTR_CLIENT_VERSION));
            $temp_debug_message("PDO server version: " . $result->getAttribute(PDO::ATTR_SERVER_VERSION));
            // Проверка кодировки для MySQL
            $stmt = $result->query("SHOW VARIABLES LIKE 'character_set_connection'");
            $charset = $stmt->fetch(PDO::FETCH_ASSOC);
            $temp_debug_message("Database character set: " . ($charset['Value'] ?? 'not set'));
        } elseif ($db_type === 'pgsql' || $db_type === 'postgresql') {
            // Проверка кодировки для PostgreSQL
            $stmt = $result->query("SHOW server_encoding");
            $charset = $stmt->fetch(PDO::FETCH_ASSOC);
            $temp_debug_message("PostgreSQL server encoding: " . ($charset['server_encoding'] ?? 'not set'));
            // Получаем версию PostgreSQL
            $stmt = $result->query("SELECT version()");
            $version = $stmt->fetch(PDO::FETCH_ASSOC);
            $temp_debug_message("PostgreSQL version: " . ($version['version'] ?? 'unknown'));
        }

        return $result;

    } catch (PDOException $e) {
        // Логируем ошибку через error_log, так как соединение не установлено
        error_log("DB_CONNECTION_ERROR: Failed to connect to $db_type");
        error_log("DB_CONNECTION_ERROR: DSN: $dsn");
        error_log("DB_CONNECTION_ERROR: User: $db_user");
        error_log("DB_CONNECTION_ERROR: Error code: " . $e->getCode());
        error_log("DB_CONNECTION_ERROR: Error message: " . $e->getMessage());
        error_log("DB_CONNECTION_ERROR: Trace: " . $e->getTraceAsString());

        // Также выводим в консоль для немедленной обратной связи
        echo "Error connect to $db_type " . PHP_EOL;
        echo "Error message: " . $e->getMessage() . PHP_EOL;
        echo "DSN: $dsn" . PHP_EOL;

        exit();
    } catch (Exception $e) {
        // Обработка других исключений (например, неподдерживаемый тип БД)
        error_log("DB_CONNECTION_ERROR: " . $e->getMessage());
        echo "Error: " . $e->getMessage() . PHP_EOL;
        exit();
    }
}

/**
 * Преобразует ассоциативный массив в человекочитаемый текстовый формат (подобие YAML/Perl hash)
 */
function hash_to_text($hash_ref, $indent = 0, &$seen = null) {
    if ($seen === null) {
        $seen = [];
    }

    if (!isset($hash_ref)) {
        return 'null';
    }

    if (is_array($hash_ref) && is_assoc($hash_ref)) {
        $spaces = str_repeat('  ', $indent);
        $lines = [];
        $keys = array_keys($hash_ref);
        sort($keys);

        foreach ($keys as $key) {
            $value = $hash_ref[$key];
            $formatted_key = preg_match('/^[a-zA-Z_]\w*$/', $key) ? $key : "'" . addslashes($key) . "'";
            $formatted_value = '';

            if (is_array($value)) {
                if (is_assoc($value)) {
                    $formatted_value = ":\n" . hash_to_text($value, $indent + 1, $seen);
                } else {
                    $formatted_value = array_to_text($value, $indent + 1, $seen);
                }
            } elseif (is_object($value)) {
                // Защита от циклических ссылок для объектов
                $obj_id = spl_object_hash($value);
                if (isset($seen[$obj_id])) {
                    $formatted_value = '[circular reference]';
                } else {
                    $seen[$obj_id] = true;
                    $formatted_value = '[' . get_class($value) . ']';
                }
            } elseif ($value === null) {
                $formatted_value = 'null';
            } else {
                $formatted_value = "'" . addslashes((string)$value) . "'";
            }

            if ($formatted_value !== '') {
                $lines[] = "$spaces  $formatted_key => $formatted_value";
            }
        }

        if (empty($lines)) {
            return "$spaces  # empty";
        }
        return implode(",\n", $lines);
    } else {
        // Не ассоциативный массив или скаляр — обрабатываем как строку
        return "'" . (isset($hash_ref) ? addslashes((string)$hash_ref) : '') . "'";
    }
}

/**
 * Преобразует индексированный массив в текстовый формат
 */
function array_to_text($array_ref, $indent = 0, &$seen = null) {
    if ($seen === null) {
        $seen = [];
    }

    if (!is_array($array_ref) || empty($array_ref)) {
        return '[]';
    }

    $spaces = str_repeat('  ', $indent);
    $lines = [];

    foreach ($array_ref as $item) {
        $formatted_item = '';

        if (is_array($item)) {
            if (is_assoc($item)) {
                $formatted_item = ":\n" . hash_to_text($item, $indent + 1, $seen);
            } else {
                $formatted_item = array_to_text($item, $indent + 1, $seen);
            }
        } elseif (is_object($item)) {
            $obj_id = spl_object_hash($item);
            if (isset($seen[$obj_id])) {
                $formatted_item = '[circular reference]';
            } else {
                $seen[$obj_id] = true;
                $formatted_item = '[' . get_class($item) . ']';
            }
        } elseif ($item === null) {
            $formatted_item = 'null';
        } else {
            $formatted_item = "'" . addslashes((string)$item) . "'";
        }

        if ($formatted_item !== '') {
            $lines[] = "$spaces  $formatted_item";
        }
    }

    if (empty($lines)) {
        return "[]";
    }
    return "[\n" . implode(",\n", $lines) . "\n$spaces]";
}

/**
 * Проверяет, является ли массив ассоциативным
 */
function is_assoc($array) {
    if (!is_array($array) || empty($array)) {
        return false;
    }
    return array_keys($array) !== range(0, count($array) - 1);
}

/**
 * Нормализует значение поля: преобразует NULL в 0 для числовых полей или в пустую строку для строковых
 * 
 * @param string $key Имя поля
 * @param mixed $value Значение поля
 * @return mixed Нормализованное значение
 */
function normalize_field_value($key, $value) {
    if ($value === null or $value === 'NULL') {
	if (isset($numericFieldsSet[$key])) {
            return 0;
        } else {
            return '';
        }
    }
    return $value;
}

/**
 * Нормализует всю запись (ассоциативный массив)
 * 
 * @param array $record Запись из БД
 * @return array Нормализованная запись
 */
function normalize_record($record) {
    if (!is_array($record) || empty($record)) {
        return $record;
    }
    
    $normalized = [];
    foreach ($record as $key => $value) {
        $normalized[$key] = normalize_field_value($key, $value);
    }
    
    return $normalized;
}

/**
 * Нормализует массив записей
 * 
 * @param array $records Массив записей из БД
 * @return array Нормализованные записи
 */
function normalize_records($records) {
    if (!is_array($records) || empty($records)) {
        return $records;
    }
    
    $normalized = [];
    foreach ($records as $index => $record) {
        $normalized[$index] = normalize_record($record);
    }
    
    return $normalized;
}

/**
 * Выполняет SQL-запрос с поддержкой параметров.
 * 
 * @param PDO $db
 * @param string $query
 * @param array $params (опционально)
 * @return mixed
 */
function run_sql($db, $query, $params = [])
{
    // Определяем тип запроса и таблицу для проверки прав
    $table_name = null;
    $operation = null;

    if (preg_match('/^\s*UPDATE\s+([a-zA-Z_][a-zA-Z0-9_]*)/i', $query, $matches)) {
        $table_name = $matches[1];
        $operation = 'update';
    } elseif (preg_match('/^\s*DELETE\s+FROM\s+([a-zA-Z_][a-zA-Z0-9_]*)/i', $query, $matches)) {
        $table_name = $matches[1];
        $operation = 'del';
    } elseif (preg_match('/^\s*INSERT\s+INTO\s+([a-zA-Z_][a-zA-Z0-9_]*)/i', $query, $matches)) {
        $table_name = $matches[1];
        $operation = 'add';
    }

    // Проверка прав доступа
    if ($table_name && $operation && !allow_update($table_name, $operation)) {
        LOG_DEBUG($db, "Access denied: $query");
        return false;
    }

    try {
        $stmt = $db->prepare($query);
        $success = $stmt->execute($params);

        if (!$success) {
            LOG_ERROR($db, "Query execution failed: $query | params: " . json_encode($params));
            return false;
        }

        // Возвращаем результат в зависимости от типа запроса
        if (preg_match('/^\s*SELECT/i', $query)) {
            return $stmt; // PDOStatement для последующего fetch
        } elseif (preg_match('/^\s*INSERT/i', $query)) {
            return $db->lastInsertId();
        } elseif (preg_match('/^\s*(UPDATE|DELETE)/i', $query)) {
            return $stmt->rowCount();
        }

        return $stmt;

    } catch (PDOException $e) {
        LOG_ERROR($db, "SQL error: $query | params: " . json_encode($params) . " | " . $e->getMessage());
        return false;
    }
}

function get_count_records($db, $table, $filter, $filter_params = [])
{
    // Валидация имени таблицы (защита от SQL-инъекций)
    if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $table)) {
        return 0;
    }

    $sql = "SELECT COUNT(*) AS cnt FROM $table";
    if (!empty($filter)) {
        $sql .= " WHERE $filter";
    }

    $result = get_record_sql($db, $sql, $filter_params);
    return !empty($result['cnt']) ? (int)$result['cnt'] : 0;
}

/**
 * Получить одну запись из таблицы по фильтру
 */
function get_record($db, $table, $filter, $filter_params = [])
{
    if (empty($table) || empty($filter)) {
        return null;
    }
    // Валидация имени таблицы
    if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $table)) {
        LOG_ERROR($db, "Invalid table name: $table");
        return null;
    }
    if (preg_match('/=$/', trim($filter))) {
        LOG_ERROR($db, "Search record ($table) with illegal filter '$filter'! Skip command.");
        return null;
    }
    $sql = "SELECT * FROM $table WHERE $filter";
    return get_record_sql($db, $sql, $filter_params);
}

/**
 * Получить несколько записей из таблицы по фильтру
 */
function get_records($db, $table, $filter = '', $filter_params = [])
{
    if (empty($table)) {
        return [];
    }

    // Валидация имени таблицы
    if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $table)) {
        LOG_ERROR($db, "Invalid table name: $table");
        return [];
    }

    if (!empty($filter)) {
        if (preg_match('/=$/', trim($filter))) {
            LOG_ERROR($db, "Search records ($table) with illegal filter '$filter'! Skip command.");
            return [];
        }
        $filter = "WHERE $filter";
    }

    $sql = "SELECT * FROM $table $filter";
    return get_records_sql($db, $sql, $filter_params);
}

/**
 * Получить одну запись по произвольному SQL-запросу
 *
 * @param PDO $db
 * @param string $sql
 * @param array|null $params
 * @return array|null
 */
function get_record_sql($db, $sql, $params = []) {
    if (empty($sql)) {
        return null;
    }

    // Добавляем LIMIT 1, если его нет (только если нет параметризованных запросов!)
    // Важно: не трогаем запрос, если он уже содержит LIMIT с placeholder'ом
    if (!preg_match('/\bLIMIT\s+(\?|\d+)/i', $sql) && !preg_match('/\bLIMIT\s+\d+\s*(?:OFFSET\s+\d+)?\s*$/i', $sql)) {
        $sql .= " LIMIT 1";
    }

    $result = get_records_sql($db, $sql, $params);

    return !empty($result) ? $result[0] : null;
}

/**
 * Получить несколько записей по произвольному SQL-запросу
 *
 * @param PDO $db
 * @param string $sql
 * @param array|null $params
 * @return array
 */
function get_records_sql($db, $sql, $params = [])
{
    if (empty($sql)) {
        return [];
    }

    // Приводим $params к массиву
    $params = $params ?: [];

    // Логируем в DEBUG
    // LOG_DEBUG($db, "SQL: $sql | params: " . json_encode($params, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

    try {
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($records)) {
            return normalize_records($records);
        }

        return [];

    } catch (PDOException $e) {
        // Логируем ошибку с параметрами
        LOG_ERROR($db, "SQL error: $sql | params: " . json_encode($params, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . " | " . $e->getMessage());
        return [];
    }
}

/**
 * Получить одно значение поля по SQL-запросу
 */
function get_single_field($db, $sql, $params = []) {
    $record = get_record_sql($db, $sql, $params);

    if (!empty($record) && is_array($record)) {
        return reset($record) ?: 0;
    }

    return 0;
}

/**
 * Получить ID записи из таблицы по фильтру
 */
function get_id_record($db, $table, $filter, $params=[]) {
    if (empty($filter)) {
        return 0;
    }
    
    $record = get_record($db, $table, $filter, $params);
    return !empty($record['id']) ? $record['id'] : 0;
}

function set_changed($db, $id)
{
    $auth['changed'] = 1;
    update_record($db, "user_auth", "id=" . $id, $auth);
}

function allow_update($table, $action = 'update', $field = '')
{
    // 1. Таблицы с полным доступом (регистронезависимо, но без regex)
    static $full_access_tables = [
        'variables' => true,
        'dns_cache' => true,
        'worklog' => true,
        'sessions' => true
    ];
    if (isset($full_access_tables[strtolower($table)])) {
        return 1;
    }

    // 2. Получение данных сессии (единая точка)
    $login = $_SESSION['login'] ?? null;
    $user_id = $_SESSION['user_id'] ?? null;
    $acl = $_SESSION['acl'] ?? null;

    // Проверка аутентификации
    if (!$login || !$user_id || !$acl) {
        return 0;
    }

    // Приведение ACL к целому числу
    $user_level = (int)$acl;

    // 3. Права по уровням
    if ($user_level === 1) {        // Администратор
        return 1;
    }
    if ($user_level === 3) {        // ViewOnly
        return 0;
    }

    // 4. Таблицы с полным доступом для оператора
    static $operator_tables = [
        'dns_queue' => true,
        'user_auth_alias' => true
    ];
    if (isset($operator_tables[strtolower($table)])) {
        return 1;
    }

    // 5. Проверка полей для оператора (только при update)
    if ($action === 'update') {
        static $operator_acl = [
            'user_auth' => [
                'description' => true,
                'dns_name' => true,
                'dns_ptr_only' => true,
                'firmware' => true,
                'link_check' => true,
                'nagios' => true,
                'nagios_handler' => true,
                'Wikiname' => true
            ],
            'user_list' => [
                'description' => true,
                'login' => true
            ]
        ];

        // Проверка существования таблицы в ACL
        if (!isset($operator_acl[$table])) {
            return 0;
        }

        // Если поле не указано — разрешаем (полный доступ к таблице)
        if ($field === '') {
            return 1;
        }

        // Проверка прав на конкретное поле
        return $operator_acl[$table][$field] ?? 0;
    }

    return 0;
}

function update_record($db, $table, $filter, $newvalue, $filter_params = [])
{
    if (!isset($table) || trim($table) === '') {
        return;
    }
    if (!isset($filter) || trim($filter) === '') {
        return;
    }
    if (preg_match('/=$/', trim($filter))) {
        LOG_WARNING($db, "Change record ($table) with illegal filter $filter! Skip command.");
        return;
    }
    if (!isset($newvalue) || !is_array($newvalue)) {
        return;
    }

    if (!allow_update($table, 'update')) {
        LOG_INFO($db, "Access denied: $table [ $filter ]");
        return 1;
    }

    $old_record = get_record_sql($db,"SELECT * FROM $table WHERE $filter",$filter_params);
    if (empty($old_record)) { return; }
    $rec_id = $old_record['id'];

    $changed_log = '';
    $set_parts = [];
    $params = [];
    $network_changed = 0;
    $dhcp_changed = 0;
    $dns_changed = 0;

    $acl_fields = [
        'ip' => '1',
        'ip_int' => '1',
        'enabled' => '1',
        'dhcp' => '1',
        'filter_group_id' => '1',
        'deleted' => '1',
        'dhcp_acl' => '1',
        'queue_id' => '1',
        'mac' => '1',
        'blocked' => '1',
    ];

    $dhcp_fields = [
        'ip' => '1',
        'dhcp' => '1',
        'deleted' => '1',
        'dhcp_option_set' =>'1',
        'dhcp_acl' => '1',
        'mac' => '1',
    ];

    $dns_fields = [
        'ip' => '1',
        'dns_name' => '1',
        'dns_ptr_only' => '1',
        'alias' => '1',
    ];

    foreach ($newvalue as $key => $value) {

        if (!allow_update($table, 'update', $key)) {
            continue;
        }

        if (!isset($value)) {
            $value = '';
        }
        $value = trim($value);
        if (isset($old_record[$key]) && strcmp($old_record[$key], $value) == 0) {
            continue;
        }
        if ($table === "user_auth") {
            if (!empty($acl_fields["$key"])) {
                $network_changed = 1;
            }
            if (!empty($dhcp_fields["$key"])) {
                $dhcp_changed = 1;
            }
            if (!empty($dns_fields["$key"])) {
                $dns_changed = 1;
            }
        }
        if ($table === "user_auth_alias") {
            if (!empty($dns_fields["$key"])) {
                $dns_changed = 1;
            }
        }
        if (!preg_match('/password/i', $key)) {
            $changed_log = $changed_log . " $key => $value (old: " . ($old_record[$key] ?? '') . "),";
        }
        $set_parts[] = "$key = ?";
        $params[] = $value;
    }

    if ($table === "user_auth" and $dns_changed) {
        if (!empty($old_record['dns_name']) and !empty($old_record['ip']) and !$old_record['dns_ptr_only'] and !preg_match('/\.$/', $old_record['dns_name'])) {
            $del_dns['name_type'] = 'A';
            $del_dns['name'] = $old_record['dns_name'];
            $del_dns['value'] = $old_record['ip'];
            $del_dns['operation_type'] = 'del';
            if (!empty($rec_id)) {
                $del_dns['auth_id'] = $rec_id;
            }
            insert_record($db, 'dns_queue', $del_dns);
        }
        if (!empty($old_record['dns_name']) and !empty($old_record['ip']) and $old_record['dns_ptr_only'] and !preg_match('/\.$/', $old_record['dns_name'])) {
            $del_dns['name_type'] = 'PTR';
            $del_dns['name'] = $old_record['dns_name'];
            $del_dns['value'] = $old_record['ip'];
            $del_dns['operation_type'] = 'del';
            if (!empty($rec_id)) {
                $del_dns['auth_id'] = $rec_id;
            }
            insert_record($db, 'dns_queue', $del_dns);
        }

        if (!empty($newvalue['dns_name']) and !empty($newvalue['ip']) and !$newvalue['dns_ptr_only'] and !preg_match('/\.$/', $newvalue['dns_name'])) {
            $new_dns['name_type'] = 'A';
            $new_dns['name'] = $newvalue['dns_name'];
            $new_dns['value'] = $newvalue['ip'];
            $new_dns['operation_type'] = 'add';
            if (!empty($rec_id)) {
                $new_dns['auth_id'] = $rec_id;
            }
            insert_record($db, 'dns_queue', $new_dns);
        }
        if (!empty($newvalue['dns_name']) and !empty($newvalue['ip']) and $newvalue['dns_ptr_only'] and !preg_match('/\.$/', $newvalue['dns_name'])) {
            $new_dns['name_type'] = 'PTR';
            $new_dns['name'] = $newvalue['dns_name'];
            $new_dns['value'] = $newvalue['ip'];
            $new_dns['operation_type'] = 'add';
            if (!empty($rec_id)) {
                $new_dns['auth_id'] = $rec_id;
            }
            insert_record($db, 'dns_queue', $new_dns);
        }
    }

    if ($table === "user_auth_alias" and $dns_changed) {
        $auth_id = NULL;
        if ($old_record['auth_id']) {
            $auth_id = $old_record['auth_id'];
        }
        if (!empty($old_record['alias']) and !preg_match('/\.$/', $old_record['alias'])) {
            $del_dns['name_type'] = 'CNAME';
            $del_dns['name'] = $old_record['alias'];
            $del_dns['operation_type'] = 'del';
            if (!empty($auth_id)) {
                $del_dns['auth_id'] = $auth_id;
                $del_dns['value'] = get_dns_name($db, $auth_id);
            }
            insert_record($db, 'dns_queue', $del_dns);
        }
        if (!empty($newvalue['alias'])  and !preg_match('/\.$/', $newvalue['alias'])) {
            $new_dns['name_type'] = 'CNAME';
            $new_dns['name'] = $newvalue['alias'];
            $new_dns['operation_type'] = 'add';
            if (!empty($auth_id)) {
                $new_dns['auth_id'] = $auth_id;
                $new_dns['value'] = get_dns_name($db, $auth_id);
            }
            insert_record($db, 'dns_queue', $new_dns);
        }
    }

    if (empty($set_parts)) {
        return 1;
    }

    if ($network_changed) {
        $set_parts[] = "changed = 1";
    }

    if ($dhcp_changed) {
        $set_parts[] = "dhcp_changed = 1";
    }

    $changed_log = substr_replace($changed_log, "", -1);
    $run_sql = implode(", ", $set_parts);

    if ($table === 'user_auth') {
        $changed_time = GetNowTimeString();
        $run_sql .= ", changed_time = ?";
        $params[] = $changed_time;
    }

    $new_sql = "UPDATE $table SET $run_sql WHERE $filter";
    $all_params = array_merge($params, $filter_params);
    LOG_DEBUG($db, "Run sql: $new_sql | params: " . json_encode($all_params, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    try {
        $stmt = $db->prepare($new_sql);
        $sql_result = $stmt->execute($all_params);
        if (!$sql_result) {
            LOG_ERROR($db, "UPDATE Request: $new_sql | params: " . json_encode($all_params, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            return;
            }
        if ($table !== "sessions") {
            LOG_VERBOSE($db, "Change table $table WHERE $filter set $changed_log | params: " . json_encode($filter_params, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            }
        return $sql_result;
    } catch (PDOException $e) {
        LOG_ERROR($db, "SQL: $new_sql | params: " . json_encode($all_params, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . " | error: " . $e->getMessage());
        return;
    }
}

function delete_records($db, $table, $filter, $filter_params = [])
{
    // Сначала получаем ID записей, подходящих под фильтр
    $records = get_records_sql($db, "SELECT id FROM $table WHERE $filter", $filter_params);
    if (empty($records)) {
        return true; // ничего не найдено — успех
    }
    // Удаляем каждую запись через уже существующую функцию delete_record
    foreach ($records as $record) {
        // Формируем фильтр по id и вызываем delete_record
        delete_record($db, $table, "id = ?", [$record['id']]);
    }
    return true;
}

function update_records($db, $table, $filter, $newvalue, $filter_params = [])
{
    // Получаем ID всех записей, подходящих под фильтр
    $records = get_records_sql($db, "SELECT id FROM $table WHERE $filter", $filter_params);
    if (empty($records)) {
        return true; // ничего не найдено — считаем успехом
    }
    // Обновляем каждую запись по отдельности через уже существующую логику
    foreach ($records as $record) {
        update_record($db, $table, "id = ?", $newvalue, [$record['id']]);
    }
    return true;
}

function delete_record($db, $table, $filter, $filter_params = [])
{
    if (!allow_update($table, 'del')) {
        return;
    }
    if (!isset($table)) {
        return;
    }
    if (!isset($filter)) {
        LOG_WARNING($db, "Delete FROM table $table with empty filter! Skip command.");
        return;
    }
    if (preg_match('/=$/', $filter)) {
        LOG_WARNING($db, "Change record ($table) with illegal filter $filter! Skip command.");
        return;
    }

    $old_record = get_record_sql($db,"SELECT * FROM $table WHERE $filter",$filter_params);
    if (empty($old_record)) { return; }
    $rec_id = $old_record['id'];

    $changed_log = 'record: ';
    if (!empty($old_record)) {
        asort($old_record, SORT_STRING);
        $old_record = array_reverse($old_record, 1);
        foreach ($old_record as $key => $value) {
            if (empty($value) || preg_match('/\b(action|status|time|found)\b/i', $key)) {
                continue;
                }
            $changed_log .= " $key => $value,";
        }
    }

    $delete_it = 1;

    //never delete user ip record
    if ($table === 'user_auth') {
        $delete_it = 0;
        update_record($db, $table, $filter, [ 'deleted'=>1, 'changed'=>1 ], $filter_params);
        //dns - A-record
        if (!empty($old_record['dns_name']) and !empty($old_record['ip']) and !$old_record['dns_ptr_only']  and !preg_match('/\.$/', $old_record['dns_name'])) {
            $del_dns['name_type'] = 'A';
            $del_dns['name'] = $old_record['dns_name'];
            $del_dns['value'] = $old_record['ip'];
            $del_dns['operation_type'] = 'del';
            if (!empty($rec_id)) {
                $del_dns['auth_id'] = $rec_id;
                }
            insert_record($db, 'dns_queue', $del_dns);
            }
        //ptr
        if (!empty($old_record['dns_name']) and !empty($old_record['ip']) and $old_record['dns_ptr_only']  and !preg_match('/\.$/', $old_record['dns_name'])) {
            $del_dns['name_type'] = 'PTR';
            $del_dns['name'] = $old_record['dns_name'];
            $del_dns['value'] = $old_record['ip'];
            $del_dns['operation_type'] = 'del';
            if (!empty($rec_id)) {
                $del_dns['auth_id'] = $rec_id;
                }
            insert_record($db, 'dns_queue', $del_dns);
            }
        LOG_VERBOSE($db, "Deleted FROM table $table WHERE $filter $changed_log");
        return $changed_log;
        }

    //never delete permanent user
    if ($table === 'user_list' and $old_record['permanent']) { return; }

    //remove aliases
    if ($table === 'user_auth_alias') {
        //dns
        if (!empty($old_record['alias'])  and !preg_match('/\.$/', $old_record['alias'])) {
            $del_dns['name_type'] = 'CNAME';
            $del_dns['name'] = $old_record['alias'];
            $del_dns['value'] = '';
            $del_dns['operation_type'] = 'del';
            if (!empty($old_record['auth_id'])) {
                $del_dns['auth_id'] = $old_record['auth_id'];
                $del_dns['value'] = get_dns_name($db, $old_record['auth_id']);
            }
            insert_record($db, 'dns_queue', $del_dns);
        }
    }

    if ($delete_it) {
        $new_sql = "DELETE FROM $table WHERE $filter";
        LOG_DEBUG($db, "Run sql: $new_sql | params: " . json_encode($filter_params, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        try {
            $stmt = $db->prepare($new_sql);
            $sql_result = $stmt->execute($filter_params);
            if ($sql_result === false) {
                LOG_ERROR($db, "DELETE Request: $new_sql | params: " . json_encode($filter_params));
                return;
            }
        } catch (PDOException $e) {
            LOG_ERROR($db, "SQL: $new_sql | params: " . json_encode($filter_params) . " : " . $e->getMessage());
            return;
        }
        } else { return; }

    if ($table !== "sessions") {
        LOG_VERBOSE($db, "Deleted FROM table $table WHERE $filter $changed_log");
    }

    return $changed_log;
}

function insert_record($db, $table, $newvalue)
{
    if (!allow_update($table, 'add')) {
        // LOG_WARNING($db, "User does not have write permission");
        return;
    }
    if (!isset($table) || empty($table)) {
        // LOG_WARNING($db, "Create record for unknown table! Skip command.");
        return;
    }
    if (empty($newvalue) || !is_array($newvalue)) {
        // LOG_WARNING($db, "Create record ($table) with empty data! Skip command.");
        return;
    }

    $changed_log = '';
    $field_list = [];
    $value_list = [];
    $params = [];

    if ($table === 'user_auth') {
        $newvalue['changed']=1;
        if (!empty($newvalue['ou_id']) and !is_system_ou($db,$newvalue['ou_id'])) { $newvalue['dhcp_changed']=1; }
        }

    foreach ($newvalue as $key => $value) {
        // Логирование (без паролей)
        if (!preg_match('/password/i', $key)) {
            $changed_log .= " $key => " . ($value ?? 'NULL') . ",";
        }
        $field_list[] = $key;
        $value_list[] = '?';
        $params[] = $value;
    }

    if (empty($field_list)) {
        return;
    }

    // Формируем SQL
    $field_list_str = implode(',', $field_list);
    $value_list_str = implode(',', $value_list);
    $new_sql = "INSERT INTO $table ($field_list_str) VALUES ($value_list_str)";

    LOG_DEBUG($db, "Run sql: $new_sql | params: " . json_encode($params, JSON_UNESCAPED_UNICODE));

    try {
        $stmt = $db->prepare($new_sql);
        $sql_result = $stmt->execute($params);

        if (!$sql_result) {
            LOG_ERROR($db, "INSERT Request");
            return;
        }
        $last_id = $db->lastInsertId();
        if ($table !== "sessions") {
            LOG_VERBOSE($db, "Create record in table $table: $changed_log with id: $last_id");
        }

        if ($table === 'user_auth_alias') {
            //dns
            if (!empty($newvalue['alias'])  and !preg_match('/\.$/', $newvalue['alias'])) {
                $add_dns['name_type'] = 'CNAME';
                $add_dns['name'] = $newvalue['alias'];
                $add_dns['value'] = get_dns_name($db, $newvalue['auth_id']);
                $add_dns['operation_type'] = 'add';
                $add_dns['auth_id'] = $newvalue['auth_id'];
                insert_record($db, 'dns_queue', $add_dns);
            }
        }

        if ($table === 'user_auth') {
            //dns - A-record
            if (!empty($newvalue['dns_name']) and !empty($newvalue['ip']) and !$newvalue['dns_ptr_only']  and !preg_match('/\.$/', $newvalue['dns_name'])) {
                $add_dns['name_type'] = 'A';
                $add_dns['name'] = $newvalue['dns_name'];
                $add_dns['value'] = $newvalue['ip'];
                $add_dns['operation_type'] = 'add';
                $add_dns['auth_id'] = $last_id;
                insert_record($db, 'dns_queue', $add_dns);
            }
            //dns - ptr
            if (!empty($newvalue['dns_name']) and !empty($newvalue['ip']) and $newvalue['dns_ptr_only'] and !preg_match('/\.$/', $newvalue['dns_name'])) {
                $add_dns['name_type'] = 'PTR';
                $add_dns['name'] = $newvalue['dns_name'];
                $add_dns['value'] = $newvalue['ip'];
                $add_dns['operation_type'] = 'add';
                $add_dns['auth_id'] = $last_id;
                insert_record($db, 'dns_queue', $add_dns);
            }
        }

        return $last_id;

    } catch (PDOException $e) {
        LOG_ERROR($db, "SQL error: $new_sql | params: " . json_encode($params) . " | " . $e->getMessage());
        return false;
    }
}

function dump_record($db, $table, $filter, $params = [])
{
    $result = '';
    $old = get_record($db, $table, $filter, $params);
    if (empty($old)) {
        return $result;
    }
    $result = 'record: ' . hash_to_text($old);
    return $result;
}

function get_diff_rec($db, $table, $filter, $newvalue, $only_changed = true, $filter_params = [])
{
    if (empty($table) || empty($filter) || !is_array($newvalue)) {
        return '';
    }
    // Валидация имени таблицы
    if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $table)) {
        return '';
    }
    $old_sql = "SELECT * FROM $table WHERE $filter";
    $old_record = get_record_sql($db, $old_sql, $filter_params);
    if (empty($old_record)) { return ''; }
    
    $changed = [];
    $unchanged = [];
    foreach ($newvalue as $key => $new_val) {
            // Пропускаем поля, отсутствующие в БД
            if (!array_key_exists($key, $old_record)) {
                continue;
            }
            $old_record_val = $old_record[$key];
            // Приведение к строке с учётом NULL
            $old_record_str = ($old_record_val === null) ? '' : (string)$old_record_val;
            $new_str = ($new_val === null) ? '' : (string)$new_val;
            if ($old_record_str !== $new_str) {
                $changed[$key] = "$new_str [old: $old_record_str]";
            } elseif (!$only_changed) {
                $unchanged[$key] = $old_record_val;
            }
        }
    if ($only_changed) {
            return !empty($changed) ? hash_to_text($changed) : '';
        }
    if (!empty($changed)) {
            $output = hash_to_text($changed);
        } else {
            $output = "";
        }
    if (!empty($unchanged)) {
            $output .= "\r\nHas not changed:\r\n" . hash_to_text($unchanged);
        }
    return $output;
}

function delete_user_auth($db, $id) {
    $msg = '';
    $record = get_record_sql($db, 'SELECT * FROM user_auth WHERE id=' . $id);
    $txt_record = hash_to_text($record);
    // remove aliases
    $t_user_auth_alias = get_records_sql($db, 'SELECT * FROM user_auth_alias WHERE auth_id=' . $id);
    if (!empty($t_user_auth_alias)) {
        foreach ($t_user_auth_alias as $row) {
            $alias_txt = record_to_txt($db, 'user_auth_alias', 'id=' . $row['id']);
            if (delete_record($db, 'user_auth_alias', 'id=' . $row['id'])) {
                $msg = "Deleting an alias: " . $alias_txt . "::Success!\n" . $msg;
            } else {
                $msg = "Deleting an alias: " . $alias_txt . "::Fail!\n" . $msg;
            }
        }
    }
    // remove connections
    run_sql($db, 'DELETE FROM connections WHERE auth_id=' . $id);
    // remove user auth record
    $changes = delete_record($db, "user_auth", "id=" . $id);
    if ($changes) {
        $msg = "Deleting ip-record: " . $txt_record . "::Success!\n" . $msg;
    } else {
        $msg = "Deleting ip-record: " . $txt_record . "::Fail!\n" . $msg;
    }
    LOG_WARNING($db, $msg);
    $send_alert_delete = isNotifyDelete(get_notify_subnet($db, $record['ip']));
    if ($send_alert_delete) { email(L_WARNING,$msg); }
    return $changes;
}

function delete_user($db,$id)
{
//remove user record
$changes = delete_record($db, "user_list", "id=" . $id);
//if fail - exit
if (!isset($changes) or empty($changes)) { return; }
//remove auth records
$t_user_auth = get_records($db,'user_auth',"user_id=$id");
if (!empty($t_user_auth)) {
    foreach ( $t_user_auth as $row ) { delete_user_auth($db,$row['id']); }
    }
//remove device
$device = get_record($db, "devices", "user_id='$id'");
if (!empty($device)) {
    LOG_INFO($db, "Delete device for user id: $id ".dump_record($db,'devices','user_id='.$id));
    unbind_ports($db, $device['id']);
    run_sql($db, "DELETE FROM connections WHERE device_id=" . $device['id']);
    run_sql($db, "DELETE FROM device_l3_interfaces WHERE device_id=" . $device['id']);
    run_sql($db, "DELETE FROM device_ports WHERE device_id=" . $device['id']);
    run_sql($db, "DELETE FROM device_filter_instances WHERE device_id=" . $device['id']);
    run_sql($db, "DELETE FROM gateway_subnets WHERE device_id=".$device['id']);
    delete_record($db, "devices", "id=" . $device['id']);
    }
//remove auth assign rules
run_sql($db, "DELETE FROM auth_rules WHERE user_id=$id");
return $changes;
}

function delete_device($db,$id)
{
LOG_INFO($db, "Try delete device id: $id ".dump_record($db,'devices','id='.$id));
//remove user record
$changes = delete_record($db, "devices", "id=" . $id);
//if fail - exit
if (!isset($changes) or empty($changes)) {
    LOG_INFO($db,"Device id: $id has not been deleted");
    return;
    }
unbind_ports($db, $id);
run_sql($db, "DELETE FROM connections WHERE device_id=" . $id);
run_sql($db, "DELETE FROM device_l3_interfaces WHERE device_id=" . $id);
run_sql($db, "DELETE FROM device_ports WHERE device_id=" . $id);
run_sql($db, "DELETE FROM device_filter_instances WHERE device_id=" . $id);
run_sql($db, "DELETE FROM gateway_subnets WHERE device_id=".$id);
return $changes;
}

function record_to_txt($db, $table, $id) {
    $record = get_record_sql($db, 'SELECT * FROM ' . $table . ' WHERE id =' . $id);
    return hash_to_text($record);
}

if (!defined("DB_TYPE")) { define("DB_TYPE","mysql"); }

$db_link = new_connection(DB_TYPE, DB_HOST, DB_USER, DB_PASS, DB_NAME);

?>