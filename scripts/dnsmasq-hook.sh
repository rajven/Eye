#!/bin/bash

time=$(date +%s)
echo "$1;$2;$3;$4;${time};${DNSMASQ_TAGS};${DNSMASQ_SUPPLIED_HOSTNAME};${DNSMASQ_OLD_HOSTNAME}" >>/var/spool/dhcp-log.socket &

exit
