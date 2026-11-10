the traffic detail aggregation mode of the collector became an option of the configuration:
0 - no aggregation, one db row per netflow record, 1 - by subscriber, router, proto, the pair
of the addresses and the service port (the default), 2 - by subscriber, router, proto and the
pair of the addresses only. scripts/eye-statd.pl asks the option of the id 77 at the start and
at every reload, and keeps the recommended mode when the base answers a value outside 0, 1, 2

note: docs/databases/{mysql,postgres}/{en,ru}/data.sql carry the option and its value now, so
a fresh installation gets the row of the config table as well and the page of the
configuration lists the mode from the first start

note: the names of the models lose the name of the vendor which stands in front of them, when
a space or a tab follows it: 'OKI MB472' becomes 'MB472', while 'SNR-S5210G-24TX-POE' of the
vendor SNR keeps itself, because a hyphen is not a space. the same was applied to the seed of
the models by hand, so a fresh installation is born clean

note: both files are safe to repeat: the option comes back with ON DUPLICATE KEY UPDATE and ON
CONFLICT, the value of the config table is created only when it is absent, and the update of
the names finds nothing to cut the second time
