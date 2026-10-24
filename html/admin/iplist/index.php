<?php
$default_displayed=500;
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/auth.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/languages/" . HTML_LANG . ".php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/header.php");
$default_sort='ip_int';
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/oufilter.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/cidrfilter.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/sortfilter.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/gatefilter.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/enabledfilter.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/iptypefilter.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/dynfilter.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/dhcpfilter.php");


$sort_table = 'user_auth';
if ($sort_field == 'login') { $sort_table = 'user_list'; }
if ($sort_field == 'description') { $sort_table = 'user_list'; }
if ($sort_field == 'ou_name') { $sort_table = 'ou'; }

$params=[];

$sort_url = "<a href=index.php?ou=" . $rou;

if ($rou == 0) { $ou_filter = ''; } else { $ou_filter = " and user_list.ou_id=?"; $params[]=$rou; }

if (empty($rcidr)) { $cidr_filter = ''; } else {
    $cidr_range = cidrToRange($rcidr);
    if (!empty($cidr_range)) { $cidr_filter = " and user_auth.ip_int>=? and user_auth.ip_int<=?"; }
    $params[]=ip2long($cidr_range[0]);
    $params[]=ip2long($cidr_range[1]);
    }

$enabled_filter='';
if ($enabled>0) {
    if ($enabled===2) { $enabled_filter = ' and (user_auth.enabled=1 and user_list.enabled=1)'; }
    if ($enabled===1) { $enabled_filter = ' and (user_auth.enabled=0 or user_list.enabled=0)'; }
    }

$dynamic_filter='';
if ($dynamic_enabled>0) {
    if ($dynamic_enabled ==1) { $dynamic_filter = ' and user_auth.dynamic=1'; }
    if ($dynamic_enabled ==2) { $dynamic_filter = ' and user_auth.dynamic=0'; }
    }

$dhcp_filter='';
if ($dhcp_enabled>0) {
    if ($dhcp_enabled ==1) { $dhcp_filter = ' and user_auth.dhcp=1'; }
    if ($dhcp_enabled ==2) { $dhcp_filter = ' and user_auth.dhcp=0'; }
    }

if (isset($_POST['search_str'])) { $f_search_str = trim($_POST['search_str']); }
if (!isset($f_search_str) and isset($_SESSION[$page_url]['search_str'])) { $f_search_str=$_SESSION[$page_url]['search_str']; }
if (!isset($f_search_str)) { $f_search_str=''; }
$_SESSION[$page_url]['search_str']=$f_search_str;

$f_search=replaceSpecialChars($f_search_str);

$ip_list_type_filter='';
if ($ip_type>0) {
    //suspicious - dhcp not found 3 last days
    if ($ip_type===3) { $ip_list_type_filter = " and (user_auth.dhcp_action IN ('add', 'old', 'del') and (ABS(user_auth.dhcp_time - user_auth.arp_found)>259200) and (UNIX_TIMESTAMP()-user_auth.arp_found)<259200)"; }
    //dhcp
    if ($ip_type===2) { $ip_list_type_filter = " and (user_auth.dhcp_action IN ('add', 'old', 'del'))"; }
    //static
    if ($ip_type===1) { $ip_list_type_filter = " and (user_auth.dhcp_action NOT IN ('add', 'old', 'del'))"; }
    }

$ip_where = '';
if (!empty($f_search_str)) {
    $f_ip = normalizeIpAddress($f_search_str);
    if (!empty($f_ip)) { 
        $ip_where = " and ip=?";
        $params[]= $f_ip;
        $f_search_str = $f_ip;
        } else {
        if (checkValidMac($f_search_str)) { 
    	    $ip_where =" and mac=?"; 
    	    $params[]= mac_dotted($f_search_str);
    	    } else {
            $ip_where =" and (mac LIKE ? or login LIKE ? or user_auth.description LIKE ? or user_list.description LIKE ? or dns_name LIKE ? or dhcp_hostname LIKE ?)";
            $mac_search = MaybeMac($f_search);
            if (!empty($mac_search)) { $params[]=MaybeMac($f_search).'%'; } else { $params[]=$f_search_str.'%'; }
            $params[]=$f_search.'%';
            $params[]=$f_search.'%';
            $params[]=$f_search.'%';
            $params[]=$f_search.'%';
            $params[]=$f_search.'%';
            }
        }
    }

