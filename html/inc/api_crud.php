<?php
// Universal CRUD layer of the api. Each entity is described once: the columns which
// may be searched, created and edited, their types, the tables they refer to and the
// unique keys. The old methods of api.php keep working, this file only adds verbs.
if (!defined("CONFIG")) {
    die("Not defined");
}

// Types of the columns: text, int, flag, ip, mac, port, rate, enum, ref (foreign key)
function api_entities()
{
    return [
        // organizational units of eye, the groups of the menu "groups"
        'group' => [
            'table'   => 'ou',
            'plural'  => 'groups',
            'alias'   => ['ou', 'ous', 'ou_group'],
            'order'   => 'id',
            'soft_delete' => false,
            'search'  => ['ou_name', 'description'],
            'exact'   => ['id', 'enabled', 'default_users', 'default_hotspot', 'dynamic', 'parent_id',
                          'filter_group_id', 'queue_id'],
            'unique'  => [['ou_name']],
            'fields'  => [
                'ou_name'               => ['type' => 'text', 'len' => 40,  'create' => true, 'edit' => true, 'search' => true],
                'description'           => ['type' => 'text', 'len' => 250, 'create' => true, 'edit' => true, 'search' => true],
                'enabled'               => ['type' => 'int',  'min' => 0, 'max' => 1,      'create' => true, 'edit' => true],
                'default_users'         => ['type' => 'flag',                               'create' => true, 'edit' => true],
                'default_hotspot'       => ['type' => 'flag',                               'create' => true, 'edit' => true],
                'dynamic'               => ['type' => 'flag',                               'create' => true, 'edit' => true],
                'life_duration'         => ['type' => 'rate',                               'create' => true, 'edit' => true],
                'parent_id'             => ['type' => 'ref',  'table' => 'ou',             'create' => true, 'edit' => true],
                'filter_group_id'       => ['type' => 'ref',  'table' => 'group_list',     'create' => true, 'edit' => true],
                'queue_id'              => ['type' => 'ref',  'table' => 'queue_list',     'create' => true, 'edit' => true],
                'nagios_dir'            => ['type' => 'text', 'len' => 255, 'system' => true, 'create' => true, 'edit' => true],
                'nagios_host_use'       => ['type' => 'text', 'len' => 50,  'system' => true, 'create' => true, 'edit' => true],
                'nagios_ping'           => ['type' => 'flag',              'system' => true, 'create' => true, 'edit' => true],
                'nagios_default_service'=> ['type' => 'text', 'len' => 100, 'system' => true, 'create' => true, 'edit' => true],
            ],
            'required' => ['ou_name'],
        ],

        // subscribers
        'user' => [
            'table'   => 'user_list',
            'plural'  => 'users',
            'alias'   => ['users', 'subscriber'],
            'order'   => 'id',
            'soft_delete' => true,
            'search'  => ['login', 'description'],
            'exact'   => ['id', 'ou_id', 'enabled', 'blocked', 'filter_group_id', 'queue_id', 'permanent', 'device_id'],
            'unique'  => [['login', 'deleted = 0']],
            'fields'  => [
                'login'            => ['type' => 'text', 'len' => 255, 'create' => true, 'edit' => true, 'search' => true],
                'description'      => ['type' => 'text', 'len' => 255, 'create' => true, 'edit' => true, 'search' => true],
                'ou_id'            => ['type' => 'ref',  'table' => 'ou',           'create' => true, 'edit' => true],
                'enabled'          => ['type' => 'flag',  'default' => 1,           'create' => true, 'edit' => true],
                'blocked'          => ['type' => 'flag',                            'create' => true, 'edit' => true],
                'permanent'        => ['type' => 'flag',                            'create' => true, 'edit' => true],
                'filter_group_id'  => ['type' => 'ref',  'table' => 'group_list',   'create' => true, 'edit' => true],
                'queue_id'         => ['type' => 'ref',  'table' => 'queue_list',    'create' => true, 'edit' => true],
                'day_quota'        => ['type' => 'int',  'min' => 0, 'max' => 1000000000, 'create' => true, 'edit' => true],
                'month_quota'      => ['type' => 'int',  'min' => 0, 'max' => 1000000000, 'create' => true, 'edit' => true],
            ],
            'required' => ['login', 'ou_id'],
            'before'   => 'api_before_user',
            // a subscriber is also found by the address and the mac of his record
            'sub'      => ['ip' => 'id IN (SELECT user_id FROM user_auth WHERE deleted = 0 AND ip_int = ?)',
                           'ip_int' => 'id IN (SELECT user_id FROM user_auth WHERE deleted = 0 AND ip_int = ?)',
                           'mac' => 'id IN (SELECT user_id FROM user_auth WHERE deleted = 0 AND mac = ?)'],
            'with'     => ['addresses' => ['entity' => 'address', 'field' => 'user_id', 'of' => 'id'],
                           'devices'  => ['entity' => 'device',  'field' => 'user_id', 'of' => 'id']],
        ],

        // ip records of the subscribers
        'address' => [
            'table'   => 'user_auth',
            'plural'  => 'addresses',
            'alias'   => ['user_auth', 'ip', 'ips', 'address'],
            'order'   => 'ip_int',
            'soft_delete' => true,
            'search'  => ['ip', 'mac', 'dns_name', 'description', 'dhcp_hostname'],
            'exact'   => ['id', 'user_id', 'ou_id', 'enabled', 'dhcp', 'dynamic', 'filter_group_id', 'queue_id',
                          'blocked', 'dns_ptr_only', 'save_traf'],
            'unique'  => [['ip', 'deleted = 0']],
            'fields'  => [
                'user_id'          => ['type' => 'ref',  'table' => 'user_list',  'create' => true, 'edit' => true],
                'ip'               => ['type' => 'ip',                            'create' => true, 'edit' => true, 'search' => true],
                'mac'              => ['type' => 'mac',                           'create' => true, 'edit' => true, 'search' => true],
                'description'      => ['type' => 'text', 'len' => 250,            'create' => true, 'edit' => true, 'search' => true],
                'dns_name'         => ['type' => 'text', 'len' => 253,            'create' => true, 'edit' => true, 'search' => true],
                'dns_ptr_only'     => ['type' => 'flag',                          'create' => true, 'edit' => true],
                'ou_id'            => ['type' => 'ref',  'table' => 'ou',         'create' => true, 'edit' => true],
                'enabled'          => ['type' => 'flag',  'default' => 1,         'create' => true, 'edit' => true],
                'dhcp'             => ['type' => 'flag',  'default' => 1,         'create' => true, 'edit' => true],
                'dynamic'          => ['type' => 'flag',                          'create' => true, 'edit' => true],
                'blocked'          => ['type' => 'flag',                          'create' => true, 'edit' => true],
                'save_traf'        => ['type' => 'flag',                          'create' => true, 'edit' => true],
                'filter_group_id'  => ['type' => 'ref',  'table' => 'group_list', 'create' => true, 'edit' => true],
                'queue_id'         => ['type' => 'ref',  'table' => 'queue_list', 'create' => true, 'edit' => true],
                'day_quota'        => ['type' => 'int',  'min' => 0, 'max' => 1000000000, 'create' => true, 'edit' => true],
                'month_quota'      => ['type' => 'int',  'min' => 0, 'max' => 1000000000, 'create' => true, 'edit' => true],
                'dhcp_hostname'    => ['type' => 'text', 'len' => 60,             'create' => false,'edit' => true],
                'dhcp_option_set'  => ['type' => 'text', 'len' => 50,             'create' => true, 'edit' => true],
                'firmware'         => ['type' => 'text', 'len' => 100,            'create' => true, 'edit' => true],
                'client_id'        => ['type' => 'text', 'len' => 250,            'create' => false,'edit' => true],
            ],
            'required' => ['user_id', 'ip'],
            'with'     => ['user' => ['entity' => 'user', 'field' => 'id', 'of' => 'user_id']],
            'before'   => 'api_before_address',
            // the parameters of the monitoring system left the ip record, they are the
            // parameters of the device of its owner now
            'moved'    => ['monitoring' => 'the device', 'monitoring_old' => 'the device',
                           'monitoring_status' => 'the device', 'nagios_handler' => 'the device',
                           'link_check' => 'the device', 'wikiname' => 'the device'],
        ],

        // net filter
        'filter' => [
            'table'   => 'filter_list',
            'plural'  => 'filters',
            'alias'   => ['filters', 'net_filter'],
            'order'   => 'name',
            'soft_delete' => false,
            'search'  => ['name', 'description', 'dst'],
            'exact'   => ['id', 'filter_type', 'proto'],
            'unique'  => [['name']],
            'fields'  => [
                'name'        => ['type' => 'text', 'len' => 50, 'create' => true, 'edit' => true, 'search' => true],
                'description' => ['type' => 'text', 'len' => 250, 'create' => true, 'edit' => true, 'search' => true],
                'proto'       => ['type' => 'enum', 'values' => ['', 'tcp', 'udp', 'icmp', 'all'], 'create' => true, 'edit' => true],
                'dst'         => ['type' => 'text', 'len' => 250, 'create' => true, 'edit' => true],
                'srcport'     => ['type' => 'portlist', 'create' => true, 'edit' => true],
                'dstport'     => ['type' => 'portlist', 'create' => true, 'edit' => true],
                'ipset_id'    => ['type' => 'ref', 'table' => 'ipset_list', 'create' => true, 'edit' => true],
                'filter_type' => ['type' => 'enum', 'values' => [0, 1], 'default' => 0, 'create' => true, 'edit' => true],
            ],
            'required' => ['name'],
            'before'   => 'api_before_filter',
        ],

        // group of the net filters
        'filtergroup' => [
            'table'   => 'group_list',
            'plural'  => 'filtergroups',
            'alias'   => ['groups_filter', 'filters_group', 'filter_group'],
            'order'   => 'group_name',
            'soft_delete' => false,
            'search'  => ['group_name', 'description'],
            'exact'   => ['id', 'instance_id'],
            'unique'  => [['group_name']],
            'fields'  => [
                'group_name'  => ['type' => 'text', 'len' => 50,  'create' => true, 'edit' => true, 'search' => true],
                'description' => ['type' => 'text', 'len' => 250, 'create' => true, 'edit' => true, 'search' => true],
                'instance_id' => ['type' => 'ref',  'table' => 'filter_instances', 'create' => true, 'edit' => true],
            ],
            'required' => ['group_name'],
        ],

        // filter of the group of the net filters: the rule which binds them
        'filtergrouprule' => [
            'table'   => 'group_filters',
            'plural'  => 'filtergrouprules',
            'alias'   => ['group_filter', 'filters_group_rule', 'rule'],
            'order'   => 'group_id, rule_order',
            'soft_delete' => false,
            'search'  => [],
            'exact'   => ['id', 'group_id', 'filter_id', 'action'],
            'unique'  => [['group_id', 'filter_id']],
            'fields'  => [
                'group_id'   => ['type' => 'ref', 'table' => 'group_list',  'create' => true, 'edit' => true],
                'filter_id'  => ['type' => 'ref', 'table' => 'filter_list', 'create' => true, 'edit' => true],
                'action'     => ['type' => 'enum', 'values' => [0, 1], 'default' => 0, 'create' => true, 'edit' => true],
                'rule_order' => ['type' => 'int',  'min' => 0, 'max' => 9999, 'create' => true, 'edit' => true],
            ],
            'required' => ['group_id', 'filter_id'],
            // a new rule of the group joins the end of the list when no order is asked
            'before'   => 'api_before_rule',

        ],

        // traffic shaper
        'queue' => [
            'table'   => 'queue_list',
            'plural'  => 'queues',
            'alias'   => ['shaper', 'shapers', 'queues'],
            'order'   => 'queue_name',
            'soft_delete' => false,
            'search'  => ['queue_name'],
            'exact'   => ['id'],
            'unique'  => [['queue_name']],
            'fields'  => [
                'queue_name' => ['type' => 'text', 'len' => 20, 'create' => true, 'edit' => true, 'search' => true],
                'download'   => ['type' => 'rate', 'min' => 0, 'max' => 100000000, 'create' => true, 'edit' => true],
                'upload'     => ['type' => 'rate', 'min' => 0, 'max' => 100000000, 'create' => true, 'edit' => true],
            ],
            'required' => ['queue_name'],
        ],

        // network device of eye: a host of the monitoring system
        'device' => [
            'table'   => 'devices',
            'plural'  => 'devices',
            'alias'   => ['device', 'netdevice'],
            'order'   => 'device_name',
            'soft_delete' => true,
            'search'  => ['device_name', 'ip', 'description', 'sn', 'wikiname', 'login'],
            'exact'   => ['id', 'user_id', 'device_type', 'device_model_id', 'building_id', 'vendor_id',
                          'monitoring', 'monitoring_old', 'link_check', 'active', 'discovery', 'dhcp',
                          'queue_enabled', 'connected_user_only', 'user_acl', 'netflow_save', 'snmp_version'],
            'unique'  => [['device_name', 'deleted = 0']],
            'fields'  => [
                'device_name'        => ['type' => 'text', 'len' => 50,  'create' => true, 'edit' => true, 'search' => true],
                'description'        => ['type' => 'text', 'len' => 255, 'create' => true, 'edit' => true, 'search' => true],
                'ip'                 => ['type' => 'ip',                'create' => true, 'edit' => true, 'search' => true],
                'device_type'        => ['type' => 'int', 'min' => 0, 'max' => 99, 'default' => 5, 'create' => true, 'edit' => true],
                'device_model_id'    => ['type' => 'ref', 'table' => 'device_models', 'create' => true, 'edit' => true],
                'building_id'        => ['type' => 'ref', 'table' => 'building',      'create' => true, 'edit' => true],
                'user_id'            => ['type' => 'ref', 'table' => 'user_list',     'create' => true, 'edit' => true],
                'port_count'         => ['type' => 'int', 'min' => 0, 'max' => 512,  'create' => true, 'edit' => true],
                'firmware'           => ['type' => 'text', 'len' => 100,              'create' => true, 'edit' => true],
                'sn'                 => ['type' => 'text', 'len' => 80,               'create' => true, 'edit' => true, 'search' => true],
                'login'              => ['type' => 'text', 'len' => 50,               'create' => true, 'edit' => true],
                'protocol'           => ['type' => 'int', 'min' => -1, 'max' => 99,  'create' => true, 'edit' => true],
                'control_port'       => ['type' => 'port',                           'create' => true, 'edit' => true],
                'snmp_version'       => ['type' => 'int', 'min' => 0, 'max' => 3,    'create' => true, 'edit' => true],
                'community'          => ['type' => 'text', 'len' => 50,               'create' => true, 'edit' => true],
                'rw_community'       => ['type' => 'text', 'len' => 50,              'create' => true, 'edit' => true],
                'discovery'          => ['type' => 'flag',                           'create' => true, 'edit' => true],
                'dhcp'               => ['type' => 'flag',                           'create' => true, 'edit' => true],
                'netflow_save'       => ['type' => 'flag',                           'create' => true, 'edit' => true],
                'user_acl'           => ['type' => 'flag',                           'create' => true, 'edit' => true],
                'queue_enabled'      => ['type' => 'flag',                           'create' => true, 'edit' => true],
                'connected_user_only'=> ['type' => 'flag',  'default' => 1,          'create' => true, 'edit' => true],
                'active'             => ['type' => 'flag',  'default' => 1,          'create' => true, 'edit' => true],
                'monitoring'         => ['type' => 'flag',                           'create' => true, 'edit' => true],
                'link_check'         => ['type' => 'flag',                           'create' => true, 'edit' => true],
                'nagios_handler'     => ['type' => 'text', 'len' => 50,              'create' => true, 'edit' => true],
                'wikiname'           => ['type' => 'text', 'len' => 250,             'create' => true, 'edit' => true, 'search' => true],
            ],
            'required' => ['device_name'],
            // the secrets of the remote access never leave the answer of the api
            'hidden'   => ['password', 'snmp3_user_ro_password', 'snmp3_user_rw_password', 'community', 'rw_community', 'login'],
            // the vendor of the device comes from its model, the ports follow the count
            'before'   => 'api_before_device',
            'after'    => 'api_after_device',
        ],
    ];
}

