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
