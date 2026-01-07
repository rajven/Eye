#!/usr/bin/perl -w

use utf8;
use open ":encoding(utf8)";
use Encode;
no warnings 'utf8';
use English;
use base;
use FindBin '$Bin';
use lib "/opt/Eye/scripts";
use strict;
use DBI;
use Time::Local;
use Net::Patricia;
use Data::Dumper;
use Date::Parse;
use DateTime;
use eyelib::config;
use eyelib::main;
use eyelib::net_utils;
use eyelib::database;
use eyelib::common;
use eyelib::snmp;
use Socket qw(AF_INET6 inet_ntop);
use IO::Socket;

my $proc_name = $MY_NAME;
$proc_name =~ s/\.[^.]+$//;

my $pid_file = '/run/eye/'.$proc_name;

my @router_ref = ();
my @interfaces = ();

my %proto_map = (
    1  => 'ICMP',    2  => 'IGMP',    6  => 'TCP',
    17 => 'UDP',     47 => 'GRE',     50 => 'ESP',
    51 => 'AH',      89 => 'OSPF',    88 => 'EIGRP'
);

my %mute;

my %routers_svi;
my %routers_by_ip;
my %routers;

my %wan_dev;
my %lan_dev;

my @traffic = ();
my $saving = 0;

#user statistics for cached data
my %user_stats;
my %wan_stats;

my $MAXREAD = 9216;

my $timeshift = get_option($dbh,55)*60;
my $save_path =  get_option($dbh,72);

my $thread_count = $cpu_count;

#save traffic to DB
my $traf_lastflush = time();

# NetFlow
my $server_port = 2055;
my $netflow5_header_len = 24;
my $netflow5_flowrec_len = 48;
my $netflow9_header_len = 20;
my $netflow9_templates = {};

# reap dead children
$SIG{CHLD} = \&REAPER;
$SIG{TERM} = \&TERM;
$SIG{INT} = \&TERM;
$SIG{HUP} = \&INIT;


if (IsNotRun($pid_file)) {
    Add_PID($pid_file);
    } else {
    print "Daemon $MY_NAME already running!\n";
    exit 100;
    }

sub REAPER {
	wait;
	$saving = 0;
	$SIG{CHLD} = \&REAPER;
}

sub TERM {
	print "SIGTERM received\n";
	flush_traffic(1);
	while (wait() != -1) {}
        if (IsMyPID($pid_file)) { Remove_PID($pid_file); }
	exit 0;
}

sub INIT {

# Create new database handle. If we can't connect, die()
my $hdb = init_db();

InitSubnets();

init_option($hdb);

#a directory for storing traffic details in text form
$save_path = get_option($dbh,72);

#the period for resetting statistics from netflow to billing
$timeshift = get_option($hdb,55)*60;

@router_ref = get_records_sql($hdb,"SELECT * FROM devices WHERE deleted=0 AND device_type=2 AND snmp_version>0 ORDER by ip" );
@interfaces = get_records_sql($hdb,"SELECT * FROM device_l3_interfaces ORDER by device_id" );

#router device_id by known device ip
foreach my $row (@router_ref) {
    setCommunity($row);
    $routers{$row->{id}}=$row;
    my $l3_list = getIpAdEntIfIndex($row->{ip},$row->{snmp});

    #create hash for interface snmp index => ip-address at interface =1;
    foreach my $router_ip (keys %$l3_list) { $routers_svi{$row->{id}}{$l3_list->{$router_ip}}{$router_ip}=1; }

    #create hash by all ip-addresses for router
    foreach my $router_ip (keys %$l3_list) {
        $routers_by_ip{$router_ip}->{id}=$row->{id};
        if ($config_ref{save_detail}) { 
            $routers_by_ip{$router_ip}->{save}=$row->{netflow_save};
            } else { $routers_by_ip{$router_ip}->{save}=0; }
        }
    }

#snmp index for WAN/LAN interface by device id
foreach my $row (@interfaces) {
    if ($row->{interface_type}) { $wan_dev{$row->{device_id}}{$row->{snmpin}}=1; } else { $lan_dev{$row->{device_id}}{$row->{snmpin}}=1; }
    }

#get userid list
my @auth_list_ref = get_records_sql($hdb,"SELECT id,ip,save_traf FROM user_auth where deleted=0 ORDER by id");

foreach my $row (@auth_list_ref) {
    $user_stats{$row->{ip}}{auth_id}=$row->{id};
    if ($config_ref{save_detail}) {
        $user_stats{$row->{ip}}{save_traf}=$row->{save_traf};
        } else {
        $user_stats{$row->{ip}}{save_traf}=0;
        }
    }
$hdb->disconnect();
}

############### MAIN ##########################

