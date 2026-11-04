<?php

/* common variables */
define("WEB_days","days");
define("WEB_sec","seconds");
define("WEB_date","Date");
define("WEB_bytes","Byte");
define("WEB_pkts","Pkt");
define("WEB_log","Log");
define("WEB_deleted","Deleted");
define("WEB_status","Status");
define("WEB_unknown","No data available");
define("WEB_changed_time","Last changed");

define("WEB_page_speed","Page generated for ");
define("WEB_rows_at_page","Lines");
define("WEB_monitoring","Monitoring");
define("WEB_search","Search");
define("WEB_monitoring_host_up","Host is active");
define("WEB_monitoring_host_down","Host is not available");
define("WEB_monitoring_host_unknown","Status unknown");

/* error messages */
define("WEB_auth_unknown","Client IP address is not set");
define("WEB_msg_exists","already exists!");
define("WEB_msg_ip_error","Address format is not correct!");
define("WEB_device_locked","Device is busy, try again later");

/* common message */
define("WEB_msg_IP","IP address");
define("WEB_msg_ERROR","Error!");
define("WEB_msg_enabled","Enabled");
define("WEB_msg_disabled","Disabled");
define("WEB_msg_login","Login");
define("WEB_msg_username","Username");
define("WEB_msg_password","Password");
define("WEB_msg_fullname","Full name");
define("WEB_msg_description","Description");
define("WEB_msg_now","Now");
define("WEB_msg_forbidden","Forbidden");
define("WEB_msg_traffic_blocked","Blocked");
define("WEB_msg_internet","Internet");
define("WEB_msg_delete","Delete");
define("WEB_msg_additional","Advanced");
define("WEB_msg_unsupported","Not supported");
define("WEB_msg_delete_filter","Remove Filter");
define("WEB_msg_add_filter", "Add filter");
define("WEB_msg_apply_selected","Apply to highlight");
define("WEB_msg_login_hint","Please enter login");
define("WEB_msg_password_hint","Please enter your password");
define("WEB_msg_delete_selected","Delete selected?");
define("WEB_msg_export_selected","Export only selected?");
define("WEB_msg_processing","Processing...");

/* SNMP */
define("WEB_snmp_version","SNMP version");
define("WEB_snmp_v3_user_ro","Snmpv3 RO user");
define("WEB_snmp_v3_user_rw","Snmpv3 RW user");
define("WEB_snmp_v3_auth_proto","Auth Proto");
define("WEB_snmp_v3_priv_proto","Priv Proto");
define("WEB_snmp_v3_ro_password","Snmpv3 RO password");
define("WEB_snmp_v3_rw_password","Snmpv3 RW password");
define("WEB_snmp_community_ro","Snmp RO Community");
define("WEB_snmp_community_rw","Snmp RW Community");
define("WEB_snmp_interface_name","Interface name");
define("WEB_snmp_interface_index","Interface index");

/* color schema description */
define("WEB_color_description","Color coding");
define("WEB_color_user_disabled","User disabled");
define("WEB_color_auth_disabled","IP disabled");
define("WEB_color_auth_enabled","IP enabled");
define("WEB_color_user_blocked","Blocked by traffic");
define("WEB_color_device_description","Device Status");
define("WEB_color_user_empty","Login is empty");
define("WEB_color_user_custom","Customised");
define("WEB_color_user_permanent","Permanent");

/* device and port state */
define("WEB_device_online","Online");
define("WEB_device_down","Down");
define("WEB_port_status","Port state");
define("WEB_port_oper_down","Oper down");
define("WEB_port_oper_up","Open up");
define("WEB_port_admin_shutdown","Admin off");
define("WEB_port_speed","Port speed");
define("WEB_port_speed_10","10M");
define("WEB_port_speed_100","100M");
define("WEB_port_speed_1G","1G");
define("WEB_port_speed_10G","10G");

