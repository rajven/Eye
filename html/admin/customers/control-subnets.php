<?php

require_once ($_SERVER['DOCUMENT_ROOT']."/inc/auth.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/languages/" . HTML_LANG . ".php");

if (isset($_POST["s_remove"])) {
    $s_id = $_POST["s_id"];
    if (!empty($s_id)) {
        foreach ($s_id as $key => $val) {
            if (isset($val)) {
                LOG_INFO($db_link, "Remove subnet id: $val");
                delete_record($db_link, "subnets", "id=" . $val);
                }
            }
        }
    header("Location: " . $_SERVER["REQUEST_URI"]);
    exit;
}

if (isset($_POST['s_save'])) {
    $len = is_array($_POST['s_save']) ? count($_POST['s_save']) : 0;
    for ($i = 0; $i < $len; $i ++) {
        $save_id = intval($_POST['s_save'][$i]);
        $len_all = is_array($_POST['n_id']) ? count($_POST['n_id']) : 0;
        for ($j = 0; $j < $len_all; $j ++) {
            if (intval($_POST['n_id'][$j]) != $save_id) { continue; }
            $new['subnet'] = trim($_POST['s_subnet'][$j]);
            $new['office'] = $_POST['s_office'][$j]*1;
            $new['hotspot'] = $_POST['s_hotspot'][$j]*1;
            $new['vpn'] = $_POST['s_vpn'][$j]*1;
            $new['free'] = $_POST['s_free'][$j]*1;
            $new['dhcp'] = $_POST['s_dhcp'][$j]*1;
            $new['dhcp_lease_time'] = $_POST['s_lease_time'][$j]*1;
            $new['static'] = $_POST['s_static'][$j]*1;
            $new['discovery'] = $_POST['s_discovery'][$j]*1;
            $new['dhcp_update_hostname'] = $_POST['s_dhcp_update'][$j]*1;
            $new['comment'] = trim($_POST['s_comment'][$j]);
            $range = cidrToRange($new['subnet']);
	    $first_user_ip = $range[0];
	    $last_user_ip = $range[1];
            $cidr = $range[2][1];
	    if (isset($cidr) and $cidr <= 32) {
	        $new['subnet'] = $first_user_ip . '/' . $cidr;
		} else {
	        $new['subnet'] = '';
		}
            $new['ip_int_start'] = ip2long($first_user_ip);
	    $new['ip_int_stop'] = ip2long($last_user_ip);
            $new['dhcp_start'] = ip2long(trim($_POST['s_dhcp_start'][$j]));
            $new['dhcp_stop'] = ip2long(trim($_POST['s_dhcp_stop'][$j]));
            $dhcp_fail=0;
            if (!isset($new['dhcp_start']) or $new['dhcp_start']==0) { $dhcp_fail=1; }
            if (!isset($new['dhcp_stop']) or $new['dhcp_stop']==0) { $dhcp_fail=1; }
            if (!$dhcp_fail and ($new['dhcp_start']-$new['ip_int_stop'] >= 0)) { $dhcp_fail=1; }
            if (!$dhcp_fail and ($new['dhcp_start']-$new['ip_int_start'] <= 0)) { $dhcp_fail=1; }
            if (!$dhcp_fail and ($new['dhcp_stop']-$new['ip_int_stop']>=0)) { $dhcp_fail=1; }
            if (!$dhcp_fail and ($new['dhcp_stop']-$new['ip_int_start']<=0)) { $dhcp_fail=1; }
            if (!$dhcp_fail and ($new['dhcp_start']-$new['dhcp_stop']>=0)) { $dhcp_fail=1; }
            if ($dhcp_fail) {
        	$new['dhcp_start']=ip2long($range[3]);
        	$new['dhcp_stop']=ip2long($range[4]);
        	}
	    $gateway = ip2long(trim($_POST['s_gateway'][$j]));
	    if (!isset($gateway)) { $gateway=$range[5]; }
	    $new['gateway']=$gateway;
	    if ($new['hotspot']) {
        	$new['dhcp_update_hostname'] = 0;
        	$new['discovery'] = 0;
        	$new['vpn'] = 0;
		}
	    if ($new['vpn']) {
        	$new['discovery'] = 0;
        	$new['dhcp'] = 0;
		}
	    if ($new['office']) {
        	$new['free'] = 0;
        	}
            if (!$new['office']) {
        	$new['discovery'] = 0;
        	$new['dhcp'] = 0;
        	$new['static'] = 0;
        	$new['dhcp_update_hostname'] = 0;
        	$new['gateway'] = 0;
        	$new['dhcp_start'] = 0;
        	$new['dhcp_stop'] = 0;
        	}
            update_record($db_link, "subnets", "id='{$save_id}'", $new);
        }
    }
    header("Location: " . $_SERVER["REQUEST_URI"]);
    exit;
}

if (isset($_POST["s_create"])) {
    $new_subnet = $_POST["s_create_subnet"];
    if (isset($new_subnet)) {
        $new['subnet'] = trim($new_subnet);
        $range = cidrToRange($new['subnet']);
        $first_user_ip = $range[0];
        $last_user_ip = $range[1];
        $cidr = $range[2][1];
        if (isset($cidr) and $cidr < 32) {
            $ip = $first_user_ip . '/' . $cidr;
        } else {
            $ip = $first_user_ip;
        }
        $new['ip_int_start'] = ip2long($first_user_ip);
        $new['ip_int_stop'] = ip2long($last_user_ip);
    	$new['dhcp_start'] = ip2long($range[3]);
    	$new['dhcp_stop'] = ip2long($range[4]);
    	$new['gateway'] = ip2long($range[5]);
        LOG_INFO($db_link, "Create new subnet $new_subnet");
        insert_record($db_link, "subnets", $new);
    }
    header("Location: " . $_SERVER["REQUEST_URI"]);
    exit;
}

