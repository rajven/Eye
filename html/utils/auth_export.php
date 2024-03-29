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
                $sSQL = "SELECT User_list.login, User_auth.ip, User_auth.mac, User_auth.comments, User_auth.dns_name, User_auth.last_found FROM User_auth, User_list WHERE User_auth.user_id = User_list.id AND User_auth.id = " . $val;
                $record = get_record_sql($db_link, $sSQL);
                print $record['login'] . ';' . $record['ip'] . ';' . $record['mac'] . ';' . $record['comments'] . ';' . $record['dns_name'] . ';' . $record['last_found'] . ';' . get_connection_string($db_link, $val)."\n";
            }
        }
    } else {
        //export all
        $ip_filter = '';
        $sort = 'User_auth.ip_int';
        if (!empty($_POST["ip-filter"])) { $ip_filter = $_POST['ip-filter']; }
        if (!empty($_POST["ip-sort"])) { $sort = $_POST['ip-sort']; }
        $sSQL = "SELECT User_auth.*, User_list.login, User_list.enabled as UEnabled, User_list.blocked as UBlocked FROM User_auth, User_list WHERE User_auth.user_id = User_list.id AND User_auth.deleted = 0 $ip_filter ORDER BY $sort";
        $auth_table = mysqli_query($db_link, $sSQL);
        while ($record = mysqli_fetch_array($auth_table)) {
            print $record['login'] . ';' . $record['ip'] . ';' . $record['mac'] . ';' . $record['comments'] . ';' . $record['dns_name'] . ';' . $record['last_found'] .';' . get_connection_string($db_link, $record['id']). "\n";
        }
    }
}