/* select items */
define("WEB_select_item_yes","Yes");
define("WEB_select_item_no","No");
define("WEB_select_item_lease","Lease added");
define("WEB_select_item_enabled","Enabled");
define("WEB_select_item_wan","External");
define("WEB_select_item_lan","Internal");
define("WEB_select_item_all_ips","All ip");
define("WEB_select_item_static","Static IP");
define("WEB_select_item_dhcp","Dhcp IP");
define("WEB_select_item_suspicious","Suspicious IP");
define("WEB_select_item_every","All");
define("WEB_select_item_all","All");
define("WEB_select_item_events","All events");
define("WEB_select_item_disabled","Disabled");
define("WEB_select_item_forbidden","Prohibit");
define("WEB_select_item_more","A lot");
define("WEB_select_item_lease_refresh","Lease renewal");
define("WEB_select_item_lease_free","Lease released");
define("WEB_select_item_allow","Allow");

/* submenu */
define("WEB_submenu_dhcp_log","dhcp Log");
define("WEB_submenu_work_log","Work Log");
define("WEB_submenu_mac_history","Mac Adventures");
define("WEB_submenu_ip_history","ip Address History");
define("WEB_submenu_mac_unknown", "Unknown");
define("WEB_submenu_traffic","Traffic");
define("WEB_submenu_syslog","syslog");
define("WEB_submenu_control","Management");
define("WEB_submenu_network","Networks");
define("WEB_submenu_network_stats","Networks (Statistics)");
define("WEB_submenu_options","Parameters");
define("WEB_submenu_customers","Users");
define("WEB_submenu_filter_list","Filter List");
define("WEB_submenu_filter_ipset","Address-list");
define("WEB_submenu_filter_group","Filter Groups");
define("WEB_submenu_filter_instances","Filter instances");
define("WEB_submenu_filter_instance","Filter instance");
define("WEB_submenu_traffic_ip_report","Traffic report for ip");
define("WEB_submenu_traffic_login_report","Traffic report for login");
define("WEB_submenu_traffic_wan_report","WAN statistics");
define("WEB_submenu_traffic_top10","TOP 10 in traffic");
define("WEB_submenu_detail_log","Detailed log");
define("WEB_submenu_net_devices","Network devices");
define("WEB_submenu_passive_net_devices","Passive devices");
define("WEB_submenu_buildings","Location");
define("WEB_submenu_hierarchy","Structure");
define("WEB_submenu_device_models","Device Models");
define("WEB_submenu_vendors","Vendors");
define("WEB_submenu_ports_vlan","Ports by vlans");
define("WEB_submenu_ports","Ports");
define("WEB_submenu_state","State");
define("WEB_submenu_connections","Connections");
define("WEB_submenu_ip_list","Address List");
define("WEB_submenu_monitoring","Information for monitoring");
define("WEB_submenu_doubles","Duplicates");
define("WEB_submenu_deleted","Remote addresses");
define("WEB_submenu_auto_rules","Auto-assignment rules");

/* header title */
define("WEB_site_title","Admin Panel");
define("WEB_title_reports","Report");
define("WEB_title_groups","Groups");
define("WEB_title_users","Users");
define("WEB_title_users_ips","All ip's");
define("WEB_title_filters","Filters");
define("WEB_title_shapers","Shapers");
define("WEB_title_devices","Infrastructure");
define("WEB_title_logs","Logs");
define("WEB_title_control","Settings");
define("WEB_title_exit","Exit");

/* traffic headers */
define("WEB_title_ip","Address");
define("WEB_title_date","Date");
define("WEB_title_input","Incoming");
define("WEB_title_output","Outgoing");
define("WEB_title_forward_input","Forward, in");
define("WEB_title_forward_output","Forward, out");
define("WEB_title_pktin","IN, pkt/s");
define("WEB_title_pktout","OUT, pkt/s");
define("WEB_title_maxpktin","Max IN, pkt/s");
define("WEB_title_maxpktout","Max OUT, pkt/s");
define("WEB_title_sum","SUM");
define("WEB_title_itog","Total");

