<?php

$default_displayed=25;
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/auth.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/languages/" . HTML_LANG . ".php");

if (isset($_POST['save'])) {
    $saved = array();
    //button save
    $len = is_array($_POST['save']) ? count($_POST['save']) : 0;
    for ($i = 0; $i < $len; $i ++) {
        $save_id = intval($_POST['save'][$i]);
        if ($save_id == 0) { continue;  }
        if ($save_id<10000) { continue; }
        array_push($saved,$save_id);
        }
    //select box
    $len = is_array($_POST['f_id']) ? count($_POST['f_id']) : 0;
    if ($len>0) {
        for ($i = 0; $i < $len; $i ++) {
            $save_id = intval($_POST['f_id'][$i]);
            if ($save_id == 0) { continue; }
            if ($save_id<10000) { continue; }
            if (!in_array($save_id, $saved)) { array_push($saved,$save_id); }
            }
        }
    //save changes
    $len = is_array($saved) ? count($saved) : 0;
    for ($i = 0; $i < $len; $i ++) {
        $save_id = intval($saved[$i]);
        if ($save_id == 0) { continue;  }
        if ($save_id<10000) { continue; }
        $len_all = is_array($_POST['id']) ? count($_POST['id']) : 0;
        for ($j = 0; $j < $len_all; $j ++) {
            if (intval($_POST['id'][$j]) != $save_id) { continue; }
            $new['name'] = $_POST['f_name'][$j];
            update_record($db_link, "vendors", "id='{$save_id}'", $new);
            }
        }
    header("Location: " . $_SERVER["REQUEST_URI"]);
    exit;
    }

if (isset($_POST["create"])) {
    $vendor_name = $_POST["new_vendor"];
    if (isset($vendor_name)) {
	$max_record = get_record_sql($db_link,"SELECT MAX(id) as max_id FROM vendors");
	if (!isset($max_record) or $max_record['max_id']<10000) { $next_id = 10000; } else { $next_id = $max_record['max_id'] + 1; }
        $new['id'] = $next_id;
        $new['name'] = $vendor_name;
        insert_record($db_link, "vendors", $new);
        }
    header("Location: " . $_SERVER["REQUEST_URI"]);
    exit;
    }

unset($_POST);
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/header.php");
print_device_submenu($page_url);

?>
<div id="cont">
<form name="def" action="devvendors.php" method="post">

<table class="data">
<tr>
<td><b>Список вендоров</b></td>
<td>Отображать:<?php print_row_at_pages('rows',$displayed); ?></td>
<td><input type="submit" name="OK" value="Показать"></td>
</tr>
</table>

<?php
$countSQL="SELECT Count(*) FROM vendors";
$res = mysqli_query($db_link, $countSQL);
$count_records = mysqli_fetch_array($res);
$total=ceil($count_records[0]/$displayed);
if ($page>$total) { $page=$total; }
if ($page<1) { $page=1; }
$start = ($page * $displayed) - $displayed;
print_navigation($page_url,$page,$displayed,$count_records[0],$total);

?>
<table class="data">
<tr align="center">
<td><input type="checkbox" onClick="checkAll(this.checked);"></td>
<td><b>Id</b></td>
<td><b>Производитель</b></td>
<td><input type="submit" name='save' value="Сохранить"></td>
</tr>
<?php
$t_ou = get_records_sql($db_link,"SELECT * FROM vendors ORDER BY name LIMIT $start,$displayed");
foreach ($t_ou as $row) {
    print "<tr align=center>\n";
    print "<td class=\"data\" style='padding:0'><input type=checkbox name=f_id[] value='{$row['id']}'></td>\n";
    print "<td class=\"data\"><input type=\"hidden\" name='id[]' value='{$row['id']}'>{$row['id']}</td>\n";
    print "<td class=\"data\"><input type=\"text\" name='f_name[]' value='{$row['name']}'></td>\n";
    print "<td class=\"data\"></td>\n";
    print "</tr>\n";
}
?>
</table>
<table>
<tr>
<td><input type=text name=new_vendor value="Unknown"></td>
<td><input type="submit" name="create" value="Добавить"></td>
<td align="right"></td>
</tr>
</table>
</form>
<?php
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/footer.php");
?>