#close default database
$dbh->disconnect();

INIT();

my $lsn_nflow;
my $sel = IO::Select->new();

# prepare to listen for NetFlow UDP packets
if ($server_port > 0) {
	$lsn_nflow = IO::Socket::INET->new(LocalPort => $server_port, Proto => "udp")
		or die "Couldn't be a NetFlow UDP server on port $server_port : $@\n";
	$sel->add($lsn_nflow);
}

my ($him,$datagram,$flags);

# main datagram receive loop
while (1) {
	while (my @ready = $sel->can_read) {
		foreach my $server (@ready) {
			$him = $server->recv($datagram, $MAXREAD);
			next if (!$him);
			
			my ($port, $ipaddr) = sockaddr_in($server->peername);
			
			if (defined($lsn_nflow) && $server == $lsn_nflow) {
				my ($version) = unpack("n", $datagram);
                                if ($version == 5) {
                                        parse_netflow_v5($datagram, $ipaddr);
                                } elsif ($version == 9) {
                                        parse_netflow_v9($datagram, $ipaddr);
                                } else {
                                        print "unknown NetFlow version: $version\n";
                                }
			}
		}
	}
}

sub parse_netflow_v5 {
        my $datagram = shift;
        my $ipaddr = shift;

        my ($version, $count, $sysuptime, $unix_secs, $unix_nsecs,
          $flow_sequence, $engine_type, $engine_id, $aggregation,
          $agg_version) = unpack("nnNNNNCCCC", $datagram);

        my $flowrecs = substr($datagram, $netflow5_header_len);

#0 - N 0-3	srcaddr	Source IP address
#1 - N 4-7	dstaddr	Destination IP address
#2 - N 8-11	nexthop	IP address of next hop router
#3 - n 12-13	input	SNMP index of input interface
#4 - n 14-15	output	SNMP index of output interface
#5 - N 16-19	dPkts	Packets in the flow
#6 - N 20-23	dOctets	Total number of Layer 3 bytes in the packets of the flow
#7 - N 24-27	First	SysUptime at start of flow
#8 - N 28-31	Last	SysUptime at the time the last packet of the flow was received
#9 - n 32-33	src_port	TCP/UDP source port number or equivalent
#10- n 34-35	dst_port	TCP/UDP destination port number or equivalent
#11- C 36	pad1	Unused (zero) byte
#12- C 37	tcp_flags	Cumulative OR of TCP flags
#13- C 38	prot	IP protocol type (for example, TCP = 6; UDP = 17)
#14- C 39	tos	IP type of service (ToS)
#15- n 40-41	src_as	Autonomous system number of the source, either origin or peer
#16- n 42-43	dst_as	Autonomous system number of the destination, either origin or peer
#17- C 44	src_mask	Source address prefix mask bits
#18- C 45	dst_mask	Destination address prefix mask bits
#19- n 46-47	pad2	Unused (zero) bytes

        for (my $i = 0; $i < $count; $i++) {
                my $flowrec = substr($datagram, $netflow5_header_len + ($i*$netflow5_flowrec_len), $netflow5_flowrec_len);
                my @flowdata = unpack("NNNnnNNNNnnCCCCnnCCn", $flowrec);
		my %flow;
                $flow{src_ip} = join '.', unpack 'C4', pack 'N', $flowdata[0];
                $flow{dst_ip} = join '.', unpack 'C4', pack 'N', $flowdata[1];
		$flow{snmp_in} = $flowdata[3] || 0;
		$flow{snmp_out} = $flowdata[4] || 0;
		$flow{pkts} = $flowdata[5] || 0;
		$flow{octets} = $flowdata[6] || 0;
		$flow{src_port} = $flowdata[9] || 0;
		$flow{dst_port} = $flowdata[10] || 0;
		$flow{proto} = $flowdata[13] || 0;
		$flow{xsrc_ip} = $flow{src_ip};
		$flow{xdst_ip} = $flow{dst_ip};
		$flow{starttime} = time();
		$flow{netflow_v} = '5';
		$flow{ipv} = '4';
		save_flow($ipaddr, \%flow);
        }
}

sub parse_netflow_v9 {
	my $datagram = shift;
	my $ipaddr = shift;
	
	# Parse packet
	my ($version, $count, $sysuptime, $unix_secs, $seqno, $source_id, @flowsets) = unpack("nnNNNN(nnX4/a)*", $datagram);
	
	# Loop through FlowSets and take appropriate action
	for (my $i = 0; $i < scalar @flowsets; $i += 2) {
		my $flowsetid = $flowsets[$i];
		my $flowsetdata = substr($flowsets[$i+1], 4);	# chop off id/length
		if ($flowsetid == 0) {
			# 0 = Template FlowSet
			parse_netflow_v9_template_flowset($flowsetdata, $ipaddr, $source_id);
		} elsif ($flowsetid == 1) {
			# 1 - Options Template FlowSet
		} elsif ($flowsetid > 255) {
			# > 255: Data FlowSet
			parse_netflow_v9_data_flowset($flowsetid, $flowsetdata, $ipaddr, $source_id);
		} else {
			# reserved FlowSet
			print "Unknown FlowSet ID $flowsetid found\n";
		}
	}
}

