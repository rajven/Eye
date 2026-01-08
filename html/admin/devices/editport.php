<?php
require_once($_SERVER['DOCUMENT_ROOT'] . "/inc/auth.php");
require_once($_SERVER['DOCUMENT_ROOT'] . "/inc/languages/" . HTML_LANG . ".php");
require_once($_SERVER['DOCUMENT_ROOT'] . "/inc/idfilter.php");

if (isset($_POST["editport"])) {
    $new['snmp_index'] = $_POST["f_snmp"] * 1;
    $new['uplink'] = $_POST["f_uplink"] * 1;
    $new['nagios'] = $_POST["f_nagios"] * 1;
    $new['skip'] = $_POST["f_skip"] * 1;
    $new['description'] = $_POST["f_description"];
    update_record($db_link, "device_ports", "id=?", $new, [ $id ]);

    $target_id = $_POST["f_target_port"];
    bind_ports($db_link, $id, $target_id);

    header("location: editport.php?id=$id");
    exit;
}

unset($_POST);

$port = get_record($db_link, 'device_ports', "id=?" ,[ $id]);
$device_id = $port['device_id'];
$device = get_record($db_link, 'devices', "id=?" , [ $device_id ]);
$user_info = get_record_sql($db_link, "SELECT * FROM user_list WHERE id=?", [ $device['user_id'] ]);

require_once($_SERVER['DOCUMENT_ROOT'] . "/inc/header.php");

print_device_submenu($page_url);
print_editdevice_submenu($page_url, $device_id, $device['device_type'], $user_info['login']);

?>
<div id="contsubmenu">

    <form name="def" action="editport.php?id=<?php echo $id; ?>" method="post">
        <div class="main">
            <div class="field">
                <?php print "<label for='port'>" . WEB_device_port_number . "</label>"; ?><input type="text" id="port" disabled="disabled" style="text-align:center;" value="<?php print $port['port']; ?>" />
            </div>
            <div class="field">
                <?php print "<label for='f_snmp'>" . WEB_device_port_snmp_index . "</label>";
                print "<input type=\"text\" name='f_snmp' style='text-align:center;' value='" . $port['snmp_index'] . "'>"; ?>
            </div>
            <div class="field">
                <?php print "<label for='f_ifIndex'>ifIndex</label>"; ?><input type="text" id="f_ifIndex" disabled="disabled" style="text-align:center;" value="<?php print $port['ifName']; ?>" />
            </div>
            <div class="field">
                <?php print "<label for='f_uplink'>" . WEB_device_port_uplink . "</label>";
                print_qa_select('f_uplink', $port['uplink']); ?>
            </div>
            <div class="field">
                <?php print "<label for='f_nagios'>" . WEB_nagios . "</label>";
                print_qa_select('f_nagios', $port['nagios']); ?>
            </div>
            <div class="field">
                <?php print "<label for='f_skip'>" . WEB_device_port_allien . "</label>";
                print_qa_select('f_skip', $port['skip']); ?>
            </div>
            <div class="field">
                <?php print "<label for='f_description'>" . WEB_cell_description . "</label>";
                print "<input type=\"text\" name='f_description' value='" . $port['description'] . "' size=38>"; ?>
            </div>
            <div class="field">
                <?php print "<label for='f_target_port'>" . WEB_device_port_uplink_device . "</label>";
                print_device_port_select($db_link, 'f_target_port', $device_id, $port['target_port_id']); ?>
            </div>
            <div class="field">
                <?php print "<input type=\"submit\" name='editport' value='" . WEB_btn_save . "'>"; ?>
            </div>
        </div>
    </form>

<?php
    require_once($_SERVER['DOCUMENT_ROOT'] . "/inc/footer.php");
?>
