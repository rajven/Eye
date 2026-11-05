# Changelog 3-2-6 - release
- bugfix: the tree of the devices was glued together out of a script text, while print_url() puts the address of a link in quotes now - a name of a
  device with a quote broke the string of the script, and the structure stopped being drawn at all. the nodes are gathered in an array and written by
  the json encoder: a text of a record is escaped by it, the anchor of a node is made by jstree itself out of its attribute, and a circle of uplinks,
  where two devices point into ports of each other, is walked once instead of going round for ever;
- bugfix: the plain page of the structure lost the decoration of its nodes (the model of a device in italics) when print_url() began to escape a name of
  a link: the name of a node is gathered from the escaped pieces of a text and the markup of eye, and is carried by print_url_html() now
- bugfix: the page of the poe ports of a building used a class of a row which the page never set, php wrote
  Undefined variable $cl into the log of the web server on every open of the page
- changed: a name of a record, an address, a port name read by snmp, a vendor of a mac, a description and a wiki name are escaped
  where they enter a page: the rows of the lists of the devices, of the subscribers and their addresses, of the ports of a switch,
  of the journals of mac, ip, dhcp and unknown mac, of the filters with their groups and ipsets, of the subnets, of the buildings and
  of the page of the poe ports go through create_url(), print_url() and the new esc() now, and the addresses of the links are put in
  quotes; the markup of eye itself, the yes and no marks of a field, stays a markup, print_url_html() is for it
- bugfix: the mark of the monitoring host of the monitoring page was printed through the name of a link, so since create_url() escapes a name
  of a record the cell showed the tags of the mark as a text: <span class="qa yes">&#10003;</span>. a link of eye takes a text by create_url(), and a
  link of a markup of eye itself takes a new create_url_html(), the page of the monitoring uses the second one;
- changed: the cells of the rows of the monitoring page are made by the same helpers as the rest of the interface: a name of a device and a login of its
  owner can not carry a markup of a third party any more, the cells of the type and of the address are escaped too.
- changed: a request of the api is let in by its key alone: the login which the caller types is not looked at, the customer whose
  api key matches the database is the caller, and the rights of the request are the rights of that customer. /api.php answers 401
  to a request without a key, with a key shorter than twenty characters or with a key which no customer owns.
- added: customers.api_key_hash - the api keys of the customers are kept as a keyed hash of them (HMAC SHA256 with the pepper of
  the config), a key is never stored in the clear and can not be read back or played back from a stolen database; the hash is
  unique and is looked up by an index.
- removed: customers.api_key as a value which eye writes - the column stays for the installations which still keep a plain key
  there, the first request with such a key is accepted and the row is moved to the hash by the same request, after that the
  column is left empty.
- changed: the interface of a customer writes only the hash, the value of a new or regenerated key lives in the session of the
  answer which shows it once; a key of a new customer is any value from twenty to sixty four characters now.
- bugfix: the methods of the entities handed the name of the action to the rights check as "sendupdate", so a customer of the
  operator level could not change a single record over the api, while the same rights of his allow it in the interface.

# Changelog 3-2-5 - release
- added: eyelib collects the mail notices of a script run into one letter: a script which cleans or rebuilds many records sets one_mail_block = 1, its
  create, update and delete notices are kept and flush_mail_block() sends them together at the end, an interrupted run sends what it has collected when it
  exits, the block is cut at one hundred notices because the whole of them is in the worklog of eye anyway. garbage.pl works that way now, a run of it makes
  one letter instead of a letter per record.
- bugfix: a letter with a cyrillic subject died with "Wide character in subroutine entry" inside the mime encoder, the decoded text of eye is now encoded
  into utf8 bytes before the base64 of the header and of the body, an already encoded string is taken as it is. garbage.pl stopped on the first such record,
  so a cleanup which reached a user with a russian description neither finished its work nor cleaned the dhcp leases of the dynamic users.
- bugfix: garbage.pl compared the place of a mac with its first mac_history record instead of the last one, so every mac which moved once got a new history
  record by every run of the garbage collection.
- changed: garbage.pl looks for a duplicate connection over a hash of the port and authentication pairs, the pairs with an unset port or an unset
  authentication were skipped before and stayed in the table forever.
