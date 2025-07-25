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

$sort_table = 'User_auth';
if ($sort_field == 'login') { $sort_table = 'User_list'; }
if ($sort_field == 'fio') { $sort_table = 'User_list'; }
if ($sort_field == 'ou_name') { $sort_table = 'OU'; }

$sort_url = "<a href=index.php?ou=" . $rou;

if ($rou == 0) { $ou_filter = ''; } else { $ou_filter = " and User_list.ou_id=$rou "; }

if (empty($rcidr)) { $cidr_filter = ''; } else {
    $cidr_range = cidrToRange($rcidr);
    if (!empty($cidr_range)) { $cidr_filter = " and User_auth.ip_int>=".ip2long($cidr_range[0])." and User_auth.ip_int<=".ip2long($cidr_range[1]); }
    }

$enabled_filter='';
if ($enabled>0) {
    if ($enabled===2) { $enabled_filter = ' and (User_auth.enabled=1 and User_list.enabled=1)'; }
    if ($enabled===1) { $enabled_filter = ' and (User_auth.enabled=0 or User_list.enabled=0)'; }
    }

$dynamic_filter='';
if ($dynamic_enabled>0) {
    if ($dynamic_enabled ==1) { $dynamic_filter = ' and User_auth.dynamic=1'; }
    if ($dynamic_enabled ==2) { $dynamic_filter = ' and User_auth.dynamic=0'; }
    }

$dhcp_filter='';
if ($dhcp_enabled>0) {
    if ($dhcp_enabled ==1) { $dhcp_filter = ' and User_auth.dhcp=1'; }
    if ($dhcp_enabled ==2) { $dhcp_filter = ' and User_auth.dhcp=0'; }
    }

if (isset($_POST['ip'])) { $f_ip = $_POST['ip']; }
if (!isset($f_ip) and isset($_SESSION[$page_url]['ip'])) { $f_ip=$_SESSION[$page_url]['ip']; }
if (!isset($f_ip)) { $f_ip=''; }
$_SESSION[$page_url]['ip']=$f_ip;

$ip_list_type_filter='';
if ($ip_type>0) {
    //suspicious - dhcp not found 3 last days
    if ($ip_type===3) { $ip_list_type_filter = " and (User_auth.dhcp_action IN ('add', 'old', 'del') and (ABS(User_auth.dhcp_time - User_auth.arp_found)>259200) and (UNIX_TIMESTAMP()-User_auth.arp_found)<259200)"; }
    //dhcp
    if ($ip_type===2) { $ip_list_type_filter = " and (User_auth.dhcp_action IN ('add', 'old', 'del'))"; }
    //static
    if ($ip_type===1) { $ip_list_type_filter = " and (User_auth.dhcp_action NOT IN ('add', 'old', 'del'))"; }
    }

$ip_where = '';
if (!empty($f_ip)) {
    if (checkValidIp($f_ip)) { $ip_where = " and ip_int=inet_aton('" . $f_ip . "') "; }
    if (empty($ip_where)) { $ip_where =" and (mac like '" . mac_dotted($f_ip) . "%' or login like '".$f_ip."%' or comments like '".$f_ip."%' or dns_name like '".$f_ip."%' or dhcp_hostname like '".$f_ip."%')"; }
    $ip_list_filter = $ip_where;
    } else {
    $ip_list_filter = $ou_filter.$cidr_filter.$enabled_filter.$ip_list_type_filter.$dynamic_filter.$dhcp_filter;
    }

print_ip_submenu($page_url);

?>
<div id="cont">
<br>
<form name="filter" action="index.php" method="post">
<input type="hidden" name="ip-filter" value="<?php print $ip_list_filter; ?>">
<input type="hidden" name="ip-sort" value="<?php print $sort_table.".".$sort_field." ".$order; ?>">
<table>
<tr>
        <td>
        <b><?php print WEB_cell_ou; ?> - </b><?php print_ou_select($db_link, 'ou', $rou); ?>
        </td>
        <td>
        <b><?php print WEB_network_subnet; ?> - </b><?php print_subnet_select_office_splitted($db_link, 'cidr', $rcidr); ?>
        </td>
        <td></td>
</tr>
<tr>
        <td>
        <b><?php echo WEB_ips_show_by_state; ?> - </b><?php print_enabled_select('enabled', $enabled); ?>
        </td>
        <td>
        <b><?php echo WEB_ips_show_by_ip_type; ?> - </b><?php print_ip_type_select('ip_type', $ip_type); ?>
        </td>
        <td></td>
</tr>
<tr>
        <td>
        <b><?php echo WEB_cell_dhcp; ?> - </b><?php print_yn_select('dhcp_enabled', $dhcp_enabled); ?>
        </td>
        <td>
        <b><?php echo WEB_cell_temporary; ?> - </b><?php print_yn_select('dynamic_enabled', $dynamic_enabled); ?>
        </td>
        <td></td>