/* table cell names */
define("WEB_cell_login","Login");
define("WEB_cell_ou","Group");
define("WEB_cell_enabled","Enabled");
define("WEB_cell_blocked","Blocking");
define("WEB_cell_perday","Per day");
define("WEB_cell_permonth","Per month");
define("WEB_cell_report","Report");
define("WEB_cell_name","Name");
define("WEB_cell_ip","IP");
define("WEB_cell_mac","MAC");
define("WEB_cell_clientid","Client-id");
define("WEB_cell_host_firmware","Firmware");
define("WEB_cell_sn","SN");
define("WEB_cell_description","Description");
define("WEB_cell_wikiname","Wiki Name");
define("WEB_cell_filter","Filter");
define("WEB_cell_proxy","Proxy");
define("WEB_cell_dhcp","Dhcp");
define("WEB_cell_dhcp_hostname","DHCP-hostname");
define("WEB_cell_nat","NAT");
define("WEB_cell_transparent","Transparent");
define("WEB_cell_shaper","Shaper");
define("WEB_cell_connection","Connected");
define("WEB_cell_last_found","Last MAC/ARP activity");
define("WEB_cell_arp_found","Last ARP activity");
define("WEB_cell_mac_found","Last MAC activity");
define("WEB_cell_ptr_only","Create only PTR");
define("WEB_cell_dns_name","Dns Name");
define("WEB_cell_aliases","Aliases");
define("WEB_cell_host_model","Device Model");
define("WEB_cell_monitoring","Monitoring");
define("WEB_cell_nagios_handler","Event response");
define("WEB_cell_link","Link");
define("WEB_cell_traf","Traffic recording");
define("WEB_cell_acl","dhcp acl");
define("WEB_cell_option_set","dhcp option set");
define("WEB_cell_le","Rules");
define("WEB_cell_login_quote_month","Quota by login, month");
define("WEB_cell_ip_quote_month","Quota per ip, month");
define("WEB_cell_login_quote_day","Quota by login, day");
define("WEB_cell_ip_quote_day","Quota per ip, day");
define("WEB_cell_type","Type");
define("WEB_cell_skip","Skip");
define("WEB_cell_vlan","Vlan");
define("WEB_cell_mac_count","Mac count");
define("WEB_cell_forename","Name");
define("WEB_cell_flags","Flags");
define("WEB_cell_created","Created");
define("WEB_cell_update","Updated");
define("WEB_cell_created_by","Created by");
define("WEB_cell_deleted","Deleted");
define("WEB_cell_gateway","Gateway");
define("WEB_cell_rule", "Rules");
define("WEB_cell_password","Password");
define("WEB_cell_control_proto","Protocol");
define("WEB_cell_control_port","Port");
define("WEB_cell_poe_in","POE In");
define("WEB_cell_poe_out","POE");
define("WEB_cell_dynamic","Dynamic");
define("WEB_cell_temporary","Temporary record");
define("WEB_cell_end_life","The END time");
define("WEB_cell_life_hours","Life duration,hours");

/* lists name */
define("WEB_list_ou","List of groups");
define("WEB_list_subnet","List of Subnets");
define("WEB_list_customers","List of administrators");
define("WEB_list_filters","List of filters");
define("WEB_list_users","List of users");
define("WEB_list_models","List of device models");
define("WEB_list_vendors","List of vendors");
define("WEB_list_queues","List of shapers");

/* ipset */
define("WEB_error_ipset_name","Invalid ipset name (a-z only, A-Z, 0-9, _, -)");
define("WEB_error_ip_address","Invalid IP address format: ");
define("WEB_ipset_edit","IPSet Editing");
define("WEB_ipset_name_hint","Only a-z, A-Z, 0-9, _, -");
define("WEB_ipset_massadd","Bulk addition");
define("WEB_ipset_massadd_hint","Format: <code>IP address [tab] description</code>. Lines with # are ignored.");
define("WEB_ipset_empty","The list is empty");
define("WEB_ipset_clear","Clear EVERYTHING");
define("WEB_ipset_clear_qa","Are you sure? ALL entries will be deleted!");
define("WEB_record_count","Elements (total: ");