// Entity of the request by its name, the singular, the plural and the aliases work
function api_entity_find($name)
{
    if ($name === null || $name === '') {
        return null;
    }

    $name = strtolower(trim((string)$name));
    foreach (api_entities() as $key => $spec) {
        $names = array_merge([$key, $spec['plural']], $spec['alias'] ?? []);
        foreach ($names as $candidate) {
            if ($name === strtolower((string)$candidate)) {
                return array_merge($spec, ['entity' => $key]);
            }
        }
    }

    return null;
}

// Value of a request parameter, the json body of the request wins over the url
function api_param($name, $data = [])
{
    if (is_array($data) && array_key_exists($name, $data)) {
        return $data[$name];
    }

    return getParam($name, null, null);
}

// The names which a request of the api carries but which are never a field of a record: they
// say what to do, how deep to page and who is asking, so a record has no column of such a name
function api_service_name($name)
{
    static $service = null;
    if ($service === null) {
        $service = [];
        foreach (['get', 'send', 'api_key', 'login', 'data', 'table', 'filter', 'search', 'q',
                  'limit', 'offset', 'with', 'with_deleted', 'action', 'id', 'ip', 'mac',
                  'subnet', 'hostname', 'zombi_days', 'sort', 'order', 'force'] as $word) {
            $service[strtolower($word)] = true;
        }
    }

    return isset($service[strtolower(trim((string)$name))]);
}