</tr>
<tr>
        <td colspan=2>
        <?php echo WEB_ips_search_host; ?>:&nbsp<input type="text" name="ip" value="<?php echo $f_ip; ?>"/>
        </td>
        <td>
        <?php print WEB_rows_at_page."&nbsp"; print_row_at_pages('rows',$displayed); ?>
        <input id="btn_filter" name="btn_filter" type="submit" value="<?php echo WEB_btn_show; ?>">
        </td>
</tr>
</table>
</form>

<a class="mainButton" href="#modal"><?php print WEB_btn_apply_selected; ?></a>
<div class="remodal" data-remodal-options="closeOnConfirm: true" data-remodal-id="modal" role="dialog" aria-labelledby="modal1Title" aria-describedby="modal1Desc">
 <div class="remodalBorder">
  <button data-remodal-action="close" class="remodal-close" aria-label="Close"></button>
      <form id="formAuthApply">
        <h2 id="modal1Title"><?php print WEB_selection_title; ?></h2>
        <input type="hidden" name="ApplyForAll" value="MassChange">
        <table class="data" align=center>
        <tr><td><input type=checkbox class="putField" name="e_new_ou" value='1'></td><td align=left><?php print WEB_cell_ou."</td><td align=right>";print_ou_select($db_link, 'a_new_ou', 0); ?></td></tr>
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
</div>
</div>

<a class="delButton" href="#modalDel"><?php print WEB_btn_delete; ?></a>
<div class="remodal" data-remodal-options="closeOnConfirm: true" data-remodal-id="modalDel" role="dialog" aria-labelledby="modal1Title" aria-describedby="modal1Desc">
 <div class="remodalBorder">
  <button data-remodal-action="close" class="remodal-close" aria-label="Close"></button>
    <form id="formAuthDel">
        <h2 id="modal1Title"><?php print WEB_msg_delete_selected; ?></h2>
        <input type="hidden" name="RemoveAuth" value="MassChange">
        <?php print_qa_select('f_deleted', 0);?><br><br>
        <input type="submit" name="submit" class="btn" value="<?php echo WEB_btn_apply; ?>">
    </form>
</div>
</div>

<a class="exportButton" href="#modalExport"><?php print WEB_btn_export; ?></a>
<div class="remodal" data-remodal-options="closeOnConfirm: true" data-remodal-id="modalExport" role="dialog" aria-labelledby="modal1Title" aria-describedby="modal1Desc">
 <div class="remodalBorder">
  <button data-remodal-action="close" class="remodal-close" aria-label="Close"></button>
    <form id="formAuthExport">
        <h2 id="modal1Title"><?php print WEB_selection_title; ?></h2>
        <input type="hidden" name="ExportAuth" value="MassChange">
        <?php print WEB_msg_export_selected."&nbsp"; print_qa_select('a_selected', 1);?>
        <br><br>
            <input type="submit" name="submit" class="btn" value="<?php echo WEB_btn_run; ?>">
    </form>
</div>
</div>

<form id="def" name="def">

<?php
$countSQL="SELECT Count(*) FROM User_auth
LEFT JOIN User_list
ON User_auth.user_id = User_list.id
LEFT JOIN OU
ON OU.id=User_list.ou_id
WHERE User_auth.deleted =0 $ip_list_filter";

$res = mysqli_query($db_link, $countSQL);
$count_records = mysqli_fetch_array($res);
$total=ceil($count_records[0]/$displayed);
if ($page>$total) { $page=$total; }
if ($page<1) { $page=1; }
$start = ($page * $displayed) - $displayed;
print_navigation($page_url,$page,$displayed,$count_records[0],$total);
?>
<br>

<table class="data">
        <tr>
        <td align=Center><input type="checkbox" onClick="checkAll(this.checked);"></td>
                <td align=Center><?php print $sort_url . "&sort=ou_name&order=$new_order>" . WEB_cell_ou . "</a>"; ?></td>
                <td align=Center><?php print $sort_url . "&sort=login&order=$new_order>" . WEB_cell_login . "</a>"; ?></td>
                <td align=Center><?php print $sort_url . "&sort=ip_int&order=$new_order>" . WEB_cell_ip . "</a>"; ?></td>
                <td align=Center><?php print $sort_url . "&sort=mac&order=$new_order>" . WEB_cell_mac . "</a>"; ?></td>
                <td align=Center><?php print WEB_cell_comment; ?></td>
                <td align=Center><?php print WEB_cell_dns_name; ?></td>
                <td align=Center><?php print WEB_cell_filter; ?></td>
                <td align=Center><?php print WEB_cell_shaper; ?></td>
                <td align=Center><?php print WEB_cell_traf; ?></td>
                <td align=Center><?php print WEB_cell_dhcp; ?></td>
                <td align=Center><?php print WEB_cell_acl; ?></td>
                <td align=Center><?php print $sort_url . "&sort=arp_found&order=$new_order>Last</a>"; ?></td>
                <td align=Center><?php print WEB_cell_connection; ?></td>
        </tr>
