#!/usr/bin/perl

#
# Author: Roman Dmitriev <rnd@rajven.ru>
# Purpose: Script to parse DHCP logs, detect client connections via switches
#          using DHCP Option 82 (remote-id / circuit-id), and store link data in the database.
#

use utf8;
use open ":encoding(utf8)";
use Encode;
no warnings 'utf8';
use English;
use base;
use FindBin '$Bin';
use lib "/opt/Eye/scripts";
use Data::Dumper;
use eyelib::config;
use eyelib::main;
use eyelib::database;
use eyelib::common;
use eyelib::net_utils;
use strict;
use warnings;
use Getopt::Long;
use Proc::Daemon;
use POSIX;
use Net::Netmask;
use Text::Iconv;
use File::Tail;
use Fcntl qw(:flock);

# === LOCKING AND INITIALIZATION ===

# Prevent multiple instances of the script
open(SELF, "<", $0) or die "Cannot open $0 - $!";
flock(SELF, LOCK_EX | LOCK_NB) or exit 1;

# Set low process priority (nice = 19)
setpriority(0, 0, 19);

# === GLOBAL VARIABLES ===

my $mute_time = 300;            # Time (in seconds) to suppress duplicate DHCP events
my $log_file = '/var/log/dhcp.log';

# Determine process name and PID file
my $proc_name = $MY_NAME;
$proc_name =~ s/\.[^.]+$//;
my $pid_file = '/run/eye/' . $proc_name;
my $pf = $pid_file . '.pid';

# Daemon setup
my $daemon = Proc::Daemon->new(
    pid_file => $pf,
    work_dir => $HOME_DIR
);

# Check if process is already running
my $pid = $daemon->Status($pf);

my $daemonize = 1;  # Default: run in background

# === COMMAND-LINE ARGUMENT HANDLING ===

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

# === DAEMON CONTROL FUNCTIONS ===

sub stop {
    log_info("Stop requested...");
    if ($pid) {
        print "Stopping pid $pid...";
        if ($daemon->Kill_Daemon($pf)) {
            print "Successfully stopped.\n";
            log_info("Daemon stopped successfully (PID $pid).");
        } else {
            print "Could not find $pid. Was it running?\n";
            log_warning("Failed to stop process PID $pid — possibly already terminated.");
        }
    } else {
        print "Not running, nothing to stop.\n";
        log_info("Daemon is not running — nothing to stop.");
    }
}

sub status {
    if ($pid) {
        print "Running with pid $pid.\n";
        log_info("Status: daemon is running (PID $pid).");
    } else {
        print "Not running.\n";
        log_info("Status: daemon is not running.");
    }
}

