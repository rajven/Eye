#!/bin/bash

CFG_FILE=/etc/freeradius/3.0/mods-config/preprocess/huntgroups

/opt/Eye/scripts/utils/radius/print_huntgroups.pl >"${CFG_FILE}.new"
ret=$?

if [ $ret -ne 0 ]; then
    echo "Error update huntgroups!"
    exit 100
    fi

cat "${CFG_FILE}.new" >${CFG_FILE}
rm -f "${CFG_FILE}.new"
systemctl restart freeradius

exit 0
