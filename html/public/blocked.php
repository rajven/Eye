<?php
define("CONFIG", 1);
define("SQL", 1);
require_once ($_SERVER['DOCUMENT_ROOT']."/cfg/config.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/sql.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/common.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/languages/russian.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/header_public.php");

if (! isset($auth_ip)) { $auth_ip = get_user_ip(); }
if (! isset($auth_ip)) { print "Error detecting user!!!"; }

$start = mktime(0, 0, 0, date("m"), 1, date("Y"));
$date1m = strftime('%Y-%m-%d', $start);
$stop = mktime(0, 0, 0, date("m")+1, 1, date("Y"));
$date2m = strftime('%Y-%m-%d', $stop);

$start = mktime(0, 0, 0, date("m"), date("d"), date("Y"));
$date1 = strftime('%Y-%m-%d', $start);
$stop = mktime(0, 0, 0, date("m"), date("d")+1, date("Y"));
$date2 = strftime('%Y-%m-%d', $stop);

?>

<div id="cont">

<?php
$ip_aton = ip2long($auth_ip);
if (! $ip_aton) { $ip_aton = 0; }

$sSQL = "SELECT * FROM User_auth WHERE ip_int='".$ip_aton."' and deleted = 0";
$auth = get_record_sql($db_link,$sSQL);
if (! isset($auth) or empty($auth)) { print "<font color=red><b>Адрес $auth_ip в списках не значится!</b><br></font>"; die; }

$id = $auth['id'];
$user_id = $auth['user_id'];

$uSQL = "SELECT * FROM User_list WHERE id='".$user_id."'";
$user = get_record_sql($db_link,$uSQL);

if (! isset($user) or empty($user)) { print "<font color=red><b>Адрес $auth_ip принадлежит несуществующему юзеру. Вероятно запись удалена.</b><br></font>"; die; }

if (empty($user['month_quota'])) { $user['month_quota']=0; }
if (empty($user['day_quota'])) { $user['day_quota']=0; }
if (empty($auth['month_quota'])) { $auth['month_quota']=0; }
if (empty($auth['day_quota'])) { $auth['day_quota']=0; }

$user['month_quota'] = $user['month_quota'] * $KB * $KB;
$user['day_quota'] = $user['day_quota'] * $KB * $KB;
$auth['month_quota'] = $auth['month_quota'] * $KB * $KB;
$auth['day_quota'] = $auth['day_quota'] * $KB * $KB;

$day =  GetNowDayString();
$month = strftime('%m',time());
$year = strftime('%Y',time());

?>
<table>
<tr>
<td><b>Сейчас</b></td><td><?php print GetNowTimeString(); ?></td></tr>
<tr>
<td><b>Login</b></td> <td><?php print $user['login']; ?></td>
</tr><tr>
<td><b>ФИО</b></td> <td><?php print $user['fio']; ?></td>
</tr><tr>
<td> Интернет (логин) </td> <td><b><?php 
if ($user['enabled'] and !$user['blocked']) { print "Включен"; }
if (!$user['enabled']) { print "<font color=red>Запрещён</font> &nbsp"; }
if ($user['blocked']) { print "<font colot=red>Блок по трафику</font>"; }
?></b>
</td></tr><tr>
<td> Интернет (этот IP) </td> <td><b><?php 
if ($user['enabled'] and !$user['blocked'] and !$auth['blocked'] and $auth['enabled']) { print "Включен"; }
if (!$user['enabled'] or !$auth['enabled']) { print "<font color=red>Запрещён</font> &nbsp"; }
if ($auth['blocked']) { print "<font color=red>Блок по трафику</font>"; }
?></b>
</td>
</tr>
<tr><td>Фильтр</td><td><?php print get_group($db_link, $auth["filter_group_id"]); ?> </td></tr>
<tr><td>Шейпер</td><td><?php print get_queue($db_link, $auth["queue_id"]); ?></td></tr>
<tr><td> Квота на логин, месяц <td><td><?php print fbytes($user['month_quota']); ?> </td></tr>
<tr><td> Квота на логин, день <td><td><?php print fbytes($user['day_quota']); ?> </td></tr>
<tr><td> Лимит на ip, месяц <td><td><?php print fbytes($auth['month_quota']); ?> </td></tr>
<tr><td> Лимит на ip, день <td><td><?php print fbytes($auth['day_quota']); ?> </td></tr>

