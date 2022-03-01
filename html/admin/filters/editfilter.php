<?php
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/auth.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/languages/" . $language . ".php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/idfilter.php");

if (isset($_POST["editfilter"])) {
    $new['name'] = $_POST["f_name"];
    $new['dst'] = $_POST["f_dst"];
    $new['proto'] = $_POST["f_proto"];
    $new['dstport'] = str_replace(':', '-', $_POST["f_dstport"]);
    $new['srcport'] = str_replace(':', '-', $_POST["f_srcport"]);
    $new['action'] = $_POST["f_action"] * 1;
    update_record($db_link, "Filter_list", "id='$id'", $new);
    unset($_POST);
    header("location: index.php");
    exit;
}

unset($_POST);

require_once ($_SERVER['DOCUMENT_ROOT']."/inc/header.php");

$filter = get_record($db_link, 'Filter_list','id='.$id);

print "<div id=cont>";
print "<form name=def action=editfilter.php?id=$id method=post>";
print "<input type=hidden name=id value=$id>";

if (isset($filter['type']) and $filter['type'] == 0) {
    print "<table class=\"data\" cellspacing=\"0\" cellpadding=\"4\">";
    print "<tr><td><b>Имя</b></td>";
    print "<td ><b>Протокол</b></td>";
    print "<td ><b>Адрес назначения</b></td>";
    print "<td ><b>Порт назначения</b></td>";
    print "<td ><b>Порт источник</b></td>";
    print "<td ><b>Действие</b></td>";

    print "</tr><td align=left><input type=text name=f_name value=".$filter['name']."></td>";
    print "<td ><input type=text name=f_proto value=".$filter['proto']."></td>";
    print "<td ><input type=text name=f_dst value=".$filter['dst']."></td>";
    print "<td ><input type=text name=f_dstport value=".$filter['dstport']."></td>";
    print "<td ><input type=text name=f_srcport value=".$filter['srcport']."></td>";
    print "<td>";
    print_action_select('f_action', $filter['action']);
    print "</td></tr>";
    print "<tr><td colspan=2><input type=submit name=editfilter value=Сохранить></td>";
    print "</tr></table>";
} else {
    print "<table class=\"data\" cellspacing=\"0\" cellpadding=\"4\">";
    print "<tr><td ><b>Имя</b></td>";
    print "<td ><b>Адрес назначения</b></td>";
    print "<td ><b>Действие</b></td></tr>";

    print "<td align=left><input type=text name=f_name value=".$filter['name']."></td>";
    print "<td ><input type=text name=f_dst value=".$filter['dst']."></td>";
    print_action_select('f_action', $filter['action']);
    print "<tr><td colspan=2><input type=submit name=editfilter value=Сохранить></td>";
    print "</tr></table>";
}
print "</form>";
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/footer.php");
?>
