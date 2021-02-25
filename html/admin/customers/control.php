<?php

require_once ($_SERVER['DOCUMENT_ROOT']."/inc/auth.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/languages/" . $language . ".php");

if (isset($_POST["recheck_ip"]) and is_option($db_link, 37)) {
    $run_cmd = get_option($db_link, 37);
    shell_exec("sudo $run_cmd >/dev/null 2>/dev/null &");
    LOG_INFO($db_link, "Run command: $run_cmd");
    header("Location: " . $_SERVER["REQUEST_URI"]);
}

if (isset($_POST["refresh_dhcp"]) and is_option($db_link, 38)) {
    $run_cmd = get_option($db_link, 38);
    shell_exec("sudo $run_cmd >/dev/null 2>/dev/null &");
    LOG_INFO($db_link, "Run command: $run_cmd");
    header("Location: " . $_SERVER["REQUEST_URI"]);
}

if (isset($_POST["refresh_nagios"]) and is_option($db_link, 40)) {
    $run_cmd = get_option($db_link, 40);
    shell_exec("sudo $run_cmd >/dev/null 2>/dev/null &");
    LOG_INFO($db_link, "Run command: $run_cmd");
    header("Location: " . $_SERVER["REQUEST_URI"]);
}

if (isset($_POST["refresh_dns"]) and is_option($db_link, 39)) {
    $run_cmd = get_option($db_link, 39);
    shell_exec("sudo $run_cmd >/dev/null 2>/dev/null &");
    LOG_INFO($db_link, "Run command: $run_cmd");
    header("Location: " . $_SERVER["REQUEST_URI"]);
}

if (isset($_POST["discovery"]) and is_option($db_link, 41)) {
    $run_cmd = get_option($db_link, 41);
    shell_exec("sudo $run_cmd >/dev/null 2>/dev/null &");
    LOG_DEBUG($db_link, "Run command: $run_cmd");
    header("Location: " . $_SERVER["REQUEST_URI"]);
}

if (isset($_POST["discovery2"]) and is_option($db_link, 41)) {
    $run_cmd = get_option($db_link, 41);
    shell_exec("sudo $run_cmd 1 >/dev/null 2>/dev/null &");
    LOG_INFO($db_link, "Run command: $run_cmd 1");
    header("Location: " . $_SERVER["REQUEST_URI"]);
}

if (isset($_POST["save_traf_all"]) and get_option($db_link, 23)) {
    run_sql($db_link, 'Update User_auth set save_traf=1 where deleted=0');
    LOG_INFO($db_link, "Enable save traffic for all!");
    header("Location: " . $_SERVER["REQUEST_URI"]);
}

if (isset($_POST["not_save_traf_all"]) and get_option($db_link, 23)) {
    run_sql($db_link, 'Update User_auth set save_traf=0 where deleted=0');
    LOG_INFO($db_link, "Disable save traffic for all!");
    header("Location: " . $_SERVER["REQUEST_URI"]);
}

if (isset($_POST["s_remove"])) {
    $s_id = $_POST["s_id"];
    while (list ($key, $val) = @each($s_id)) {
        if (isset($val)) {
            LOG_INFO($db_link, "Remove subnet id: $val");
            delete_record($db_link, "subnets", "id=" . $val);
        }
    }
    header("Location: " . $_SERVER["REQUEST_URI"]);
}

unset($_POST);

require_once ($_SERVER['DOCUMENT_ROOT']."/inc/header.php");

print_control_submenu($page_url);

?>
<div id="cont">
<br>
<form name="def" action="control.php" method="post">
<table class="data">
<?php
        if (is_option($db_link, 37)) {
            print "<tr><td align=right>Управление доступом &nbsp<input type=submit name='recheck_ip' value='Обновить'></td></tr>";
        }
        if (is_option($db_link, 38)) {
            print "<tr><td align=right>Конфигурация dhcp &nbsp<input type=submit name='refresh_dhcp' value='Обновить' ></td></tr>";
        }
        if (is_option($db_link, 39)) {
            print "<tr><td align=right>Конфигурация dns &nbsp<input type=submit name='refresh_dns' value='Обновить'  ></td></tr>";
        }
        if (is_option($db_link, 40)) {
            print "<tr><td align=right>Reconfigure Nagios &nbsp<input type=submit name='refresh_nagios' value='Обновить'></td></tr>";
        }
        if (is_option($db_link, 41)) {
            print "<tr><td align=right>Сканирование сети &nbsp<input type=submit name='discovery' value='Выполнить'></td></tr>";
        }
        if (is_option($db_link, 41)) {
            print "<tr><td  align=right>Активное сканирование &nbsp<input type=submit name='discovery2' value='Выполнить'></td></tr>";
        }
        if (get_option($db_link, 23)) {
            print "<tr><td  align=right>Включить запись трафика у всех&nbsp<input type=submit name='save_traf_all' value='Выполнить'></td></tr>";
            print "<tr><td  align=right>Выключить запись трафика у всех&nbsp<input type=submit name='not_save_traf_all' value='Выполнить'></td></tr>";
        }
?>
<tr>
<td align=right><a href="ipcam.php">Управление портами</a></td>
</tr>
</table>
</form>
<?php
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/footer.php");
?>
