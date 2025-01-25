<?php

if (!defined("CONFIG")) die("Not defined");

require_once($_SERVER['DOCUMENT_ROOT'] . "/inc/consts.php");

function get_ifmib_index_table($ip, $snmp)
{
    $ifmib_map = NULL;

    $is_mikrotik = walk_snmp($ip, $snmp, MIKROTIK_DHCP_SERVER);
    $mk_ros_version = 0;

    if ($is_mikrotik) {
        $mikrotik_version = walk_snmp($ip, $snmp, MIKROTIK_ROS_VERSION);
        $mk_ros_version = 6491;
        $result = preg_match('/RouterOS\s+(\d)\.(\d{1,3})\.(\d{1,3})\s+/', $mikrotik_version[MIKROTIK_ROS_VERSION], $matches);
        if ($result) {
            $mk_ros_version = $matches[1] * 1000 + $matches[2] * 10 + $matches[3];
        }
    }

    if ($mk_ros_version == 0 or $mk_ros_version > 6468) {
        #fdb_index => snmp_index
        $index_map_table = walk_snmp($ip, $snmp, IFMIB_IFINDEX_MAP);
        #get map snmp interfaces to fdb table
        if (isset($index_map_table) and count($index_map_table) > 0) {
            foreach ($index_map_table as $key => $value) {
                $key = trim($key);
                $value = intval(trim(str_replace('INTEGER:', '', $value)));
                $result = preg_match('/\.(\d{1,10})$/', $key, $matches);
                if ($result) {
                    $fdb_index = preg_replace('/^\./', '', $matches[0]);
                    $ifmib_map[$fdb_index] = $value;
                }
            }
        }
    }

    #return simple map snmp_port_index = snmp_port_index
    if (empty($ifmib_map)) {
        #ifindex
        $index_table = walk_snmp($ip, $snmp, IFMIB_IFINDEX);
        if (isset($index_table) and count($index_table) > 0) {
            foreach ($index_table as $key => $value) {
                $key = trim($key);
                $value = intval(trim(str_replace('INTEGER:', '', $value)));
                $result = preg_match('/\.(\d{1,10})$/', $key, $matches);
                if ($result) {
                    $fdb_index = preg_replace('/^\./', '', $matches[0]);
                    $ifmib_map[$fdb_index] = $value;
                }
            }
        }
    }
    return $ifmib_map;
}

#get mac table by selected snmp oid
function get_mac_table($ip, $snmp, $oid, $index_map)
{
    if (!isset($ip)) {
        return;
    }

    if (!isset($oid)) {
        return;
    }

    $mac_table = walk_snmp($ip, $snmp, $oid);
    if (isset($mac_table) and gettype($mac_table) == 'array' and count($mac_table) > 0) {
        foreach ($mac_table as $key => $value) {
            if (empty($value)) {
                continue;
            }
            if (empty($key)) {
                continue;
            }
            $key = trim($key);
            $value_raw = intval(trim(str_replace('INTEGER:', '', $value)));
            if (empty($value_raw)) {
                continue;
            }
            if (!empty($index_map)) {
                if (empty($index_map[$value_raw])) {
                    $value = $value_raw;
                } else {
                    $value = $index_map[$value_raw];
                }
            } else {
                $value = $value_raw;
            }
            $pattern = '/\.(\d{1,3})\.(\d{1,3})\.(\d{1,3})\.(\d{1,3})\.(\d{1,3})\.(\d{1,3})$/';
            $result = preg_match($pattern, $key, $matches);
            if (!empty($result)) {
                $mac_key = preg_replace('/^\./', '', $matches[0]);
                $fdb_table[$mac_key] = $value;
            }
        }
    }
    return $fdb_table;
}

#get ip interfaces
function getIpAdEntIfIndex($db, $ip, $snmp)
{
    if (!isset($ip)) {
        return;
    }
    #oid+ip = index
    $ip_table = walk_snmp($ip, $snmp, ipAdEntIfIndex);
    #oid+index=name
    $int_table = walk_snmp($ip, $snmp, ifDescr);
    $result = [];
    if (isset($ip_table) and gettype($ip_table) == 'array' and count($ip_table) > 0) {
        foreach ($ip_table as $key => $value) {
            if (empty($value)) {
                continue;
            }
            if (empty($key)) {
                continue;
            }
            $key = trim($key);
            $interface_index = intval(trim(str_replace('INTEGER:', '', $value)));
            if (empty($value)) {
                continue;
            }
            $interface_name = $int_table[ifDescr . '.' . $interface_index];
            $interface_name = trim(str_replace('STRING:', '', $interface_name));
            $interface_ip = trim(str_replace(ipAdEntIfIndex . '.', '', $key));
            if (empty($interface_name)) {
                continue;
            }
            $result[$interface_index]['ip'] = $interface_ip;
            $result[$interface_index]['index'] = $interface_index;
            $result[$interface_index]['name'] = $interface_name;
            //type: 0 - local, 1 - WAN
            $result[$interface_index]['type'] = 1;
            if (is_our_network($db, $interface_ip)) {
                $result[$interface_index]['type'] = 0;
            }
        }
    }
    return $result;
}

#get mac table by analyze all available tables
function get_fdb_table($ip, $snmp)
{

    if (!isset($ip)) {
        return;
    }

    $ifindex_map = get_ifmib_index_table($ip, $snmp);
    $fdb1_table = get_mac_table($ip, $snmp, MAC_TABLE_OID, $ifindex_map);
    if (!empty($fdb1_table)) {
        $fdb_table = $fdb1_table;
    } else {
        $fdb2_table = get_mac_table($ip, $snmp, MAC_TABLE_OID2, $ifindex_map);
        if (!empty($fdb2_table)) {
            $fdb_table = $fdb2_table;
        }
    }

    $snmp_cisco = $snmp;

    // maybe cisco?!
    if (!isset($fdb_table) or empty($fdb_table) or count($fdb_table) == 0) {
        $vlan_table = walk_snmp($ip, $snmp, CISCO_VLAN_OID);
        if (empty($vlan_table)) {
            return;
        }
        foreach ($vlan_table as $vlan_oid => $value) {
            if (empty($vlan_oid)) {
                continue;
            }
            $pattern = '/\.(\d{1,4})$/';
            $result = preg_match($pattern, $vlan_oid, $matches);
            if (!empty($result)) {
                $vlan_id = preg_replace('/^\./', '', $matches[0]);
                if ($vlan_id > 1000 and $vlan_id < 1009) {
                    continue;
                }
                $snmp_cisco["ro-community"] = $snmp["ro-community"] . '@' . $vlan_id;
                $fdb_vlan_table = get_mac_table($ip, $snmp_cisco, MAC_TABLE_OID, $ifindex_map);
                if (!isset($fdb_vlan_table) or !$fdb_vlan_table or count($fdb_vlan_table) == 0) {
                    $fdb_vlan_table = get_mac_table($ip, $snmp_cisco, MAC_TABLE_OID2, $ifindex_map);
                }
                foreach ($fdb_vlan_table as $mac => $port) {
                    if (!isset($mac)) {
                        continue;
                    }
                    $fdb_table[$mac] = $port;
                }
            }
        }
    }
    return $fdb_table;
}

function check_snmp_access($ip, $snmp)
{
    if (!isset($ip)) {
        return;
    }
    #check host up
    $status = exec(escapeshellcmd("ping -W 1 -i 1 -c 3 " . $ip));
    if (empty($status)) {
        return;
    }
    #check snmp
    $result = get_snmp($ip, $snmp, SYS_DESCR_MIB);
    if (empty($result)) {
        return;
    }
    return 1;
}