- changed: the retention of the debug entries of the worklog is taken from the number at the top of garbage.pl, it was hardcoded in the middle of the script.
- changed: the form of a device model shows only what the site monitors with: the nagios template field appears when nagios is the
  monitoring system, the block of the zabbix templates when zabbix is it with its api configured, and a chosen zabbix without the
  credentials keeps its single note about the api. saving the parameters of a model never touches the nagios template when the
  field is hidden, an empty form does not erase the template of a model which nagios does not monitor.
- bugfix: create_url() demanded a string, so print_url() of a record with an empty field raised a type error instead of printing
  an empty link, the values of the link are made strings inside the function as they were before.
- changed: a device model has its own form admin/customers/editmodel.php which keeps the parameters of the model and the
  templates of zabbix bound to it together; the list of the models only lists the models, creates a new one and removes them.
- removed: admin/customers/devmodels-zabbix.php - the block of the templates of the model lives in the form of the model now.
- changed: the name of a device model is a link to the form of that model wherever the interface prints it: the list of the
  models, the lists of the active and the passive devices, the footer of the form of a device.
- added: create_url() beside print_url(), it returns the markup of a link instead of printing it, and get_device_model_page()
  with get_device_model_link() build the link of a model by its identifier or its value.
- note: the tree view of the devices keeps the name of the model inside the link of the node, a second link there would nest
  inside the first one, so the model stays plain text in the tree.
- note: a model of the installation (an identifier below 10000) keeps its vendor and its name fixed in the form, its poe
  parameters, its nagios template and its zabbix templates stay editable.
- added: the crud methods of the api for the entities of eye: get=<entity> (search and a single record with its related ones), send=add_<entity>, send=update_<entity>, send=del_<entity> for the organizational units (groups), the subscribers, their ip records, the net filters, the groups of filters with their rules, the shapers and the devices.
- added: the fields of a request are checked by the rules of the entity: the ip addresses and the macs are normalized, the subnets of a filter are validated, the references to the groups, the shapers, the models and the buildings are verified, the unique keys are respected, a view only customer gets 403.
- changed: an api answer never carries the secrets: the passwords and the api keys of the customers, the cli and snmp passwords of a device.
- changed: the parameters which left the ip record (monitoring, link_check, nagios_handler, wikiname) are refused with a hint that they belong to the device now.
- added: force=1 of a removal: a record which is in use is not taken away in silence, the answer names its holders, the force releases the references of the shapers, the groups of filters and the filters, re-homes the subscribers of a removed organizational unit to the default one and takes the records which hang on a removed subscriber or the topology of a removed device.
- changed: a new subscriber inherits the state, the shaper and the group of filters of his organizational unit only where the request named none of them, the values chosen over the api are not overwritten any more.
- changed: the api key of a customer is shown only once, in the form right after the creation of the record, an ordinary save of the customer never shows and never touches it; a lost key can be replaced by a new issue which is shown the same single time.
- bugfix: delete_group() compared the identifier of the default group with an assignment, so the removal of an organizational unit did nothing at all, both in the interface and over the api.
- added: devices.link_check, devices.nagios_handler, devices.wikiname - the parameters of the monitoring system belong to the device, it is the host of the monitoring system.
- removed: user_auth.monitoring, user_auth.monitoring_old, user_auth.monitoring_status, user_auth.nagios_handler, user_auth.link_check, user_auth.wikiname - an ip record of a user is not a host since the zabbix export appeared, 3-2-5 update patches move the values to the device of the owner of the address.
- changed: the form of the device carries monitoring, the link check, the event handler and the wiki page of the host; the form of the address only shows the host of the device of its owner.
- changed: the monitoring page lists the devices of eye with their hosts, states, handlers and wiki pages; its mass operations set monitoring, the link check and the handler of the device, and can add and remove a zabbix tag or a zabbix macro of the global list for every selected device.
- changed: the exclusivity of monitoring of the addresses of one user is gone, a host is a device now, so several addresses of a user no longer conflict and the mass operations of the addresses process every selected address.
- changed: sync_zabbix.pl, gen_nagios_config.pl, eyelib::nagios and hmonitor.pl work with the devices only: a monitored device of any type is a host, the parent switch of the equipment is found over the uplink port or over the port of an address of its owner, the state and the event handler are kept in the row of the device.
- changed: scan_ipcam.pl, sync_mikrotik_poe_monitor.pl, set_port_descr.pl and the wiki exchange scripts read the names and the monitoring state of the device instead of the ip record.
- changed: api.php no longer changes wikiname of an address, the new method update_device writes the monitoring parameters of the device.
- note: the hosts created in zabbix for the ip records earlier are left in zabbix untouched, eye does not manage them.

