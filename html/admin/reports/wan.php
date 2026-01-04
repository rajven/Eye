<?php
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/auth.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/languages/" . HTML_LANG . ".php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/header.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/datetimefilter.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/gatefilter.php");

print_reports_submenu($page_url);

function print_gateway_statistics($db,$device_id,$device_name,$date1,$date2) {

$start_time = new DateTimeImmutable($date1);
$stop_time = new DateTimeImmutable($date2);
$interval = $stop_time->diff($start_time,true);

$delta = $interval->format("%a");

$l3_interfaces = get_wan_interfaces($db,$device_id);

#for day - show hour statistics
$dt_template = '%Y-%m-%d %H:00:00';
if ($delta == 1) { $dt_template = '%Y-%m-%d %H:00:00'; } 
    elseif ($delta >1 and $delta<=31) { $dt_template = '%Y-%m-%d'; }
        elseif ($delta >31) { $dt_template = '%Y-%m'; }


$global_int_in = 0;
$global_int_out = 0;
$global_int_f_in = 0;
$global_int_f_out = 0;

print "<tr ><td class=\"info\" colspan=5><b>".$device_name."</b></td></tr>\n";

foreach ($l3_interfaces as $row) {
    if (!empty($row['description'])) {
        print "<tr ><td class=\"data\" colspan=5><b>".$row['name']." (".$row['description'].")</b></td></tr>\n";
        } else {
        print "<tr ><td class=\"data\" colspan=5><b>".$row['name']."</b></td></tr>\n";
        }
    print "<tr class=\"info\">\n";
    print "<td >".WEB_date."</td>\n";
    print "<td >".WEB_title_input."</td>\n";
    print "<td >".WEB_title_output."</td>\n";
    print "<td >".WEB_title_forward_input."</td>\n";
    print "<td >".WEB_title_forward_output."</td>\n";
    print "</tr>\n";

    $trafSQL="SELECT DATE_FORMAT(ts, '".$dt_template."'  ) AS dt,SUM(bytes_in) as byte_in,SUM(bytes_out) as byte_out, SUM(forward_in) as byte_f_in,SUM(forward_out) as byte_f_out FROM wan_stats ";
    $trafSQL .=" WHERE router_id='".$device_id."' AND interface_id='".$row['snmpin']."' AND time>='$date1' AND time<'$date2'";
    $trafSQL .=" GROUP BY DATE_FORMAT(ts, '".$dt_template."' ) ORDER BY dt;";

    $int_statistics = get_records_sql($db,$trafSQL);

    $int_in = 0;
    $int_out = 0;
    $int_f_in = 0;
    $int_f_out = 0;
    foreach ($int_statistics as $stat) {
        print "<tr align=center class=\"tr1\" onmouseover=\"className='tr2'\" onmouseout=\"className='tr1'\">\n";
        print "<td class=\"data\">".$stat['dt']."</td>\n";
        print "<td class=\"data\">".fbytes($stat['byte_in'])."</td>\n";
        print "<td class=\"data\">".fbytes($stat['byte_out'])."</td>\n";
        print "<td class=\"data\">".fbytes($stat['byte_f_in'])."</td>\n";
        print "<td class=\"data\">".fbytes($stat['byte_f_out'])."</td>\n";
        print "</tr>\n";
        $int_in +=$stat['byte_in'];
        $int_out +=$stat['byte_out'];
        $int_f_in +=$stat['byte_f_in'];
        $int_f_out +=$stat['byte_f_out'];
        }
    print "<tr align=center class=\"tr1\" onmouseover=\"className='tr2'\" onmouseout=\"className='tr1'\">\n";
    print "<td class=\"data\"><b>".WEB_title_itog."</b></td>\n";
    print "<td class=\"data\">".fbytes($int_in)."</td>\n";
    print "<td class=\"data\">".fbytes($int_out)."</td>\n";
    print "<td class=\"data\">".fbytes($int_f_in)."</td>\n";
    print "<td class=\"data\">".fbytes($int_f_out)."</td>\n";
    print "</tr>\n";

    $global_int_in += $int_in;
    $global_int_out += $int_out;
    $global_int_f_in += $int_f_in;
    $global_int_f_out += $int_f_out;
    }

print "<tr align=center class=\"tr1\" onmouseover=\"className='tr2'\" onmouseout=\"className='tr1'\">\n";
print "<td class=\"data\" colspan=5><b>".WEB_title_itog."</b></td></tr>\n";
print "<tr align=center class=\"tr1\" onmouseover=\"className='tr2'\" onmouseout=\"className='tr1'\">\n";
print "<td class=\"data\"></td>\n";
print "<td class=\"data\">".fbytes($global_int_in)."</td>\n";
print "<td class=\"data\">".fbytes($global_int_out)."</td>\n";
print "<td class=\"data\">".fbytes($global_int_f_in)."</td>\n";
print "<td class=\"data\">".fbytes($global_int_f_out)."</td>\n";
print "</tr>\n";

}

?>
<div id="cont">

<form action="wan.php" method="post">
<?php print_date_fields($date1,$date2,$date_shift); ?>
<?php echo WEB_cell_gateway; ?>:&nbsp<?php print_gateway_select($db_link, 'gateway', $rgateway); ?>
<input type="submit" value="<?php echo WEB_btn_show; ?>">
</form>

<br>
<br>
<table class="data">

<?php

if ($rgateway==0) {
    $gateways = get_gateways($db_link);
    foreach ($gateways as $key => $val) {
        print_gateway_statistics($db_link,$key,$val,$date1,$date2);
        }
    } else {
        $router = get_record_sql($db_link,"SELECT device_name FROM devices WHERE id='".$rgateway."'");
        print_gateway_statistics($db_link,$rgateway,$router['device_name'],$date1,$date2);
    }

?>

</table>

<?php
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/footer.php");
?>
