<?php
// Zabbix json rpc api client used by the web interface.
if (!defined("CONFIG")) {
    die("Not defined");
}

// Send a single request to the zabbix api. Returns the decoded result or null
function zabbix_api_request($method, $params = [])
{
    global $config, $db_link;

    $api_url = trim((string)($config["zabbix_api_url"] ?? ''));
    $api_key = trim((string)($config["zabbix_api_key"] ?? ''));
    
    if ($api_url === '' || $api_key === '') {
        LOG_ERROR($db_link, 'Zabbix: API URL or Key is not configured');
        return null;
    }

    if (!preg_match('#api_jsonrpc\.php$#', $api_url)) {
        $api_url = rtrim($api_url, '/') . '/api_jsonrpc.php';
    }

    $body = [
        'jsonrpc' => '2.0',
        'method'  => $method,
        'params'  => $params,
        'id'      => bin2hex(random_bytes(8)),
    ];

    $headers = [
        'Content-Type: application/json-rpc',
        'Authorization: Bearer ' . $api_key
    ];

    error_log("Zabbix URL: " . $api_url);
    error_log("Zabbix Headers: " . print_r($headers, true));
    error_log("Zabbix Body: " . json_encode($body, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

    $raw = zabbix_http_post($api_url, json_encode($body, JSON_UNESCAPED_UNICODE), $headers);
    if ($raw === null) {
        return null;
    }

    $answer = json_decode($raw, true);
    if (!is_array($answer)) {
        LOG_ERROR($db_link, 'Zabbix: API returned non-JSON answer: ' . substr($raw, 0, 250));
        return null;
    }

    // Если Zabbix вернул ошибку API
    if (isset($answer['error'])) {
        $err_msg = $answer['error']['message'] ?? 'Unknown error';
        $err_data = $answer['error']['data'] ?? 'No details';
        LOG_ERROR($db_link, "Zabbix API Error [$method]: $err_msg - $err_data");
        return null;
    }

    return $answer['result'] ?? null;
}

// Http transport, curl is used when the extension is available
function zabbix_http_post($url, $body, $headers)
{
    global $db_link;
    
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        
        $result = curl_exec($ch);
        if ($result === false) {
            LOG_ERROR($db_link, 'Zabbix: cURL request failed: ' . curl_error($ch));
            curl_close($ch);
            return null;
        }
        
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code < 200 || $http_code >= 300) {
            LOG_ERROR($db_link, "Zabbix: HTTP request failed, code $http_code. Response: " . substr($result, 0, 200));
            return null;
        }
        return $result;
    }

    // Fallback на file_get_contents
    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => implode("\r\n", $headers) . "\r\n",
            'content' => $body,
            'timeout' => 30,
            'ignore_errors' => true, // Позволяет прочитать тело ответа даже при 4xx/5xx
        ],
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
        ],
    ]);

    $result = @file_get_contents($url, false, $context);
    
    // Проверяем HTTP-код для file_get_contents (массив $http_response_header создается автоматически)
    if (isset($http_response_header) && is_array($http_response_header)) {
        $status_line = $http_response_header[0] ?? '';
        if (preg_match('{HTTP/\S+ (\d{3})}', $status_line, $match)) {
            $http_code = (int)$match[1];
            if ($http_code < 200 || $http_code >= 300) {
                LOG_ERROR($db_link, "Zabbix: HTTP request failed, code $http_code. Response: " . substr($result, 0, 200));
                return null;
            }
        }
    }

    if ($result === false) {
        LOG_ERROR($db_link, 'Zabbix: HTTP request to ' . $url . ' failed completely');
        return null;
    }
    
    return $result;
}

// All templates of the zabbix installation: id, technical and visible names
function zabbix_template_list()
{
    $templates = zabbix_api_request('template.get', [
        'output' => ['templateid', 'host', 'name'],
        'sortfield' => 'host',
    ]);
    if (!is_array($templates)) {
        return [];
    }

    $list = [];
    foreach ($templates as $template) {
        $list[] = [
            'id' => $template['templateid'],
            'host' => $template['host'] ?? '',
            'name' => $template['name'] ?? ($template['host'] ?? ''),
        ];
    }
    return $list;
}