function get_port_state($port, $ip, $snmp)
{
    if (!isset($port)) {
        return;
    }
    if (!isset($ip)) {
        return;
    }
    $port_oid = PORT_STATUS_OID . '.' . $port;
    $port_state = get_snmp($ip, $snmp, $port_oid);
    return $port_state;
}


function get_last_digit($oid)
{
    if (!isset($oid)) {
        return;
    }
    $pattern = '/\.(\d{1,})$/';
    preg_match($pattern, $oid, $matches);
    return $matches[1];
}

function get_cisco_sensors($ip, $snmp, $mkey)
{
    $index = get_last_digit($mkey);
    $result = parse_snmp_value(get_snmp($ip, $snmp, CISCO_SFP_SENSORS . "." . $index));
    $prec = parse_snmp_value(get_snmp($ip, $snmp, CISCO_SFP_PRECISION . "." . $index));
    if (!isset($prec)) {
        $prec = 1;
    }
    $result = round(trim($result) / (10 * $prec), 2);
    return $result;
}

function get_snmp_ifname($ip, $snmp, $port)
{
    $port_name = parse_snmp_value(get_snmp($ip, $snmp, IFMIB_IFNAME . "." . $port));
    if (empty($port_name)) {
        $port_name = parse_snmp_value(get_snmp($ip, $snmp, IFMIB_IFDESCR . "." . $port));
    }
    if (empty($port_name)) {
        $port_name = parse_snmp_value(get_snmp($ip, $snmp, IFMIB_IFALIAS . "." . $port));
    }
    return $port_name;
}


function get_snmp_interfaces($ip, $snmp)
{
    $result = [];
    $ifmib_list = walk_snmp($ip, $snmp, IFMIB_IFNAME);
    if (empty($ifmib_list)) {
        $ifmib_list = walk_snmp($ip, $snmp, IFMIB_IFDESCR);
    }
    if (empty($ifmib_list)) {
        $ifmib_list = walk_snmp($ip, $snmp, IFMIB_IFALIAS);
    }
    if (!empty($ifmib_list)) {
        foreach ($ifmib_list as $key => $value) {
            $key = trim($key);
            $value = parse_snmp_value($value);
            if (preg_match('/\.(\d{1,10})$/', $key, $matches)) {
                $int_index = preg_replace('/^\./', '', $matches[0]);
                $result[$int_index] = $value;
            }
        }
    }
    return $result;
}

function walk_snmp($ip, $snmp, $oid)
{
    snmp_set_oid_output_format(SNMP_OID_OUTPUT_NUMERIC);
    $result = NULL;
    $version = $snmp["version"];
    if ($version == 3) {
        $result = snmp3_real_walk($ip, $snmp["ro-user"], 'authPriv', $snmp['auth-proto'], $snmp['ro-password'], $snmp["priv-proto"], $snmp["ro-password"], $oid, SNMP_timeout, SNMP_retry);
    }
    if ($version == 2) {
        $result = snmp2_real_walk($ip,  $snmp["ro-community"], $oid, SNMP_timeout, SNMP_retry);
    }
    if ($version == 1) {
        $result = snmprealwalk($ip,  $snmp["ro-community"], $oid, SNMP_timeout, SNMP_retry);
    }
    return $result;
}

function getSnmpAccess($device)
{
    $result['port']         = 161;
    $result['version']      = $device['snmp_version'];
    $result['ro-community'] = $device['community'];
    $result['rw-community'] = $device['rw_community'];
    #snmpv3
    $result['auth-proto']   = $device['snmp3_auth_proto'];
    $result['priv-proto']   = $device['snmp3_priv_proto'];
    $result['ro-user']      = $device['snmp3_user_ro'];
    $result['rw-user']      = $device['snmp3_user_rw'];
    $result['ro-password']  = $device['snmp3_user_ro_password'];
    $result['rw-password']  = $device['snmp3_user_rw_password'];
    return $result;
}

function get_snmp_module_id($modules_oids, $port_name)
{
    $port_name = preg_quote(trim($port_name), '/');
    foreach ($modules_oids as $key => $value) {
        $pattern = '/' . $port_name . '/i';
        preg_match($pattern, $value, $matches);
        if (isset($matches[0])) {
            $module_id = get_last_digit($key);
            return $module_id;
        }
    }
    return;
}