/* button names */
define("WEB_btn_remove","Delete");
define("WEB_btn_add","Add");
define("WEB_btn_save","Save");
define("WEB_btn_move","Move");
define("WEB_btn_config_apply","Apply Configuration");
define("WEB_btn_device","+Device");
define("WEB_btn_mac_add","+MAC");
define("WEB_btn_mac_del","-MAC");
define("WEB_btn_ip_add","+IP");
define("WEB_btn_ip_del","-IP");
define("WEB_btn_run","Execute");
define("WEB_btn_refresh","Refresh");
define("WEB_btn_delete","Delete");
define("WEB_btn_export","Export");
define("WEB_btn_apply","Apply");
define("WEB_btn_show","Show");
define("WEB_btn_reorder","Apply Order");
define("WEB_btn_recover","Restore");
define("WEB_btn_transfom","Transform");
define("WEB_btn_login","Enter");
define("WEB_btn_apply_selected","Apply for selected");
define("WEB_btn_save_filters","Save filters");
define("WEB_btn_on","On");
define("WEB_btn_off","Off");
define("WEB_btn_auto_clean","Auto clean");
define("WEB_btn_auto_mark","Mark old");

/* control options */
define("WEB_config_remove_option","Parameter removed");
define("WEB_config_set_option","Parameter changed");
define("WEB_config_add_option","Parameter added");
define("WEB_config_parameters","Settings");
define("WEB_config_option","Parameter");
define("WEB_config_value","Value");

/* control-subnets-usage */
define("WEB_network_usage_title","Usage statistics for the organization's networks");
define("WEB_network_subnet","Network");
define("WEB_network_vlan","Vlan");
define("WEB_network_all_ip","Total<br>addresses");
define("WEB_network_used_ip","Busy");
define("WEB_network_free_ip","Free<br>(total)");
define("WEB_network_dhcp_size","Size<br>dhcp pool");
define("WEB_network_dhcp_used","Used<br>in the pool");
define("WEB_network_dhcp_free","Free<br>in the pool");
define("WEB_network_static_free","Free<br>(static)");
define("WEB_network_zombi_dhcp","Zombies, dhcp");
define("WEB_network_zombi","Zombies, total");
define("WEB_network_notify","Notify");

/* control subnets */
define("WEB_network_org_title","Organization networks");
define("WEB_network_gateway","Gateway");
define("WEB_network_use_dhcp","DHCP");
define("WEB_network_static","Static");
define("WEB_network_dhcp_first","DHCP start");
define("WEB_network_dhcp_last","DHCP end");
define("WEB_network_dhcp_leasetime","Lease time,m");
define("WEB_network_office_subnet","Office");
define("WEB_network_hotspot","Hot Spot");
define("WEB_network_vpn","VPN");
define("WEB_network_free","Free");
define("WEB_network_dyndns","Update dns<br>from dhcp");
define("WEB_network_discovery","Discovery");
define("WEB_network_create","New network");

/* control */
define("WEB_control_access","Access Control");
define("WEB_control_dhcp","dhcp config");
define("WEB_control_dns","dns config");
define("WEB_control_nagios","Reconfigure Nagios");
define("WEB_control_nagios_clear_alarm","Nagios - clear alarm");
define("WEB_control_scan_network","Network scan");
define("WEB_control_fping_scan_network","Active Scan");
define("WEB_control_log_traffic_on","Enable traffic logging for everyone");
define("WEB_control_log_traffic_off","Turn off traffic logging for everyone");
define("WEB_control_clear_dns_cache","Clear DNS Cache");
define("WEB_control_port_off","Port Control");
define("WEB_control_edit_mode","Configuration Mode");

/* editcustom */
define("WEB_customer_titles","Administrator");
define("WEB_customer_login","Login");
define("WEB_customer_password","Password");
define("WEB_customer_mode","Access rights");
define("WEB_customer_api_key","API Key");
define("WEB_customer_api_key_once","The api key is shown only now, right after the record is created. Keep it: the interface will not show it again");
define("WEB_customer_api_key_wrong","The api key must contain exactly 20 characters");
define("WEB_customer_api_key_regenerate","Issue a new api key (the current key is not shown)");
define("WEB_customer_api_key_renewed","A new api key has been issued, write it down: the interface will not show it again");