$ip_list_filter = $ou_filter.$cidr_filter.$enabled_filter.$ip_list_type_filter.$dynamic_filter.$dhcp_filter.$ip_where;

print_ip_submenu($page_url);

?>

<div id="cont">
<br>
<form id="filter" name="filter" action="index.php" method="post">
<input type="hidden" name="ip-filter" value="<?php print $ip_list_filter; ?>">
<input type="hidden" name="ip-sort" value="<?php print $sort_table.".".$sort_field." ".$order; ?>">
<div class="filters">
<div class="f"><label for="ou"><?php print WEB_cell_ou; ?></label><?php print_ou_select($db_link, 'ou', $rou); ?></div>
<div class="f"><label for="cidr"><?php print WEB_network_subnet; ?></label><?php print_subnet_select_office_splitted($db_link, 'cidr', $rcidr); ?></div>
<div class="f"><label for="enabled"><?php echo WEB_ips_show_by_state; ?></label><?php print_enabled_select('enabled', $enabled); ?></div>
<div class="f"><label for="ip_type"><?php echo WEB_ips_show_by_ip_type; ?></label><?php print_ip_type_select('ip_type', $ip_type); ?></div>
<div class="f"><label for="dhcp_enabled"><?php echo WEB_cell_dhcp; ?></label><?php print_yn_select('dhcp_enabled', $dhcp_enabled); ?></div>
<div class="f"><label for="dynamic_enabled"><?php echo WEB_cell_temporary; ?></label><?php print_yn_select('dynamic_enabled', $dynamic_enabled); ?></div>
<div class="f grow"><label for="search_str"><?php echo WEB_ips_search_host; ?></label>
  <span class="q"><input type="search" id="search_str" name="search_str" value="<?php echo htmlspecialchars($f_search_str, ENT_QUOTES); ?>"
    placeholder="<?php echo WEB_ips_search_host; ?>">
    <kbd>/</kbd></span></div>
<div class="f end"><label for="rows"><?php print WEB_rows_at_page; ?></label><?php print_row_at_pages('rows',$displayed); ?></div>
<p class="hint"><?php echo WEB_msg_filter_hint; ?></p>
<input id="btn_filter" name="btn_filter" type="submit" value="<?php echo WEB_btn_show; ?>">
</div>
</form>

<a class="mainButton" href="#eye-mass"><?php print WEB_btn_apply_selected; ?></a>
<dialog id="eye-mass" class="eye-dlg">
  <button type="button" class="x" data-close aria-label="<?php echo WEB_btn_close; ?>">&#10005;</button>
  <form id="formAuthApply" name="formAuthApply" onsubmit="blockForm('formAuthApply');">
        <h2><?php print WEB_selection_title; ?></h2>
        <input type="hidden" name="ApplyForAll" value="MassChange">
        <table class="data" align=center>
        <tr><td><input type=checkbox class="putField" name="e_new_ou" value='1'></td><td align=left><?php print WEB_cell_ou."</td><td align=right>";print_ou_set($db_link, 'a_new_ou', 0); ?></td></tr>
        <tr><td><input type=checkbox class="putField" name="e_enabled" value='1'></td><td align=left><?php print WEB_cell_enabled."</td><td align=right>";print_qa_select('a_enabled', 1);?></td></tr>
        <tr><td><input type=checkbox class="putField" name="e_group_id" value='1'></td><td align=left><?php print WEB_cell_filter."</td><td align=right>";print_filter_group_select($db_link, 'a_group_id', 0);?></td></tr>
        <tr><td><input type=checkbox class="putField" name="e_queue_id" value='1'></td><td align=left><?php print WEB_cell_shaper."</td><td align=right>";print_queue_select($db_link, 'a_queue_id', 0);?></td></tr>
        <tr><td><input type=checkbox class="putField" name="e_dhcp" value='1'></td><td align=left><?php print "Dhcp"."</td><td align=right>"; print_qa_select('a_dhcp', 1);?></td></tr>
        <tr><td><input type=checkbox class="putField" name="e_dhcp_acl" value='1'></td><td align=left><?php print "Dhcp-acl"."</td><td align=right>"; print_dhcp_acl_list($db_link,"a_dhcp_acl"); ?></td></tr>
        <tr><td><input type=checkbox class="putField" name="e_dhcp_option_set" value='1'></td><td align=left><?php print "Dhcp-option-set"."</td><td align=right>"; print_dhcp_option_set_list($db_link,"a_dhcp_option_set"); ?></td></tr>
        <tr><td><input type=checkbox class="putField" name="e_traf" value='1'></td><td align=left><?php print "Save traffic"."</td><td align=right>"; print_qa_select('a_traf',1);?></td></tr>
        <tr><td><input type=checkbox class="putField" name="e_bind_mac" value='1'></td><td align=left><?php print WEB_user_bind_mac."</td><td align=right>";print_qa_select('a_bind_mac', 1);?></td></tr>
        <tr><td><input type=checkbox class="putField" name="e_bind_ip" value='1'></td><td align=left><?php print WEB_user_bind_ip."</td><td align=right>";print_qa_select('a_bind_ip', 1);?></td></tr>
        </table>
        <input type="submit" name="submit" class="btn" value="<?php echo WEB_btn_apply; ?>">
    </form>