sub run {
    log_info("Starting main DHCP log processing loop...");

    if ($pid) {
        print "Already Running with pid $pid\n";
        log_warning("Attempt to start already running daemon (PID $pid).");
        return;
    }

    print "Starting...\n";
    log_info("Initializing daemon...");

    if ($daemonize) {
        $daemon->Init;
        log_debug("Daemon initialized in background mode.");
    }

    setpriority(0, 0, 19);  # Ensure priority is set in child process

    # Converter for legacy cp866-encoded logs
    my $converter = Text::Iconv->new("cp866", "utf8");

    # Main infinite log-processing loop
    while (1) {
        eval {
            log_debug("Starting new DHCP log processing cycle.");

            my %leases;  # cache to suppress duplicates

            # Establish fresh DB connection
            my $hdb = init_db();
            log_debug("Database connection established.");

            # Open log file for tail-like reading
            my $dhcp_log = File::Tail->new(
                name              => $log_file,
                maxinterval       => 5,
                interval          => 1,
                ignore_nonexistent => 1
            ) || die "$log_file not found!";

            log_info("Beginning to read logs from $log_file...");

            while (my $logline = $dhcp_log->read) {
                next unless $logline;
                chomp($logline);

                log_verbose("Log line received: $logline");

                # Remove non-printable characters (keep letters, digits, punctuation, whitespace)
                $logline =~ s/[^\p{L}\p{N}\p{P}\p{Z}]//g;
                log_debug("Line after filtering: $logline");

                my @field_names = qw(
                    type mac ip hostname timestamp
                    tags sup_hostname old_hostname
                    circuit_id remote_id client_id
                    decoded_circuit_id decoded_remote_id
                );

                # Parse fields by semicolon
                my @values = split(/;/, $logline);

                my %dhcp_event;
                log_verbose("GET::");
                @dhcp_event{@field_names} = @values;
                for my $name (@field_names) {
                    my $val = defined $dhcp_event{$name} ? $dhcp_event{$name} : '';
                    log_verbose("Param '$name': $val");
                }

                # Skip lines without valid event type
                next unless $dhcp_event{'type'} && $dhcp_event{'type'} =~ /^(old|add|del)$/i;

                log_debug("Processing DHCP event: type='$dhcp_event{'type'}', MAC='$dhcp_event{'mac'}', IP='$dhcp_event{'ip'}'");

                # Suppress duplicate events within $mute_time window
                if (exists $leases{$dhcp_event{'ip'}} && $leases{$dhcp_event{'ip'}}{type} eq $dhcp_event{'type'} && (time() - $leases{$dhcp_event{'ip'}}{last_time} <= $mute_time)) {
                    log_debug("Skipping duplicate: IP=$dhcp_event{'ip'}, type=$dhcp_event{'type'} (within $mute_time sec window)");
                    next;
                }

                # Refresh config every 60 seconds
                if (time() - $last_refresh_config >= 60) {
                    log_debug("Refreshing configuration...");
                    init_option($hdb);
                }

                # Process DHCP request: update/create DB record
                my $dhcp_record = process_dhcp_request($hdb, $dhcp_event{'type'}, $dhcp_event{'mac'}, $dhcp_event{'ip'}, $dhcp_event{'hostname'}, $dhcp_event{'client_id'}, $dhcp_event{'decoded_circuit_id'}, $dhcp_event{'$decoded_remote_id'});
                next unless $dhcp_record;

                # Cache to suppress duplicates
                $leases{$dhcp_event{'ip'}} = {
                    type => $dhcp_event{'type'},
                    last_time => time()
                };
                my $auth_id = $dhcp_record->{auth_id};

                # === SWITCH AND PORT IDENTIFICATION LOGIC ===

                my ($switch, $switch_port);
                my ($t_remote_id, $t_circuit_id) = ($dhcp_event{'remote_id'}, $dhcp_event{'circuit_id'});

                # Only process connection events (add/old)
                if ($dhcp_event{'type'} =~ /^(add|old)$/i) {
                    log_debug("Attempting to identify switch using Option 82 data...");

                    # 1. Try decoded_remote_id as MAC address
                    if ($dhcp_event{'$decoded_remote_id'}) {
                        $t_remote_id = $dhcp_event{'$decoded_remote_id'};
                        $t_remote_id .= "0" x (12 - length($t_remote_id)) if length($t_remote_id) < 12;
                        $t_remote_id = mac_splitted(isc_mac_simplify($t_remote_id));
                        my $devSQL = "SELECT D.id, D.device_name, D.ip, A.mac " .
                                     "FROM devices AS D, user_auth AS A " .
                                     "WHERE D.user_id = A.User_id AND D.ip = A.ip AND A.deleted = 0 " .
                                     "AND A.mac = ?";
                        $switch = get_record_sql($hdb, $devSQL, $t_remote_id);
                        if ($switch) {
                            $dhcp_event{'remote_id'} = $t_remote_id;
                            $dhcp_event{'circuit_id'} = $dhcp_event{'decoded_circuit_id'};
                            $dhcp_record->{circuit_id} = $dhcp_event{'circuit_id'};
                            $dhcp_record->{remote_id} = $dhcp_event{'remote_id'};
                            log_debug("Switch found via decoded_remote_id: " . $switch->{device_name});
                        }
                    }

                    # 2. If not found, try raw remote_id as MAC
                    if (!$switch && $dhcp_event{'remote_id'}) {
                        $t_remote_id = $dhcp_event{'remote_id'};
                        $t_remote_id .= "0" x (12 - length($t_remote_id)) if length($t_remote_id) < 12;
                        $t_remote_id = mac_splitted(isc_mac_simplify($t_remote_id));
                        my $devSQL = "SELECT D.id, D.device_name, D.ip, A.mac " .
                                     "FROM devices AS D, user_auth AS A " .
                                     "WHERE D.user_id = A.User_id AND D.ip = A.ip AND A.deleted = 0 " .
                                     "AND A.mac = ?";
                        $switch = get_record_sql($hdb, $devSQL, $t_remote_id);
                        if ($switch) {
                            $dhcp_event{'remote_id'} = $t_remote_id;
                            $dhcp_record->{circuit_id} = $dhcp_event{'circuit_id'};
                            $dhcp_record->{remote_id} = $dhcp_event{'remote_id'};
                            log_debug("Switch found via remote_id: " . $switch->{device_name});
                        }
                    }

                    # 3. If still not found, try remote_id as device name prefix
                    if (!$switch && $dhcp_event{'remote_id'}) {
                        my @id_words = split(/ /, $dhcp_event{'remote_id'});
                        if ($id_words[0]) {
                            my $devSQL = "SELECT D.id, D.device_name, D.ip, A.mac " .
                                         "FROM devices AS D, user_auth AS A " .
                                         "WHERE D.user_id = A.User_id AND D.ip = A.ip AND A.deleted = 0 " .
                                         "AND D.device_name LIKE ?";
                            $switch = get_record_sql($hdb, $devSQL, $id_words[0] . '%');
                            if ($switch) {
                                log_debug("Switch found by name: " . $switch->{device_name});
                            }
                        }
                    }

                    # 4. Special case: MikroTik (circuit-id may contain device name)
                    if (!$switch && $dhcp_event{'circuit_id'}) {
                        my @id_words = split(/ /, $dhcp_event{'circuit_id'});
                        if ($id_words[0]) {
                            my $devSQL = "SELECT D.id, D.device_name, D.ip, A.mac " .
                                         "FROM devices AS D, user_auth AS A " .
                                         "WHERE D.user_id = A.User_id AND D.ip = A.ip AND A.deleted = 0 " .
                                         "AND D.device_name LIKE ?";
                            $switch = get_record_sql($hdb, $devSQL, $id_words[0] . '%');
                            if ($switch) {
                                # MikroTik often swaps circuit-id and remote-id
                                ($dhcp_event{'circuit_id'}, $dhcp_event{'remote_id'}) = ($dhcp_event{'remote_id'}, $t_circuit_id);
                                $dhcp_record->{circuit_id} = $dhcp_event{'circuit_id'};
                                $dhcp_record->{remote_id} = $dhcp_event{'remote_id'};
                                log_debug("Detected MikroTik — swapped circuit-id and remote-id");
                            }
                        }
                    }

                    # === LOG IF NO SWITCH MATCH FOUND ===
                    unless ($switch) {
                        log_warning("No matching switch found for DHCP event: IP=$dhcp_event{'ip'}, MAC=$dhcp_event{'mac'}, remote_id='$dhcp_event{'remote_id'}', circuit_id='$dhcp_event{'circuit_id'}'");
                    }

                    # === PORT IDENTIFICATION ===
                    if ($switch) {
                        # Normalize circuit_id for port matching
                        $t_circuit_id =~ s/[\+\-\s]+/ /g;

                        # Load switch ports
                        my @device_ports = get_records_sql($hdb, "SELECT * FROM device_ports WHERE device_id = ?", $switch->{id});

                        my %device_ports_h;
                        foreach my $port_data (@device_ports) {
                            $port_data->{snmp_index} //= $port_data->{port};
                            $device_ports_h{$port_data->{port}} = $port_data;
                        }

                        # Try to match by interface name (ifName)
                        $switch_port = undef;
                        foreach my $port_data (@device_ports) {
                            if ($t_circuit_id =~ /\s*$port_data->{ifname}$/i ||
                                $t_circuit_id =~ /^$port_data->{ifname}\s+/i) {
                                $switch_port = $port_data;
                                last;
                            }
                        }

                        # If not found by name, try hex port (last 2 bytes of decoded_circuit_id)
                        if (!$switch_port && $dhcp_event{'decoded_circuit_id'}) {
                            my $hex_port = substr($dhcp_event{'decoded_circuit_id'}, -2);
                            if ($hex_port && $hex_port =~ /^[0-9a-fA-F]{2}$/) {
                                my $t_port = hex($hex_port);
                                $switch_port = $device_ports_h{$t_port} if exists $device_ports_h{$t_port};
                                log_debug("Port identified via hex: $t_port") if $switch_port;
                            }
                        }

                        # Log and update connection
                        if ($switch_port) {
                            db_log_verbose($hdb, "DHCP $dhcp_event{'type'}: IP=$dhcp_event{'ip'}, MAC=$dhcp_event{'mac'} " . $switch->{device_name} . " / " . $switch_port->{ifname});

                            # Check if connection already exists
                            my $connection = get_records_sql($hdb, "SELECT * FROM connections WHERE auth_id = ?", $auth_id);
                            if (!$connection || !@{$connection}) {
                                my $new_connection = {
                                    port_id    => $switch_port->{id},
                                    device_id  => $switch->{id},
                                    auth_id    => $auth_id
                                };
                                insert_record($hdb, 'connections', $new_connection);
                                log_debug("New connection created: auth_id=$auth_id");
                            }
                        } else {
                            db_log_verbose($hdb, "DHCP $dhcp_event{'type'}: IP=$dhcp_event{'ip'}, MAC=$dhcp_event{'mac'} " . $switch->{device_name} . " (port not identified)");
                            log_warning("Failed to identify port for IP=$dhcp_event{'ip'} on switch=" . $switch->{device_name});
                        }
                    }

                    log_debug("Switch identified: " . ($switch ? $switch->{device_name} : "NONE"));
                    log_debug("Port identified: " . ($switch_port ? $switch_port->{ifname} : "NONE"));
                }
            } # end while log reading

        }; # end eval

        # Exception handling
        if ($@) {
            log_error("Critical error in main loop: $@");
            sleep(60);  # pause before retry
        }
    } # end while(1)
}

# === HELPER FUNCTIONS ===

sub usage {
    print "usage: $MY_NAME (start|stop|status|restart)\n";
    exit(0);
}

sub reload {
    print "reload process not implemented.\n";
    log_warning("Command 'reload' is not supported.");
}

sub restart {
    log_info("Restart requested...");
    stop();
    sleep(2);
    run();
}
