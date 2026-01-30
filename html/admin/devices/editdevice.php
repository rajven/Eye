<?php
require_once($_SERVER['DOCUMENT_ROOT'] . "/inc/auth.php");
require_once($_SERVER['DOCUMENT_ROOT'] . "/inc/languages/" . HTML_LANG . ".php");
require_once($_SERVER['DOCUMENT_ROOT'] . "/inc/idfilter.php");

$device = get_record($db_link, 'devices', "id = ?", [$id]);
$user_info = get_record_sql($db_link, "SELECT * FROM user_list WHERE id = ?", [$device['user_id']]);

if (getPOST("editdevice") !== null && isset($id)) {
    // === УПРАВЛЕНИЕ ПОРТАМИ ====================================================
    $sw_ports = (int)getPOST("f_port_count", null, 0);
    $sSQL = "SELECT COUNT(id) FROM device_ports WHERE device_ports.device_id = ?";
    $d_ports = (int)get_single_field($db_link, $sSQL, [$id]);

    if ($d_ports != $sw_ports) {
        LOG_DEBUG($db_link, "Device id: $id changed port count!");
        
        if ($sw_ports > $d_ports) {
            $start_port = $d_ports + 1;
            LOG_DEBUG($db_link, "Device id: $id add connection for port from $start_port to $sw_ports.");
            for ($port = $start_port; $port <= $sw_ports; $port++) {
                insert_record($db_link, "device_ports", [
                    'device_id'   => $id,
                    'snmp_index'  => $port,
                    'port'        => $port
                ]);
            }
        }
        
        if ($sw_ports < $d_ports) {
            LOG_DEBUG($db_link, "Device id: $id remove connection for port from $d_ports to $sw_ports");
            for ($port = $d_ports; $port > $sw_ports; $port--) {
                $port_id = get_id_record($db_link, 'device_ports', "device_id = ? AND port = ?", [$id, $port]);
                if ($port_id) {
                    delete_record($db_link, "device_ports", "id = ?", [$port_id]);
                    delete_records($db_link, "connections", "port_id = ?", [$port_id]);
                } else {
                    LOG_DEBUG($db_link, "Device id: $id port_id not found for port: $port!");
                }
            }
        }
    }

    // === ОСНОВНЫЕ ДАННЫЕ УСТРОЙСТВА ============================================
    $new = [];
    $cur_device = get_record_sql($db_link, "SELECT * FROM devices WHERE id = ?", [$id]);

    // IP-адрес
    $f_ip = trim(getPOST("f_ip", null, ''));
    if ($f_ip !== '') {
        $new['ip'] = $f_ip;
        $new['ip_int'] = ip2long($f_ip);
        $cur_auth = get_record_sql($db_link, "SELECT * FROM user_auth WHERE deleted = 0 AND ip = ?", [$f_ip]);
    }

    // Модель устройства
    $f_device_model_id = (int)getPOST("f_device_model_id", null, 0);
    if ($f_device_model_id > 0) {
        $new['device_model_id'] = $f_device_model_id;
        $new['vendor_id'] = get_device_model_vendor($db_link, $f_device_model_id);
    }

    // Количество портов
    $new['port_count'] = $sw_ports;

    // Тип устройства
    $new['device_type'] = (int)getPOST("f_devtype_id", null, 0);

    // === УПРАВЛЕНИЕ ЭКЗЕМПЛЯРАМИ ФИЛЬТРОВ ======================================
    if ($new['device_type'] == 2) {
        // Это шлюз — должен иметь хотя бы один экземпляр
        $instances_count = get_count_records($db_link, 'device_filter_instances', 'device_id = ?', [$id]);
        if (empty($instances_count) || $instances_count == 0) {
            // Создаём стандартный экземпляр (ID=1)
            insert_record($db_link, "device_filter_instances", [
                'instance_id' => 1,
                'device_id'   => $id
            ]);
        }
    } else {
        // Не шлюз — удаляем все экземпляры
        if ($device['device_type'] == 2) {
            $instances_count = get_count_records($db_link, 'device_filter_instances', 'device_id = ?', [$id]);
            if (!empty($instances_count) && $instances_count > 0) {
                delete_records($db_link, 'device_filter_instances', 'device_id = ?', [$id]);
            }
        }
    }

    // === ОСТАЛЬНЫЕ ПОЛЯ =========================================================
    $new['description']           = trim(getPOST("f_description", null, ''));
    $new['sn']                    = trim(getPOST("f_sn", null, ''));
    $new['firmware']              = trim(getPOST("f_firmware", null, ''));

    // SNMP
    $new['snmp_version']          = (int)getPOST("f_snmp_version", null, 0);
    $new['community']             = substr(trim(getPOST("f_community", null, '')), 0, 50);
    $new['snmp3_auth_proto']      = substr(trim(getPOST("f_snmp3_auth_proto", null, '')), 0, 10);
    $new['snmp3_priv_proto']      = substr(trim(getPOST("f_snmp3_priv_proto", null, '')), 0, 10);
    $new['rw_community']          = substr(trim(getPOST("f_rw_community", null, '')), 0, 50);
    $new['snmp3_user_rw']         = substr(trim(getPOST("f_snmp3_user_rw", null, '')), 0, 20);
    $new['snmp3_user_ro']         = substr(trim(getPOST("f_snmp3_user_ro", null, '')), 0, 20);
    $new['snmp3_user_rw_password']= substr(trim(getPOST("f_snmp3_user_rw_password", null, '')), 0, 20);
    $new['snmp3_user_ro_password']= substr(trim(getPOST("f_snmp3_user_ro_password", null, '')), 0, 20);

    // ACL и настройки
    $new['queue_enabled']         = (int)getPOST("f_queue_enabled", null, 0);
    $new['connected_user_only']   = (int)getPOST("f_connected_user_only", null, 0);
    $new['dhcp']                  = (int)getPOST("f_dhcp", null, 0);
    $new['user_acl']              = (int)getPOST("f_user_acl", null, 0);

    // Расположение
    $new['building_id']           = (int)getPOST("f_building_id", null, 0);

    // Доступ
    $new['login']                 = trim(getPOST("f_login", null, ''));
    $f_password                   = getPOST("f_password", null, '');
    if ($f_password !== '' && !preg_match('/^\*+$/', $f_password)) {
        $new['password'] = crypt_string($f_password);
    }

    $new['protocol']              = (int)getPOST("f_protocol", null, 0);
    $new['control_port']          = (int)getPOST("f_control_port", null, 0);
    $new['netflow_save']          = (int)getPOST("f_save_netflow", null, 0);

    // Discovery
    $new['discovery']             = trim(getPOST("f_discovery", null, 0));

    // Nagios
    $f_nagios                     = (int)getPOST("f_nagios", null, -1);
    if ($f_nagios !== -1) {
        $new['nagios'] = $f_nagios;
        if ($new['nagios'] == 0) {
            $new['nagios_status'] = 'UP';
        }
    } else {
        if (!empty($cur_auth)) {
            $new['nagios'] = 0;
            $new['nagios_status'] = $cur_auth['nagios_status'];
        }
    }

    // === ЗАВИСИМОСТИ ПО ТИПУ УСТРОЙСТВА ========================================
    if ($new['device_type'] == 0 || $new['protocol'] < 0) {
        $new['queue_enabled']       = 0;
        $new['connected_user_only'] = 1;
        $new['user_acl']            = 0;
    }

    // === СОХРАНЕНИЕ =============================================================
    update_record($db_link, "devices", "id = ?", $new, [$id]);
    header("Location: " . $_SERVER["REQUEST_URI"]);
    exit;
}

