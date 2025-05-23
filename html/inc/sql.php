<?php
if (! defined("CONFIG")) die("Not defined");

if (! defined("SQL")) { die("Not defined"); }


function new_connection ($db_host, $db_user, $db_password, $db_name)
{
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$result = mysqli_connect($db_host,$db_user,$db_password,$db_name);

if (! $result) {
    echo "Error connect to MYSQL " . PHP_EOL;
    echo "Errno: " . mysqli_connect_errno() . PHP_EOL;
    echo "Error message: " . mysqli_connect_error() . PHP_EOL;
    exit();
    }

/* enable utf8 */
if (!mysqli_set_charset($result,'utf8mb4')) {
    printf("Error loading utf8: %s\n", mysqli_error($result));
    exit();
    }

//mysqli_options($result, MYSQLI_OPT_INT_AND_FLOAT_NATIVE, 1);

return $result;
}

function run_sql($db, $query)
{
    if (preg_match('/^\s*(UPDATE|DELETE)/i', $query)) {
        unset($matches);
        preg_match('/FROM\s+(.*)\s+/i', $query, $matches);
        if (!empty($matches[1])) {
            if (!allow_update($matches[1], 'del')) {
                LOG_DEBUG($db, "Access denied: $query ");
                return;
            }
        }
        unset($matches);
        preg_match('/INSERT\s+INTO\s+(.*)\s+/i', $query, $matches);
        if (!empty($matches[1])) {
            if (!allow_update($matches[1], 'add')) {
                LOG_DEBUG($db, "Access denied: $query ");
                return;
            }
        }
        unset($matches);
        preg_match('/UPDATE\s+(.*)\s+/i', $query, $matches);
        if (!empty($matches[1])) {
            if (!allow_update($matches[1], 'update')) {
                LOG_DEBUG($db, "Access denied: $query ");
                return;
            }
        }
        unset($matches);
    }
    $sql_result = mysqli_query($db, $query);
    if (!$sql_result) {
        LOG_ERROR($db, "At simple SQL: $query :" . mysqli_error($db));
        return;
    }
    return $sql_result;
}

function get_count_records($db, $table, $filter)
{
    if (!empty($filter)) {
        $filter = 'where ' . $filter;
    }
    $t_count = mysqli_query($db, "SELECT count(*) FROM $table $filter");
    list($count) = mysqli_fetch_array($t_count);
    if (!isset($count)) {
        $count = 0;
    }
    return $count;
}

function get_id_record($db, $table, $filter)
{
    if (isset($filter)) {
        $filter = 'WHERE ' . $filter;
    }
    $t_record = mysqli_query($db, "SELECT id FROM $table $filter limit 1");
    list($id) = mysqli_fetch_array($t_record);
    return $id;
}

function set_changed($db, $id)
{
    $auth['changed'] = 1;
    update_record($db, "User_auth", "id=" . $id, $auth);
}

//action: add,update,del
function allow_update($table, $action = 'update', $field = '')
{
    //always allow modification for tables
    if (preg_match('/(variables|dns_cache|worklog|sessions)/i', $table)) {
        return 1;
    }

    if (isset($_SESSION['login'])) {
        $work_user = $_SESSION['login'];
    }
    if (isset($_SESSION['user_id'])) {
        $work_id = $_SESSION['user_id'];
    }
    if (isset($_SESSION['acl'])) {
        $user_level = $_SESSION['acl'];
    }
    if (!isset($work_user) or !isset($work_id) or empty($user_level)) {
        return 0;
    }

    //always allow Administrator
    if ($user_level == 1) {
        return 1;
    }

    //always forbid ViewOnly
    if ($user_level == 3) {
        return 0;
    }

    //allow tables for Operator
    if (preg_match('/(dns_queue|User_auth_alias)/i', $table)) {
        return 1;
    }

    if ($action == 'update') {
        $operator_acl = [
            'User_auth' => [
                'comments' => '1',
                'dns_name' => '1',
                'dns_ptr_only' => '1',
                'firmware' => '1',
                'link_check' => '1',
                'nagios' => '1',
                'nagios_handler' => '1',
                'Wikiname' => '1'
            ],
            'User_list' => [
                'fio' => '1',
                'login' => '1',
            ],
        ];
        if (!isset($operator_acl[$table])) {
            return 0;
        }
        if (isset($operator_acl[$table]) and empty($field)) {
            return 1;
        }
        if (!isset($operator_acl[$table][$field])) {
            return 0;
        }
        if (empty($operator_acl[$table][$field]) or $operator_acl[$table][$field] == '0') {
            return 0;
        }
        return 1;
    }

    return 0;
}

