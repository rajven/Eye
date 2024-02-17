#!/bin/bash

time=$(date +%s)

#bug in dnsmasq - send relay ip as user ip
if [ "x${DNSMASQ_RELAY_ADDRESS}" = "x${3}" ]; then
    exit
    fi

CIRCUIT_ID=$(echo ${DNSMASQ_CIRCUIT_ID} | /usr/bin/hexdump -v -e '/1 "%02X"' | sed 's/0A$//i' )
REMOTE_ID=$(echo ${DNSMASQ_REMOTE_ID} | /usr/bin/hexdump -v -e '/1 "%02X"' | sed 's/0A$//i' )
#printenv >>/tmp/1

echo "$1;$2;$3;$4;${time};${DNSMASQ_TAGS};${DNSMASQ_SUPPLIED_HOSTNAME};${DNSMASQ_OLD_HOSTNAME};${DNSMASQ_CIRCUIT_ID};${DNSMASQ_REMOTE_ID};${DNSMASQ_CLIENT_ID};${CIRCUIT_ID};${REMOTE_ID}" >>/var/log/dhcp.log &

exit