# Changelog 3-2-4 - release
- added: ou_zabbix_groups - an organizational unit is bound to several zabbix host groups, the form of the ou shows the bound groups as a list
  with an unlink check box and adds a new group through a searchable dropdown, so a long list of the groups of zabbix stays readable.
- removed: ou.zabbix_group_id - the single host group of an organizational unit, 3-2-4 update patches drop it for mysql and postgres.
- added: zabbix_tags_list - the global list of the zabbix host tag names, and zabbix_tags - the tags with their values assigned to the host of an eye network device.
- added: zabbixtags.php page in the settings - the global list of the tag names, the value of a tag belongs to the device.
- changed: zabbix macros are bound to an eye network device (zabbix_macros.device_id) instead of a device model, the existing model bindings are dropped by the update.
- removed: zabbix_macros_list.default_value - the global macros list contains the names only, the value of a macro is set for every device.
- changed: the device model form keeps the templates only, the macros and the tags of the host are edited in the form of the device.
- changed: the export of a host adds the missing host groups of the organizational unit, refreshes the tags and the macros of the device and removes the tags and the macros which eye does not assign anymore. the tags, the macros and the groups of the other integrations are kept.
- changed: unbinding a host group in the form of the organizational unit takes that group away from the hosts of this ou at once, the reconciliation of a host itself never removes a group.
- changed: web interface and sync_zabbix.pl use the same rules of the groups, the macros and the tags, so both ways give the same host in zabbix.

# Changelog 3-2-3 - release
- added: the zabbix host of a device or an ip record is created, updated and switched off by the web interface itself, the sync_zabbix.pl script is kept as a fallback for the scheduled reconciliation.
- added: the forms of the device and of the ip record show the state of the zabbix host and the buttons of the manual export and of switching the host off.
- changed: user_auth.device_model_id is dropped, the model of the addresses is taken from the device bound to them only.
- changed: monitoring of the addresses of one user is exclusive, while one of the addresses is monitored the field is blocked on the others.
- changed: the mass operations of the addresses skip the records of a user which is processed already, the skipped addresses are written to the journal.
- changed: searchable dropdowns take the whole table cell, a long option (a device model) is cut with an ellipsis instead of wrapping to several lines.
- changed: the transliteration table of the web interface repeats eyelib::main::translit, so the web and sync_zabbix.pl give the same host names.
- changed: sync_zabbix.pl is documented as the reserve way, the web interface exports a record to zabbix while the record is saved.

# Changelog 3-2-2 - release
- changed: zabbix_macros_list is the global list of the zabbix macros now, the macro name is unique and the device_model_id field is removed.
- changed: zabbix_macros binds a macro of the global list to a device model (macros_list_id + device_model_id), the duplicated macro_name field is removed, an empty macro_value means the default value of the list.
- added: zabbixmacros.php page in the settings - the global list of the zabbix macros, the macro names can be picked from the templates of zabbix or typed by hand, the macros of the templates can be loaded into the list by a button.
- added: the device model form shows the macros used by the model, they are taken from the global list by a dropdown with a search, an individual value of the model overrides the default one.
- changed: the template of the device model zabbix form is added by a dropdown with a search instead of the list box.
- added: 3-2-2 update patches for mysql and postgres, the duplicated macros of the models are merged into single records of the list.

# Changelog 3-2-1 - release
- added: support for zabbix as a monitoring system. The eye config options 74 (monitoring system), 75 (zabbix api url), 76 (zabbix api key) are used to configure it.
- added: zabbix_templates, zabbix_macros_list, zabbix_macros tables - zabbix templates and macros for the device models.
- added: zabbix_group_id field in the ou table - host group of the organizational unit, the hosts of the unit are created in it.
- added: new form "Templates and macros zabbix" in the device models. Templates are checked for presence and renaming in zabbix, used macros are stored with their values.
- changed: nagios => monitoring, nagios_status => monitoring_status in the devices, device_ports, user_auth tables.
- added: monitoring_old field - the previous value of the monitoring field. It allows the sync script to disable a host in the monitoring system when monitoring is switched off in eye.
- added: docs/zabbix/sync_zabbix.pl script (analog of docs/nagios/gen_nagios_config.pl) and the eyelib::zabbix api library. link_check parameter is ignored by the script, hosts are never deleted from zabbix - they are disabled instead.
- bugfix: postgres data.sql used EXCLUDED."order" instead of EXCLUDED.rule_order while importing group_filters, the import of the group filters was interrupted.

