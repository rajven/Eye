<?php
$default_displayed = 500;
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/auth.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/languages/" . HTML_LANG . ".php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/header.php");
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/oufilter.php");
$default_sort='login';
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/sortfilter.php");

$msg_error = "";

if (getPOST("restore") !== null) {
    $login = trim(getPOST("newlogin", null, ''));
    
    if ($login !== '') {
        // Проверка существования логина
        $lcount = get_count_records($db_link, "user_list", "LOWER(login) = LOWER(?)", [$login]);
        
        if ($lcount > 0) {
            $msg_error = WEB_cell_login . " " . $login . " " . $msg_exists . "!";
        } else {
            $new = ['login' => $login];
            // Определение OU
            if ($rou > 0) {
                $new['ou_id'] = $rou;
            } else {
                $rou = 3;
                $ou_exists = get_record_sql($db_link, "SELECT id FROM ou WHERE id = ?", [$rou]);
                if (empty($ou_exists)) {
                    $new['ou_id'] = $default_user_ou_id; // по умолчанию
                } else {
                    $new['ou_id'] = $rou;
                }
            }
            // Наследование настроек от OU
            $ou_info = get_record_sql($db_link, "SELECT * FROM ou WHERE id = ?", [$new['ou_id']]);
            if (!empty($ou_info)) {
                $new['enabled']           = isset($ou_info['enabled']) ? (int)$ou_info['enabled'] : 0;
                $new['queue_id']          = isset($ou_info['queue_id']) ? (int)$ou_info['queue_id'] : 0;
                $new['filter_group_id']   = isset($ou_info['filter_group_id']) ? (int)$ou_info['filter_group_id'] : 0;
            } else {
                // Если OU не найден — значения по умолчанию
                $new['enabled']           = 0;
                $new['queue_id']          = 0;
                $new['filter_group_id']   = 0;
            }
            $lid = insert_record($db_link, "user_list", $new);
            if (!empty($lid)) {
                header("Location: edituser.php?id=$lid");
                exit;
            }
        }
    }
    
    header("Location: " . $_SERVER["REQUEST_URI"]);
    exit;
}

?>
<div id="cont">

<?php

if (isset($_POST['search_str'])) { $f_search_str = trim($_POST['search_str']); }
if (!isset($f_search_str) and isset($_SESSION[$page_url]['search_str'])) { $f_search_str=$_SESSION[$page_url]['search_str']; }
if (!isset($f_search_str)) { $f_search_str=''; }
$_SESSION[$page_url]['search_str']=$f_search_str;

$f_search=replaceSpecialChars($f_search_str);

if ($msg_error) {
    print "<div id='msg'><b>$msg_error</b></div><br>\n";
    }
?>

<form id="filter" name="filter" action="index.php" method="post">
  <fieldset style="display: flex; align-items: center; gap: 10px; padding: 10px;">
    <legend><b><?php echo WEB_cell_ou; ?> —</b></legend>
    <?php print_ou_select($db_link, 'ou', $rou); ?>
    <span><?php echo WEB_rows_at_page; ?></span>
    <?php print_row_at_pages('rows', $displayed); ?>
    <label for="search_str" style="margin: 0;"><?php echo WEB_ips_search; ?>:</label>
    <input type="text" id="search_str" name="search_str" value="<?php echo $f_search_str; ?>" style="margin: 0;">
    <input id="btn_filter" name="btn_filter" type="submit" value="<?php echo WEB_btn_show; ?>" style="margin: 0;">
  </fieldset>
  <fieldset>
    <legend><?php echo WEB_new_user; ?></legend>
    <div>
      <input type="text" id="newlogin" name="newlogin" value="Unknown">
      <input type="submit" name="restore" value="<?php echo WEB_btn_add; ?>">
    </div>
  </fieldset>
</form>

<?php

$sort_url = "<a href=/admin/users/deleted.php?";

$params = [];
$conditions = ["deleted = 1"];

if ($rou != 0) {
    $conditions[] = "ou_id = ?";
    $params[] = (int)$rou;
}

if (isset($f_search_str) && strlen($f_search_str) > 0) {
    $search_pattern = '%' . $f_search . '%';
    $conditions[] = "(login LIKE ? OR description LIKE ?)";
    $params[] = $search_pattern;
    $params[] = $search_pattern;
}

// Защита от пустого WHERE
if (!empty($conditions)) {
    $whereClause = ' WHERE ' . implode(' AND ', $conditions);
} else {
    $whereClause = '';
}

// === 2. Безопасная сортировка (БЕЛЫЙ СПИСОК!) ===
$allowed_sort_fields = ['id', 'login', 'description' ];
$allowed_order = ['ASC', 'DESC'];

$sort_field = in_array($sort_field, $allowed_sort_fields, true) ? $sort_field : 'id';
$order = in_array(strtoupper($order), $allowed_order, true) ? strtoupper($order) : 'ASC';

// === 3. Подсчёт записей ===
$countSQL = "SELECT COUNT(*) FROM user_list $whereClause";
$count_records = (int)get_single_field($db_link, $countSQL, $params);

// === 4. Пагинация ===
$total = ceil($count_records / $displayed);
$page = max(1, min($page, $total));
$start = ($page - 1) * $displayed;

print_navigation($page_url, $page, $displayed, $count_records, $total);

// === 5. Запрос данных ===
$limit = (int)$displayed;
$offset = (int)$start;

$dataParams = array_merge($params, [$limit, $offset]);

$sSQL = "
    SELECT *
    FROM user_list U
    $whereClause
    ORDER BY $sort_field $order
    LIMIT ? OFFSET ?
";

$users = get_records_sql($db_link, $sSQL, $dataParams);

?>

<form id="def" name="def" >

<table class="data">
<tr align="center">
<td><input type="checkbox" onClick="checkAll(this.checked);"></td>
<td><b><?php print $sort_url . "sort=id&order=$new_order>id</a>"; ?></b></td>
<td><b><?php print $sort_url . "sort=login&order=$new_order>" . WEB_cell_login . "</a>"; ?></b></td>
<td><b><?php print $sort_url . "sort=description&order=$new_order>" . WEB_cell_description . "</a>"; ?></b></td>
</tr>
<?php


foreach ($users as $row) {
    $auth_customs = get_count_records($db_link,"user_auth","user_id=? AND deleted=1");
    $cl = "data";
    if (! $row['enabled']) {
        $cl = "off";
    }
    if ($row['blocked']) {
        $cl = "error";
    }
    if ($auth_customs > 0) {
	$cl = "custom";
	}
    print "<tr align=center>\n";
    print "<td class=\"$cl\" style='padding:0'><input type=checkbox name=fid[] value=".$row['id']."></td>\n";
    $cl_id = $cl;
    print "<td class=\"$cl_id\">".$row['id']."</td>\n";
    if (empty($row['login'])) { $row['login']=$row['id']; }
    print "<td class=\"$cl\" align=left>";
    print_url((string)$row['login'], 'edituser.php?id=' . (int)$row['id']);
    print "</td>\n";
    print "<td class=\"$cl\">".esc($row['description'])."</td>\n";
}
?>
</table>

<?php
print_navigation($page_url,$page,$displayed,$count_records,$total);
?>

</form>

<script>
document.getElementById('ou').addEventListener('change', function(event) {
  const buttonApply = document.getElementById('btn_filter');
  buttonApply.click();
});

document.getElementById('rows').addEventListener('change', function(event) {
  const buttonApply = document.getElementById('btn_filter');
  buttonApply.click();
});

</script>

<?php
require_once ($_SERVER['DOCUMENT_ROOT']."/inc/footer.php");
?>