function get_sfp_status($vendor_id, $port, $ip, $snmp, $modules_oids)
{
    if (!isset($vendor_id)) {
        return;
    }
    if (!isset($port)) {
        return;
    }
    if (!isset($ip)) {
        return;
    }

    try {
        $status = '';
        // eltex
        if ($vendor_id == 2) {
            $sfp_vendor = parse_snmp_value(get_snmp($ip, $snmp, ELTEX_SFP_VENDOR . "." . $port));
            if (!empty($sfp_vendor)) {
                $sfp_status_temp = ELTEX_SFP_STATUS . "." . $port . ".5";
                $sfp_status_volt = ELTEX_SFP_STATUS . "." . $port . ".6";
                $sfp_status_circut = ELTEX_SFP_STATUS . "." . $port . ".7";
                $sfp_status_tx = ELTEX_SFP_STATUS . "." . $port . ".8";
                $sfp_status_rx = ELTEX_SFP_STATUS . "." . $port . ".9";
                $temp = parse_snmp_value(get_snmp($ip, $snmp, $sfp_status_temp));
                $volt = parse_snmp_value(get_snmp($ip, $snmp, $sfp_status_volt));
                $circut = parse_snmp_value(get_snmp($ip, $snmp, $sfp_status_circut));
                $tx = parse_snmp_value(get_snmp($ip, $snmp, $sfp_status_tx));
                $rx = parse_snmp_value(get_snmp($ip, $snmp, $sfp_status_rx));
                $sfp_sn = parse_snmp_value(get_snmp($ip, $snmp, ELTEX_SFP_SN . "." . $port));
                $sfp_freq = parse_snmp_value(get_snmp($ip, $snmp, ELTEX_SFP_FREQ . "." . $port));
                if (!isset($sfp_freq) or $sfp_freq == 65535) {
                    $sfp_freq = 'unspecified';
                }
                $sfp_length = parse_snmp_value(get_snmp($ip, $snmp, ELTEX_SFP_LENGTH . "." . $port));
                $status = 'Vendor: ' . $sfp_vendor . ' Serial: ' . $sfp_sn . ' Laser: ' . $sfp_freq . ' Distance: ' . $sfp_length . '<br>';
                if (!empty($sfp_status_temp) and $temp > 0.1) {
                    $status .= 'Temp: ' . $temp . " C";
                }
                if (!empty($sfp_status_volt) and $volt > 0.1) {
                    $status .= ' Volt: ' . round($volt / 1000000, 2) . ' V';
                }
                if (!empty($sfp_status_circut) and $circut > 0.1) {
                    $status .= ' Circut: ' . round($circut / 1000, 2) . ' mA';
                }
                if (!empty($sfp_status_tx) and $tx > 0.1) {
                    $status .= ' Tx: ' . round($tx / 1000, 2) . ' dBm';
                }
                if (!empty($sfp_status_rx) and $rx > 0.1) {
                    $status .= ' Rx: ' . round($rx / 1000, 2) . ' dBm';
                }
                $status .= '<br>';
                return $status;
            }
            return;
        }

        // snr
        if ($vendor_id == 6) {
            $sfp_vendor = parse_snmp_value(get_snmp($ip, $snmp, SNR_SFP_VendorName . "." . $port));
            if (!empty($sfp_vendor) and $sfp_vendor != 'NULL') {
                $oid_sfp_model_name = SNR_SFP_ModelName . "." . $port;
                $oid_sfp_type_name = SNR_SFP_TypeName . "." . $port;
                $oid_sfp_bitrate = SNR_SFP_BitRate . "." . $port;
                $oid_sfp_status_volt = SNR_SFP_VOLT . "." . $port;
                $oid_sfp_status_circut = SNR_SFP_BIAS . "." . $port;
                $oid_sfp_status_tx = SNR_SFP_TX . "." . $port;
                $oid_sfp_status_rx = SNR_SFP_RX . "." . $port;
                $oid_sfp_length = SNR_SFP_WaveLength . "." . $port;

                $volt = parse_snmp_value(get_snmp($ip, $snmp, $oid_sfp_status_volt));
                $circut = parse_snmp_value(get_snmp($ip, $snmp, $oid_sfp_status_circut));
                $tx = parse_snmp_value(get_snmp($ip, $snmp, $oid_sfp_status_tx));
                $rx = parse_snmp_value(get_snmp($ip, $snmp, $oid_sfp_status_rx));
                $sfp_model_name = parse_snmp_value(get_snmp($ip, $snmp, $oid_sfp_model_name));
                $sfp_type_name = parse_snmp_value(get_snmp($ip, $snmp, $oid_sfp_type_name));
                $sfp_freq = parse_snmp_value(get_snmp($ip, $snmp, $oid_sfp_bitrate));
                $sfp_length = parse_snmp_value(get_snmp($ip, $snmp, $oid_sfp_length));

                $status = 'Vendor: ' . $sfp_vendor . ' ' . $sfp_model_name . ' ' . $sfp_type_name . ' Speed: ' . $sfp_freq . ' Freq: ' . $sfp_length . '<br>';
                if (!empty($sfp_status_volt) and $volt > 0.1) {
                    $status .= ' Volt: ' . round($volt / 1000000, 2) . ' V';
                }
                if (!empty($sfp_status_circut) and $circut > 0.1) {
                    $status .= ' Circut: ' . round($circut / 1000, 2) . ' mA';
                }
                if (!empty($sfp_status_tx) and $tx > 0.1) {
                    $status .= ' Tx: ' . round($tx / 1000, 2) . ' dBm';
                }
                if (!empty($sfp_status_rx) and $rx > 0.1) {
                    $status .= ' Rx: ' . round($rx / 1000, 2) . ' dBm';
                }
                $status .= '<br>';
                return $status;
            }
            return;
        }

        // cisco
        if ($vendor_id == 16) {
            // get interface names
            $port_name = parse_snmp_value(get_snmp($ip, $snmp, IFMIB_IFNAME . "." . $port));
            if (empty($port_name)) {
                $port_name = parse_snmp_value(get_snmp($ip, $snmp, IFMIB_IFDESCR . "." . $port));
            }
            // search module indexes
            $port_name = preg_quote(trim($port_name), '/');
            foreach ($modules_oids as $key => $value) {
                $pattern = '/(' . $port_name . ' Module Temperature Sensor)/i';
                preg_match($pattern, $value, $matches);
                if (isset($matches[0])) {
                    $temp = get_cisco_sensors($ip, $snmp, $key);
                    continue;
                }
                $pattern = '/(' . $port_name . ' Supply Voltage Sensor)/i';
                preg_match($pattern, $value, $matches);
                if (isset($matches[0])) {
                    $volt = get_cisco_sensors($ip, $snmp, $key);
                    continue;
                }
                $pattern = '/(' . $port_name . ' Bias Current Sensor)/i';
                preg_match($pattern, $value, $matches);
                if (isset($matches[0])) {
                    $circut = get_cisco_sensors($ip, $snmp, $key);
                    continue;
                }
                $pattern = '/(' . $port_name . ' Transmit Power Sensor)/i';
                preg_match($pattern, $value, $matches);
                if (isset($matches[0])) {
                    $tx = get_cisco_sensors($ip, $snmp, $key);
                    continue;
                }
                $pattern = '/(' . $port_name . ' Receive Power Sensor)/i';
                preg_match($pattern, $value, $matches);
                if (isset($matches[0])) {
                    $rx = get_cisco_sensors($ip, $snmp, $key);
                    continue;
                }
            }
            if (!empty($temp) and $temp > 0) {
                $status .= 'Temp: ' . $temp . " C";
            }
            if (!empty($volt) and $volt > 0) {
                $status .= ' Volt: ' . $volt . ' V';
            }
            if (!empty($circut) and $circut > 0) {
                $status .= ' Circut: ' . $circut . ' mA';
            }
            if (!empty($tx) and abs($tx) > 0.1) {
                $status .= ' Tx: ' . $tx . ' dBm';
            }
            if (!empty($rx) and abs($rx) > 0.1) {
                $status .= ' Rx: ' . $rx . ' dBm';
            }
            if (!empty($status)) {
                $status = preg_replace('/"/', '', $status);
                $status .= '<br>';
            }
            return $status;
        }

        // huawei
        if ($vendor_id == 3) {

            // get interface names
            $port_name = parse_snmp_value(get_snmp($ip, $snmp, IFMIB_IFNAME . "." . $port));
            if (empty($port_name)) {
                $port_name = parse_snmp_value(get_snmp($ip, $snmp, IFMIB_IFDESCR . "." . $port));
            }
            // search module indexes
            $port_name = preg_quote(trim($port_name), '/');
            foreach ($modules_oids as $key => $value) {
                $pattern = '/' . $port_name . '/i';
                preg_match($pattern, $value, $matches);
                if (isset($matches[0])) {
                    $module_id = get_last_digit($key);
                    unset($result);
                    $result = parse_snmp_value(get_snmp($ip, $snmp, HUAWEI_SFP_VENDOR . "." . $module_id));
                    if (!empty($result)) {
                        $sfp_vendor = $result;
                    }
                    unset($result);
                    $result = parse_snmp_value(get_snmp($ip, $snmp, HUAWEI_SFP_SPEED . "." . $module_id));
                    if (!empty($result)) {
                        list($sfp_speed, $spf_lenght, $sfp_type) = explode('-', $result);
                        if ($sfp_type == 0) {
                            $sfp_type = 'MultiMode';
                        }
                        if ($sfp_type == 1) {
                            $sfp_type = 'SingleMode';
                        }
                    }

                    $volt = parse_snmp_value(get_snmp($ip, $snmp, HUAWEI_SFP_VOLT . "." . $module_id));
                    $circut = parse_snmp_value(get_snmp($ip, $snmp, HUAWEI_SFP_BIASCURRENT . "." . $module_id));
                    $tx = parse_snmp_value(get_snmp($ip, $snmp, HUAWEI_SFP_OPTTX . "." . $module_id));
                    $rx = parse_snmp_value(get_snmp($ip, $snmp, HUAWEI_SFP_OPTRX . "." . $module_id));
                    if (!isset($tx)) {
                        $tx = parse_snmp_value(get_snmp($ip, $snmp, HUAWEI_SFP_TX . "." . $module_id));
                    }
                    if (!isset($rx)) {
                        $rx = parse_snmp_value(get_snmp($ip, $snmp, HUAWEI_SFP_RX . "." . $module_id));
                    }
                    if (!empty($sfp_vendor)) {
                        $status .= ' Name:' . $sfp_vendor . '<br>';
                    }
                    //                if (isset($sfp_speed)) { $status .= ' ' . $sfp_speed; }
                    //                if (isset($spf_lenght)) { $status .= ' ' . $spf_lenght; }
                    if ($volt > 0) {
                        $status .= ' Volt: ' . round($volt / 1000, 2) . ' V';
                    }
                    if (!empty($circut) and $circut > 0) {
                        $status .= ' Circut: ' . $circut . ' mA <br>';
                    }
                    if (!empty($tx)) {
                        $tx = preg_replace('/"/', '', $tx);
                        try {
                            list($tx_dbm, $pattern) = explode('.', $tx);
                            $tx_dbm = round(floatval(trim($tx_dbm)) / 100, 2);
                        } catch (Exception $e) {
                            $tx_dbm = 0;
                        }
                        if (abs($tx_dbm) > 0.1) {
                            $status .= ' Tx: ' . $tx_dbm . ' dBm';
                        }
                    }
                    if (!empty($rx)) {
                        $rx = preg_replace('/"/', '', $rx);
                        try {
                            list($rx_dbm, $pattern) = explode('.', $rx);
                            $rx_dbm = round(floatval(trim($rx_dbm)) / 100, 2);
                        } catch (Exception $e) {
                            $rx_dbm = 0;
                        }
                        if (abs($rx_dbm) > 0.1) {
                            $status .= ' Rx: ' . $rx_dbm . ' dBm';
                        }
                    }

                    break;
                }
            }
            if (isset($status)) {
                $status = preg_replace('/"/', '', $status);
                $status .= '<br>';
            }
            return $status;
        }
    } catch (Exception $e) {
        return;
    }
}