// User macros of the given templates
function zabbix_template_macros($template_ids)
{
    if (empty($template_ids)) {
        return [];
    }

    $macros = zabbix_api_request('template.get', [
        'output' => ['templateid'],
        'templateids' => array_values(array_map('intval', $template_ids)),
        'selectMacros' => ['macro', 'value', 'type', 'description'],
    ]);
    if (!is_array($macros)) {
        return [];
    }

    $list = [];
    foreach ($macros as $template) {
        foreach ($template['macros'] ?? [] as $macro) {
            if (empty($macro['macro'])) {
                continue;
            }
            $name = strtoupper($macro['macro']);
            // low level discovery macros can not be set for a host
            if (strpos($name, '{#') === 0) {
                continue;
            }
            if (isset($list[$name])) {
                continue;
            }
            $list[$name] = [
                'macro' => $name,
                'value' => $macro['value'] ?? '',
                'type' => (int)($macro['type'] ?? 0),
                'description' => $macro['description'] ?? '',
            ];
        }
    }
    return array_values($list);
}

// Host groups of the zabbix installation
function zabbix_host_group_list()
{
    $groups = zabbix_api_request('hostgroup.get', [
        'output' => ['groupid', 'name'],
        'sortfield' => 'name',
    ]);
    return is_array($groups) ? $groups : [];
}

// Templates bound to the device model checked against zabbix.
// ОПТИМИЗИРОВАНО: теперь делает 1 запрос к API вместо N запросов (проблема N+1)
function zabbix_check_model_templates($db, $model_id)
{
    $result = [
        'ok' => [],
        'renamed' => [],
        'missing' => [],
    ];

    $bound = get_records_sql($db, "SELECT * FROM zabbix_templates WHERE device_model_id = ? ORDER BY template_name", [$model_id]);
    if (empty($bound)) {
        return $result;
    }

    // Собираем все ID и имена для массового запроса
    $template_ids = [];
    $template_names = [];
    foreach ($bound as $row) {
        if (!empty($row['template_id'])) {
            $template_ids[] = (int)$row['template_id'];
        }
        if (!empty($row['template_name'])) {
            $template_names[] = $row['template_name'];
        }
    }

    // ОДИН запрос к API вместо цикла
    $api_params = [
        'output' => ['templateid', 'host', 'name'],
    ];
    if (!empty($template_ids)) {
        $api_params['templateids'] = array_unique($template_ids);
    }
    if (!empty($template_names)) {
        // Если есть фильтр по ID, Zabbix проигнорирует filter, поэтому делаем хитрый запрос или объединяем
        // Для простоты и надежности делаем запрос по ID, а если ID нет - по имени
        if (empty($template_ids)) {
            $api_params['filter'] = ['host' => array_unique($template_names)];
        }
    }
    
    // Если есть и ID, и имена, лучше сделать два запроса или один без filter, но с широким templateids
    // Самый надежный способ получить всё за один раз, если есть и то, и другое:
    $api_templates = zabbix_api_request('template.get', $api_params);
    
    // Если были имена, которых не было в ID, допрашиваем их (редкий кейс, но для надежности)
    if (!empty($template_names) && (!is_array($api_templates) || count($api_templates) < count($bound))) {
        $extra_templates = zabbix_api_request('template.get', [
            'output' => ['templateid', 'host', 'name'],
            'filter' => ['host' => array_unique($template_names)]
        ]);
        if (is_array($extra_templates)) {
            $api_templates = is_array($api_templates) ? array_merge($api_templates, $extra_templates) : $extra_templates;
        }
    }

    // Строим карту для мгновенного поиска: id => template, name => template
    $templates_map = [];
    if (is_array($api_templates)) {
        // Убираем дубликаты на случай пересечения запросов
        $api_templates = array_unique($api_templates, SORT_REGULAR);
        foreach ($api_templates as $t) {
            $templates_map['id_' . $t['templateid']] = $t;
            $templates_map['name_' . $t['host']] = $t;
        }
    }

    foreach ($bound as $row) {
        $template = null;
        if (!empty($row['template_id']) && isset($templates_map['id_' . $row['template_id']])) {
            $template = $templates_map['id_' . $row['template_id']];
        } elseif (!empty($row['template_name']) && isset($templates_map['name_' . $row['template_name']])) {
            $template = $templates_map['name_' . $row['template_name']];
        }

        if ($template === null) {
            $result['missing'][] = $row;
            continue;
        }

        $api_name = $template['host'] ?? '';
        $api_id = (int)$template['templateid'];

        if ($api_name !== $row['template_name'] || $api_id !== (int)$row['template_id']) {
            $result['renamed'][] = [
                'row' => $row,
                'name' => $api_name,
            ];
            update_record($db, 'zabbix_templates', 'id = ?',
                ['template_name' => $api_name, 'template_id' => $api_id], [(int)$row['id']]
            );
            $row['template_name'] = $api_name;
            $row['template_id'] = $api_id;
        }

        $result['ok'][] = $row;
    }

    return $result;
}

