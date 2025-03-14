package eyelib::config;

#
# Copyright (C) Roman Dmitiriev, rnd@rajven.ru
#

use utf8;
use open ":encoding(utf8)";
use strict;
use English;
use FindBin '$Bin';
use lib "/opt/Eye/scripts";
use base 'Exporter';
use vars qw(@EXPORT @ISA);
use Config::Tiny;
use File::Basename;
use Data::Dumper;

@ISA = qw(Exporter);
@EXPORT = qw(
$HOME_DIR
@FN
$MY_NAME
$SPID
$LOG_DIR
$LOG_COMMON
$LOG
$LOG_ERR
$LOG_DEBUG
$DHCPD_CONF
$BEGIN_STR
$END_STR
$WARN_MSG
$WAIT_TIME
$MIN_SLEEP
$MAX_SLEEP
$admin_email
$sender_email
$send_email
$HOSTNAME
$debug
$log_enable
$log_level
$W_INFO
$W_ERROR
$W_DEBUG
$DBHOST
$DBNAME
$DBUSER
$DBPASS
$domain_auth
$winexe
$fping
$log_owner_user
$log_owner_group
$use_smsd
$smsaero_wait
$smsd_group
$smsd_user
$def_timeout
$parallel_process_count
$save_detail
$add_unknown_user
$router_ip
$dns_server
$dhcp_server
$snmp_default_version
$snmp_default_community
$KB
$office_networks
$hotspot_networks
$all_networks
@office_network_list
@hotspot_network_list
@all_network_list
$dhcp_pool
$history
$history_dhcp
$router_login
$router_password
$router_port
$org_name
$domain_name
$connections_history
$dbh
$urgent_sync
$default_user_ou_id
$default_hotspot_ou_id
$ignore_hotspot_dhcp_log
$ignore_update_dhcp_event
$update_hostname_from_dhcp
@subnets
%subnets_ref
$history_log_day
$history_syslog_day
$history_trafstat_day
$free_networks
$vpn_networks
@free_network_list
@vpn_network_list
%config_ref
%switch_auth
$last_refresh_config
$tftp_dir
$tftp_server
$cpu_count
);