function get_switch_vlans($vendor, $ip, $snmp)
{

    $switch_vlans = [];
    $port_status  = [];
    $vlan_status  = [];

    //cisco...
    if ($vendor == 16) {
        //all vlan at switch
        $vlan_list = walk_snmp($ip, $snmp, vtpVlanName);
        if (empty($vlan_list)) {
            return;
        }
        foreach ($vlan_list as $key => $value) {
            if (empty($value)) {
                $value = '';
            }
            $key = trim($key);
            $value = parse_snmp_value($value);
            $vlan_id = NULL;
            if (preg_match('/\.(\d{1,10})$/', $key, $matches)) {
                $vlan_id = preg_replace('/^\./', '', $matches[0]);
            }
            //skip service vlan
            if (preg_match('/(1002|1003|1004|1005)/', $vlan_id)) {
                continue;
            }
            if (isset($vlan_id) and !empty($vlan_id)) {
                $switch_vlans[$vlan_id] = $value;
            }
        }

        //native vlan for port - get list of all ports
        $pvid_list = walk_snmp($ip, $snmp, vlanTrunkPortNativeVlan);
        if (!empty($pvid_list)) {
            foreach ($pvid_list as $key => $value) {
                if (empty($value)) {
                    $value = '';
                }
                $key = trim($key);
                $value = parse_snmp_value($value);
                $port = NULL;
                if (preg_match('/\.(\d{1,10})$/', $key, $matches)) {
                    $port = preg_replace('/^\./', '', $matches[0]);
                }
                if (isset($port) and !empty($port)) {
                    $port_status[$port]['native'] = $value;
                }
            }
        }

        //pvid
        $pvid_list = walk_snmp($ip, $snmp, vmVlanPvid);
        if (!empty($pvid_list)) {
            foreach ($pvid_list as $key => $value) {
                if (empty($value)) {
                    $value = '';
                }
                $key = trim($key);
                $value = parse_snmp_value($value);
                $port = NULL;
                if (preg_match('/\.(\d{1,10})$/', $key, $matches)) {
                    $port = preg_replace('/^\./', '', $matches[0]);
                }
                if (isset($port) and !empty($port)) {
                    $port_status[$port]['pvid'] = $value;
                }
            }
        }

        //init port config
        foreach ($port_status as &$port) {
            if (!is_array($port)) {
                continue;
            }
            if (!isset($port['pvid'])) {
                $port['pvid'] = $port['native'];
            }
            $port['untagged'] = '';
            $port['tagged'] = '';
        }
        unset($port);

        //get vlan list at ports
        $egress_vlan = walk_snmp($ip, $snmp, vlanTrunkPortVlansEnabled);
        if (!empty($egress_vlan)) {
            $j = 0;
            foreach ($egress_vlan as $key => $value) {
                $j++;
                if (empty($value)) {
                    $value = '';
                }
                $key = trim($key);
                $value = parse_snmp_value($value);
                if (preg_match('/\.(\d{1,10})$/', $key, $matches)) {
                    $port = preg_replace('/^\./', '', $matches[0]);
                }
                if (isset($port) and !empty($port)) {
                    //skip access ports
                    if (!is_array($port_status[$port]) or !isset($port_status[$port]['pvid']) or !isset($port_status[$port]['native'])) {
                        continue;
                    }
                    if ($port_status[$port]['pvid'] != $port_status[$port]['native']) {
                        continue;
                    }
                    //get vlan at port in hex
                    $hex_value = preg_replace('/\s+/', '', $value);
                    $bin_value = strHexToBin($hex_value);
                    //analyze switch vlans
                    foreach ($switch_vlans as $vlan_id => $vlan_name) {
                        if (isset($bin_value[$vlan_id]) and $bin_value[$vlan_id] == '1') {
                            $port_status[$port]['tagged'] = $port_status[$port]['tagged'] . ',' . $vlan_id;
                        }
                    }
                }
            }
        }

        //remove lliding ,
        foreach ($port_status as &$port) {
            if (!is_array($port)) {
                continue;
            }
            $port['untagged'] = preg_replace('/^,/', '', $port['untagged']);
            $port['tagged'] = preg_replace('/^,/', '', $port['tagged']);
        }
        unset($port);

        return $port_status;
    }

    //standart switches

    //tplink
    if ($vendor == 69) {
        //pvid for port
        $pvid_list = walk_snmp($ip, $snmp, TPLINK_dot1qPortVlanEntry);
        if (!empty($pvid_list)) {
            foreach ($pvid_list as $key => $value) {
                if (empty($value)) {
                    $value = '';
                }
                $key = trim($key);
                $value = parse_snmp_value($value);
                $port = NULL;
                if (preg_match('/\.(\d{1,10})$/', $key, $matches)) {
                    $port = preg_replace('/^\./', '', $matches[0]);
                }
                if (isset($port) and !empty($port)) {
                    $port_status[$port]['pvid'] = $value;
                }
            }
        }
        return $port_status;
    }

    //default
    //pvid for port
    $pvid_list = walk_snmp($ip, $snmp, dot1qPortVlanEntry);
    if (!empty($pvid_list)) {
        foreach ($pvid_list as $key => $value) {
            if (empty($value)) {
                $value = '';
            }
            $key = trim($key);
            $value = parse_snmp_value($value);
            $port = NULL;
            if (preg_match('/\.(\d{1,10})$/', $key, $matches)) {
                $port = preg_replace('/^\./', '', $matches[0]);
            }
            if (isset($port) and !empty($port)) {
                $port_status[$port]['pvid'] = $value;
            }
        }
    }

    //init port config
    foreach ($port_status as &$port) {
        if (!is_array($port)) {
            continue;
        }
        $port['native'] = $port['pvid'];
        $port['untagged'] = '';
        $port['tagged'] = '';
    }
    unset($port);

    //all vlan at switch
    $vlan_list = walk_snmp($ip, $snmp, dot1qVlanStaticName);
    if (empty($vlan_list)) {
        return $port_status;
    }
    foreach ($vlan_list as $key => $value) {
        if (empty($value)) {
            $value = '';
        }
        $key = trim($key);
        $value = parse_snmp_value($value);
        $vlan_id = NULL;
        if (preg_match('/\.(\d{1,10})$/', $key, $matches)) {
            $vlan_id = preg_replace('/^\./', '', $matches[0]);
        }
        if (isset($vlan_id) and !empty($vlan_id)) {
            $switch_vlans[$vlan_id] = $value;
        }
    }

    $forbidden_vlan = walk_snmp($ip, $snmp, dot1qVlanForbiddenEgressPorts);
    if (!empty($forbidden_vlan)) {
        foreach ($forbidden_vlan as $key => $value) {
            if (empty($value)) {
                $value = '';
            }
            $key = trim($key);
            $value = parse_snmp_value($value);
            if (preg_match('/\.(\d{1,10})$/', $key, $matches)) {
                $vlan_id = preg_replace('/^\./', '', $matches[0]);
            }
            if (isset($vlan_id) and !empty($vlan_id)) {
                $hex_value = preg_replace('/\s+/', '', $value);
                $hex_value = preg_replace('/0*$/', '', $hex_value);
                $bin_value = strHexToBin($hex_value);
                for ($i = 0; $i < strlen($bin_value); $i++) {
                    $port = $i + 1;
                    $vlan_status['forbidden_vlan'][$vlan_id][$port] = $bin_value[$i];
                    if ($bin_value[$i] == '1') {
                        $port_status[$port]['forbidden'] .= ',' . $vlan_id;
                    }
                }
            }
        }
    }
    $untagged_vlan = walk_snmp($ip, $snmp, dot1qVlanStaticUntaggedPorts);
    if (!empty($untagged_vlan)) {
        foreach ($untagged_vlan as $key => $value) {
            if (empty($value)) {
                $value = '';
            }
            $key = trim($key);
            $value = parse_snmp_value($value);
            if (preg_match('/\.(\d{1,10})$/', $key, $matches)) {
                $vlan_id = preg_replace('/^\./', '', $matches[0]);
            }
            if (isset($vlan_id) and !empty($vlan_id)) {
                $hex_value = preg_replace('/\s+/', '', $value);
                $hex_value = preg_replace('/0*$/', '', $hex_value);
                $bin_value = strHexToBin($hex_value);
                for ($i = 0; $i < strlen($bin_value); $i++) {
                    $port = $i + 1;
                    $vlan_status['untagged_vlan'][$vlan_id][$port] = $bin_value[$i];
                    if ($bin_value[$i] == '1') {
                        if (isset($vlan_status['forbidden_vlan']) and $vlan_status['forbidden_vlan'][$vlan_id][$port] == '0') {
                            $port_status[$port]['untagged'] .= ',' . $vlan_id;
                        } else {
                            $vlan_status['untagged_vlan'][$vlan_id][$port] = '0';
                        }
                    }
                }
            }
        }
    }

    $egress_vlan = walk_snmp($ip, $snmp, dot1qVlanStaticEgressPorts);
    if (!empty($egress_vlan)) {
        foreach ($egress_vlan as $key => $value) {
            if (empty($value)) {
                $value = '';
            }
            $key = trim($key);
            $value = parse_snmp_value($value);
            if (preg_match('/\.(\d{1,10})$/', $key, $matches)) {
                $vlan_id = preg_replace('/^\./', '', $matches[0]);
            }
            //exclude vlan 1 from tagged vlan
            if ($vlan_id == '1') {
                continue;
            }
            if (isset($vlan_id) and !empty($vlan_id)) {
                $hex_value = preg_replace('/\s+/', '', $value);
                $hex_value = preg_replace('/0*$/', '', $hex_value);
                $bin_value = strHexToBin($hex_value);
                for ($i = 0; $i < strlen($bin_value); $i++) {
                    $port = $i + 1;
                    $vlan_status['egress_vlan'][$vlan_id][$port] = $bin_value[$i];
                    //analyze egress & untagged vlans
                    if ($bin_value[$i] == '1') {
                        if ((!isset($vlan_status['untagged_vlan'][$vlan_id][$port]) or $vlan_status['untagged_vlan'][$vlan_id][$port] == '0') and
                            (!isset($vlan_status['forbidden_vlan'][$vlan_id][$port]) or $vlan_status['forbidden_vlan'][$vlan_id][$port] == '0') and
                            (!isset($port_status[$port]['pvid']) or $port_status[$port]['pvid'] != $vlan_id)
                        ) {
                            $vlan_status['tagged_vlan'][$vlan_id][$port] = '1';
                            $port_status[$port]['tagged'] .= ',' . $vlan_id;
                        } else {
                            $vlan_status['tagged_vlan'][$vlan_id][$port] = '0';
                        }
                    }
                }
            }
        }
    }

    foreach ($port_status as &$port) {
        if (!is_array($port)) {
            continue;
        }
        $port['untagged'] = preg_replace('/^,/', '', $port['untagged']);
        $port['tagged'] = preg_replace('/^,/', '', $port['tagged']);
    }
    unset($port);

    return $port_status;
}