// One column of a request: the text of a row, an identifier, an ip, a mac, a port
function api_check_field($db, $spec, $field, $value)
{
    $conf = $spec['fields'][$field];
    $type = $conf['type'] ?? 'text';

    if ($value === null) {
        return [false, 'empty value'];
    }

    if (is_array($value)) {
        return [false, 'array instead of a value'];
    }

    switch ($type) {
        case 'flag':
            $value = trim((string)$value);
            if ($value === '') {
                $value = $conf['default'] ?? 0;
            }
            if (!in_array($value, ['0', '1'], true)) {
                return [false, 'must be 0 or 1'];
            }
            return [true, (int)$value];

        case 'enum':
            $value = trim((string)$value);
            if ($value === '') {
                return [true, $conf['default'] ?? ''];
            }
            if (!in_array((int)$value, $conf['values'], true) && !in_array($value, $conf['values'], true)) {
                return [false, 'unknown value, one of: ' . implode(', ', $conf['values'])];
            }
            return [true, is_numeric($value) ? (int)$value : $value];

        case 'int':
        case 'rate':
            if (trim((string)$value) === '') {
                return [true, $conf['default'] ?? 0];
            }
            if (!is_numeric($value) || (int)$value != $value) {
                return [false, 'must be a whole number'];
            }
            $value = (int)$value;
            $min = $conf['min'] ?? 0;
            $max = $conf['max'] ?? 2000000000;
            if ($value < $min || $value > $max) {
                return [false, "must be between $min and $max"];
            }
            return [true, $value];

        case 'port':
            if (trim((string)$value) === '') {
                return [true, 1];
            }
            if (!is_numeric($value) || (int)$value < 1 || (int)$value > 65535) {
                return [false, 'port must be 1..65535'];
            }
            return [true, (int)$value];

        case 'portlist':
            // the ports of a filter are stored with a dash, 80:90 comes to 80-90
            $value = str_replace(':', '-', trim((string)$value));
            if ($value === '') {
                return [true, ''];
            }
            if (!preg_match('/^[0-9\-]{1,20}$/', $value)) {
                return [false, 'ports must look like 80 or 80-90 or 80:25'];
            }
            return [true, $value];

        case 'mac':
            $value = trim((string)$value);
            if ($value === '') {
                return [true, ''];
            }
            if (!checkValidMac($value)) {
                return [false, 'malformed mac address'];
            }
            return [true, mac_dotted($value)];

        case 'ip':
            $value = normalizeIpAddress(trim((string)$value));
            if ($value === '' || !filter_var($value, FILTER_VALIDATE_IP)) {
                return [false, 'malformed ip address'];
            }
            return [true, $value];

        case 'ref':
            if (trim((string)$value) === '' || (int)$value <= 0) {
                if (!empty($conf['required'])) {
                    return [false, 'must reference an existing ' . $conf['table']];
                }
                return [true, 0];
            }
            if (!is_numeric($value)) {
                return [false, 'reference must be an identifier'];
            }
            $found = get_record_sql($db, "SELECT id FROM " . $conf['table'] . " WHERE id = ?", [(int)$value]);
            if (empty($found)) {
                return [false, 'no such record in ' . $conf['table']];
            }
            return [true, (int)$value];

        default:
            $value = trim(preg_replace('/[[:cntrl:]]/', '', (string)$value));
            $len = $conf['len'] ?? 250;
            if (function_exists('mb_strlen') ? mb_strlen($value) > $len : strlen($value) > $len) {
                return [false, "longer than $len characters"];
            }
            return [true, $value];
    }
}