sub parse_netflow_v9_template_flowset {
	my $templatedata = shift;
	my $ipaddr = shift;
	my $source_id = shift;
	
	# Note: there may be multiple templates in a Template FlowSet
	
	my @template_ints = unpack("n*", $templatedata);

	my $i = 0;
	while ($i < scalar @template_ints) {
		my $template_id = $template_ints[$i];
		my $fldcount = $template_ints[$i+1];

		last if (!defined($template_id) || !defined($fldcount));

#		print "Updated template ID $template_id (source ID $source_id, from " . inet_ntoa($ipaddr) . ")\n" if ($debug);
		my $template = [@template_ints[($i+2) .. ($i+2+$fldcount*2-1)]];
		$netflow9_templates->{$ipaddr}->{$source_id}->{$template_id}->{'template'} = $template;
		
		# total length of template data
		my $totallen = 0;
		for (my $j = 1; $j < scalar @$template; $j += 2) {
			$totallen += $template->[$j];
		}

		$netflow9_templates->{$ipaddr}->{$source_id}->{$template_id}->{'len'} = $totallen;

		$i += (2 + $fldcount*2);
	}
}

sub parse_netflow_v9_data_flowset {
    my ($flowsetid, $flowsetdata, $ipaddr, $source_id) = @_;

    my $template = $netflow9_templates->{$ipaddr}->{$source_id}->{$flowsetid}->{'template'};
    unless (defined $template) {
        return;
    }

    my $len = $netflow9_templates->{$ipaddr}->{$source_id}->{$flowsetid}->{'len'};
    my $offset = 0;
    my $datalen = length($flowsetdata);

    while (($offset + $len) <= $datalen) {
        my %flow = (
            netflow_v => '9',
            starttime => time(),
            ipv      => '4',
            snmp_in  => 0,
            snmp_out => 0,
            octets   => 0,
            pkts     => 0
        );

        for (my $i = 0; $i < scalar @$template; $i += 2) {
            my $field_type = $template->[$i];
            my $field_length = $template->[$i+1];
            my $value = substr($flowsetdata, $offset, $field_length);
            $offset += $field_length;

            # IN_BYTES (1)
            if ($field_type == 1) {
                $flow{octets} = $field_length == 8 ? unpack("Q>", $value) : unpack("N", $value);
            }
            # IN_PACKETS (2)
            elsif ($field_type == 2) {
                $flow{pkts} = $field_length == 8 ? unpack("Q>", $value) : unpack("N", $value);
            }
            # IN_PROTOCOL (4)
            elsif ($field_type == 4) {
                $flow{proto} = unpack("C", $value);
            }
            # L4_SRC_PORT (7)
            elsif ($field_type == 7) {
                $flow{src_port} = unpack("n", $value);
            }
            # IPV4_SRC_ADDR (8)
            elsif ($field_type == 8) {
                $flow{src_ip} = inet_ntop(AF_INET, $value);
            }
            # INPUT_SNMP (10)
            elsif ($field_type == 10) {
                $flow{snmp_in} = $field_length == 4 ? unpack("N", $value) : unpack("n", $value);
            }
            # L4_DST_PORT (11)
            elsif ($field_type == 11) {
                $flow{dst_port} = unpack("n", $value);
            }
            # IPV4_DST_ADDR (12)
            elsif ($field_type == 12) {
                $flow{dst_ip} = inet_ntop(AF_INET, $value);
            }
            # OUTPUT_SNMP (14)
            elsif ($field_type == 14) {
                $flow{snmp_out} = $field_length == 4 ? unpack("N", $value) : unpack("n", $value);
            }
            # ICMP_TYPE (32)
            elsif ($field_type == 32) {
                $flow{icmp_type} = unpack("C", $value);
            }
            # ICMP_CODE (33)
            elsif ($field_type == 33) {
                $flow{icmp_code} = unpack("C", $value);
            }
            # IP_PROTOCOL_VERSION (60)
            elsif ($field_type == 60) {
                my $ipversion = unpack("C", $value);
                if ($ipversion == 6) {
                    %flow = ();
                    last;
                }
                $flow{ipv} = $ipversion;
            }
            # XLATE_SRC_ADDR_IPV4 (225)
            elsif ($field_type == 225) {
                $flow{xsrc_ip} = inet_ntop(AF_INET, $value);
            }
            # XLATE_DST_ADDR_IPV4 (226)
            elsif ($field_type == 226) {
                $flow{xdst_ip} = inet_ntop(AF_INET, $value);
            }
        }

        # Обработка не-TCP/UDP трафика
        if ($flow{proto} == 1) {          # ICMP
            $flow{src_port} = $flow{icmp_type} || 0;
            $flow{dst_port} = $flow{icmp_code} || 0;
        }
        elsif ($flow{proto} == 2) {       # IGMP
            $flow{src_port} = $flow{igmp_type} || 0;
        }
        elsif ($flow{proto} == 47) {      # GRE
            $flow{src_port} = $flow{gre_version} || 0;
            $flow{dst_port} = $flow{gre_key} || 0;
        }
        elsif ($flow{proto} == 50 ||      # ESP
               $flow{proto} == 51) {      # AH
            $flow{src_port} = $flow{dst_port} = 0;  # Нет портов
        }
        elsif ($flow{proto} == 89) {      # OSPF
            $flow{src_port} = $flow{dst_port} = 0;
        }

        # Сохраняем только валидные потоки
        if (%flow && exists $flow{src_ip} && exists $flow{dst_ip}) {
            save_flow($ipaddr, \%flow);
        }
    }
}

