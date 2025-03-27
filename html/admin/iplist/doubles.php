<?php
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/auth.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/languages/" . HTML_LANG . ".php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/header.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/cidrfilter.php");

if (isset($_POST["removeauth"])) {
    $auth_id = $_POST["f_auth_id"];
    foreach ($auth_id as $key => $val) {
        if ($val) { delete_user_auth($db_link,$val); }
        }
    header("Location: " . $_SERVER["REQUEST_URI"]);
    exit;
    }

print_ip_submenu($page_url);

if (empty($rcidr)) { $cidr_filter = ''; } else {
    $cidr_range = cidrToRange($rcidr);
    if (!empty($cidr_range)) { $cidr_filter = " AND (U.ip_int>=".ip2long($cidr_range[0])." AND U.ip_int<=".ip2long($cidr_range[1]).")"; }
    }

?>
<div id="cont">
<br>
<form name="def" action="doubles.php" method="post">
<b><?php print WEB_network_subnet; ?> - </b><?php print_subnet_select_office_splitted($db_link, 'cidr', $rcidr); ?>
<input id="btn_filter" name="btn_filter" type="submit" value="<?php echo WEB_btn_show; ?>">

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
$sSQL = "SELECT U.id, U.ip, U.mac, U.arp_found, S.subnet as net FROM User_auth U, subnets S WHERE (U.mac IS NOT NULL AND U.mac<>'') AND (U.ip_int BETWEEN S.ip_int_start AND S.ip_int_stop) $cidr_filter AND S.office=1 AND U.deleted=0 ORDER BY net,mac,arp_found";
$users = get_records_sql($db_link,$sSQL);
$f_subnet=NULL;
$f_mac=NULL;
$f_id=NULL;
$printed = NULL;
$f_index = 0;
$f_count = 0;
foreach ($users as $row) {
    //инициализируем перебор по первой записи
    if (empty($f_subnet)) { 
        //сохраняем для обработки
        $f_subnet = $row['net'];
        $f_mac=$row['mac'];
        $f_id=$row['id'];
        $f_index=0;
        continue;
        }
    //начинаем перебор - проверяем 
    if ($row['net'] === $f_subnet and $row['mac']===$f_mac) {
        //если первая запись не выводилась - выводим на печать
        if (!isset($printed[$f_id])) {
            //считаем сколько у нас дублей
            $dSQL = "SELECT  U.id, U.ip, U.mac, U.arp_found FROM User_auth U WHERE U.mac='".$f_mac."' $cidr_filter AND U.deleted=0";
            $doubles = get_records_sql($db_link,$dSQL);
            $f_count = count($doubles);

            $f_index++;
            $user = get_record_sql($db_link,"SELECT * FROM User_auth WHERE id=".$f_id);
            if (empty($user['arp_found']) or $user['arp_found'] === '0000-00-00 00:00:00') { $user['arp_found'] = ''; }
            if (empty($user['timestamp']) or $user['timestamp'] === '0000-00-00 00:00:00') { $user['timestamp'] = ''; }
            if (empty($user['changed_time']) or $user['changed_time'] === '0000-00-00 00:00:00') { $user['changed_time'] = ''; }
            print "<tr align=center>\n";
            $cl = "data";
            print "<td class=\"data\" style='padding:0'><input type=checkbox name=f_auth_id[] value=".$user["id"];
            if ($f_index != $f_count) { print " checked"; }
            print "></td>\n";
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
            print "<td class=\"$cl\" >".$user['arp_found']."</td>\n";
            print "</tr>\n";
            $printed[$f_id] = 1;
            }
        //проверяем текущую запись
        if (!isset($printed[$row['id']])) {
            $f_index++;
            $user = get_record_sql($db_link,"SELECT * FROM User_auth WHERE id=".$row['id']);
            if (empty($user['arp_found']) or $user['arp_found'] === '0000-00-00 00:00:00') { $user['arp_found'] = ''; }
            if (empty($user['timestamp']) or $user['timestamp'] === '0000-00-00 00:00:00') { $user['timestamp'] = ''; }
            if (empty($user['changed_time']) or $user['changed_time'] === '0000-00-00 00:00:00') { $user['changed_time'] = ''; }
            print "<tr align=center>\n";
            $cl = "data";
            print "<td class=\"data\" style='padding:0'><input type=checkbox name=f_auth_id[] value=".$user["id"];
            if ($f_index != $f_count) { print " checked"; }
            print " ></td>\n";
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
            print "<td class=\"$cl\" >".$user['arp_found']."</td>\n";
            print "</tr>\n";
            $printed[$row['id']] = 1;
            }
        } else {
        $f_subnet = $row['net'];
        $f_mac = $row['mac'];
        $f_id = $row['id'];
        $f_index = 0;
        }
    }
print "</table>\n";
?>
</form>
<br>
<?php
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/footer.php");
?>
