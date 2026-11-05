<?php
$default_displayed=500;
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/auth.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/languages/" . HTML_LANG . ".php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/header.php");
$default_sort='device_name';
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/oufilter.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/devtypesfilter.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/buildingfilter.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/sortfilter.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/enabledfilter.php");

// the host of the monitoring system is a device of eye, so this page lists the devices
$sort_table = 'devices';
if ($sort_field == 'login') { $sort_table = 'user_list'; }
if ($sort_field == 'model_name') { $sort_table = 'device_models'; }

$sort_url = "<a href=monitoring.php?ou=" . $rou;

$params=[];
if ($rou == 0) { $ou_filter = ''; } else { $ou_filter = " and user_list.ou_id=?"; $params[]=$rou; }

if ($f_devtype_id <0) { $type_filter = ''; } else { $type_filter = " and devices.device_type=?"; $params[]=$f_devtype_id; }

if ($f_building_id > 0) { $building_filter = " and devices.building_id=?"; $params[]=$f_building_id; } else { $building_filter=''; }

// the monitoring state of the device is the state of its host in the monitoring system
$enabled_filter='';
if ($enabled>0) {
    if ($enabled===2) { $enabled_filter = ' and devices.monitoring=1'; }
    if ($enabled===1) { $enabled_filter = ' and devices.monitoring=0'; }
    }

$device_list_filter = $ou_filter.$type_filter.$building_filter.$enabled_filter;

print_ip_submenu($page_url);

?>
<div id="cont">
<form name="filter" action="monitoring.php" method="post">
<table class="data">
	<tr>
        <td>
        <b><?php print WEB_cell_ou; ?> :</b><?php print_ou_select($db_link, 'ou', $rou); ?>
        <b><?php print WEB_device_type_show; ?> :</b><?php print_devtypes_select($db_link, 'devtypes', $f_devtype_id, ""); ?>
        <b><?php print WEB_location_name; ?> - </b><?php print_building_select($db_link, 'building_id', $f_building_id); ?>
        <b><?php print WEB_monitoring; ?> :</b><?php print_enabled_select('enabled', $enabled); ?>
        <?php print WEB_rows_at_page."&nbsp"; print_row_at_pages('rows',$displayed); ?>
        <input id="btn_filter" name="btn_filter" type="submit" value="<?php print WEB_btn_show; ?>">
        </td>
	</tr>
</table>
</form>

<a class="mainButton" href="#eye-mass"><?php print WEB_btn_apply_selected; ?></a>
<dialog id="eye-mass" class="eye-dlg">
  <button type="button" class="x" data-close aria-label="<?php echo WEB_btn_close; ?>">&#10005;</button>
  <form id="formNetDevApply">
        <h2><?php print WEB_selection_title; ?></h2>
        <input type="hidden" name="ApplyForAll" value="MassChange">
        <table class="data" align=center>
        <?php if (is_monitoring_enabled()): ?>
            <tr><td><input type=checkbox class="putField" name="e_monitoring" value='1'></td><td><?php print WEB_monitoring; ?>&nbsp<?php print_qa_select('a_monitoring', 1); ?></td></tr>
        <?php endif; ?>
        <?php if (is_nagios_enabled()): ?>
            <tr><td><input type=checkbox class="putField" name="e_link" value='1'></td><td><?php print WEB_cell_link; ?>&nbsp<?php print_qa_select('a_link', 0); ?></td></tr>
            <tr><td><input type=checkbox class="putField" name="e_handler" value='1'></td><td><?php print WEB_cell_nagios_handler; ?>&nbsp<?php print_nagios_handler_select($db_link,'a_handler'); ?></td></tr>
        <?php endif; ?>
        <?php if (is_zabbix_enabled()): ?>
            <tr><td><input type=checkbox class="putField" name="e_tag_add" value='1'></td><td><?php print WEB_zabbix_mass_tag_add; ?>&nbsp<?php print_zabbix_tag_id_select('a_tag_add', zabbix_global_names($db_link, 'tags'), WEB_zabbix_tag_select_model_empty); ?>&nbsp<?php print WEB_zabbix_value; ?>&nbsp<input type="text" name="a_tag_value" value="" size=12></td></tr>
            <tr><td><input type=checkbox class="putField" name="e_tag_del" value='1'></td><td><?php print WEB_zabbix_mass_tag_del; ?>&nbsp<?php print_zabbix_tag_id_select('a_tag_del', zabbix_global_names($db_link, 'tags'), WEB_zabbix_tag_select_model_empty); ?></td></tr>
            <tr><td><input type=checkbox class="putField" name="e_macro_add" value='1'></td><td><?php print WEB_zabbix_mass_macro_add; ?>&nbsp<?php print_zabbix_macro_id_select('a_macro_add', zabbix_global_names($db_link, 'macros'), WEB_zabbix_macro_select_model_empty); ?>&nbsp<?php print WEB_zabbix_value; ?>&nbsp<input type="text" name="a_macro_value" value="" size=12></td></tr>
            <tr><td><input type=checkbox class="putField" name="e_macro_del" value='1'></td><td><?php print WEB_zabbix_mass_macro_del; ?>&nbsp<?php print_zabbix_macro_id_select('a_macro_del', zabbix_global_names($db_link, 'macros'), WEB_zabbix_macro_select_model_empty); ?></td></tr>
        <?php endif; ?>
        </table>
        <input type="submit" name="submit" class="btn" value="<?php echo WEB_btn_apply; ?>">
    </form>
