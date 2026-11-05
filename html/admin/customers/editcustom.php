<?php
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/auth.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/languages/" . HTML_LANG . ".php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/idfilter.php");

$msg_error = "";

// разрешаем только Администраторам
if (get_user_acl() <> 1) {
    header("Location: /admin");
    exit;
    }

$customer=get_record($db_link,'customers',"id=?", [$id]);

// the api key of a customer is a secret: the form of a record which has just been
// created shows it once, every next visit shows nothing, and an ordinary save does not
// touch the key at all
// the value of a fresh key is kept by the session of the one answer which shows it, the
// database holds only the hash, so a key can not be read back at all
$show_key = isset($_SESSION['api_key_once'][(int)$id]) ? (string)$_SESSION['api_key_once'][(int)$id] : '';
unset($_SESSION['api_key_once'][(int)$id]);

if (getPOST("edituser") !== null) {
    global $salt;

    $new = [];

    // Логин (макс. 20 символов)
    $new['login'] = substr(trim(getPOST("login", null, $customer['login'])), 0, 20);

    // Описание (макс. 100 символов)
    $new['description'] = substr(trim(getPOST("description", null, '')), 0, 100);

    // Пароль (если задан и не пустой)
    $pass = trim(getPOST("pass", null, ''));
    if ($pass !== '') {
        $new['password'] = password_hash($pass, PASSWORD_BCRYPT);
    }

    // the key is written only by the form of the creation, which marks itself with the
    // hidden field, so no ordinary save can change or blank the key of the api
    $key_form = getPOST("api_key_set", null, null) !== null;
    $api_key = trim((string)getPOST("api_key", null, ''));
    if ($key_form) {
        if (strlen($api_key) < 20 or strlen($api_key) > 64) {
            // the value can not be saved, the form asks for it once again
            $_SESSION['api_key_once'][(int)$id] = $api_key;
            $_SESSION[$page_url]['msg'] = WEB_customer_api_key_wrong;
            header("Location: " . $_SERVER["REQUEST_URI"]);
            exit;
        }
        $new['api_key_hash'] = api_key_hash($api_key);
        $new['api_key'] = null;
        // the key which has just been written is shown by the next answer of the form
        $_SESSION['api_key_once'][(int)$id] = $api_key;
    }

    // a lost key can not be read back, but it can be replaced: the new value is a
    // secret which the form shows once, right after the issue
    if (getPOST("api_key_regenerate", null, null) !== null) {
        $api_key = randomPassword(20);
        $new['api_key_hash'] = api_key_hash($api_key);
        $new['api_key'] = null;
        $_SESSION['api_key_once'][(int)$id] = $api_key;
        $_SESSION[$page_url]['msg'] = WEB_customer_api_key_renewed;
    }

    // Права доступа
    $new['rights'] = (int)getPOST("f_acl", null, 0);

    // Обновление записи
    update_record($db_link, "customers", "id = ?", $new, [$id]);

    header("Location: " . $_SERVER["REQUEST_URI"]);
    exit;
}

unset($_POST);

print_control_submenu($page_url);

require_once ($_SERVER['DOCUMENT_ROOT']."/inc/header.php");

?>

<div id="cont">
<?php
if (!empty($_SESSION[$page_url]['msg'])) {
    print "<div id='msg'>" . $_SESSION[$page_url]['msg'] . "</div>
";
    unset($_SESSION[$page_url]['msg']);
}
?>
<br><b><?php echo WEB_customer_titles; ?></b><br>
<form name="def" action="editcustom.php?id=<?php echo $id; ?>" method="post">
    <input type="hidden" name="id" value="<?php echo $id; ?>">
    <table class="data">
        <tr>
            <td><?php echo WEB_customer_login; ?></td>
            <td><input type="text" name="login" value="<?php print htmlspecialchars($customer['login']); ?>" size=20></td>
        </tr>
        <tr>
            <td><?php echo WEB_cell_description; ?></td>
            <td><input type="text" name="description" value="<?php print htmlspecialchars($customer['description']); ?>" size=50></td>
        </tr>
        <tr>
            <td><?php echo WEB_customer_password; ?></td>
            <td><input type="password" name="pass" value="" size=20></td>
        </tr>
        <?php if (!$show_key): ?>
        <tr>
            <td><?php echo WEB_customer_api_key; ?></td>
            <td colspan="3">
                <label><input type="checkbox" name="api_key_regenerate" value="1">&nbsp;<?php echo WEB_customer_api_key_regenerate; ?></label>
            </td>
        </tr>
        <?php endif; ?>
        <?php if ($show_key): ?>
        <tr>
            <td><?php echo WEB_customer_api_key; ?></td>
            <td>
                <input type="hidden" name="api_key_set" value="1">
                <input type="text" name="api_key" id="api_key" value="<?php print htmlspecialchars($show_key); ?>" size=50 maxlength=64>
                <button type="button" onclick="generateApiKey()">&#128260;</button>
                <br><?php echo WEB_customer_api_key_once; ?>
            </td>
        </tr>
        <?php endif; ?>
        <tr>
            <td><?php echo WEB_customer_mode; ?></td>
            <td><?php print_acl_select($db_link,'f_acl',$customer['rights']); ?></td>
        </tr>
        <tr>
            <td colspan=2>
                <input type="submit" name="edituser" value="<?php echo WEB_btn_save; ?>">
            </td>
        </tr>
    </table>
</form>

<script>
function generateApiKey() {
    const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
    let result = '';
    for (let i = 0; i < 20; i++) {
        result += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    document.getElementById('api_key').value = result;
}
</script>

<?php require_once ($_SERVER['DOCUMENT_ROOT']."/inc/footer.php"); ?>