function get_record_field($db, $table, $field, $filter)
{
    if (!isset($table)) {
#        LOG_ERROR($db, "Search in unknown table! Skip command.");
        return;
    }
    if (!isset($filter)) {
#        LOG_ERROR($db, "Search filter is empty! Skip command.");
        return;
    }
    if (!isset($field)) {
#        LOG_ERROR($db, "Search field is empty! Skip command.");
        return;
    }
    if (preg_match('/=$/', $filter)) {
        LOG_ERROR($db, "Search record ($table) with illegal filter $filter! Skip command.");
        return;
    }
    $old_sql = "SELECT $field FROM $table WHERE $filter LIMIT 1";
    $old_record = mysqli_query($db, $old_sql) or LOG_ERROR($db, "SQL: $old_sql :" . mysqli_error($db));
    $old = mysqli_fetch_array($old_record, MYSQLI_ASSOC);
    foreach ($old as $key => $value) {
        if (!isset($value) or $value === 'NULL') {
            $value = '';
        }
        $result[$key] = $value;
    }
    return $result[$field];
}

function get_record($db, $table, $filter)
{
    if (!isset($table)) {
#        LOG_ERROR($db, "Search in unknown table! Skip command.");
        return;
    }
    if (!isset($filter)) {
#        LOG_ERROR($db, "Search filter is empty! Skip command.");
        return;
    }
    if (preg_match('/=$/', $filter)) {
        LOG_ERROR($db, "Search record ($table) with illegal filter $filter! Skip command.");
        return;
    }
    $get_sql = "SELECT * FROM $table WHERE $filter LIMIT 1";
    $get_record = mysqli_query($db, $get_sql);
    if (!$get_record) {
        LOG_ERROR($db, "SQL: $get_sql :" . mysqli_error($db));
        return;
    }
    $fields = [];
    while ($field = mysqli_fetch_field($get_record)) {
        $f_table = $field->table;
        $f_name = $field->name;
        $fields[$f_table][$f_name] = $field;
    }
    $record = mysqli_fetch_array($get_record, MYSQLI_ASSOC);
    $result = NULL;
    if (!empty($record)) {
        foreach ($record as $key => $value) {
            if (!isset($value) or $value === 'NULL' or $value == NULL) {
                if (!empty($key) and !empty($fields[$table]) and !empty($fields[$table][$key])) {
                    if (in_array($fields[$table][$key]->type, MYSQL_FIELD_DIGIT)) {
                        $value = 0;
                    }
                    if (in_array($fields[$table][$key]->type, MYSQL_FIELD_STRING)) {
                        $value = '';
                    }
                }
            }
            if (!empty($key)) {
                $result[$key] = $value;
            }
        }
    }
    return $result;
}

