<?php
// Zabbix json rpc api client used by the web interface.
if (!defined("CONFIG")) {
    die("Not defined");
}

// Send a single request to the zabbix api. Returns the decoded result or null
function zabbix_api_request($method, $params = [], $use_auth_member = false)
{
    global $config, $db_link;

    $api_url = trim((string)($config["zabbix_api_url"] ?? ''));
    $api_key = trim((string)($config["zabbix_api_key"] ?? ''));
    if ($api_url === '' or $api_key === '') {
        return null;
    }

    if (!preg_match('#api_jsonrpc\.php$#', $api_url)) {
        $api_url = rtrim($api_url, '/') . '/api_jsonrpc.php';
    }

    $request_id = uniqid('eye', true);
    $body = [
        'jsonrpc' => '2.0',
        'method' => $method,
        'params' => $params,
        'id' => $request_id,
    ];
    //older zabbix versions know nothing about the Authorization header
    if ($use_auth_member) {
        $body['auth'] = $api_key;
    }

    $headers = ['Content-Type: application/json-rpc'];
    if (!$use_auth_member) {
        $headers[] = 'Authorization: Bearer ' . $api_key;
    }

    $raw = zabbix_http_post($api_url, json_encode($body, JSON_UNESCAPED_UNICODE), $headers);
    if ($raw === null) {
        return null;
    }

    $answer = json_decode($raw, true);
    if (!is_array($answer)) {
        LOG_ERROR($db_link, 'Zabbix: api returned not a json answer');
        return null;
    }

    //the bearer token is supported since zabbix 6.4, retry with the auth member
    if (isset($answer['error']) && !$use_auth_member) {
        LOG_DEBUG($db_link, 'Zabbix: bearer token rejected, retrying with the auth member');
        return zabbix_api_request($method, $params, true);
    }

    if (isset($answer['error'])) {
        LOG_ERROR($db_link, 'Zabbix api error [' . $method . ']: '
            . ($answer['error']['message'] ?? '') . ' ' . ($answer['error']['data'] ?? ''));
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
        //monitoring servers usally use self signed certificates
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        $result = curl_exec($ch);
        if ($result === false) {
            LOG_ERROR($db_link, 'Zabbix: http request failed: ' . curl_error($ch));
            curl_close($ch);
            return null;
        }
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($http_code < 200 or $http_code >= 300) {
            LOG_ERROR($db_link, 'Zabbix: http request failed, code ' . $http_code);
            return null;
        }
        return $result;
    }

    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => implode("\r\n", $headers) . "\r\n",
            'content' => $body,
            'timeout' => 30,
            'ignore_errors' => true,
        ],
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
        ],
    ]);

    $result = @file_get_contents($url, false, $context);
    if ($result === false) {
        LOG_ERROR($db_link, 'Zabbix: http request to ' . $url . ' failed');
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
            //low level discovery macros can not be set for a host
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
// Renamed templates are updated, disappeared templates are reported
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

    foreach ($bound as $row) {
        $template = empty($row['template_id']) ? null : zabbix_template_by_id($db, $row['template_id']);
        if ($template === null) {
            $template = zabbix_template_by_name($db, $row['template_name']);
        }

        if ($template === null) {
            $result['missing'][] = $row;
            continue;
        }

        if ($template['host'] !== $row['template_name'] || (string)$template['id'] !== (string)$row['template_id']) {
            $result['renamed'][] = [
                'row' => $row,
                'name' => $template['host'],
            ];
            update_record($db, 'zabbix_templates', 'id = ?',
                ['template_name' => $template['host'], 'template_id' => (int)$template['id']], [(int)$row['id']]);
            $row['template_name'] = $template['host'];
            $row['template_id'] = (int)$template['id'];
        }

        $result['ok'][] = $row;
    }

    return $result;
}

// Template by its id
function zabbix_template_by_id($db, $template_id)
{
    if (empty($template_id)) {
        return null;
    }

    $templates = zabbix_api_request('template.get', [
        'output' => ['templateid', 'host', 'name'],
        'templateids' => [(int)$template_id],
    ]);
    if (!is_array($templates) or empty($templates)) {
        return null;
    }

    return [
        'id' => $templates[0]['templateid'],
        'host' => $templates[0]['host'] ?? '',
        'name' => $templates[0]['name'] ?? ($templates[0]['host'] ?? ''),
    ];
}

// Template by its technical name
function zabbix_template_by_name($db, $name)
{
    if ($name === '' or $name === null) {
        return null;
    }

    $templates = zabbix_api_request('template.get', [
        'output' => ['templateid', 'host', 'name'],
        'filter' => ['host' => $name],
    ]);
    if (!is_array($templates) or empty($templates)) {
        return null;
    }

    return [
        'id' => $templates[0]['templateid'],
        'host' => $templates[0]['host'] ?? '',
        'name' => $templates[0]['name'] ?? ($templates[0]['host'] ?? ''),
    ];
}

// Host group validation for the organizational unit
function zabbix_group_exists($db, $group_id)
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
    print "<select id=\"$name\" name=\"$name\">\n";
    print_select_item(WEB_zabbix_macro_type_text, 0, $selected);
    print_select_item(WEB_zabbix_macro_type_secret, 1, $selected);
    print "</select>\n";
}
// Select of the zabbix host groups. A plain number field is shown when the api is unavailable
function print_zabbix_group_select($db, $name, $current = 0)
{
    $current = (int)$current;
    $groups = zabbix_host_group_list();

    if (empty($groups)) {
        print "<input type=\"number\" min=\"0\" id=\"$name\" name=\"$name\" value=\"$current\">";
        print "&nbsp;" . WEB_zabbix_group_api_unavailable;
        return;
    }

    print "<select id=\"$name\" name=\"$name\">\n";
    print_select_item(WEB_zabbix_group_none, 0, $current);
    $found = false;
    foreach ($groups as $group) {
        if ((int)$group['groupid'] === $current) {
            $found = true;
        }
        print_select_item($group['name'], $group['groupid'], $current);
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
