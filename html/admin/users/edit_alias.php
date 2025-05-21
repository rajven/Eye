<?php

require_once ($_SERVER['DOCUMENT_ROOT']."/inc/auth.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/languages/" . HTML_LANG . ".php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/idfilter.php");

$msg_error = "";

$sSQL = "SELECT * FROM User_auth WHERE id=$id";
$auth_info = get_record_sql($db_link, $sSQL);

if (empty($auth_info['dns_name']) or $auth_info['deleted']) {
    header("Location: /admin/users/editauth.php?id=".$id);
    exit;
    }

run_sql($db_link,"DELETE FROM User_auth_alias WHERE auth_id in (SELECT id FROM User_auth WHERE deleted=1)");

if (isset($_POST["s_remove"])) {
    $s_id = $_POST["s_id"];
    foreach ($s_id as $key => $val) {
        if (isset($val)) {
            LOG_INFO($db_link, "Remove alias id: $val ".dump_record($db_link,'User_auth_alias','id='.$val));
            delete_record($db_link, "User_auth_alias", "id=" . $val);
        }
    }
    header("Location: " . $page_url);
    exit;
}

if (isset($_POST['s_save'])) {
    $len = is_array($_POST['s_save']) ? count($_POST['s_save']) : 0;
    $domain_zone = get_option($db_link, 33);
    $domain_zone = ltrim($domain_zone, '.');
    for ($i = 0; $i < $len; $i ++) {
        $save_id = intval($_POST['s_save'][$i]);
        $len_all = is_array($_POST['n_id']) ? count($_POST['n_id']) : 0;
        for ($j = 0; $j < $len_all; $j ++) {
            if (intval($_POST['n_id'][$j]) != $save_id) { continue; }
            $f_dnsname = trim($_POST['s_alias'][$j]);
            if (!empty($f_dnsname)) {
                $f_dnsname = preg_replace('/\.' . str_replace('.', '\.', $domain_zone) . '$/', '', $f_dnsname);
//                $f_dnsname = preg_replace('/\.$/','',$f_dnsname);
                $f_dnsname = preg_replace('/\s+/','-',$f_dnsname);
//                $f_dnsname = preg_replace('/\./','-',$f_dnsname);
                }
            if (empty($f_dnsname) or !checkValidHostname($f_dnsname) or !checkUniqHostname($db_link,$id,$f_dnsname)) { continue; }
            $new['alias'] = $f_dnsname;
            $new['description'] = trim($_POST['s_comment'][$j]);
            update_record($db_link, "User_auth_alias", "id='{$save_id}'", $new);
        }
    }
    header("Location: " . $page_url);
    exit;
}

if (isset($_POST["s_create"])) {
    $new_alias = $_POST["s_create_alias"];
    if (isset($new_alias)) {
        $f_dnsname = trim($new_alias);
        if (!empty($f_dnsname)) {
            $domain_zone = get_option($db_link, 33);
            $domain_zone = ltrim($domain_zone, '.');
            $f_dnsname = preg_replace('/\.' . str_replace('.', '\.', $domain_zone) . '$/', '', $f_dnsname);
//            $f_dnsname = preg_replace('/\.$/','',$f_dnsname);
            $f_dnsname = preg_replace('/\s+/','-',$f_dnsname);
//            $f_dnsname = preg_replace('/\./','-',$f_dnsname);
            }

        if (empty($f_dnsname) or !checkValidHostname($f_dnsname) or !checkUniqHostname($db_link,$id,$f_dnsname)) {
            $msg_error = "DNS $f_dnsname already exists at: ".searchHostname($db_link,$id,$f_dnsname)." Discard changes!";
            $_SESSION[$page_url]['msg'] = $msg_error;
            LOG_ERROR($db_link, $msg_error);
            header("Location: " . $_SERVER["REQUEST_URI"]);
            exit;
        }

        if (empty($f_dnsname)) { $f_dnsname = ''; }

        $new_rec['alias'] = $f_dnsname;
        $new_rec['auth_id'] = $id;
        LOG_INFO($db_link, "Create new alias $new_alias");
        insert_record($db_link, "User_auth_alias", $new_rec);
    }
    header("Location: " . $page_url);
    exit;
}

require_once ($_SERVER['DOCUMENT_ROOT']."/inc/header.php");


?>
<div id="cont">

<?php
    if (!empty($_SESSION[$page_url]['msg'])) {
        print '<div id="msg">' . $_SESSION[$page_url]['msg'] . '</div>';
        unset($_SESSION[$page_url]['msg']);
    }
?>

<br>
<form name="def" action="edit_alias.php?id=<?php echo $id; ?>" method="post">
<b><?php print WEB_user_alias_for."&nbsp"; print_url($auth_info['ip'],"/admin/users/editauth.php?id=$id"); ?></b> <br>
<table class="data">
<tr align="center">
	<td></td>
	<td width=30><b>id</b></td>
	<td><b><?php echo WEB_cell_name; ?></b></td>
	<td><b><?php echo WEB_cell_comment; ?></b></td>
	<td><input type="submit" onclick="return confirm('<?php echo WEB_msg_delete; ?>?')" name="s_remove" value="<?php echo WEB_btn_delete; ?>"></td>
</tr>
<?php
$t_User_auth_alias = get_records($db_link,'User_auth_alias',"auth_id=$id ORDER BY alias");
if (!empty($t_User_auth_alias)) {
foreach ( $t_User_auth_alias as $row ) {
    print "<tr align=center>\n";
    print "<td class=\"data\" style='padding:0'><input type=checkbox name=s_id[] value='{$row['id']}'></td>\n";
    print "<td class=\"data\"><input type=\"hidden\" name='n_id[]' value='{$row['id']}'>{$row['id']}</td>\n";
    print "<td class=\"data\"><input type=\"text\" name='s_alias[]' value='{$row['alias']}' pattern=\"^(([a-zA-Z0-9]|[a-zA-Z0-9][a-zA-Z0-9\-]*[a-zA-Z0-9])\.)*([A-Za-z0-9]|[A-Za-z0-9][A-Za-z0-9\-]*[A-Za-z0-9])$\"></td>\n";
    print "<td class=\"data\"><input type=\"text\" name='s_comment[]' value='{$row['description']}'></td>\n";
    print "<td class=\"data\"><button name='s_save[]' value='{$row['id']}'>".WEB_btn_save."</button></td>\n";
    print "</tr>\n";
}
}
?>
</table>
<div>
<?php echo WEB_user_dns_add_alias; ?>:
<input type="text" name='s_create_alias' value='' pattern="^(([a-zA-Z0-9]|[a-zA-Z0-9][a-zA-Z0-9\-]*[a-zA-Z0-9])\.)*([A-Za-z0-9]|[A-Za-z0-9][A-Za-z0-9\-]*[A-Za-z0-9])$">
<input type="submit" name="s_create" value="<?php echo WEB_btn_add; ?>">
</div>
</form>
<?php
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/footer.php");
?>