function get_records($db, $table, $filter)
{
    if (!isset($table)) {
#        LOG_ERROR($db, "Search in unknown table! Skip command.");
        return;
    }
    if (isset($filter) and preg_match('/=$/', $filter)) {
        LOG_ERROR($db, "Search record ($table) with illegal filter $filter! Skip command.");
        return;
    }
    $s_filter = '';
    if (isset($filter)) {
        $s_filter = 'WHERE ' . $filter;
    }
    $get_sql = "SELECT * FROM $table $s_filter";
    $get_record = mysqli_query($db, $get_sql);
    if (!$get_record) {
        LOG_ERROR($db, "SQL: $get_sql :" . mysqli_error($db));
        return;
    }
    $fields = [];
    while ($field = mysqli_fetch_field($get_record)) {
        $f_table = $field->table;
        $f_name = $field->name;
        $fields[$f_table][$f_name] = $field;
    }
    $result = NULL;
    $index = 0;
    while ($rec = mysqli_fetch_array($get_record, MYSQLI_ASSOC)) {
        foreach ($rec as $key => $value) {
            if (!isset($value) or $value === 'NULL' or $value == NULL) {
                if (!empty($key) and !empty($fields[$table]) and !empty($fields[$table][$key])) {
                    if (in_array($fields[$table][$key]->type, MYSQL_FIELD_DIGIT)) {
                        $value = 0;
                    }
                    if (in_array($fields[$table][$key]->type, MYSQL_FIELD_STRING)) {
                        $value = '';
                    }
                }
            }
            $result[$index][$key] = $value;
        }
        $index++;
    }
    return $result;
}

function get_records_sql($db, $sql)
{
    $result = NULL;
    if (empty($sql)) {
#        LOG_ERROR($db, "Empty query! Skip command.");
        return $result;
    }
    $records = mysqli_query($db, $sql);
    if (!$records) {
        LOG_ERROR($db, "SQL: $sql :" . mysqli_error($db));
        return $result;
    }
    $fields = [];
    //we assume that fields with the same name have the same type
    while ($field = mysqli_fetch_field($records)) {
        $f_name = $field->name;
        $fields[$f_name] = $field;
    }
    $index = 0;
    while ($rec = mysqli_fetch_array($records, MYSQLI_ASSOC)) {
        foreach ($rec as $key => $value) {
            if (!isset($value) or $value === 'NULL' or $value == NULL) {
                if (!empty($key) and !empty($fields[$key])) {
                    if (in_array($fields[$key]->type, MYSQL_FIELD_DIGIT)) {
                        $value = 0;
                    }
                    if (in_array($fields[$key]->type, MYSQL_FIELD_STRING)) {
                        $value = '';
                    }
                }
            }
            if (!empty($key)) {
                $result[$index][$key] = $value;
            }
        }
        $index++;
    }
    return $result;
}

function get_record_sql($db, $sql)
{
    $result = NULL;
    if (!isset($sql)) {
#        LOG_ERROR($db, "Empty query! Skip command.");
        return $result;
    }
    $record = mysqli_query($db, $sql . " LIMIT 1");
    if (!isset($record)) {
        LOG_ERROR($db, "SQL: $sql LIMIT 1: " . mysqli_error($db));
        return $result;
    }
    $fields = [];
    //we assume that fields with the same name have the same type
    while ($field = mysqli_fetch_field($record)) {
        $f_name = $field->name;
        $fields[$f_name] = $field;
    }
    $rec = mysqli_fetch_array($record, MYSQLI_ASSOC);
    if (!empty($rec)) {
        foreach ($rec as $key => $value) {
            if (!isset($value) or $value === 'NULL' or $value == NULL) {
                if (!empty($key) and !empty($fields[$key])) {
                    if (in_array($fields[$key]->type, MYSQL_FIELD_DIGIT)) {
                        $value = 0;
                    }
                    if (in_array($fields[$key]->type, MYSQL_FIELD_STRING)) {
                        $value = '';
                    }
                }
            }
            if (!empty($key)) {
                $result[$key] = $value;
            }
        }
    }
    return $result;
}


