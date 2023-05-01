<?php
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/auth.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/languages/" . HTML_LANG . ".php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/header.php");

if (isset($_POST["removeauth"])) {
    $auth_id = $_POST["f_auth_id"];
    foreach ($auth_id as $key => $val) {
        if ($val) {
                run_sql($db_link, 'DELETE FROM connections WHERE auth_id='.$val);
                run_sql($db_link, 'DELETE FROM User_auth_alias WHERE auth_id='.$val);
                $changes=delete_record($db_link, "User_auth", "id=" . $val);
                if (!empty($changes)) { LOG_WARNING($db_link,"Remove user ip: \r\n $changes"); }
                }
            }
    header("Location: " . $_SERVER["REQUEST_URI"]);
    exit;
    }

print_ip_submenu($page_url);
?>
<div id="cont">
<br>
<form name="def" action="doubles.php" method="post">
<table class="data">
<tr>
    <td class="data"><input type="checkbox" onClick="checkAll(this.checked);"></td>
    <td align=Center><?php print WEB_cell_login; ?></td>
    <td align=Center><?php print WEB_cell_ip; ?></td>
    <td align=Center><?php print WEB_cell_mac ; ?></td>
    <td align=Center><?php print WEB_cell_comment; ?></td>
    <td align=Center><?php print WEB_cell_dns_name; ?></td>
    <td align=Center><?php print WEB_cell_created; ?></td>
    <td align=Center><?php print WEB_cell_last_found; ?></td>
    <td align=right><input type="submit" onclick="return confirm('<?php echo WEB_msg_delete; ?>?')" name="removeauth" value="<?php echo WEB_btn_delete; ?>"></td>
</tr>
<?php
$sSQL = "SELECT U.id, U.ip, U.mac, S.subnet as net FROM User_auth U, subnets S WHERE (U.mac IS NOT NULL AND U.mac<>'') AND (U.ip_int BETWEEN S.ip_int_start AND S.ip_int_stop) AND S.office=1 AND deleted=0 ORDER BY net,mac,ip";
$users = get_records_sql($db_link,$sSQL);
$f_subnet=NULL;
$f_mac=NULL;
$f_id=NULL;
$printed = NULL;
foreach ($users as $row) {
    if (empty($f_subnet)) { $f_subnet = $row['net']; $f_mac=$row['mac']; $f_id=$row['id']; continue; }
    if ($row['net'] === $f_subnet and $row['mac']===$f_mac) {
        if (!isset($printed[$f_id])) {
            $user = get_record_sql($db_link,"SELECT * FROM User_auth WHERE id=".$f_id);
            if (empty($user['last_found']) or $user['last_found'] === '0000-00-00 00:00:00') { $user['last_found'] = ''; }
            if (empty($user['timestamp']) or $user['timestamp'] === '0000-00-00 00:00:00') { $user['timestamp'] = ''; }
            if (empty($user['changed_time']) or $user['changed_time'] === '0000-00-00 00:00:00') { $user['changed_time'] = ''; }
            print "<tr align=center>\n";
            $cl = "data";
            print "<td class=\"data\" style='padding:0'><input type=checkbox name=f_auth_id[] value=".$user["id"]." ></td>\n";
            print "<td class=\"$cl\" ><a href=/admin/users/edituser.php?id=".$user['user_id'].">" . get_login($db_link,$user['user_id']) . "</a></td>\n";
            print "<td class=\"$cl\" ><a href=/admin/users/editauth.php?id=".$user['id'].">" . $user['ip'] . "</a></td>\n";
            print "<td class=\"$cl\" >" . expand_mac($db_link,$user['mac']) . "</td>\n";
            if (isset($user['dhcp_hostname']) and strlen($user['dhcp_hostname']) > 0) {
                print "<td class=\"$cl\" >".$user['comments']." [" . $user['dhcp_hostname'] . "]</td>\n";
                } else {
                print "<td class=\"$cl\" >".$user['comments']."</td>\n";
                }
            print "<td class=\"$cl\" >".$user['dns_name']."</td>\n";
            print "<td class=\"$cl\" >".$user['timestamp']."</td>\n";
            print "<td class=\"$cl\" >".$user['last_found']."</td>\n";
            print "</tr>\n";
            $printed[$f_id] = 1;
            }
        if (!isset($printed[$row['id']])) {
            $user = get_record_sql($db_link,"SELECT * FROM User_auth WHERE id=".$row['id']);
            if (empty($user['last_found']) or $user['last_found'] === '0000-00-00 00:00:00') { $user['last_found'] = ''; }
            if (empty($user['timestamp']) or $user['timestamp'] === '0000-00-00 00:00:00') { $user['timestamp'] = ''; }
            if (empty($user['changed_time']) or $user['changed_time'] === '0000-00-00 00:00:00') { $user['changed_time'] = ''; }
            print "<tr align=center>\n";
            $cl = "data";
            print "<td class=\"data\" style='padding:0'><input type=checkbox name=f_auth_id[] value=".$user["id"]." ></td>\n";
            print "<td class=\"$cl\" ><a href=/admin/users/edituser.php?id=".$user['user_id'].">" . get_login($db_link,$user['user_id']) . "</a></td>\n";
            print "<td class=\"$cl\" ><a href=/admin/users/editauth.php?id=".$user['id'].">" . $user['ip'] . "</a></td>\n";
            print "<td class=\"$cl\" >" . expand_mac($db_link,$user['mac']) . "</td>\n";
            if (isset($user['dhcp_hostname']) and strlen($user['dhcp_hostname']) > 0) {
                print "<td class=\"$cl\" >".$user['comments']." [" . $user['dhcp_hostname'] . "]</td>\n";
                } else {
                print "<td class=\"$cl\" >".$user['comments']."</td>\n";
                }
            print "<td class=\"$cl\" >".$user['dns_name']."</td>\n";
            print "<td class=\"$cl\" >".$user['timestamp']."</td>\n";
            print "<td class=\"$cl\" >".$user['last_found']."</td>\n";
            print "</tr>\n";
            $printed[$row['id']] = 1;
            }
        }
    $f_subnet = $row['net'];
    $f_mac=$row['mac'];
    $f_id=$row['id'];
    }
print "</table>\n";
?>
</form>
<br>
<?php
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/footer.php");
?>
