<?php
if (!defined("CONFIG")) die("Not defined");

// rule_type — целое число
$rule_type = getParam('rule_type', $page_url, 0, FILTER_VALIDATE_INT);
$_SESSION[$page_url]['rule_type'] = (int)$rule_type;

// rule_target — целое число  
$rule_target = getParam('rule_target', $page_url, 0, FILTER_VALIDATE_INT);
$_SESSION[$page_url]['rule_target'] = (int)$rule_target;

// search string — строка с очисткой
$f_rule = getParam('f_rule', $page_url, '');
$f_rule = trim($f_rule);
$f_rule = htmlspecialchars($f_rule, ENT_QUOTES, 'UTF-8');
$f_rule = str_replace('%', '', $f_rule); // удаляем % для безопасности в LIKE-запросах
$_SESSION[$page_url]['f_rule'] = $f_rule;
?>
