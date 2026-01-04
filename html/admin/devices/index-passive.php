<?php
$default_displayed=500;
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/auth.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/languages/" . HTML_LANG . ".php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/header.php");
$default_sort='ip_int';
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/oufilter.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/subnetfilter.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/sortfilter.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/vendorfilter.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/devtypesfilter.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/buildingfilter.php");

$unknown=1;
if (!isset($_POST['f_unknown']) and isset($_POST['OK'])) { $unknown=0; }
if (isset($_POST['f_unknown'])) { $unknown=$_POST['f_unknown']*1; }

$unknown_checked='';
if ($unknown) { $unknown_checked='checked="checked"'; }

$sort_table = 'A';
if ($sort_field == 'login') { $sort_table = 'L'; }
if ($sort_field == 'fio') { $sort_table = 'L'; }
if ($sort_field == 'model_name') { $sort_table = 'M'; }

$sort_url = "<a href=index-passive.php?ou=" . $rou;

if ($rou == 0) { $ou_filter = ''; } else { $ou_filter = " and L.ou_id=$rou "; }

if ($rsubnet == 0) { $subnet_filter = ''; } else {
    $subnet_range = get_subnet_range($db_link,$rsubnet);
    if (!empty($subnet_range)) { $subnet_filter = " and A.ip_int>=".$subnet_range['start']." and A.ip_int<=".$subnet_range['stop']; }
    }

$d_filter='';
if ($f_building_id > 0) { $d_filter .= ' and D.building_id=' . $f_building_id; }
if ($f_devtype_id > 0) { $d_filter .= ' and D.device_type=' . $f_devtype_id; } else { $d_filter .= ' and D.device_type>2'; }

$ip_list_filter = $ou_filter.$subnet_filter;

unset($_POST);

print_device_submenu($page_url);

?>
<div id="cont">
<form name="filter" action="index-passive.php" method="post">
<table class="data">
<tr>
<td class="info"> <?php echo WEB_device_type_show; ?>: </td>
<td class="info"> <?php print_devtypes_select($db_link, "devtypes", $f_devtype_id, "id>2"); ?>
<td class="info"> <?php print WEB_device_show_location; ?></td>
<td class="info" colspan=2> <?php print_building_select($db_link, "building_id", $f_building_id); ?></td>
<td class="info" colspan=2 align=right><input name="OK" type="submit" value="<?php echo WEB_btn_show; ?>"></td>
</tr>
<tr>
<td class="info"><?php print WEB_cell_ou."&nbsp"; ?> </td>
<td class="info"><?php print_ou_select($db_link, 'ou', $rou); ?></td>
<td class="info"><?php print WEB_rows_at_page."&nbsp:"; print_row_at_pages('rows',$displayed); ?></td>
<td class="info"><?php print WEB_network_subnet; ?> </td>
<td class="info"><?php print_subnet_select_office($db_link, 'subnet', $rsubnet); ?></td>
<td class="info"><?php print WEB_device_hide_unknown."&nbsp"; ?> <input type=checkbox name=f_unknown value="1" <?php print $unknown_checked; ?>> </td>
<td class="info"><?php print WEB_model_vendor."&nbsp"; print_vendor_select($db_link,"vendor_select",$f_vendor_select); ?></td>
</td>
</tr>
</table>
</form>

<br>
<a class="mainButton" href="#modal"><?php print WEB_btn_apply_selected; ?></a>
<div class="remodal" data-remodal-options="closeOnConfirm: true" data-remodal-id="modal" role="dialog" aria-labelledby="modal1Title" aria-describedby="modal1Desc">
 <div class="remodalBorder">
  <button data-remodal-action="close" class="remodal-close" aria-label="Close"></button>
      <form id="formNetDevApply">
        <h2 id="modal1Title"><?php print WEB_selection_title; ?></h2>
        <input type="hidden" name="ApplyForAll" value="MassChange">
        <table class="data" align=center>
        <tr><td><input type=checkbox class="putField" name="e_set_type" value='1'></td><td><?php print WEB_cell_type."</td><td>";print_devtype_select($db_link,'a_dev_type',5);?></td></tr>
        <tr><td><input type=checkbox class="putField" name="e_set_model" value='1'></td><td><?php echo WEB_cell_host_model."</td><td>"; print_devmodels_select($db_link,'a_device_model_id',0,'device_type>2');?></td></tr>
        <tr><td><input type=checkbox class="putField" name="e_set_building" value='1'></td><td><?php echo WEB_location_name."</td><td>"; print_building_select($db_link, 'a_building_id', 0);?></td></tr>
        <tr><td><input type=checkbox class="putField" name="e_set_snmp_version" value='1'></td><td><?php echo WEB_snmp_version."</td><td>";print_snmp_select('a_snmp_version', get_const('snmp_default_version'));?></td></tr>
        <tr><td><input type=checkbox class="putField" name="e_set_ro_community" value='1'></td><td><?php echo WEB_snmp_community_ro."</td><td>"; ?><input type='text' name='a_ro_community' value="<?php print get_const('snmp_default_community'); ?>"></td></tr>
        <tr><td><input type=checkbox class="putField" name="e_set_rw_community" value='1'></td><td><?php echo WEB_snmp_community_rw."</td><td>"; ?><input type='text' name='a_rw_community' value="private"></td></tr>
        </table>
        <input type="submit" name="submit" class="btn" value="<?php echo WEB_btn_apply; ?>">
    </form>