// Fields of the request which the entity allows for this operation
function api_collect($db, $spec, $data, $mode, $id = 0)
{
    $values = [];
    $errors = [];

    $create = ($mode === 'create');

    // the request can not carry a column which the entity does not have: the caller
    // must learn about it instead of seeing a record without the expected value. a name of the
    // query is asked about the same way as a name of the json body, so that nothing which the
    // caller sent is dropped in silence
    $asked_names = array_keys(is_array($data) ? $data : []);
    foreach (array_merge(array_keys($_GET ?: []), array_keys($_POST ?: [])) as $param) {
        if (api_service_name($param)) {
            continue;
        }
        $asked_names[] = $param;
    }

    foreach ($asked_names as $asked) {
        $asked = strtolower(trim((string)$asked));
        if ($asked === '' || $asked === 'id' || array_key_exists($asked, $spec['fields'])) {
            continue;
        }
        $moved = $spec['moved'][$asked] ?? null;
        $errors[$asked] = $moved === null
            ? 'unknown field of a ' . $spec['entity']
            : 'the field belongs to ' . $moved . ' now, edit that record instead';
    }

    foreach ($spec['fields'] as $field => $conf) {
        if ($create && empty($conf['create'])) {
            continue;
        }
        if (!$create && empty($conf['edit'])) {
            continue;
        }

        $raw = api_param($field, $data);
        if ($raw === null) {
            if ($create && in_array($field, $spec['required'] ?? [], true)) {
                $errors[$field] = 'required field';
            }
            continue;
        }

        list($ok, $value) = api_check_field($db, $spec, $field, $raw);
        if (!$ok) {
            $errors[$field] = $value;
            continue;
        }
        $values[$field] = $value;
    }

    if ($create) {
        foreach ($spec['required'] ?? [] as $field) {
            if (!array_key_exists($field, $values)) {
                $errors[$field] = $errors[$field] ?? 'required field';
            }
        }
    }

    if ($errors) {
        return [null, $errors];
    }

    // a unique key of the entity: the columns which the request brings in
    foreach ($spec['unique'] ?? [] as $key) {
        $conditions = [];
        $params = [];
        $shown = [];
        $extra = '';
        $whole = true;

        foreach ($key as $item) {
            if (strpos($item, ' ') !== false) {
                // an extra condition of the key, for example: deleted = 0
                $extra = $item;
                continue;
            }
            if (!array_key_exists($item, $values)) {
                $whole = false;
                break;
            }
            $conditions[] = "$item = ?";
            $params[] = $values[$item];
            $shown[$item] = $values[$item];
        }

        if (!$whole || empty($conditions)) {
            continue;
        }

        $sql = 'SELECT id FROM ' . $spec['table'] . ' WHERE ' . implode(' AND ', $conditions);
        if ($extra !== '') {
            $sql .= ' AND ' . $extra;
        }
        if ($id > 0) {
            $sql .= ' AND id <> ?';
            $params[] = (int)$id;
        }

        $clash = get_record_sql($db, $sql, $params);
        if (!empty($clash)) {
            return [null, ['id' => 'record ' . json_encode($shown, JSON_UNESCAPED_UNICODE)
                . ' already exists with id ' . (int)$clash['id']]];
        }
    }

    return [$values, []];
}

// === ХУКИ СУЩНОСТЕЙ ==========================================
// a filter refers to a subnet or to a list of addresses, the web form keeps them apart
function api_before_filter($db, $spec, $values, $mode, $id = 0)
{
    $ipset = (int)($values['ipset_id'] ?? 0);
    $dst = trim((string)($values['dst'] ?? ''));

    if ($dst === '' && $ipset === 0) {
        return [null, ['dst' => 'a subnet, an address or a list of addresses is required']];
    }

    if ($ipset > 0) {
        // the reference is checked by the column rules, the filter drops its own dst
        $values['dst'] = '';
        return [$values, []];
    }

    foreach (preg_split('/[\r\n,;]+/', $dst, -1, PREG_SPLIT_NO_EMPTY) as $line) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }
        if (!api_valid_cidr($line)) {
            return [null, ['dst' => "malformed address or subnet: $line"]];
        }
    }

    return [$values, []];
}

