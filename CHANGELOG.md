# Changelog 3-2-9 - release
- changed: the aggregation mode of the traffic details is an option of the configuration now
  (the id 77, netflow_detail_aggregation), it is not written in the collector by hand any
  more. scripts/eye-statd.pl asks the option at the start and at every reload of the
  configuration, and keeps the recommended mode 1 when the base answers nothing or answers a
  value outside 0, 1, 2 - note: the recommended mode is the default value of the option, so a
  base without a record of the value works as the collector worked before. the seeds of both
  engines carry the option and its value, and the page of the configuration lists the mode
  from the first start: its type is int between 0 and 2, as the mode of the mikrotik
  interface is written
- added: 001_detail_aggregation - the option of the id 77 and its value for an installed
  base. the value is created only when the option is not in the config table yet, so the mode
  which the operator chose of his own survives the upgrade
- added: 002_model_vendor_prefix - the name of the vendor in front of the name of a model
  goes away when a space or a tab stands right behind it: 'OKI MB472' becomes 'MB472'. only
  the vendor of the model itself is compared, so 'Linux server' of the vendor Unknown keeps
  itself although a vendor Linux exists in the list - note: a name which only looks like the
  vendor is not touched: 'SNR-S5210G-24TX-POE' of the vendor SNR keeps itself, because a
  hyphen is not a space, and a model whose name is the vendor name alone also keeps itself,
  because nothing would be left of it after the cut
- changed: the version of the installation became 3-2-9: the seeds and the schemas of both
  engines name it, and the directory of the updates scripts/updates/3-2-9 is the stage which
  upgrade.pl walks through last