</div>
</div>

<a class="delButton" href="#modalDel"><?php print WEB_btn_delete; ?></a>
<div class="remodal" data-remodal-options="closeOnConfirm: true" data-remodal-id="modalDel" role="dialog" aria-labelledby="modal1Title" aria-describedby="modal1Desc">
 <div class="remodalBorder">
  <button data-remodal-action="close" class="remodal-close" aria-label="Close"></button>
    <form id="formNetDevDel">
        <h2 id="modal1Title"><?php print WEB_msg_delete_selected; ?></h2>
        <input type="hidden" name="RemoveDevice" value="MassChange">
        <?php print_qa_select('f_deleted', 0);?><br><br>
        <input type="submit" name="submit" class="btn" value="<?php echo WEB_btn_apply; ?>">
    </form>
</div>
</div>

<?php

$u_filter='';
if ($unknown and $f_vendor_select==0) { $u_filter=' AND V.id<>1 '; } else {
    if (!empty($f_vendor_select) and $f_vendor_select>=1) {
        $u_filter = " AND V.id=".$f_vendor_select." ";
        }
    }

$countSQL="SELECT Count(*) FROM user_auth A, user_list L, devices D, device_models M, vendors V
WHERE D.user_id=L.id AND A.ip = D.ip AND D.device_model_id=M.id AND M.vendor_id=V.id AND A.deleted =0
$u_filter $ip_list_filter $d_filter";
$count_records = get_single_field($db_link,$countSQL);
$total=ceil($count_records/$displayed);
if ($page>$total) { $page=$total; }
if ($page<1) { $page=1; }
$start = ($page * $displayed) - $displayed;
print_navigation($page_url,$page,$displayed,$count_records,$total);
?>
<br>

<form id="def" name="def">

<table class="data">
    <tr>
        <td align=Center><input type="checkbox" onClick="checkAll(this.checked);"></td>
        <td align=Center><?php print $sort_url . "&sort=login&order=$new_order>" . WEB_cell_login . "</a>"; ?></td>
        <td align=Center><?php print $sort_url . "&sort=ip_int&order=$new_order>" . WEB_cell_ip . "</a>"; ?></td>
        <td align=Center><?php print $sort_url . "&sort=mac&order=$new_order>" . WEB_cell_mac . "</a>"; ?></td>
        <td align=Center><?php print $sort_url . "&sort=model_name&order=$new_order>".WEB_cell_host_model; ?></td>
        <td align=Center><?php print WEB_cell_description; ?></td>
        <td align=Center><?php print WEB_cell_connection; ?></td>
        <td align=Center><?php print $sort_url . "&sort=last_found&order=$new_order>".WEB_cell_last_found."</a>"; ?></td>
    </tr>
<?php

$sSQL = "SELECT A.id, D.id as dev_id, D.device_type, A.ip, A.mac, A.user_id, L.login, D.description, A.last_found, V.name, M.model_name
FROM user_auth A, user_list L, devices D, device_models M, vendors V
WHERE D.user_id=L.id AND A.ip = D.ip AND D.device_model_id=M.id AND M.vendor_id=V.id AND A.deleted =0
$u_filter $ip_list_filter $d_filter
ORDER BY $sort_table.$sort_field $order LIMIT $start,$displayed";

$users = get_records_sql($db_link,$sSQL);
foreach ($users as $user) {
    if ($user['last_found'] == '0000-00-00 00:00:00') { $user['last_found'] = ''; }
    print "<tr align=center>\n";
    $cl = "data";
    print "<td class='".$cl."' style='padding:0'><input type=checkbox name=fid[] value=".$user['dev_id']."></td>\n";
    print "<td class='".$cl."' ><a href=/admin/devices/editdevice.php?id=".$user['dev_id'].">" . $user['login'] . "</a></td>\n";
    print "<td class='".$cl."' ><a href=/admin/users/edituser.php?id=".$user['user_id'].">" . $user['ip'] . "</a></td>\n";
    print "<td class='".$cl."' >" . expand_mac($db_link,$user['mac']) . "</td>\n";
    print "<td class='".$cl."' >".$user['name'].' '.$user['model_name']."</td>\n";
    print "<td class='".$cl."' >".$user['description']."</td>\n";
    print "<td class='data'>" . get_connection($db_link, $user['id']) . "</td>\n";
    print "<td class='".$cl."' >".$user['last_found']."</td>\n";
    print "</tr>\n";
}
print "</table>\n";
print_navigation($page_url,$page,$displayed,$count_records[0],$total);
?>
<br>
<table class="data">
<tr><td><?php echo WEB_color_description; ?></td></tr>
<tr>
<td class="warn"><?php echo WEB_color_user_disabled; ?></td>
<td class="error"><?php echo WEB_color_user_blocked; ?></td>
</table>
</form>

<script src="/js/remodal/remodal.min.js"></script>
<script src="/js/remodal-devices.js"></script>

<?php
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/footer.php");
?>
