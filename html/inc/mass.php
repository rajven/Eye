<?php
// The common part of the mass operations of the lists: the marks which a dialog carries,
// and the dialog of the lists of the devices of eye.
//
// Every list which shows the devices - the passive equipment, the hosts of the monitoring
// page - asks the same questions about the same records and posts the answers to the same
// endpoint, so the fields of the dialog are described here once: the page names the groups
// which its selection owns, and the endpoint reads the marks of the very same list. A field
// which was added to the dialog can not be forgotten by the endpoint, and the endpoint can
// not trust a field which the dialog does not offer. The lists of the subscribers and of
// the addresses keep their own dialogs, but the rule of the marks is one for everybody
if (!defined("CONFIG")) {
    die("Not defined");
}

// The fields of the dialog by groups. Every field carries the mark of it (the check box
// which asks for the change), its caption and the set of the new value. The groups which
// the monitoring system does not know are not built at all: zabbix has no macros while
// nagios is chosen, and the whole block is empty while the api is not configured
function mass_device_fields($db, $conf = [])
{
    $groups = [];

    $groups['device'] = [
        'caption' => WEB_mass_group_device,
        'fields' => [
            ['mark' => 'e_set_type', 'caption' => WEB_cell_type,
             'control' => function () use ($db, $conf) {
                 print_devtype_select($db, 'a_dev_type', (int)($conf['dev_type'] ?? 5));
             }],
            ['mark' => 'e_set_model', 'caption' => WEB_cell_host_model,
             'control' => function () use ($db, $conf) {
                 print_device_model_select($db, 'a_device_model_id', 0, $conf['model_filter'] ?? '');
             }],
            ['mark' => 'e_set_building', 'caption' => WEB_location_name,
             'control' => function () use ($db) {
                 print_building_select($db, 'a_building_id', 0);
             }],
            ['mark' => 'e_set_snmp_version', 'caption' => WEB_snmp_version,
             'control' => function () {
                 print_snmp_select('a_snmp_version', get_const('snmp_default_version'));
             }],
            ['mark' => 'e_set_ro_community', 'caption' => WEB_snmp_community_ro,
             'control' => function () {
                 print "<input type='text' name='a_ro_community' value=\""
                     . htmlspecialchars((string)get_const('snmp_default_community'), ENT_QUOTES, 'UTF-8')
                     . '">';
             }],
            ['mark' => 'e_set_rw_community', 'caption' => WEB_snmp_community_rw,
             'control' => function () {
                 print "<input type='text' name='a_rw_community' value='private'>";
             }],
        ],
    ];

    if (is_monitoring_enabled()) {
        $fields = [
            ['mark' => 'e_monitoring', 'caption' => WEB_monitoring,
             'control' => function () {
                 print_qa_select('a_monitoring', 1);
             }],
        ];
        if (is_nagios_enabled()) {
            $fields[] = ['mark' => 'e_link', 'caption' => WEB_cell_link,
                'control' => function () {
                    print_qa_select('a_link', 0);
                }];
            $fields[] = ['mark' => 'e_handler', 'caption' => WEB_cell_nagios_handler,
                'control' => function () use ($db) {
                    print_nagios_handler_select($db, 'a_handler');
                }];
        }
        $groups['monitoring'] = ['caption' => WEB_mass_group_monitoring, 'fields' => $fields];
    }

    if (is_zabbix_enabled()) {
        $groups['zabbix'] = [
            'caption' => WEB_mass_group_zabbix,
            // the model of the device owns the templates of its host, so the dialog is
            // able to follow the model or to leave the host without templates: the names
            // of the tags and of the macros belong to the device itself and stay in eye
            'hint' => WEB_zabbix_mass_templates_hint,
            'fields' => [
                ['mark' => 'e_tag_add', 'caption' => WEB_zabbix_mass_tag_add,
                 'control' => function () use ($db) {
                     print_zabbix_tag_id_select('a_tag_add', zabbix_global_names($db, 'tags'),
                         WEB_zabbix_tag_select_model_empty);
                     print '&nbsp;' . WEB_zabbix_value . '&nbsp;<input type="text" name="a_tag_value" value="" size=12>';
                 }],
                ['mark' => 'e_tag_del', 'caption' => WEB_zabbix_mass_tag_del,
                 'control' => function () use ($db) {
                     print_zabbix_tag_id_select('a_tag_del', zabbix_global_names($db, 'tags'),
                         WEB_zabbix_tag_select_model_empty);
                 }],
                ['mark' => 'e_macro_add', 'caption' => WEB_zabbix_mass_macro_add,
                 'control' => function () use ($db) {
                     print_zabbix_macro_id_select('a_macro_add', zabbix_global_names($db, 'macros'),
                         WEB_zabbix_macro_select_model_empty);
                     print '&nbsp;' . WEB_zabbix_value . '&nbsp;<input type="text" name="a_macro_value" value="" size=12>';
                 }],
                ['mark' => 'e_macro_del', 'caption' => WEB_zabbix_mass_macro_del,
                 'control' => function () use ($db) {
                     print_zabbix_macro_id_select('a_macro_del', zabbix_global_names($db, 'macros'),
                         WEB_zabbix_macro_select_model_empty);
                 }],
                ['mark' => 'e_templates', 'caption' => WEB_zabbix_mass_templates,
                 'control' => function () {
                     mass_templates_select('a_templates');
                 }],
            ],
        ];
    }

    return $groups;
}

