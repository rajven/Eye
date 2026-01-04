<?php
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/auth.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/languages/" . HTML_LANG . ".php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/idfilter.php");

if (isset($_POST["editfilter"])) {
    $new['name'] = $_POST["f_name"];
    $new['dst'] = $_POST["f_dst"];
    $new['proto'] = $_POST["f_proto"];
    $new['dstport'] = str_replace(':', '-', $_POST["f_dstport"]);
    $new['srcport'] = str_replace(':', '-', $_POST["f_srcport"]);
    $new['comment'] = $_POST["f_comment"];
    update_record($db_link, "filter_list", "id='$id'", $new);
    unset($_POST);
    header("Location: " . $_SERVER["REQUEST_URI"]);
    exit;
}

unset($_POST);

require_once ($_SERVER['DOCUMENT_ROOT']."/inc/header.php");

$filter = get_record($db_link, 'filter_list','id='.$id);

print_filters_submenu($page_url);

print "<div id=cont>";

print "<br> <b>".WEB_title_filter."</b> <br>";

print "<form name=def action='editfilter.php?id=".$id."' method=post>";
print "<input type=hidden name=id value=$id>";

if (isset($filter['type']) and $filter['type'] == 0) {
    print "<table class=\"data\" cellspacing=\"0\" cellpadding=\"4\">";
    print "<tr><td><b>".WEB_cell_forename."</b></td>";
    print "<td colspan=2><b>".WEB_cell_comment."</b></td>";
    print "</tr>";
    print "<tr>";
    print "<td align=left><input type=text name=f_name value='".$filter['name']."'></td>";
    print "<td colspan=2><input type=text name=f_comment value='".$filter['comment']."'></td>";
    print "<td><input type=submit name=editfilter value='".WEB_btn_save."'></td>";
    print "</tr>";
    print "<tr>";
    print "<td ><b>".WEB_traffic_proto."</b></td>";
    print "<td ><b>".WEB_traffic_dest_address."</b></td>";
    print "<td ><b>".WEB_traffic_dst_port."</b></td>";
    print "<td ><b>".WEB_traffic_src_port."</b></td>";
    print "</tr>";
    print "<tr>";
    print "<td ><input type=text name=f_proto value='".$filter['proto']."'></td>";
    print "<td ><input type=text name=f_dst value='".$filter['dst']."'></td>";
    print "<td ><input type=text name=f_dstport value='".$filter['dstport']."'></td>";
    print "<td ><input type=text name=f_srcport value='".$filter['srcport']."'></td>";
    print "</tr>";
    print "</table>";
} else {
    print "<table class=\"data\" cellspacing=\"0\" cellpadding=\"4\">";
    print "<tr><td><b>".WEB_cell_forename."</b></td>";
    print "<td><b>".WEB_cell_comment."</b></td>";
    print "<td><input type=submit name=editfilter value=".WEB_btn_save."></td>";
    print "</tr>";
    print "<tr>";
    print "<td align=left><input type=text name=f_name value='".$filter['name']."'></td>";
    print "<td ><input type=text name=f_comment value='".$filter['comment']."'></td>";
    print "<td ><input type=text name=f_dst value='".$filter['dst']."'></td>";
    print "</tr>";
    print "</table>";
}
print "</form>";
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/footer.php");