// an address of a filter is a bare ip (v4 or v6) or an ip with a netmask behind it
function api_valid_cidr($line)
{
    if (strpos($line, '/') === false) {
        return filter_var($line, FILTER_VALIDATE_IP) !== false;
    }

    list($address, $mask) = array_pad(explode('/', $line, 2), 2, '');
    if ($address === '' || !ctype_digit($mask)) {
        return false;
    }

    $limit = filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false ? 128 : 32;
    if (filter_var($address, FILTER_VALIDATE_IP) === false) {
        return false;
    }

    return (int)$mask >= 0 && (int)$mask <= $limit;
}

// the values of a new ip record follow the record of its owner, as the form of the
// address of the user does it
function api_before_address($db, $spec, $values, $mode, $id = 0)
{
    if (!empty($values['user_id'])) {
        $user = get_record_sql($db, 'SELECT * FROM user_list WHERE id = ? AND deleted = 0', [(int)$values['user_id']]);
        if (empty($user)) {
            return [null, ['user_id' => 'no such subscriber']];
        }
        if (empty($values['ou_id'])) {
            $values['ou_id'] = (int)$user['ou_id'];
        }
    }

    if ($mode === 'create' && !empty($values['ip']) && !is_our_network($db, $values['ip'])) {
        return [null, ['ip' => 'the address belongs to no subnet of the installation']];
    }

    if (!empty($values['ip'])) {
        $values['ip_int'] = sprintf('%u', ip2long($values['ip']));
    }

    return [$values, []];
}

// an ip record tells the dhcp and the switch rules about itself by itself, the sql
// layer raises those flags on any insert and update of the address

// a subscriber inherits the state, the shaper and the group of filters of his
// organizational unit, as the interface does it, but only those settings which the
// request did not name: the api caller is allowed to choose them for the new record
function api_before_user($db, $spec, $values, $mode, $id = 0)
{
    if ($mode !== 'create' || empty($values['ou_id'])) {
        return [$values, []];
    }

    $ou = get_record_sql($db, 'SELECT * FROM ou WHERE id = ?', [(int)$values['ou_id']]);
    if (empty($ou)) {
        return [null, ['ou_id' => 'no such organizational unit']];
    }

    foreach (['enabled', 'queue_id', 'filter_group_id'] as $field) {
        if (!array_key_exists($field, $values)) {
            $values[$field] = (int)$ou[$field];
        }
    }

    return [$values, []];
}

// the vendor of the device comes from its model, the ip of the device gets its int form
function api_before_device($db, $spec, $values, $mode, $id = 0)
{
    if (!empty($values['device_model_id'])) {
        $model = get_record_sql($db, 'SELECT vendor_id FROM device_models WHERE id = ?', [(int)$values['device_model_id']]);
        if (empty($model)) {
            return [null, ['device_model_id' => 'no such model of the devices']];
        }
        $values['vendor_id'] = (int)$model['vendor_id'];
    }

    if (!empty($values['ip'])) {
        $values['ip_int'] = sprintf('%u', ip2long($values['ip']));
    }

    if ($mode === 'create' && !array_key_exists('port_count', $values)) {
        $values['port_count'] = 0;
    }

    return [$values, []];
}

// the ports of the device follow its port_count, the count is the switch topology of
// the web form, so a new port is added and a removed one takes its connections with it
function api_after_device($db, $id, $port_count = null)
{
    if ($port_count === null) {
        return;
    }

    $count = (int)get_single_field($db, 'SELECT COUNT(id) FROM device_ports WHERE device_id = ?', [(int)$id]);
    $wanted = (int)$port_count;

    if ($wanted > $count) {
        for ($port = $count + 1; $port <= $wanted; $port++) {
            insert_record($db, 'device_ports', ['device_id' => (int)$id, 'snmp_index' => $port, 'port' => $port]);
        }
        return;
    }

    for ($port = $count; $port > $wanted; $port--) {
        $port_id = get_id_record($db, 'device_ports', 'device_id = ? AND port = ?', [(int)$id, $port]);
        if ($port_id) {
            delete_records($db, 'connections', 'port_id = ?', [(int)$port_id]);
            delete_record($db, 'device_ports', 'id = ?', [(int)$port_id]);
        }
    }
}

// a filter joins the end of the list of its group when the order is not asked
function api_before_rule($db, $spec, $values, $mode, $id = 0)
{
    if ($mode === 'create' && !array_key_exists('rule_order', $values)) {
        $last = (int)get_single_field($db, 'SELECT MAX(rule_order) FROM group_filters WHERE group_id = ?',
            [(int)$values['group_id']]);
        $values['rule_order'] = $last + 1;
    }

    return [$values, []];
}

// the filters of a group reach the subscribers when the background scripts rebuild the
// lists of the ipset rules, the interface does the same and waits for its cron
// the columns which an api answer never shows
function api_hide($spec, $row)
{
    if (!is_array($row) || empty($spec['hidden'])) {
        return $row;
    }
    foreach ($spec['hidden'] as $column) {
        unset($row[$column]);
    }
    return $row;
}

// === МЕТОДЫ ==============================================
function api_answer($data, $code = 200)
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    do_exit();
}

function api_bad_request($errors)
{
    api_answer(['error' => 'Invalid parameters', 'fields' => $errors], 400);
}

