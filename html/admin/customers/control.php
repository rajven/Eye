<?php

require_once ($_SERVER['DOCUMENT_ROOT']."/inc/auth.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/languages/" . $language . ".php");

if (isset($_POST["recheck_ip"]) and is_option($db_link, 37)) {
    $run_cmd = get_option($db_link, 37);
    shell_exec("sudo $run_cmd >/dev/null 2>/dev/null &");
    LOG_INFO($db_link, "Run command: $run_cmd");
    header("Location: " . $_SERVER["REQUEST_URI"]);
    exit;
}

if (isset($_POST["refresh_dhcp"]) and is_option($db_link, 38)) {
    $run_cmd = get_option($db_link, 38);
    shell_exec("sudo $run_cmd >/dev/null 2>/dev/null &");
    LOG_INFO($db_link, "Run command: $run_cmd");
    header("Location: " . $_SERVER["REQUEST_URI"]);
    exit;
}

if (isset($_POST["refresh_nagios"]) and is_option($db_link, 40)) {
    $run_cmd = get_option($db_link, 40);
    shell_exec("sudo $run_cmd >/dev/null 2>/dev/null &");
    LOG_INFO($db_link, "Run command: $run_cmd");
    header("Location: " . $_SERVER["REQUEST_URI"]);
    exit;
}

if (isset($_POST["up_nagios"])) {
    run_sql($db_link,"UPDATE User_auth SET nagios_status='UP'");
    run_sql($db_link,"UPDATE devices SET nagios_status='UP'");
    header("Location: " . $_SERVER["REQUEST_URI"]);
    exit;
}

if (isset($_POST["refresh_dns"]) and is_option($db_link, 39)) {
    $run_cmd = get_option($db_link, 39);
    shell_exec("sudo $run_cmd >/dev/null 2>/dev/null &");
    LOG_INFO($db_link, "Run command: $run_cmd");
    header("Location: " . $_SERVER["REQUEST_URI"]);
    exit;
}

if (isset($_POST["discovery"]) and is_option($db_link, 41)) {
    $run_cmd = get_option($db_link, 41);
    shell_exec("sudo $run_cmd >/dev/null 2>/dev/null &");
    LOG_DEBUG($db_link, "Run command: $run_cmd");
    header("Location: " . $_SERVER["REQUEST_URI"]);
    exit;
}

if (isset($_POST["discovery2"]) and is_option($db_link, 41)) {
    $run_cmd = get_option($db_link, 41);
    shell_exec("sudo $run_cmd 1 >/dev/null 2>/dev/null &");
    LOG_INFO($db_link, "Run command: $run_cmd 1");
    header("Location: " . $_SERVER["REQUEST_URI"]);
    exit;
}

if (!empty($_POST["save_traf_all"]) and $_POST["save_traf_all"]) {
    run_sql($db_link, 'Update User_auth SET save_traf=1');
    LOG_INFO($db_link, "Enable save traffic for all!");
    header("Location: " . $_SERVER["REQUEST_URI"]);
    exit;
}

if (!empty($_POST["not_save_traf_all"]) and $_POST["not_save_traf_all"]) {
    run_sql($db_link, 'Update User_auth SET save_traf=0');
    LOG_INFO($db_link, "Disable save traffic for all!");
    header("Location: " . $_SERVER["REQUEST_URI"]);
    exit;
}

if (isset($_POST["s_remove"])) {
    $s_id = $_POST["s_id"];
    foreach ($s_id as $key => $val) {
        if (isset($val)) {
            LOG_INFO($db_link, "Remove subnet id: $val");
            delete_record($db_link, "subnets", "id=" . $val);
        }
    }
    header("Location: " . $_SERVER["REQUEST_URI"]);
    exit;
}

if (isset($_POST["clean_cache"])) {
    LOG_INFO($db_link, "Clean dns cache");
    run_sql($db_link,"DELETE FROM dns_cache");
    header("Location: " . $_SERVER["REQUEST_URI"]);
    exit;
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
            print "<tr><td align=right>Nagios - сбросить аварию &nbsp<input type=submit name='up_nagios' value='Сбросить'></td></tr>";
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
        print "<tr><td  align=right>Сбросить кэш&nbsp<input type=submit name='clean_cache' value='Выполнить'></td></tr>";
?>
<tr>
<td align=right><a href="ipcam.php">Управление портами</a></td>
</tr>
</table>
</form>
<?php
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/footer.php");
?>