unset($_POST);

require_once($_SERVER['DOCUMENT_ROOT'] . "/inc/header.php");

print_device_submenu($page_url);
print_editdevice_submenu($page_url, $id, $device['device_type'], $user_info['login']);

?>
<div id="contsubmenu">

    <?php
    if (!empty($_GET['status'])) {
        if ($_GET['status'] === 'locked') {
            print '<div id="msg">' . WEB_device_locked . '</div>';
        }
    }
    ?>

    <form name="def" action="editdevice.php?id=<?php echo $id; ?>" method="post">
        <table class="data">
            <tr>
                <td><?php echo WEB_cell_name; ?></td>
                <td><?php echo WEB_cell_ip; ?></td>
                <td><?php echo WEB_cell_type; ?></td>
                <?php
                if ($device['device_type'] <= 2) {
                    print "<td>" . WEB_device_port_count . "</td>";
                } else {
                    print "<td></td>";
                }
                print "</tr>";
                print "<tr>\n";
                print "<td class='data'><a href=/admin/users/edituser.php?id=" . $device['user_id'] . ">" . $user_info['login'] . "</a></td>\n";
                print "<td class='data'>";
                print_device_ip_select($db_link, 'f_ip', $device['ip'], $device['user_id']);
                print "</td>\n";
                print "<td class='data'>";
                print_devtype_select($db_link, 'f_devtype_id', $device['device_type']);
                print "</td>\n";
                if ($device['device_type'] <= 2) {
                    print "<td class='data'><input type='text' name='f_port_count' value='" . $device['port_count'] . "' size=5></td>\n";
                } else {
                    print "<td></td>";
                }
                print "</tr>\n";
                ?>
            </tr>
            <td colspan=2><?php echo WEB_cell_host_model; ?></td>
            <td><?php echo WEB_cell_host_firmware; ?></td>
            <td><?php echo WEB_cell_sn; ?></td>
            <?php

            //common information
            print "<tr>\n";
            print "<td class='data' colspan=2>";
            print_device_model_select($db_link, 'f_device_model_id', $device['device_model_id']);
            print "</td>\n";
            print "<td class='data' ><input type='text' name='f_firmware' value='" . $device['firmware'] . "' class='full-width'></td>\n";
            print "<td class='data' ><input type='text' name='f_sn' value='" . $device['sn'] . "' class='full-width'></td>\n";
            print "</tr>\n";
            print "<tr><td colspan=2>" . WEB_location_name . "</td><td colspan=2>" . WEB_cell_description . "</td>";
            print "</tr><tr>";
            print "<td class='data'>";
            print_building_select($db_link, 'f_building_id', $device['building_id']);
            print "</td>\n";
            print "<td class='data' colspan=3><input type='text' size=50 name='f_description' value='" . $device['description'] . "' class='full-width'></td>\n";
            print "</tr>";

            //print gateway settings
            if ($device['device_type'] == 2) {
                print "<tr><td>"; print_url(WEB_device_access_control,"/admin/devices/edit_gw_instances.php?id=$id", 'linkButton'); print "</td><td>" . WEB_device_dhcp_server . "</td><td>" . WEB_device_queues_enabled . "</td><td>" . WEB_device_connected_only . "</td></tr>";
                print "<tr>";
                print "<td class='data'>";
                print_qa_select('f_user_acl', $device['user_acl']);
                print "</td>\n";
                print "<td class='data'>";
                print_qa_select('f_dhcp', $device['dhcp']);
                print "</td>\n";
                print "<td class='data'>";
                print_qa_select('f_queue_enabled', $device['queue_enabled']);
                print "</td>\n";
                print "<td class='data'>";
                print_qa_select('f_connected_user_only', $device['connected_user_only']);
                print "</td>\n";
                print "</tr>\n";
                print "<tr><td colspan=2>";
                print_url(WEB_list_l3_interfaces, "/admin/devices/edit_l3int.php?id=$id",'linkButton');
                print "</td>";
                print "<td colspan=2>";
                print_url(WEB_list_gateway_subnets, "/admin/devices/edit_gw_subnets.php?id=$id", 'linkButton');
                print "</td></tr>";
                print "<tr><td colspan=2 class='data'>";
                print get_l3_interfaces($db_link, $device['id']);
                print "</td>";
                print "<td colspan=2 class='data'>";
                print get_gw_subnets($db_link, $device['id']);
                print "</td></tr>";
            }

            //print router settings
            if ($device['device_type'] == 0) {
                print "<tr><td>" . WEB_device_dhcp_server . "</td><td></td><td></td><td></td></tr>";
                print "<tr>";
                print "<td class='data'>";
                print_qa_select('f_dhcp', $device['dhcp']);
                print "<td class='data' colspan=4>";
                print_url(WEB_list_l3_networks, "/admin/devices/edit_gw_subnets.php?id=$id", 'linkButton');
                print "</tr>\n";
            }

            //for all active network devices
            if ($device['device_type'] <= 2) {
                //cli access settings
                print "<tr><td>" . WEB_cell_login . "</td><td>" . WEB_cell_password . "</td><td>" . WEB_cell_control_proto . "</td><td>" . WEB_cell_control_port . "</td></tr>";
                print "<tr>";
                print "<td class='data'><input type='text' name='f_login' value=" . $device['login'] . "></td>\n";
                print "<td class='data'><input type='text' name='f_password' value='********'></td>\n";
                print "<td class='data'>";
                print_control_proto_select('f_protocol', $device['protocol']);
                print "</td>\n";
                print "<td class='data'><input type='text' name='f_control_port' value=" . $device['control_port'] . "></td>\n";
                print "</tr>";
                //snmp settings & discovery & nagios
                print "<tr><td>" . WEB_network_discovery . "</td><td>" . WEB_nagios . "</td><td>" . WEB_device_save_netflow . "</td><td></td></tr>";
                print "<tr>";
                print "<td class='data'>";
                print_qa_select('f_discovery', $device['discovery']);
                print "</td>\n";
                print "<td class='data'>";
                print_qa_select('f_nagios', $device['nagios']);
                print "</td>\n";
                print "<td class='data'>";
                print_qa_select('f_save_netflow', $device['netflow_save']);
                print "</td>\n";
                print "<td class='data'></td></tr>";
            }

            if ($device['snmp_version'] == 3) {
                //snmp settings
                print "<tr><td>" . WEB_snmp_version . "</td><td>" . WEB_snmp_v3_auth_proto . "</td><td>" . WEB_snmp_v3_priv_proto . "</td><td></td></tr>";
                print "<tr><td class='data'>";
                print_snmp_select('f_snmp_version', $device['snmp_version']);
                print "</td>\n";
                print "<td class='data'>";
                print_snmp_auth_proto_select('f_snmp3_auth_proto', $device['snmp3_auth_proto']);
                print "</td>\n";
                print "<td class='data'>";
                print_snmp_priv_proto_select('f_snmp3_priv_proto', $device['snmp3_priv_proto']);
                print "</td>\n";
                print "<td class='data'></td>";
                print "</tr>";
                print "<tr><td>" . WEB_snmp_v3_user_ro . "</td><td>" . WEB_snmp_v3_ro_password . "</td><td>" . WEB_snmp_v3_user_rw . "</td><td>" . WEB_snmp_v3_rw_password . "</td><td></td>";
                print "</tr><tr>";
                print "<td class='data'><input type='text' name='f_snmp3_user_ro' value=" . $device['snmp3_user_ro'] . "></td>\n";
                print "<td class='data'><input type='text' name='f_snmp3_user_ro_password' minlength='8' value=" . $device['snmp3_user_ro_password'] . "></td>\n";
                print "<td class='data'><input type='text' name='f_snmp3_user_rw' value=" . $device['snmp3_user_rw'] . "></td>\n";
                print "<td class='data'><input type='text' name='f_snmp3_user_rw_password' minlength='8' value=" . $device['snmp3_user_rw_password'] . "></td>\n";
                print "</tr>\n";
            } else {
                print "<tr><td>" . WEB_snmp_version . "</td><td></td><td></td><td></td></tr>";
                print "<tr><td class='data'>";
                print_snmp_select('f_snmp_version', $device['snmp_version']);
                print "</td><td class='data' colspan=3></td>\n";
                print "</tr>";
                if ($device['snmp_version'] > 0) {
                    print "<tr><td>" . WEB_snmp_community_ro . "</td><td>" . WEB_snmp_community_rw . "</td><td></td><td></td></tr>";
                    print "<tr>\n";
                    print "<td class='data'><input type='text' name='f_community' value=" . $device['community'] . "></td>\n";
                    print "<td class='data'><input type='text' name='f_rw_community' value=" . $device['rw_community'] . "></td>\n";
                    print "<td class='data' colspan=2></td>";
                    print "</tr>";
                }
            }

            //save button
            if ($device['snmp_version'] > 0) {
                print "<tr><td colspan=2>" . $device['ip'] . "::" . get_device_model_name($db_link, $device['device_model_id']) . "</td>";
                print "<td><button name='mac_walk' onclick=\"window.open('mactable.php?id=" . $id . "')\">" . WEB_device_mac_table . "</button>";
                print "<button name='port_walk' onclick=\"window.open('snmpwalk.php?id=" . $id . "')\">" . WEB_device_walk_port_list . "</button></td>";
            } else {
                print "<tr><td colspan=3>" . $device['ip'] . "::" . get_device_model_name($db_link, $device['device_model_id']) . "</td>";
            }
            print "<td align=right><input type='submit' id='editdevice' name='editdevice' value='" . WEB_btn_save . "'></td></tr>";
            print "</table>\n";
            ?>
    </form>

<script>

document.getElementById('f_devtype_id').addEventListener('change', function(event) {
  const buttonApply = document.getElementById('editdevice');
  buttonApply.click();
});

document.getElementById('f_snmp_version').addEventListener('change', function(event) {
  const buttonApply = document.getElementById('editdevice');
  buttonApply.click();
});

</script>

<?php require_once($_SERVER['DOCUMENT_ROOT'] . "/inc/footer.php"); ?>
