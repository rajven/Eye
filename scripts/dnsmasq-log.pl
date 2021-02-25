#!/usr/bin/perl

#
# Copyright (C) Roman Dmitiriev, rnd@rajven.ru
#

use FindBin '$Bin';
use lib "$Bin/";
use Data::Dumper;
use Rstat::config;
use Rstat::main;
use Rstat::mysql;
use Rstat::net_utils;
use strict;
use warnings;
use Getopt::Long;
use Proc::Daemon;
use Cwd;
use Net::Netmask;
use File::Spec::Functions;
use File::Copy qw(move);
use Text::Iconv;

my $pf = '/var/run/dnsmasq-log.pid';
my $log='/var/lib/dnsmasq/dnsmasq.log';

my $daemon = Proc::Daemon->new(
        pid_file => $pf,
        work_dir => $HOME_DIR
);

# are you running?  Returns 0 if not.
my $pid = $daemon->Status($pf);

my $daemonize = 1;

GetOptions(
    'daemon!' => \$daemonize,
    "help"    => \&usage,
    "reload"  => \&reload,
    "restart" => \&restart,
    "start"   => \&run,
    "status"  => \&status,
    "stop"    => \&stop
) or &usage;

exit(0);

sub stop {
        if ($pid) {
                print "Stopping pid $pid...";
                if ($daemon->Kill_Daemon($pf)) {
                        print "Successfully stopped.\n";
                } else {
                        print "Could not find $pid.  Was it running?\n";
                }
         } else {
                print "Not running, nothing to stop.\n";
         }
}

sub status {
        if ($pid) {
                print "Running with pid $pid.\n";
        } else {
                print "Not running.\n";
        }
}

