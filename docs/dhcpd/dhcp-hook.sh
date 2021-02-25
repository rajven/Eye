#!/bin/bash

pipe='/var/lib/dhcpd/dhcpd.log'

#TYPE;IP;MAC;HOSTNAME
echo "$1;$2;$3;$4" >>${pipe}

exit
