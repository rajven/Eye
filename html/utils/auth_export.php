<?php
require_once($_SERVER['DOCUMENT_ROOT'] . "/inc/auth.php");
require_once($_SERVER['DOCUMENT_ROOT'] . "/inc/languages/" . HTML_LANG . ".php");

if (!defined("CONFIG")) die("Not defined");

if (isset($_POST["ExportAuth"])) {
    print "login;ip;mac;comment;dns name;last_found;connected\n";
    if (isset($_POST["a_selected"]) and $_POST["a_selected"] * 1) {
        //export selected only
        $auth_id = $_POST["fid"];
        foreach ($auth_id as $key => $val) {
            if ($val) {
                $sSQL = "SELECT user_list.login, user_auth.ip, user_auth.mac, user_auth.comments, user_auth.dns_name, user_auth.last_found FROM user_auth, user_list WHERE user_auth.user_id = user_list.id AND user_auth.id = " . $val;
                $record = get_record_sql($db_link, $sSQL);
                print $record['login'] . ';' . $record['ip'] . ';' . $record['mac'] . ';' . $record['comments'] . ';' . $record['dns_name'] . ';' . $record['last_found'] . ';' . get_connection_string($db_link, $val)."\n";
            }
        }
    } else {
        //export all
        $ip_filter = '';
        $sort = 'user_auth.ip_int';
        if (!empty($_POST["ip-filter"])) { $ip_filter = $_POST['ip-filter']; }
        if (!empty($_POST["ip-sort"])) { $sort = $_POST['ip-sort']; }
        $sSQL = "SELECT user_auth.*, user_list.login, user_list.enabled as UEnabled, user_list.blocked as UBlocked FROM user_auth, user_list WHERE user_auth.user_id = user_list.id AND user_auth.deleted = 0 $ip_filter ORDER BY $sort";
        $auth_table = get_records_sql($db_link, $sSQL);
        foreach ($auth_table as $record) {
            print $record['login'] . ';' . $record['ip'] . ';' . $record['mac'] . ';' . $record['comments'] . ';' . $record['dns_name'] . ';' . $record['last_found'] .';' . get_connection_string($db_link, $record['id']). "\n";
        }
}