function update_record($db, $table, $filter, $newvalue)
{
    if (!isset($table)) {
#        LOG_WARNING($db, "Change record for unknown table! Skip command.");
        return;
    }
    if (!isset($filter)) {
#        LOG_WARNING($db, "Change record ($table) with empty filter! Skip command.");
        return;
    }
    if (preg_match('/=$/', $filter)) {
        LOG_WARNING($db, "Change record ($table) with illegal filter $filter! Skip command.");
        return;
    }
    if (!isset($newvalue)) {
#        LOG_WARNING($db, "Change record ($table [ $filter ]) with empty data! Skip command.");
        return;
    }

    if (!allow_update($table, 'update')) {
        LOG_INFO($db, "Access denied: $table [ $filter ]");
        return 1;
    }

    $old_sql = "SELECT * FROM $table WHERE $filter";
    $old_record = mysqli_query($db, $old_sql) or LOG_ERROR($db, "SQL: $old_sql :" . mysqli_error($db));
    $old = mysqli_fetch_array($old_record, MYSQLI_ASSOC);

    $rec_id = NULL;
    if (!empty($old['id'])) {
        $rec_id = $old['id'];
    }

    $changed_log = '';
    $run_sql = '';
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
        if (strcmp($old[$key], $value) == 0) {
            continue;
        }
        if ($table === "User_auth") {
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
        if ($table === "User_auth_alias") {
            if (!empty($dns_fields["$key"])) {
                $dns_changed = 1;
            }
        }
        if (!preg_match('/password/i', $key)) {
            $changed_log = $changed_log . " $key => $value (old: $old[$key]),";
        }
        $run_sql = $run_sql . " `" . $key . "`='" . mysqli_real_escape_string($db, $value) . "',";
    }

    if ($table === "User_auth" and $dns_changed) {
        if (!empty($old['dns_name']) and !empty($old['ip']) and !$old['dns_ptr_only'] and !preg_match('/\.$/', $old['dns_name'])) {
            $del_dns['name_type'] = 'A';
            $del_dns['name'] = $old['dns_name'];
            $del_dns['value'] = $old['ip'];
            $del_dns['type'] = 'del';
            if (!empty($rec_id)) {
                $del_dns['auth_id'] = $rec_id;
            }
            insert_record($db, 'dns_queue', $del_dns);
        }
        if (!empty($old['dns_name']) and !empty($old['ip']) and $old['dns_ptr_only'] and !preg_match('/\.$/', $old['dns_name'])) {
            $del_dns['name_type'] = 'PTR';
            $del_dns['name'] = $old['dns_name'];
            $del_dns['value'] = $old['ip'];
            $del_dns['type'] = 'del';
            if (!empty($rec_id)) {
                $del_dns['auth_id'] = $rec_id;
            }
            insert_record($db, 'dns_queue', $del_dns);
        }

        if (!empty($newvalue['dns_name']) and !empty($newvalue['ip']) and !$newvalue['dns_ptr_only'] and !preg_match('/\.$/', $newvalue['dns_name'])) {
            $new_dns['name_type'] = 'A';
            $new_dns['name'] = $newvalue['dns_name'];
            $new_dns['value'] = $newvalue['ip'];
            $new_dns['type'] = 'add';
            if (!empty($rec_id)) {
                $new_dns['auth_id'] = $rec_id;
            }
            insert_record($db, 'dns_queue', $new_dns);
        }
        if (!empty($newvalue['dns_name']) and !empty($newvalue['ip']) and $newvalue['dns_ptr_only'] and !preg_match('/\.$/', $newvalue['dns_name'])) {
            $new_dns['name_type'] = 'PTR';
            $new_dns['name'] = $newvalue['dns_name'];
            $new_dns['value'] = $newvalue['ip'];
            $new_dns['type'] = 'add';
            if (!empty($rec_id)) {
                $new_dns['auth_id'] = $rec_id;
            }
            insert_record($db, 'dns_queue', $new_dns);
        }
    }

    if ($table === "User_auth_alias" and $dns_changed) {
        $auth_id = NULL;
        if ($old['auth_id']) {
            $auth_id = $old['auth_id'];
        }
        if (!empty($old['alias']) and !preg_match('/\.$/', $old['alias'])) {
            $del_dns['name_type'] = 'CNAME';
            $del_dns['name'] = $old['alias'];
            $del_dns['type'] = 'del';
            if (!empty($auth_id)) {
                $del_dns['auth_id'] = $auth_id;
                $del_dns['value'] = get_dns_name($db, $auth_id);
            }
            insert_record($db, 'dns_queue', $del_dns);
        }
        if (!empty($newvalue['alias'])  and !preg_match('/\.$/', $newvalue['alias'])) {
            $new_dns['name_type'] = 'CNAME';
            $new_dns['name'] = $newvalue['alias'];
            $new_dns['type'] = 'add';
            if (!empty($auth_id)) {
                $new_dns['auth_id'] = $auth_id;
                $new_dns['value'] = get_dns_name($db, $auth_id);
            }
            insert_record($db, 'dns_queue', $new_dns);
        }
    }

    if (empty($run_sql)) {
        return 1;
    }

    if ($network_changed) {
        $run_sql = $run_sql . " `changed`='1',";
    }

    if ($dhcp_changed) {
        $run_sql = $run_sql . " `dhcp_changed`='1',";
    }

    $changed_log = substr_replace($changed_log, "", -1);
    $run_sql = substr_replace($run_sql, "", -1);

    if ($table === 'User_auth') {
        $changed_time = GetNowTimeString();
        $run_sql = $run_sql . ", `changed_time`='" . $changed_time . "'";
    }

    $new_sql = "UPDATE $table SET $run_sql WHERE $filter";
    LOG_DEBUG($db, "Run sql: $new_sql");
    $sql_result = mysqli_query($db, $new_sql) or LOG_ERROR($db, "SQL: $new_sql :" . mysqli_error($db));
    if (!$sql_result) {
        LOG_ERROR($db, "UPDATE Request: $new_sql :" . mysqli_error($db));
        return;
    }
    if ($table !== "sessions") {
        LOG_VERBOSE($db, "Change table $table WHERE $filter set $changed_log");
    }
    return $sql_result;
}