// Template by its id (параметр $db удален, так как он не используется)
function zabbix_template_by_id($template_id)
{
    if (empty($template_id)) {
        return null;
    }

    $templates = zabbix_api_request('template.get', [
        'output' => ['templateid', 'host', 'name'],
        'templateids' => [(int)$template_id],
    ]);
    if (!is_array($templates) || empty($templates)) {
        return null;
    }

    return [
        'id' => $templates[0]['templateid'],
        'host' => $templates[0]['host'] ?? '',
        'name' => $templates[0]['name'] ?? ($templates[0]['host'] ?? ''),
    ];
}

// Template by its technical name (параметр $db удален, так как он не используется)
function zabbix_template_by_name($name)
{
    if ($name === '' || $name === null) {
        return null;
    }

    $templates = zabbix_api_request('template.get', [
        'output' => ['templateid', 'host', 'name'],
        'filter' => ['host' => $name],
    ]);
    if (!is_array($templates) || empty($templates)) {
        return null;
    }

    return [
        'id' => $templates[0]['templateid'],
        'host' => $templates[0]['host'] ?? '',
        'name' => $templates[0]['name'] ?? ($templates[0]['host'] ?? ''),
    ];
}

// Host group validation for the organizational unit (параметр $db удален)
function zabbix_group_exists($group_id)
{
    if (empty($group_id)) {
        return false;
    }

    $groups = zabbix_api_request('hostgroup.get', [
        'output' => ['groupid'],
        'groupids' => [(int)$group_id],
    ]);
    return is_array($groups) && !empty($groups);
}

// Select of the zabbix macro type
function print_zabbix_macro_type_select($name, $selected = 0)
{
    $selected = (int)$selected;
    $safe_name = htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); // Защита от XSS
    print "<select id=\"$safe_name\" name=\"$safe_name\">\n";
    print_select_item(WEB_zabbix_macro_type_text, 0, $selected);
    print_select_item(WEB_zabbix_macro_type_secret, 1, $selected);
    print "</select>\n";
}

// Select of the zabbix host groups. A plain number field is shown when the api is unavailable
function print_zabbix_group_select($db, $name, $current = 0)
{
    $current = (int)$current;
    $safe_name = htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); // Защита от XSS
    $groups = zabbix_host_group_list();

    if (empty($groups)) {
        print "<input type=\"number\" min=\"0\" id=\"$safe_name\" name=\"$safe_name\" value=\"$current\">";
        print "&nbsp;" . WEB_zabbix_group_api_unavailable;
        return;
    }

    print "<select id=\"$safe_name\" name=\"$safe_name\">\n";
    print_select_item(WEB_zabbix_group_none, 0, $current);
    $found = false;
    foreach ($groups as $group) {
        if ((int)$group['groupid'] === $current) {
            $found = true;
        }
        $group_name = htmlspecialchars($group['name'], ENT_QUOTES, 'UTF-8');
        print_select_item($group_name, $group['groupid'], $current);
    }
    if ($current !== 0 && !$found) {
        print_select_item(WEB_zabbix_group_not_found . " " . $current, $current, $current);
    }
    print "</select>\n";
}

// Macro names are stored in the zabbix notation, for example {\$SNMP_COMMUNITY}
function normalizer_zabbix_macro_name($name)
{
    $name = trim((string)$name);
    if ($name === '') {
        return '';
    }
    $name = strtoupper($name);
    $name = preg_replace('/[^A-Z0-9_.\${}]/', '', $name);
    if (substr($name, 0, 2) !== '{$') {
        $name = '{$' . preg_replace('/^\W+/', '', $name);
    }
    if (substr($name, -1) !== '}') {
        $name = $name . '}';
    }
    return $name;
}