sub get_proto_name {
    my ($proto_num) = @_;
    return $proto_map{$proto_num} || "Proto-$proto_num";
}

sub save_flow {
	my $router_ip = shift;
	my $flow = shift;

	$router_ip = inet_ntoa($router_ip);
	#direction for user, 0 - in, 1 - out
	$flow->{direction} = '0';
	my $router_id;
	#skip unknown router
	if (exists $routers_by_ip{$router_ip}) { 
		$router_id = $routers_by_ip{$router_ip}{id};
		$flow->{router_ip} = $router_ip;
		$flow->{device_id} = $router_id;
		$flow->{save} = $routers_by_ip{$router_ip}{save};
		} else {
                if (!exists $mute{$router_ip}) { $mute{$router_ip} = time(); }
                if (time() - $mute{$router_ip} >=3600) {
                    $mute{$router_ip} = time();
                    log_warning("Found unknown router ip [".$router_ip."] in netflow!");
                    }
                return;
                }

	#skip local traffic for router
	if (!exists $wan_dev{$router_id}->{$flow->{snmp_out}} and ! exists $wan_dev{$router_id}->{$flow->{snmp_in}}) { return; }

        #detect traffic direction
	if (exists $wan_dev{$router_id}->{$flow->{snmp_out}}) { $flow->{direction} = 1; }

	push(@traffic,$flow);
	flush_traffic(0);
}

