<?php

$default_displayed=25;
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/auth.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/languages/" . HTML_LANG . ".php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/vendorfilter.php");

if (isset($_POST['save'])) {
    $saved = array();
    //button save
    $len = is_array($_POST['save']) ? count($_POST['save']) : 0;
    for ($i = 0; $i < $len; $i ++) {
        $save_id = intval($_POST['save'][$i]);
        if ($save_id == 0) { continue;  }
        array_push($saved,$save_id);
        }
    //select box
    $len = is_array($_POST['f_id']) ? count($_POST['f_id']) : 0;
    if ($len>0) {
        for ($i = 0; $i < $len; $i ++) {
            $save_id = intval($_POST['f_id'][$i]);
            if ($save_id == 0) { continue; }
            if (!in_array($save_id, $saved)) { array_push($saved,$save_id); }
            }
        }
    //save changes
    $len = is_array($saved) ? count($saved) : 0;
    for ($i = 0; $i < $len; $i ++) {
        $save_id = intval($saved[$i]);
        if ($save_id == 0) { continue;  }
        $len_all = is_array($_POST['r_id']) ? count($_POST['r_id']) : 0;
        for ($j = 0; $j < $len_all; $j ++) {
            if (intval($_POST['r_id'][$j]) != $save_id) { continue; }
            $new = NULL;
            if ($save_id>=10000) {
                $new['vendor_id'] = $_POST['f_vendor'][$j];
	            $new['model_name'] = $_POST['f_name'][$j];
	            $new['poe_in'] = $_POST['f_poe_in'][$j];
	            $new['poe_out'] = $_POST['f_poe_out'][$j];
	        } else {
	            $new['poe_in'] = $_POST['f_poe_in'][$j];
	            $new['poe_out'] = $_POST['f_poe_out'][$j];      
            }
            if (!isset($new['poe_in']) OR empty($new['poe_in'])) { $new['poe_in'] = 0; }
            if (!isset($new['poe_out']) OR empty($new['poe_out'])) { $new['poe_out'] = 0; }
            $new['nagios_template'] = $_POST['f_nagios'][$j];
            update_record($db_link, "device_models", "id='{$save_id}'", $new);
            }
        }
    header("Location: " . $_SERVER["REQUEST_URI"]);
    exit;
    }

if (isset($_POST['remove'])) {
        $saved = array();
        //button save
        $len = is_array($_POST['save']) ? count($_POST['save']) : 0;
        for ($i = 0; $i < $len; $i ++) {
            $save_id = intval($_POST['save'][$i]);
            if ($save_id == 0) { continue;  }
            array_push($saved,$save_id);
            }
        //select box
        $len = is_array($_POST['f_id']) ? count($_POST['f_id']) : 0;
        if ($len>0) {
            for ($i = 0; $i < $len; $i ++) {
                $save_id = intval($_POST['f_id'][$i]);
                if ($save_id == 0) { continue; }
                if (!in_array($save_id, $saved)) { array_push($saved,$save_id); }
                }
            }
        //save changes
        $len = is_array($saved) ? count($saved) : 0;
        for ($i = 0; $i < $len; $i ++) {
            $save_id = intval($saved[$i]);
            if ($save_id == 0) { continue;  }
            $len_all = is_array($_POST['r_id']) ? count($_POST['r_id']) : 0;
            for ($j = 0; $j < $len_all; $j ++) {
                if (intval($_POST['r_id'][$j]) != $save_id) { continue; }
                if ($save_id>=10000) { 
                    delete_record($db_link, "device_models", "id='{$save_id}'");
                    run_sql($db_link,"UPDATE devices set device_model_id=NULL WHERE device_model_id=".$save_id);
                    }
                }
            }
        header("Location: " . $_SERVER["REQUEST_URI"]);
        exit;
        }