function get_port_vlan($vendor, $port, $port_index, $ip, $snmp)
{
    if (!isset($port_index)) {
        return;
    }

    if (!isset($ip)) {
        return;
    }

    //default - default port index
    $port_oid = dot1qPortVlanEntry . "." . $port_index;

    //tplink
    if ($vendor == 69) {
        $port_oid = TPLINK_dot1qPortVlanEntry . "." . $port_index;
    }

    //huawei
    if ($vendor == 3) {
        $port_oid = dot1qPortVlanEntry . "." . $port;
    }

    //allied telesys
    if ($vendor == 8) {
        $port_oid = dot1qPortVlanEntry . "." . $port;
    }

    $port_vlan = get_snmp($ip, $snmp, $port_oid);
    $port_vlan = preg_replace('/.*\:/', '', $port_vlan);
    $port_vlan = intval(trim($port_vlan));
    return $port_vlan;
}

function get_ports_poe_state($vendor_id, $ip, $snmp)
{

    if (!isset($vendor_id)) {
        return;
    }
    if (!isset($ip)) {
        return;
    }

    // default poe oid
    $poe_status = PETH_PSE_PORT_ADMIN_ENABLE;

    if ($vendor_id == 3) {
        $poe_status = HUAWEI_POE_OID;
    }
    if ($vendor_id == 6) {
        $poe_status = SNR_POE_OID;
    }
    if ($vendor_id == 8) {
        $poe_status = ALLIED_POE_OID;
    }
    if ($vendor_id == 9) {
        $poe_status = MIKROTIK_POE_OID;
    }
    if ($vendor_id == 10) {
        $poe_status = NETGEAR_POE_OID;
    }
    if ($vendor_id == 15) {
        $poe_status = HP_POE_OID;
    }
    if ($vendor_id == 69) {
        $poe_status = TPLINK_POE_OID;
    }

    $result = [];

    $c_state = walk_snmp($ip, $snmp, $poe_status);
    if (isset($c_state) and !empty($c_state)) {
        foreach ($c_state as $key => $value) {
            if (empty($value)) {
                $value = '';
            }
            $key = trim($key);
            $value = parse_snmp_value($value);
            $port = NULL;
            if (preg_match('/\.(\d{1,10})$/', $key, $matches)) {
                $port = preg_replace('/^\./', '', $matches[0]);
                $result[$port] = $value;
                // patch for mikrotik
                if ($vendor_id == 9) {
                    if ($value == 1) {
                        $result[$port] = 2;
                    }
                    if ($value > 1) {
                        $result[$port] = 1;
                    }
                }
                //patch for tplink
                if ($vendor_id == 69) {
                    if ($value == 0) {
                        $result[$port] = 2;
                    }
                    if ($value >= 1) {
                        $result[$port] = 1;
                    }
                }
            }
        }
    }
    return $result;
}

