#!/bin/bash

time=$(date +%s)

#bug in dnsmasq - send relay ip as user ip
if [ "x${DNSMASQ_RELAY_ADDRESS}" = "x${3}" ]; then
    exit
    fi

# dnsmasq passes option 82 data (DNSMASQ_CIRCUIT_ID / DNSMASQ_REMOTE_ID /
# DNSMASQ_SUBSCRIBER_ID) as RAW binary bytes, not hex. Values must be quoted:
# unquoted expansions are subject to word splitting and glob expansion.
# printf appends no trailing newline (unlike echo), so there is no need to
# trim it from the hexdump output. hexdump -v prevents suppression of
# repeated bytes.
CIRCUIT_ID=$(printf '%s' "${DNSMASQ_CIRCUIT_ID}" | /usr/bin/hexdump -v -e '/1 "%02X"')
REMOTE_ID=$(printf '%s' "${DNSMASQ_REMOTE_ID}" | /usr/bin/hexdump -v -e '/1 "%02X"')

# Raw fields are written to the log verbatim. Semicolon is the field delimiter
# and CR/LF would split the 13-field line, so strip them from the raw data.
RAW_CIRCUIT_ID="${DNSMASQ_CIRCUIT_ID//[$'\r\n;']/}"
RAW_REMOTE_ID="${DNSMASQ_REMOTE_ID//[$'\r\n;']/}"

# Debug: touch /run/dnsmasq-hook.debug to enable, rm to disable.
# No dnsmasq restart required, the flag is checked on every event.
if [ -f /run/dnsmasq-hook.debug ]; then
    {
        printf '=== %s action=%s mac=%s ip=%s hostname=%s\n' "$(date '+%Y-%m-%d %H:%M:%S')" "$1" "$2" "$3" "$4"
        for v in DNSMASQ_RELAY_ADDRESS DNSMASQ_CIRCUIT_ID DNSMASQ_REMOTE_ID DNSMASQ_SUBSCRIBER_ID DNSMASQ_CLIENT_ID DNSMASQ_TAGS DNSMASQ_SUPPLIED_HOSTNAME DNSMASQ_OLD_HOSTNAME DNSMASQ_VENDOR_CLASS; do
            if [ -n "${!v+x}" ]; then
                printf '%s bytes=' "$v"
                printf '%s' "${!v}" | /usr/bin/hexdump -v -e '/1 "%02x"'
                printf ' escaped=%q\n' "${!v}"
            else
                printf '%s UNSET\n' "$v"
            fi
        done
        printf 'logline='
        printf '%s;%s;%s;%s;%s;%s;%s;%s;%s;%s;%s;%s;%s\n' "$1" "$2" "$3" "$4" "$time" "${DNSMASQ_TAGS}" "${DNSMASQ_SUPPLIED_HOSTNAME}" "${DNSMASQ_OLD_HOSTNAME}" "${RAW_CIRCUIT_ID}" "${RAW_REMOTE_ID}" "${DNSMASQ_CLIENT_ID}" "${CIRCUIT_ID}" "${REMOTE_ID}"
    } >> /var/log/dhcp-hook-debug.log 2>&1
fi

echo "$1;$2;$3;$4;${time};${DNSMASQ_TAGS};${DNSMASQ_SUPPLIED_HOSTNAME};${DNSMASQ_OLD_HOSTNAME};${RAW_CIRCUIT_ID};${RAW_REMOTE_ID};${DNSMASQ_CLIENT_ID};${CIRCUIT_ID};${REMOTE_ID}" >>/var/log/dhcp.log &

exit
