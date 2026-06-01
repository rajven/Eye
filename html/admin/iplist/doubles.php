<?php
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/auth.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/languages/" . HTML_LANG . ".php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/header.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/cidrfilter.php");

// Удаление записей авторизации
if (getPOST("removeauth") !== null) {
    $auth_id = getPOST("f_auth_id", null, []);
    if (!empty($auth_id) && is_array($auth_id)) {
        foreach ($auth_id as $val) {
            $val = trim($val);
            if ($val !== '') {
                delete_user_auth($db_link, (int)$val);
            }
        }
    }
    
    header("Location: " . $_SERVER["REQUEST_URI"]);
    exit;
}

// Фильтрация по CIDR
$params = [];
if (!empty($rcidr)) {
    $cidr_range = cidrToRange($rcidr);
    if (!empty($cidr_range) && isset($cidr_range[0], $cidr_range[1])) {
        $cidr_filter = " AND (U.ip_int >= ? AND U.ip_int <= ?)";
        $params[] = ip2long($cidr_range[0]);
        $params[] = ip2long($cidr_range[1]);
    } else {
        $cidr_filter = '';
    }
} else {
    $cidr_filter = '';
}

print_ip_submenu($page_url);

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
    <td align=Center><?php print WEB_cell_description; ?></td>
    <td align=Center><?php print WEB_cell_dns_name; ?></td>
    <td align=Center><?php print WEB_cell_created; ?></td>
    <td align=Center><?php print WEB_cell_last_found; ?></td>
    <td align=right><input type="submit" onclick="return confirm('<?php echo WEB_msg_delete; ?>?')" name="removeauth" value="<?php echo WEB_btn_delete; ?>"></td>
</tr>

<?php
$sSQL = "SELECT U.*, S.subnet as net FROM user_auth U, subnets S 
WHERE (U.mac IS NOT NULL AND U.mac<>'') AND (U.ip_int BETWEEN S.ip_int_start AND S.ip_int_stop) $cidr_filter AND S.office=1 AND U.deleted=0 
ORDER BY net,mac,arp_found,id";
$auth_list = get_records_sql($db_link,$sSQL, $params);
$f_subnet=NULL;
$f_mac=NULL;
$f_id=NULL;
$f_index = 0;
$f_count = 0;
foreach ($auth_list as $row) {
    if (empty($row['mac'])) { continue; }
    if (empty($row['net'])) { continue; }
    //инициализируем перебор по первой записи
    if (empty($f_subnet)) {
        $d_params = $params;
        //считаем сколько у нас дублей
        $dSQL = "SELECT U.*, S.subnet as net FROM user_auth U, subnets S WHERE S.office=1 AND U.deleted=0  AND (U.ip_int BETWEEN S.ip_int_start AND S.ip_int_stop) $cidr_filter AND U.mac=? AND S.subnet=?";
        $d_params[]= $row['mac'];
        $d_params[]= $row['net'];
        $doubles = get_records_sql($db_link,$dSQL, $d_params);
        $f_count = count($doubles);
        if ($f_count > 1) {
            //сохраняем для обработки
            $f_subnet = $row['net'];
            $f_mac=$row['mac'];
            $f_id=$row['id'];
            $f_index = 0;
            } else { continue; }
        }
    //начинаем перебор - проверяем 
    if ($row['net'] === $f_subnet and $row['mac']===$f_mac) {
            $f_index++;
            if (empty($row['arp_found']) || is_empty_datetime($row['arp_found'])) { $row['arp_found'] = ''; }
            if (empty($row['ts']) || is_empty_datetime($row['ts'])) { $row['ts'] = ''; }
            if (empty($row['changed_time']) || is_empty_datetime($row['changed_time'])) { $row['changed_time'] = ''; }
            print "<tr align=center>\n";
            $cl = "data";
            print "<td class=\"data\" style='padding:0'><input type=checkbox name=f_auth_id[] value=".$row["id"];
            if ($f_index >1) {
                $checked = true;
                $mac_simplified = mac_simplify($row['mac']);
                $mac_rules = get_records_sql($db_link, "SELECT * FROM auth_rules WHERE rule_type = 2 AND LENGTH(rule) > 0 AND user_id = ?", [ $row['user_id'] ]);
                foreach ($mac_rules as $rule_row) {
                    if (!empty($rule_row['rule'])) {
                        $pattern = '/^' . preg_quote(mac_simplify($rule_row['rule']), '/') . '/';
                        if (preg_match($pattern, $mac_simplified)) { $checked = false; break; }
                        }
                }
                if ($checked) { print " checked"; }
                }
            print "></td>\n";
            print "<td class=\"$cl\" ><a href=/admin/users/edituser.php?id=".$row['user_id'].">" . get_login($db_link,$row['user_id']) . "</a></td>\n";
            print "<td class=\"$cl\" ><a href=/admin/users/editauth.php?id=".$row['id'].">" . $row['ip'] . "</a></td>\n";
            print "<td class=\"$cl\" >" . expand_mac($db_link,$row['mac']) . "</td>\n";
            if (isset($row['dhcp_hostname']) and strlen($row['dhcp_hostname']) > 0) {
                print "<td class=\"$cl\" >".$row['description']." [" . $row['dhcp_hostname'] . "]</td>\n";
                } else {
                print "<td class=\"$cl\" >".$row['description']."</td>\n";
                }
            print "<td class=\"$cl\" >".$row['dns_name']."</td>\n";
            print "<td class=\"$cl\" >".$row['ts']."</td>\n";
            print "<td class=\"$cl\" >".$row['last_found']."</td>\n";
            print "<td class=\"$cl\" ></td>\n";
            print "</tr>\n";
        } else {
            $f_subnet = NULL;
            $f_mac = NULL;
            $f_id = NULL;
            $f_index = 0;
            $f_count = 0; 
        }
    }
print "</table>\n";
?>

</form>
<br>
<?php
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/footer.php");
?>