/* custom index */
define("WEB_customer_index_title","Administrators");

/* ipcam */
define("WEB_control_group","For group");
define("WEB_control_port_poe_off","Turn off ports");
define("WEB_control_port_poe_on","Enable ports");

/* public user */
define("WEB_msg_auth_unknown","not listed");
define("WEB_msg_user_unknown","Owned by a non-existent user. Probably the entry has been deleted.");
define("WEB_msg_traffic_for_ip","Traffic to address");
define("WEB_msg_traffic_for_login","Client traffic");
define("WEB_public_day_traffic","per day, (In/Out)");
define("WEB_public_month_traffic","per month, (In/Out)");

/* device models */
define("WEB_model_vendor","Vendor");
define("WEB_devices","Devices");
define("WEB_model_parameters","Parameters of the device model");
define("WEB_model_system","A model of the installation: its vendor and name can not be changed");
define("WEB_model_name_empty","The name of the model can not be empty");
define("WEB_models","Models");
define("WEB_nagios_template","Nagios Template");

/* edit_l3int */
define("WEB_list_l3_interfaces","List of L3 interfaces");
define("WEB_l3_interface_add","Add interface");
define("WEB_l3_interface_bandwidth","Bandwidth");
define("WEB_list_gateway_subnets","List of subnets that work through the gateway");
define("WEB_list_l3_networks","List of networks");

/* editdevice */
define("WEB_location_name","Location");
define("WEB_device_access_control","Access Control");
define("WEB_device_queues_enabled","Shapers");
define("WEB_device_connected_only","Router networks only");
define("WEB_device_dhcp_server","DHCP-Server");
define("WEB_device_snmp_hint","Some devices give mac-table by port index in snmp, others - by number");
define("WEB_device_mac_by_oid","Mac by snmp");
define("WEB_device_mac_table","Show mac table");
define("WEB_device_walk_port_list","Port Walk");
define("WEB_device_port_count","Ports");
define("WEB_device_save_netflow","Save Netflow");

/* editport */
define("WEB_device_port_number","Port N");
define("WEB_device_port_name","Port");
define("WEB_device_port_snmp_index","Snmp index");
define("WEB_device_port_uplink_device","Connected device");
define("WEB_device_port_uplink","Uplink");
define("WEB_device_port_allien","Don't check");

/* devices: index-passive */
define("WEB_device_type_show","Hardware type");
define("WEB_device_hide_unknown","Hide unknowns");
define("WEB_device_show_location","Hardware location");

/* mac table */
define("WEB_device_mac_table_show","List of active macs on hardware");
define("WEB_device_port_mac_table_show","List of active macs on the hardware at port");
define("WEB_device_port_mac_table_history","List of macs ever found on the port");

/* portsbyvlan */
define("WEB_device_ports_by_vlan","List of ports in vlan");

/* switchport connection */
define("WEB_device_port_connections","List of connections on ports");

/* switchport */
define("WEB_device_port_list","Port List");
define("WEB_device_connected_endpoint","User/Device");
define("WEB_device_first_port_snmp_value","SNMP index of the first port");
define("WEB_device_recalc_snmp_port","Recalculate snmp indexes");

/* switchport-status */
define("WEB_device_port_state_list","Port State");
define("WEB_device_snmp_port_oid_name","IfName");
define("WEB_device_port_speed","Speed");
define("WEB_device_port_errors","Errors");
define("WEB_device_poe_control","POE control");
define("WEB_device_port_control","Port control");
define("WEB_device_port_on","Enable port");
define("WEB_device_port_off","Turn off port");
define("WEB_device_poe_on","Enable POE");
define("WEB_device_poe_off","Turn off POE");

