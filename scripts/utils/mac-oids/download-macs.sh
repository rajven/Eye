#!/bin/bash

#wget http://standards-oui.ieee.org/cid/cid.csv
#wget http://standards-oui.ieee.org/iab/iab.csv
#wget http://standards-oui.ieee.org/oui/oui.csv
#wget http://standards-oui.ieee.org/oui28/mam.csv
#wget http://standards-oui.ieee.org/oui36/oui36.csv

wget https://gitlab.com/wireshark/wireshark/-/raw/master/manuf -O - | grep -v "^#" | grep -v "^$" | sed -e 's/\t/;/g' | sed 's/00:00:00;//'> manuf.csv

exit