</dialog>

<a class="delButton" href="#eye-del"><?php print WEB_btn_delete; ?></a>
<dialog id="eye-del" class="eye-dlg">
  <button type="button" class="x" data-close aria-label="<?php echo WEB_btn_close; ?>">&#10005;</button>
  <form id="formAuthDel" name="formAuthDel" onsubmit="blockForm('formAuthDel');">
        <h2><?php print WEB_msg_delete_selected; ?></h2>
        <input type="hidden" name="RemoveAuth" value="MassChange">
        <?php print_qa_select('f_deleted', 0);?><br><br>
        <input type="submit" name="submit" class="btn" value="<?php echo WEB_btn_apply; ?>">
    </form>
</dialog>

<a class="exportButton" href="#eye-export"><?php print WEB_btn_export; ?></a>
<dialog id="eye-export" class="eye-dlg">
  <button type="button" class="x" data-close aria-label="<?php echo WEB_btn_close; ?>">&#10005;</button>
  <form id="formAuthExport" name="formAuthExport" onsubmit="blockForm('formAuthExport');">
        <h2><?php print WEB_selection_title; ?></h2>
        <input type="hidden" name="ExportAuth" value="MassChange">
        <?php print WEB_msg_export_selected."&nbsp"; print_qa_select('a_selected', 1);?>
        <br><br>
            <input type="submit" name="submit" class="btn" value="<?php echo WEB_btn_run; ?>">
    </form>
</dialog>

<form id="def" name="def">

<?php
$countSQL="SELECT Count(*) FROM user_auth
LEFT JOIN user_list
ON user_auth.user_id = user_list.id
LEFT JOIN ou
ON ou.id=user_list.ou_id
WHERE user_auth.deleted =0 $ip_list_filter";

$count_records = get_single_field($db_link,$countSQL, $params);
$total=ceil($count_records/$displayed);
if ($page>$total) { $page=$total; }
if ($page<1) { $page=1; }
$start = ($page * $displayed) - $displayed;
print_navigation($page_url,$page,$displayed,$count_records,$total);
?>
<br>

<table class="data">
        <tr>
        <td align=Center><input type="checkbox" onClick="checkAll(this.checked);"></td>
                <td align=Center><?php print $sort_url . "&sort=ou_name&order=$new_order>" . WEB_cell_ou . "</a>"; ?></td>
                <td align=Center><?php print $sort_url . "&sort=login&order=$new_order>" . WEB_cell_login . "</a>"; ?></td>
                <td align=Center><?php print $sort_url . "&sort=ip_int&order=$new_order>" . WEB_cell_ip . "</a>"; ?></td>
                <td align=Center><?php print $sort_url . "&sort=mac&order=$new_order>" . WEB_cell_mac . "</a>"; ?></td>
                <td align=Center><?php print WEB_cell_description; ?></td>
                <td align=Center><?php print WEB_cell_dns_name; ?></td>
                <td align=Center><?php print WEB_cell_filter; ?></td>
                <td align=Center><?php print WEB_cell_shaper; ?></td>
                <td align=Center><?php print WEB_cell_traf; ?></td>
                <td align=Center><?php print WEB_cell_dhcp; ?></td>
                <td align=Center><?php print WEB_cell_acl; ?></td>
                <td align=Center><?php print $sort_url . "&sort=arp_found&order=$new_order>Arp/Mac</a>"; ?></td>
                <td align=Center><?php print WEB_cell_connection; ?></td>
        </tr>
<?php

