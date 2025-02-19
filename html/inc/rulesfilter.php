<?php
if (! defined("CONFIG")) die("Not defined");

//rule type
if (isset($_GET['rule_type'])) { $rule_type = $_GET["rule_type"] * 1; }
if (isset($_POST['rule_type'])) { $rule_type = $_POST["rule_type"] * 1; }
if (!isset($rule_type)) {
    if (isset($_SESSION[$page_url]['rule_type'])) { $rule_type = $_SESSION[$page_url]['rule_type']*1; }
    }
if (!isset($rule_type)) { $rule_type = 0; }
$_SESSION[$page_url]['rule_type']=$rule_type;

//rule target
if (isset($_GET['rule_target'])) { $rule_target = $_GET["rule_target"] * 1; }
if (isset($_POST['rule_target'])) { $rule_target = $_POST["rule_target"] * 1; }
if (!isset($rule_target)) {
    if (isset($_SESSION[$page_url]['rule_target'])) { $rule_target = $_SESSION[$page_url]['rule_target']*1; }
    }
if (!isset($rule_target)) { $rule_target = 0; }
$_SESSION[$page_url]['rule_target']=$rule_target;

//search string
if (isset($_GET['f_rule'])) { $f_rule = htmlspecialchars(trim($_GET["f_rule"]), ENT_QUOTES, 'UTF-8'); }
if (isset($_POST['f_rule'])) { $f_rule = htmlspecialchars(trim($_POST["f_rule"]), ENT_QUOTES, 'UTF-8'); }

if (!isset($f_rule)) {
    if (isset($_SESSION[$page_url]['f_rule'])) { $f_rule = $_SESSION[$page_url]['f_rule']; }
    }

if (!isset($f_rule)) { $f_rule = ''; }

$f_rule = str_replace('%', '', $f_rule);

$_SESSION[$page_url]['f_rule']=$f_rule;

?>