// the rows of an entity: search, exact conditions, not only alive ones, paging
function api_search($db, $spec)
{
    $conditions = [];
    $params = [];

    foreach ($spec['exact'] as $field) {
        $value = getParam($field, null, null);
        if ($value === null || $value === '') {
            continue;
        }
        // a subscriber of the group: the zero of an optional reference is a value too
        if (!preg_match('/^[a-z_][a-z0-9_]*$/i', $field)) {
            continue;
        }
        $conditions[] = "$field = ?";
        $params[] = (is_numeric($value) ? (int)$value : $value);
    }

    if ($spec['soft_delete'] && getParam('with_deleted', null, null) === null) {
        $conditions[] = 'deleted = 0';
    }

    // a condition over a record of another table, for example a subscriber by his ip
    foreach ($spec['sub'] ?? [] as $field => $condition) {
        $value = getParam($field, null, null);
        if ($value === null || $value === '') {
            continue;
        }
        if ($field === 'ip' || $field === 'ip_int') {
            $address = normalizeIpAddress(trim((string)$value));
            $value = ($field === 'ip') ? sprintf('%u', ip2long($address)) : (int)$value;
        }
        if ($field === 'mac') {
            $value = mac_dotted(trim((string)$value));
        }
        $conditions[] = $condition;
        $params[] = $value;
    }

    // search: a substring of any column which the entity marks as searchable
    $search = trim((string)getParam('search', null, getParam('q', null, '')));
    if ($search !== '' && !empty($spec['search'])) {
        $like = [];
        foreach ($spec['search'] as $field) {
            $like[] = "$field LIKE ?";
            $params[] = '%' . $search . '%';
        }
        $conditions[] = '(' . implode(' OR ', $like) . ')';
    }

    $limit = (int)getParam('limit', null, 100);
    $limit = ($limit > 0 && $limit <= 1000) ? $limit : 100;
    $offset = max(0, (int)getParam('offset', null, 0));

    $sql = 'SELECT * FROM ' . $spec['table'];
    if ($conditions) {
        $sql .= ' WHERE ' . implode(' AND ', $conditions);
    }

    $total = (int)get_single_field($db, 'SELECT COUNT(id) FROM ' . $spec['table']
        . ($conditions ? ' WHERE ' . implode(' AND ', $conditions) : ''), $params);

    $sql .= ' ORDER BY ' . $spec['order'] . ' LIMIT ' . $limit . ' OFFSET ' . $offset;
    $rows = get_records_sql($db, $sql, $params);

    api_answer([
        'entity'  => $spec['entity'],
        'total'   => $total,
        'limit'   => $limit,
        'offset'  => $offset,
        // the secrets of the entity never enter the list of records
        'records' => array_map(function ($row) use ($spec) {
            return api_hide($spec, $row);
        }, ($rows ?: [])),
    ]);
}

// one row of the entity by its id or by a unique key of the search
function api_record($db, $spec)
{
    $id = (int)getParam('id', null, 0);
    if ($id > 0) {
        $row = get_record_sql($db, 'SELECT * FROM ' . $spec['table'] . ' WHERE id = ?', [$id]);
        if (empty($row)) {
            api_answer(['error' => 'Not found', 'entity' => $spec['entity'], 'id' => $id], 404);
        }
        $answer = ['entity' => $spec['entity'], 'record' => api_hide($spec, $row)];
        $related = api_related($db, $spec, $row);
        if ($related) {
            $answer['with'] = $related;
        }
        api_answer($answer);
    }

    foreach ($spec['unique'] ?? [] as $key) {
        $conditions = [];
        $params = [];
        foreach ($key as $item) {
            if (strpos($item, ' ') !== false) {
                $conditions[] = $item;
                continue;
            }
            $value = getParam($item, null, null);
            if ($value === null || $value === '') {
                continue 2;
            }
            $conditions[] = "$item = ?";
            $params[] = $value;
        }
        if (!$conditions) {
            continue;
        }
        $row = get_record_sql($db, 'SELECT * FROM ' . $spec['table'] . ' WHERE ' . implode(' AND ', $conditions), $params);
        if (!empty($row)) {
            $answer = ['entity' => $spec['entity'], 'record' => api_hide($spec, $row)];
            $related = api_related($db, $spec, $row);
            if ($related) {
                $answer['with'] = $related;
            }
            api_answer($answer);
        }
    }

    api_answer(['error' => 'Not found', 'entity' => $spec['entity']], 404);
}

function api_create($db, $spec, $data)
{
    list($values, $errors) = api_collect($db, $spec, $data, 'create');
    if ($errors) {
        api_bad_request($errors);
    }

    if (!empty($spec['before'])) {
        list($values, $errors) = $spec['before']($db, $spec, $values, 'create');
        if ($errors) {
            api_bad_request($errors);
        }
    }

    $id = insert_record($db, $spec['table'], $values);
    if (!$id) {
        LOG_ERROR($db, 'API: can not create a record of ' . $spec['entity'] . ' from '
            . json_encode($values, JSON_UNESCAPED_UNICODE));
        api_answer(['error' => 'Create failed'], 500);
    }

    api_after_create_hooks($db, $spec, (int)$id, $values);

    LOG_INFO($db, 'API: created ' . $spec['entity'] . ' ' . (int)$id);
    api_answer(['entity' => $spec['entity'], 'status' => 'created', 'id' => (int)$id], 201);
}

// the work which the web form does after the record itself
function api_after_create_hooks($db, $spec, $id, $values)
{
    switch ($spec['entity']) {
        case 'device':
            api_after_device($db, $id, $values['port_count'] ?? null);
            break;
    }
}

function api_update($db, $spec, $data)
{
    $id = (int)getParam('id', null, 0);
    if ($id <= 0 && isset($data['id'])) {
        $id = (int)$data['id'];
    }
    if ($id <= 0) {
        api_bad_request(['id' => 'id of the record is required']);
    }

    $old = get_record_sql($db, 'SELECT * FROM ' . $spec['table'] . ' WHERE id = ?', [$id]);
    if (empty($old)) {
        api_answer(['error' => 'Not found', 'entity' => $spec['entity'], 'id' => $id], 404);
    }

    list($values, $errors) = api_collect($db, $spec, $data, 'update', $id);
    if ($errors) {
        api_bad_request($errors);
    }
    if (!$values) {
        api_bad_request(['data' => 'nothing to update']);
    }

    // the operator of eye is allowed to change a part of the columns of a record only, so the
    // rights are weighed over the fields which this request really touches
    foreach (array_keys($values) as $field) {
        if (!allow_update($db, $spec['table'], 'update', $field)) {
            api_answer(['error' => 'Not enough rights to change the field ' . $field
                . ' of a ' . $spec['entity'] . ' for this customer',
                'entity' => $spec['entity'], 'field' => $field], 403);
        }
    }

    if (!empty($spec['before'])) {
        list($values, $errors) = $spec['before']($db, $spec, $values, 'update', $id);
        if ($errors) {
            api_bad_request($errors);
        }
    }

    if (!update_record($db, $spec['table'], 'id = ?', $values, [$id])) {
        LOG_ERROR($db, 'API: can not update ' . $spec['entity'] . ' ' . $id);
        api_answer(['error' => 'Update failed'], 500);
    }

    api_after_update_hooks($db, $spec, $id, $values, $old);

    LOG_INFO($db, 'API: updated ' . $spec['entity'] . ' ' . $id);
    api_answer(['entity' => $spec['entity'], 'status' => 'updated', 'id' => $id]);
}

