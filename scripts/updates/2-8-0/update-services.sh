#!/bin/bash

if [ ! -e /etc/systemd/system/dnsmasq.service.d ]; then
    mkdir -p /etc/systemd/system/dnsmasq.service.d
    fi

cat 2-8-0/override.conf >/etc/systemd/system/dnsmasq.service.d/override.conf
cat 2-8-0/dhcp-log-truncate.service >/etc/systemd/system/dhcp-log-truncate.service
cat 2-8-0/dhcp-log.service >/etc/systemd/system/dhcp-log.service

systemctl daemon-reload
systemctl enable dhcp-log-truncate.service
systemctl restart dhcp-log.service

exit 0