sub flush_traffic {

my $force = shift || 0;

if (!$force && ($saving || ((time - $traf_lastflush) < $timeshift))) { return; }

$saving++;

my $pid = fork();

INIT();

#log_debug("ROUTERS-SVI:".Dumper(\%routers_svi));
#log_debug("ROUTERS by IP::".Dumper(\%routers_by_ip));
#log_debug("ROUTERS:".Dumper(\%routers));
#log_debug("WAN-DEVS:".Dumper(\%wan_dev));
#log_debug("LAN-DEVS:".Dumper(\%lan_dev));

if (!defined $pid) {
    $saving = 0;
    print "cannot fork! Save traffic and exit...\n";
    } elsif ($pid != 0) {
        # in parent
	$traf_lastflush = time();
	#clean main cache
	@traffic = ();
        return;
    }


#create oper-cache
my @flush_table = ();

push(@flush_table,@traffic);

my $hdb=init_db();

#saved packet by users
my @detail_traffic = ();
my %saved_netflow = ();

my %routers_found;

#last packet timestamp
my $last_time = time();
my $start_time;

foreach my $traf_record (@flush_table) {

#log_debug("RAW-DATA: ".hash_to_kv_csv($traf_record));

my ($auth_id,$l_src_ip,$l_dst_ip,$user_ip,$router_id);

#skip unknown router
next if (!$traf_record->{device_id});

$router_id = $traf_record->{device_id};

#prepare router traffic detailization data only if traffic retention is enabled globally
if ($config_ref{save_detail} and $traf_record->{save}) {
    push(@{$saved_netflow{$traf_record->{device_id}}},join(';',$traf_record->{starttime},$traf_record->{proto},$traf_record->{snmp_in},$traf_record->{snmp_out},$traf_record->{src_ip},$traf_record->{dst_ip},$traf_record->{xsrc_ip},$traf_record->{xdst_ip},$traf_record->{src_port},$traf_record->{dst_port},$traf_record->{octets},$traf_record->{pkts}));
    }

$routers_found{$router_id} = 1;

#save start netflow time
if (!$start_time) { $start_time = $traf_record->{starttime}; }

#--- router statistics

#input traffic and traffic originated from router
if (!$traf_record->{snmp_out} or !$traf_record->{snmp_in}) {
    #input
    if (!$traf_record->{snmp_out} and exists $routers_svi{$router_id}{$traf_record->{snmp_in}}{$traf_record->{dst_ip}}) {
#        log_debug("ROUTER id: $router_id I-DATA: ".hash_to_kv_csv($traf_record));
        #input
        if (!$free_networks->match_string($traf_record->{src_ip})) {
            if (exists $wan_stats{$router_id}{$traf_record->{snmp_in}}{in}) {
                $wan_stats{$router_id}{$traf_record->{snmp_in}}{in}+=$traf_record->{octets};
                } else {
                $wan_stats{$router_id}{$traf_record->{snmp_in}}{in}=$traf_record->{octets};
                }
            }
        next;
	}
    #output
    if (!$traf_record->{snmp_in} and exists $routers_svi{$router_id}{$traf_record->{snmp_out}}{$traf_record->{src_ip}}) {
#        log_debug("ROUTER id: $router_id O-DATA: ".hash_to_kv_csv($traf_record));
        #output
        if (!$free_networks->match_string($traf_record->{dst_ip})) {
            if (exists $wan_stats{$router_id}{$traf_record->{snmp_out}}{out}) {
                $wan_stats{$router_id}{$traf_record->{snmp_out}}{out}+=$traf_record->{octets};
                } else {
                $wan_stats{$router_id}{$traf_record->{snmp_out}}{out}=$traf_record->{octets};
                }
            }
        next;
        }
#    log_debug("ROUTER id: $router_id U-DATA: ".hash_to_kv_csv($traf_record));
    #unknown packet
    next;
    }

#simple output traffic from router
if (exists $wan_dev{$router_id}->{$traf_record->{snmp_out}} and exists $wan_dev{$router_id}->{$traf_record->{snmp_in}}) {
    if (exists $routers_svi{$router_id}{$traf_record->{snmp_out}}{$traf_record->{src_ip}}) {
#        log_debug("ROUTER id: $router_id O-SDATA: ".hash_to_kv_csv($traf_record));
        #output
        if (!$free_networks->match_string($traf_record->{dst_ip})) {
            if (exists $wan_stats{$router_id}{$traf_record->{snmp_out}}{out}) {
                $wan_stats{$router_id}{$traf_record->{snmp_out}}{out}+=$traf_record->{octets};
                } else {
                $wan_stats{$router_id}{$traf_record->{snmp_out}}{out}=$traf_record->{octets};
                }
            }
        next;
        }
    #It is unlikely that it will ever work out
    if (exists $routers_svi{$router_id}{$traf_record->{snmp_in}}{$traf_record->{dst_ip}}) {
#        log_debug("ROUTER id: $router_id I-SDATA: ".hash_to_kv_csv($traf_record));
        #input
        if (!$free_networks->match_string($traf_record->{src_ip})) {
            if (exists $wan_stats{$router_id}{$traf_record->{snmp_in}}{in}) {
                $wan_stats{$router_id}{$traf_record->{snmp_in}}{in}+=$traf_record->{octets};
                } else {
                $wan_stats{$router_id}{$traf_record->{snmp_in}}{in}=$traf_record->{octets};
                }
            }
        next;
        }
#    log_debug("ROUTER id: $router_id U-SDATA: ".hash_to_kv_csv($traf_record));
    #unknown packet
    next;
    } else {
    #forward
    if (!$free_networks->match_string($traf_record->{src_ip}) and !$free_networks->match_string($traf_record->{dst_ip})) {
        if ($traf_record->{direction}) {
#	    log_debug("ROUTER id: $router_id FO-DATA: ".hash_to_kv_csv($traf_record));
            #out
            if (exists $wan_stats{$router_id}{$traf_record->{snmp_out}}{forward_out}) {
                $wan_stats{$router_id}{$traf_record->{snmp_out}}{forward_out}+=$traf_record->{octets};
                } else {
                $wan_stats{$router_id}{$traf_record->{snmp_out}}{forward_out}+=$traf_record->{octets};
                }
            } else {
#	    log_debug("ROUTER id: $router_id FI-DATA: ".hash_to_kv_csv($traf_record));
            #in
            if (exists $wan_stats{$router_id}{$traf_record->{snmp_in}}{forward_in}) {
                $wan_stats{$router_id}{$traf_record->{snmp_in}}{forward_in}+=$traf_record->{octets};
                } else {
                $wan_stats{$router_id}{$traf_record->{snmp_in}}{forward_in}+=$traf_record->{octets};
                }
            }
        } else {
#	    log_debug("ROUTER id: $router_id FREE-DATA: ".hash_to_kv_csv($traf_record));
	}
    }

#--- user statistics

my $free = 0;

if ($traf_record->{direction}) {
    #outbound traffic
    if (exists $user_stats{$traf_record->{src_ip}}) {
	$user_ip  = $traf_record->{src_ip};
	$l_src_ip = $traf_record->{src_ip};
	$l_dst_ip = $traf_record->{dst_ip};
        $free = $free_networks->match_string($l_dst_ip);
        #skip calculate free net
        if (!$free) {
            if (exists $user_stats{$user_ip}{$router_id}{out}) {
		$user_stats{$user_ip}{$router_id}{out}+=$traf_record->{octets};
		} else {
		$user_stats{$user_ip}{$router_id}{out}=$traf_record->{octets};
		}
	    if (exists $user_stats{$user_ip}{$router_id}{pkt_out}) {
		$user_stats{$user_ip}{$router_id}{pkt_out}+=$traf_record->{pkts};
		} else {
		$user_stats{$user_ip}{$router_id}{pkt_out}=$traf_record->{pkts};
		}
            }
	}
    #a new user is created only by the presence of outgoing traffic
    if (!$user_ip and $config_ref{add_unknown_user}) {
        #skip create router interface as user
        if (exists $routers_by_ip{$traf_record->{src_ip}}) { next; }
	if (!$office_networks->match_string($traf_record->{src_ip})) {
	    log_debug("Unknown src network ".hash_to_kv_csv($traf_record));
	    next;
	    }
        $user_ip = $traf_record->{src_ip};
	$auth_id = new_auth($hdb,$user_ip);
        if (!$auth_id) { next; }
	$l_src_ip = $traf_record->{src_ip};
	$l_dst_ip = $traf_record->{dst_ip};
	$user_stats{$user_ip}{auth_id}=$auth_id;
	$user_stats{$user_ip}{$router_id}{in}=0;
	$user_stats{$user_ip}{$router_id}{pkt_in}=0;
	$user_stats{$user_ip}{$router_id}{out}=0;
	$user_stats{$user_ip}{$router_id}{pkt_out}=0;
	$user_stats{$user_ip}{save_traf}=$config_ref{save_detail};

        $free = $free_networks->match_string($l_dst_ip);
        #skip calculate free net
        if (!$free) {
        	$user_stats{$user_ip}{$router_id}{out}=$traf_record->{octets};
	        $user_stats{$user_ip}{$router_id}{pkt_out}=$traf_record->{pkts};
                }
	}
    } else {
    #inbound traffic
    if (exists $user_stats{$traf_record->{xdst_ip}}) {
	$user_ip  = $traf_record->{xdst_ip};
	$l_src_ip = $traf_record->{src_ip};
	$l_dst_ip = $traf_record->{xdst_ip};

        $free = $free_networks->match_string($l_src_ip);
        #skip calculate free net
        if (!$free) {
        	if (exists $user_stats{$user_ip}{$router_id}{in}) {
	        	$user_stats{$user_ip}{$router_id}{in}+=$traf_record->{octets};
		        } else {
        		$user_stats{$user_ip}{$router_id}{in}=$traf_record->{octets};
	        	}
        	if (exists $user_stats{$user_ip}{$router_id}{pkt_in}) {
	        	$user_stats{$user_ip}{$router_id}{pkt_in}+=$traf_record->{pkts};
		        } else {
        		$user_stats{$user_ip}{$router_id}{pkt_in}=$traf_record->{pkts};
	        	}
                }
	}
    }

if (!$user_ip) {
    log_debug("Unknown USER: ".hash_to_kv_csv($traf_record));
    next;
    }

$last_time = $traf_record->{starttime};

$user_stats{$user_ip}{last_found} = $last_time;

next if (!$config_ref{save_detail} and !$user_stats{$user_ip}{save_traf});

my $l_src_ip_aton=StrToIp($l_src_ip);
my $l_dst_ip_aton=StrToIp($l_dst_ip);

my ($sec,$min,$hour,$day,$month,$year,$zone) = (localtime($last_time))[0,1,2,3,4,5];
$month++;
$year += 1900;
my $full_time = sprintf "%04d-%02d-%02d %02d:%02d:%02d",$year,$month,$day,$hour,$min,$sec;

my @detail_array = ($user_stats{$user_ip}->{auth_id},$router_id,$full_time,$traf_record->{proto},$l_src_ip_aton,$l_dst_ip_aton,$traf_record->{src_port},$traf_record->{dst_port},$traf_record->{octets},$traf_record->{pkts});
push(@detail_traffic,\@detail_array);
}

@flush_table=();

#start hour
my ($sec,$min,$hour,$day,$month,$year) = (localtime($last_time))[0,1,2,3,4,5];

#save netflow
if ($config_ref{save_detail}) {
    $save_path=~s/\/$//;
    foreach my $dev_id (keys %saved_netflow) {
        my $netflow_file_path = $save_path.'/'.$dev_id.'/'.sprintf "%04d/%02d/%02d/%02d/",$year+1900,$month+1,$day,$hour;
        my $nmin = int($min/10)*10;
        my $netflow_file_name = $netflow_file_path.sprintf "%04d%02d%02d-%02d%02d.csv",$year+1900,$month+1,$day,$hour,$nmin;
        if ($saved_netflow{$dev_id} and scalar @{$saved_netflow{$dev_id}}) {
            use File::Path;
            File::Path::make_path($netflow_file_path);
            if ( -e $netflow_file_name) {
                open (ND,">>$netflow_file_name") || die("Error open file $netflow_file_name!!! die...");
                binmode(ND,':utf8');
                } else {
                open (ND,">$netflow_file_name") || die("Error open file $netflow_file_name!!! die...");
                binmode(ND,':utf8');
                print ND join(';',"time","proto","snmp_in","snmp_out","src_ip","dst_ip","xsrc_ip","xdst_ip","src_port","dst_port","octets","pkts")."\n";
                }
            foreach my $row (@{$saved_netflow{$dev_id}}) {
                next if (!$row);
                print ND $row."\n";
                }
            close ND;
            @{$saved_netflow{$dev_id}}=();
            }
        }
    }
undef %saved_netflow;

#save statistics

#start stat time
my $hour_date1 = sprintf "%04d-%02d-%02d %02d:00:00",$year+1900,$month+1,$day,$hour;
#end hour
($hour,$day,$month,$year) = (localtime($last_time+3600))[2,3,4,5];
my $hour_date2 = sprintf "%04d-%02d-%02d %02d:00:00",$year+1900,$month+1,$day,$hour;

my @batch_user_stats=();
my @batch_user_stats_update=();
my @batch_user_stats_full=();
my @batch_auth_status=();
my @batch_wan_stats=();

#log_debug("User STATS: ".Dumper(\%user_stats));

# update database
foreach my $user_ip (keys %user_stats) {
    next if (!exists $user_stats{$user_ip}{last_found});
    my $user_ip_aton=StrToIp($user_ip);
    my $auth_id = $user_stats{$user_ip}{auth_id};

    #last flow for user
    my ($sec,$min,$hour,$day,$month,$year) = (localtime($user_stats{$user_ip}{last_found}))[0,1,2,3,4,5];
    #flow time string
    my $flow_date = sprintf "%04d-%02d-%02d %02d:%02d:%02d",$year+1900,$month+1,$day,$hour,$min,$sec;

    #last found timestamp
    push @batch_auth_status, [ $flow_date, $flow_date, $auth_id ];

    #per router stats
    foreach my $router_id (keys %routers_found) {
	next if (!exists $user_stats{$user_ip}{$router_id});
	if (!exists $user_stats{$user_ip}{$router_id}{in})  { $user_stats{$user_ip}{$router_id}{in} = 0; }
	if (!exists $user_stats{$user_ip}{$router_id}{out}) { $user_stats{$user_ip}{$router_id}{out} = 0; }
	#skip empty stats
	if ($user_stats{$user_ip}{$router_id}{in} + $user_stats{$user_ip}{$router_id}{out} ==0) { next; }
	#packet count per router
	if (!exists $user_stats{$user_ip}{$router_id}{pkt_in})  { $user_stats{$user_ip}{$router_id}{pkt_in} = 0; }
	if (!exists $user_stats{$user_ip}{$router_id}{pkt_out}) { $user_stats{$user_ip}{$router_id}{pkt_out} = 0; }
	#current stats
        push @batch_user_stats_full, [
    	    $flow_date, 
    	    $auth_id, 
    	    $router_id, 
    	    $user_stats{$user_ip}{$router_id}{in}, 
    	    $user_stats{$user_ip}{$router_id}{out}, 
    	    $user_stats{$user_ip}{$router_id}{pkt_in}, 
    	    $user_stats{$user_ip}{$router_id}{pkt_out}, 
    	    $timeshift ];
	#hour stats
	# get current stats
	my $sql = "SELECT id, byte_in, byte_out FROM user_stats WHERE ts >= ? AND ts < ? AND router_id = ? AND auth_id = ?";
	my $hour_stat = get_record_sql($hdb, $sql, 
	    $hour_date1,
	    $hour_date2,
	    $router_id,
	    $auth_id
	    );
	if (!$hour_stat) {
            push @batch_user_stats, [ $flow_date, $auth_id, $router_id, $user_stats{$user_ip}{$router_id}{in}, $user_stats{$user_ip}{$router_id}{out} ];
	    next;
	    }
	if (!$hour_stat->{byte_in}) { $hour_stat->{byte_in}=0; }
	if (!$hour_stat->{byte_out}) { $hour_stat->{byte_out}=0; }
	$hour_stat->{byte_in} += $user_stats{$user_ip}{$router_id}{in};
	$hour_stat->{byte_out} += $user_stats{$user_ip}{$router_id}{out};
        push @batch_user_stats_update, [ $hour_stat->{byte_in}, $hour_stat->{byte_out}, $auth_id, $router_id ];
	}
    }

#print Dumper(\%wan_stats) if ($debug);

# update database
foreach my $router_id (keys %wan_stats) {
    #last flow for user
    my ($sec,$min,$hour,$day,$month,$year) = (localtime($start_time))[0,1,2,3,4,5];
    #flow time string
    my $flow_date = $hdb->quote(sprintf "%04d-%02d-%02d %02d:%02d:%02d",$year+1900,$month+1,$day,$hour,$min,$sec);
    #per interface stats
    foreach my $int_id (keys %{$wan_stats{$router_id}}) {
	if (!$wan_stats{$router_id}{$int_id}{in})  { $wan_stats{$router_id}{$int_id}{in} = 0; }
	if (!$wan_stats{$router_id}{$int_id}{out})  { $wan_stats{$router_id}{$int_id}{out} = 0; }
	if (!$wan_stats{$router_id}{$int_id}{forward_in})  { $wan_stats{$router_id}{$int_id}{forward_in} = 0; }
	if (!$wan_stats{$router_id}{$int_id}{forward_out})  { $wan_stats{$router_id}{$int_id}{forward_out} = 0; }
	#skip empty stats
        if ($wan_stats{$router_id}{$int_id}{in} + $wan_stats{$router_id}{$int_id}{out} + $wan_stats{$router_id}{$int_id}{forward_in} + $wan_stats{$router_id}{$int_id}{forward_out} ==0) { next; }
	#current wan stats
	push @batch_wan_stats, [
            $flow_date,
            $router_id,
            $int_id,
            $wan_stats{$router_id}{$int_id}{in},
            $wan_stats{$router_id}{$int_id}{out},
            $wan_stats{$router_id}{$int_id}{forward_in},
            $wan_stats{$router_id}{$int_id}{forward_out}
        ];
	}
    }

my $tSQL="UPDATE user_auth SET arp_found= ?, last_found= ? WHERE id= ?";
batch_db_sql_cached($tSQL,\@batch_auth_status);

$tSQL="INSERT INTO user_stats_full (ts,auth_id,router_id,byte_in,byte_out,pkt_in,pkt_out,step) VALUES( ?, ?, ?, ?, ?, ?, ?, ?)";
batch_db_sql_cached($tSQL,\@batch_user_stats_full);

my $dSQL="INSERT INTO user_stats (ts,auth_id,router_id,byte_in,byte_out)  VALUES( ?, ?, ?, ?, ?, ?)";
batch_db_sql_cached($tSQL,\@batch_user_stats);

$tSQL="UPDATE user_stats SET byte_in= ?, byte_out= ? WHERE id= ? AND router_id= ?";
batch_db_sql_cached($tSQL,\@batch_user_stats_update);

#update statistics in DB
my $tSQL="INSERT INTO wan_stats (ts,router_id,interface_id,bytes_in,bytes_out,forward_in,forward_out) VALUES( ?, ?, ?, ?, ?, ?, ?)";
batch_db_sql_cached($tSQL,\@batch_wan_stats);

@batch_sql_traf = ();

if ($config_ref{enable_quotes}) {
    db_log_debug($hdb,"Recalc quotes started");
    foreach my $router_id (keys %routers_found) { recalc_quotes($hdb,$router_id); }
    db_log_debug($hdb,"Recalc quotes stopped");
    }

if (scalar(@detail_traffic)) {
    db_log_debug($hdb,"Start write traffic detail to DB. ".scalar @detail_traffic." lines count") if ($debug);
    #mysql dont work at parallel table lock
    batch_db_sql_csv("traffic_detail", \@detail_traffic);
    @detail_traffic = ();
    db_log_debug($hdb,"Write traffic detail to DB stopped") if ($debug);
    }

$hdb->disconnect();

$saving = 0;

exit;
}

if (IsMyPID($pid_file)) { Remove_PID($pid_file); }
exit;
