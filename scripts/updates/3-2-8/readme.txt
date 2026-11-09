the values of the zabbix macros of the secret type are ciphered in the base, as the passwords of the equipment are
the interface shows the mask of such a value instead of the value, and a save which brings the mask back changes nothing
added: a name of the global list which moved from the text to the secret type (or back) takes the values of the devices with it
note: the values of the secret macros of an installed base are ciphered by after_macro_secrets.pl, the export of a host opens them for zabbix

added: 001_web_indexes - the indexes of the queries of the web interface, the same list
for both engines. P0 is the hot path: the ptr cache by ip, the addresses of a user, the
ip and the mac of an address with the sign of the archive, the ordered live and archived
ip list, the hostname of an address, the aliases, the users of an organizational unit,
the traffic of one subscriber, the connections of one port. P1 is the log and report
pages: the dhcp log, the netflow report, the wan report, the remote syslog. P2 is the
secondary pages: the devices of a user, the settings of a device, the unknown macs of a
device and of a port. the optional cleanups of the keys which these make redundant stand
commented at the end of both files

added: 002_column_parity - the three counters of a flow take the empty default which the
copy of the base in postgres has always had, the mac of the history and of the unknown
macs becomes free in postgres as it is free in mysql, the default of the version row
names the version of the installation

note: the mysql file applies one index per ALTER, a repeated run reports a duplicate key
name for the keys which are present allready and goes on with the rest; the postgres
file is idempotent (IF NOT EXISTS) and can be re-run as it stands

note: docs/databases/{mysql,postgres}/{en,ru}/create_db carry the same indexes and the
same columns now, so a fresh installation is born as the base which the patches upgrade

note: upgrade.pl takes the comments out of a statement now. before it a '-- ' line above
an ALTER or a CREATE INDEX stayed inside the text of the statement, and the line which
skips a statement starting with a comment threw the whole ALTER away: the
001_web_indexes as it was written created 3 of its 16 mysql keys and 13 of its 38
postgres ones, while the base answered that the patch was applied. the same defect
silently skipped 27 of the 103 statements of 3-0-1/migration.msql and all four
statements of 3-0-5/user_auth.psql, whose migration of the mac column of postgres never
reached a base which was upgraded through that version
