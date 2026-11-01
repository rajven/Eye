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

// Searchable select of the zabbix templates. The templates are passed as a list
// from zabbix_template_list(), the value of the option is the technical name.
// A text field is printed instead when the api gives no templates
function print_zabbix_template_select($name, $templates, $current = '')
{
    $safe_name = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
    $safe_current = htmlspecialchars($current, ENT_QUOTES, 'UTF-8');

    if (empty($templates)) {
        print "<input type=\"text\" id=\"$safe_name\" name=\"$safe_name\" value=\"$safe_current\" size=30>";
        print "&nbsp;" . WEB_zabbix_api_unavailable;
        return;
    }

    print "<select id=\"$safe_name\" name=\"$safe_name\" class=\"js-select-single\">\n";
    print_select_item(WEB_zabbix_template_select_empty, '', $current);
    foreach ($templates as $template) {
        $technical = $template['host'] ?? '';
        if ($technical === '') {
            continue;
        }
        // the visible name is shown when it differs from the technical one
        $label = $technical;
        if (!empty($template['name']) && $template['name'] !== $technical) {
            $label .= " [" . $template['name'] . "]";
        }
        print_select_item($label, $technical, $current);
    }
    print "</select>\n";
}

// User macros of all templates of the zabbix installation, they are used to fill
// the macro names of the model without typing them by hand
function zabbix_all_template_macros()
{
    $templates = zabbix_api_request('template.get', [
        'output' => ['templateid'],
        'selectMacros' => ['macro', 'value', 'type', 'description'],
    ]);
    if (!is_array($templates)) {
        return [];
    }

    $list = [];
    foreach ($templates as $template) {
        foreach ($template['macros'] ?? [] as $macro) {
            if (empty($macro['macro'])) {
                continue;
            }
            $name = strtoupper($macro['macro']);
            //low level discovery macros can not be set for a host
            if (strpos($name, '{#') === 0 || isset($list[$name])) {
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

    $list = array_values($list);
    usort($list, function ($a, $b) {
        return strcmp($a['macro'], $b['macro']);
    });
    return $list;
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

// Searchable select of the zabbix macros taken from the templates. A text field is
// printed instead when the api gives no macros, the name can always be typed by hand
function print_zabbix_macro_select($name, $macros, $current = '', $empty_label = NULL)
{
    $safe_name = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
    $safe_current = htmlspecialchars($current, ENT_QUOTES, 'UTF-8');

    if ($empty_label === NULL) {
        $empty_label = WEB_zabbix_macro_select_empty;
    }

    if (empty($macros)) {
        print "<input type=\"text\" id=\"$safe_name\" name=\"$safe_name\" value=\"$safe_current\" size=25>";
        print "&nbsp;" . WEB_zabbix_api_unavailable;
        return;
    }

    print "<select id=\"$safe_name\" name=\"$safe_name\" class=\"js-select-single\">\n";
    print_select_item($empty_label, '', $current);
    foreach ($macros as $macro) {
        $macro_name = $macro['macro'] ?? '';
        if ($macro_name === '') {
            continue;
        }
        $description = $macro_name;
        if (!empty($macro['description'])) {
            $description .= " - " . $macro['description'];
        }
        print_select_item($description, $macro_name, $current);
    }
    print "</select>\n";
}

// Searchable select of the macros list, the value of the option is the list id
function print_zabbix_macro_id_select($name, $macros, $placeholder = '')
{
    $safe_name = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
    print "<select id=\"$safe_name\" name=\"$safe_name\" class=\"js-select-single\">\n";
    print_select_item($placeholder, '', '');
    foreach ($macros as $macro) {
        $macro_name = $macro['macro'] ?? '';
        if ($macro_name === '') {
            continue;
        }
        $label = $macro_name;
        if (!empty($macro['description'])) {
            $label .= " - " . $macro['description'];
        }
        print_select_item($label, $macro['id'], '');
    }
    print "</select>\n";
}

// Searchable select of the tags list, the value of the option is the list id
function print_zabbix_tag_id_select($name, $tags, $placeholder = '')
{
    $safe_name = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
    print "<select id=\"$safe_name\" name=\"$safe_name\" class=\"js-select-single\">\n";
    print_select_item($placeholder, '', '');
    foreach ($tags as $tag) {
        $tag_name = $tag['macro'] ?? '';
        if ($tag_name === '') {
            continue;
        }
        $label = $tag_name;
        if (!empty($tag['description'])) {
            $label .= " - " . $tag['description'];
        }
        print_select_item($label, $tag['id'], '');
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

// Tags written to the zabbix hosts, they are the same as in the sync_zabbix script
define('ZABBIX_TAG_EYE', 'eye');
define('ZABBIX_TAG_PARENT', 'parent');

// Tag names are typed by hand, zabbix does not allow the equals sign in a tag name
function normalizer_zabbix_tag_name($name)
{
    $name = trim((string)$name);
    if ($name === '') {
        return '';
    }
    $name = preg_replace('/\s+/u', ' ', $name);
    $name = preg_replace('/[^\p{L}\p{N}_.\-]/u', '', $name);
    return substr($name, 0, 250);
}

// Host groups of the organizational unit, the identifiers bound to the ou
function zabbix_ou_group_ids($db, $ou_id)
{
    $group_ids = [];
    foreach (get_records_sql($db, "SELECT * FROM ou_zabbix_groups WHERE ou_id = ? ORDER BY id", [(int)$ou_id]) as $row) {
        if ((int)$row['group_id'] > 0) {
            $group_ids[] = (int)$row['group_id'];
        }
    }

    return $group_ids;
}

// The groups of zabbix which the ou does not use yet are offered in a searchable
// list, the value of the option is the identifier of the group
function print_zabbix_group_pick_select($name, $groups, $placeholder = '')
{
    $safe_name = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
    print "<select id=\"$safe_name\" name=\"$safe_name\" class=\"js-select-single\">\n";
    print_select_item($placeholder, '', '');
    foreach ($groups as $group) {
        $group_id = (int)($group['groupid'] ?? 0);
        if ($group_id <= 0) {
            continue;
        }
        print_select_item((string)($group['name'] ?? $group_id), $group_id, '');
    }
    print "</select>\n";
}

// The host groups of the ou: every bound group is a row of the list with the check box
// which unbinds it, a new group is chosen in the searchable list of the groups which
// the ou does not use yet. Without the api only the identifiers bound before stay in
// the list and a new identifier is typed by hand
function print_zabbix_groups_select($db, $group_ids)
{
    $bound = [];
    foreach ($group_ids as $group_id) {
        $group_id = (int)$group_id;
        if ($group_id > 0) {
            $bound[$group_id] = 1;
        }
    }

    // the marker shows that the block was sent, an ou without groups is legal
    print "<input type=\"hidden\" name=\"f_zabbix_groups_posted\" value=\"1\">\n";

    $groups = zabbix_host_group_list();
    $names = [];
    foreach ($groups as $group) {
        $group_id = (int)($group['groupid'] ?? 0);
        if ($group_id > 0) {
            $names[$group_id] = (string)($group['name'] ?? $group_id);
        }
    }

    print "<table class=\"data\">\n";
    print "<tr align=\"center\"><td><b>" . WEB_zabbix_group . "</b></td><td><b>" . WEB_zabbix_remove . "</b></td></tr>\n";

    if (empty($bound)) {
        print "<tr><td class=\"data\" colspan=\"2\" align=\"center\">" . WEB_ipset_empty . "</td></tr>\n";
    }

    foreach (array_keys($bound) as $group_id) {
        // the name of zabbix is shown when it is known, without the api only the
        // identifier is printed, a group which zabbix really does not know is marked
        if (isset($names[$group_id])) {
            $label = htmlspecialchars($names[$group_id], ENT_QUOTES, 'UTF-8');
        } elseif (empty($groups)) {
            $label = WEB_zabbix_group_id . "&nbsp;" . $group_id;
        } else {
            $label = WEB_zabbix_group_not_found . "&nbsp;" . $group_id;
        }
        print "<tr align=center><td class=\"data\">" . $label . "</td>\n";
        print "<td class=\"data\" style='padding:0'><input type=\"checkbox\" name=\"f_unlink[" . $group_id . "]\" value=\"1\"></td></tr>\n";
    }

    $free = [];
    foreach ($groups as $group) {
        $group_id = (int)($group['groupid'] ?? 0);
        if ($group_id <= 0 || isset($bound[$group_id])) {
            continue;
        }
        $free[] = ['groupid' => $group_id, 'name' => (string)($group['name'] ?? $group_id)];
    }

    print "<tr><td colspan=\"2\" class=\"data\">";
    if (!empty($groups)) {
        print "<b>" . WEB_zabbix_group_add . ":&nbsp;</b>";
        if (!empty($free)) {
            print_zabbix_group_pick_select('f_add_group', $free, WEB_zabbix_group_select_empty);
        } else {
            print WEB_zabbix_group_no_free;
        }
    } else {
        print WEB_zabbix_group_api_unavailable
            . "&nbsp;<input type=\"number\" min=\"1\" name=\"f_zabbix_group_new\" value=\"\">";
    }
    print "</td></tr>\n";
    print "</table>\n";
}

// The check boxes of the list and the group chosen in the searchable list are stored
// for the ou, the bindings which are not touched stay as they are
function save_zabbix_ou_groups($db, $ou_id)
{
    $ou_id = (int)$ou_id;
    if ($ou_id <= 0 || getPOST("f_zabbix_groups_posted", null, null) === null) {
        return;
    }

    $unlink = getPOST("f_unlink", null, []);
    if (is_array($unlink)) {
        foreach (array_keys($unlink) as $group_id) {
            $group_id = (int)$group_id;
            if ($group_id <= 0) {
                continue;
            }
            delete_records($db, "ou_zabbix_groups", "ou_id = ? AND group_id = ?", [$ou_id, $group_id]);
            // the hosts of this ou lose the group at once, it is the deliberate action
            // of the administrator and not a part of the reconciliation of a host
            zabbix_ou_group_detach($db, $ou_id, $group_id);
        }
    }

    $bound = zabbix_ou_group_ids($db, $ou_id);

    // the group of the searchable list, and the identifier typed by hand when the api
    // does not answer
    $new_ids = [];
    $new_ids[] = (int)getPOST("f_add_group", null, 0);
    $new_ids[] = (int)getPOST("f_zabbix_group_new", null, 0);

    foreach ($new_ids as $group_id) {
        if ($group_id <= 0 || in_array($group_id, $bound, true)) {
            continue;
        }
        insert_record($db, "ou_zabbix_groups", ['ou_id' => $ou_id, 'group_id' => $group_id]);
        $bound[] = $group_id;
    }
}

// Transliteration of the eye names. The table repeats eyelib::main::translit, so the
// web interface and the sync_zabbix.pl script give the same host names in zabbix
function zabbix_translit($text)
{
    $text = trim((string)$text);
    if ($text === '') {
        return '';
    }

    $replace = Array(
        'А' => 'A',
        'а' => 'a',
        'Б' => 'B',
        'б' => 'b',
        'В' => 'V',
        'в' => 'v',
        'Г' => 'G',
        'г' => 'g',
        'Д' => 'D',
        'д' => 'd',
        'Е' => 'E',
        'е' => 'e',
        'Ё' => 'E',
        'ё' => 'e',
        'Ж' => 'Zh',
        'ж' => 'zh',
        'З' => 'Z',
        'з' => 'z',
        'И' => 'I',
        'и' => 'i',
        'Й' => 'I',
        'й' => 'i',
        'К' => 'K',
        'к' => 'k',
        'Л' => 'L',
        'л' => 'l',
        'М' => 'M',
        'м' => 'm',
        'Н' => 'N',
        'н' => 'n',
        'О' => 'O',
        'о' => 'o',
        'П' => 'P',
        'п' => 'p',
        'Р' => 'R',
        'р' => 'r',
        'ТС' => 'T-S',
        'Тс' => 'T-s',
        'тс' => 't-s',
        'С' => 'S',
        'с' => 's',
        'Т' => 'T',
        'т' => 't',
        'У' => 'U',
        'у' => 'u',
        'Ф' => 'F',
        'ф' => 'f',
        'Х' => 'Kh',
        'х' => 'kh',
        'Ц' => 'Ts',
        'ц' => 'ts',
        'Ч' => 'Ch',
        'ч' => 'ch',
        'Ш' => 'Sh',
        'ш' => 'sh',
        'Щ' => 'Shch',
        'щ' => 'shch',
        'Ь' => '',
        'ь' => '',
        'Ъ' => '',
        'ъ' => '',
        'Ь' => '',
        'ь' => '',
        'Ъ' => '',
        'ъ' => '',
        'Ы' => 'Y',
        'ы' => 'y',
        'Э' => 'E',
        'э' => 'e',
        'Ю' => 'Yu',
        'ю' => 'yu',
        'Я' => 'Ya',
        'я' => 'ya'
    );

    return strtr($text, $replace);
}

// The name of the ip record host, the same rule as in the sync_zabbix script
function zabbix_auth_host_name($auth, $login = NULL)
{
    if (!empty($auth['dns_name'])) {
        return $auth['dns_name'];
    }
    if (empty(trim($login['login']))) {
        return 'user_' . $auth['user_id'];
    }
    return trim($login['login']);
}

// The name of the network device host, the same rule as in the sync_zabbix script
function zabbix_device_host_name($device)
{
    $name = trim((string)zabbix_translit($device['device_name'] ?? ''));
    if ($name !== '') {
        return $name;
    }
    return 'netdev_' . $device['id'];
}

// The visible name of the host, the description of the eye record is used for it
function zabbix_host_visible_name($description, $fallback)
{
    $visible = trim((string)zabbix_translit($description ?? ''));
    if ($visible !== '') {
        return $visible;
    }
    return $fallback;
}

// One host by its technical name
function zabbix_get_host_by_name($name)
{
    $found = zabbix_get_hosts_by_names([$name]);
    return $found[strtolower(trim((string)$name))] ?? null;
}

// Hosts by their technical names, the result is keyed by the lower case name
function zabbix_get_hosts_by_names($names)
{
    $names = array_values(array_filter((array)$names, function ($name) {
        return trim((string)$name) !== '';
    }));
    if (empty($names)) {
        return [];
    }

    $hosts = zabbix_api_request('host.get', [
        'output' => ['hostid', 'host', 'name', 'status', 'display', 'description'],
        'filter' => ['host' => $names],
        'selectParentTemplates' => ['templateid', 'host', 'name'],
        'selectMacros' => ['macro', 'value', 'type'],
        'selectTags' => ['tag', 'value'],
        'selectHostGroups' => ['groupid', 'name'],
    ]);
    if (!is_array($hosts)) {
        return [];
    }

    $found = [];
    foreach ($hosts as $host) {
        $key = strtolower(trim((string)$host['host']));
        $found[$key] = zabbix_host_normalize($host);
    }
    return $found;
}

// All hosts carrying the eye tag, one call replaces a search per record
function zabbix_get_tagged_hosts($tag_name = ZABBIX_TAG_EYE)
{
    $hosts = zabbix_api_request('host.get', [
        'output' => ['hostid', 'host', 'name', 'status', 'display', 'description'],
        // operator 4 - the host has a tag with this name, the value is not checked
        'tags' => [['tag' => $tag_name, 'value' => '', 'operator' => 4]],
        'selectParentTemplates' => ['templateid', 'host', 'name'],
        'selectMacros' => ['macro', 'value', 'type'],
        'selectTags' => ['tag', 'value'],
        'selectHostGroups' => ['groupid', 'name'],
    ]);
    if (!is_array($hosts)) {
        return [];
    }

    $found = [];
    foreach ($hosts as $host) {
        $info = zabbix_host_normalize($host);
        $value = zabbix_host_tag_value($info, $tag_name);
        if ($value !== '') {
            $found[$value] = $info;
        }
    }
    return $found;
}

// A host by the eye tag value, it is found even after it was renamed
function zabbix_get_host_by_tag($tag_value, $tag_name = ZABBIX_TAG_EYE)
{
    if ($tag_value === '' || $tag_value === null) {
        return null;
    }

    $hosts = zabbix_api_request('host.get', [
        'output' => ['hostid', 'host', 'name', 'status', 'display', 'description'],
        // operator 1 - the tag value is equal to the requested one
        'tags' => [['tag' => $tag_name, 'value' => $tag_value, 'operator' => 1]],
        'selectParentTemplates' => ['templateid', 'host', 'name'],
        'selectMacros' => ['macro', 'value', 'type'],
        'selectTags' => ['tag', 'value'],
        'selectHostGroups' => ['groupid', 'name'],
    ]);
    if (!is_array($hosts) || empty($hosts)) {
        return null;
    }

    return zabbix_host_normalize($hosts[0]);
}

// Host structure with the templates, the macros, the groups and the tags
function zabbix_host_normalize($host)
{
    $templates = [];
    foreach ($host['parentTemplates'] ?? [] as $template) {
        $templates[] = [
            'templateid' => $template['templateid'],
            'host' => $template['host'] ?? '',
            'name' => $template['name'] ?? ($template['host'] ?? ''),
        ];
    }

    $macros = [];
    foreach ($host['macros'] ?? [] as $macro) {
        $macros[] = [
            'macro' => $macro['macro'],
            'value' => $macro['value'] ?? '',
            'type' => (int)($macro['type'] ?? 0),
        ];
    }

    $tags = [];
    foreach ($host['tags'] ?? [] as $tag) {
        $tags[] = ['tag' => $tag['tag'], 'value' => $tag['value'] ?? ''];
    }

    $groups = [];
    foreach ($host['hostgroups'] ?? $host['groups'] ?? [] as $group) {
        $groups[] = ['groupid' => $group['groupid'], 'name' => $group['name'] ?? ''];
    }

    return [
        'hostid' => $host['hostid'],
        'host' => $host['host'] ?? '',
        'name' => $host['name'] ?? '',
        'description' => $host['description'] ?? '',
        'display' => $host['display'] ?? '',
        'status' => isset($host['status']) ? (int)$host['status'] : 0,
        'templates' => $templates,
        'macros' => $macros,
        'tags' => $tags,
        'groups' => $groups,
    ];
}

// Value of the host tag
function zabbix_host_tag_value($host, $tag_name)
{
    foreach ($host['tags'] ?? [] as $tag) {
        if (strtolower((string)($tag['tag'] ?? '')) === strtolower((string)$tag_name)) {
            return (string)($tag['value'] ?? '');
        }
    }
    return '';
}

// Only the missing templates are added, the templates of the host are never removed
function zabbix_host_merge_templates($host, $wanted)
{
    $merged = [];
    $current = [];
    foreach ($host['templates'] ?? [] as $template) {
        $id = $template['templateid'] ?? 0;
        if ($id && !isset($current[$id])) {
            $current[$id] = 1;
            $merged[] = (int)$id;
        }
    }

    $added = [];
    foreach ($wanted as $id) {
        if (!$id || isset($current[$id])) {
            continue;
        }
        $current[$id] = 1;
        $merged[] = (int)$id;
        $added[] = (int)$id;
    }

    return [$merged, $added];
}

// The macros of the model are refreshed, the foreign macros of the host are kept
function zabbix_host_merge_macros($host, $wanted, $owned = [])
{
    $wanted_index = [];
    foreach ($wanted as $macro) {
        if (!empty($macro['macro'])) {
            $wanted_index[strtoupper((string)$macro['macro'])] = 1;
        }
    }

    $current = [];
    $merged = [];
    foreach ($host['macros'] ?? [] as $macro) {
        if (empty($macro['macro'])) {
            continue;
        }
        $key = strtoupper($macro['macro']);
        if (isset($current[$key])) {
            continue;
        }
        // a macro of the global list which is not assigned anymore leaves the host,
        // the macros of the other integrations stay
        if (isset($owned[$key]) && !isset($wanted_index[$key])) {
            continue;
        }
        $current[$key] = 1;
        $merged[] = [
            'macro' => $macro['macro'],
            'value' => $macro['value'] ?? '',
            'type' => (int)($macro['type'] ?? 0),
        ];
    }

    foreach ($wanted as $macro) {
        if (empty($macro['macro'])) {
            continue;
        }
        $key = strtoupper($macro['macro']);
        $entry = [
            'macro' => $macro['macro'],
            'value' => $macro['value'] ?? '',
            'type' => (int)($macro['type'] ?? 0),
        ];
        if (isset($current[$key])) {
            // the host knows this macro, its value is refreshed
            foreach ($merged as $index => $item) {
                if (strtoupper($item['macro']) === $key) {
                    $merged[$index] = $entry;
                }
            }
        } else {
            $current[$key] = 1;
            $merged[] = $entry;
        }
    }

    return $merged;
}

// Interface of the eye record: a snmp interface for the equipment with snmp, an agent
// interface for the rest of the hosts, zabbix can not poll a host without an interface
function zabbix_host_interface($spec)
{
    $ip = trim((string)($spec['ip'] ?? ''));
    $ip = preg_replace('#/\d+$#', '', $ip);
    if ($ip === '') {
        return null;
    }

    $interface = [
        'type' => 1,
        'ip' => $ip,
        'dns' => '',
        'port' => $spec['agent_port'] ?? '10050',
        'useip' => 1,
        'main' => 1,
    ];

    $snmp = $spec['snmp'] ?? [];
    $version = (int)($snmp['version'] ?? 0);
    if ($version < 1) {
        return $interface;
    }

    $interface['type'] = 2;
    $interface['port'] = $snmp['port'] ?? '161';

    $details = ['version' => $version, 'bulk' => 1];
    if ($version == 3) {
        $password = $snmp['ro-password'] ?? '';
        $details['securityname'] = $snmp['ro-user'] ?? '';
        // eye stores a single passphrase, it is used for the authorization and the encryption
        $details['securitylevel'] = ($password !== '') ? 2 : 0;
        $details['authprotocol'] = zabbix_snmp_auth_protocol($snmp['auth-proto'] ?? '');
        $details['privprotocol'] = zabbix_snmp_priv_protocol($snmp['priv-proto'] ?? '');
        $details['authpassphrase'] = $password;
        $details['privpassphrase'] = $password;
        $details['contextname'] = '';
    } else {
        $details['community'] = $snmp['ro-community'] ?? '';
    }
    $interface['details'] = $details;

    return $interface;
}

// snmp authorization protocol of eye => number of zabbix
function zabbix_snmp_auth_protocol($protocol)
{
    $protocol = strtolower(trim((string)$protocol));
    if ($protocol === '') {
        $protocol = 'sha';
    }
    $map = [
        'md5' => 0,
        'sha' => 1,
        'sha1' => 1,
        'sha224' => 2,
        'sha256' => 3,
        'sha384' => 4,
        'sha512' => 5,
    ];
    return $map[$protocol] ?? 1;
}

// snmp encryption protocol of eye => number of zabbix
function zabbix_snmp_priv_protocol($protocol)
{
    $protocol = strtolower(trim((string)$protocol));
    if ($protocol === '') {
        $protocol = 'aes128';
    }
    $map = [
        'des' => 0,
        'aes' => 1,
        'aes128' => 1,
        'aes192' => 2,
        'aes256' => 3,
    ];
    return $map[$protocol] ?? 1;
}

// Creation of the host, an empty group is not allowed by zabbix
function zabbix_api_create_host($spec)
{
    $params = [
        'host' => $spec['host'],
        'name' => $spec['display'] ?? $spec['host'],
        'description' => (string)($spec['description'] ?? ''),
        'status' => 0,
    ];

    $interface = zabbix_host_interface($spec);
    if ($interface) {
        $params['interfaces'] = [$interface];
    }

    if (!empty($spec['groups'])) {
        $params['groups'] = array_map(function ($group_id) {
            return ['groupid' => (int)$group_id];
        }, array_values($spec['groups']));
    }

    if (!empty($spec['templates'])) {
        $params['templates'] = array_map(function ($template_id) {
            return ['templateid' => (int)$template_id];
        }, array_values($spec['templates']));
    }

    if (!empty($spec['macros'])) {
        $params['macros'] = $spec['macros'];
    }

    if (!empty($spec['tags'])) {
        $params['tags'] = zabbix_api_tag_list($spec['tags']);
    }

    $created = zabbix_api_request('host.create', $params);
    if (!is_array($created) || empty($created['hostids'])) {
        return null;
    }
    return $created['hostids'][0];
}

// Update of the host, only the passed keys are touched. zabbix replaces the full
// lists of templates, macros and tags, so the merged lists must be passed
function zabbix_api_update_host($hostid, $update)
{
    $params = ['hostid' => $hostid];

    if (!empty($update['host'])) {
        $params['host'] = $update['host'];
    }
    if (isset($update['description'])) {
        $params['description'] = $update['description'];
    }
    if (!empty($update['templates'])) {
        $params['templates'] = array_map(function ($template_id) {
            return ['templateid' => (int)$template_id];
        }, array_values($update['templates']));
    }
    if (!empty($update['macros'])) {
        $params['macros'] = $update['macros'];
    }
    if (!empty($update['tags'])) {
        $params['tags'] = zabbix_api_tag_list($update['tags']);
    }
    if (!empty($update['groups'])) {
        $params['groups'] = array_map(function ($group_id) {
            return ['groupid' => (int)$group_id];
        }, array_values($update['groups']));
    }
    if (isset($update['status'])) {
        $params['status'] = (int)$update['status'];
    }

    return zabbix_api_request('host.update', $params) !== null;
}

// Monitoring of the host is switched off, the host is never removed from zabbix
function zabbix_api_set_host_status($hostid, $status)
{
    return zabbix_api_update_host($hostid, ['status' => (int)$status]);
}

// hash reference of tags => list of zabbix tags
function zabbix_api_tag_list($tags)
{
    $list = [];
    foreach ($tags as $tag => $value) {
        if ($value === null) {
            continue;
        }
        $value = preg_replace('/\s+/', ' ', (string)$value);
        $list[] = ['tag' => $tag, 'value' => $value];
    }
    return $list;
}