// The hosts of eye take the templates of their model; a host of another owner keeps its
// own templates, and the operation says so instead of touching them
function mass_templates_select($name, $current = ZABBIX_TEMPLATES_RELINK)
{
    print "<select id=\"" . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . "\" name=\""
        . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . "\">\n";
    print_select_item(WEB_zabbix_mass_templates_relink, ZABBIX_TEMPLATES_RELINK, $current);
    print_select_item(WEB_zabbix_mass_templates_unlink, ZABBIX_TEMPLATES_UNLINK, $current);
    print "</select>\n";
}

// The marks which the dialog of the devices can send: the endpoint asks the same list, so
// a request which brings no mark at all changes nothing and must not be answered green
function mass_device_marks($db, $conf = [])
{
    $marks = [];
    foreach (mass_device_fields($db, $conf) as $group) {
        foreach ($group['fields'] as $field) {
            $marks[] = $field['mark'];
        }
    }
    return $marks;
}

// The marks of a dialog which the request really carries
function mass_marks_asked($marks, $page_url = null)
{
    $asked = [];
    foreach ($marks as $mark) {
        if (getPOST($mark, $page_url) !== null) {
            $asked[] = $mark;
        }
    }
    return $asked;
}

// The marks of the dialog of the devices which the request really carries
function mass_device_asked($db, $page_url = null)
{
    return mass_marks_asked(mass_device_marks($db), $page_url);
}

// The button of the list and the dialog above it. The page prints nothing when the
// reader may not change the records: the endpoint would refuse the operation, and an
// offer which can not be taken is not an offer
function print_mass_devices_dialog($db, $groups = null, $conf = [])
{
    if (!acl_can_edit()) {
        return false;
    }

    $fields = mass_device_fields($db, $conf);
    if ($groups === null) {
        $groups = array_keys($fields);
    }

    $rows = '';
    $notes = [];
    foreach ($groups as $name) {
        if (empty($fields[$name])) {
            continue;
        }
        // the caption of a group is a line of its own: the class of the page tables would
        // make it a sticky header of the table and the columns of the dialog would lean on
        // the single cell which spans them all
        $rows .= "<tr class=\"dlg-group\"><td colspan=\"3\">" . $fields[$name]['caption'] . "</td></tr>\n";
        foreach ($fields[$name]['fields'] as $field) {
            $rows .= "<tr>\n";
            $rows .= "<td><input type=checkbox class=\"putField\" name=\""
                . $field['mark'] . "\" value='1'></td>\n";
            $rows .= "<td>" . $field['caption'] . "</td>\n";
            $rows .= "<td>";
            ob_start();
            call_user_func($field['control']);
            $rows .= ob_get_clean();
            $rows .= "</td>\n";
            $rows .= "</tr>\n";
        }
        if (!empty($fields[$name]['hint'])) {
            // the note is not a row of the table: it stands under it, in the body of the
            // card, and does not carry the caption look of a page table
            $notes[] = $fields[$name]['hint'];
        }
    }

    if ($rows === '') {
        return false;
    }

    print "<a class=\"mainButton\" href=\"#eye-mass\">" . WEB_btn_apply_selected . "</a>\n";
    print "<dialog id=\"eye-mass\" class=\"eye-dlg\">\n";
    print "  <button type=\"button\" class=\"x\" data-close aria-label=\""
        . htmlspecialchars(WEB_btn_close, ENT_QUOTES, 'UTF-8') . "\">&#10005;</button>\n";
    // the card: the head and the action of the dialog keep their places, the body of a long
    // form scrolls on its own. the shared dialog lists the fields of every device list at
    // once, and on a short screen the button of the operation would otherwise fall out of
    // the box behind the last row
    print "  <form id=\"formNetDevApply\" class=\"dlg-card\">\n";
    print "        <div class=\"dlg-h\"><h2>" . WEB_selection_title . "</h2></div>\n";
    print "        <input type=\"hidden\" name=\"ApplyForAll\" value=\"MassChange\">\n";
    print "        <div class=\"dlg-b\">\n";
    print "        <table class=\"data\" align=center>\n";
    // the first line of the table is a caption over three columns, and a fixed layout reads
    // the widths of the columns from the first line: they are named here instead
    print "        <colgroup><col><col><col></colgroup>\n";
    print $rows;
    print "        </table>\n";
    foreach ($notes as $note) {
        print "        <p class=\"dlg-i\">" . $note . "</p>\n";
    }
    print "        </div>\n";
    print "        <div class=\"dlg-f\"><input type=\"submit\" name=\"submit\" class=\"btn\" value=\""
        . WEB_btn_apply . "\"></div>\n";
    print "    </form>\n";
    print "</dialog>\n";

    return true;
}
