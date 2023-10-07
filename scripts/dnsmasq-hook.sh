#!/bin/bash

time=$(date +%s)

CIRCUIT_ID=$(echo ${DNSMASQ_CIRCUIT_ID} | /usr/bin/hexdump -v -e '/1 "%02X"' | sed 's/0A$//i' )
REMOTE_ID=$(echo ${DNSMASQ_REMOTE_ID} | /usr/bin/hexdump -v -e '/1 "%02X"' | sed 's/0A$//i' )

echo "$1;$2;$3;$4;${time};${DNSMASQ_TAGS};${DNSMASQ_SUPPLIED_HOSTNAME};${DNSMASQ_OLD_HOSTNAME};${CIRCUIT_ID};${REMOTE_ID};${DNSMASQ_CLIENT_ID};${CIRCUIT_ID};${REMOTE_ID}" >>/var/log/dhcp.log &

exit