function api_after_update_hooks($db, $spec, $id, $values, $old)
{
    switch ($spec['entity']) {
        case 'device':
            if (array_key_exists('port_count', $values)) {
                api_after_device($db, $id, (int)$values['port_count']);
            }
            // the templates of the host of zabbix follow the model of the device, so the
            // api relinks them as the form of the device does: the answer of the
            // monitoring system stays in the journal, while the answer of the api names
            // the record of the base which it changed
            if (array_key_exists('device_model_id', $values)
                && (int)($old['device_model_id'] ?? 0) !== (int)$values['device_model_id']) {
                zabbix_record_report_message($db, 'netdev', $id, '', ZABBIX_TEMPLATES_RELINK);
            }
            break;
    }
}

// the references which hold a record in the base: where a group of filters, a shaper
// or a filter is used, and how the reference is released by the force of the request
function api_links($db, $spec, $id)
{
    $sets = [
        // a subscriber and his address return to the settings of their organizational
        // unit, the unit itself returns to the default settings of the installation
        'queue' => [
            ['table' => 'user_list', 'column' => 'queue_id', 'value' => 0, 'label' => 'subscribers'],
            ['table' => 'user_auth', 'column' => 'queue_id', 'value' => 0, 'label' => 'ip records'],
            ['table' => 'ou',        'column' => 'queue_id', 'value' => 0, 'label' => 'organizational units'],
        ],
        'filtergroup' => [
            ['table' => 'user_list',   'column' => 'filter_group_id', 'value' => 0,     'label' => 'subscribers'],
            ['table' => 'user_auth',   'column' => 'filter_group_id', 'value' => 0,     'label' => 'ip records'],
            ['table' => 'ou',          'column' => 'filter_group_id', 'value' => 0,     'label' => 'organizational units'],
            ['table' => 'group_filters', 'column' => 'group_id',      'value' => null,  'label' => 'rules of the groups'],
        ],
        'filter' => [
            ['table' => 'group_filters', 'column' => 'filter_id',     'value' => null,  'label' => 'groups of filters'],
        ],
    ];

    $links = [];
    foreach ($sets[$spec['entity']] ?? [] as $link) {
        $count = (int)get_single_field($db,
            'SELECT COUNT(id) FROM ' . $link['table'] . ' WHERE ' . $link['column'] . ' = ?', [$id]);
        if ($count > 0) {
            $links[] = array_merge($link, ['count' => $count]);
        }
    }

    return $links;
}

// releasing of a reference: the column of the record is set to the value of the
// settings, a row of a relation table is removed with it
function api_release($db, $link, $id)
{
    if ($link['value'] === null) {
        delete_records($db, $link['table'], $link['column'] . ' = ?', [$id]);
        return;
    }

    update_records($db, $link['table'], $link['column'] . ' = ?', [$link['column'] => $link['value']], [$id]);
}

// removing a record: the same work as the web interface does, the ports and the
// connections of a device go away, an address is taken with its aliases.
// a record which is in use is not removed in silence: the answer names the holders,
// and force=1 releases them first
function api_delete($db, $spec, $data = [])
{
    $id = (int)api_param('id', $data);
    if ($id <= 0) {
        $id = (int)getParam('id', null, 0);
    }
    if ($id <= 0) {
        api_bad_request(['id' => 'id of the record is required']);
    }

    $old = get_record_sql($db, 'SELECT * FROM ' . $spec['table'] . ' WHERE id = ?', [$id]);
    if (empty($old)) {
        api_answer(['error' => 'Not found', 'entity' => $spec['entity'], 'id' => $id], 404);
    }

    $force        = filter_var(api_param('force', $data) ?? getParam('force', null, null), FILTER_VALIDATE_BOOLEAN);

    switch ($spec['entity']) {
        case 'group':
            // a group of the subscribers is a part of the settings of the installation,
            // no force can take it away
            if ((int)$old['default_users'] === 1 || (int)$old['default_hotspot'] === 1) {
                api_answer(['error' => 'A default organizational unit can not be removed', 'entity' => 'group', 'id' => $id], 400);
            }
            break;
    }

    // the records which hang on this one and leave it together with the cascade of the
    // web functions: they are not released but taken away, so the request must say so
    $holders = [];
    $cascades = [
        'group' => [['sql' => 'SELECT COUNT(id) FROM user_list WHERE deleted = 0 AND ou_id = ?',
                     'label' => 'subscribers'],
                    ['sql' => 'SELECT COUNT(id) FROM ou WHERE parent_id = ?',
                     'label' => 'groups under it']],
        'user' => [['sql' => 'SELECT COUNT(id) FROM user_auth WHERE deleted = 0 AND user_id = ?',
                    'label' => 'ip records'],
                   ['sql' => 'SELECT COUNT(id) FROM devices WHERE deleted = 0 AND user_id = ?',
                    'label' => 'devices']],
        'device' => [['sql' => 'SELECT COUNT(id) FROM device_ports WHERE device_id = ? AND target_port_id > 0',
                      'label' => 'units under its ports'],
                     ['sql' => 'SELECT COUNT(id) FROM device_ports WHERE device_id = ? AND auth_id > 0',
                      'label' => 'addresses on its ports']],
    ];

    foreach ($cascades[$spec['entity']] ?? [] as $cascade) {
        $count = (int)get_single_field($db, $cascade['sql'], [$id]);
        if ($count > 0) {
            $holders[$cascade['label']] = $count;
        }
    }

    $links = api_links($db, $spec, $id);
    foreach ($links as $link) {
        $holders[$link['label']] = $link['count'];
    }

    if ($holders && !$force) {
        api_answer(['error' => 'The record is in use, repeat with force=1 to release the references and remove it',
                   'entity' => $spec['entity'], 'id' => $id, 'in_use_by' => $holders], 400);
    }

    $released = [];
    foreach ($links as $link) {
        api_release($db, $link, $id);
        $released[$link['label']] = $link['count'];
    }

    switch ($spec['entity']) {
        case 'device':
            // the host of the device is switched off in the monitoring system before the
            // record disappears: no script would find the device afterwards
            if (is_zabbix_enabled() && ((int)$old['monitoring'] === 1 || (int)$old['monitoring_old'] === 1)) {
                // the same rule as in sync_zabbix.pl: a host which the monitoring system
                // still polls is disabled there, eye does not delete the hosts itself
                $zbx_state = zabbix_record_state($db, 'netdev', $id);
                if ($zbx_state !== null) {
                    zabbix_record_switch_off($db, $zbx_state);
                    LOG_INFO($db, 'API: the host of the device ' . $id . ' is switched off in zabbix');
                }
            }
            delete_device($db, $id);
            break;
        case 'user':
            delete_user($db, $id);
            break;
        case 'address':
            delete_user_auth($db, $id);
            break;
        case 'group':
            delete_records($db, 'ou_zabbix_groups', 'ou_id = ?', [$id]);
            // the units under the removed one do not stay without a parent
            update_records($db, 'ou', 'parent_id = ?', ['parent_id' => (int)$old['parent_id']], [$id]);
            delete_group($db, $id);
            if (get_record_sql($db, 'SELECT id FROM ou WHERE id = ?', [$id])) {
                api_answer(['error' => 'The group is still in the database, it has not been removed', 'entity' => 'group', 'id' => $id], 500);
            }
            break;
        default:
            delete_record($db, $spec['table'], 'id = ?', [$id]);
            if (get_record_sql($db, 'SELECT id FROM ' . $spec['table'] . ' WHERE id = ?', [$id])
                && empty($spec['soft_delete'])) {
                api_answer(['error' => 'The record is still in the database, it has not been removed',
                            'entity' => $spec['entity'], 'id' => $id], 500);
            }
            break;
    }

    LOG_INFO($db, 'API: removed ' . $spec['entity'] . ' ' . $id
        . ($released ? ', released ' . json_encode($released) : ''));
    $answer = ['entity' => $spec['entity'], 'status' => 'removed', 'id' => $id];
    if ($released) {
        $answer['released'] = $released;
    }
    // the subscribers of a removed unit do not stay orphaned: delete_group() moves
    // them and their records to the default unit, the answer names the destination
    if ($spec['entity'] === 'group' && !empty($holders['subscribers'])) {
        $answer['subscribers'] = (int)$holders['subscribers'];
        $answer['subscribers_moved_to'] = (int)get_const('default_user_ou_id');
    }
    api_answer($answer);
}