function get_port_poe_state($vendor_id, $port, $port_snmp_index, $ip, $snmp)
{
    if (!isset($port)) {
        return;
    }
    if (!isset($ip)) {
        return;
    }
    // default poe oid
    $poe_status = PETH_PSE_PORT_ADMIN_ENABLE . "." . $port_snmp_index;

    if ($vendor_id == 3) {
        $poe_status = HUAWEI_POE_OID . "." . $port_snmp_index;
    }

    if ($vendor_id == 6) {
        $poe_status = SNR_POE_OID . "." . $port_snmp_index;
    }

    if ($vendor_id == 8) {
        $poe_status = ALLIED_POE_OID . "." . $port_snmp_index;
    }

    if ($vendor_id == 15) {
        $poe_status = HP_POE_OID . "." . $port_snmp_index;
    }

    if ($vendor_id == 9) {
        $poe_status = MIKROTIK_POE_OID . "." . $port_snmp_index;
    }

    if ($vendor_id == 10) {
        $poe_status = NETGEAR_POE_OID . "." . $port_snmp_index;
    }

    if ($vendor_id == 69) {
        $poe_status = TPLINK_POE_OID . "." . $port;
    }

    $result = '';
    $c_state = get_snmp($ip, $snmp, $poe_status);

    if (isset($c_state) and !empty($c_state)) {
        $p_state = parse_snmp_value($c_state);
        if (empty($p_state)) {
            return NULL;
        }
        // patch for mikrotik
        if ($vendor_id == 9) {
            if ($p_state == 1) {
                return 2;
            }
            if ($p_state > 1) {
                return 1;
            }
        }
        //patch for tplink
        if ($vendor_id == 69) {
            if ($p_state == 0) {
                return 2;
            }
            if ($p_state >= 1) {
                return 1;
            }
        }
        return $p_state;
    }
    return NULL;
}

function set_port_poe_state($vendor_id, $port, $port_snmp_index, $ip, $snmp, $state)
{
    if (!isset($ip)) {
        return;
    }

    //default poe status
    $poe_enable = 1;
    $poe_disable = 2;

    // default poe oid
    $poe_status = PETH_PSE_PORT_ADMIN_ENABLE . "." . $port_snmp_index;

    if ($vendor_id == 3) {
        $poe_status = HUAWEI_POE_OID . "." . $port_snmp_index;
    }

    if ($vendor_id == 8) {
        $poe_status = ALLIED_POE_OID . "." . $port_snmp_index;
    }

    if ($vendor_id == 15) {
        $poe_status = HP_POE_OID . "." . $port_snmp_index;
    }

    if ($vendor_id == 10) {
        $poe_status = NETGEAR_POE_OID . "." . $port_snmp_index;
    }

    if ($vendor_id == 69) {
        $poe_status = TPLINK_POE_OID . "." . $port;
        $poe_enable = 1;
        $poe_disable = 0;
    }

    if ($state) {
        // enable port
        $c_state = set_snmp($ip, $snmp, $poe_status, 'i', $poe_enable);
        return $c_state;
    } else {
        // disable port
        $c_state = set_snmp($ip, $snmp, $poe_status, 'i', $poe_disable);
        return $c_state;
    }
}


function get_ports_poe_detail($vendor_id, $ip, $snmp)
{
    if (!isset($vendor_id)) {
        return;
    }

    if (!isset($ip)) {
        return;
    }

    $result = [];

    $poe_class = PETH_PSE_PORT_POE_CLASS;

    // eltex
    if ($vendor_id == 2) {
        $poe_power = ELTEX_POE_USAGE;
        $poe_current = ELTEX_POE_CURRENT;
        $poe_volt = ELTEX_POE_VOLT;
    }

    // huawei
    if ($vendor_id == 3) {
        $poe_power = HUAWEI_POE_USAGE;
        $poe_current = HUAWEI_POE_CURRENT;
        $poe_volt = HUAWEI_POE_VOLT;
    }

    // snr
    if ($vendor_id == 6) {
        $poe_class = SNR_POE_CLASS;
        $poe_power = SNR_POE_USAGE;
        $poe_current = SNR_POE_CURRENT;
        $poe_volt = SNR_POE_VOLT;
    }

    // AT
    if ($vendor_id == 8) {
        $poe_power = ALLIED_POE_USAGE;
        $poe_current = ALLIED_POE_CURRENT;
        $poe_volt = ALLIED_POE_VOLT;
    }

    // mikrotik
    if ($vendor_id == 9) {
        $poe_power = MIKROTIK_POE_USAGE;
        $poe_current = MIKROTIK_POE_CURRENT;
        $poe_volt = MIKROTIK_POE_VOLT;
    }

    // netgear
    if ($vendor_id == 10) {
        $poe_power = NETGEAR_POE_USAGE;
        $poe_current = NETGEAR_POE_CURRENT;
        $poe_volt = NETGEAR_POE_VOLT;
    }

    // HP
    if ($vendor_id == 15) {
        $poe_power = HP_POE_USAGE;
        $poe_volt = HP_POE_VOLT;
    }

    // TP-Link
    if ($vendor_id == 69) {
        $poe_power = TPLINK_POE_USAGE;
        $poe_current = TPLINK_POE_CURRENT;
        $poe_volt = TPLINK_POE_VOLT;
        $poe_class = TPLINK_POE_CLASS;
    }

    if (isset($poe_power)) {
        $c_power = walk_snmp($ip, $snmp, $poe_power);
        if (isset($c_power)) {
            foreach ($c_power as $key => $value) {
                if (empty($value)) {
                    $value = 'INT:0';
                }
                $key = trim($key);
                $p_power = parse_snmp_value($value);
                $port = NULL;
                if (preg_match('/\.(\d{1,10})$/', $key, $matches)) {
                    $port = preg_replace('/^\./', '', $matches[0]);
                    $result[$port]['power'] = 0;
                    $result[$port]['power_display'] = '';
                    switch ($vendor_id) {
                        case 9: //mikrotik
                            $p_power = round($p_power / 10, 2);
                            break;
                        case 69: //tplink
                            $p_power = round($p_power / 10, 2);
                            break;
                        default:
                            $p_power = round($p_power / 1000, 2);
                            break;
                    }
                    if ($p_power > 0) {
                        $result[$port]['power'] = $p_power;
                        $result[$port]['power_display'] = 'P: ' . $p_power . ' W';
                    }
                }
            }
        }
    }

    if (isset($poe_current)) {
        $c_current = walk_snmp($ip, $snmp, $poe_current);
        if (isset($c_current)) {
            foreach ($c_current as $key => $value) {
                if (empty($value)) {
                    $value = 'INT:0';
                }
                $key = trim($key);
                $p_current = parse_snmp_value($value);
                $port = NULL;
                if (preg_match('/\.(\d{1,10})$/', $key, $matches)) {
                    $port = preg_replace('/^\./', '', $matches[0]);
                    $result[$port]['current'] = 0;
                    $result[$port]['current_display'] = '';
                    if ($p_current > 0) {
                        $result[$port]['current'] = $p_current;
                        $result[$port]['current_display'] = 'C: ' . $p_current . ' mA';
                    }
                }
            }
        }
    }

    if (isset($poe_volt)) {
        $c_volt = walk_snmp($ip, $snmp, $poe_volt);
        if (isset($c_volt)) {
            foreach ($c_volt as $key => $value) {
                if (empty($value)) {
                    $value = 'INT:0';
                }
                $key = trim($key);
                $p_volt = parse_snmp_value($value);
                $port = NULL;
                if (preg_match('/\.(\d{1,10})$/', $key, $matches)) {
                    $port = preg_replace('/^\./', '', $matches[0]);
                    $result[$port]['volt'] = 0;
                    $result[$port]['volt_display'] = '';
                    switch ($vendor_id) {
                        case 2:
                        case 8:
                            $p_volt = round($p_volt / 1000, 2);
                            break;
                        case 9:
                        case 69:
                            $p_volt = round($p_volt / 10, 2);
                            break;
                        case 15:
                            $p_volt = round($p_volt / 100, 2);
                            break;
                    }
                    if ($p_volt > 0 and $result[$port]['power'] > 0) {
                        $result[$port]['volt'] = $p_volt;
                        $result[$port]['volt_display'] = ' V: ' . $p_volt . " V";
                    }
                }
            }
        }
    }

    if (isset($poe_class)) {
        $c_class = walk_snmp($ip, $snmp, $poe_class);
        if (isset($c_class)) {
            foreach ($c_class as $key => $value) {
                if (empty($value)) {
                    $value = 'INT:0';
                }
                $key = trim($key);
                $p_class = parse_snmp_value($value);
                $port = NULL;
                if (preg_match('/\.(\d{1,10})$/', $key, $matches)) {
                    $port = preg_replace('/^\./', '', $matches[0]);
                    $result[$port]['class'] = 0;
                    $result[$port]['class_display'] = '';
                    switch ($vendor_id) {
                        case 69:
                            if ($p_class > 0 and $result[$port]['power'] > 0) {
                                if ($p_class == 7) {
                                    $p_class = 'class-not-defined';
                                }
                                $result[$port]['class_display'] = 'Class: ' . $p_class;
                                $result[$port]['class'] = $p_class;
                            }
                            break;
                        default:
                            if ($p_class > 0 and $result[$port]['power'] > 0) {
                                $result[$port]['class_display'] = 'Class: ' . ($p_class - 1);
                                $result[$port]['class'] = $p_class - 1;
                            }
                            break;
                    }
                }
            }
        }
    }

    foreach ($result as &$port) {
        if (!isset($port['power'])) {
            $port['power'] = 0;
        }
        if (!isset($port['current'])) {
            $port['current'] = 0;
        }
        if (!isset($port['volt'])) {
            $port['volt'] = 0;
        }
        if (!isset($port['class'])) {
            $port['class'] = 0;
        }
    }

    unset($port);

    return $result;
}