</dialog>

<?php
// an address of the user has no host itself, the device of the user is the host, so the
// owner of the device is joined to show the organizational unit of the equipment
$countSQL="SELECT Count(*) FROM devices
  LEFT JOIN user_list ON user_list.id = devices.user_id
  WHERE devices.deleted = 0 $device_list_filter";
$count_records = get_single_field($db_link,$countSQL, $params);
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
		<td align=Center><?php print $sort_url . "&sort=device_name&order=$new_order>" . WEB_cell_name . "</a>"; ?></td>
		<td align=Center><?php print $sort_url . "&sort=ip_int&order=$new_order>" . WEB_cell_ip . "</a>"; ?></td>
		<td align=Center><?php print $sort_url . "&sort=device_type&order=$new_order>" . WEB_cell_type . "</a>"; ?></td>
		<td align=Center><?php print $sort_url . "&sort=login&order=$new_order>" . WEB_cell_login . "</a>"; ?></td>
		<td align=Center><?php print $sort_url . "&sort=monitoring&order=$new_order>" . WEB_cell_monitoring; ?></td>
		<td align=Center><?php print $sort_url . "&sort=link_check&order=$new_order>" . WEB_cell_link; ?></td>
		<td align=Center><?php print WEB_cell_arp_found; ?></td>
		<td align=Center><?php print WEB_cell_wikiname; ?></td>
		<td align=Center><?php print WEB_zabbix_macros_device . '&nbsp' . WEB_zabbix_tags_device; ?></td>
	</tr>
<?php

$sSQL = "SELECT devices.*, user_list.login, device_models.model_name FROM devices
  LEFT JOIN user_list ON user_list.id = devices.user_id
  LEFT JOIN device_models ON device_models.id = devices.device_model_id
  WHERE devices.deleted = 0 $device_list_filter
ORDER BY $sort_table.$sort_field $order LIMIT ? OFFSET ?";
$params[]=$displayed;
$params[]=$start;

$devices_list = get_records_sql($db_link,$sSQL, $params);

// number of the macros and of the tags which every device of the page carries
$counts = zabbix_device_binding_counts($db_link, array_column($devices_list, 'id'));

// the last time the arp of the address of every device of the page was seen; the addresses of the
// page are asked at once, so a row of the table does not make a query of its own. the device and
// its address are matched by the ip as they are in the list of the passive devices
$arp_seen = array();
$ips = array();
foreach ($devices_list as $row) {
    if (!empty($row['ip'])) {
        $ips[$row['ip']] = true;
    }
}

if ($ips) {
    $keys = array_keys($ips);
    $sql_arp = 'SELECT ip, arp_found FROM user_auth WHERE deleted = 0 AND ip IN ('
        . implode(',', array_fill(0, count($keys), '?')) . ') ORDER BY arp_found DESC';
    foreach (get_records_sql($db_link, $sql_arp, $keys) as $row) {
        // the address is unique over the live records, the first of them is the freshest one
        if (!isset($arp_seen[$row['ip']])) {
            $arp_seen[$row['ip']] = $row['arp_found'];
        }
    }
}