unset($_POST);

fix_auth_rules($db_link);

require_once ($_SERVER['DOCUMENT_ROOT']."/inc/header.php");

print_control_submenu($page_url);

?>
<div id="cont">
<br>
<form name="def" action="control-subnets.php" method="post">
<b>Сети организации</b> <br>
<table class="data">
<tr align="center">
	<td></td>
	<td width=30><b>id</b></td>
	<td><b>Сеть</b></td>
	<td><b>Шлюз</b></td>
	<td><b>DHCP</b></td>
	<td><b>Static</b></td>
	<td><b>DHCP start</b></td>
	<td><b>DHCP end</b></td>
	<td><b>Lease time,m</b></td>
	<td><b>Офисная</b></td>
	<td><b>Хот-спот</b></td>
	<td><b>VPN</b></td>
	<td><b>Free</b></td>
	<td><b>Обновлять dns</b></td>
	<td><b>Discovery</b></td>
	<td><b>Комментарий</b></td>
	<td><input type="submit" onclick="return confirm('Удалить?')" name="s_remove" value="Удалить"></td>
</tr>
<?php
$t_subnets = get_records($db_link,'subnets','True ORDER BY ip_int_start');
foreach ( $t_subnets as $row ) {
    print "<tr align=center>\n";
    $cl="data";
    print "<td class=\"$cl\" style='padding:0'><input type=checkbox name=s_id[] value='{$row['id']}'></td>\n";
    print "<td class=\"$cl\"><input type=\"hidden\" name='n_id[]' value='{$row['id']}'>{$row['id']}</td>\n";
    print "<td class=\"$cl\"><input type=\"text\" name='s_subnet[]' value='{$row['subnet']}' size='18'></td>\n";
    $cell_disabled='';
    if ($row['office'] and !$row['vpn']) {
	$default_range=cidrToRange($row['subnet']);
        if (!isset($row['dhcp_start']) or !($row['dhcp_start']>0)) { $row['dhcp_start']=ip2long($default_range[3]); }
        if (!isset($row['dhcp_stop']) or !($row['dhcp_stop']>0)) { $row['dhcp_stop']=ip2long($default_range[4]); }
	} else {
	$cell_disabled='readonly=true';
	$cl='down';
	}
    print "<td class=\"$cl\"><input type=\"text\" name='s_gateway[]' value='".long2ip($row['gateway'])."'  size='15' $cell_disabled></td>\n";
    if ($row['dhcp']) { $cl = 'up'; } else { $cl = 'data'; }
    print "<td class=\"$cl\">"; print_qa_select("s_dhcp[]",$row['dhcp']); print "</td>\n";
    if ($row['static']) { $cl = 'up'; } else { $cl = 'data'; }
    print "<td class=\"$cl\">"; print_qa_select("s_static[]",$row['static']); print "</td>\n";
    $cl = 'data';
    print "<td class=\"$cl\"><input type=\"text\" name='s_dhcp_start[]' value='".long2ip($row['dhcp_start'])."' size='15' $cell_disabled></td>\n";
    print "<td class=\"$cl\"><input type=\"text\" name='s_dhcp_stop[]' value='".long2ip($row['dhcp_stop'])."' size='15' $cell_disabled></td>\n";
    print "<td class=\"$cl\"><input type=\"text\" name='s_lease_time[]' value='".$row['dhcp_lease_time']."'size='3' $cell_disabled></td>\n";
    $row_cl = 'data';
    if (!$row['office']) { $row_cl='down'; }
    if ($row['office']) { $cl = 'up'; } else { $cl = 'data'; }
    print "<td class=\"$cl\">";
    print_qa_select("s_office[]",$row['office']);
    print "</td>\n";
    if ($row_cl ==='data' and $row['hotspot']) { $cl = 'up'; } else { $cl = $row_cl; }
    print "<td class=\"$cl\">";
    print_qa_select_ext("s_hotspot[]",$row['hotspot'],!$row['office']);
    print "</td>\n";
    if ($row_cl ==='data' and $row['vpn']) { $cl = 'up'; } else { $cl = $row_cl; }
    print "<td class=\"$cl\">";
    print_qa_select_ext("s_vpn[]",$row['vpn'],!$row['office']);
    print "</td>\n";
    if ($row['free']) { $cl = 'up'; } else { $cl = $row_cl; }
    print "<td class=\"$cl\">";
    print_qa_select("s_free[]",$row['free']);
    print "</td>\n";
    if ($row_cl ==='data' and $row['dhcp_update_hostname']) { $cl = 'up'; } else { $cl = $row_cl; }
    print "<td class=\"$cl\">";
    print_qa_select_ext("s_dhcp_update[]",$row['dhcp_update_hostname'],!$row['office']);
    print "</td>\n";
    if ($row_cl ==='data' and $row['discovery']) { $cl = 'up'; } else { $cl = $row_cl; }
    print "<td class=\"$cl\">";
    print_qa_select_ext("s_discovery[]",$row['discovery'],!$row['office']);
    print "</td>\n";
    print "<td class=\"data\"><input type=\"text\" name='s_comment[]' value='{$row['comment']}'></td>\n";
    print "<td class=\"data\"><button name='s_save[]' value='{$row['id']}'>Сохранить</button></td>\n";
    print "</tr>\n";
}
?>
<tr>
<td colspan=6>Новая сеть :<?php print "<input type=\"text\" name='s_create_subnet' value=''>"; ?></td>
<td><input type="submit" name="s_create" value="Добавить"></td>
</tr>
</table>
</form>
<?php require_once ($_SERVER['DOCUMENT_ROOT']."/inc/footer.php"); ?>