function get_port_poe_detail($vendor_id, $port, $port_snmp_index, $ip, $snmp)
{
    if (!isset($port) or !isset($port_snmp_index)) {
        return;
    }
    if (!isset($ip)) {
        return;
    }
    if (!isset($community)) {
        $community = 'public';
    }
    if (!isset($version)) {
        $version = '2';
    }

    $result = '';

    $poe_class = PETH_PSE_PORT_POE_CLASS . $port_snmp_index;

    // eltex
    if ($vendor_id == 2) {
        $poe_power = ELTEX_POE_USAGE . '.' . $port_snmp_index;
        $poe_current = ELTEX_POE_CURRENT . '.' . $port_snmp_index;
        $poe_volt = ELTEX_POE_VOLT . '.' . $port_snmp_index;
    }

    // huawei
    if ($vendor_id == 3) {
        $poe_power = HUAWEI_POE_USAGE . '.' . $port_snmp_index;
        $poe_current = HUAWEI_POE_CURRENT . '.' . $port_snmp_index;
        $poe_volt = HUAWEI_POE_VOLT . '.' . $port_snmp_index;
    }

    // snr
    if ($vendor_id == 6) {
        $poe_class = SNR_POE_CLASS . '.' . $port_snmp_index;
        $poe_power = SNR_POE_USAGE . '.' . $port_snmp_index;
        $poe_current = SNR_POE_CURRENT . '.' . $port_snmp_index;
        $poe_volt = SNR_POE_VOLT . '.' . $port_snmp_index;
    }

    // AT
    if ($vendor_id == 8) {
        $poe_power = ALLIED_POE_USAGE . '.' . $port_snmp_index;
        $poe_current = ALLIED_POE_CURRENT . '.' . $port_snmp_index;
        $poe_volt = ALLIED_POE_VOLT . '.' . $port_snmp_index;
    }

    // mikrotik
    if ($vendor_id == 9) {
        $poe_power = MIKROTIK_POE_USAGE . '.' . $port_snmp_index;
        $poe_current = MIKROTIK_POE_CURRENT . '.' . $port_snmp_index;
        $poe_volt = MIKROTIK_POE_VOLT . '.' . $port_snmp_index;
    }

    // netgear
    if ($vendor_id == 10) {
        $poe_power = NETGEAR_POE_USAGE . '.' . $port_snmp_index;
        $poe_current = NETGEAR_POE_CURRENT . '.' . $port_snmp_index;
        $poe_volt = NETGEAR_POE_VOLT . '.' . $port_snmp_index;
    }

    // HP
    if ($vendor_id == 15) {
        $poe_power = HP_POE_USAGE . '.' . $port_snmp_index;
        $poe_volt = HP_POE_VOLT . '.' . $port_snmp_index;
    }

    // TP-Link
    if ($vendor_id == 69) {
        $poe_power = TPLINK_POE_USAGE . '.' . $port;
        $poe_current = TPLINK_POE_CURRENT . '.' . $port;
        $poe_volt = TPLINK_POE_VOLT . '.' . $port;
        $poe_class = TPLINK_POE_CLASS . "." . $port;
    }

    if (isset($poe_power)) {
        $c_power = get_snmp($ip, $snmp, $poe_power);
        if (isset($c_power)) {
            $p_power = parse_snmp_value($c_power);
            switch ($vendor_id) {
                case 9:
                    $p_power = round($p_power / 10, 2);
                    break;
                case 69:
                    $p_power = round($p_power / 10, 2);
                    break;
                default:
                    $p_power = round($p_power / 1000, 2);
                    break;
            }
            if ($p_power > 0) {
                $result .= ' P: ' . $p_power . ' W';
            }
        }
    }

    if (isset($poe_current)) {
        $c_current = get_snmp($ip, $snmp, $poe_current);
        if (isset($c_current)) {
            $p_current = parse_snmp_value($c_current);
            if ($p_current > 0) {
                $result .= ' C: ' . $p_current . ' mA';
            }
        }
    }

    if (isset($poe_volt)) {
        $c_volt = get_snmp($ip, $snmp, $poe_volt);
        if (isset($c_volt)) {
            $p_volt = parse_snmp_value($c_volt);
            switch ($vendor_id) {
                case 2:
                case 8:
                    $p_volt = round($p_volt / 1000, 2);
                    break;
                case 9:
                case 69:
                    $p_volt = round($p_volt / 10, 2);
                    break;
                case 15:
                    $p_volt = round($p_volt / 100, 2);
                    break;
            }
            if ($p_volt > 0 and $p_power > 0) {
                $result .= ' V: ' . $p_volt . " V";
            }
        }
    }

    if (isset($poe_class)) {
        $c_class = get_snmp($ip, $snmp, $poe_class);
        if (isset($c_class)) {
            $p_class = parse_snmp_value($c_class);
            switch ($vendor_id) {
                case 69:
                    if ($p_class > 0 and $p_power > 0) {
                        if ($p_class == 7) {
                            $p_class = 'class-not-defined';
                        }
                        $result .= ' Class: ' . $p_class;
                    }
                    break;
                default:
                    if ($p_class > 0 and $p_power > 0) {
                        $result .= ' Class: ' . ($p_class - 1);
                    }
                    break;
            }
        }
    }

    return $result;
}