/* edit filter */
define("WEB_title_filter","Filter");
define("WEB_filter_type","Filter type");
define("WEB_traffic_action","Action");
define("WEB_traffic_dest_address","Dst ip");
define("WEB_traffic_source_address","Src ip");
define("WEB_traffic_proto","Proto");
define("WEB_traffic_src_port","Src-port");
define("WEB_traffic_dst_port","Dst-port");
define("WEB_traffic_dst_subnet","IP address/network");
define("WEB_traffic_dst_ipset","Address list");
define("WEB_traffic_select_ipset","Select address list");
define("WEB_traffic_dst_hint","Specify the IP address or network in the destination field");

/* edit group filters */
define("WEB_title_group","Group");
define("WEB_group_instances","Filter instances");
define("WEB_group_instance_name","Instance name");
define("WEB_groups_filter_list","Group filter list");
define("WEB_group_filter_order","Rules order");
define("WEB_group_filter_name","Filter name");

/* edit OU */
define("WEB_ou_parent","Parent group");
define("WEB_ou_autoclient_rules","Rules for autoclient clients");
define("WEB_ou_rules_for_autoassigning","Rules for autoassigning addresses in");
define("WEB_ou_rules_order","Apply order");
define("WEB_ou_rule","Rule");
define("WEB_ou_new_rule","New rule");

/* auto rules */
define("WEB_rules_target","User/Group");
define("WEB_rules_target_user","User");
define("WEB_rules_target_group","Group");
define("WEB_rules_search_target","Rule target");
define("WEB_rules_search_type","Rule type");
define("WEB_rules_type_subnet","Subnet");
define("WEB_rules_type_mac","Mac");
define("WEB_rules_type_hostname","Hostname");

/* all ip list */
define("WEB_ips_show_by_state","By activity");
define("WEB_ips_show_by_ip_type","By ip type");
define("WEB_ips_search_host","Search ip,mac or description");
define("WEB_ips_search","Search");
define("WEB_selection_title","Apply to Selection");
define("WEB_ips_search_full","Search by description/ip/mac/dhcp hostname");

/* logs */
define("WEB_log_start_date","Start");
define("WEB_log_stop_date","End");
define("WEB_date_shift","Time preset");
define("WEB_date_shift_hour","Last hour");
define("WEB_date_shift_8hour","Last 8 hour");
define("WEB_date_shift_day","Last day");
define("WEB_date_shift_month","Last month");
define("WEB_log_level_display","Log level");
define("WEB_log_filter_source","Source");
define("WEB_log_filter_event","Event");
define("WEB_log_message","Message");
define("WEB_log_time","Time");
define("WEB_log_manager","Administrator");
define("WEB_log_level","Level");
define("WEB_log_event","Event");
define("WEB_log_event_type","Event type");
define("WEB_log_detail_for","Detail for");
define("WEB_log_full","Full log");
define("WEB_log_select_ip_mac","ip or mac");
define("WEB_log_report_by_device","Device report");
define("WEB_log_mac_history_hint","Here is the history of all the macs/ip that used to work.<br>If you need to find a place to connect, watch mac adventures!<br>");
define("WEB_log_dhcp_add","Create lease");
define("WEB_log_dhcp_del","Release ip");
define("WEB_log_dhcp_old","Refresh lease");

/* reports */
define("WEB_report_user_traffic","User traffic");
define("WEB_report_traffic_for_ip","for address");
define("WEB_report_detail","Detail");
define("WEB_report_top10_in","Top 10 by incoming traffic");
define("WEB_report_top10_out","Top 10 by outgoing traffic");
define("WEB_report_by_day","Traffic per day");

/* user info */
define("WEB_user_alias_for","Alias for");
define("WEB_user_dns_add_alias","New alias");
define("WEB_user_title","User access address");
define("WEB_user_rules_for_autoassign","Parameters for autoassigned addresses");
define("WEB_user_rule_list","List of rules");
define("WEB_user_ip_list","List of access addresses");
define("WEB_user_add_ip","New IP access address");
define("WEB_user_add_mac","Mac (optional)");
define("WEB_user_list_apply","Apply to list");
define("WEB_new_user","New user");
define("WEB_user_deleted","belongs to a non-existent user. Probably the record has been deleted");
define("WEB_user_bind_mac","Bind mac for login");
define("WEB_user_unbind_mac","Unbind mac for login");
define("WEB_user_bind_ip","Bind ip-address for login");
define("WEB_user_unbind_ip","Unbind ip-address for login");
define("WEB_user_create_netdev","Create network device");
define("WEB_user_permanent","Permanent");