# Changelog 3-1-5 - release
- Due to the uncertain future of the old SystemV initialization system, ipset support has been reconfigured to run as a regular bash script.
- bugfix: Only administrators are allowed to view project users.

# Changelog 3-1-4 - release
- changed: A record creation time stamp field has been added to the connections.
- changed: Added the function of current statistics on ip addresses for subnets - get=subnet_stats, additionally you can ask for a specific subnet subnet=192.168.0.0/24
- changed: Added a template for zabbix that works with these statistics.
- changed: nagios-related code has been moved from the root of the project to docs
- bugfix: fixed the handling of dns record changes
- bugfix: fixed the update of the last timestamp for connections
- changed: added: When updating, the configuration mode flag is now used.
- bugfix: updated set_port_descr.pl for EYE version 3. The priority of the port names has been changed to: router, switch, gateway, wi-fi ap, server, network device

# Changelog 3-1-3 - release
- changed: When updating the backend, only new versions of files are installed, and the entire backend is not copied.
- bugfix: Removed unnecessary quotas in email notifications from the web
- bugfix: Fixed the entry of ip addresses into the ipset address group
- bugfix: restored snmp settings for netdevices
- clear empty params for nagios template
- added empty password for device
- Added login and password for all devices. Added support to nagios templates.
- bugfix: fixed the handling of uninitialized variables in dhcp-log & eye-statd

# Changelog 3-1-2
- html: fixed the display of auto-assignment rules in the general list by rule type
- bugfix: Records with other|invalid statuses are excluded from arp processing html: Optimized the search for records with the same mac in subnets
- Added verification by protocol code, not by name.
- Fixed the comparison of iptables filters. Now the order of the elements is ignored.
- removed hardcoded debug from web, small fixes in upgrade sub

# Changelog 3-1-1
- updated the ipset startup script the name of the DB server variable in the initial backend config has been changed to DBHOST fixed the update procedure - only installed components are updated, not the entire backend.

# Changelog 3.1.0 - release
- bugfix: fixed "OID not increasing"
- bugfix: fixed the installation of locks for polling equipment
- Type for some systemd services changed to simple
- The log has been rewritten to Log::Log4perl.
- Optimized the survey for snmp
- rewrited shaper block im sync_mikrotik script
- bugfix: fixed a shaper processing error for bands above 50M
- bugfix: fixed the update mode. now those files and services that are available in a working system are being updated.
- added support address-list for mikrotik provisiong
- added support ipset filters for iptables script
- added address-list for ip group filtering
- restored work with Iptables
- bugfix: fixed search ip-doubles
- added search for user list

# Changelog 3.0.5 - release
- the mac MACADDR field type has been changed to mac VARCHAR(17) for postgres (to maintain compatibility of data processing with mysql)
- The send_user_ip function has been added to the API, which allows you to create an ip record with a comment, a mac, and a hostname.
- Added empty foswiki web
- uploaded last plugin for foswiki
- added get_user_subnet for api
- bugfix: fixed editing of device models with the vendor's filter installed. 
- bugfix: restored support php 7
- bugfix: When creating a user from netflow, the dynamic group parameters were ignored.

# Changelog 3.0.4 - release
- added automatic configuration of shapers for gateway interfaces

# Changelog 3.0.3 - release

- added the api_login key as a synonym for login for authorization via the API
- bugfix: web - restored wiki link in user auth record 
- bugfix: web - restored device interfaces name in switch ports
- bugfix: web - fixed the inability to clear filter fields without logging out.
- bugfix: Modification/insertion operations via the api did not work, falling out with an error about the lack of rights
- for AltLinux changed ping access to public_caps

# Changelog 3.0.2 - release