function delete_record($db, $table, $filter)
{
    if (!allow_update($table, 'del')) {
#        LOG_INFO($db, "User does not have write permission");
        return;
    }
    if (!isset($table)) {
#        LOG_WARNING($db, "Delete FROM unknown table! Skip command.");
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

    $old_sql = "SELECT * FROM $table WHERE $filter";
    $old_record = mysqli_query($db, $old_sql) or LOG_ERROR($db, "SQL: $old_sql :" . mysqli_error($db));
    $old = mysqli_fetch_array($old_record, MYSQLI_ASSOC);

    $rec_id = NULL;
    if (!empty($old['id'])) {
        $rec_id = $old['id'];
    }

    $changed_log = 'record: ';
    if (!empty($old)) {
        asort($old, SORT_STRING);
        $old = array_reverse($old, 1);
        foreach ($old as $key => $value) {
            if (empty($value)) {
                continue;
            }
            if (preg_match('/action/', $key)) {
                continue;
            }
            if (preg_match('/status/', $key)) {
                continue;
            }
            if (preg_match('/time/', $key)) {
                continue;
            }
            if (preg_match('/found/', $key)) {
                continue;
            }
            $changed_log = $changed_log . " $key => $value,";
        }
    }

    $delete_it = 1;

    //never delete user ip record
    if ($table === 'User_auth') {
        $delete_it = 0;
        $changed_time = GetNowTimeString();
        $new_sql = "UPDATE $table SET deleted=1, changed=1, `changed_time`='" . $changed_time . "' WHERE $filter";
        LOG_DEBUG($db, "Run sql: $new_sql");
        $sql_result = mysqli_query($db, $new_sql) or LOG_ERROR($db, "SQL: $new_sql :" . mysqli_error($db));
        if (!$sql_result) {
            LOG_ERROR($db, "UPDATE Request (from delete): " . mysqli_error($db));
            return;
            }
        //dns - A-record
        if (!empty($old['dns_name']) and !empty($old['ip']) and !$old['dns_ptr_only']  and !preg_match('/\.$/', $old['dns_name'])) {
            $del_dns['name_type'] = 'A';
            $del_dns['name'] = $old['dns_name'];
            $del_dns['value'] = $old['ip'];
            $del_dns['type'] = 'del';
            if (!empty($rec_id)) {
                $del_dns['auth_id'] = $rec_id;
                }
            insert_record($db, 'dns_queue', $del_dns);
            }
        //ptr
        if (!empty($old['dns_name']) and !empty($old['ip']) and $old['dns_ptr_only']  and !preg_match('/\.$/', $old['dns_name'])) {
            $del_dns['name_type'] = 'PTR';
            $del_dns['name'] = $old['dns_name'];
            $del_dns['value'] = $old['ip'];
            $del_dns['type'] = 'del';
            if (!empty($rec_id)) {
                $del_dns['auth_id'] = $rec_id;
                }
            insert_record($db, 'dns_queue', $del_dns);
            }
        LOG_VERBOSE($db, "Deleted FROM table $table WHERE $filter $changed_log");
        return $changed_log;
        }

    //never delete permanent user
    if ($table === 'User_list' and $old['permanent']) { return; }

    //remove aliases
    if ($table === 'User_auth_alias') {
        //dns
        if (!empty($old['alias'])  and !preg_match('/\.$/', $old['alias'])) {
            $del_dns['name_type'] = 'CNAME';
            $del_dns['name'] = $old['alias'];
            $del_dns['value'] = '';
            $del_dns['type'] = 'del';
            if (!empty($old['auth_id'])) {
                $del_dns['auth_id'] = $old['auth_id'];
                $del_dns['value'] = get_dns_name($db, $old['auth_id']);
            }
            insert_record($db, 'dns_queue', $del_dns);
        }
    }

    if ($delete_it) {
        $new_sql = "DELETE FROM $table WHERE $filter";
        LOG_DEBUG($db, "Run sql: $new_sql");
        $sql_result = mysqli_query($db, $new_sql) or LOG_ERROR($db, "SQL: $new_sql :" . mysqli_error($db));
        if (!$sql_result) {
            LOG_ERROR($db, "DELETE Request: $new_sql : " . mysqli_error($db));
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
#        LOG_WARNING($db, "User does not have write permission");
        return;
    }
    if (!isset($table)) {
#        LOG_WARNING($db, "Create record for unknown table! Skip command.");
        return;
    }
    if (empty($newvalue)) {
#        LOG_WARNING($db, "Create record ($table) with empty data! Skip command.");
        return;
    }

    $changed_log = '';
    $field_list = '';
    $value_list = '';
    foreach ($newvalue as $key => $value) {
        if (empty($value) and $value != '0') {
            $value = '';
        }
        if (!preg_match('/password/i', $key)) {
            $changed_log = $changed_log . " $key => $value,";
        }
        $field_list = $field_list . "`" . $key . "`,";
        $value = trim($value);
        $value_list = $value_list . "'" . mysqli_real_escape_string($db, $value) . "',";
    }
    if (empty($value_list)) {
        return;
    }

    $changed_log = substr_replace($changed_log, "", -1);
    $field_list = substr_replace($field_list, "", -1);
    $value_list = substr_replace($value_list, "", -1);
    $new_sql = "insert into $table(" . $field_list . ") values(" . $value_list . ")";
    LOG_DEBUG($db, "Run sql: $new_sql");
    $sql_result = mysqli_query($db, $new_sql) or LOG_ERROR($db, "SQL: $new_sql :" . mysqli_error($db));
    if (!$sql_result) {
        LOG_ERROR($db, "INSERT Request:" . mysqli_error($db));
        return;
    }
    $last_id = mysqli_insert_id($db);
    if ($table !== "sessions") {
        LOG_VERBOSE($db, "Create record in table $table: $changed_log with id: $last_id");
    }
    if ($table === 'User_auth') {
        run_sql($db, "UPDATE User_auth SET changed=1, dhcp_changed=1 WHERE id=" . $last_id);
    }

    if ($table === 'User_auth_alias') {
        //dns
        if (!empty($newvalue['alias'])  and !preg_match('/\.$/', $newvalue['alias'])) {
            $add_dns['name_type'] = 'CNAME';
            $add_dns['name'] = $newvalue['alias'];
            $add_dns['value'] = get_dns_name($db, $newvalue['auth_id']);
            $add_dns['type'] = 'add';
            $add_dns['auth_id'] = $newvalue['auth_id'];
            insert_record($db, 'dns_queue', $add_dns);
        }
    }

    if ($table === 'User_auth') {
        //dns - A-record
        if (!empty($newvalue['dns_name']) and !empty($newvalue['ip']) and !$newvalue['dns_ptr_only']  and !preg_match('/\.$/', $newvalue['dns_name'])) {
            $add_dns['name_type'] = 'A';
            $add_dns['name'] = $newvalue['dns_name'];
            $add_dns['value'] = $newvalue['ip'];
            $add_dns['type'] = 'add';
            $add_dns['auth_id'] = $last_id;
            insert_record($db, 'dns_queue', $add_dns);
        }
        //dns - ptr
        if (!empty($newvalue['dns_name']) and !empty($newvalue['ip']) and $newvalue['dns_ptr_only'] and !preg_match('/\.$/', $newvalue['dns_name'])) {
            $add_dns['name_type'] = 'PTR';
            $add_dns['name'] = $newvalue['dns_name'];
            $add_dns['value'] = $newvalue['ip'];
            $add_dns['type'] = 'add';
            $add_dns['auth_id'] = $last_id;
            insert_record($db, 'dns_queue', $add_dns);
        }
    }

    return $last_id;
}

function dump_record($db, $table, $filter)
{
    $result = '';
    $old = get_record($db, $table, $filter);
    if (empty($old)) {
        return $result;
    }
    $result = 'record: ' . get_rec_str($old);
    return $result;
}

function get_rec_str($array)
{
    $result = '';
    foreach ($array as $key => $value) {
        $result .= "[" . $key . "]=" . $value . ", ";
    }
    $result = preg_replace('/,\s+$/', '', $result);
    return $result;
}

function get_diff_rec($db, $table, $filter, $newvalue, $only_changed)
{
    if (!isset($table)) {
        return;
    }
    if (!isset($filter)) {
        return;
    }
    if (!isset($newvalue)) {
        return;
    }

    if (!isset($only_changed)) {
        $only_changed = 0;
    }

    $old_sql = "SELECT * FROM $table WHERE $filter";
    $old_record = mysqli_query($db, $old_sql) or LOG_ERROR($db, "SQL: $old_sql :" . mysqli_error($db));
    $old = mysqli_fetch_array($old_record, MYSQLI_ASSOC);
    $changed_log = "\r\n";
    foreach ($newvalue as $key => $value) {
        if (strcmp($old[$key], $value) !== 0) {
            $changed_log = $changed_log . " $key => cur: $value old: $old[$key],\r\n";
        }
    }
    $old_record = '';
    if (!$only_changed) {
        $old_record = "\r\n Has not changed:\r\n";
        foreach ($old as $key => $value) {
            if (!empty($newvalue[$key])) {
                $old_record = $old_record . " $key = $value,\r\n";
            }
        }
        $old_record = substr_replace($old_record, "", -3);
    }
    // print $changed_log;
    return $changed_log . $old_record;
}

function delete_user_auth($db, $id)
{
//remove aliases
$t_User_auth_alias = get_records($db,'User_auth_alias',"auth_id=$id ORDER BY alias");
if (!empty($t_User_auth_alias)) {
    foreach ( $t_User_auth_alias as $row ) {
        LOG_INFO($db, "Remove alias id: ".$row['id']." for auth_id: $id :: ".dump_record($db,'User_auth_alias','id='.$row['id']));
        delete_record($db,'User_auth_alias','id='.$row['id']); 
        }
    }
//remove connections
run_sql($db, 'DELETE FROM connections WHERE auth_id=' . $id);
//remove user auth record
LOG_INFO($db, "Removed user auth_id: $id :: ".dump_record($db,'User_auth','id='.$id));
$changes = delete_record($db, "User_auth", "id=" . $id);
return $changes;
}

function delete_user($db,$id)
{
//remove user record
$changes = delete_record($db, "User_list", "id=" . $id);
//if fail - exit
if (!isset($changes) or empty($changes)) { return; }
//remove auth records
$t_User_auth = get_records($db,'User_auth',"user_id=$id");
if (!empty($t_User_auth)) {
    foreach ( $t_User_auth as $row ) { delete_user_auth($db,$row['id']); }
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

$db_link = new_connection(DB_HOST, DB_USER, DB_PASS, DB_NAME);

?>
