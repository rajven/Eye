#!/bin/bash

time=$(date +%s)
echo "$1;$2;$3;$4;${time};${DNSMASQ_TAGS};${DNSMASQ_SUPPLIED_HOSTNAME};${DNSMASQ_OLD_HOSTNAME};${DNSMASQ_CIRCUIT_ID};${DNSMASQ_REMOTE_ID};${DNSMASQ_CLIENT_ID}" >>/var/log/dhcp.log &

exit
