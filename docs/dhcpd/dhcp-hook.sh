#!/bin/bash

time=$(date +%s)
#TYPE;MAC;IP;HOSTNAME
echo "$1;$3;$2;$4;${time}" >>/var/spool/dhcp-log.socket &

exit