<?php
$auth_list = get_records_sql($db_link,"SELECT id FROM User_auth WHERE user_id='".$user_id."' AND deleted=0");

####### day
$sSQL = "SELECT SUM(byte_in) as tin, SUM(byte_out) as tout FROM User_stats WHERE `timestamp`>='".$date1."' AND `timestamp`<'".$date2."' AND auth_id='".$id."'";
$day_auth_itog = get_record_sql($db_link,$sSQL);

$day_auth_sum_in=0;
$day_auth_sum_in=0;

if (!empty($day_auth_itog)) {
    if (empty($day_auth_itog['tin'])) { $day_auth_itog['tin']=0; }
    if (empty($day_auth_itog['tout'])) { $day_auth_itog['tout']=0; }
    $day_auth_sum_in=$day_auth_itog['tin'];
    $day_auth_sum_out=$day_auth_itog['tout'];
    }

$day_user_sum_in=0;
$day_user_sum_out=0;

if (!empty($auth_list)) {
    foreach ($auth_list as $row) {
        $auth_itog2 = get_record_sql($db_link,"SELECT SUM(byte_in) as tin, SUM(byte_out) as tout FROM User_stats WHERE `timestamp`>='".$date1."' AND `timestamp`<'".$date2."' AND auth_id='".$row['id']."'");
        if (!empty($auth_itog2)) { 
                if (empty($auth_itog2['tin'])) { $auth_itog2['tin']=0; }
                if (empty($auth_itog2['tout'])) { $auth_itog2['tout']=0; }
                $day_user_sum_in+=$auth_itog2['tin'];
                $day_user_sum_out+=$auth_itog2['tout'];
                }
        }
    }

#### month
$sSQL = "SELECT SUM(byte_in) as tin, SUM(byte_out) as tout FROM User_stats WHERE `timestamp`>='".$date1m."' AND `timestamp`<'".$date2m."' AND auth_id='".$id."'";
$month_auth_itog = get_record_sql($db_link,$sSQL);

$month_auth_sum_in=0;
$month_auth_sum_in=0;

if (!empty($month_auth_itog)) {
    if (empty($month_auth_itog['tin'])) { $month_auth_itog['tin']=0; }
    if (empty($month_auth_itog['tout'])) { $month_auth_itog['tout']=0; }
    $month_auth_sum_in=$month_auth_itog['tin'];
    $month_auth_sum_out=$month_auth_itog['tout'];
    }

$month_user_sum_in=0;
$month_user_sum_out=0;

if (!empty($auth_list)) {
    foreach ($auth_list as $row) {
        $auth_itog2 = get_record_sql($db_link,"SELECT SUM(byte_in) as tin, SUM(byte_out) as tout FROM User_stats WHERE `timestamp`>='".$date1m."' AND `timestamp`<'".$date2m."' AND auth_id='".$row['id']."'");
        if (!empty($auth_itog2)) {
                if (empty($auth_itog2['tin'])) { $auth_itog2['tin']=0; }
                if (empty($auth_itog2['tout'])) { $auth_itog2['tout']=0; }
                $month_user_sum_in+=$auth_itog2['tin'];
                $month_user_sum_out+=$auth_itog2['tout'];
                }
        }
    }

#### print
print "<tr class='data'><td><b>Текущий трафик на IP</b></td><td>$auth_ip</td></tr>\n";
print "<tr class='data'><td>за день in/out </td><td>" . fbytes($day_auth_sum_in)." / ".fbytes($day_auth_sum_out). "</td></tr>\n";
print "<tr class='data'><td>за месяц in/out </td><td>" . fbytes($month_auth_sum_in)." / ".fbytes($month_auth_sum_out). "</td></tr>\n";
print "<tr class='data'><td><b>Текущий трафик на логин</b></td><td>".$user['login']."</td></tr>\n";
print "<tr class='data'><td>за день in/out </td><td>" . fbytes($day_user_sum_in)." / ".fbytes($day_user_sum_out). "</td></tr>\n";
print "<tr class='data'><td>за месяц in/out </td><td>" . fbytes($month_user_sum_in)." / ".fbytes($month_user_sum_out). "</td></tr>\n";
print "</table>\n";

require_once ($_SERVER['DOCUMENT_ROOT']."/inc/footer.php");
?>