// === ТОЧКА ВХОДА ==========================================
// The method names of the entity crud:
//   get=<entity>            one record by id or by a unique key
//   get=<entity> + params   search of the records
//   send=add_<entity>       creation of a record, the data comes from a json body or
//                           from the request parameters
//   send=update_<entity>    editing of the allowed columns
//   send=del_<entity>       removal of a record with the same work as the interface
function api_crud($db, $action, $data)
{
    $entity = null;
    $verb = null;

    foreach (['get_', 'send_add_', 'send_update_', 'send_del_'] as $prefix) {
        if (strpos($action, $prefix) === 0) {
            $verb = $prefix;
            $entity = api_entity_find(substr($action, strlen($prefix)));
            break;
        }
    }

    if ($verb === null || $entity === null) {
        return false;
    }

    // the methods with these names answer by their own code since long, a customer of
    // the api expects exactly their shape of an answer
    if (in_array($action, ['get_user', 'get_user_auth'], true)) {
        return false;
    }

    // these two methods of the api exist since long, they answer by their own code
    if ($action === 'get_user' || $action === 'get_user_auth') {
        return false;
    }

    log_api_call($action, ['entity' => $entity['entity'], 'id' => getParam('id', null, null)]);

    // the same rights as the interface and the database layer use: the request was already
    // given the session of the owner of the api key by IsSilentAuthenticated()
    // the name of the action is what the rights of the interface are checked by: a verb of
    // the api method with its prefixes inside would never match those rules
    $verb_actions = ['send_add_' => 'add', 'send_update_' => 'update', 'send_del_' => 'del'];
    if ($verb !== 'get_' && !allow_update($db, $entity['table'], $verb_actions[$verb] ?? $verb)) {
        api_answer(['error' => 'Not enough rights to change a record of ' . $entity['entity']
            . ' for this customer', 'entity' => $entity['entity']], 403);
    }

    if ($verb === 'get_') {
        if (getParam('id', null, null) !== null || api_has_unique($entity)) {
            api_record($db, $entity);
        }
        api_search($db, $entity);
    }

    $request = is_array($data) ? $data : [];
    if ($verb === 'send_add_') {
        api_create($db, $entity, $request);
    }
    if ($verb === 'send_update_') {
        api_update($db, $entity, $request);
    }
    if ($verb === 'send_del_') {
        api_delete($db, $entity, is_array($data) ? $data : []);
    }

    return false;
}

// a single record can bring the records which hang on it, for example the addresses
// of a subscriber or the device of a subscriber
function api_related($db, $spec, $row)
{
    $want = trim((string)getParam('with', null, ''));
    if ($want === '' || empty($spec['with'])) {
        return [];
    }

    $names = array_map('trim', explode(',', strtolower($want)));
    $related = [];

    foreach ($names as $name) {
        if ($name === 'all') {
            $names = array_merge($names, array_keys($spec['with']));
            break;
        }
    }

    foreach ($spec['with'] as $key => $link) {
        if (!in_array($key, $names, true)) {
            continue;
        }
        $child = api_entity_find($link['entity']);
        if ($child === null) {
            continue;
        }
        $rows = get_records_sql($db,
            'SELECT * FROM ' . $child['table'] . ' WHERE ' . $link['field'] . ' = ? ORDER BY ' . $child['order'],
            [(int)$row[$link['of']]]);
        if ($child['soft_delete']) {
            $rows = array_values(array_filter($rows ?: [], function ($item) {
                return (int)$item['deleted'] === 0;
            }));
        }
        $related[$key] = array_map(function ($row) use ($child) {
            return api_hide($child, $row);
        }, ($rows ?: []));
    }

    return $related;
}

// a unique key of the entity counts as asked for only when every its column is in the
// request: a rule of a filter group is found by the group and the filter together
function api_has_unique($spec)
{
    foreach ($spec['unique'] ?? [] as $key) {
        $whole = true;
        $asked = 0;
        foreach ($key as $item) {
            if (strpos($item, ' ') !== false) {
                continue;
            }
            $value = getParam($item, null, null);
            if ($value === null || $value === '') {
                $whole = false;
                break;
            }
            $asked++;
        }
        if ($whole && $asked > 0) {
            return true;
        }
    }

    return false;
}

// the body of a request: json of a post, or the parameters of the query
function api_request_data()
{
    $raw = api_param('data', []);
    if (is_string($raw) && $raw !== '') {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            return $decoded;
        }
    }

    $content = file_get_contents('php://input');
    if (is_string($content) && $content !== '') {
        $decoded = json_decode($content, true);
        if (is_array($decoded)) {
            return $decoded;
        }
    }

    return [];
}