$sSQL = "SELECT user_auth.*, user_list.login, user_list.enabled as uenabled, user_list.blocked as ublocked, ou.ou_name
FROM user_auth
LEFT JOIN user_list
ON user_auth.user_id = user_list.id
LEFT JOIN ou
ON ou.id=user_list.ou_id
WHERE user_auth.deleted =0 $ip_list_filter
ORDER BY $sort_table.$sort_field $order LIMIT ? OFFSET ?";

$params[]=$displayed;
$params[]=$start;

$users = get_records_sql($db_link,$sSQL, $params);
foreach ($users as $user) {
    if (is_empty_datetime($user['dhcp_time'])) {
        $dhcp_str = '';
    } else {
        $dhcp_str = $user['dhcp_time'] . " (" . $user['dhcp_action'] . ")";
    }
    if (is_empty_datetime($user['last_found'])) { $user['last_found'] = ''; }
    if (is_empty_datetime($user['arp_found'])) { $user['arp_found'] = ''; }
    if (is_empty_datetime($user['mac_found'])) { $user['mac_found'] = ''; }
    print "<tr align=center>\n";
    $cl = "data";
    if (!$user['enabled']) { $cl = "warn"; }
    if ($user['blocked']) { $cl = "error"; }
    if ($user['uenabled'] !== 1 || $user['ublocked'] == 1) { $cl = "off"; }
    print "<td class=\"$cl\" style='padding:0'><input type=checkbox name=fid[] value=".$user['id']."></td>\n";
    print "<td class=\"$cl\" >".$user['ou_name']."</td>\n";
    if (empty($user['login'])) { $user_name = $user['user_id']; } else { $user_name = $user['login']; }
    print "<td class=\"$cl\" ><a href=/admin/users/edituser.php?id=".$user['user_id'].">" . $user_name . "</a></td>\n";
    print "<td class=\"$cl\" ><a href=/admin/users/editauth.php?id=".$user['id'].">" . $user['ip'] . "</a></td>\n";
    print "<td class=\"$cl\" >" . expand_mac($db_link,$user['mac']) . "</td>\n";
    if (isset($user['dhcp_hostname']) and strlen($user['dhcp_hostname']) > 0) {
        print "<td class=\"$cl\" width=200 >".$user['description']." [" . $user['dhcp_hostname'] . "]</td>\n";
    } else {
        print "<td class=\"$cl\" width=200 >".$user['description']."</td>\n";
    }

    $aliases = get_records_sql($db_link, 'SELECT * FROM user_auth_alias WHERE auth_id=?', [$user['id']]);
    $dns_display = $user['dns_name'];
    if ($user["dns_ptr_only"]) { $dns_display.='&nbsp(ptr)'; }
    if (!empty($aliases)) {
        $dns_display .= '<hr>';
        $alias_list = [];
        foreach ($aliases as $alias) {
            $alias_list[] = htmlspecialchars($alias['alias'], ENT_QUOTES, 'UTF-8');
        }
        $dns_display .= implode('<br>', $alias_list);
    }
    print "<td class=\"$cl\" >".$dns_display."</td>\n";
    print "<td class=\"$cl\" >" . get_group($db_link, $user['filter_group_id']) . "</td>\n";
    print "<td class=\"$cl\" >" . get_queue($db_link, $user['queue_id']) . "</td>\n";
    print_td_qa($user['save_traf'],FALSE,$cl);
    print_td_qa($user['dhcp'],FALSE,$cl);
    print "<td class=\"$cl\" >".$user['dhcp_acl']."</td>\n";
    print "<td class=\"$cl\" >";
    if (!empty($user['arp_found'])) {
        print $user['arp_found'];
        } else { print "-"; }
    print "&nbsp/&nbsp";
    if (!empty($user['mac_found'])) {
        print $user['mac_found'];
        } else { print "-"; }
    print "</td>\n";
    print "<td class=\"$cl\" >" . get_connection($db_link, $user['id']) . "</td>\n";
    print "</tr>\n";
}
print "</table>\n";
print_navigation($page_url,$page,$displayed,$count_records,$total);
?>
<br>
<table class="data">
<tr><td><?php echo WEB_color_description; ?></td></tr>
<tr>
<td class="data"><?php echo WEB_color_auth_enabled; ?></td>
<td class="warn"><?php echo WEB_color_auth_disabled; ?></td>
<td class="error"><?php echo WEB_color_user_blocked; ?></td>
<td class="off"><?php echo WEB_color_user_disabled; ?></td>
</table>



<?php
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/footer.php");
?>