foreach ($devices_list as $device) {
    print "<tr align=center>\n";
    $cl = "data";
    if ((int)$device['monitoring'] === 1) {
        if ($device['monitoring_status'] === 'UP') { $cl = "up"; }
        if ($device['monitoring_status'] === 'DOWN' or $device['monitoring_status'] === 'HARDDOWN'
            or $device['monitoring_status'] === 'SOFTDOWN') { $cl = "down"; }
        }

    print "<td class=\"$cl\" style='padding:0'><input type=checkbox name=fid[] value=".(int)$device['id']."></td>\n";

    // a name of a record is a text, it is escaped by create_url(), only the marks of eye
    // itself are passed as a markup, print_url_html()
    print "<td class=\"$cl\">";
    print_url((string)$device['device_name'], '/admin/devices/editdevice.php?id=' . (int)$device['id']);
    print "</td>\n";
    print "<td class=\"$cl\">" . htmlspecialchars((string)$device['ip'], ENT_QUOTES, 'UTF-8') . "</td>\n";
    print "<td class=\"$cl\">" . htmlspecialchars((string)get_devtype_name($db_link, (int)$device['device_type']),
        ENT_QUOTES, 'UTF-8') . "</td>\n";
    if (!empty($device['login'])) {
        print "<td class=\"$cl\">";
        print_url((string)$device['login'], '/admin/users/edituser.php?id=' . (int)$device['user_id']);
        print "</td>\n";
    } else {
        print "<td class=\"$cl\" ></td>\n";
        }

    // the name of the host is a link to the page of the monitoring system
    if ((int)$device['monitoring'] === 1) {
        $monitoring_link = get_host_monitoring_link($db_link, $device);
        if ($monitoring_link === NULL) { print "<td class=\"$cl\">" . get_qa($device['monitoring']) . "</td>\n"; } else {
            // the mark stays a markup, the link of the host is built by the monitoring rules
            print "<td class=\"$cl\">";
            print_url_html(get_qa($device['monitoring']), $monitoring_link);
            print "</td>\n";
            }
        } else {
        print_td_qa($device['monitoring'],FALSE,$cl);
        }

    print_td_qa($device['link_check'],FALSE,$cl);

    // the reaction to an event is edited in the form of the device and by the mass operation
    // below, the column of the table shows the last arp of the address of this device
    $seen = $arp_seen[$device['ip']] ?? '';
    if (is_empty_datetime($seen)) { $seen = ''; }
    if ($seen === '') {
        print "<td class=\"$cl\">-</td>\n";
    } else {
        print "<td class=\"$cl\">" . htmlspecialchars((string)$seen, ENT_QUOTES, 'UTF-8') . "</td>\n";
    }
    if (!empty($device['wikiname'])) {
        $wiki_url = rtrim((string)get_option($db_link, 60),'/');
        if ($wiki_url === '' or preg_match('/127.0.0.1/', $wiki_url)) { print "<td class=\"$cl\" ></td>\n"; } else {
            $wiki_web = ltrim(rtrim((string)get_option($db_link, 63),'/'),'/');
            $wiki_link = $wiki_url.'/'.$wiki_web.'/'.$device['wikiname'];
            print "<td class=\"$cl\" >"; print_url($device['wikiname'],$wiki_link); print "</td>\n";
            }
        } else {
        print "<td class=\"$cl\" ></td>\n";
        }

    $device_id = (int)$device['id'];
    print "<td class=\"$cl\" >" . ($counts[$device_id]['macros'] ?? 0) . "&nbsp" . WEB_zabbix_macro
        . "&nbsp|&nbsp" . ($counts[$device_id]['tags'] ?? 0) . "&nbsp" . WEB_zabbix_tag . "</td>\n";
    print "</tr>\n";
}
print "</table>\n";
print_navigation($page_url,$page,$displayed,$count_records,$total);
?>
<br>
<table class="data">
<tr><td><?php echo WEB_color_description; ?></td></tr>
<tr>
<td class="up"><?php echo WEB_monitoring_host_up; ?></td>
<td class="down"><?php echo WEB_monitoring_host_down; ?></td>
<td class="data"><?php echo WEB_monitoring_host_unknown; ?></td>
</table>

<script>
document.getElementById('ou').addEventListener('change', function(event) {
  document.getElementById('btn_filter').click();
});

document.getElementById('devtypes').addEventListener('change', function(event) {
  document.getElementById('btn_filter').click();
});

document.getElementById('building_id').addEventListener('change', function(event) {
  document.getElementById('btn_filter').click();
});

document.getElementById('enabled').addEventListener('change', function(event) {
  document.getElementById('btn_filter').click();
});

document.getElementById('rows').addEventListener('change', function(event) {
  document.getElementById('btn_filter').click();
});


</script>

<?php
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/footer.php");
?>
