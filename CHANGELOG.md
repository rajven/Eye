# Changelog 3-2-9 - release
- bugfix: choosing a row of the searchable select closed the dialog of the mass operations.
  the list of the widget is fixed to the window and its rows may stand below the panel of the
  dialog, while the pick of a row hid the list at the mousedown: the release of the button
  fell on the empty place where the row had laid, the browser gives such a pair of events to
  the nearest living node - to the dialog itself - and the handler which closes the panel on a
  click aside read the choice as a click on the backdrop. the row is chosen in the click now,
  the mousedown keeps only the focus of the search field and the ban on the selection of the
  text, and a click of the real backdrop still closes the panel
- note: the focus of a picked row returns to the button of the field: the search input is
  hidden together with the list, and a hidden input leaves the browser with the focus of the
  body, so the operator lost his place in the dialog after every choice
- added: the dialog of the mass operations of the address list creates a device. the row
  carries the mark of the operation, the type of the device and its model, because the model
  owns the ports, the templates of the monitoring system and the way of the polling; the
  vendor of the new device is read from the model and not asked of the operator - note: an
  address which already owns a device, an address without an ip and an address of the sixth
  version are passed by with a line in the work log, and the answers of the dialog are read
  against the dictionaries of the models and of the types before the first record is touched
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
- changed: the list of a searchable select left the scrolling body of the dialog. it was
  clamped to the room which the panel of the dialog leaves, and a short panel is smaller than
  the list needs: two rows of twenty were shown in a dialog of three hundred pixels. the list
  is fixed to the viewport now, keeps to the panel sideways and is limited by the window from
  top to bottom, so a dropdown may cover the head and the action of the dialog while it is
  open, as any menu does, and ten rows of twenty are seen without a scroll
- changed: a fixed list follows its field instead of vanishing. the first thought was to
  close the list on any scroll, but the browser itself scrolls the page when a list is
  revealed on a short window, and the operator saw a dropdown which disappeared the moment it
  appeared. now the place of the list is computed again in the same frame, and the list is
  taken away only when the field itself has gone out of the window or out of the box which
  clips it
- bugfix: below the width of 1180 pixels the switches of the density and of the theme were
  not reachable at all. the responsive rule hid the whole foot of the rail together with the
  captions of the navigation, while the foot is the only place where those two buttons live:
  on a laptop, in a scaled window or on a narrow screen the operator could not change the
  look of the interface by any means, keyboard included. the foot stays now and the captions
  of the buttons turn into icons - a frame of rows of a different height for the density, a
  half of a circle for the theme, which fills for the dark one and empties for the light one
- note: the name of the mode which is in effect is written into the title and into the
  aria-label of the button by eye.js and stands in the markup of the rail from the start: the
  icon is drawn instead of the text, so without those attributes the control would be
  anonymous for a screen reader and would give no hint to a pointing hand
- note: the wide rail of 232 pixels keeps its text captions and shows no icons, the horizontal
  bar of 860 pixels puts both switches side by side at its end
- bugfix: the button of the density in the rail did nothing after the first press. the mode
  'normal' was written by removing the attribute of the root element, while the cycle read
  the current mode from that same attribute and took 'compact' where there was nothing: the
  second press computed 'compact' again, the next mode was 'normal' again, and 'roomy' could
  not be reached at all. the attribute now names the mode everywhere - in js/eye.js, in the
  inline script of inc/header.php and in that of inc/header_public.php - the cycle asks the
  remembered value first, and all three agree that the compact look is the default, so a
  page is born dense and the rows do not jump when eye.js arrives
- note: a visitor who never pressed the button keeps no record of his own: the init used to
  run setDens with the default value and wrote 'compact' into the storage of everybody on
  the first opened page instead of leaving the choice empty
- note: the caption of the button names the mode which is in effect and is synced at the
  init as well, so a reload of a page which remembers 'roomy' no longer offers to press
  'Плотно' while the rows are already wide
- changed: the version of the installation became 3-2-9: the seeds and the schemas of both
  engines name it, and the directory of the updates scripts/updates/3-2-9 is the stage which
  upgrade.pl walks through last
