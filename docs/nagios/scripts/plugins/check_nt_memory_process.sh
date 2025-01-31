#!/bin/bash
#Date: 11.04.08
#Author: Christian Mies (cmies@itnovum.de)
#Version 0.0.1
#About: Take Working Set Perfmon Value of specified Process and calculate it to MB
#       ./check_nt -H <HOSTNAME> -p 1248 -v COUNTER -l "\\Process(NSClient++)\Working Set","Belegter Arbeitsspeicher NSClient++,Byte"

#typeset -i mb crit warn

pluginpath="/usr/lib/nagios/plugins"
pluginname=`basename $0`
. $pluginpath/utils.sh

while getopts "H:p:s:P:L:w:c:" options; do
  case $options in
        H)hostname=$OPTARG;;
        p)port=$OPTARG;;
        P)process=$OPTARG;;
	L)language=$OPTARG;;
	s)secret=$OPTARG;;
        w)warn=$OPTARG;;
        c)crit=$OPTARG;;
        *)
          echo "$pluginname Help:"
          echo "-----------------"
          echo "-H <Hostname> : Hostname/IP of Citrix Enterprise Server 4"
          echo "-p <portnumber>: NSClient++ Port Default: 1248"
          echo "-s <secret>: NSClient++ secret"
          echo "-P Process: Which Process to check?"
          echo "-L Language: Language for Perfmon. Values: german/english Default: english"
          echo "-----------------"
          echo "Usage: $pluginname -H <HOSTADDRESS> -p <port> -s <secret> -P <processname> -L <language> -w <warn> -c <crit>"
          exit 3
        ;;
  esac
done

if [ -z $port ]; then
        port=1248;
fi;
if [ -z $language ]; then
        language="english";
fi;
if [ -z $process ]; then
        echo "UNKNOWN: Missing Process Name";
	exit $STATE_UNKNOWN;
fi;
if [ -z $crit ] || [ -z $warn ]; then
        echo "UNKNOWN: Critical / Warning Value must be set";
	exit $STATE_UNKNOWN;
fi;

if [ -z $secret ]; then
        echo "UNKNOWN: Secret not defined!";
	exit $STATE_UNKNOWN;
fi;

if [ $language = "english" ]; then
	perfcount_name="Process"
	perfcount_value="Working Set"
	output="Used Memory of"
	perfout="UsedMemory"
	fi;

if [ $language = "german" ]; then
	perfcount_name="Prozess"
	perfcount_value="Arbeitsseiten"
	output="Belegter Speicher von"
	perfout="Speicer"
	fi;

if [ $language = "russian" ]; then
	perfcount_name="Процесс"
	perfcount_value="Рабочий набор"
	output="Использовано памяти"
	perfout="UsedMemory"
	fi;

command="\\$perfcount_name($process)\\$perfcount_value"
memory=`$pluginpath/check_nt -H $hostname -p $port -s $secret -v COUNTER -l "$command" | tr -d '\n'`
mb=`echo "scale=0; $memory/1048576"|bc -l`

if [ $mb -ge $crit ]; then
	echo "CRITICAL: $output $process: $mb MB|$perfout=${mb}MB;$warn;$crit;;;"
        exit $STATE_CRITICAL;
fi;
if [ $mb -ge $warn ] && [ $mb -lt $crit ]; then
        echo "WARNING: $output $process: $mb MB|$perfout=${mb}MB;$warn;$crit;;;"
        exit $STATE_WARNING;
fi;

echo "OK: $output $process: $mb MB|$perfout=${mb}MB;$warn;$crit;;;"
exit $STATE_OK;