/* public */
define("WEB_msg_access_login","Internet for login");
define("WEB_msg_access_ip","Internet for ip");
define("WEB_traffic_stats","Current statisticks for");

/* nofify */
define("WEB_NOTIFY_NONE","Disabled");
define("WEB_NOTIFY_CREATE","Create");
define("WEB_NOTIFY_UPDATE","Change");
define("WEB_NOTIFY_DELETE","Delete");

/* Nagios */
define("WEB_nagios","Nagios");
define("WEB_nagios_host_up","Host is active");
define("WEB_nagios_host_down","Host is not available");
define("WEB_nagios_host_unknown","Status unknown");


/* --- eye theme: labels used by inc/footer.php and js/eye.js --- */
define("WEB_perf_page","Page generated in");
define("WEB_perf_sec","sec");
define("WEB_perf_memory","Memory (peak):");
define("WEB_perf_used","Changed:");
define("WEB_lbl_dense","Compact");
define("WEB_lbl_normal","Normal");
define("WEB_lbl_roomy","Roomy");
define("WEB_lbl_theme","Theme: auto");
define("WEB_lbl_theme_light","Theme: light");
define("WEB_lbl_theme_dark","Theme: dark");
define("WEB_lbl_selected","selected");
define("WEB_lbl_clear","Clear");
define("WEB_lbl_failed","Failed:");
define("WEB_msg_filter_hint","Filters are applied immediately");
define("WEB_btn_close","Close");
define("WEB_lbl_typeahead","Type to search");
define("WEB_lbl_nomatch","Nothing found");