if (isset($_POST["create"])) {
    $model_name = $_POST["new_model"];
    if (isset($model_name)) {
	$max_record = get_record_sql($db_link,"SELECT MAX(id) as max_id FROM device_models");
	if (!isset($max_record) or $max_record['max_id']<10000) { $next_id = 10000; } else { $next_id = $max_record['max_id'] + 1; }
        $new['vendor_id']=1;
        $new['id'] = $next_id;
        if (isset($f_vendor_select) and $f_vendor_select>1) { $new['vendor_id']=$f_vendor_select; }
        $new['model_name'] = $model_name;
        insert_record($db_link, "device_models", $new);
        }
    header("Location: " . $_SERVER["REQUEST_URI"]);
    exit;
    }

unset($_POST);
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/header.php");
print_control_submenu($page_url);

?>
<div id="cont">
<br>
<form name="def" action="devmodels.php" method="post">

<table class="data">
<tr>
<td><b><?php echo WEB_list_models; ?></b></td>
<td><?php print_vendor_select($db_link,'vendor_select',$f_vendor_select); ?></td>
<td><?php print WEB_rows_at_page."&nbsp:";print_row_at_pages('rows',$displayed); ?></td>
<td><input id='apply' name='apply' type="submit" name="OK" value="<?php print WEB_btn_show; ?>"></td>
</tr>
</table>

<?php
$v_filter='';
if (!empty($f_vendor_select)) { $v_filter = "WHERE vendor_id=".$f_vendor_select; }

$countSQL="SELECT Count(*) FROM device_models $v_filter";
$res = mysqli_query($db_link, $countSQL);
$count_records = mysqli_fetch_array($res);
$total=ceil($count_records[0]/$displayed);
if ($page>$total) { $page=$total; }
if ($page<1) { $page=1; }
$start = ($page * $displayed) - $displayed;
print_navigation($page_url,$page,$displayed,$count_records[0],$total);
?>
<br>
<table class="data">
<tr align="center">
<td><input type="checkbox" onClick="checkAll(this.checked);"></td>
<td><b>Id</b></td>
<td><b><?php echo WEB_model_vendor; ?></b></td>
<td><b><?php echo WEB_cell_name; ?></b></td>
<td><b><?php echo WEB_cell_poe_in; ?></b></td>
<td><b><?php echo WEB_cell_poe_out; ?></b></td>
<td><b><?php echo WEB_nagios_template; ?></b></td>
<td><input id='save' type="submit" name='save' value="<?php echo WEB_btn_save; ?>"></td>
<td><input id='remove' type="submit" name='remove' value="<?php echo WEB_btn_delete; ?>"></td>
</tr>
<?php
$t_ou = get_records_sql($db_link,'SELECT * FROM device_models '.$v_filter." ORDER BY vendor_id, model_name LIMIT $start,$displayed");
foreach ($t_ou as $row) {
    print "<tr align=center>\n";
    print "<td class=\"data\" style='padding:0'><input type=checkbox name=f_id[] value='{$row['id']}'></td>\n";
    print "<td class=\"data\"><input type=\"hidden\" name='r_id[]' value='{$row['id']}'>{$row['id']}</td>\n";
    print "<td class=\"data\" width=150>"; print_vendor_set($db_link,'f_vendor[]',$row['vendor_id']); print "</td>\n";
    print "<td class=\"data\"><input type=\"text\" name='f_name[]' value='{$row['model_name']}'></td>\n";
    print "<td class=\"data\">";print_qa_select("f_poe_in[]", $row['poe_in']); print "</td>\n";
    print "<td class=\"data\">";print_qa_select("f_poe_out[]", $row['poe_out']); print "</td>\n";
    print "<td class=\"data\"><input type=\"text\" name='f_nagios[]' value='{$row['nagios_template']}'></td>\n";
    print "<td class=\"data\"></td>\n";
    print "<td class=\"data\"></td>\n";
    print "</tr>\n";
}
?>
</table>
<div><input type=text name=new_model value="Unknown">
<input type="submit" name="create" value="<?php echo WEB_btn_add; ?>">
</div>
</form>

<?php
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/footer.php");
?>
