<?php
define("CONFIG", 1);
define("SQL", 1);
require_once ($_SERVER['DOCUMENT_ROOT']."/cfg/config.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/sql.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/common.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/languages/russian.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/sql.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/header_public.php");

list ($rhour, $rday, $rmonth, $ryear) = explode(" ", date("H j n Y", time()));

if (! isset($auth_ip)) {
    $auth_ip = get_user_ip();
}
if (! isset($auth_ip)) {
    print "Error detecting user!!!";
}

$ip_aton = ip2long($auth_ip);
if (! $ip_aton) {
    $ip_aton = 0;
}
$sSQL = "SELECT U.login,A.enabled,A.user_id FROM User_list U,User_auth A WHERE U.id=A.user_id and (A.ip_int='$ip_aton' or A.ip='$auth_ip' and A.deleted=0) Limit 1";
list ($login, $enabled, $id) = mysqli_fetch_array(mysqli_query($db_link, $sSQL));

if (! isset($id) or $id < 1) {
    $msg_error = "<b>Адрес $auth_ip в списках не значится!</b><br>";
}
;
if (!$enabled) { $msg_error="<b> (Доступ запрещён администратором!)</b><br>\n"; }

?>
<div id="cont">
<table>
<tr>
<td>
<table class="data">
<td align="center">
<?php
$sSQL = "SELECT month_quota,day_quota FROM User_list WHERE User_list.id=$id";
list ($limit, $limit1) = mysqli_fetch_array(mysqli_query($db_link, $sSQL));
$limit = $limit * $KB * $KB;
$limit1 = $limit1 * $KB * $KB;

$sSQL = "SELECT SUM(tin),SUM(tout) FROM
    (select auth_id,SUM(byte_in) as tin, SUM(byte_out) as tout from User_stats where ((YEAR(`timestamp`)=2017) and (MONTH(`timestamp`)=10)) GROUP by auth_id) as V,
    User_auth, User_list WHERE (V.auth_id=User_auth.id) and (User_auth.user_id=User_list.id) and (User_list.id=$id) GROUP by Login Order by Login";

$useritog = mysqli_query($db_link, $sSQL);

list ($uin, $uout) = mysqli_fetch_array($useritog);
if (! isset($login) or ! isset($auth_ip)) {
    print "<tr class='data'><div id='msg'><b>$msg_error</div></b></tr><br>\n";
}
;

print "<tr class='data'><div id='msg'><b>Пользователь: $login IP-адрес: $auth_ip</div></b></tr><br>\n";
print "<tr class='data'><div id='msg2'>Текущий трафик</div></tr>\n";
print "<tr class='data'><div id='msg2'>за месяц " . fbytes($uin) . " - лимит " . fbytes($limit) . "</div></tr>\n";

$useritog = mysqli_query($db_link, "SELECT SUM(tin),SUM(tout) FROM (select auth_id,SUM(byte_in) as tin,
                SUM(byte_out) as tout from User_stats
                where ((YEAR(`timestamp`)=$ryear) and (MONTH(`timestamp`)=$rmonth) and (DAY(`timestamp`)=$rday))
                GROUP by auth_id) as V, User_auth, User_list
                WHERE (V.auth_id=User_auth.id) and (User_auth.user_id=User_list.id) and (User_list.id=$id)
                GROUP by Login Order by Login");

list ($uin, $uout) = mysqli_fetch_array($useritog);
print "<tr class='data'><div id='msg2'>за день " . fbytes($uin) . " - лимит " . fbytes($limit1) . "</div></tr>\n";
// print "<tr class='data'><a href=/public/userdaydetail.php><div id='msg2'>Подробно за сегодня</div></a></tr>\n";
print "<tr class='data'><br></tr>\n";
print "</td>\n";

require_once ($_SERVER['DOCUMENT_ROOT']."/inc/footer.php");
?>