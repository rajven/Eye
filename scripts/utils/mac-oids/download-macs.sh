#!/bin/bash

#wget http://standards-oui.ieee.org/cid/cid.csv
#wget http://standards-oui.ieee.org/iab/iab.csv
#wget http://standards-oui.ieee.org/oui/oui.csv
#wget http://standards-oui.ieee.org/oui28/mam.csv
#wget http://standards-oui.ieee.org/oui36/oui36.csv

set -o pipefail
wget https://www.wireshark.org/download/automated/data/manuf -O - | grep -v "^#" | grep -v "^$" | sed -e 's/\t/;/g' | sed 's/00:00:00;//'> /opt/Eye/scripts/utils/mac-oids/manuf.csv
[ $? -ne 0 ] && cp -f Eye/scripts/utils/mac-oids/manuf.csv /opt/Eye/scripts/utils/mac-oids/

exit