<?php

$sSQL = "SELECT User_auth.*, User_list.login, User_list.enabled as UEnabled, User_list.blocked as UBlocked, OU.ou_name
FROM User_auth
LEFT JOIN User_list
ON User_auth.user_id = User_list.id
LEFT JOIN OU
ON OU.id=User_list.ou_id
WHERE User_auth.deleted =0 $ip_list_filter
ORDER BY $sort_table.$sort_field $order LIMIT $start,$displayed";

$users = get_records_sql($db_link,$sSQL);
foreach ($users as $user) {
    if ($user['dhcp_time'] == '0000-00-00 00:00:00') {
        $dhcp_str = '';
    } else {
        $dhcp_str = $user['dhcp_time'] . " (" . $user['dhcp_action'] . ")";
    }
    if ($user['last_found'] == '0000-00-00 00:00:00') { $user['last_found'] = ''; }
    if ($user['arp_found'] == '0000-00-00 00:00:00') { $user['arp_found'] = ''; }
    print "<tr align=center>\n";
    $cl = "data";
    if (!$user['enabled']) { $cl = "warn"; }
    if ($user['blocked']) { $cl = "error"; }
    if (!$user['UEnabled'] or $user['UBlocked']) { $cl = "off"; }
    print "<td class=\"$cl\" style='padding:0'><input type=checkbox name=fid[] value=".$user['id']."></td>\n";
    print "<td class=\"$cl\" >".$user['ou_name']."</td>\n";
    if (empty($user['login'])) { $user_name = $user['user_id']; } else { $user_name = $user['login']; }
    print "<td class=\"$cl\" ><a href=/admin/users/edituser.php?id=".$user['user_id'].">" . $user_name . "</a></td>\n";
    print "<td class=\"$cl\" ><a href=/admin/users/editauth.php?id=".$user['id'].">" . $user['ip'] . "</a></td>\n";
    print "<td class=\"$cl\" >" . expand_mac($db_link,$user['mac']) . "</td>\n";
    if (isset($user['dhcp_hostname']) and strlen($user['dhcp_hostname']) > 0) {
        print "<td class=\"$cl\" width=200 >".$user['comments']." [" . $user['dhcp_hostname'] . "]</td>\n";
    } else {
        print "<td class=\"$cl\" width=200 >".$user['comments']."</td>\n";
    }
    print "<td class=\"$cl\" >".$user['dns_name']."</td>\n";
    print "<td class=\"$cl\" >" . get_group($db_link, $user['filter_group_id']) . "</td>\n";
    print "<td class=\"$cl\" >" . get_queue($db_link, $user['queue_id']) . "</td>\n";
    print_td_qa($user['save_traf'],FALSE,$cl);
    print_td_qa($user['dhcp'],FALSE,$cl);
    print "<td class=\"$cl\" >".$user['dhcp_acl']."</td>\n";
    if (empty($user['arp_found'])) {
        print "<td class=\"$cl\" >".$user['last_found']."</td>\n";
        } else {
        print "<td class=\"$cl\" >".$user['arp_found']."</td>\n";
        }
    print "<td class=\"$cl\" >" . get_connection($db_link, $user['id']) . "</td>\n";
    print "</tr>\n";
}
print "</table>\n";
print_navigation($page_url,$page,$displayed,$count_records[0],$total);
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

<script src="/js/remodal/remodal.min.js"></script>
<script src="/js/remodal-auth.js"></script>

<script>
document.getElementById('ou').addEventListener('change', function(event) {
  const buttonApply = document.getElementById('btn_filter');
  buttonApply.click();
});

document.getElementById('cidr').addEventListener('change', function(event) {
  const buttonApply = document.getElementById('btn_filter');
  buttonApply.click();
});

document.getElementById('enabled').addEventListener('change', function(event) {
  const buttonApply = document.getElementById('btn_filter');
  buttonApply.click();
});

document.getElementById('ip_type').addEventListener('change', function(event) {
  const buttonApply = document.getElementById('btn_filter');
  buttonApply.click();
});

document.getElementById('rows').addEventListener('change', function(event) {
  const buttonApply = document.getElementById('btn_filter');
  buttonApply.click();
});

document.getElementById('dhcp_enabled').addEventListener('change', function(event) {
  const buttonApply = document.getElementById('btn_filter');
  buttonApply.click();
});

document.getElementById('dynamic_enabled').addEventListener('change', function(event) {
  const buttonApply = document.getElementById('btn_filter');
  buttonApply.click();
});


</script>

<?php
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/footer.simple.php");
?>
