<?php
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/auth.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/languages/" . HTML_LANG . ".php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/idfilter.php");

$msg_error = "";

$customer=get_record($db_link,'customers',"id=?", [$id]);

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

    // API-ключ (если длина > 20)
    $api_key = getPOST("api_key", null, randomPassword(20));
    if (strlen(trim($api_key)) > 20) {
        $new['api_key'] = substr(trim($api_key),0,20);
    }
    if (strlen(trim($api_key)) <20) {
        $new['api_key'] = $customer['api_key'];
    }
    $new['api_key'] = trim($api_key);

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
        <tr>
            <td><?php echo WEB_customer_api_key; ?></td>
            <td>
                <input type="text" name="api_key" id="api_key" value="<?php print htmlspecialchars($customer['api_key']); ?>" size=50>
                <!-- Кнопка перегенерации -->
                <button type="button" onclick="generateApiKey()">🔄</button>
            </td>
        </tr>
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