- bugfix: when processing the mac device table, the switches were "forgotten". Fixed
- upload bash script for run update wiki *.pl
- upload plugin for FosWiki
- log login & ip from $_POST|$_GET or server headers if SESSION is empty
- API for wiki support
- changed dhcp log event levels
- bugfix: fixed create dns records
- bugfix: fixed double mac in subnet page
- added blocking of the group operations form at the start of processing
- bugfix: the subnet selection field has been fixed with the deployment of networks greater than /24
- fixed page navigation - save all arg's in url
- bugfix: fixed timestamp update in eye-statd
- temporary: for dhcp request always update client_id. see...
- bugfix: we do not update the client_id if it is empty.

# Changelog 3.0.1 - release

- Now the last_found and mac_found fields are updated for all active entries with the same mac when they are found in the device tables. Arp_found is updated only if it is present in the arp table.
- Removed +ssh-dss from ssh connect options added --changes-only for sync_mikrotik, that applied only to the routers associated with the changed users simplified the log
- added email notify by subnets
- event logging has been simplified
- bugfix: fixed utf-8 error messages
- bugfix: The update script can now be run from anywhere.
- added diagnostic messages for upgrade
- bugfix: fixed an old logging bug - printing a DB hash instead of a message in some cases
- bugfix: fixed work netflow collector
- rewrite web for PDO support
- The installation script added. Its support different types of installation - web only, backend only, full
- bugfix: fixed clearing of lease records when changing dnsmasq configuration.
- html: added support for php 8.4
- PostgreSQL support added

# Changelog 2.9.1 - release

- Added PostgreSQL support (EXPERIMENTAL — DO NOT USE IN PRODUCTION!)
- html: switched to use PDO
- added perl-Net-SNMP module patch to support sha512 in AltLinux
- The installer has been added 
- Old versions of the database dump have been removed 
- The installation now uses php-fpm by default. 
- Revised English translation 
- Redesigned the import of vendor mac address data
- bugfix: fixed clearing of lease records when changing dnsmasq configuration html: added support for php 8.4
- The name of the dynamic address elimination field is now written in red font for temporary recording
- Added a check on the database connection activity in the backend, since the automatic reconnect is no longer in use.
- The debug log is not written to the database.
- The CNAME output method in dnsmasq has been changed. Now the alias is given as an A record, since dnsmasq does not resolve the parent record, and the secondary one does not send the IP address to the CNAME.
- improved the function of checking the availability of the host in the web interface
- double encoding of the redirect url has been removed
- bugfix: when the subnet was changed, the default gateway was not recalculated. bugfix: the multiplication of the redirect address with an invalid login has been removed.
- bugfix: removed the artifacts of working with snmp for mikrotik candles updated to the current version of oxidized 0.34.3 pack for a list with support for custom ssh|telnet ports
- bugfix: duplication of styles has been removed
- bugfix: Fixed the handling of possible looping in the switch connection scheme
- bugfix: fixed sorting the list of devices by name and location
- bugfix: fixed an ancient bug with permanent deletion of the ip address record from the backend
- In the search string, * (asterisk) can work anywhere except at the end of the string.
- bugfix: authorization has been fixed. Authorization is no longer reset when opening a direct link. 
- bugfix: The address import script now handles dns name changes correctly.

# Changelog 2.8.2 - release

- html: The default interval is 1 hour for displaying logs and reports.
- html: The search in the list of all addresses now takes into account the filters set
- html: The IP address can be written using the russain letter Ю instead of a dot.
- bugfix: devices with control disabled were configured anyway.
- bugfix: for router-type devices, the flag for processing user policies and shapers has been removed.
- api: The get=user function has been added to the api to get user information.
- utils: added a script for configuring openvpn ccd files based on data from user addresses

# Changelog 2.8.1 - release

- html: set autofocus for login page
- bugfix: Fixed the processing of non-tcp|udp traffic in the netflow collector
- always remove dynamic user ip record by dhcp release request

# Changelog 2.8.0 - draft