function get_snmp($ip, $snmp, $oid)
{
    snmp_set_oid_output_format(SNMP_OID_OUTPUT_NUMERIC);
    $result = NULL;
    try {
        $version = $snmp["version"];
        if ($version == 3) {
            $result = snmp3_get($ip, $snmp["ro-user"], 'authPriv', $snmp['auth-proto'], $snmp['ro-password'], $snmp["priv-proto"], $snmp["ro-password"], $oid, SNMP_timeout, SNMP_retry);
        }

        if ($version == 2) {
            $result = snmp2_get($ip, $snmp["ro-community"], $oid, SNMP_timeout, SNMP_retry);
        }
        if ($version == 1) {
            $result = snmpget($ip, $snmp["ro-community"], $oid, SNMP_timeout, SNMP_retry);
        }
        if (empty($result)) {
            $result = NULL;
        }
    } catch (Exception $e) {
        #	echo 'Caught exception: ',  $e->getMessage(), "\n";
        $result = NULL;
    }
    return $result;
}

function set_snmp($ip, $snmp, $oid, $field, $value)
{
    $result = false;
    try {
        $version = $snmp["version"];
        if ($version == 3) {
            $result = snmp3_set($ip, $snmp["rw-user"], 'authPriv', $snmp['auth-proto'], $snmp['rw-password'], $snmp["priv-proto"], $snmp["rw-password"], $oid, $field, $value, SNMP_timeout, SNMP_retry);
        }
        if ($version == 2) {
            $result = snmp2_set($ip, $snmp["rw-community"], $oid, $field, $value, SNMP_timeout, SNMP_retry);
        }
        if ($version == 1) {
            $result = snmpset($ip, $snmp["rw-community"], $oid, $field, $value, SNMP_timeout, SNMP_retry);
        }
    } catch (Exception $e) {
        #	echo 'Caught exception: ',  $e->getMessage(), "\n";
        $result = false;
    }
    return $result;
}

function set_port_state($vendor_id, $port, $ip, $snmp, $state)
{
    // port -> snmp_index!!!
    if (!isset($port)) {
        return;
    }
    if (!isset($ip)) {
        return;
    }
    $port_status = PORT_ADMIN_STATUS_OID . '.' . $port;
    if ($state == 1) {
        // enable port
        $c_state = set_snmp($ip, $snmp, $port_status, 'i', 1);
        return $c_state;
    } else {
        // disable port
        $c_state = set_snmp($ip, $snmp, $port_status, 'i', 2);
        return $c_state;
    }
}


function get_ports_state_detail($ip, $snmp)
{

    if (!isset($ip)) {
        return;
    }

    $result = [];

    //post status
    $p_state = walk_snmp($ip, $snmp, PORT_STATUS_OID);
    if (!empty($p_state)) {
        foreach ($p_state as $key => $value) {
            if (empty($value)) {
                $value = '';
            }
            $key = trim($key);
            $value = parse_snmp_value($value);
            $port = NULL;
            if (preg_match('/\.(\d{1,10})$/', $key, $matches)) {
                $port = preg_replace('/^\./', '', $matches[0]);
                $result[$port]['status'] = $value;
                $result[$port]['admin_status'] = 0;
                $result[$port]['speed'] = 0;
                $result[$port]['errors'] = 0;
            }
        }
    }

    //admin state
    $p_admin = walk_snmp($ip, $snmp, PORT_ADMIN_STATUS_OID);
    if (!empty($p_admin)) {
        foreach ($p_admin as $key => $value) {
            if (empty($value)) {
                $value = '';
            }
            $key = trim($key);
            $value = parse_snmp_value($value);
            $port = NULL;
            if (preg_match('/\.(\d{1,10})$/', $key, $matches)) {
                $port = preg_replace('/^\./', '', $matches[0]);
                $result[$port]['admin_status'] = $value;
            }
        }
    }

    //port speed
    $p_speed = walk_snmp($ip, $snmp, PORT_SPEED_OID);
    if (!empty($p_speed)) {
        foreach ($p_speed as $key => $value) {
            if (empty($value)) {
                $value = 'INT:0';
            }
            $key = trim($key);
            $value = parse_snmp_value($value);
            $port = NULL;
            if (preg_match('/\.(\d{1,10})$/', $key, $matches)) {
                $port = preg_replace('/^\./', '', $matches[0]);
                $result[$port]['speed'] = $value;
            }
        }
    }

    //errors at
    $p_errors = walk_snmp($ip, $snmp, PORT_ERRORS_OID);
    if (!empty($p_errors)) {
        foreach ($p_errors as $key => $value) {
            if (empty($value)) {
                $value = 'INT:0';
            }
            $key = trim($key);
            $value = parse_snmp_value($value);
            $port = NULL;
            if (preg_match('/\.(\d{1,10})$/', $key, $matches)) {
                $port = preg_replace('/^\./', '', $matches[0]);
                $result[$port]['errors'] = $value;
            }
        }
    }

    return $result;
}

function get_port_state_detail($port, $ip, $snmp)
{
    if (!isset($port)) {
        return;
    }
    if (!isset($ip)) {
        return;
    }
    // if (!is_up($ip)) { return; }

    $oper = PORT_STATUS_OID . '.' . $port;
    $admin = PORT_ADMIN_STATUS_OID . '.' . $port;
    $speed = PORT_SPEED_OID . '.' . $port;
    $errors = PORT_ERRORS_OID . '.' . $port;
    $result = '';
    $c_state = get_snmp($ip, $snmp, $oper);
    $p_state = parse_snmp_value($c_state);
    $c_admin = get_snmp($ip, $snmp, $admin);
    $p_admin = parse_snmp_value($c_admin);
    if ($p_state == 1) {
        $c_speed = get_snmp($ip, $snmp, $speed);
    } else {
        $c_speed = 'INT:0';
    }
    $p_speed = parse_snmp_value($c_speed);
    $c_errors = get_snmp($ip, $snmp, $errors);
    $p_errors = parse_snmp_value($c_errors);
    $result = $p_state . ";" . $p_admin . ";" . $p_speed . ";" . $p_errors;
    return $result;
}

function parse_snmp_value($value)
{
    if (empty($value)) {
        return NULL;
    }
    if (!preg_match('/:/', $value)) {
        return NULL;
    }
    list($p_type, $p_value) = explode(':', $value);
    if (empty($p_value)) {
        return NULL;
    }
    $p_value = trim($p_value);
    $p_value = preg_replace('/^\"/', '', $p_value);
    $p_value = preg_replace('/\"$/', '', $p_value);
    $p_value = trim($p_value);
    return $p_value;
}

snmp_set_valueretrieval(SNMP_VALUE_LIBRARY);
snmp_set_enum_print(1);
