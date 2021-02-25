#!/bin/bash

log='/var/lib/dnsmasq/dnsmasq.log'
echo "$1;$2;$3;$4;${DNSMASQ_TAGS};${DNSMASQ_SUPPLIED_HOSTNAME};${DNSMASQ_OLD_HOSTNAME}" >>${log}

exit