- fixed upgrade script name
- The rule of the only mac in the subnet has been implemented for dynamic records.
- The ability to set a group has been added to mass editing from the address list.
- enhanced security for the redirected page
- connections for remote/old addresses are hidden
- a redirect was made to the requested page after authorization
- Hotspot exceptions will not be added for an inactive service.
- Added an exception from the hotspot for entries with dhcp-acl = hotspot-free
- ignoring packets from an unknown router has been added to the netflow collector.
- The api has added the function of returning dhcp records only for a specific subnet: dhcp_subnet&subnet='192.168.1.0' logging of packets from unknown sources has been added to the netflow collector
- expanded the log of some events in the netflow collector
- api: added the get_dhcp_all function, which returns all entries for the configuration of the dhcp server. 
- html: added fractional number support for the lifetime of dynamic records
- log level fixed for some event
- using the ENUM field was a mistake.
- The database schema has been updated to the latest version the indexes of the log tables have been redesigned, which significantly accelerated their operation Log tables with text fields have been compressed the processing of dhcp events received via the api has been moved through separate tables. there is no need to call the command via sudo anymore
- the notification was removed in the mail/log for some types of operations.
- bugfix: fixed logged dhcp event with option82 bugfix: fixed dns update request from dhcp
- rewrited upgrade script
- added support for "foreign" domains. If the host name ends with a dot, then this entry will not be added to the office DNS.
- removed the option to put an empty username

# Changelog 2.7.9

- bugfix: fixed the garbage collection script that was broken by the previous patch
- bugfix: During garbage collection, the user's "non-deleted" records were also deleted.
- added a selection list for setting the dhcp parameters
- added display of dhcp options to the user's page
- added support dhcp-group (for dhcp option set as mikrotik) to dnsmasq
- added truncate dhcp.log after restart dnsmasq service
- added support ptr-dns records

# Changelog 2.7.8

- the work with mass modification of records has been simplified.
- added comment for user record created by ip-record
- the function of assigning a new address to a user/group has been redesigned. Now, no other rules apply to addresses from the hotspot range. Any match stops processing the remaining rules.
- when creating a mac/ip address binding to a login, the notification about an existing record for the same login was removed.
- added login search to the list of all addresses
- removed warning for new ip by dhcp event
- added the ability to delete an entry in a dynamic group via dhcp release event
- added: dhcp-option-set for mikrotik dhcp server 
- the authorization procedure has been rewritten
- fixed clearing the work log from debugging entries
- added: Auto-select of old recordings has been added to the list of duplicates.
- bugfix: Fixed group operation on users 
- bugfix: optimized processing of dhcp events
- bugfix: Fixed auto-cleaning of rules for linking to hotspot and dhcp groups 
- bugfix: Fixed programming of the dhcp server on mikrotik for some configuration options 
- bugfix: Fixed deleting entries from the list of duplicates 

# Changelog 2.7.7

- added comment for user auto rules (6f111ca)
- we update the device's management address when the associated authorization record changes. (92e9f4e)
- added sort device list by ip as ip-address, not string (e44f605)
- fixed sfp snmp status for port without snmp (41c74cc)
- bugfix: Fixed the oldest bug for date filters with a start date setting added: Added subnet selection for the dhcp server log (915a7b7)
- changed sort timestamp to arp record (4b59701)
- fixed change dns_name field (060ff5a)
- fixed deleting a user record based on a dhcp event (f5a736c)
- fixed delete_record function (a4bb764)
- added setting of the 'permanent' flag from the list of users (ed6c353)
- upload last mysql schema (a3eeca6)
- added support for permanent user record rewrited sub for remove user and user auth record (cc51fac)
- changed log format for sync mikrotik devices (794b5e1)
- bugfix: fixed edit group rules (3388ae4)
- fixed show transceiver status by snmp for SNR switches (519a405)
- added personal filtering instances for each gateway (f4efb1b)
- filter groups are linked to filtering instances, which allows you to filter traffic between different interfaces of the same router. Example: (01c0972)
- added the sysctl config to allow icmp ping to an unprivileged user (cdc222b)
- hide dhcp timestamp if action is empty (96f841a)
- added install libcrypt-rijndael-perl to manual (dc0ec8c)
- optimized snmp collector (abe0374)
- changed log level warning messages from collector to debug removed No state for switchport status page (d63e210)
- optimized: prepare router traffic detailization data only if traffic retention is enabled globally (61e23a0)
- bugfix: fixed the immediate update of the dhcp server configuration. bugfix: fixed name sessionsclean-fpm -> sessionclean-fpm bugfix: fixed readme for usage php-fpm bugfix: fixed perl scripts for hide utf-8 warnings changed: all Eye subsystem perl daemons run from user eye (f868cce)
- added search form for auto rules page (cae0ec6)
- the source for creating the address record is displayed in a separate field. (ec35789)
- optimization of tables during garbage collection is disabled (079e19f)
- outdated version updates have been removed (1edde3c)
- added separate timestamp field for arp event (8309d42)
- added script for generate freeradius huntgroups restored support free network for traffic collector (3c9ad91)
- web: added several interface improvements nagios: fixed generation of configs for passive devices during snmp polling (1084df5)
- fixed snmp-uptime nagios plugins (003c254)
- fixed parameter transmission to snmp plugins nagios (4b05511)
- added support snmpv3 for nagios subsystem (f7d70e4)
- fixed create new user by discovery (7e5326f)
- web: added EOF time for user form (2410da2)
- added temporary user records in OU (42e4712)
- added support dhcp relay for mikrotik dhcp server (a52e65b)
- fixed write snmp (73c0b65)
- fixed port control by snmp (b47d870)
- set minmal length for snmp v3 password to 8 (df41423)
- added snmpv3 to web (f65466d)
- added support snmpv3 to perl backend (cfb1d63)