/* zabbix monitoring */
define("WEB_cell_monitoring_status","Monitoring status");
define("WEB_host_monitoring_device","Monitoring host of the device:");
define("WEB_monitoring_disabled_host","this device is not monitored");
define("WEB_monitoring_no_device","The monitoring system sees the device of the user, this address has no device of eye, so there is no host");
define("WEB_zabbix_mass_tag_add","Add the tag of zabbix");
define("WEB_zabbix_mass_tag_del","Remove the tag of zabbix");
define("WEB_zabbix_mass_macro_add","Assign the macro of zabbix");
define("WEB_zabbix_mass_macro_del","Remove the macro of zabbix");
define("WEB_zabbix_mass_tags","tags of zabbix are assigned to the devices");
define("WEB_zabbix_mass_macros","macros of zabbix are assigned to the devices");
define("WEB_zabbix_host_groups","Zabbix host groups");
define("WEB_zabbix_group_not_found","group not found in zabbix:");
define("WEB_zabbix_group_id","Host group id");
define("WEB_zabbix_group_api_unavailable","Zabbix api is not available, the host group id is typed manually");
define("WEB_zabbix_group","Host group");
define("WEB_zabbix_group_add","Add host group");
define("WEB_zabbix_group_select_empty","- select a host group -");
define("WEB_zabbix_group_no_free","zabbix has no free host groups");
define("WEB_zabbix_templates","Templates of the model zabbix");
define("WEB_zabbix_template","Zabbix template");
define("WEB_zabbix_template_add","Add template");
define("WEB_zabbix_templates_check","Check templates in zabbix");
define("WEB_zabbix_template_missing","Templates are not found in zabbix:");
define("WEB_zabbix_template_renamed","Template names were updated from zabbix:");
define("WEB_zabbix_host_title","Zabbix host");
define("WEB_zabbix_host_none","The host of this record is not found in zabbix");
define("WEB_zabbix_host_created","Zabbix: host %s created (%d template(s), %d macro(s), %d tag(s), %d host group(s))");
define("WEB_zabbix_host_updated","Zabbix: host %s updated: %s");
define("WEB_zabbix_host_unchanged","Zabbix: host %s is up to date");
define("WEB_zabbix_host_disabled","Zabbix: host %s is already disabled");
define("WEB_zabbix_host_switched_off","Zabbix: monitoring of host %s is switched off");
define("WEB_zabbix_host_missing","Zabbix: there is no host of this record yet");
define("WEB_zabbix_host_failed","Zabbix: host %s is not updated, check the api errors in the log");
define("WEB_zabbix_record_skip","This record can not be exported to zabbix: the record or its device does not exist, or the device with monitoring is a switch or a router");
define("WEB_zabbix_host_note_renamed","the name");
define("WEB_zabbix_host_note_visible","the description");
define("WEB_zabbix_host_note_tags","the tags");
define("WEB_zabbix_host_note_templates","new templates");
define("WEB_zabbix_host_note_macros","the macros");
define("WEB_zabbix_host_note_status","monitoring is switched on again");
define("WEB_zabbix_sync_now","Export to zabbix");
define("WEB_zabbix_sync_off","Switch the host off in zabbix");
define("WEB_zabbix_macros_global","The macros and the tags are bound to a device, not to its model:");
define("WEB_submenu_zabbix_macros","Zabbix macros");
define("WEB_zabbix_macros_page","Zabbix macros: the global list of the names");
define("WEB_zabbix_used_by_devices","Devices using");
define("WEB_zabbix_macro_load_all","Load the macros of the zabbix templates");
define("WEB_zabbix_template_select_empty","- select a template -");
define("WEB_zabbix_macro_select_empty","- type the macro name -");
define("WEB_zabbix_api_unavailable","no macros or templates received from zabbix");
define("WEB_zabbix_macros_device","Macros of the device");
define("WEB_zabbix_macro_select_keep_empty","- select a macro of the zabbix templates -");
define("WEB_zabbix_macro_manual","or type the macro name");
define("WEB_zabbix_macro_select_model_empty","- select a macro of the global list -");
define("WEB_zabbix_macros_hint","The macro names are edited in the global list of the settings, the value of every macro and the tag belong to the concrete device");
define("WEB_zabbix_macro","Macro");
define("WEB_zabbix_value","Value");
define("WEB_zabbix_used","Used");
define("WEB_zabbix_macro_type","Type");
define("WEB_zabbix_macro_type_text","Text");
define("WEB_zabbix_macro_type_secret","Secret");
define("WEB_zabbix_macro_load","Load macros from the zabbix templates");
define("WEB_zabbix_disabled","Zabbix monitoring is disabled or the api is not configured in the config options");
define("WEB_zabbix_model","Device model:");
define("WEB_zabbix_add_macro","Add macro");
define("WEB_zabbix_unlink","Unlink templates");
define("WEB_zabbix_remove","Remove");

define("WEB_zabbix_macro_hint","An empty value of the used macro takes the default value of the macros list while the host is created");
define("WEB_zabbix","Zabbix");
define("WEB_zabbix_templates_ok","All templates of the model are present in zabbix");
define("WEB_zabbix_tags_device","Tags of the device");
define("WEB_zabbix_host_note_groups","the host groups");
define("WEB_zabbix_groups_hint","The host of the organizational unit is created in every bound host group, without the bound groups the default group of zabbix is used. Unbinding a group takes it away from the hosts of this organizational unit");
define("WEB_zabbix_model_templates_hint","The templates of the monitoring are bound to the model, the macros and the tags belong to the concrete device");
define("WEB_submenu_zabbix_tags","Zabbix tags");
define("WEB_zabbix_tags_page","Zabbix tags: the global list of the names");
define("WEB_zabbix_tags_global","The tags list is global:");
define("WEB_zabbix_tags_hint","The tag name is edited in the global list of the settings, the value of the tag belongs to the concrete device");
define("WEB_zabbix_tag","Tag");
define("WEB_zabbix_tag_manual","Name of the tag");
define("WEB_zabbix_add_tag","Add tag");
define("WEB_zabbix_tag_select_model_empty","- select a tag of the global list -");
define("WEB_zabbix_list_is_empty","the global list has no free names");
define("WEB_zabbix_group_none","no group");
define("WEB_zabbix_tag_none","no tags");
?>
