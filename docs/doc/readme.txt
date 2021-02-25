yum install krb5-workstation bind-utils -y

ktutil 
ktutil:  addent -password -p dns_updater@ORG.LOCAL -k 1 -e rc4-hmac
Password for dns_updater@ORG.LOCAL: 
ktutil:  write_kt /usr/local/scripts/cfg/dns_updater.keytab
ktutil:  quit

kinit -k -t /usr/local/scripts/cfg/dns_updater.keytab dns_updater@ORG.LOCAL