# Changelog 2.7.0

- log entry of Martian packets (not belonging to office networks, but passing through the router) (078c2ad)
- bugfix: fixed lifetime of collector state variables (eb795c8)
- bugfix: fixed editing of user bindings to mac or address (3cc7b44)
- optimized netflow collector (18d43da)
- bugfix: fixed old bug for parallel calc user quotes (7f181ee)
- the scroll bar is hidden in the block diagram (bb680c9)
- Added the display of the switch model to the block diagram (ee9266c)
- the directory for the pid files is set to /run The netflow collector is launched from the eye user (659e7a8)
- fixed remote control handling for router synchronization (54d04f9)
- added config for use php-fpm rewrite provisiong module for switch access bugfix: fixed path for pid file (ba8c7e4)
- bugfix: fixed call time function (cac1ec5)
- web: added WAN statistics page (e762590)
- fixed statistics for new record, created from netflow (339107e)
- fixed WAN input counters (2e6a1d5)
- bugfix: fixed datetime field type and field names in sql request (9cd4443)
- the fixes of the previous commit have been restored (e338fda)
- added WAN statistics in collector (ad57c02)
- web: added definition of the ip protocol name by code (b9869b0)
- web: add search by ip in detail log (502592d)
- bugfix: fixed header in netflow dump (2388fd4)
- the netflow is saved in the router's subdirectory (50e6e7e)
- fixed update DNS by DHCP for domain's computer cosmetic changes (4142b2d)
- netflow is saved to files in multiples of 10 minutes (5620f7b)
- web: added save netflow field for device (e8d73f5)
- added save netflow as csv added verification of the existence of a computer in the AD domain when updating DNS by DHCP request (8fdfc31)
- bugfix: Fixed the calculation of hourly statistics for several gateways (550bb05)
- web: bugfix - fixed value for select field (a0d8d31)
- mail notifications for changing user records are enabled again (4480320)
- web: removed class.simple.mail upgrade: fixed upgrade script for 2.7.0 (b49c302)
- bugfix: Fixed the calculation of hourly statistics for several gateways added: A collector has been written for netflow (v5|v9). Now there is no need for an external collector. (59e2a9d)
- set script path for nagios module (3aa10fc)
- set nagios device name by dns name for managment ip record (5b28e8f)
- bugfix: fixed mass change device models (e0e046c)
- we always update the vlan membership for the port when snmp is available (a898d04)
- The address and port comments are combined in the output of the switch ports (73160f2)

# Changelog 2.6.3

- fixed display poe status for sfp port at poe device (1265710)
- set poe fields variables as array (4b956f9)
- set default false for poe fields device models (a067d35)
- allow change predefined models options (d02a00b)
- added poe field to device models (e846394)
- fixes in snmp walk for poe oid (d18122f)
- bugfix: fixed switch status page if device don't support POE (c129a26)
- changed display information for restarted devices (76b0a8b)
- added dhcp script for ROS7 in Readme release 2.6.2 (15a70c9)
- added dhcp script for ROS7 in Readme (a3034e7)
- If the ip address comment is allowed, fill it out from the user's record (d72d829)
- removed warning for empty api login sesion (fe9217a)
- removed warning for empty login session (a38634c)
- fixed display customer comment (588227f)