sub run {
if (!$pid) {
    print "Starting...";
    if ($daemonize) {
        # when Init happens, everything under it runs in the child process.
        # this is important when dealing with file handles, due to the fact
        # Proc::Daemon shuts down all open file handles when Init happens.
        # Keep this in mind when laying out your program, particularly if
        # you use filehandles.
        $daemon->Init;
        }

    setpriority(0,0,19);

    my $converter = Text::Iconv->new("cp866", "utf8");


    while (1) {
        eval {
        # Create new database handle. If we can't connect, die()
        system('touch "'.$log.'"');
        my $hdb = DBI->connect("dbi:mysql:database=$DBNAME;host=$DBHOST","$DBUSER","$DBPASS");
        if ( !defined $hdb ) { die "Cannot connect to mySQL server: $DBI::errstr\n"; }
        open(DNSMASQ, "tail -n 0 -F $log |") || die "$log not found!";
        while (my $logline = <DNSMASQ>) {
            next unless defined $logline;
            chomp($logline);
            log_info("GET CLIENT REQUEST: $logline");
            my ($type,$mac,$ip,$hostname,$tags,$sup_hostname,$old_hostname) = split (/\;/, $logline);
            next if (!$type);
            next if ($type!~/(old|add|del)/i);

	    if (time()-$last_refresh_config>=60) { init_option($hdb); }

            my $client_hostname='UNDEFINED';
            if ($hostname) { $client_hostname=$hostname; } else {
                if ($sup_hostname) { $client_hostname=$sup_hostname; } else {
                    if ($old_hostname) { $client_hostname=$old_hostname; }
                    }
                }

            my $auth_network = $office_networks->match_string($ip);
	    if (!$auth_network) {
		log_error("Unknown network in dhcp request! IP: $ip");
		next;
		}

            my $utf_client_hostname = $converter->convert($client_hostname);
            log_debug(uc($type).">>");
            log_debug("MAC:      ".$mac);
            log_debug("IP:       ".$ip);
            log_debug("TAGS:     ".$tags);
            log_debug("HOSTNAME: ".$client_hostname);
            log_debug("UTF8 HOSTNAME: ".$utf_client_hostname);
            log_debug("END GET");

            my $ip_aton=StrToIp($ip);
            $mac=mac_splitted($mac);
            if ($type eq 'add') {
                log_info("Check for new auth...");
                resurrection_auth($hdb,$ip,$mac,$type);
                }

            my $auth_record = get_custom_record($hdb,'SELECT * FROM User_auth WHERE ip="'.$ip.'" and mac="'.$mac.'" and deleted=0 ORDER BY last_found DESC');
            my $auth_id = $auth_record->{id};

	    my $ad_zone = get_option($hdb,33);
	    my $ad_dns = get_option($hdb,3);
	    $update_hostname_from_dhcp = get_option($hdb,46) || 0;
	    my $subnets_dhcp = get_subnets_ref($hdb);
	    my $enable_ad_dns_update = ($ad_zone and $ad_dns and $update_hostname_from_dhcp);

            log_debug("Subnet: $auth_network");
            log_debug("DNS update flags - zone: $ad_zone dns: $ad_dns config: $update_hostname_from_dhcp subnet: $subnets_dhcp->{$auth_network}->{dhcp_update_hostname}");

            my $maybe_update_dns=(($type=~/add/i or $type=~/old/i) and $utf_client_hostname and $utf_client_hostname !~/UNDEFINED/i and $enable_ad_dns_update and $subnets_dhcp->{$auth_network}->{dhcp_update_hostname});

            if ($maybe_update_dns) {
                log_debug("DNS update enabled.");
		#update dns block
	        my $fqdn_static;
	        if ($auth_record->{dns_name}) {
	                $fqdn_static=lc($auth_record->{dns_name});
        		if ($fqdn_static!~/$ad_zone$/i) {
        		    $fqdn_static=~s/\.$//;
        		    $fqdn_static=lc($fqdn_static.'.'.$ad_zone);
        		    }
        		}
        	my $fqdn=lc(trim($utf_client_hostname));
        	if ($fqdn!~/$ad_zone$/i) {
        		$fqdn=~s/\.$//;
        		$fqdn=lc($fqdn.'.'.$ad_zone);
        		}
                db_log_debug($hdb,"FOUND Auth_id: $auth_id dns_name: $fqdn_static dhcp_hostname: $fqdn");
                #check exists static dns name
	        my $static_exists = 0;
	        my $dynamic_exists = 0;
	        my $static_ok = 0;
	        my $dynamic_ok = 0;
	        my $static_ref;
	        my $dynamic_ref;
                if ($fqdn_static ne '') {
		        my @dns_record=ResolveNames($fqdn_static);
		        $static_exists = (scalar @dns_record>0);
		        if ($static_exists) {
		    	    $static_ref = join(' ',@dns_record);
		    	    foreach my $dns_a (@dns_record) {
		    		if ($dns_a=~/^$ip$/) { $static_ok = $dns_a; }
		    		}
		    	    }
		        } else { $static_ok = 1; }
                if ($fqdn ne '') {
		        my @dns_record=ResolveNames($fqdn);
		        $dynamic_exists = (scalar @dns_record>0);
		        if ($dynamic_exists) {
		    	    $dynamic_ref = join(' ',@dns_record);
		    	    foreach my $dns_a (@dns_record) {
		    		if ($dns_a=~/^$ip$/) { $dynamic_ok = $dns_a; }
		    		}
		    	    }
		        }
		if ($fqdn_static ne '') {
		        if (!$static_ok) {
		            db_log_info($hdb,"Static record mismatch! Expected $fqdn_static => $ip, recivied: $static_ref"); 
                            if (!$static_exists) {
				db_log_info($hdb,"Static dns hostname defined but not found. Create it ($fqdn_static => $ip)!");
				update_ad_hostname($fqdn_static,$ip,$ad_zone,$ad_dns);
				}
		            } else { db_log_debug($hdb,"Static record for $fqdn_static [$static_ok] correct."); }
		        }
		if ($fqdn ne '' and $dynamic_ok ne '') { db_log_debug($hdb,"Dynamic record for $fqdn [$dynamic_ok] correct. No changes required."); }
		if ($fqdn ne '' and !$dynamic_ok) {
		        #log only to file!!!
			log_error($hdb,"Dynamic record mismatch! Expected: $fqdn => $ip, recivied: $dynamic_ref. Checking the status.");
		        #check exists hostname
			my $another_hostname_exists = 0;
			my $hostname_filter = ' LOWER(dns_name)="'.lc($utf_client_hostname).'"';
			if ($fqdn_static ne '' and $fqdn !~/$fqdn_static/) { $hostname_filter = $hostname_filter . ' or LOWER(dns_name)="'.lc($auth_record->{dns_name}).'"'; }
			#check exists another records with some static hostname
    	        	my $name_record = get_custom_record($hdb,'SELECT * FROM User_auth WHERE id<>'.$auth_id.' and deleted=0 and ('.$hostname_filter.') ORDER BY last_found DESC');
        		if ($name_record->{id}) { $another_hostname_exists = 1; }
			if (!$another_hostname_exists) {
			    if ($fqdn_static and $fqdn_static ne '') {
	        		    if ($fqdn_static!~/$fqdn/) {
				        db_log_info($hdb,"Hostname from dhcp request $fqdn differs from static dns hostanme $fqdn_static. Ignore dynamic binding!");
#				        update_ad_hostname($fqdn,$ip,$ad_zone,$ad_dns);
                                        }
				    } else {
				    db_log_info($hdb,"Static dns hostname not defined. Create dns record by dhcp request. $fqdn => $ip");
				    update_ad_hostname($fqdn,$ip,$ad_zone,$ad_dns);
				    }
			    } else {
			    db_log_error($hdb,"Found another record with some hostname id: $name_record->{id} ip: $name_record->{ip} hostname: $name_record->{dns_hostname}. Skip update.");
			    }
			}
            	#end update dns block
            	} else {
            	db_log_debug($hdb,"FOUND Auth_id: $auth_id");
            	}

            if ($type=~/add/i and $utf_client_hostname and $utf_client_hostname !~/UNDEFINED/i) {
        	my $auth_rec;
        	$auth_rec->{dhcp_hostname} = $utf_client_hostname;
                update_record($hdb,'User_auth',$auth_rec,"id=$auth_id");
                }

            if ($hotspot_networks->match_string($ip) and $ignore_hotspot_dhcp_log) { next; }

            if ($ignore_update_dhcp_event and $type=~/old/i) { next; }

            if ($type=~/(old|del)/i) {
		    my $auth_rec;
		    $auth_rec->{dhcp_action}=$type;
		    $auth_rec->{dhcp_time}=GetNowTime();
		    update_record($hdb,'User_auth',$auth_rec,"id=$auth_id");
    	        }

	    my $dhcp_log;
	    $dhcp_log->{auth_id} = $auth_id;
	    $dhcp_log->{ip} = $ip;
	    $dhcp_log->{ip_int} = $ip_aton;
	    $dhcp_log->{mac} = $mac;
	    $dhcp_log->{action} = $type;
	    insert_record($hdb,'dhcp_log',$dhcp_log);
            }

        close DNSMASQ;
        };
        if ($@) { log_error("Exception found: $@"); }
        }
    } else {
        print "Already Running with pid $pid\n";
    }
}

sub usage {
    print "usage: dnsmasq-log.pl (start|stop|status|restart)\n";
    exit(0);
}

sub reload {
    print "reload process not implemented.\n";
}

sub restart {
    stop;
    run;
}
