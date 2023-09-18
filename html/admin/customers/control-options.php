<?php

require_once($_SERVER['DOCUMENT_ROOT'] . "/inc/auth.php");
require_once($_SERVER['DOCUMENT_ROOT'] . "/inc/languages/" . HTML_LANG . ".php");

if (isset($_POST["remove"])) {
    if (!empty($_POST["f_id"])) {
        $fid = $_POST["f_id"];
        foreach ($fid as $option_id => $config_id) {
            $opt_def = get_record($db_link, "config_options", "id=" . $option_id);
            LOG_INFO($db_link, WEB_config_remove_option . " id: " . $config_id . " name: " . $opt_def["option_name"]);
            delete_record($db_link, "config", "id=" . $config_id);
        }
    }
    header("Location: " . $_SERVER["REQUEST_URI"]);
    exit;
}

if (isset($_POST['save'])) {
    if (!empty($_POST["f_id"])) {
        $fid = $_POST["f_id"];
        foreach ($fid as $option_id => $config_id) {
            $value = $_POST['f_config_value'][$config_id];
            $option = get_record_sql($db_link, "SELECT * FROM config_options WHERE id=" . $option_id);
            if (isset($value)) {
                $new['value'] = $value;
            }
            //crypt password
            if ($option_id == 29) { 
                $new['value']=crypt_string($value); 
            } else {
            //log event if changed option not password
                LOG_INFO($db_link, WEB_config_set_option . " id: " . $config_id . " name: " . $option["option_name"] . " = " . $value);
            }
            if (!empty($new)) {
                update_record($db_link, "config", "id=" . $config_id, $new);
            }
        }
    }
    header("Location: " . $_SERVER["REQUEST_URI"]);
    exit;
}

if (isset($_POST["create"])) {
    $new_option = $_POST["f_new_option"];
    if (isset($new_option)) {
        $new['option_id'] = $new_option;
        $new['value'] = get_option($db_link, $new_option);
        $opt_def = get_record($db_link, "config_options", "id=$new_option");
        LOG_INFO($db_link, WEB_config_add_option . " id: " . $new_option . " name: " . $opt_def["option_name"] . " = " . $new['value']);
        insert_record($db_link, "config", $new);
    }
    header("Location: " . $_SERVER["REQUEST_URI"]);
    exit;
}

unset($_POST);

fix_auth_rules($db_link);

require_once($_SERVER['DOCUMENT_ROOT'] . "/inc/header.php");

print_control_submenu($page_url);

?>
<div id="cont">
    <br>
    <form name="def" action="control-options.php" method="post">
        <br><b><?php print WEB_config_parameters; ?></b><br>
        <table class="data" width=800>
            <tr align="center">
                <td width=20><input type="checkbox" onClick="checkAll(this.checked);"></td>
                <td width=150><b><?php print WEB_config_option; ?></b></td>
                <td width=150><b><?php print WEB_config_value; ?></b></td>
                <td width=350><b><?php print WEB_msg_comment; ?></b></td>
                <td class="warn">
                    <input type="submit" onclick="return confirm('<?php print WEB_btn_delete; ?>?')" name="remove" value="<?php print WEB_btn_remove; ?>">
                </td>
                <td class="up">
                    <button name='save'><?php print WEB_btn_save; ?></button>
                </td>
            </tr>

            <?php
            $descr_field = "description." . HTML_LANG;
            $t_config = mysqli_query($db_link, "SELECT `config`.`id`,`option_id`,`option_name`,`value`,`type`,`" . $descr_field . "`,`min_value`,`max_value` FROM `config`,`config_options` WHERE
 `config`.`option_id`=`config_options`.`id` ORDER BY `option_name`");
            while ($row = mysqli_fetch_array($t_config)) {
                print "<tr align=center>\n";
                print "<td class=\"data\" style='padding:0'><input type=checkbox name=f_id[" . $row["option_id"] . "] value='" . $row['id'] . "'></td>\n";
                print "<td class=\"data\"><input type=\"text\" value='" . $row['option_name'] . "' disabled=true readonly=true></td>\n";
                $type = $row['type'];
                print "<td class=\"data\">";
                $option_value = $row['value'];
                if ($row['option_id'] == 29) {
                    $option_value = '********';
                }
                if (!isset($option_value)) {
                    $option_value = get_option($db_link, $row['option_id']);
                    set_option($db_link, $row['option_id'], $option_value);
                }
                if ($type == 'int') {
                    $min = '';
                    $max = '';
                    if (!empty($row['min_value']) or $row['min_value'] == 0) {
                        $min = "min=" . $row['min_value'];
                    }
                    if (!empty($row['max_value'])) {
                        $max = "max=" . $row['max_value'];
                    }
                    print "<input type=\"number\" name='f_config_value[" . $row['id'] . "]' value='$option_value' $min $max>";
                }
                if ($type == 'text') {
                    print "<input type=\"text\" name='f_config_value[" . $row['id'] . "]' value='$option_value' size=30>";
                }
                if ($type == 'bool') {
                    print_qa_select("f_config_value[" . $row['id'] . "]", $option_value);
                }
                print "</td>\n";
                print "<td colspan=3 class=\"data\">" . $row[$descr_field] . "</td>\n";
                print "</tr>\n";
            }
            ?>
            <tr>
                <td colspan=4 class="data"><?php print WEB_btn_add . " " . mb_strtolower(WEB_config_option) . ":&nbsp";
                                            print_option_select($db_link, "f_new_option"); ?></td>
                <td class="up" align="center"><input type="submit" name="create" value="<?php echo WEB_btn_add; ?>"></td>
                <td colspan=2 class="data"></td>
            </tr>
        </table>
    </form>
    <?php require_once($_SERVER['DOCUMENT_ROOT'] . "/inc/footer.php"); ?>