BEGIN
{

our $HOME_DIR = '/opt/Eye/scripts';
my $config_file = $HOME_DIR."/cfg/config";

if (! -e "$config_file") { die "Config $config_file not found!"; }

my $Config = Config::Tiny->new;
$Config = Config::Tiny->read($config_file, 'utf8' );

our %config_ref;

### current script pathname
our @FN=split("/",$0);
### script pid file name

$config_ref{my_name}=$FN[-1];

$config_ref{pid_dir} ='/run';

#for run as root - use /run dir for pid files
if ($> > 0) {
    $config_ref{pid_dir}=$HOME_DIR.'/run';
    }

$config_ref{pid_file}       = $config_ref{pid_dir}."/".$FN[-1];
$config_ref{log_dir}        = $Config->{_}->{log_dir} || $HOME_DIR.'/log';
$config_ref{log_common}     = $config_ref{log_dir}."/$FN[-1].log";
$config_ref{dhcpd_conf}     = $Config->{_}->{dhcpd_conf} || "/etc/dnsmasq.d";
$config_ref{DBTYPE}	    = $Config->{_}->{DBTYPE} || 'mysql';
$config_ref{DBHOST}	    = $Config->{_}->{DBSERVER} || '127.0.0.1';
$config_ref{DBNAME}	    = $Config->{_}->{DBNAME} || "stat";
$config_ref{DBUSER}	    = $Config->{_}->{DBUSER} || "rstat";
$config_ref{DBPASS}	    = $Config->{_}->{DBPASS} || "rstat";
$config_ref{domain_auth}    = $Config->{_}->{domain_auth} || 'Administrator%password';
$config_ref{winexe}	    = $Config->{_}->{winexe} || '/usr/bin/winexe';
$config_ref{fping}	    = $Config->{_}->{fping} || '/sbin/fping';
$config_ref{log_owner_user} = $Config->{_}->{user} || 'eye';
$config_ref{log_owner_group}= $Config->{_}->{group} || 'eye';

$config_ref{nagios_dir}=$Config->{_}->{nagios_dir} || '/etc/nagios4';
$config_ref{nagios_dir}=~s/\/$//;
$config_ref{nagios_cmd}=$Config->{_}->{nagios_cmd} || '/var/spool/nagios/cmd/nagios.cmd';
$config_ref{nagios_event_socket}=$Config->{_}->{nagios_event_socket} || '/var/spool/nagios/hoststate.socket';

$config_ref{encryption_key}=$Config->{_}->{encryption_key} || '!!!CHANGE_ME!!!';
$config_ref{encryption_iv}=$Config->{_}->{encryption_iv} || '123456782345';

our $MY_NAME=$FN[-1];
our $SPID=$config_ref{pid_file};

#iptables log
our $LOG_DIR            = $config_ref{log_dir};
our $LOG_COMMON         = "$LOG_DIR/$FN[-1].log";

our $LOG                = $LOG_COMMON;
our $LOG_ERR            = $LOG_COMMON;
our $LOG_DEBUG          = $LOG_COMMON;

our $DHCPD_CONF         = $Config->{_}->{dhcpd_conf} || "/etc/dnsmasq.d";

our $BEGIN_STR          ="================= Start transaction ========================";
our $END_STR            ="================= Stop  transaction ========================";
our $WARN_MSG           ="#     DYNAMIC GENERATED FILE\n#     DO NOT EDIT THIS FILE BY HAND -- YOUR CHANGES WILL BE OVERWRITTEN\n";

### timeout for wait remove lock before exit
our $WAIT_TIME          =600;
our $MIN_SLEEP          =5;
our $MAX_SLEEP          =30;

### mail options
our $admin_email;
our $sender_email;
our $send_email = 0;

my $HOSTNAME1=`hostname`;
chomp($HOSTNAME1);
our $HOSTNAME=$HOSTNAME1;

### debug
our $debug=0;

our $log_enable = 1;

our $log_level = 2;

our $W_INFO = 0;
our $W_ERROR = 1;
our $W_DEBUG = 2;

our $DBHOST 		= $config_ref{DBHOST};
our $DBNAME 		= $config_ref{DBNAME};
our $DBUSER 		= $config_ref{DBUSER};
our $DBPASS 		= $config_ref{DBPASS};

our $domain_auth	= $config_ref{domain_auth};
our $winexe		= $config_ref{winexe};
our $fping		= $config_ref{fping};

our @subnets=();

our $history_log_day;
our $history_syslog_day;
our $history_trafstat_day;

our $log_owner_user	= $config_ref{log_owner_user};
our $log_owner_group	= $config_ref{log_owner_group};

################################################################

our $def_timeout = 90;
our $parallel_process_count = 10;

our $cpu_count = 1;

################## DB options ##################################

our $save_detail;
our $add_unknown_user;
our $router_ip;
our $dns_server;
our $dhcp_server;
our $snmp_default_version;
our $snmp_default_community;
our $KB;
our $office_networks;
our $hotspot_networks;
our $all_networks;
our @office_network_list;
our @hotspot_network_list;
our @all_network_list;
our $free_networks;
our $vpn_networks;
our @free_network_list;
our @vpn_network_list;
our $dhcp_pool;
our $default_user_ou_id;
our $default_hotspot_ou_id;
our $history;
our $history_dhcp;
our $router_login;
our $router_password;
our $router_port;
our $org_name;
our $domain_name;
our $connections_history;
our $dbh;
our $urgent_sync = 0;
our $tftp_dir=$Config->{_}->{tftp_dir} || '/var/lib/tftpboot';
our $tftp_server=$Config->{_}->{tftp_server} || '';

our $last_refresh_config = time();

our %switch_auth = (
'8'=>{'vendor'=>'Allied Telesis','enable'=>'en','proto'=>'essh','port'=>'22','login'=> '(login|User Name):','password'=>'Password:','prompt'=>qr/(\010\013){0,5}(([-\w]+|[-\w(config)+])\#|[-\w]+\>)/},
'3'=>{'vendor'=>'Huawei','proto'=>'essh','port'=>'22','enable'=>'system-view','login'=> 'login as:','password'=>'Password: ','prompt'=>qr/(\<.*\>|\[.*\])/},
'16'=>{'vendor'=>'Cisco','proto'=>'ssh','port'=>'22','enable'=>'en','login'=> 'Username:','password'=>'Password:','prompt'=>qr/([-\w]+|[-\w(config)+])\#/},
'5'=>{'vendor'=>'Raisecom','proto'=>'telnet','port'=>'23','enable'=>'en','login'=> 'Login:','password'=>'Password:','prompt'=>qr/([-\w]+|[-\w(config)+])\#/},
'6'=>{'vendor'=>'SNR','proto'=>'telnet','port'=>'23','login'=> 'login:','password'=>'Password:','prompt'=>qr/([-\w]+|[-\w(config)+])\#/},
'7'=>{'vendor'=>'Dlink','proto'=>'telnet','port'=>'23','login'=> 'UserName:','password'=>'PassWord:','prompt'=>qr/[-\w]+\#$/},
#'15'=>{'vendor'=>'HP','proto'=>'telnet','port'=>'23','enable'=>'system-view','login'=> 'login:','password'=>'Password:','prompt'=>qr/([-\w]+|[-\w(config)+])\#/},
'2'=>{'vendor'=>'Eltex','proto'=>'telnet','port'=>'23','login'=> 'User Name:','password'=>'Password:','prompt'=>qr/([-\w]+|[-\w(config)+])\#/},
'17'=>{'vendor'=>'Maipu','proto'=>'telnet','port'=>'23','login'=> 'login:','password'=>'password:','prompt'=>qr/([-\w]+|[-\w(config)+])\#/},
'4'=>{'vendor'=>'Zyxel','proto'=>'telnet','port'=>'23','login'=> 'User name:','password'=>'Password:','prompt'=>qr/([-\w]+|[-\w(config)+]|[-\w(config-interface)+])\#/},
'38'=>{'vendor'=>'Qtech','proto'=>'telnet','port'=>'23','enable'=>'en','login'=> 'login:','password'=>'Password:','prompt'=>qr/([-\w]+|[-\w(config)+])\#/},
'9'=>{'vendor'=>'Mikrotik','proto'=>'ssh','port'=>'22','login'=> 'login as:','password'=>'password:','prompt'=>qr/\[[-\w]+\@[-\w]+\]\s+\>/},
'39'=>{'vendor'=>'Extreme','proto'=>'telnet','port'=>'23','login'=> 'login:','password'=>'password:','prompt'=>qr/[-\w]+\s\#\s/},
);

mkdir $LOG_DIR unless (-d $LOG_DIR);
mkdir $config_ref{pid_dir} unless (-d $config_ref{pid_dir});

my @cpu_list = `grep ^processor /proc/cpuinfo`;
$cpu_count = scalar @cpu_list;

1;
}
